<?php
defined('_SECURED') or die('Restricted access');

class ApiController {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function handle(string $endpoint, array $params, string $method): void {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        try {
            switch ($endpoint) {
                case 'status':        echo $this->status(); break;
                case 'blocks':        echo $this->blocks($params); break;
                case 'block':         echo $this->block($params); break;
                case 'transactions':  echo $this->transactions($params); break;
                case 'tx':            echo $this->tx($params, $method); break;
                case 'mempool':       echo $this->mempool(); break;
                case 'accounts':      echo $this->accounts($params); break;
                case 'account':       echo $this->account($params); break;
                case 'peers':         echo $this->peers($params, $method); break;
                case 'masternode':    echo $this->masternode($params); break;
                case 'assets':        echo $this->assets($params); break;
                case 'asset':         echo $this->asset($params); break;
                case 'orderbook':     echo $this->orderbook($params); break;
                case 'governance':    echo $this->governance($params); break;
                case 'votes':         echo $this->votes($params); break;
                case 'network':       echo $this->network(); break;
                case 'send':          echo $this->send($params); break;
                case 'mine':          echo $this->mine($params); break;
                default:
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'error' => 'Unknown endpoint']);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function status(): string {
        $chain = new SChain($this->db);
        return json_encode(['ok' => true, 'data' => $chain->getStatus()]);
    }

    private function blocks(array $p): string {
        $limit  = min((int)($p['limit'] ?? 20), 100);
        $offset = (int)($p['offset'] ?? 0);
        $blocks = $this->db->rows(
            "SELECT * FROM blocks ORDER BY height DESC LIMIT ? OFFSET ?", [$limit, $offset]
        );
        return json_encode(['ok' => true, 'data' => $blocks]);
    }

    private function block(array $p): string {
        $block = new SBlock($this->db);
        $b = isset($p['hash'])
            ? $block->getByHash($p['hash'])
            : $block->getByHeight((int)($p['height'] ?? 0));
        if (!$b) return json_encode(['ok' => false, 'error' => 'Block not found']);
        $b['transactions_list'] = (new SChain($this->db))->getBlockTransactions($b['id']);
        return json_encode(['ok' => true, 'data' => $b]);
    }

    private function transactions(array $p): string {
        $limit  = min((int)($p['limit'] ?? 20), 100);
        $offset = (int)($p['offset'] ?? 0);
        $txs    = $this->db->rows(
            "SELECT * FROM transactions ORDER BY id DESC LIMIT ? OFFSET ?", [$limit, $offset]
        );
        return json_encode(['ok' => true, 'data' => $txs]);
    }

    private function tx(array $p, string $method): string {
        if ($method === 'POST') {
            // Receive a transaction from peer
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $tx   = new STx($this->db);
            $ok   = $tx->addToMempool($data, $_SERVER['REMOTE_ADDR'] ?? '');
            return json_encode(['ok' => $ok, 'error' => $ok ? null : 'Rejected']);
        }
        // GET — lookup by signature or address
        if (!empty($p['signature'])) {
            $t = (new STx($this->db))->getTx($p['signature']);
            return $t ? json_encode(['ok' => true, 'data' => $t])
                      : json_encode(['ok' => false, 'error' => 'Not found']);
        }
        if (!empty($p['address'])) {
            $txs = (new STx($this->db))->getByAddress($p['address'], (int)($p['limit'] ?? 50));
            return json_encode(['ok' => true, 'data' => $txs]);
        }
        return json_encode(['ok' => false, 'error' => 'Provide signature or address']);
    }

    private function mempool(): string {
        $txs = (new STx($this->db))->getMempool(100);
        return json_encode(['ok' => true, 'data' => $txs, 'count' => count($txs)]);
    }

