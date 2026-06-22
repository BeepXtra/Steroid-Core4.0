<?php
defined('_SECURED') or die('Restricted access');

class SMine {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function mine(string $address, string $privateKey, string $publicKey, int $maxIterations = 10000): ?array {
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
                // Found valid block — sign and submit
                $sigData  = $sblock->signingData($candidate);
                $candidate['signature'] = $swallet->sign($sigData, $privateKey);
                $candidate['public_key'] = $publicKey;

                $blockId = $sblock->add($candidate);
                SCore::log('info', "Block mined at height {$candidate['height']}", [
                    'hash'   => $hash,
                    'nonce'  => $nonce,
                    'miner'  => $address,
                ]);

                // Propagate to peers
                (new SPeers($this->db))->propagate('api/block/receive', $candidate);

                return ['block_id' => $blockId, 'hash' => $hash, 'nonce' => $nonce, 'height' => $candidate['height']];
            }
        }

        return null; // No block found in this round
    }

    private function computeArgon(array $block): string {
        $data = $block['height'] . $block['prev_hash'] . $block['generator']
              . $block['date'] . $block['nonce'];
        return password_hash($data, PASSWORD_ARGON2I, [
            'memory_cost' => ARGON_MEMORY,
            'time_cost'   => ARGON_TIME,
            'threads'     => ARGON_THREADS,
        ]);
    }

    private function checkDifficulty(string $hash, int $difficulty): bool {
        $target = (new SBlock($this->db))->computeArgon(['height'=>0,'prev_hash'=>'','generator'=>'','date'=>0,'nonce'=>'']);
        // Simple leading-zero check: difficulty/1000000 leading hex zeros
        $zeros  = (int)floor($difficulty / 1000000);
        return substr($hash, 0, $zeros) === str_repeat('0', $zeros);
    }

    public function getInfo(): array {
        $chain = new SChain($this->db);
        $top   = $chain->getTop();
        return [
            'height'     => $chain->getHeight(),
            'difficulty' => $top ? $top['difficulty'] : 1000000,
            'reward'     => (new SBlock($this->db))->getReward($chain->getHeight() + 1),
            'mempool'    => (int)$this->db->val("SELECT COUNT(*) FROM mempool"),
        ];
    }
}
