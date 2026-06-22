<?php
defined('_SECURED') or die('Restricted access');

/**
 * STx — Transaction Engine. All versions v0–v111.
 */
class STx {
    private $db;
    private $cfg;
    private $wallet;

    const VERSION_TRANSFER       = 0;
    const VERSION_TRANSFER_MSG   = 1;
    const VERSION_ALIAS_REG      = 2;
    const VERSION_ALIAS_TRANSFER = 3;
    const VERSION_MN_REG         = 100;
    const VERSION_MN_DEREG       = 101;
    const VERSION_MN_PAUSE       = 102;
    const VERSION_MN_RESUME      = 103;
    const VERSION_COLD_STAKE     = 104;
    const VERSION_GOV_PROPOSAL   = 105;
    const VERSION_GOV_VOTE       = 106;
    const VERSION_GOV_CLOSE      = 107;
    const VERSION_ASSET_CREATE   = 110;
    const VERSION_ASSET_TRANSFER = 111;

    public function __construct($db, $cfg, $wallet) {
        $this->db     = $db;
        $this->cfg    = $cfg;
        $this->wallet = $wallet;
    }

    public function create($data) {
        $required = array('src','dst','val','fee','version','public_key','signature');
        foreach ($required as $f) {
            if (!isset($data[$f])) throw new InvalidArgumentException("STx: missing field $f");
        }
        return array(
            'id'         => $this->txId($data),
            'src'        => $data['src'],
            'dst'        => $data['dst'],
            'val'        => number_format((float)$data['val'], 8, '.', ''),
            'fee'        => number_format((float)$data['fee'], 8, '.', ''),
            'signature'  => $data['signature'],
            'version'    => (int)$data['version'],
            'message'    => substr(isset($data['message']) ? $data['message'] : '', 0, 256),
            'date'       => (int)(isset($data['date']) ? $data['date'] : time()),
            'public_key' => $data['public_key'],
        );
    }

    public function validate($tx) {
        $errors = array();
        $sigStr = $this->wallet->txSignatureString($tx);
        if (!$this->wallet->verify($sigStr, $tx['signature'], $tx['public_key']))
            $errors[] = 'Invalid signature';

        $derivedAddr = $this->wallet->publicKeyToAddress($tx['public_key']);
        if ($derivedAddr !== $tx['src'])
            $errors[] = 'Public key does not match src address';

        $minFee  = $this->calcFee((float)$tx['val']);
        if ((float)$tx['fee'] < $minFee)
            $errors[] = "Fee too low: minimum $minFee";

        $balance = (float)$this->wallet->getBalance($tx['src']);
        $total   = (float)$tx['val'] + (float)$tx['fee'];
        if ($balance < $total)
            $errors[] = "Insufficient balance: have $balance, need $total";

        if ($this->exists($tx['id']))
            $errors[] = 'Duplicate transaction';

        return array_merge($errors, $this->validateVersion($tx));
    }

    public function execute($tx, $blockId, $height) {
        $row = array(
            'id'         => $tx['id'],
            'block'      => $blockId,
            'height'     => $height,
            'src'        => $tx['src'],
            'dst'        => $tx['dst'],
            'val'        => $tx['val'],
            'fee'        => $tx['fee'],
            'signature'  => $tx['signature'],
            'version'    => $tx['version'],
            'message'    => isset($tx['message']) ? $tx['message'] : '',
            'date'       => $tx['date'],
            'public_key' => $tx['public_key'],
        );
        $this->db->insert('transactions', $row);
        $this->wallet->updateBalance($tx['src'], '-' . ((float)$tx['val'] + (float)$tx['fee']));

        if (in_array((int)$tx['version'], array(self::VERSION_TRANSFER, self::VERSION_TRANSFER_MSG))) {
            if (!$this->wallet->accountExists($tx['dst']))
                $this->wallet->createAccount($tx['dst'], '', $blockId);
            $this->wallet->updateBalance($tx['dst'], $tx['val']);
        }
        $this->executeVersion($tx, $blockId, $height);
    }

