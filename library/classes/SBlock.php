<?php
defined('_SECURED') or die('Restricted access');

class SBlock {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function candidate($generator, $publicKey) {
        $chain      = new SChain($this->db);
        $top        = $chain->getTop();
        $height     = $top ? (int)$top['height'] + 1 : 1;
        $prevHash   = $top ? $top['hash'] : str_repeat('0', 64);
        $difficulty = $this->getDifficulty($height);
        $reward     = $this->getReward($height);
        $mn         = $this->selectMasternode();
        return array(
            'height'        => $height,
            'prev_hash'     => $prevHash,
            'generator'     => $generator,
            'public_key'    => $publicKey,
            'difficulty'    => $difficulty,
            'reward'        => $reward,
            'masternode_id' => $mn ? $mn['id'] : 0,
            'date'          => time(),
            'transactions'  => array(),
        );
    }

    public function add($block) {
        $errors = $this->validate($block);
        if (!empty($errors)) {
            throw new RuntimeException('Block validation failed: ' . implode(', ', $errors));
        }
        $self = $this;
        return $this->db->transaction(function($db) use ($block, $self) {
            $blockId = $db->insert('blocks', array(
                'height'        => $block['height'],
                'hash'          => $block['hash'],
                'prev_hash'     => $block['prev_hash'],
                'generator'     => $block['generator'],
                'signature'     => $block['signature'],
                'nonce'         => $block['nonce'],
                'difficulty'    => $block['difficulty'],
                'argon'         => $block['argon'],
                'transactions'  => count(isset($block['transactions']) ? $block['transactions'] : array()),
                'date'          => $block['date'],
                'reward'        => $block['reward'],
                'masternode_id' => isset($block['masternode_id']) ? $block['masternode_id'] : 0,
                'version'       => isset($block['version']) ? $block['version'] : 1,
            ));

            $tx   = new STx($db);
            $sigs = array();
            foreach ((isset($block['transactions']) ? $block['transactions'] : array()) as $t) {
                $tx->confirm($t, $blockId, $block['height']);
                $sigs[] = $t['signature'];
            }
            if (!empty($sigs)) $tx->removeFromMempool($sigs);
            $self->payReward($block, $blockId, $db);

            if ($block['masternode_id']) {
                $db->update('masternode', array('last_won' => time()), 'id=?', array($block['masternode_id']));
            }
            return $blockId;
        });
    }

    public function validate($block) {
        $errors = array();
        $chain  = new SChain($this->db);
        $top    = $chain->getTop();
        $expectedHeight = $top ? (int)$top['height'] + 1 : 1;

        if ((int)$block['height'] !== $expectedHeight) {
            $errors[] = "Height mismatch: expected $expectedHeight got {$block['height']}";
        }
        $expectedPrev = $top ? $top['hash'] : str_repeat('0', 64);
        if ($block['prev_hash'] !== $expectedPrev) {
            $errors[] = 'Previous hash mismatch';
        }
        if (!$this->checkPoW($block)) {
            $errors[] = 'Proof-of-work failed';
        }
        $wallet  = new SWallet($this->db);
        $sigData = $this->signingData($block);
        if (!$wallet->verify($sigData, $block['signature'], $block['public_key'])) {
            $errors[] = 'Block signature invalid';
        }
        return $errors;
    }

    public function checkPoW($block) {
        $target = $this->hashTarget((int)$block['difficulty']);
        $hash   = $this->computeArgon($block);
        return strcmp($hash, $target) <= 0;
    }

    public function computeArgon($block) {
        $data = $block['height'] . $block['prev_hash'] . $block['generator']
              . $block['date'] . $block['nonce'];
        $hash = password_hash($data, PASSWORD_ARGON2I, array(
            'memory_cost' => ARGON_MEMORY,
            'time_cost'   => ARGON_TIME,
            'threads'     => ARGON_THREADS,
        ));
        return hash('sha256', $hash);
    }

    private function hashTarget($difficulty) {
        $zeros  = (int)floor($difficulty / 16);
        $zeros  = min($zeros, 63);
        return str_repeat('0', $zeros) . str_repeat('f', 64 - $zeros);
    }

    public function getReward($height) {
        $halvings = (int)floor($height / 210000);
        $reward   = 50.0 / pow(2, $halvings);
        return number_format($reward, 8, '.', '');
    }

    public function payReward($block, $blockId, $db) {
        $reward      = (float)$block['reward'];
        $mnPct       = MASTERNODE_REWARD_PCT / 100;
        $mnReward    = number_format($reward * $mnPct, 8, '.', '');
        $minerReward = number_format($reward * (1 - $mnPct), 8, '.', '');

        $wallet = new SWallet($db);
        $wallet->getOrCreateAccount($block['generator'], isset($block['public_key']) ? $block['public_key'] : '');
        $wallet->creditBalance($block['generator'], $minerReward);

        if ($block['masternode_id']) {
            $mn = $db->row("SELECT address FROM masternode WHERE id=?", array($block['masternode_id']));
            if ($mn) {
                $wallet->getOrCreateAccount($mn['address'], '');
                $wallet->creditBalance($mn['address'], $mnReward);
            }
        }
    }

    public function getDifficulty($height) {
        if ($height <= DIFFICULTY_RETARGET) return 1000000;
        $last = $this->db->row("SELECT difficulty, date FROM blocks ORDER BY height DESC LIMIT 1");
        $prev = $this->db->row(
            "SELECT date FROM blocks ORDER BY height DESC LIMIT 1 OFFSET " . DIFFICULTY_RETARGET
        );
        if (!$last || !$prev) return 1000000;
        $elapsed  = max(1, (int)$last['date'] - (int)$prev['date']);
        $target   = BLOCK_TIME * DIFFICULTY_RETARGET;
        $ratio    = $target / $elapsed;
        $newDiff  = (int)((int)$last['difficulty'] * $ratio);
        return max(1000, min($newDiff, 999999999));
    }

    private function selectMasternode() {
        return $this->db->row(
            "SELECT * FROM masternode WHERE status=1 ORDER BY last_won ASC LIMIT 1"
        );
    }

    public function signingData($block) {
        return implode(':', array(
            $block['height'],
            $block['prev_hash'],
            $block['generator'],
            $block['date'],
            $block['nonce'],
        ));
    }

    public function computeHash($block) {
        return hash('sha256', $this->signingData($block) . $block['argon']);
    }

    public function getByHeight($height) {
        return $this->db->row("SELECT * FROM blocks WHERE height=?", array($height));
    }

    public function getByHash($hash) {
        return $this->db->row("SELECT * FROM blocks WHERE hash=?", array($hash));
    }

    public function getLatest($limit = 10) {
        return $this->db->rows("SELECT * FROM blocks ORDER BY height DESC LIMIT ?", array($limit));
    }
}
