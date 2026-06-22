<?php
defined('_SECURED') or die('Restricted access');

class SMasternode {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    // Status constants
    const STATUS_INACTIVE    = 0;
    const STATUS_ACTIVE      = 1;
    const STATUS_PAUSED      = 2;
    const STATUS_BLACKLISTED = 3;

    // ── Register ─────────────────────────────────────────────────────────────

    public function register(array $tx, int $height): void {
        $msg = json_decode($tx['message'] ?? '{}', true) ?? [];
        $existing = $this->db->row("SELECT id FROM masternode WHERE address=?", [$tx['src']]);

        if ($existing) {
            $this->db->update('masternode', [
                'status'       => self::STATUS_ACTIVE,
                'collateral'   => $tx['val'],
                'ip'           => $msg['ip'] ?? '',
                'public_key'   => $tx['public_key'],
                'height'       => $height,
                'fails'        => 0,
                'last_seen'    => time(),
            ], 'address=?', [$tx['src']]);
        } else {
            $this->db->insert('masternode', [
                'address'      => $tx['src'],
                'public_key'   => $tx['public_key'],
                'ip'           => $msg['ip'] ?? '',
                'collateral'   => $tx['val'],
                'status'       => self::STATUS_ACTIVE,
                'last_seen'    => time(),
                'last_won'     => 0,
                'fails'        => 0,
                'vote_key'     => '',
                'cold_address' => $msg['cold_address'] ?? '',
                'height'       => $height,
            ]);
        }
    }

    // ── Pause / Resume ────────────────────────────────────────────────────────

    public function pause(string $address): void {
        $this->db->update('masternode', ['status' => self::STATUS_PAUSED], 'address=?', [$address]);
    }

    public function resume(string $address): void {
        $this->db->update('masternode', [
            'status'    => self::STATUS_ACTIVE,
            'last_seen' => time(),
            'fails'     => 0,
        ], 'address=?', [$address]);
    }

    // ── Blacklist ─────────────────────────────────────────────────────────────

    public function blacklist(string $address): void {
        $this->db->update('masternode', ['status' => self::STATUS_BLACKLISTED], 'address=?', [$address]);
    }

    public function unblacklist(string $address): void {
        $this->db->update('masternode', [
            'status' => self::STATUS_ACTIVE,
            'fails'  => 0,
        ], 'address=?', [$address]);
    }

    // ── Cold Staking ─────────────────────────────────────────────────────────

    public function bindColdAddress(string $address, string $coldAddress): void {
        $this->db->update('masternode', ['cold_address' => $coldAddress], 'address=?', [$address]);
    }

    // ── Vote Key ─────────────────────────────────────────────────────────────

    public function setVoteKey(string $address, string $voteKey): void {
        $this->db->update('masternode', ['vote_key' => $voteKey], 'address=?', [$address]);
    }

    // ── Fail Tracking ─────────────────────────────────────────────────────────

    public function recordFail(string $address): void {
        $this->db->query("UPDATE masternode SET fails=fails+1 WHERE address=?", [$address]);
        $mn = $this->db->row("SELECT fails FROM masternode WHERE address=?", [$address]);
        if ($mn && (int)$mn['fails'] >= 10) {
            $this->blacklist($address);
            SCore::log('warn', "Masternode auto-blacklisted after 10 fails: $address");
        }
    }

    public function heartbeat(string $address): void {
        $this->db->update('masternode', ['last_seen' => time(), 'fails' => 0], 'address=?', [$address]);
    }

    // ── Sweep ────────────────────────────────────────────────────────────────

    public function sweepInactive(): int {
        $cutoff = time() - 3600; // 1 hour without heartbeat
        $stale  = $this->db->rows(
            "SELECT address FROM masternode WHERE status=? AND last_seen<?",
            [self::STATUS_ACTIVE, $cutoff]
        );
        foreach ($stale as $mn) {
            $this->recordFail($mn['address']);
        }
        return count($stale);
    }

    // ── Queries ───────────────────────────────────────────────────────────────

    public function getActive(): array {
        return $this->db->rows(
            "SELECT * FROM masternode WHERE status=? ORDER BY last_won ASC",
            [self::STATUS_ACTIVE]
        );
    }

    public function get(string $address): ?array {
        return $this->db->row("SELECT * FROM masternode WHERE address=?", [$address]);
    }

    public function getAll(int $limit = 100): array {
        return $this->db->rows("SELECT * FROM masternode ORDER BY id DESC LIMIT ?", [$limit]);
    }

    public function count(int $status = self::STATUS_ACTIVE): int {
        return (int)$this->db->val("SELECT COUNT(*) FROM masternode WHERE status=?", [$status]);
    }

    public function getRewards(string $address): array {
        $mn = $this->get($address);
        if (!$mn) return [];
        $blocks = $this->db->rows(
            "SELECT height, reward, date FROM blocks WHERE masternode_id=? ORDER BY height DESC LIMIT 100",
            [$mn['id']]
        );
        $total = 0;
        foreach ($blocks as $b) $total += (float)$b['reward'] * (MASTERNODE_REWARD_PCT / 100);
        return [
            'blocks_won'   => count($blocks),
            'total_earned' => number_format($total, 8, '.', ''),
            'history'      => $blocks,
        ];
    }
}
