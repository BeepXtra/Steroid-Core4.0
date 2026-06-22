<?php
defined('_SECURED') or die('Restricted access');

class SGovernance {
    private $db;
    private $cfg;

    const VOTE_YES     = 1;
    const VOTE_NO      = -1;
    const VOTE_ABSTAIN = 0;
    const STATUS_OPEN    = 0;
    const STATUS_PASSED  = 1;
    const STATUS_FAILED  = 2;
    const STATUS_EXPIRED = 3;

    public function __construct($db, $cfg) {
        $this->db  = $db;
        $this->cfg = $cfg;
    }

    public function createProposal($txId, $nfo, $val = 0) {
        $this->db->upsert('votes', array(
            'id'  => $txId,
            'nfo' => substr($nfo, 0, 64),
            'val' => $val,
        ));
    }

    public function getProposal($id) {
        return $this->db->fetchOne('SELECT * FROM votes WHERE id = ?', array($id));
    }

    public function getAll() {
        return $this->db->fetchAll('SELECT * FROM votes ORDER BY id DESC');
    }

    public function castVote($proposalId, $mnPubKey, $vote) {
        $proposal = $this->getProposal($proposalId);
        if (!$proposal) throw new RuntimeException("Proposal $proposalId not found");

        $existing = $this->db->fetchOne(
            'SELECT * FROM votes WHERE id = ? AND nfo = ?',
            array($proposalId . ':' . $mnPubKey, $mnPubKey)
        );
        if ($existing) throw new RuntimeException('Masternode already voted on this proposal');

        $this->db->upsert('votes', array(
            'id'  => $proposalId . ':' . $mnPubKey,
            'nfo' => $mnPubKey,
            'val' => $vote,
        ));
        $this->db->execute('UPDATE votes SET val = val + ? WHERE id = ?', array($vote, $proposalId));
        $this->db->execute('UPDATE masternode SET voted = 1 WHERE public_key = ?', array($mnPubKey));
    }

    public function closeProposal($proposalId) {
        $proposal = $this->getProposal($proposalId);
        if (!$proposal) throw new RuntimeException("Proposal $proposalId not found");

        $totalMN   = (int)$this->db->fetchColumn('SELECT COUNT(*) FROM masternode WHERE status = 1 AND blacklist = 0');
        $voteCount = (int)$this->db->fetchColumn(
            'SELECT COUNT(*) FROM votes WHERE id LIKE ?',
            array($proposalId . ':%')
        );
        $tally  = (int)$proposal['val'];
        $quorum = (int)ceil($totalMN * 0.5);
        $passed = $voteCount >= $quorum && $tally > 0;
        $status = $passed ? self::STATUS_PASSED : self::STATUS_FAILED;

        $this->db->execute(
            'UPDATE votes SET nfo = ?, val = ? WHERE id = ?',
            array(
                $proposal['nfo'] . '|status:' . $status . '|votes:' . $voteCount . '|tally:' . $tally,
                $status,
                $proposalId,
            )
        );
        $this->db->execute('UPDATE masternode SET voted = 0');

        return array(
            'proposal_id' => $proposalId,
            'status'      => $status,
            'passed'      => $passed,
            'total_mn'    => $totalMN,
            'votes_cast'  => $voteCount,
            'tally'       => $tally,
            'quorum'      => $quorum,
        );
    }

    public function getStats() {
        $all    = $this->getAll();
        $open   = 0; $passed = 0; $failed = 0;
        foreach ($all as $p) {
            if (strpos($p['nfo'], '|status:') === false) $open++;
            elseif (strpos($p['nfo'], '|status:1') !== false) $passed++;
            else $failed++;
        }
        return array('total' => count($all), 'open' => $open, 'passed' => $passed, 'failed' => $failed);
    }
}
