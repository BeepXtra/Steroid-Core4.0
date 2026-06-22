<?php
defined('_SECURED') or die('Restricted access');

class SBlock {
    private $db;
    private $cfg;
    private $tx;

    public function __construct($db, $cfg, $tx) {
        $this->db  = $db;
        $this->cfg = $cfg;
        $this->tx  = $tx;
    }

    public function getByHeight($height) {
        return $this->db->fetchOne('SELECT * FROM blocks WHERE height = ?', array($height));
    }

    public function getById($id) {
        return $this->db->fetchOne('SELECT * FROM blocks WHERE id = ?', array($id));
    }

    public function getLatest() {
        return $this->db->fetchOne('SELECT * FROM blocks ORDER BY height DESC LIMIT 1');
    }

    public function getHeight() {
        return (int)($this->db->fetchColumn('SELECT MAX(height) FROM blocks') ?: 0);
    }

    public function getLast($n) {
        return $this->db->fetchAll('SELECT * FROM blocks ORDER BY height DESC LIMIT ?', array($n));
    }

    public function create($generator, $nonce, $argon, $txs = array()) {
        $prev       = $this->getLatest();
        $height     = ($prev ? (int)$prev['height'] : 0) + 1;
        $difficulty = $this->getDifficulty();
        $date       = time();
        $block = array(
            'generator'    => $generator,
            'height'       => $height,
            'date'         => $date,
            'nonce'        => $nonce,
            'difficulty'   => $difficulty,
            'argon'        => $argon,
            'transactions' => count($txs),
        );
        $block['id']        = $this->blockId($block, $prev ? $prev['id'] : '0');
        $block['signature'] = $this->signBlock($block);
        return $block;
    }

    public function commit($block, $txs, $privateKeyPem) {
        $self = $this;
        $this->db->transaction(function($db) use ($block, $txs, $self) {
            $db->insert('blocks', array(
                'id'           => $block['id'],
                'generator'    => $block['generator'],
                'height'       => $block['height'],
                'date'         => $block['date'],
                'nonce'        => $block['nonce'],
                'signature'    => $block['signature'],
                'difficulty'   => $block['difficulty'],
                'argon'        => $block['argon'],
                'transactions' => count($txs),
            ));
            foreach ($txs as $tx) {
                $self->tx->execute($tx, $block['id'], $block['height']);
            }
            $self->tx->clearMempool(array_column($txs, 'id'));
            $self->distributeRewards($block);
        });
    }

    public function distributeRewards($block) {
        $totalReward = $this->calcBlockReward($block['height']);
        $mnShare     = round($totalReward * ($this->cfg->masternode_reward / 100), 8);
        $minerShare  = round($totalReward - $mnShare, 8);

        $this->db->execute(
            'UPDATE accounts SET balance = balance + ? WHERE id = ?',
            array($minerShare, $block['generator'])
        );

        $mn = $this->db->fetchOne(
            'SELECT public_key FROM masternode WHERE status = 1 AND blacklist = 0 ORDER BY last_won ASC LIMIT 1'
        );
        if ($mn) {
            $wallet = new SWallet($this->db);
            $mnAddr = $wallet->publicKeyToAddress($mn['public_key']);
            $this->db->execute(
                'UPDATE accounts SET balance = balance + ? WHERE id = ?',
                array($mnShare, $mnAddr)
            );
            $this->db->execute(
                'UPDATE masternode SET last_won = ? WHERE public_key = ?',
                array(time(), $mn['public_key'])
            );
            $this->db->insert('masternode_rewards', array(
                'public_key' => $mn['public_key'],
                'height'     => $block['height'],
                'reward'     => $mnShare,
                'date'       => $block['date'],
            ));
        }
    }

    public function calcBlockReward($height) {
        $halvings = floor($height / 525600);
        $reward   = $this->cfg->mining_reward / pow(2, $halvings);
        return max(round($reward, 8), 0.00000001);
    }

    public function getDifficulty() {
        $current = $this->db->fetchColumn('SELECT difficulty FROM blocks ORDER BY height DESC LIMIT 1');
        if (!$current) return str_pad('', 9, '0') . 'ffff0000000000000000000000000000000000000000000000000000';
        $height = $this->getHeight();
        if ($height % $this->cfg->difficulty_retarget !== 0) return $current;
        return $this->retargetDifficulty($height, $current);
    }

    private function retargetDifficulty($height, $current) {
        $window = $this->cfg->difficulty_retarget;
        $blocks = $this->db->fetchAll(
            'SELECT date FROM blocks WHERE height > ? ORDER BY height ASC LIMIT ?',
            array($height - $window, $window)
        );
        if (count($blocks) < 2) return $current;
        $actualTime = end($blocks)['date'] - reset($blocks)['date'];
        $targetTime = $this->cfg->block_time_target * $window;
        $ratio      = $targetTime / max($actualTime, 1);
        $ratio      = min(max($ratio, 0.25), 4.0);
        $target     = gmp_init($current, 16);
        $newTarget  = gmp_div_q(gmp_mul($target, gmp_init((int)($ratio * 1000))), gmp_init(1000));
        return str_pad(gmp_strval($newTarget, 16), strlen($current), '0', STR_PAD_LEFT);
    }

    public function validatePoW($block) {
        $hash = $this->argonHash($block);
        return $this->meetsTarget($hash, $block['difficulty']);
    }

    public function argonHash($block) {
        $data = implode('-', array(
            $block['generator'], $block['height'],
            $block['date'], $block['nonce'], $block['difficulty'],
        ));
        return password_hash($data, PASSWORD_ARGON2I, array(
            'time_cost'   => $this->cfg->argon2_time,
            'memory_cost' => $this->cfg->argon2_memory,
            'threads'     => $this->cfg->argon2_threads,
        ));
    }

    private function meetsTarget($hash, $difficulty) {
        $hashHex   = bin2hex(hash('sha256', $hash, true));
        $hashInt   = gmp_init($hashHex, 16);
        $targetInt = gmp_init($difficulty, 16);
        return gmp_cmp($hashInt, $targetInt) <= 0;
    }

    private function signBlock($block) {
        return hash('sha256', implode('-', array(
            $block['id'], $block['generator'], $block['height'], $block['date'],
        )));
    }

    public function blockId($block, $prevId) {
        return hash('sha256', implode('-', array(
            $prevId, $block['generator'], $block['height'],
            $block['date'], $block['nonce'], $block['difficulty'],
        )));
    }

    public function validate($block) {
        $errors = array();
        $prev   = $this->getLatest();
        $expectedHeight = ($prev ? (int)$prev['height'] : 0) + 1;
        if ((int)$block['height'] !== $expectedHeight) $errors[] = "Height mismatch: expected $expectedHeight";
        if (empty($block['generator']))                 $errors[] = 'Missing generator';
        if (!$this->validatePoW($block))                $errors[] = 'PoW failed';
        if ($this->getById($block['id']))               $errors[] = 'Duplicate block';
        return $errors;
    }
}