    public function addToMempool($tx, $peer = null) {
        $errors = $this->validate($tx);
        if (!empty($errors)) return false;
        $this->db->upsert('mempool', array(
            'id'         => $tx['id'],
            'height'     => 0,
            'src'        => $tx['src'],
            'dst'        => $tx['dst'],
            'val'        => $tx['val'],
            'fee'        => $tx['fee'],
            'signature'  => $tx['signature'],
            'version'    => $tx['version'],
            'message'    => isset($tx['message']) ? $tx['message'] : '',
            'public_key' => $tx['public_key'],
            'date'       => $tx['date'],
            'peer'       => $peer,
        ));
        return true;
    }

    public function getMempoolTxs($limit = 500) {
        return $this->db->fetchAll('SELECT * FROM mempool ORDER BY fee DESC, date ASC LIMIT ?', array($limit));
    }

    public function clearMempool($txIds) {
        if (empty($txIds)) return;
        $places = implode(',', array_fill(0, count($txIds), '?'));
        $this->db->execute("DELETE FROM mempool WHERE id IN ($places)", $txIds);
    }

    public function purgeExpiredMempool() {
        $cutoff = time() - $this->cfg->mempool_max_age;
        return $this->db->execute('DELETE FROM mempool WHERE date < ?', array($cutoff));
    }

    public function get($id) {
        return $this->db->fetchOne('SELECT * FROM transactions WHERE id = ?', array($id));
    }

    public function exists($id) {
        return (bool)$this->db->fetchColumn('SELECT COUNT(*) FROM transactions WHERE id = ?', array($id));
    }

    public function getByAddress($address, $limit = 50, $offset = 0) {
        return $this->db->fetchAll(
            'SELECT * FROM transactions WHERE src = ? OR dst = ? ORDER BY height DESC LIMIT ? OFFSET ?',
            array($address, $address, $limit, $offset)
        );
    }

    public function getByBlock($blockId) {
        return $this->db->fetchAll('SELECT * FROM transactions WHERE block = ? ORDER BY date ASC', array($blockId));
    }

    public function txId($tx) {
        $str = implode('-', array(
            isset($tx['src'])        ? $tx['src']        : '',
            isset($tx['dst'])        ? $tx['dst']        : '',
            isset($tx['val'])        ? $tx['val']        : '',
            isset($tx['fee'])        ? $tx['fee']        : '',
            isset($tx['version'])    ? $tx['version']    : '',
            isset($tx['date'])       ? $tx['date']       : '',
            isset($tx['public_key']) ? $tx['public_key'] : '',
        ));
        return hash('sha256', $str);
    }

    public function calcFee($val) {
        $fee = $val * ($this->cfg->fee_percent / 100);
        return max($fee, $this->cfg->min_fee);
    }

    private function validateVersion($tx) {
        $errors = array();
        switch ((int)$tx['version']) {
            case self::VERSION_ALIAS_REG:
                if (empty($tx['message'])) $errors[] = 'Alias required in message field';
                elseif (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $tx['message']))
                    $errors[] = 'Invalid alias format';
                elseif ($this->aliasExists($tx['message']))
                    $errors[] = 'Alias already taken';
                break;
            case self::VERSION_MN_REG:
                if ((float)$tx['val'] < $this->cfg->mn_collateral)
                    $errors[] = "MN collateral must be >= {$this->cfg->mn_collateral} STR";
                break;
        }
        return $errors;
    }

    private function executeVersion($tx, $blockId, $height) {
        switch ((int)$tx['version']) {
            case self::VERSION_ALIAS_REG:
                $this->db->execute('UPDATE accounts SET alias = ? WHERE id = ?', array($tx['message'], $tx['src']));
                break;
            case self::VERSION_MN_REG:
                $this->db->upsert('masternode', array(
                    'public_key' => $tx['public_key'], 'height' => $height,
                    'ip' => '', 'status' => 1,
                ), array('height','status'));
                break;
            case self::VERSION_MN_DEREG:
                $this->db->execute('DELETE FROM masternode WHERE public_key = ?', array($tx['public_key']));
                break;
            case self::VERSION_MN_PAUSE:
                $this->db->execute('UPDATE masternode SET status = 0 WHERE public_key = ?', array($tx['public_key']));
                break;
            case self::VERSION_MN_RESUME:
                $this->db->execute('UPDATE masternode SET status = 1 WHERE public_key = ?', array($tx['public_key']));
                break;
        }
    }

    private function aliasExists($alias) {
        return (bool)$this->db->fetchColumn('SELECT COUNT(*) FROM accounts WHERE alias = ?', array($alias));
    }
}
