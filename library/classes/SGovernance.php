<?php
defined('_SECURED') or die('Restricted access');

class SGovernance {
    private $db;

    const PARAMS = array(
        'emission30',
        'masternodereward50',
        'endless10reward',
        'coldstaking',
        'block_reward',
        'masternode_reward_pct',
        'fee_pct',
        'block_time',
    );

    public function __construct($db) {
        $this->db = $db;
    }

    public function applyTx($tx, $height) {
        $type = (int)$tx['type'];
        $msg  = json_decode(isset($tx['message']) ? $tx['message'] : '{}', true);
        if (!$msg) $msg = array();

        switch ($type) {
            case TX_GOV_PROPOSAL: $this->createProposal($tx, $msg, $height); break;
            case TX_GOV_VOTE:     $this->castVote($tx, $msg, $height); break;
            case TX_GOV_RESULT:   $this->applyResult($msg, $height); break;
        }
    }

    private function createProposal($tx, $msg, $height) {
        if (!in_array(isset($msg['param']) ? $msg['param'] : '', self::PARAMS)) return;
        $mn = $this->db->row(
            "SELECT id FROM masternode WHERE address=? AND status=1", array($tx['src'])
        );
        if (!$mn) return;

        $this->db->query(
            "INSERT IGNORE INTO config (cfg_key, cfg_val) VALUES (?,?)",
            array('proposal_' . $msg['param'], json_encode(array(
                'proposer'  => $tx['src'],
                'param'     => $msg['param'],
                'value'     => $msg['value'],
                'height'    => $height,
                'votes_yes' => 0,
                'votes_no'  => 0,
                'status'    => 'pending',
            )))
        );
    }

    private function castVote($tx, $msg, $height) {
        if (!in_array(isset($msg['param']) ? $msg['param'] : '', self::PARAMS)) return;
        $mn = $this->db->row(
            "SELECT id, vote_key FROM masternode WHERE address=? AND status=1", array($tx['src'])
        );
        if (!$mn) return;
        if ($this->db->exists('votes', 'address=? AND param=?', array($tx['src'], $msg['param']))) return;

        $this->db->insert('votes', array(
            'address'  => $tx['src'],
            'vote_key' => $mn['vote_key'],
            'param'    => $msg['param'],
            'value'    => $msg['value'],
            'height'   => $height,
            'date'     => time(),
        ));
    }

    public function tally($param) {
        $votes = $this->db->rows(
            "SELECT value, COUNT(*) AS cnt FROM votes WHERE param=? GROUP BY value",
            array($param)
        );
        $total = 0;
        foreach ($votes as $v) $total += (int)$v['cnt'];
        return array('votes' => $votes, 'total' => $total);
    }

    public function checkAndApply($param, $quorumPct = 51) {
        $mnCount = (new SMasternode($this->db))->count();
        if ($mnCount === 0) return false;

        $tally  = $this->tally($param);
        $quorum = (int)ceil($mnCount * $quorumPct / 100);
        if ($tally['total'] < $quorum) return false;

        usort($tally['votes'], function($a, $b) { return $b['cnt'] - $a['cnt']; });
        $winner = isset($tally['votes'][0]) ? $tally['votes'][0] : null;
        if (!$winner) return false;

        SCore::setConfig($param, $winner['value']);
        SCore::log('info', "Governance param applied: $param = {$winner['value']}", array(
            'votes_total' => $tally['total'],
            'quorum'      => $quorum,
        ));
        return true;
    }

    private function applyResult($msg, $height) {
        if (!empty($msg['param']) && !empty($msg['value'])) {
            SCore::setConfig($msg['param'], $msg['value']);
        }
    }

    public function getVotes($param) {
        return $this->db->rows(
            "SELECT * FROM votes WHERE param=? ORDER BY height ASC", array($param)
        );
    }

    public function getAllVotes() {
        return $this->db->rows("SELECT * FROM votes ORDER BY id DESC LIMIT 500");
    }

    public function getProposals() {
        $rows = $this->db->rows("SELECT * FROM config WHERE cfg_key LIKE 'proposal_%'");
        $result = array();
        foreach ($rows as $r) {
            $result[] = json_decode($r['cfg_val'], true);
        }
        return $result;
    }
}
