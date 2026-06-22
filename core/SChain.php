<?php
defined('_SECURED') or die('Restricted access');

class SChain {
    private $db;
    private $cfg;

    public function __construct($db, $cfg) {
        $this->db  = $db;
        $this->cfg = $cfg;
    }

    public function getHeight() {
        return (int)($this->db->fetchColumn('SELECT MAX(height) FROM blocks') ?: 0);
    }

    public function getTopBlock() {
        return $this->db->fetchOne('SELECT * FROM blocks ORDER BY height DESC LIMIT 1');
    }

    public function getBlockByHeight($height) {
        return $this->db->fetchOne('SELECT * FROM blocks WHERE height = ?', array($height));
    }

    public function getBlockById($id) {
        return $this->db->fetchOne('SELECT * FROM blocks WHERE id = ?', array($id));
    }

    public function getRange($from, $to) {
        return $this->db->fetchAll(
            'SELECT * FROM blocks WHERE height BETWEEN ? AND ? ORDER BY height ASC',
            array($from, $to)
        );
    }

    public function getTotalSupply() {
        return $this->db->fetchColumn('SELECT COALESCE(SUM(balance),0) FROM accounts') ?: '0';
    }

    public function syncWithPeer($peerHostname) {
        $myHeight   = $this->getHeight();
        $peerStatus = $this->fetchPeerStatus($peerHostname);
        if (!$peerStatus || (int)$peerStatus['height'] <= $myHeight) return 0;
        $added  = 0;
        $from   = $myHeight + 1;
        $to     = min((int)$peerStatus['height'], $myHeight + 500);
        $blocks = $this->fetchBlocksFromPeer($peerHostname, $from, $to);
        foreach ($blocks as $blockData) {
            if ($this->acceptBlock($blockData)) $added++;
        }
        return $added;
    }

    public function acceptBlock($blockData) {
        $height   = (int)$blockData['height'];
        $existing = $this->getBlockByHeight($height);
        if ($existing) {
            if ($existing['id'] <= $blockData['id']) return false;
            $this->revertToHeight($height - 1);
        }
        $this->db->insert('blocks', array(
            'id'           => $blockData['id'],
            'generator'    => $blockData['generator'],
            'height'       => $blockData['height'],
            'date'         => $blockData['date'],
            'nonce'        => $blockData['nonce'],
            'signature'    => $blockData['signature'],
            'difficulty'   => $blockData['difficulty'],
            'argon'        => $blockData['argon'],
            'transactions' => isset($blockData['transactions']) ? $blockData['transactions'] : 0,
        ));
        return true;
    }

    public function revertToHeight($height) {
        $this->db->execute('DELETE FROM blocks WHERE height > ?', array($height));
    }

    private function fetchPeerStatus($hostname) {
        $url = rtrim($hostname, '/') . '/api/status';
        $ch  = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->cfg->peer_timeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ));
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$body) return null;
        return json_decode($body, true);
    }

    private function fetchBlocksFromPeer($hostname, $from, $to) {
        $url = rtrim($hostname, '/') . "/api/blocks?from={$from}&to={$to}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ));
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$body) return array();
        $data = json_decode($body, true);
        return isset($data['blocks']) ? $data['blocks'] : array();
    }

    public function getStats() {
        $top = $this->getTopBlock();
        return array(
            'height'         => $this->getHeight(),
            'top_block'      => $top ? $top['id'] : null,
            'top_block_date' => $top ? $top['date'] : null,
            'total_supply'   => $this->getTotalSupply(),
            'difficulty'     => $top ? $top['difficulty'] : null,
        );
    }
}
