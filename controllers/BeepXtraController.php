<?php
defined('_SECURED') or die('Restricted access');

class BeepXtraController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function handle($action, $params) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        try {
            switch ($action) {
                case 'loyalty_reward': echo $this->loyaltyReward($params); break;
                case 'merchant_pay':   echo $this->merchantPay($params); break;
                case 'check_balance':  echo $this->checkBalance($params); break;
                case 'wallet_info':    echo $this->walletInfo($params); break;
                case 'tx_history':     echo $this->txHistory($params); break;
                case 'asset_balance':  echo $this->assetBalance($params); break;
                case 'sdk_send':       echo $this->sdkSend($params); break;
                default:
                    http_response_code(404);
                    echo json_encode(array('ok' => false, 'error' => 'Unknown action'));
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
        }
    }

    private function loyaltyReward($p) {
        $required = array('src', 'dst', 'val', 'signature', 'public_key');
        foreach ($required as $f) {
            if (empty($p[$f])) return json_encode(array('ok' => false, 'error' => "Missing: $f"));
        }
        $tx = new STx($this->db);
        $txData = $tx->build(array(
            'type'       => TX_TRANSFER,
            'src'        => $p['src'],
            'dst'        => $p['dst'],
            'val'        => $p['val'],
            'message'    => 'loyalty_reward',
            'signature'  => $p['signature'],
            'public_key' => $p['public_key'],
            'date'       => time(),
        ));
        $ok = $tx->addToMempool($txData);
        return json_encode(array('ok' => $ok, 'error' => $ok ? null : 'Rejected'));
    }

    private function merchantPay($p) {
        $required = array('src', 'dst', 'val', 'signature', 'public_key', 'merchant_ref');
        foreach ($required as $f) {
            if (empty($p[$f])) return json_encode(array('ok' => false, 'error' => "Missing: $f"));
        }
        $tx = new STx($this->db);
        $txData = $tx->build(array(
            'type'       => TX_TRANSFER,
            'src'        => $p['src'],
            'dst'        => $p['dst'],
            'val'        => $p['val'],
            'message'    => json_encode(array('type' => 'merchant_pay', 'ref' => $p['merchant_ref'])),
            'signature'  => $p['signature'],
            'public_key' => $p['public_key'],
            'date'       => time(),
        ));
        $ok = $tx->addToMempool($txData);
        if ($ok) (new SPeers($this->db))->propagate('api/tx', $txData);
        return json_encode(array('ok' => $ok));
    }

    private function checkBalance($p) {
        if (empty($p['address'])) return json_encode(array('ok' => false, 'error' => 'Address required'));
        $wallet = new SWallet($this->db);
        if (strpos($p['address'], '@') === 0 || !preg_match('/^[A-Za-z0-9]{20,64}$/', $p['address'])) {
            $resolved = $wallet->resolveAlias($p['address']);
            if (!$resolved) return json_encode(array('ok' => false, 'error' => 'Alias not found'));
            $p['address'] = $resolved;
        }
        return json_encode(array('ok' => true, 'data' => array(
            'address' => $p['address'],
            'balance' => $wallet->getBalance($p['address']),
            'pending' => $wallet->getPendingBalance($p['address']),
        )));
    }

    private function walletInfo($p) {
        if (empty($p['address'])) return json_encode(array('ok' => false, 'error' => 'Address required'));
        $wallet  = new SWallet($this->db);
        $account = $wallet->getAccount($p['address']);
        if (!$account) return json_encode(array('ok' => false, 'error' => 'Account not found'));
        $mn = $this->db->row("SELECT status, collateral FROM masternode WHERE address=?", array($p['address']));
        return json_encode(array('ok' => true, 'data' => array(
            'address'    => $account['address'],
            'alias'      => $account['alias'],
            'balance'    => $account['balance'],
            'pending'    => $wallet->getPendingBalance($p['address']),
            'masternode' => $mn ?: null,
            'first_seen' => $account['first_seen'],
            'last_seen'  => $account['last_seen'],
        )));
    }

    private function txHistory($p) {
        if (empty($p['address'])) return json_encode(array('ok' => false, 'error' => 'Address required'));
        $limit = min((int)(isset($p['limit']) ? $p['limit'] : 50), 200);
        $txs   = (new STx($this->db))->getByAddress($p['address'], $limit);
        return json_encode(array('ok' => true, 'data' => $txs));
    }

    private function assetBalance($p) {
        if (empty($p['address']) || empty($p['asset_id'])) {
            return json_encode(array('ok' => false, 'error' => 'address and asset_id required'));
        }
        $balance = (new SAssets($this->db))->getAssetBalance($p['asset_id'], $p['address']);
        return json_encode(array('ok' => true, 'data' => array('balance' => $balance)));
    }

    private function sdkSend($p) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $p;
        $stx = new STx($this->db);
        $ok  = $stx->addToMempool($data);
        if ($ok) (new SPeers($this->db))->propagate('api/tx', $data);
        return json_encode(array('ok' => $ok));
    }
}
