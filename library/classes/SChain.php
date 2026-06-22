<?php
defined('_SECURED') or die('Restricted access');

class SChain {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getTop() {
        return $this->db->row("SELECT * FROM blocks ORDER BY height DESC LIMIT 1");
    }

    public function getHeight() {
        $row = $this->db->row("SELECT MAX(height) AS h FROM blocks");
        return $row ? (int)$row['h'] : 0;
    }

    public function getTotalSupply() {
        $row = $this->db->row("SELECT COALESCE(SUM(balance),0) AS s FROM accounts");
        return $row ? number_format((float)$row['s'], 8, '.', '') : '0.00000000';
    }

    public function getStatus() {
        $top = $this->getTop();
        return array(
            'height'       => $this->getHeight(),
            'top_block'    => $top ? $top['hash'] : null,
            'total_supply' => $this->getTotalSupply(),
            'version'      => NODE_VERSION,
            'hostname'     => NODE_HOST,
        );
    }

    public function isValidChain() {
        $blocks = $this->db->rows(
            "SELECT hash, prev_hash, height FROM blocks ORDER BY height ASC"
        );
        $prev = str_repeat('0', 64);
        foreach ($blocks as $block) {
            if ($block['prev_hash'] !== $prev) return false;
            $prev = $block['hash'];
        }
        return true;
    }

    public function getRange($from, $to) {
        return $this->db->rows(
            "SELECT * FROM blocks WHERE height BETWEEN ? AND ? ORDER BY height ASC",
            array($from, $to)
        );
    }

    public function getBlockTransactions($blockId) {
        return $this->db->rows(
            "SELECT * FROM transactions WHERE block=? ORDER BY id ASC",
            array($blockId)
        );
    }

    public function getStats() {
        return array(
            'total_blocks'     => (int)$this->db->val("SELECT COUNT(*) FROM blocks"),
            'total_tx'         => (int)$this->db->val("SELECT COUNT(*) FROM transactions"),
            'total_accounts'   => (int)$this->db->val("SELECT COUNT(*) FROM accounts"),
            'total_supply'     => $this->getTotalSupply(),
            'mempool_count'    => (int)$this->db->val("SELECT COUNT(*) FROM mempool"),
            'peer_count'       => (int)$this->db->val("SELECT COUNT(*) FROM peers WHERE blacklisted=0"),
            'masternode_count' => (int)$this->db->val("SELECT COUNT(*) FROM masternode WHERE status=1"),
        );
    }

    public function seedGenesis() {
        if ($this->getHeight() > 0) return;
        $genesisHash = hash('sha256', 'steroid_v2_genesis_' . NODE_HOST);
        $this->db->insert('blocks', array(
            'height'        => 0,
            'hash'          => $genesisHash,
            'prev_hash'     => str_repeat('0', 64),
            'generator'     => 'genesis',
            'signature'     => 'genesis',
            'nonce'         => '0',
            'difficulty'    => 0,
            'argon'         => 'genesis',
            'transactions'  => 0,
            'date'          => time(),
            'reward'        => '0.00000000',
            'masternode_id' => 0,
            'version'       => 1,
        ));
    }
}
