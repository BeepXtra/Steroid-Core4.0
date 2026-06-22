<?php
defined('_SECURED') or die('Restricted access');

class SMasternode {
    private $db;

    const STATUS_INACTIVE    = 0;
    const STATUS_ACTIVE      = 1;
    const STATUS_PAUSED      = 2;
    const STATUS_BLACKLISTED = 3;

    public function __construct($db) {
        $this->db = $db;
    }

    public function register($tx, $height) {
        $msg      = json_decode(isset($tx['message']) ? $tx['message'] : '{}', true);
        if (!$msg) $msg = array();
        $existing = $this->db->row("SELECT id FROM masternode WHERE address=?", array($tx['src']));

        if ($existing) {
            $this->db->update('masternode', array(
                'status'     => self::STATUS_ACTIVE,
                'collateral' => $tx['val'],
                'ip'         => isset($msg['ip']) ? $msg['ip'] : '',
                'public_key' => $tx['public_key'],
                'height'     => $height,
                'fails'      => 0,
                'last_seen'  => time(),
            ), 'address=?', array($tx['src']));
        } else {
            $this->db->insert('masternode', array(
                'address'      => $tx['src'],
                'public_key'   => $tx['public_key'],
                'ip'           => isset($msg['ip']) ? $msg['ip'] : '',
                'collateral'   => $tx['val'],
                'status'       => self::STATUS_ACTIVE,
                'last_seen'    => time(),
                'last_won'     => 0,
                'fails'        => 0,
                'vote_key'     => '',
                'cold_address' => isset($msg['cold_address']) ? $msg['cold_address'] : '',
                'height'       => $height,
            ));
        }
    }

    public function pause($address) {
        $this->db->update('masternode', array('status' => self::STATUS_PAUSED), 'address=?', array($address));
    }

    public function resume($address) {
        $this->db->update('masternode', array(
            'status'    => self::STATUS_ACTIVE,
            'last_seen' => time(),
            'fails'     => 0,
        ), 'address=?', array($address));
    }

    public function blacklist($address) {
        $this->db->update('masternode', array('status' => self::STATUS_BLACKLISTED), 'address=?', array($address));
    }

    public function unblacklist($address) {
        $this->db->update('masternode', array(
            'status' => self::STATUS_ACTIVE,
            'fails'  => 0,
        ), 'address=?', array($address));
    }

    public function bindColdAddress($address, $coldAddress) {
        $this->db->update('masternode', array('cold_address' => $coldAddress), 'address=?', array($address));
    }

    public function setVoteKey($address, $voteKey) {
        $this->db->update('masternode', array('vote_key' => $voteKey), 'address=?', array($address));
    }

    public function recordFail($address) {
        $this->db->query("UPDATE masternode SET fails=fails+1 WHERE address=?", array($address));
        $mn = $this->db->row("SELECT fails FROM masternode WHERE address=?", array($address));
        if ($mn && (int)$mn['fails'] >= 10) {
            $this->blacklist($address);
            SCore::log('warn', "Masternode auto-blacklisted after 10 fails: $address");
        }
    }

    public function heartbeat($address) {
        $this->db->update('masternode', array('last_seen' => time(), 'fails' => 0), 'address=?', array($address));
    }

    public function sweepInactive() {
        $cutoff = time() - 3600;
        $stale  = $this->db->rows(
            "SELECT address FROM masternode WHERE status=? AND last_seen<?",
            array(self::STATUS_ACTIVE, $cutoff)
        );
        foreach ($stale as $mn) {
            $this->recordFail($mn['address']);
        }
        return count($stale);
    }

    public function getActive() {
        return $this->db->rows(
            "SELECT * FROM masternode WHERE status=? ORDER BY last_won ASC",
            array(self::STATUS_ACTIVE)
        );
    }

    public function get($address) {
        return $this->db->row("SELECT * FROM masternode WHERE address=?", array($address));
    }

    public function getAll($limit = 100) {
        return $this->db->rows("SELECT * FROM masternode ORDER BY id DESC LIMIT ?", array($limit));
    }

    public function count($status = self::STATUS_ACTIVE) {
        return (int)$this->db->val("SELECT COUNT(*) FROM masternode WHERE status=?", array($status));
    }

    public function getRewards($address) {
        $mn = $this->get($address);
        if (!$mn) return array();
        $blocks = $this->db->rows(
            "SELECT height, reward, date FROM blocks WHERE masternode_id=? ORDER BY height DESC LIMIT 100",
            array($mn['id'])
        );
        $total = 0;
        foreach ($blocks as $b) $total += (float)$b['reward'] * (MASTERNODE_REWARD_PCT / 100);
        return array(
            'blocks_won'   => count($blocks),
            'total_earned' => number_format($total, 8, '.', ''),
            'history'      => $blocks,
        );
    }
}
