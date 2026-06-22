<?php
defined('_SECURED') or die('Restricted access');

/**
 * SMine — Mining engine.
 * Argon2id nonce search, mempool collection, block submission.
 */
class SMine {
    private Database $db;
    private Config   $cfg;
    private SBlock   $block;
    private SChain   $chain;

    public function __construct(Database $db, Config $cfg, SBlock $block, SChain $chain) {
        $this->db    = $db;
        $this->cfg   = $cfg;
        $this->block = $block;
        $this->chain = $chain;
    }

    // ─── Mine One Block ──────────────────────────────────────

    /**
     * Attempt to mine a single block.
     * $generator = miner's wallet address
     * $privateKey = miner's private key PEM for signing
     * Returns committed block array on success, null if no solution found within $maxAttempts.
     */
    public function mine(string $generator, string $privateKeyPem, int $maxAttempts = 100000): ?array {
        $difficulty = $this->block->getDifficulty();
        $txs        = (new STx($this->db, $this->cfg, new SWallet($this->db)))->getMempoolTxs(500);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $nonce = $this->generateNonce();
            $candidate = [
                'generator'  => $generator,
                'height'     => $this->chain->getHeight() + 1,
                'date'       => time(),
                'nonce'      => $nonce,
                'difficulty' => $difficulty,
                'argon'      => '',
                'transactions'=> count($txs),
            ];
            $argon = $this->block->argonHash($candidate);
            $candidate['argon'] = $argon;

            if ($this->block->validatePoW($candidate)) {
                $candidate['id']        = $this->block->blockId($candidate, $this->chain->getTopBlock()['id'] ?? '0');
                $candidate['signature'] = hash('sha256', implode('-', [
                    $candidate['id'], $candidate['generator'],
                    $candidate['height'], $candidate['date'],
                ]));
                $this->block->commit($candidate, $txs, $privateKeyPem);
                return $candidate;
            }
        }
        return null; // No solution found in this batch
    }

    // ─── Continuous Mining Loop (CLI) ────────────────────────

    public function loop(string $generator, string $privateKeyPem): void {
        while (true) {
            $result = $this->mine($generator, $privateKeyPem);
            if ($result) {
                echo '[' . date('Y-m-d H:i:s') . '] Block #' . $result['height'] . ' mined: ' . $result['id'] . PHP_EOL;
            }
            // Sync chain periodically
            $this->syncPeers();
            usleep(100000); // 0.1s breathing room
        }
    }

    // ─── Peer Sync ───────────────────────────────────────────

    private function syncPeers(): void {
        $peers = $this->db->fetchAll(
            'SELECT hostname FROM peers WHERE blacklisted = 0 AND fails < 5 ORDER BY RAND() LIMIT 3'
        );
        $chain = new SChain($this->db, $this->cfg);
        foreach ($peers as $peer) {
            try {
                $chain->syncWithPeer($peer['hostname']);
            } catch (Throwable $e) {
                // Log and continue
                error_log('SMine sync error: ' . $e->getMessage());
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function generateNonce(): string {
        return bin2hex(random_bytes(32));
    }

    // ─── Stats ───────────────────────────────────────────────

    public function getHashrate(int $blocks = 100): float {
        $recent = $this->db->fetchAll(
            'SELECT date FROM blocks ORDER BY height DESC LIMIT ?',
            [$blocks]
        );
        if (count($recent) < 2) return 0.0;
        $span = reset($recent)['date'] - end($recent)['date'];
        if ($span <= 0) return 0.0;
        return count($recent) / $span; // blocks per second
    }
}
