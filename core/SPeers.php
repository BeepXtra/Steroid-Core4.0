<?php
defined('_SECURED') or die('Restricted access');

/**
 * SPeers — Peer network management.
 * Discovery, heartbeat, blacklisting, and tx/block propagation.
 */
class SPeers {
    private Database $db;
    private Config   $cfg;

    public function __construct(Database $db, Config $cfg) {
        $this->db  = $db;
        $this->cfg = $cfg;
    }

    // ─── Peer Management ─────────────────────────────────────

    public function getActive(int $limit = 20): array {
        return $this->db->fetchAll(
            'SELECT * FROM peers WHERE blacklisted = 0 AND fails < 5
             ORDER BY ping ASC, reserve ASC LIMIT ?',
            [$limit]
        );
    }

    public function getAll(): array {
        return $this->db->fetchAll('SELECT * FROM peers ORDER BY ping ASC');
    }

    public function add(string $hostname, string $ip): void {
        $this->db->upsert('peers', [
            'hostname'    => $hostname,
            'ip'          => $ip,
            'ping'        => 0,
            'blacklisted' => 0,
            'reserve'     => 0,
            'fails'       => 0,
            'stuckfail'   => 0,
        ], ['ip','fails','blacklisted']);
    }

    public function blacklist(string $hostname): void {
        $this->db->execute(
            'UPDATE peers SET blacklisted = ? WHERE hostname = ?',
            [time(), $hostname]
        );
    }

    public function fail(string $hostname): void {
        $this->db->execute(
            'UPDATE peers SET fails = fails + 1 WHERE hostname = ?',
            [$hostname]
        );
        // Auto-blacklist after 10 consecutive fails
        $this->db->execute(
            'UPDATE peers SET blacklisted = ? WHERE hostname = ? AND fails >= 10',
            [time(), $hostname]
        );
    }

    public function resetFails(string $hostname): void {
        $this->db->execute(
            'UPDATE peers SET fails = 0, stuckfail = 0 WHERE hostname = ?',
            [$hostname]
        );
    }

    public function updatePing(string $hostname, int $ping): void {
        $this->db->execute(
            'UPDATE peers SET ping = ? WHERE hostname = ?',
            [$ping, $hostname]
        );
    }

    // ─── Heartbeat ───────────────────────────────────────────

    public function heartbeat(): void {
        $this->db->execute('DELETE FROM heartbeat');
        $this->db->insert('heartbeat', ['beep' => 1, 'timestamp' => date('Y-m-d H:i:s')]);
    }

    public function getHeartbeat(): ?array {
        return $this->db->fetchOne('SELECT * FROM heartbeat LIMIT 1');
    }

    // ─── Propagation ─────────────────────────────────────────

    public function propagateTx(array $tx): array {
        $peers   = $this->getActive();
        $results = [];
        foreach ($peers as $peer) {
            $r = $this->postToPeer($peer['hostname'], '/api/tx/receive', $tx);
            $results[$peer['hostname']] = $r;
            if ($r === false) $this->fail($peer['hostname']);
            else $this->resetFails($peer['hostname']);
        }
        return $results;
    }

    public function propagateBlock(array $block): array {
        $peers   = $this->getActive();
        $results = [];
        foreach ($peers as $peer) {
            $r = $this->postToPeer($peer['hostname'], '/api/block/receive', $block);
            $results[$peer['hostname']] = $r;
            if ($r === false) $this->fail($peer['hostname']);
            else $this->resetFails($peer['hostname']);
        }
        return $results;
    }

    // ─── Discovery ───────────────────────────────────────────

    public function discoverFromPeer(string $hostname): int {
        $url  = rtrim($hostname, '/') . '/api/peers';
        $body = $this->get($url);
        if (!$body) return 0;
        $data  = json_decode($body, true);
        $peers = $data['peers'] ?? [];
        $added = 0;
        foreach ($peers as $p) {
            if (!empty($p['hostname']) && !empty($p['ip'])) {
                $this->add($p['hostname'], $p['ip']);
                $added++;
            }
        }
        return $added;
    }

    // ─── HTTP Helpers ─────────────────────────────────────────

    private function postToPeer(string $hostname, string $path, array $data): bool {
        $url = rtrim($hostname, '/') . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => $this->cfg->propagation_timeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    private function get(string $url): string|false {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->cfg->peer_timeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200 ? $body : false;
    }
}
