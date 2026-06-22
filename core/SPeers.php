<?php
defined('_SECURED') or die('Restricted access');

class SPeers {
    private $db;
    private $cfg;

    public function __construct($db, $cfg) {
        $this->db  = $db;
        $this->cfg = $cfg;
    }

    public function getActive($limit = 20) {
        return $this->db->fetchAll(
            'SELECT * FROM peers WHERE blacklisted = 0 AND fails < 5 ORDER BY ping ASC, reserve ASC LIMIT ?',
            array($limit)
        );
    }

    public function getAll() {
        return $this->db->fetchAll('SELECT * FROM peers ORDER BY ping ASC');
    }

    public function add($hostname, $ip) {
        $this->db->upsert('peers', array(
            'hostname' => $hostname, 'ip' => $ip,
            'ping' => 0, 'blacklisted' => 0, 'reserve' => 0, 'fails' => 0, 'stuckfail' => 0,
        ), array('ip','fails','blacklisted'));
    }

    public function blacklist($hostname) {
        $this->db->execute('UPDATE peers SET blacklisted = ? WHERE hostname = ?', array(time(), $hostname));
    }

    public function fail($hostname) {
        $this->db->execute('UPDATE peers SET fails = fails + 1 WHERE hostname = ?', array($hostname));
        $this->db->execute('UPDATE peers SET blacklisted = ? WHERE hostname = ? AND fails >= 10', array(time(), $hostname));
    }

    public function resetFails($hostname) {
        $this->db->execute('UPDATE peers SET fails = 0, stuckfail = 0 WHERE hostname = ?', array($hostname));
    }

    public function updatePing($hostname, $ping) {
        $this->db->execute('UPDATE peers SET ping = ? WHERE hostname = ?', array($ping, $hostname));
    }

    public function heartbeat() {
        $this->db->execute('DELETE FROM heartbeat');
        $this->db->insert('heartbeat', array('beep' => 1, 'timestamp' => date('Y-m-d H:i:s')));
    }

    public function getHeartbeat() {
        return $this->db->fetchOne('SELECT * FROM heartbeat LIMIT 1');
    }

    public function propagateTx($tx) {
        $peers   = $this->getActive();
        $results = array();
        foreach ($peers as $peer) {
            $r = $this->postToPeer($peer['hostname'], '/api/tx/receive', $tx);
            $results[$peer['hostname']] = $r;
            if ($r === false) $this->fail($peer['hostname']);
            else $this->resetFails($peer['hostname']);
        }
        return $results;
    }

    public function propagateBlock($block) {
        $peers   = $this->getActive();
        $results = array();
        foreach ($peers as $peer) {
            $r = $this->postToPeer($peer['hostname'], '/api/block/receive', $block);
            $results[$peer['hostname']] = $r;
            if ($r === false) $this->fail($peer['hostname']);
            else $this->resetFails($peer['hostname']);
        }
        return $results;
    }

    public function discoverFromPeer($hostname) {
        $body  = $this->httpGet(rtrim($hostname, '/') . '/api/peers');
        if (!$body) return 0;
        $data  = json_decode($body, true);
        $peers = isset($data['peers']) ? $data['peers'] : array();
        $added = 0;
        foreach ($peers as $p) {
            if (!empty($p['hostname']) && !empty($p['ip'])) {
                $this->add($p['hostname'], $p['ip']);
                $added++;
            }
        }
        return $added;
    }

    private function postToPeer($hostname, $path, $data) {
        $url = rtrim($hostname, '/') . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_TIMEOUT        => $this->cfg->propagation_timeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ));
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    private function httpGet($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->cfg->peer_timeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ));
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200 ? $body : false;
    }
}
