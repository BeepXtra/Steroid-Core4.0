<?php
defined('_SECURED') or die('Restricted access');

/**
 * SGovernance — On-chain governance.
 * Proposal submission (v105), masternode voting (v106), proposal close (v107).
 */
class SGovernance {
    private Database $db;
    private Config   $cfg;

    // Vote values
    const VOTE_YES     = 1;
    const VOTE_NO      = -1;
    const VOTE_ABSTAIN = 0;

    // Proposal status
    const STATUS_OPEN    = 0;
    const STATUS_PASSED  = 1;
    const STATUS_FAILED  = 2;
    const STATUS_EXPIRED = 3;

    public function __construct(Database $db, Config $cfg) {
        $this->db  = $db;
        $this->cfg = $cfg;
    }

    // ─── Proposals ───────────────────────────────────────────

    public function createProposal(string $txId, string $nfo, int $val = 0): void {
        $this->db->upsert('votes', [
            'id'  => $txId,
            'nfo' => substr($nfo, 0, 64),
            'val' => $val,
        ]);
    }

    public function getProposal(string $id): ?array {
        return $this->db->fetchOne('SELECT * FROM votes WHERE id = ?', [$id]);
    }

    public function getAll(): array {
        return $this->db->fetchAll('SELECT * FROM votes ORDER BY id DESC');
    }

    // ─── Voting ──────────────────────────────────────────────

    /**
     * Cast a vote.
     * $vote: +1 yes, -1 no, 0 abstain
     * Only masternodes may vote (caller must validate MN status).
     */
    public function castVote(string $proposalId, string $mnPubKey, int $vote): void {
        $proposal = $this->getProposal($proposalId);
        if (!$proposal) throw new RuntimeException("Proposal $proposalId not found");

        // Check MN hasn't already voted
        $existing = $this->db->fetchOne(
            'SELECT * FROM votes WHERE id = ? AND nfo = ?',
            [$proposalId . ':' . $mnPubKey, $mnPubKey]
        );
        if ($existing) throw new RuntimeException('Masternode already voted on this proposal');

        // Record individual vote
        $this->db->upsert('votes', [
            'id'  => $proposalId . ':' . $mnPubKey,
            'nfo' => $mnPubKey,
            'val' => $vote,
        ]);

        // Update tally on main proposal
        $this->db->execute(
            'UPDATE votes SET val = val + ? WHERE id = ?',
            [$vote, $proposalId]
        );

        // Mark MN as voted
        $this->db->execute(
            'UPDATE masternode SET voted = 1 WHERE public_key = ?',
            [$mnPubKey]
        );
    }

    // ─── Close Proposal ──────────────────────────────────────

    public function closeProposal(string $proposalId): array {
        $proposal = $this->getProposal($proposalId);
        if (!$proposal) throw new RuntimeException("Proposal $proposalId not found");

        $totalMN   = (int)$this->db->fetchColumn(
            'SELECT COUNT(*) FROM masternode WHERE status = 1 AND blacklist = 0'
        );
        $voteCount = (int)$this->db->fetchColumn(
            'SELECT COUNT(*) FROM votes WHERE id LIKE ?',
            [$proposalId . ':%']
        );
        $tally  = (int)$proposal['val'];
        $quorum = ceil($totalMN * 0.5); // 50% quorum

        $passed = $voteCount >= $quorum && $tally > 0;
        $status = $passed ? self::STATUS_PASSED : self::STATUS_FAILED;

        // Archive result in nfo field
        $this->db->execute(
            'UPDATE votes SET nfo = ?, val = ? WHERE id = ?',
            [
                $proposal['nfo'] . '|status:' . $status . '|votes:' . $voteCount . '|tally:' . $tally,
                $status,
                $proposalId,
            ]
        );

        // Reset MN voted flags
        $this->db->execute('UPDATE masternode SET voted = 0');

        return [
            'proposal_id' => $proposalId,
            'status'      => $status,
            'passed'      => $passed,
            'total_mn'    => $totalMN,
            'votes_cast'  => $voteCount,
            'tally'       => $tally,
            'quorum'      => $quorum,
        ];
    }

    // ─── Stats ───────────────────────────────────────────────

    public function getStats(): array {
        $all    = $this->getAll();
        $open   = array_filter($all, fn($p) => !str_contains($p['nfo'], '|status:'));
        $passed = array_filter($all, fn($p) => str_contains($p['nfo'], '|status:1'));
        $failed = array_filter($all, fn($p) => str_contains($p['nfo'], '|status:2'));

        return [
            'total'  => count($all),
            'open'   => count($open),
            'passed' => count($passed),
            'failed' => count($failed),
        ];
    }
}
