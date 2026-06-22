<?php
defined('_SECURED') or die('Restricted access');

class SPeers {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function register(string $address, int $port = 8080, string $version = ''): void {
        $existing = $this->db->row("SELECT id, fails FROM peers WHERE address=?", [$address]);
        if ($existing) {
            $this->db->update('peers', [
                'last_seen' => time(),
                'fails'     => 0,
                'version'   => $version,
            ], 'address=?', [$address]);
        } else {
            if ($this->db->count('peers', 'blacklisted=0') >= MAX_PEERS) return;
            $this->db->insert('peers', [
                'address'    => $address,
                'port'       => $port,
                'last_seen'  => time(),
                'fails'      => 0,
                'blacklisted'=> 0,
                'version'    => $version,
            ]);
        }
    }

    public function getActive(int $limit = 20): array {
        return $this->db->rows(
            "SELECT * FROM peers WHERE blacklisted=0 ORDER BY last_seen DESC LIMIT ?", [$limit]
        );
    }

    public function getAll(): array {
        return $this->db->rows("SELECT * FROM peers WHERE blacklisted=0");
    }

    public function ping(string $address, int $port): bool {
        $url = "http://{$address}:{$port}/api/status";
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'method' => 'GET']]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) {
            $this->recordFail($address);
            return false;
        }
        $this->db->update('peers', ['last_seen' => time(), 'fails' => 0], 'address=?', [$address]);
        return true;
    }

    public function recordFail(string $address): void {
        $this->db->query(
            "UPDATE peers SET fails=fails+1 WHERE address=?", [$address]
        );
        $peer = $this->db->row("SELECT fails FROM peers WHERE address=?", [$address]);
        if ($peer && (int)$peer['fails'] >= PEER_FAIL_LIMIT) {
            $this->blacklist($address);
        }
    }

    public function blacklist(string $address): void {
        $this->db->update('peers', ['blacklisted' => 1], 'address=?', [$address]);
    }

    public function unblacklist(string $address): void {
        $this->db->update('peers', ['blacklisted' => 0, 'fails' => 0], 'address=?', [$address]);
    }

    public function propagate(string $endpoint, array $data): void {
        $peers = $this->getActive();
        $payload = json_encode($data);
        foreach ($peers as $peer) {
            $url = "http://{$peer['address']}:{$peer['port']}/{$endpoint}";
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => 'Content-Type: application/json',
                    'content' => $payload,
                    'timeout' => 3,
                ],
            ]);
            @file_get_contents($url, false, $ctx);
        }
    }

    public function heartbeat(): void {
        $this->db->insert('heartbeat', [
            'address' => NODE_HOST,
            'ip'      => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            'height'  => (new SChain($this->db))->getHeight(),
            'date'    => time(),
        ]);
    }

    public function sweepDead(): int {
        $cutoff = time() - 3600;
        return $this->db->query(
            "UPDATE peers SET fails=fails+1 WHERE last_seen<? AND blacklisted=0", [$cutoff]
        )->rowCount();
    }
}
