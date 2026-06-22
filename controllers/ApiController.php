<?php
defined('_SECURED') or die('Restricted access');

/**
 * ApiController — REST API dispatcher.
 * All public node API endpoints. Rate-limited, logged.
 *
 * Routes (via index.php?request=ajax&action=X):
 *  GET  status
 *  GET  blocks             ?from=&to=&limit=
 *  GET  block              ?id=|height=
 *  GET  transactions       ?address=&limit=&offset=
 *  GET  tx                 ?id=
 *  POST tx/send            {src,dst,val,fee,version,message,public_key,signature,date}
 *  POST tx/receive         (peer propagation)
 *  GET  mempool
 *  GET  peers
 *  POST peers/add          {hostname,ip}
 *  GET  accounts           ?address=
 *  GET  masternode
 *  GET  masternode/rewards ?public_key=
 *  GET  assets
 *  GET  assets/orderbook   ?id=
 *  POST assets/bid         {account,asset,price,val,signature}
 *  POST assets/ask         {account,asset,price,val,signature}
 *  GET  governance
 *  GET  explorer/search    ?q=
 */
class ApiController {
    private SCore $app;

    public function __construct(SCore $app) {
        $this->app = $app;
    }

    public function handle(string $action): void {
        $this->rateLimit();
        $this->logRequest($action);

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $input  = $method === 'POST' ? json_decode(file_get_contents('php://input'), true) ?? [] : [];

        try {
            $result = match ($action) {
                'status'              => $this->status(),
                'blocks'              => $this->blocks(),
                'block'               => $this->block(),
                'transactions'        => $this->transactions(),
                'tx'                  => $this->tx(),
                'tx/send'             => $this->txSend($input),
                'tx/receive'          => $this->txReceive($input),
                'mempool'             => $this->mempool(),
                'peers'               => $this->peers(),
                'peers/add'           => $this->peersAdd($input),
                'accounts'            => $this->accounts(),
                'masternode'          => $this->masternode(),
                'masternode/rewards'  => $this->masternodeRewards(),
                'assets'              => $this->assets(),
                'assets/orderbook'    => $this->assetsOrderbook(),
                'assets/bid'          => $this->assetsBid($input),
                'assets/ask'          => $this->assetsAsk($input),
                'governance'          => $this->governance(),
                'explorer/search'     => $this->explorerSearch(),
                default               => $this->notFound($action),
            };
            echo json_encode(['ok' => true, 'data' => $result]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ─── Endpoints ───────────────────────────────────────────

    private function status(): array {
        /** @var SChain $chain */
        $chain = $this->app->make('chain');
        /** @var SMasternode $mn */
        $mn    = $this->app->make('masternode');
        /** @var Config $cfg */
        $cfg   = $this->app->make('config');

        return array_merge($chain->getStats(), [
            'masternodes' => $mn->count(),
            'version'     => $cfg->version,
            'hostname'    => $cfg->hostname,
        ]);
    }

    private function blocks(): array {
        /** @var Database $db */
        $db    = $this->app->make('db');
        $from  = (int)($_GET['from']  ?? 0);
        $to    = (int)($_GET['to']    ?? 0);
        $limit = min((int)($_GET['limit'] ?? 50), 500);

        if ($from && $to) {
            $blocks = $db->fetchAll(
                'SELECT * FROM blocks WHERE height BETWEEN ? AND ? ORDER BY height ASC',
                [$from, $to]
            );
        } else {
            $blocks = $db->fetchAll(
                'SELECT * FROM blocks ORDER BY height DESC LIMIT ?', [$limit]
            );
        }
        return ['blocks' => $blocks];
    }

    private function block(): array {
        $db = $this->app->make('db');
        if (!empty($_GET['id'])) {
            $b = $db->fetchOne('SELECT * FROM blocks WHERE id = ?', [$_GET['id']]);
        } elseif (isset($_GET['height'])) {
            $b = $db->fetchOne('SELECT * FROM blocks WHERE height = ?', [(int)$_GET['height']]);
        } else {
            $b = $db->fetchOne('SELECT * FROM blocks ORDER BY height DESC LIMIT 1');
        }
        if (!$b) throw new RuntimeException('Block not found');
        return $b;
    }

    private function transactions(): array {
        /** @var STx $tx */
        $tx     = $this->app->make('tx');
        $addr   = $_GET['address'] ?? null;
        $limit  = min((int)($_GET['limit']  ?? 50), 500);
        $offset = (int)($_GET['offset'] ?? 0);

        if ($addr) {
            return ['transactions' => $tx->getByAddress($addr, $limit, $offset)];
        }
        $db = $this->app->make('db');
        return ['transactions' => $db->fetchAll(
            'SELECT * FROM transactions ORDER BY height DESC LIMIT ?', [$limit]
        )];
    }

    private function tx(): array {
        $id = $_GET['id'] ?? '';
        if (!$id) throw new InvalidArgumentException('id required');
        $tx = $this->app->make('tx');
        $r  = $tx->get($id);
        if (!$r) throw new RuntimeException('Transaction not found');
        return $r;
    }

    private function txSend(array $input): array {
        /** @var STx $tx */
        $tx  = $this->app->make('tx');
        $new = $tx->create($input);
        if (!$tx->addToMempool($new, $_SERVER['REMOTE_ADDR'] ?? null)) {
            throw new RuntimeException('Transaction rejected');
        }
        // Propagate
        $peers = $this->app->make('peers');
        $peers->propagateTx($new);
        return ['id' => $new['id']];
    }

    private function txReceive(array $input): array {
        $tx  = $this->app->make('tx');
        $new = $tx->create($input);
        $tx->addToMempool($new, $_SERVER['REMOTE_ADDR'] ?? null);
        return ['accepted' => true];
    }

    private function mempool(): array {
        $tx = $this->app->make('tx');
        return ['mempool' => $tx->getMempoolTxs(200)];
    }

    private function peers(): array {
        $peers = $this->app->make('peers');
        return ['peers' => $peers->getAll()];
    }

    private function peersAdd(array $input): array {
        if (empty($input['hostname']) || empty($input['ip']))
            throw new InvalidArgumentException('hostname and ip required');
        $peers = $this->app->make('peers');
        $peers->add($input['hostname'], $input['ip']);
        return ['added' => true];
    }

    private function accounts(): array {
        $address = $_GET['address'] ?? '';
        if (!$address) throw new InvalidArgumentException('address required');
        $wallet = $this->app->make('wallet');
        $acc    = $wallet->getAccount($address);
        if (!$acc) throw new RuntimeException('Account not found');

        // Include asset balances
        $assets = $this->app->make('assets');
        $acc['assets'] = $assets->getBalancesByAccount($address);
        return $acc;
    }

    private function masternode(): array {
        $mn = $this->app->make('masternode');
        return [
            'masternodes' => $mn->getAll(),
            'stats'       => $mn->getStats(),
        ];
    }

    private function masternodeRewards(): array {
        $pubKey = $_GET['public_key'] ?? '';
        if (!$pubKey) throw new InvalidArgumentException('public_key required');
        $mn = $this->app->make('masternode');
        return [
            'rewards' => $mn->getRewards($pubKey),
            'total'   => $mn->getTotalRewards($pubKey),
        ];
    }

    private function assets(): array {
        $assets = $this->app->make('assets');
        return ['assets' => $assets->getAll()];
    }

    private function assetsOrderbook(): array {
        $id = $_GET['id'] ?? '';
        if (!$id) throw new InvalidArgumentException('id required');
        $assets = $this->app->make('assets');
        return $assets->getOrderBook($id);
    }

    private function assetsBid(array $input): array {
        $assets = $this->app->make('assets');
        $id = $assets->placeBid(
            $input['account'],
            $input['asset'],
            (float)$input['price'],
            (int)$input['val']
        );
        return ['order_id' => $id];
    }

    private function assetsAsk(array $input): array {
        $assets = $this->app->make('assets');
        $id = $assets->placeAsk(
            $input['account'],
            $input['asset'],
            (float)$input['price'],
            (int)$input['val']
        );
        return ['order_id' => $id];
    }

    private function governance(): array {
        $gov = $this->app->make('governance');
        return [
            'proposals' => $gov->getAll(),
            'stats'     => $gov->getStats(),
        ];
    }

    private function explorerSearch(): array {
        $q  = trim($_GET['q'] ?? '');
        if (!$q) throw new InvalidArgumentException('q required');
        $db = $this->app->make('db');

        // Try block by id or height
        if (is_numeric($q)) {
            $b = $db->fetchOne('SELECT * FROM blocks WHERE height = ?', [(int)$q]);
            if ($b) return ['type' => 'block', 'data' => $b];
        }
        $b = $db->fetchOne('SELECT * FROM blocks WHERE id = ?', [$q]);
        if ($b) return ['type' => 'block', 'data' => $b];

        // Try tx
        $tx = $db->fetchOne('SELECT * FROM transactions WHERE id = ?', [$q]);
        if ($tx) return ['type' => 'transaction', 'data' => $tx];

        // Try account
        $acc = $db->fetchOne('SELECT * FROM accounts WHERE id = ? OR alias = ?', [$q, $q]);
        if ($acc) return ['type' => 'account', 'data' => $acc];

        throw new RuntimeException('Not found');
    }

    private function notFound(string $action): never {
        http_response_code(404);
        throw new RuntimeException("Unknown API action: $action");
    }

    // ─── Rate Limiting ───────────────────────────────────────

    private function rateLimit(): void {
        // Simple in-memory rate limit via DB apilog
        $cfg = $this->app->make('config');
        if (!$cfg->api_log_enabled) return;

        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $db    = $this->app->make('db');
        $count = (int)$db->fetchColumn(
            'SELECT COUNT(*) FROM apilog WHERE ip = ? AND date > ?',
            [$ip, time() - 60]
        );
        if ($count >= $cfg->api_rate_limit) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'error' => 'Rate limit exceeded']);
            exit;
        }
    }

    private function logRequest(string $action): void {
        $cfg = $this->app->make('config');
        if (!$cfg->api_log_enabled) return;

        $db = $this->app->make('db');
        $db->insert('apilog', [
            'endpoint' => $action,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'date'     => time(),
            'response' => 200,
        ]);
    }
}