    private function accounts(array $p): string {
        $limit  = min((int)($p['limit'] ?? 20), 100);
        $offset = (int)($p['offset'] ?? 0);
        $accs   = $this->db->rows(
            "SELECT * FROM accounts ORDER BY balance DESC LIMIT ? OFFSET ?", [$limit, $offset]
        );
        return json_encode(['ok' => true, 'data' => $accs]);
    }

    private function account(array $p): string {
        if (empty($p['address'])) return json_encode(['ok' => false, 'error' => 'Address required']);
        $wallet = new SWallet($this->db);
        $acc    = $wallet->getAccount($p['address']);
        if (!$acc) return json_encode(['ok' => false, 'error' => 'Account not found']);
        $acc['pending'] = $wallet->getPendingBalance($p['address']);
        return json_encode(['ok' => true, 'data' => $acc]);
    }

    private function peers(array $p, string $method): string {
        $peers = new SPeers($this->db);
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $peers->register($data['address'] ?? '', (int)($data['port'] ?? 8080), $data['version'] ?? '');
            return json_encode(['ok' => true]);
        }
        return json_encode(['ok' => true, 'data' => $peers->getActive()]);
    }

    private function masternode(array $p): string {
        $mn = new SMasternode($this->db);
        if (!empty($p['address'])) {
            $node = $mn->get($p['address']);
            if (!$node) return json_encode(['ok' => false, 'error' => 'Not found']);
            $node['rewards'] = $mn->getRewards($p['address']);
            return json_encode(['ok' => true, 'data' => $node]);
        }
        return json_encode(['ok' => true, 'data' => $mn->getAll(), 'count' => $mn->count()]);
    }

    private function assets(array $p): string {
        $assets = (new SAssets($this->db))->listAssets((int)($p['limit'] ?? 50));
        return json_encode(['ok' => true, 'data' => $assets]);
    }

    private function asset(array $p): string {
        if (empty($p['id'])) return json_encode(['ok' => false, 'error' => 'Asset ID required']);
        $asset = (new SAssets($this->db))->getAsset($p['id']);
        return $asset ? json_encode(['ok' => true, 'data' => $asset])
                      : json_encode(['ok' => false, 'error' => 'Not found']);
    }

    private function orderbook(array $p): string {
        if (empty($p['id'])) return json_encode(['ok' => false, 'error' => 'Asset ID required']);
        $book = (new SAssets($this->db))->getOrderBook($p['id']);
        return json_encode(['ok' => true, 'data' => $book]);
    }

    private function governance(array $p): string {
        $gov = new SGovernance($this->db);
        return json_encode(['ok' => true, 'data' => [
            'proposals' => $gov->getProposals(),
            'votes'     => $gov->getAllVotes(),
        ]]);
    }

    private function votes(array $p): string {
        $gov   = new SGovernance($this->db);
        $param = $p['param'] ?? '';
        return json_encode(['ok' => true, 'data' => $param ? $gov->getVotes($param) : $gov->getAllVotes()]);
    }

    private function network(): string {
        $chain = new SChain($this->db);
        return json_encode(['ok' => true, 'data' => $chain->getStats()]);
    }

    private function send(array $p): string {
        $data = json_decode(file_get_contents('php://input'), true) ?? $p;
        $stx  = new STx($this->db);
        $ok   = $stx->addToMempool($data);
        if ($ok) {
            (new SPeers($this->db))->propagate('api/tx', $data);
            return json_encode(['ok' => true, 'message' => 'Transaction accepted']);
        }
        return json_encode(['ok' => false, 'error' => 'Transaction rejected']);
    }

    private function mine(array $p): string {
        if (empty($p['address']) || empty($p['private_key']) || empty($p['public_key'])) {
            return json_encode(['ok' => false, 'error' => 'address, private_key, public_key required']);
        }
        $result = (new SMine($this->db))->mine($p['address'], $p['private_key'], $p['public_key']);
        return $result
            ? json_encode(['ok' => true, 'data' => $result])
            : json_encode(['ok' => false, 'message' => 'No block found this round']);
    }
}
