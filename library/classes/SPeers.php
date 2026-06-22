<?php
defined('_SECURED') or die('Restricted access');

class SPeers {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function register($address, $port = 8080, $version = '') {
        $existing = $this->db->row("SELECT id, fails FROM peers WHERE address=?", array($address));
        if ($existing) {
            $this->db->update('peers', array(
                'last_seen' => time(),
                'fails'     => 0,
                'version'   => $version,
            ), 'address=?', array($address));
        } else {
            if ($this->db->count('peers', 'blacklisted=0') >= MAX_PEERS) return;
            $this->db->insert('peers', array(
                'address'     => $address,
                'port'        => $port,
                'last_seen'   => time(),
                'fails'       => 0,
                'blacklisted' => 0,
                'version'     => $version,
            ));
        }
    }

    public function getActive($limit = 20) {
        return $this->db->rows(
            "SELECT * FROM peers WHERE blacklisted=0 ORDER BY last_seen DESC LIMIT ?", array($limit)
        );
    }

    public function getAll() {
        return $this->db->rows("SELECT * FROM peers WHERE blacklisted=0");
    }

    public function ping($address, $port) {
        $url = "http://{$address}:{$port}/api/status";
        $ctx = stream_context_create(array('http' => array('timeout' => 5, 'method' => 'GET')));
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) {
            $this->recordFail($address);
            return false;
        }
        $this->db->update('peers', array('last_seen' => time(), 'fails' => 0), 'address=?', array($address));
        return true;
    }

    public function recordFail($address) {
        $this->db->query("UPDATE peers SET fails=fails+1 WHERE address=?", array($address));
        $peer = $this->db->row("SELECT fails FROM peers WHERE address=?", array($address));
        if ($peer && (int)$peer['fails'] >= PEER_FAIL_LIMIT) {
            $this->blacklist($address);
        }
    }

    public function blacklist($address) {
        $this->db->update('peers', array('blacklisted' => 1), 'address=?', array($address));
    }

    public function unblacklist($address) {
        $this->db->update('peers', array('blacklisted' => 0, 'fails' => 0), 'address=?', array($address));
    }

    public function propagate($endpoint, $data) {
        $peers   = $this->getActive();
        $payload = json_encode($data);
        foreach ($peers as $peer) {
            $url = "http://{$peer['address']}:{$peer['port']}/{$endpoint}";
            $ctx = stream_context_create(array(
                'http' => array(
                    'method'  => 'POST',
                    'header'  => 'Content-Type: application/json',
                    'content' => $payload,
                    'timeout' => 3,
                ),
            ));
            @file_get_contents($url, false, $ctx);
        }
    }

    public function heartbeat() {
        $this->db->insert('heartbeat', array(
            'address' => NODE_HOST,
            'ip'      => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1',
            'height'  => (new SChain($this->db))->getHeight(),
            'date'    => time(),
        ));
    }

    public function sweepDead() {
        $cutoff = time() - 3600;
        return $this->db->query(
            "UPDATE peers SET fails=fails+1 WHERE last_seen<? AND blacklisted=0", array($cutoff)
        )->rowCount();
    }
}
