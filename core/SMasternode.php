<?php
defined('_SECURED') or die('Restricted access');

/**
 * SMasternode — Masternode lifecycle management.
 * Register, pause, resume, blacklist, cold staking, rewards.
 */
class SMasternode {
    private Database $db;
    private Config   $cfg;

    public function __construct(Database $db, Config $cfg) {
        $this->db  = $db;
        $this->cfg = $cfg;
    }

    // ─── Registration ────────────────────────────────────────

    public function register(string $pubKey, string $ip, int $height): void {
        if ($this->exists($pubKey)) throw new RuntimeException('Masternode already registered');
        $this->db->insert('masternode', [
            'public_key'    => $pubKey,
            'height'        => $height,
            'ip'            => $ip,
            'last_won'      => 0,
            'blacklist'     => 0,
            'fails'         => 0,
            'status'        => 1,
            'vote_key'      => null,
            'cold_last_won' => 0,
            'voted'         => 0,
        ]);
    }

    public function deregister(string $pubKey): void {
        $this->db->execute('DELETE FROM masternode WHERE public_key = ?', [$pubKey]);
    }

    // ─── Status Control ──────────────────────────────────────

    public function pause(string $pubKey): void {
        $this->db->execute('UPDATE masternode SET status = 0 WHERE public_key = ?', [$pubKey]);
    }

    public function resume(string $pubKey): void {
        $this->db->execute('UPDATE masternode SET status = 1 WHERE public_key = ?', [$pubKey]);
    }

    public function blacklist(string $pubKey): void {
        $this->db->execute(
            'UPDATE masternode SET blacklist = ?, status = 0 WHERE public_key = ?',
            [time(), $pubKey]
        );
    }

    public function unblacklist(string $pubKey): void {
        $this->db->execute(
            'UPDATE masternode SET blacklist = 0, status = 1, fails = 0 WHERE public_key = ?',
            [$pubKey]
        );
    }

    public function recordFail(string $pubKey): void {
        $this->db->execute(
            'UPDATE masternode SET fails = fails + 1 WHERE public_key = ?',
            [$pubKey]
        );
        // Auto-blacklist at 10 fails
        $mn = $this->get($pubKey);
        if ($mn && (int)$mn['fails'] >= 10) {
            $this->blacklist($pubKey);
        }
    }

    // ─── Cold Staking ────────────────────────────────────────

    public function setColdVoteKey(string $pubKey, string $voteKey): void {
        $this->db->execute(
            'UPDATE masternode SET vote_key = ? WHERE public_key = ?',
            [$voteKey, $pubKey]
        );
    }

    public function getColdStakers(): array {
        return $this->db->fetchAll(
            'SELECT * FROM masternode WHERE vote_key IS NOT NULL AND status = 1 AND blacklist = 0'
        );
    }

    public function updateColdLastWon(string $pubKey): void {
        $this->db->execute(
            'UPDATE masternode SET cold_last_won = ? WHERE public_key = ?',
            [time(), $pubKey]
        );
    }

    // ─── Reward Winner Selection ─────────────────────────────

    public function pickWinner(): ?array {
        return $this->db->fetchOne(
            'SELECT * FROM masternode WHERE status = 1 AND blacklist = 0
             ORDER BY last_won ASC LIMIT 1'
        );
    }

    public function pickColdWinner(): ?array {
        return $this->db->fetchOne(
            'SELECT * FROM masternode WHERE status = 1 AND blacklist = 0 AND vote_key IS NOT NULL
             ORDER BY cold_last_won ASC LIMIT 1'
        );
    }

    public function markWon(string $pubKey): void {
        $this->db->execute(
            'UPDATE masternode SET last_won = ? WHERE public_key = ?',
            [time(), $pubKey]
        );
    }

    // ─── Voting ──────────────────────────────────────────────

    public function setVoted(string $pubKey, int $voted = 1): void {
        $this->db->execute(
            'UPDATE masternode SET voted = ? WHERE public_key = ?',
            [$voted, $pubKey]
        );
    }

    public function resetVotes(): void {
        $this->db->execute('UPDATE masternode SET voted = 0');
    }

    // ─── Lookups ─────────────────────────────────────────────

    public function get(string $pubKey): ?array {
        return $this->db->fetchOne('SELECT * FROM masternode WHERE public_key = ?', [$pubKey]);
    }

    public function exists(string $pubKey): bool {
        return (bool)$this->db->fetchColumn(
            'SELECT COUNT(*) FROM masternode WHERE public_key = ?', [$pubKey]
        );
    }

    public function getActive(): array {
        return $this->db->fetchAll(
            'SELECT * FROM masternode WHERE status = 1 AND blacklist = 0 ORDER BY last_won ASC'
        );
    }

    public function getAll(): array {
        return $this->db->fetchAll('SELECT * FROM masternode ORDER BY height ASC');
    }

    public function count(): int {
        return (int)$this->db->fetchColumn(
            'SELECT COUNT(*) FROM masternode WHERE status = 1 AND blacklist = 0'
        );
    }

    // ─── Reward History ──────────────────────────────────────

    public function getRewards(string $pubKey, int $limit = 50): array {
        return $this->db->fetchAll(
            'SELECT * FROM masternode_rewards WHERE public_key = ? ORDER BY height DESC LIMIT ?',
            [$pubKey, $limit]
        );
    }

    public function getTotalRewards(string $pubKey): string {
        return $this->db->fetchColumn(
            'SELECT COALESCE(SUM(reward),0) FROM masternode_rewards WHERE public_key = ?',
            [$pubKey]
        ) ?? '0.00000000';
    }

    // ─── Stats ───────────────────────────────────────────────

    public function getStats(): array {
        return [
            'total'       => (int)$this->db->fetchColumn('SELECT COUNT(*) FROM masternode'),
            'active'      => $this->count(),
            'blacklisted' => (int)$this->db->fetchColumn('SELECT COUNT(*) FROM masternode WHERE blacklist > 0'),
            'cold_staking'=> (int)$this->db->fetchColumn('SELECT COUNT(*) FROM masternode WHERE vote_key IS NOT NULL'),
        ];
    }
}
