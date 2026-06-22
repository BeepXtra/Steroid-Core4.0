<?php
defined('_SECURED') or die('Restricted access');

/**
 * SChain — Blockchain state manager.
 * Handles chain sync, fork resolution, peer block fetching, and chain validation.
 */
class SChain {
    private Database $db;
    private Config   $cfg;

    public function __construct(Database $db, Config $cfg) {
        $this->db  = $db;
        $this->cfg = $cfg;
    }

    // ─── Chain State ─────────────────────────────────────────

    public function getHeight(): int {
        return (int)($this->db->fetchColumn('SELECT MAX(height) FROM blocks') ?? 0);
    }

    public function getTopBlock(): ?array {
        return $this->db->fetchOne('SELECT * FROM blocks ORDER BY height DESC LIMIT 1');
    }

    public function getBlockByHeight(int $height): ?array {
        return $this->db->fetchOne('SELECT * FROM blocks WHERE height = ?', [$height]);
    }

    public function getBlockById(string $id): ?array {
        return $this->db->fetchOne('SELECT * FROM blocks WHERE id = ?', [$id]);
    }

    public function getRange(int $from, int $to): array {
        return $this->db->fetchAll(
            'SELECT * FROM blocks WHERE height BETWEEN ? AND ? ORDER BY height ASC',
            [$from, $to]
        );
    }

    // ─── Supply ──────────────────────────────────────────────

    public function getTotalSupply(): string {
        return $this->db->fetchColumn('SELECT COALESCE(SUM(balance),0) FROM accounts') ?? '0';
    }

    public function getCirculatingSupply(): string {
        return $this->getTotalSupply(); // all non-locked balances
    }

    // ─── Sync ────────────────────────────────────────────────

    /**
     * Attempt to sync with a peer.
     * Returns number of new blocks added.
     */
    public function syncWithPeer(string $peerHostname): int {
        $myHeight   = $this->getHeight();
        $peerStatus = $this->fetchPeerStatus($peerHostname);
        if (!$peerStatus || (int)$peerStatus['height'] <= $myHeight) return 0;

        $added = 0;
        $from  = $myHeight + 1;
        $to    = min((int)$peerStatus['height'], $myHeight + 500); // batch 500

        $blocks = $this->fetchBlocksFromPeer($peerHostname, $from, $to);
        foreach ($blocks as $blockData) {
            if ($this->acceptBlock($blockData)) {
                $added++;
            }
        }
        return $added;
    }

    public function acceptBlock(array $blockData): bool {
        // Validate then insert (full validation in SBlock — here we do chain-level check)
        $height   = (int)$blockData['height'];
        $existing = $this->getBlockByHeight($height);
        if ($existing) {
            // Fork: keep the block with lower id hash (deterministic)
            if ($existing['id'] <= $blockData['id']) return false;
            $this->revertToHeight($height - 1);
        }

        $this->db->insert('blocks', [
            'id'           => $blockData['id'],
            'generator'    => $blockData['generator'],
            'height'       => $blockData['height'],
            'date'         => $blockData['date'],
            'nonce'        => $blockData['nonce'],
            'signature'    => $blockData['signature'],
            'difficulty'   => $blockData['difficulty'],
            'argon'        => $blockData['argon'],
            'transactions' => $blockData['transactions'] ?? 0,
        ]);
        return true;
    }

    // ─── Fork Resolution ─────────────────────────────────────

    public function revertToHeight(int $height): void {
        // CASCADE delete via FK will remove transactions too
        $this->db->execute('DELETE FROM blocks WHERE height > ?', [$height]);
    }

    // ─── Peer Fetch Helpers ──────────────────────────────────

    private function fetchPeerStatus(string $hostname): ?array {
        $url = rtrim($hostname, '/') . '/api/status';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->cfg->peer_timeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$body) return null;
        return json_decode($body, true);
    }

    private function fetchBlocksFromPeer(string $hostname, int $from, int $to): array {
        $url = rtrim($hostname, '/') . "/api/blocks?from={$from}&to={$to}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$body) return [];
        $data = json_decode($body, true);
        return $data['blocks'] ?? [];
    }

    // ─── Stats ───────────────────────────────────────────────

    public function getStats(): array {
        $top = $this->getTopBlock();
        return [
            'height'            => $this->getHeight(),
            'top_block'         => $top['id'] ?? null,
            'top_block_date'    => $top['date'] ?? null,
            'total_supply'      => $this->getTotalSupply(),
            'difficulty'        => $top['difficulty'] ?? null,
        ];
    }
}
