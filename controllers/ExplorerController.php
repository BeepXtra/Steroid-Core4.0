<?php
defined('_SECURED') or die('Restricted access');

/**
 * ExplorerController — Blockchain explorer API.
 * Used by external explorer UIs and wallets.
 */
class ExplorerController {
    private SCore $app;

    public function __construct(SCore $app) {
        $this->app = $app;
    }

    public function index(): void {
        header('Content-Type: application/json');
        $action = $_GET['action'] ?? 'stats';
        try {
            $result = match ($action) {
                'stats'        => $this->stats(),
                'blocks'       => $this->blocks(),
                'block'        => $this->block(),
                'tx'           => $this->tx(),
                'account'      => $this->account(),
                'richlist'     => $this->richlist(),
                'search'       => $this->search(),
                default        => throw new RuntimeException("Unknown explorer action: $action"),
            };
            echo json_encode(['ok' => true, 'data' => $result]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function stats(): array {
        $db  = $this->app->make('db');
        $cfg = $this->app->make('config');
        $mn  = $this->app->make('masternode');

        $top = $db->fetchOne('SELECT * FROM blocks ORDER BY height DESC LIMIT 1');
        return [
            'height'       => (int)($top['height'] ?? 0),
            'top_block'    => $top['id'] ?? null,
            'difficulty'   => $top['difficulty'] ?? null,
            'total_supply' => $db->fetchColumn('SELECT COALESCE(SUM(balance),0) FROM accounts'),
            'accounts'     => (int)$db->fetchColumn('SELECT COUNT(*) FROM accounts'),
            'transactions' => (int)$db->fetchColumn('SELECT COUNT(*) FROM transactions'),
            'masternodes'  => $mn->count(),
            'version'      => $cfg->version,
        ];
    }

    private function blocks(): array {
        $db    = $this->app->make('db');
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $offset= ($page - 1) * $limit;

        $blocks = $db->fetchAll(
            'SELECT b.*, COUNT(t.id) as tx_count
             FROM blocks b
             LEFT JOIN transactions t ON t.height = b.height
             GROUP BY b.height
             ORDER BY b.height DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
        $total = (int)$db->fetchColumn('SELECT COUNT(*) FROM blocks');
        return ['blocks' => $blocks, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    private function block(): array {
        $db = $this->app->make('db');
        if (!empty($_GET['id'])) {
            $b = $db->fetchOne('SELECT * FROM blocks WHERE id = ?', [$_GET['id']]);
        } elseif (isset($_GET['height'])) {
            $b = $db->fetchOne('SELECT * FROM blocks WHERE height = ?', [(int)$_GET['height']]);
        } else {
            throw new InvalidArgumentException('id or height required');
        }
        if (!$b) throw new RuntimeException('Block not found');

        $b['transactions'] = $db->fetchAll(
            'SELECT * FROM transactions WHERE height = ? ORDER BY date ASC',
            [$b['height']]
        );
        return $b;
    }

    private function tx(): array {
        $id = $_GET['id'] ?? '';
        if (!$id) throw new InvalidArgumentException('id required');
        $db = $this->app->make('db');
        $tx = $db->fetchOne('SELECT * FROM transactions WHERE id = ?', [$id]);
        if (!$tx) throw new RuntimeException('Transaction not found');
        return $tx;
    }

    private function account(): array {
        $addr = $_GET['address'] ?? '';
        if (!$addr) throw new InvalidArgumentException('address required');

        $wallet = $this->app->make('wallet');
        $acc    = $wallet->getAccount($addr);
        if (!$acc) throw new RuntimeException('Account not found');

        $db   = $this->app->make('db');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $lim  = min((int)($_GET['limit'] ?? 20), 100);
        $off  = ($page - 1) * $lim;

        $acc['transactions'] = $db->fetchAll(
            'SELECT * FROM transactions WHERE src = ? OR dst = ? ORDER BY height DESC LIMIT ? OFFSET ?',
            [$addr, $addr, $lim, $off]
        );
        $acc['tx_count'] = (int)$db->fetchColumn(
            'SELECT COUNT(*) FROM transactions WHERE src = ? OR dst = ?',
            [$addr, $addr]
        );

        $assets = $this->app->make('assets');
        $acc['assets'] = $assets->getBalancesByAccount($addr);

        return $acc;
    }

    private function richlist(): array {
        $db    = $this->app->make('db');
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        return ['richlist' => $db->fetchAll(
            'SELECT id, balance, alias FROM accounts ORDER BY balance DESC LIMIT ?',
            [$limit]
        )];
    }

    private function search(): array {
        $q = trim($_GET['q'] ?? '');
        if (!$q) throw new InvalidArgumentException('q required');
        $db = $this->app->make('db');

        if (is_numeric($q)) {
            $b = $db->fetchOne('SELECT * FROM blocks WHERE height = ?', [(int)$q]);
            if ($b) return ['type' => 'block', 'data' => $b];
        }
        if (strlen($q) === 64) {
            $b = $db->fetchOne('SELECT * FROM blocks WHERE id = ?', [$q]);
            if ($b) return ['type' => 'block', 'data' => $b];
            $t = $db->fetchOne('SELECT * FROM transactions WHERE id = ?', [$q]);
            if ($t) return ['type' => 'transaction', 'data' => $t];
        }
        $a = $db->fetchOne('SELECT * FROM accounts WHERE id = ? OR alias = ?', [$q, $q]);
        if ($a) return ['type' => 'account', 'data' => $a];

        throw new RuntimeException('Nothing found for: ' . $q);
    }
}
