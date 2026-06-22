<?php
defined('_SECURED') or die('Restricted access');

class BeepXtraController {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function handle(string $action, array $params): void {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        try {
            switch ($action) {
                case 'loyalty_reward':  echo $this->loyaltyReward($params); break;
                case 'merchant_pay':    echo $this->merchantPay($params); break;
                case 'check_balance':   echo $this->checkBalance($params); break;
                case 'wallet_info':     echo $this->walletInfo($params); break;
                case 'tx_history':      echo $this->txHistory($params); break;
                case 'asset_balance':   echo $this->assetBalance($params); break;
                case 'sdk_send':        echo $this->sdkSend($params); break;
                default:
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // ── Loyalty Reward ────────────────────────────────────────────────────────
    // Called by BeepXtra platform when a shopper earns reward BPC

    private function loyaltyReward(array $p): string {
        $required = ['src', 'dst', 'val', 'signature', 'public_key'];
        foreach ($required as $f) {
            if (empty($p[$f])) return json_encode(['ok' => false, 'error' => "Missing: $f"]);
        }

        $tx = new STx($this->db);
        $txData = $tx->build([
            'type'       => TX_TRANSFER,
            'src'        => $p['src'],
            'dst'        => $p['dst'],
            'val'        => $p['val'],
            'message'    => 'loyalty_reward',
            'signature'  => $p['signature'],
            'public_key' => $p['public_key'],
            'date'       => time(),
        ]);

        $ok = $tx->addToMempool($txData);
        return json_encode(['ok' => $ok, 'error' => $ok ? null : 'Rejected']);
    }

    // ── Merchant Pay ──────────────────────────────────────────────────────────
    // BeepXtra checkout: shopper pays merchant in BPC

    private function merchantPay(array $p): string {
        $required = ['src', 'dst', 'val', 'signature', 'public_key', 'merchant_ref'];
        foreach ($required as $f) {
            if (empty($p[$f])) return json_encode(['ok' => false, 'error' => "Missing: $f"]);
        }

        $tx = new STx($this->db);
        $txData = $tx->build([
            'type'       => TX_TRANSFER,
            'src'        => $p['src'],
            'dst'        => $p['dst'],
            'val'        => $p['val'],
            'message'    => json_encode(['type' => 'merchant_pay', 'ref' => $p['merchant_ref']]),
            'signature'  => $p['signature'],
            'public_key' => $p['public_key'],
            'date'       => time(),
        ]);

        $ok = $tx->addToMempool($txData);
        if ($ok) {
            (new SPeers($this->db))->propagate('api/tx', $txData);
        }
        return json_encode(['ok' => $ok]);
    }

    // ── Check Balance ─────────────────────────────────────────────────────────

    private function checkBalance(array $p): string {
        if (empty($p['address'])) return json_encode(['ok' => false, 'error' => 'Address required']);
        $wallet = new SWallet($this->db);

        // Resolve alias if needed
        if (strpos($p['address'], '@') === 0 || !preg_match('/^[A-Za-z0-9]{20,64}$/', $p['address'])) {
            $resolved = $wallet->resolveAlias($p['address']);
            if (!$resolved) return json_encode(['ok' => false, 'error' => 'Alias not found']);
            $p['address'] = $resolved;
        }

        return json_encode(['ok' => true, 'data' => [
            'address'  => $p['address'],
            'balance'  => $wallet->getBalance($p['address']),
            'pending'  => $wallet->getPendingBalance($p['address']),
        ]]);
    }

    // ── Wallet Info ───────────────────────────────────────────────────────────

    private function walletInfo(array $p): string {
        if (empty($p['address'])) return json_encode(['ok' => false, 'error' => 'Address required']);
        $wallet  = new SWallet($this->db);
        $account = $wallet->getAccount($p['address']);
        if (!$account) return json_encode(['ok' => false, 'error' => 'Account not found']);

        $mn = $this->db->row("SELECT status, collateral FROM masternode WHERE address=?", [$p['address']]);

        return json_encode(['ok' => true, 'data' => [
            'address'    => $account['address'],
            'alias'      => $account['alias'],
            'balance'    => $account['balance'],
            'pending'    => $wallet->getPendingBalance($p['address']),
            'masternode' => $mn ?: null,
            'first_seen' => $account['first_seen'],
            'last_seen'  => $account['last_seen'],
        ]]);
    }

    // ── TX History ────────────────────────────────────────────────────────────

    private function txHistory(array $p): string {
        if (empty($p['address'])) return json_encode(['ok' => false, 'error' => 'Address required']);
        $limit = min((int)($p['limit'] ?? 50), 200);
        $txs   = (new STx($this->db))->getByAddress($p['address'], $limit);
        return json_encode(['ok' => true, 'data' => $txs]);
    }

    // ── Asset Balance ─────────────────────────────────────────────────────────

    private function assetBalance(array $p): string {
        if (empty($p['address']) || empty($p['asset_id'])) {
            return json_encode(['ok' => false, 'error' => 'address and asset_id required']);
        }
        $balance = (new SAssets($this->db))->getAssetBalance($p['asset_id'], $p['address']);
        return json_encode(['ok' => true, 'data' => ['balance' => $balance]]);
    }

    // ── SDK Send ──────────────────────────────────────────────────────────────
    // BeepWallet SDK compatibility endpoint

    private function sdkSend(array $p): string {
        $data = json_decode(file_get_contents('php://input'), true) ?? $p;
        $stx  = new STx($this->db);
        $ok   = $stx->addToMempool($data);
        if ($ok) (new SPeers($this->db))->propagate('api/tx', $data);
        return json_encode(['ok' => $ok]);
    }
}
