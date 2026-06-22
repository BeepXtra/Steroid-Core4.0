<?php
defined('_SECURED') or die('Restricted access');

class ApiController {
    private $app;

    public function __construct($app) {
        $this->app = $app;
    }

    public function handle($action) {
        $this->rateLimit();
        $this->logRequest($action);

        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $input  = array();
        if ($method === 'POST') {
            $raw   = file_get_contents('php://input');
            $input = $raw ? (json_decode($raw, true) ?: array()) : array();
        }

        try {
            switch ($action) {
                case 'status':             $result = $this->status();              break;
                case 'blocks':             $result = $this->blocks();              break;
                case 'block':              $result = $this->block();               break;
                case 'transactions':       $result = $this->transactions();        break;
                case 'tx':                 $result = $this->tx();                  break;
                case 'tx/send':            $result = $this->txSend($input);        break;
                case 'tx/receive':         $result = $this->txReceive($input);     break;
                case 'mempool':            $result = $this->mempool();             break;
                case 'peers':              $result = $this->peers();               break;
                case 'peers/add':          $result = $this->peersAdd($input);      break;
                case 'accounts':           $result = $this->accounts();            break;
                case 'masternode':         $result = $this->masternode();          break;
                case 'masternode/rewards': $result = $this->masternodeRewards();   break;
                case 'assets':             $result = $this->assets();              break;
                case 'assets/orderbook':   $result = $this->assetsOrderbook();     break;
                case 'assets/bid':         $result = $this->assetsBid($input);     break;
                case 'assets/ask':         $result = $this->assetsAsk($input);     break;
                case 'governance':         $result = $this->governance();          break;
                case 'explorer/search':    $result = $this->explorerSearch();      break;
                default:
                    http_response_code(404);
                    throw new RuntimeException("Unknown API action: $action");
            }
            echo json_encode(array('ok' => true, 'data' => $result));
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
        }
    }

    private function status() {
        $chain = $this->app->make('chain');
        $mn    = $this->app->make('masternode');
        $cfg   = $this->app->make('config');
        return array_merge($chain->getStats(), array(
            'masternodes' => $mn->count(),
            'version'     => $cfg->version,
            'hostname'    => $cfg->hostname,
        ));
    }

    private function blocks() {
        $db    = $this->app->make('db');
        $from  = (int)(isset($_GET['from'])  ? $_GET['from']  : 0);
        $to    = (int)(isset($_GET['to'])    ? $_GET['to']    : 0);
        $limit = min((int)(isset($_GET['limit']) ? $_GET['limit'] : 50), 500);
        if ($from && $to) {
            $blocks = $db->fetchAll(
                'SELECT * FROM blocks WHERE height BETWEEN ? AND ? ORDER BY height ASC',
                array($from, $to)
            );
        } else {
            $blocks = $db->fetchAll('SELECT * FROM blocks ORDER BY height DESC LIMIT ?', array($limit));
        }
        return array('blocks' => $blocks);
    }

    private function block() {
        $db = $this->app->make('db');
        if (!empty($_GET['id'])) {
            $b = $db->fetchOne('SELECT * FROM blocks WHERE id = ?', array($_GET['id']));
        } elseif (isset($_GET['height'])) {
            $b = $db->fetchOne('SELECT * FROM blocks WHERE height = ?', array((int)$_GET['height']));
        } else {
            $b = $db->fetchOne('SELECT * FROM blocks ORDER BY height DESC LIMIT 1');
        }
        if (!$b) throw new RuntimeException('Block not found');
        return $b;
    }

    private function transactions() {
        $tx     = $this->app->make('tx');
        $addr   = isset($_GET['address']) ? $_GET['address'] : null;
        $limit  = min((int)(isset($_GET['limit'])  ? $_GET['limit']  : 50), 500);
        $offset = (int)(isset($_GET['offset']) ? $_GET['offset'] : 0);
        if ($addr) return array('transactions' => $tx->getByAddress($addr, $limit, $offset));
        $db = $this->app->make('db');
        return array('transactions' => $db->fetchAll('SELECT * FROM transactions ORDER BY height DESC LIMIT ?', array($limit)));
    }

    private function tx() {
        $id = isset($_GET['id']) ? $_GET['id'] : '';
        if (!$id) throw new InvalidArgumentException('id required');
        $tx = $this->app->make('tx');
        $r  = $tx->get($id);
        if (!$r) throw new RuntimeException('Transaction not found');
        return $r;
    }

