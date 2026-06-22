<?php
defined('_SECURED') or die('Restricted access');

class SMasternode {
    private $db;
    private $cfg;

    public function __construct($db, $cfg) {
        $this->db  = $db;
        $this->cfg = $cfg;
    }

    public function register($pubKey, $ip, $height) {
        if ($this->exists($pubKey)) throw new RuntimeException('Masternode already registered');
        $this->db->insert('masternode', array(
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
        ));
    }

    public function deregister($pubKey) {
        $this->db->execute('DELETE FROM masternode WHERE public_key = ?', array($pubKey));
    }

    public function pause($pubKey) {
        $this->db->execute('UPDATE masternode SET status = 0 WHERE public_key = ?', array($pubKey));
    }

    public function resume($pubKey) {
        $this->db->execute('UPDATE masternode SET status = 1 WHERE public_key = ?', array($pubKey));
    }

    public function blacklist($pubKey) {
        $this->db->execute(
            'UPDATE masternode SET blacklist = ?, status = 0 WHERE public_key = ?',
            array(time(), $pubKey)
        );
    }

    public function unblacklist($pubKey) {
        $this->db->execute(
            'UPDATE masternode SET blacklist = 0, status = 1, fails = 0 WHERE public_key = ?',
            array($pubKey)
        );
    }

    public function recordFail($pubKey) {
        $this->db->execute('UPDATE masternode SET fails = fails + 1 WHERE public_key = ?', array($pubKey));
        $mn = $this->get($pubKey);
        if ($mn && (int)$mn['fails'] >= 10) $this->blacklist($pubKey);
    }

    public function setColdVoteKey($pubKey, $voteKey) {
        $this->db->execute('UPDATE masternode SET vote_key = ? WHERE public_key = ?', array($voteKey, $pubKey));
    }

    public function getColdStakers() {
        return $this->db->fetchAll(
            'SELECT * FROM masternode WHERE vote_key IS NOT NULL AND status = 1 AND blacklist = 0'
        );
    }

    public function updateColdLastWon($pubKey) {
        $this->db->execute('UPDATE masternode SET cold_last_won = ? WHERE public_key = ?', array(time(), $pubKey));
    }

    public function pickWinner() {
        return $this->db->fetchOne(
            'SELECT * FROM masternode WHERE status = 1 AND blacklist = 0 ORDER BY last_won ASC LIMIT 1'
        );
    }

    public function pickColdWinner() {
        return $this->db->fetchOne(
            'SELECT * FROM masternode WHERE status = 1 AND blacklist = 0 AND vote_key IS NOT NULL ORDER BY cold_last_won ASC LIMIT 1'
        );
    }

    public function markWon($pubKey) {
        $this->db->execute('UPDATE masternode SET last_won = ? WHERE public_key = ?', array(time(), $pubKey));
    }

    public function setVoted($pubKey, $voted = 1) {
        $this->db->execute('UPDATE masternode SET voted = ? WHERE public_key = ?', array($voted, $pubKey));
    }

    public function resetVotes() {
        $this->db->execute('UPDATE masternode SET voted = 0');
    }

    public function get($pubKey) {
        return $this->db->fetchOne('SELECT * FROM masternode WHERE public_key = ?', array($pubKey));
    }

    public function exists($pubKey) {
        return (bool)$this->db->fetchColumn('SELECT COUNT(*) FROM masternode WHERE public_key = ?', array($pubKey));
    }

    public function getActive() {
        return $this->db->fetchAll('SELECT * FROM masternode WHERE status = 1 AND blacklist = 0 ORDER BY last_won ASC');
    }

    public function getAll() {
        return $this->db->fetchAll('SELECT * FROM masternode ORDER BY height ASC');
    }

    public function count() {
        return (int)$this->db->fetchColumn('SELECT COUNT(*) FROM masternode WHERE status = 1 AND blacklist = 0');
    }

    public function getRewards($pubKey, $limit = 50) {
        return $this->db->fetchAll(
            'SELECT * FROM masternode_rewards WHERE public_key = ? ORDER BY height DESC LIMIT ?',
            array($pubKey, $limit)
        );
    }

    public function getTotalRewards($pubKey) {
        $r = $this->db->fetchColumn('SELECT COALESCE(SUM(reward),0) FROM masternode_rewards WHERE public_key = ?', array($pubKey));
        return $r !== null ? $r : '0.00000000';
    }

    public function getStats() {
        return array(
            'total'        => (int)$this->db->fetchColumn('SELECT COUNT(*) FROM masternode'),
            'active'       => $this->count(),
            'blacklisted'  => (int)$this->db->fetchColumn('SELECT COUNT(*) FROM masternode WHERE blacklist > 0'),
            'cold_staking' => (int)$this->db->fetchColumn('SELECT COUNT(*) FROM masternode WHERE vote_key IS NOT NULL'),
        );
    }
}
