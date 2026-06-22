<?php
defined('_SECURED') or die('Restricted access');

class STx {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function build($params) {
        $required = array('type', 'src', 'dst', 'val', 'public_key', 'signature');
        foreach ($required as $field) {
            if (!isset($params[$field])) throw new InvalidArgumentException("Missing field: $field");
        }
        $fee = $this->calcFee((float)$params['val'], (int)$params['type']);
        return array(
            'type'       => (int)$params['type'],
            'src'        => $params['src'],
            'dst'        => $params['dst'],
            'val'        => number_format((float)$params['val'], 8, '.', ''),
            'fee'        => number_format($fee, 8, '.', ''),
            'signature'  => $params['signature'],
            'message'    => isset($params['message']) ? $params['message'] : '',
            'version'    => isset($params['version']) ? (int)$params['version'] : 1,
            'date'       => isset($params['date']) ? $params['date'] : time(),
            'public_key' => $params['public_key'],
        );
    }

    public function calcFee($amount, $type) {
        if (in_array($type, array(TX_REWARD, TX_GOV_RESULT))) return 0.0;
        $fee = $amount * FEE_PCT;
        return max($fee, (float)MIN_FEE);
    }

    public function validate($tx, $mempool = true) {
        $errors = array();
        $wallet  = new SWallet($this->db);
        $sigData = $this->signingData($tx);
        if (!$wallet->verify($sigData, $tx['signature'], $tx['public_key'])) {
            $errors[] = 'Invalid signature';
        }

        if ($tx['type'] !== TX_REWARD) {
            $balance = (float)$wallet->getBalance($tx['src']);
            $pending = $mempool ? (float)$this->pendingOut($tx['src']) : 0.0;
            $needed  = (float)$tx['val'] + (float)$tx['fee'];
            if (($balance - $pending) < $needed) {
                $errors[] = 'Insufficient balance';
            }
        }

        if ($mempool && $this->db->exists('mempool', 'signature=?', array($tx['signature']))) {
            $errors[] = 'Transaction already in mempool';
        }

        $typeErrors = $this->validateType($tx);
        $errors = array_merge($errors, $typeErrors);
        return $errors;
    }

    private function validateType($tx) {
        $errors = array();
        switch ((int)$tx['type']) {
            case TX_ALIAS_SET:
                if (empty($tx['message'])) $errors[] = 'Alias cannot be empty';
                break;
            case TX_MN_REGISTER:
                $collateral = (float)MASTERNODE_COLLATERAL;
                if ((float)$tx['val'] < $collateral) {
                    $errors[] = "Masternode collateral must be >= $collateral";
                }
                break;
            case TX_ASSET_CREATE:
                $msg = json_decode($tx['message'], true);
                if (!$msg || empty($msg['name'])) $errors[] = 'Asset name required';
                break;
        }
        return $errors;
    }

    public function addToMempool($tx, $peer = '') {
        $errors = $this->validate($tx);
        if (!empty($errors)) return false;
        $this->db->insert('mempool', array(
            'type'       => $tx['type'],
            'src'        => $tx['src'],
            'dst'        => $tx['dst'],
            'val'        => $tx['val'],
            'fee'        => $tx['fee'],
            'signature'  => $tx['signature'],
            'message'    => isset($tx['message']) ? $tx['message'] : '',
            'version'    => isset($tx['version']) ? $tx['version'] : 1,
            'date'       => isset($tx['date']) ? $tx['date'] : time(),
            'public_key' => $tx['public_key'],
            'peer'       => $peer,
        ));
        return true;
    }

    public function getMempool($limit = 500) {
        return $this->db->rows("SELECT * FROM mempool ORDER BY fee DESC, date ASC LIMIT ?", array($limit));
    }

    public function cleanMempool() {
        $cutoff = time() - MAX_MEMPOOL_AGE;
        return $this->db->delete('mempool', 'date<?', array($cutoff));
    }

    public function removeFromMempool($signatures) {
        if (empty($signatures)) return;
        $placeholders = implode(',', array_fill(0, count($signatures), '?'));
        $this->db->query("DELETE FROM mempool WHERE signature IN ($placeholders)", $signatures);
    }

    public function confirm($tx, $blockId, $height) {
        $self = $this;
        $this->db->transaction(function($db) use ($tx, $blockId, $height, $self) {
            $db->insert('transactions', array(
                'block'      => $blockId,
                'height'     => $height,
                'type'       => $tx['type'],
                'src'        => $tx['src'],
                'dst'        => $tx['dst'],
                'val'        => $tx['val'],
                'fee'        => $tx['fee'],
                'signature'  => $tx['signature'],
                'message'    => isset($tx['message']) ? $tx['message'] : '',
                'version'    => isset($tx['version']) ? $tx['version'] : 1,
                'date'       => isset($tx['date']) ? $tx['date'] : time(),
                'public_key' => $tx['public_key'],
            ));
            $wallet = new SWallet($db);
            $self->applyEffect($tx, $wallet, $height, $db);
        });
    }

    public function applyEffect($tx, $wallet, $height, $db = null) {
        if ($db === null) $db = $this->db;
        $type = (int)$tx['type'];

        if (in_array($type, array(TX_REWARD, TX_TRANSFER, TX_ALIAS_PAY))) {
            if ($tx['src'] !== '0') {
                $wallet->debitBalance($tx['src'], bcadd($tx['val'], $tx['fee'], 8));
            }
            $wallet->getOrCreateAccount($tx['dst'], '');
            $wallet->creditBalance($tx['dst'], $tx['val']);
        }

        if ($type === TX_ALIAS_SET) {
            $wallet->setAlias($tx['src'], $tx['message']);
        }

        if ($type === TX_MN_REGISTER) {
            (new SMasternode($db))->register($tx, $height);
        }
        if ($type === TX_MN_PAUSE)        (new SMasternode($db))->pause($tx['src']);
        if ($type === TX_MN_RESUME)       (new SMasternode($db))->resume($tx['src']);
        if ($type === TX_MN_BLACKLIST)    (new SMasternode($db))->blacklist($tx['src']);
        if ($type === TX_MN_UNBLACKLIST)  (new SMasternode($db))->unblacklist($tx['src']);

        if (in_array($type, array(TX_ASSET_CREATE, TX_ASSET_TRANSFER, TX_ASSET_BID, TX_ASSET_ASK,
                                  TX_ASSET_CANCEL, TX_ASSET_INFLATE, TX_ASSET_DIVIDEND, TX_ASSET_FILL))) {
            (new SAssets($db))->applyTx($tx, $height);
        }

        if (in_array($type, array(TX_GOV_PROPOSAL, TX_GOV_VOTE, TX_GOV_RESULT))) {
            (new SGovernance($db))->applyTx($tx, $height);
        }
    }

    public function signingData($tx) {
        return implode(':', array(
            $tx['type'],
            $tx['src'],
            $tx['dst'],
            $tx['val'],
            $tx['fee'],
            isset($tx['message']) ? $tx['message'] : '',
            $tx['date'],
        ));
    }

    private function pendingOut($address) {
        $row = $this->db->row(
            "SELECT COALESCE(SUM(val+fee),0) AS t FROM mempool WHERE src=?", array($address)
        );
        return (float)(isset($row['t']) ? $row['t'] : 0);
    }

    public function getTx($signature) {
        return $this->db->row("SELECT * FROM transactions WHERE signature=?", array($signature));
    }

    public function getByAddress($address, $limit = 50) {
        return $this->db->rows(
            "SELECT * FROM transactions WHERE src=? OR dst=? ORDER BY id DESC LIMIT ?",
            array($address, $address, $limit)
        );
    }
}
