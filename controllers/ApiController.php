<?php
defined('_SECURED') or die('Restricted access');

class ApiController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function handle($endpoint, $params, $method) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        try {
            switch ($endpoint) {
                case 'status':       echo $this->status(); break;
                case 'blocks':       echo $this->blocks($params); break;
                case 'block':        echo $this->block($params); break;
                case 'transactions': echo $this->transactions($params); break;
                case 'tx':           echo $this->tx($params, $method); break;
                case 'mempool':      echo $this->mempool(); break;
                case 'accounts':     echo $this->accounts($params); break;
                case 'account':      echo $this->account($params); break;
                case 'peers':        echo $this->peers($params, $method); break;
                case 'masternode':   echo $this->masternode($params); break;
                case 'assets':       echo $this->assets($params); break;
                case 'asset':        echo $this->asset($params); break;
                case 'orderbook':    echo $this->orderbook($params); break;
                case 'governance':   echo $this->governance($params); break;
                case 'votes':        echo $this->votes($params); break;
                case 'network':      echo $this->network(); break;
                case 'send':         echo $this->send($params); break;
                case 'mine':         echo $this->mine($params); break;
                default:
                    http_response_code(404);
                    echo json_encode(array('ok' => false, 'error' => 'Unknown endpoint'));
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
        }
    }

    private function status() {
        return json_encode(array('ok' => true, 'data' => (new SChain($this->db))->getStatus()));
    }

    private function blocks($p) {
        $limit  = min((int)(isset($p['limit']) ? $p['limit'] : 20), 100);
        $offset = (int)(isset($p['offset']) ? $p['offset'] : 0);
        $blocks = $this->db->rows("SELECT * FROM blocks ORDER BY height DESC LIMIT ? OFFSET ?", array($limit, $offset));
        return json_encode(array('ok' => true, 'data' => $blocks));
    }

    private function block($p) {
        $sblock = new SBlock($this->db);
        $b = isset($p['hash'])
            ? $sblock->getByHash($p['hash'])
            : $sblock->getByHeight((int)(isset($p['height']) ? $p['height'] : 0));
        if (!$b) return json_encode(array('ok' => false, 'error' => 'Block not found'));
        $b['transactions_list'] = (new SChain($this->db))->getBlockTransactions($b['id']);
        return json_encode(array('ok' => true, 'data' => $b));
    }

    private function transactions($p) {
        $limit  = min((int)(isset($p['limit']) ? $p['limit'] : 20), 100);
        $offset = (int)(isset($p['offset']) ? $p['offset'] : 0);
        $txs    = $this->db->rows("SELECT * FROM transactions ORDER BY id DESC LIMIT ? OFFSET ?", array($limit, $offset));
        return json_encode(array('ok' => true, 'data' => $txs));
    }

    private function tx($p, $method) {
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = array();
            $tx = new STx($this->db);
            $ok = $tx->addToMempool($data, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
            return json_encode(array('ok' => $ok, 'error' => $ok ? null : 'Rejected'));
        }
        if (!empty($p['signature'])) {
            $t = (new STx($this->db))->getTx($p['signature']);
            return $t ? json_encode(array('ok' => true, 'data' => $t))
                      : json_encode(array('ok' => false, 'error' => 'Not found'));
        }
        if (!empty($p['address'])) {
            $txs = (new STx($this->db))->getByAddress($p['address'], (int)(isset($p['limit']) ? $p['limit'] : 50));
            return json_encode(array('ok' => true, 'data' => $txs));
        }
        return json_encode(array('ok' => false, 'error' => 'Provide signature or address'));
    }

    private function mempool() {
        $txs = (new STx($this->db))->getMempool(100);
        return json_encode(array('ok' => true, 'data' => $txs, 'count' => count($txs)));
    }

    private function accounts($p) {
        $limit  = min((int)(isset($p['limit']) ? $p['limit'] : 20), 100);
        $offset = (int)(isset($p['offset']) ? $p['offset'] : 0);
        $accs   = $this->db->rows("SELECT * FROM accounts ORDER BY balance DESC LIMIT ? OFFSET ?", array($limit, $offset));
        return json_encode(array('ok' => true, 'data' => $accs));
    }

    private function account($p) {
        if (empty($p['address'])) return json_encode(array('ok' => false, 'error' => 'Address required'));
        $wallet = new SWallet($this->db);
        $acc    = $wallet->getAccount($p['address']);
        if (!$acc) return json_encode(array('ok' => false, 'error' => 'Account not found'));
        $acc['pending'] = $wallet->getPendingBalance($p['address']);
        return json_encode(array('ok' => true, 'data' => $acc));
    }

    private function peers($p, $method) {
        $peers = new SPeers($this->db);
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = array();
            $peers->register(
                isset($data['address']) ? $data['address'] : '',
                (int)(isset($data['port']) ? $data['port'] : 8080),
                isset($data['version']) ? $data['version'] : ''
            );
            return json_encode(array('ok' => true));
        }
        return json_encode(array('ok' => true, 'data' => $peers->getActive()));
    }

    private function masternode($p) {
        $mn = new SMasternode($this->db);
        if (!empty($p['address'])) {
            $node = $mn->get($p['address']);
            if (!$node) return json_encode(array('ok' => false, 'error' => 'Not found'));
            $node['rewards'] = $mn->getRewards($p['address']);
            return json_encode(array('ok' => true, 'data' => $node));
        }
        return json_encode(array('ok' => true, 'data' => $mn->getAll(), 'count' => $mn->count()));
    }

    private function assets($p) {
        $assets = (new SAssets($this->db))->listAssets((int)(isset($p['limit']) ? $p['limit'] : 50));
        return json_encode(array('ok' => true, 'data' => $assets));
    }

    private function asset($p) {
        if (empty($p['id'])) return json_encode(array('ok' => false, 'error' => 'Asset ID required'));
        $asset = (new SAssets($this->db))->getAsset($p['id']);
        return $asset ? json_encode(array('ok' => true, 'data' => $asset))
                      : json_encode(array('ok' => false, 'error' => 'Not found'));
    }

    private function orderbook($p) {
        if (empty($p['id'])) return json_encode(array('ok' => false, 'error' => 'Asset ID required'));
        $book = (new SAssets($this->db))->getOrderBook($p['id']);
        return json_encode(array('ok' => true, 'data' => $book));
    }

    private function governance($p) {
        $gov = new SGovernance($this->db);
        return json_encode(array('ok' => true, 'data' => array(
            'proposals' => $gov->getProposals(),
            'votes'     => $gov->getAllVotes(),
        )));
    }

    private function votes($p) {
        $gov   = new SGovernance($this->db);
        $param = isset($p['param']) ? $p['param'] : '';
        return json_encode(array('ok' => true, 'data' => $param ? $gov->getVotes($param) : $gov->getAllVotes()));
    }

    private function network() {
        return json_encode(array('ok' => true, 'data' => (new SChain($this->db))->getStats()));
    }

    private function send($p) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $p;
        $stx = new STx($this->db);
        $ok  = $stx->addToMempool($data);
        if ($ok) (new SPeers($this->db))->propagate('api/tx', $data);
        return $ok
            ? json_encode(array('ok' => true, 'message' => 'Transaction accepted'))
            : json_encode(array('ok' => false, 'error' => 'Transaction rejected'));
    }

    private function mine($p) {
        if (empty($p['address']) || empty($p['private_key']) || empty($p['public_key'])) {
            return json_encode(array('ok' => false, 'error' => 'address, private_key, public_key required'));
        }
        $result = (new SMine($this->db))->mine($p['address'], $p['private_key'], $p['public_key']);
        return $result
            ? json_encode(array('ok' => true, 'data' => $result))
            : json_encode(array('ok' => false, 'message' => 'No block found this round'));
    }
}
