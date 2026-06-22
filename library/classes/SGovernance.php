<?php
defined('_SECURED') or die('Restricted access');

class SGovernance {
    private Database $db;

    // Vote params supported on-chain
    const PARAMS = [
        'emission30',
        'masternodereward50',
        'endless10reward',
        'coldstaking',
        'block_reward',
        'masternode_reward_pct',
        'fee_pct',
        'block_time',
    ];

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function applyTx(array $tx, int $height): void {
        $type = (int)$tx['type'];
        $msg  = json_decode($tx['message'] ?? '{}', true) ?? [];

        switch ($type) {
            case TX_GOV_PROPOSAL: $this->createProposal($tx, $msg, $height); break;
            case TX_GOV_VOTE:     $this->castVote($tx, $msg, $height); break;
            case TX_GOV_RESULT:   $this->applyResult($msg, $height); break;
        }
    }

    // ── Proposal ─────────────────────────────────────────────────────────────

    private function createProposal(array $tx, array $msg, int $height): void {
        if (!in_array($msg['param'] ?? '', self::PARAMS)) return;

        // Only masternodes can propose
        $mn = $this->db->row(
            "SELECT id FROM masternode WHERE address=? AND status=1", [$tx['src']]
        );
        if (!$mn) return;

        $this->db->query(
            "INSERT IGNORE INTO config (cfg_key, cfg_val) VALUES (?,?)",
            ['proposal_' . $msg['param'], json_encode([
                'proposer'  => $tx['src'],
                'param'     => $msg['param'],
                'value'     => $msg['value'],
                'height'    => $height,
                'votes_yes' => 0,
                'votes_no'  => 0,
                'status'    => 'pending',
            ])]
        );
    }

    // ── Cast Vote ─────────────────────────────────────────────────────────────

    private function castVote(array $tx, array $msg, int $height): void {
        if (!in_array($msg['param'] ?? '', self::PARAMS)) return;

        // Verify voter is a masternode with valid vote_key
        $mn = $this->db->row(
            "SELECT id, vote_key FROM masternode WHERE address=? AND status=1", [$tx['src']]
        );
        if (!$mn) return;

        // Prevent double-vote per param per masternode
        if ($this->db->exists('votes', 'address=? AND param=?', [$tx['src'], $msg['param']])) return;

        $this->db->insert('votes', [
            'address'  => $tx['src'],
            'vote_key' => $mn['vote_key'],
            'param'    => $msg['param'],
            'value'    => $msg['value'],
            'height'   => $height,
            'date'     => time(),
        ]);
    }

    // ── Tally + Apply ─────────────────────────────────────────────────────────

    public function tally(string $param): array {
        $votes = $this->db->rows(
            "SELECT value, COUNT(*) AS cnt FROM votes WHERE param=? GROUP BY value",
            [$param]
        );
        $total = array_sum(array_column($votes, 'cnt'));
        return compact('votes', 'total');
    }

    public function checkAndApply(string $param, int $quorumPct = 51): bool {
        $mnCount = (new SMasternode($this->db))->count();
        if ($mnCount === 0) return false;

        $tally   = $this->tally($param);
        $quorum  = (int)ceil($mnCount * $quorumPct / 100);

        if ($tally['total'] < $quorum) return false;

        // Find winning value
        usort($tally['votes'], fn($a, $b) => $b['cnt'] - $a['cnt']);
        $winner = $tally['votes'][0] ?? null;
        if (!$winner) return false;

        SCore::setConfig($param, $winner['value']);
        SCore::log('info', "Governance param applied: $param = {$winner['value']}", [
            'votes_total' => $tally['total'],
            'quorum'      => $quorum,
        ]);
        return true;
    }

    private function applyResult(array $msg, int $height): void {
        if (!empty($msg['param']) && !empty($msg['value'])) {
            SCore::setConfig($msg['param'], $msg['value']);
        }
    }

    // ── Queries ───────────────────────────────────────────────────────────────

    public function getVotes(string $param): array {
        return $this->db->rows(
            "SELECT * FROM votes WHERE param=? ORDER BY height ASC", [$param]
        );
    }

    public function getAllVotes(): array {
        return $this->db->rows("SELECT * FROM votes ORDER BY id DESC LIMIT 500");
    }

    public function getProposals(): array {
        $rows = $this->db->rows("SELECT * FROM config WHERE cfg_key LIKE 'proposal_%'");
        return array_map(fn($r) => json_decode($r['cfg_val'], true), $rows);
    }
}
