<?php
defined('_SECURED') or die('Restricted access');

class SMine {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function mine($address, $privateKey, $publicKey, $maxIterations = 10000) {
        $sblock  = new SBlock($this->db);
        $swallet = new SWallet($this->db);

        $candidate = $sblock->candidate($address, $publicKey);
        $txPool    = (new STx($this->db))->getMempool(500);
        $candidate['transactions'] = $txPool;

        for ($nonce = 0; $nonce < $maxIterations; $nonce++) {
            $candidate['nonce'] = (string)$nonce;
            $argon = $this->computeArgon($candidate);
            $candidate['argon'] = $argon;
            $hash  = $sblock->computeHash($candidate);
            $candidate['hash'] = $hash;

            if ($this->checkDifficulty($hash, (int)$candidate['difficulty'])) {
                $sigData  = $sblock->signingData($candidate);
                $candidate['signature']  = $swallet->sign($sigData, $privateKey);
                $candidate['public_key'] = $publicKey;

                $blockId = $sblock->add($candidate);
                SCore::log('info', "Block mined at height {$candidate['height']}", array(
                    'hash'  => $hash,
                    'nonce' => $nonce,
                    'miner' => $address,
                ));

                (new SPeers($this->db))->propagate('api/block/receive', $candidate);

                return array(
                    'block_id' => $blockId,
                    'hash'     => $hash,
                    'nonce'    => $nonce,
                    'height'   => $candidate['height'],
                );
            }
        }
        return null;
    }

    private function computeArgon($block) {
        $data = $block['height'] . $block['prev_hash'] . $block['generator']
              . $block['date'] . $block['nonce'];
        return password_hash($data, PASSWORD_ARGON2I, array(
            'memory_cost' => ARGON_MEMORY,
            'time_cost'   => ARGON_TIME,
            'threads'     => ARGON_THREADS,
        ));
    }

    private function checkDifficulty($hash, $difficulty) {
        $zeros = (int)floor($difficulty / 1000000);
        if ($zeros === 0) return true;
        return substr($hash, 0, $zeros) === str_repeat('0', $zeros);
    }

    public function getInfo() {
        $chain = new SChain($this->db);
        $top   = $chain->getTop();
        return array(
            'height'     => $chain->getHeight(),
            'difficulty' => $top ? $top['difficulty'] : 1000000,
            'reward'     => (new SBlock($this->db))->getReward($chain->getHeight() + 1),
            'mempool'    => (int)$this->db->val("SELECT COUNT(*) FROM mempool"),
        );
    }
}
