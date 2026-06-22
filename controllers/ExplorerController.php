<?php
defined('_SECURED') or die('Restricted access');

class ExplorerController {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function handle(string $action, array $params): void {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        try {
            switch ($action) {
                case 'search':    echo $this->search($params); break;
                case 'block':     echo $this->block($params); break;
                case 'tx':        echo $this->tx($params); break;
                case 'address':   echo $this->address($params); break;
                case 'richlist':  echo $this->richlist($params); break;
                case 'stats':     echo $this->stats(); break;
                case 'masternodes': echo $this->masternodes(); break;
                case 'assets':    echo $this->assets(); break;
                default:
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function search(array $p): string {
        $q = trim($p['q'] ?? '');
        if (!$q) return json_encode(['ok' => false, 'error' => 'Query required']);

        // Try block hash
        $block = $this->db->row("SELECT height FROM blocks WHERE hash=?", [$q]);
        if ($block) return json_encode(['ok' => true, 'type' => 'block', 'height' => $block['height']]);

        // Try block height
        if (is_numeric($q)) {
            $block = $this->db->row("SELECT hash FROM blocks WHERE height=?", [(int)$q]);
            if ($block) return json_encode(['ok' => true, 'type' => 'block', 'height' => (int)$q]);
        }

        // Try tx signature
        $tx = $this->db->row("SELECT id FROM transactions WHERE signature=?", [$q]);
        if ($tx) return json_encode(['ok' => true, 'type' => 'transaction', 'signature' => $q]);

        // Try address
        $acc = $this->db->row("SELECT address FROM accounts WHERE address=? OR alias=?", [$q, $q]);
        if ($acc) return json_encode(['ok' => true, 'type' => 'address', 'address' => $acc['address']]);

        return json_encode(['ok' => false, 'error' => 'Not found']);
    }

    private function block(array $p): string {
        $sblock = new SBlock($this->db);
        $b = isset($p['hash'])
            ? $sblock->getByHash($p['hash'])
            : $sblock->getByHeight((int)($p['height'] ?? 0));

        if (!$b) return json_encode(['ok' => false, 'error' => 'Block not found']);

        $b['tx_list'] = (new SChain($this->db))->getBlockTransactions($b['id']);
        $b['tx_count'] = count($b['tx_list']);

        return json_encode(['ok' => true, 'data' => $b]);
    }

    private function tx(array $p): string {
        if (empty($p['signature'])) return json_encode(['ok' => false, 'error' => 'Signature required']);
        $tx = (new STx($this->db))->getTx($p['signature']);
        if (!$tx) return json_encode(['ok' => false, 'error' => 'Transaction not found']);

        // Enrich with block info
        $block = $this->db->row("SELECT height, hash, date FROM blocks WHERE id=?", [$tx['block']]);
        $tx['block_hash'] = $block['hash'] ?? null;
        $tx['block_date'] = $block['date'] ?? null;

        return json_encode(['ok' => true, 'data' => $tx]);
    }

    private function address(array $p): string {
        if (empty($p['address'])) return json_encode(['ok' => false, 'error' => 'Address required']);

        $wallet  = new SWallet($this->db);

        // Resolve alias
        $address = $p['address'];
        if (!preg_match('/^[A-Za-z0-9]{30,64}$/', $address)) {
            $resolved = $wallet->resolveAlias($address);
            if ($resolved) $address = $resolved;
        }

        $account = $wallet->getAccount($address);
        if (!$account) return json_encode(['ok' => false, 'error' => 'Address not found']);

        $limit = min((int)($p['limit'] ?? 50), 200);
        $txs   = (new STx($this->db))->getByAddress($address, $limit);
        $mn    = $this->db->row("SELECT * FROM masternode WHERE address=?", [$address]);

        return json_encode(['ok' => true, 'data' => [
            'account'      => $account,
            'masternode'   => $mn,
            'transactions' => $txs,
            'tx_count'     => count($txs),
        ]]);
    }

    private function richlist(array $p): string {
        $limit = min((int)($p['limit'] ?? 100), 500);
        $rows  = $this->db->rows(
            "SELECT address, alias, balance FROM accounts ORDER BY balance DESC LIMIT ?", [$limit]
        );
        $total = (float)$this->db->val("SELECT SUM(balance) FROM accounts");
        foreach ($rows as &$r) {
            $r['pct'] = $total > 0 ? round((float)$r['balance'] / $total * 100, 4) : 0;
        }
        return json_encode(['ok' => true, 'data' => $rows]);
    }

    private function stats(): string {
        $chain = new SChain($this->db);
        $top   = $chain->getTop();
        $stats = $chain->getStats();
        $stats['last_block_time'] = $top ? $top['date'] : null;
        $stats['last_block_hash'] = $top ? $top['hash'] : null;
        $stats['avg_block_time']  = $this->avgBlockTime();
        return json_encode(['ok' => true, 'data' => $stats]);
    }

    private function masternodes(): string {
        $mn   = new SMasternode($this->db);
        $list = $mn->getAll(200);
        return json_encode(['ok' => true, 'data' => $list, 'count' => $mn->count()]);
    }

    private function assets(): string {
        $list = (new SAssets($this->db))->listAssets(100);
        return json_encode(['ok' => true, 'data' => $list]);
    }

    private function avgBlockTime(): ?float {
        $row = $this->db->row(
            "SELECT AVG(diff) AS avg FROM (
                SELECT b1.date - b2.date AS diff
                FROM blocks b1
                JOIN blocks b2 ON b2.height = b1.height - 1
                ORDER BY b1.height DESC
                LIMIT 100
            ) sub"
        );
        return $row ? round((float)$row['avg'], 2) : null;
    }
}