    private function txSend($input) {
        $tx  = $this->app->make('tx');
        $new = $tx->create($input);
        if (!$tx->addToMempool($new, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null))
            throw new RuntimeException('Transaction rejected');
        $peers = $this->app->make('peers');
        $peers->propagateTx($new);
        return array('id' => $new['id']);
    }

    private function txReceive($input) {
        $tx  = $this->app->make('tx');
        $new = $tx->create($input);
        $tx->addToMempool($new, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);
        return array('accepted' => true);
    }

    private function mempool() {
        return array('mempool' => $this->app->make('tx')->getMempoolTxs(200));
    }

    private function peers() {
        return array('peers' => $this->app->make('peers')->getAll());
    }

    private function peersAdd($input) {
        if (empty($input['hostname']) || empty($input['ip']))
            throw new InvalidArgumentException('hostname and ip required');
        $this->app->make('peers')->add($input['hostname'], $input['ip']);
        return array('added' => true);
    }

    private function accounts() {
        $address = isset($_GET['address']) ? $_GET['address'] : '';
        if (!$address) throw new InvalidArgumentException('address required');
        $wallet = $this->app->make('wallet');
        $acc    = $wallet->getAccount($address);
        if (!$acc) throw new RuntimeException('Account not found');
        $assets        = $this->app->make('assets');
        $acc['assets'] = $assets->getBalancesByAccount($address);
        return $acc;
    }

    private function masternode() {
        $mn = $this->app->make('masternode');
        return array('masternodes' => $mn->getAll(), 'stats' => $mn->getStats());
    }

    private function masternodeRewards() {
        $pubKey = isset($_GET['public_key']) ? $_GET['public_key'] : '';
        if (!$pubKey) throw new InvalidArgumentException('public_key required');
        $mn = $this->app->make('masternode');
        return array('rewards' => $mn->getRewards($pubKey), 'total' => $mn->getTotalRewards($pubKey));
    }

    private function assets() {
        return array('assets' => $this->app->make('assets')->getAll());
    }

    private function assetsOrderbook() {
        $id = isset($_GET['id']) ? $_GET['id'] : '';
        if (!$id) throw new InvalidArgumentException('id required');
        return $this->app->make('assets')->getOrderBook($id);
    }

    private function assetsBid($input) {
        $assets = $this->app->make('assets');
        $id = $assets->placeBid($input['account'], $input['asset'], (float)$input['price'], (int)$input['val']);
        return array('order_id' => $id);
    }

    private function assetsAsk($input) {
        $assets = $this->app->make('assets');
        $id = $assets->placeAsk($input['account'], $input['asset'], (float)$input['price'], (int)$input['val']);
        return array('order_id' => $id);
    }

    private function governance() {
        $gov = $this->app->make('governance');
        return array('proposals' => $gov->getAll(), 'stats' => $gov->getStats());
    }

    private function explorerSearch() {
        $q  = trim(isset($_GET['q']) ? $_GET['q'] : '');
        if (!$q) throw new InvalidArgumentException('q required');
        $db = $this->app->make('db');
        if (is_numeric($q)) {
            $b = $db->fetchOne('SELECT * FROM blocks WHERE height = ?', array((int)$q));
            if ($b) return array('type' => 'block', 'data' => $b);
        }
        $b = $db->fetchOne('SELECT * FROM blocks WHERE id = ?', array($q));
        if ($b) return array('type' => 'block', 'data' => $b);
        $tx = $db->fetchOne('SELECT * FROM transactions WHERE id = ?', array($q));
        if ($tx) return array('type' => 'transaction', 'data' => $tx);
        $acc = $db->fetchOne('SELECT * FROM accounts WHERE id = ? OR alias = ?', array($q, $q));
        if ($acc) return array('type' => 'account', 'data' => $acc);
        throw new RuntimeException('Not found');
    }

    private function rateLimit() {
        $cfg = $this->app->make('config');
        if (!$cfg->api_log_enabled) return;
        $ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $db    = $this->app->make('db');
        $count = (int)$db->fetchColumn('SELECT COUNT(*) FROM apilog WHERE ip = ? AND date > ?', array($ip, time() - 60));
        if ($count >= $cfg->api_rate_limit) {
            http_response_code(429);
            echo json_encode(array('ok' => false, 'error' => 'Rate limit exceeded'));
            exit;
        }
    }

    private function logRequest($action) {
        $cfg = $this->app->make('config');
        if (!$cfg->api_log_enabled) return;
        $db = $this->app->make('db');
        $db->insert('apilog', array(
            'endpoint' => $action,
            'ip'       => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
            'date'     => time(),
            'response' => 200,
        ));
    }
}
