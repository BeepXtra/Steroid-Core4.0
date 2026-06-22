<?php
defined('_SECURED') or die('Restricted access');

class SBlock {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    // ── Mining candidate ─────────────────────────────────────────────────────

    public function candidate(string $generator, string $publicKey): array {
        $chain   = new SChain($this->db);
        $top     = $chain->getTop();
        $height  = $top ? (int)$top['height'] + 1 : 1;
        $prevHash = $top ? $top['hash'] : str_repeat('0', 64);
        $difficulty = $this->getDifficulty($height);
        $reward     = $this->getReward($height);
        $mn         = $this->selectMasternode();

        return [
            'height'        => $height,
            'prev_hash'     => $prevHash,
            'generator'     => $generator,
            'public_key'    => $publicKey,
            'difficulty'    => $difficulty,
            'reward'        => $reward,
            'masternode_id' => $mn ? $mn['id'] : 0,
            'date'          => time(),
            'transactions'  => [],
        ];
    }

    // ── Add block ────────────────────────────────────────────────────────────

    public function add(array $block): int {
        $errors = $this->validate($block);
        if (!empty($errors)) {
            throw new RuntimeException('Block validation failed: ' . implode(', ', $errors));
        }

        return $this->db->transaction(function(Database $db) use ($block) {
            // Insert block record
            $blockId = $db->insert('blocks', [
                'height'        => $block['height'],
                'hash'          => $block['hash'],
                'prev_hash'     => $block['prev_hash'],
                'generator'     => $block['generator'],
                'signature'     => $block['signature'],
                'nonce'         => $block['nonce'],
                'difficulty'    => $block['difficulty'],
                'argon'         => $block['argon'],
                'transactions'  => count($block['transactions'] ?? []),
                'date'          => $block['date'],
                'reward'        => $block['reward'],
                'masternode_id' => $block['masternode_id'] ?? 0,
                'version'       => $block['version'] ?? 1,
            ]);

            // Process transactions
            $tx = new STx($db);
            $sigs = [];
            foreach ($block['transactions'] ?? [] as $t) {
                $tx->confirm($t, $blockId, $block['height']);
                $sigs[] = $t['signature'];
            }
            if (!empty($sigs)) $tx->removeFromMempool($sigs);

            // Reward payout
            $this->payReward($block, $blockId, $db);

            // Update masternode last_won
            if ($block['masternode_id']) {
                $db->update('masternode', ['last_won' => time()], 'id=?', [$block['masternode_id']]);
            }

            return $blockId;
        });
    }

    // ── Validate block ───────────────────────────────────────────────────────

    public function validate(array $block): array {
        $errors = [];
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

        // Argon2 PoW check
        if (!$this->checkPoW($block)) {
            $errors[] = 'Proof-of-work failed';
        }

        // Signature check
        $wallet  = new SWallet($this->db);
        $sigData = $this->signingData($block);
        if (!$wallet->verify($sigData, $block['signature'], $block['public_key'])) {
            $errors[] = 'Block signature invalid';
        }

        return $errors;
    }

    // ── PoW ──────────────────────────────────────────────────────────────────

    public function checkPoW(array $block): bool {
        $target = $this->hashTarget((int)$block['difficulty']);
        $hash   = $this->computeArgon($block);
        return strcmp($hash, $target) <= 0;
    }

    public function computeArgon(array $block): string {
        $data = $block['height'] . $block['prev_hash'] . $block['generator']
              . $block['date'] . $block['nonce'];
        $hash = password_hash($data, PASSWORD_ARGON2I, [
            'memory_cost' => ARGON_MEMORY,
            'time_cost'   => ARGON_TIME,
            'threads'     => ARGON_THREADS,
        ]);
        return hash('sha256', $hash);
    }

    private function hashTarget(int $difficulty): string {
        // Returns a hex string: 64 hex chars with leading zeros proportional to difficulty
        $zeros  = (int)floor($difficulty / 16);
        $target = str_repeat('0', min($zeros, 63)) . str_repeat('f', 64 - min($zeros, 63));
        return $target;
    }

    // ── Reward ───────────────────────────────────────────────────────────────

    public function getReward(int $height): string {
        // Halving every 210,000 blocks
        $halvings = (int)floor($height / 210000);
        $reward   = 50.0 / pow(2, $halvings);
        return number_format($reward, 8, '.', '');
    }

    private function payReward(array $block, int $blockId, Database $db): void {
        $reward     = (float)$block['reward'];
        $mnPct      = MASTERNODE_REWARD_PCT / 100;
        $minerPct   = 1 - $mnPct;
        $mnReward   = number_format($reward * $mnPct, 8, '.', '');
        $minerReward = number_format($reward * $minerPct, 8, '.', '');

        $wallet = new SWallet($db);

        // Miner reward
        $wallet->getOrCreateAccount($block['generator'], $block['public_key'] ?? '');
        $wallet->creditBalance($block['generator'], $minerReward);

        // Masternode reward
        if ($block['masternode_id']) {
            $mn = $db->row("SELECT address FROM masternode WHERE id=?", [$block['masternode_id']]);
            if ($mn) {
                $wallet->getOrCreateAccount($mn['address'], '');
                $wallet->creditBalance($mn['address'], $mnReward);
            }
        }
    }

    // ── Difficulty ───────────────────────────────────────────────────────────

    public function getDifficulty(int $height): int {
        if ($height <= DIFFICULTY_RETARGET) return 1000000;

        $last = $this->db->row(
            "SELECT difficulty, date FROM blocks ORDER BY height DESC LIMIT 1"
        );
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

    // ── Masternode selector ──────────────────────────────────────────────────

    private function selectMasternode(): ?array {
        return $this->db->row(
            "SELECT * FROM masternode WHERE status=1 ORDER BY last_won ASC LIMIT 1"
        );
    }

    // ── Signing data ─────────────────────────────────────────────────────────

    public function signingData(array $block): string {
        return implode(':', [
            $block['height'],
            $block['prev_hash'],
            $block['generator'],
            $block['date'],
            $block['nonce'],
        ]);
    }

    public function computeHash(array $block): string {
        return hash('sha256', $this->signingData($block) . $block['argon']);
    }

    public function getByHeight(int $height): ?array {
        return $this->db->row("SELECT * FROM blocks WHERE height=?", [$height]);
    }

    public function getByHash(string $hash): ?array {
        return $this->db->row("SELECT * FROM blocks WHERE hash=?", [$hash]);
    }

    public function getLatest(int $limit = 10): array {
        return $this->db->rows(
            "SELECT * FROM blocks ORDER BY height DESC LIMIT ?", [$limit]
        );
    }
}
