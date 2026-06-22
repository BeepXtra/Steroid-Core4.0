<?php
defined('_SECURED') or die('Restricted access');

/**
 * SAssets — On-chain asset engine.
 * Asset creation, transfer, on-chain DEX (bid/ask order book), dividends, auto-dividends.
 */
class SAssets {
    private Database $db;
    private Config   $cfg;

    public function __construct(Database $db, Config $cfg) {
        $this->db  = $db;
        $this->cfg = $cfg;
    }

    // ─── Asset Creation ──────────────────────────────────────

    public function create(array $data, int $height): string {
        $id = hash('sha256', implode('-', [
            $data['account'], $data['max_supply'], $data['name'] ?? '', time()
        ]));
        $this->db->insert('assets', [
            'id'            => $id,
            'max_supply'    => (int)$data['max_supply'],
            'tradable'      => (int)($data['tradable'] ?? 1),
            'price'         => number_format((float)($data['price'] ?? 0), 8, '.', ''),
            'dividend_only' => (int)($data['dividend_only'] ?? 0),
            'auto_dividend' => (int)($data['auto_dividend'] ?? 0),
            'allow_bid'     => (int)($data['allow_bid'] ?? 1),
            'height'        => $height,
        ]);
        // Credit full supply to creator
        $this->creditBalance($data['account'], $id, (string)$data['max_supply']);
        return $id;
    }

    public function get(string $id): ?array {
        return $this->db->fetchOne('SELECT * FROM assets WHERE id = ?', [$id]);
    }

    public function getAll(): array {
        return $this->db->fetchAll('SELECT * FROM assets ORDER BY height DESC');
    }

    // ─── Balances ────────────────────────────────────────────

    public function getBalance(string $account, string $assetId): string {
        return $this->db->fetchColumn(
            'SELECT balance FROM assets_balance WHERE account = ? AND asset = ?',
            [$account, $assetId]
        ) ?: '0.00000000';
    }

    public function getBalancesByAccount(string $account): array {
        return $this->db->fetchAll(
            'SELECT ab.*, a.tradable, a.price, a.dividend_only
             FROM assets_balance ab
             JOIN assets a ON a.id = ab.asset
             WHERE ab.account = ?',
            [$account]
        );
    }

    public function transfer(string $from, string $to, string $assetId, string $amount): void {
        $balance = (float)$this->getBalance($from, $assetId);
        if ($balance < (float)$amount)
            throw new RuntimeException("Insufficient asset balance: have $balance, need $amount");

        $this->db->transaction(function () use ($from, $to, $assetId, $amount) {
            $this->debitBalance($from, $assetId, $amount);
            $this->creditBalance($to, $assetId, $amount);
        });
    }

    // ─── DEX: Order Book ─────────────────────────────────────

    public function placeBid(string $account, string $assetId, float $price, int $val): string {
        return $this->placeOrder($account, $assetId, $price, $val, 'bid');
    }

    public function placeAsk(string $account, string $assetId, float $price, int $val): string {
        return $this->placeOrder($account, $assetId, $price, $val, 'ask');
    }

    private function placeOrder(string $account, string $assetId, float $price, int $val, string $type): string {
        $asset = $this->get($assetId);
        if (!$asset || !$asset['tradable']) throw new RuntimeException('Asset not tradable');
        if ($type === 'bid' && !$asset['allow_bid']) throw new RuntimeException('Bids not allowed for this asset');

        $id = hash('sha256', implode('-', [$account, $assetId, $price, $val, $type, time()]));
        $this->db->insert('assets_market', [
            'id'         => $id,
            'account'    => $account,
            'asset'      => $assetId,
            'price'      => number_format($price, 8, '.', ''),
            'date'       => time(),
            'status'     => 0,
            'type'       => $type,
            'val'        => $val,
            'val_done'   => 0,
            'cancelable' => 1,
        ]);
        $this->matchOrders($assetId);
        return $id;
    }

    private function matchOrders(string $assetId): void {
        $asks = $this->db->fetchAll(
            "SELECT * FROM assets_market WHERE asset = ? AND type = 'ask' AND status = 0
             ORDER BY price ASC, date ASC",
            [$assetId]
        );
        $bids = $this->db->fetchAll(
            "SELECT * FROM assets_market WHERE asset = ? AND type = 'bid' AND status = 0
             ORDER BY price DESC, date ASC",
            [$assetId]
        );
        foreach ($bids as $bid) {
            foreach ($asks as &$ask) {
                if ($ask['status'] !== 0) continue;
                if ((float)$bid['price'] < (float)$ask['price']) break;

                $qty = min($bid['val'] - $bid['val_done'], $ask['val'] - $ask['val_done']);
                if ($qty <= 0) continue;

                // Execute match
                $this->db->transaction(function () use ($bid, $ask, $qty) {
                    $this->transfer($ask['account'], $bid['account'], $ask['asset'], (string)$qty);
                    $cost = number_format($qty * (float)$ask['price'], 8, '.', '');
                    $this->db->execute(
                        'UPDATE accounts SET balance = balance - ? WHERE id = ?',
                        [$cost, $bid['account']]
                    );
                    $this->db->execute(
                        'UPDATE accounts SET balance = balance + ? WHERE id = ?',
                        [$cost, $ask['account']]
                    );
                    $this->db->execute(
                        'UPDATE assets_market SET val_done = val_done + ?, status = IF(val_done + ? >= val, 1, 0) WHERE id = ?',
                        [$qty, $qty, $bid['id']]
                    );
                    $this->db->execute(
                        'UPDATE assets_market SET val_done = val_done + ?, status = IF(val_done + ? >= val, 1, 0) WHERE id = ?',
                        [$qty, $qty, $ask['id']]
                    );
                });
                $ask['val_done'] += $qty;
                if ($ask['val_done'] >= $ask['val']) $ask['status'] = 1;
            }
        }
    }

    public function cancelOrder(string $orderId, string $account): void {
        $order = $this->db->fetchOne('SELECT * FROM assets_market WHERE id = ?', [$orderId]);
        if (!$order) throw new RuntimeException('Order not found');
        if ($order['account'] !== $account) throw new RuntimeException('Not your order');
        if (!$order['cancelable']) throw new RuntimeException('Order not cancelable');
        $this->db->execute("UPDATE assets_market SET status = 2 WHERE id = ?", [$orderId]);
    }

    public function getOrderBook(string $assetId): array {
        return [
            'bids' => $this->db->fetchAll(
                "SELECT * FROM assets_market WHERE asset = ? AND type = 'bid' AND status = 0 ORDER BY price DESC LIMIT 50",
                [$assetId]
            ),
            'asks' => $this->db->fetchAll(
                "SELECT * FROM assets_market WHERE asset = ? AND type = 'ask' AND status = 0 ORDER BY price ASC LIMIT 50",
                [$assetId]
            ),
        ];
    }

    // ─── Dividends ───────────────────────────────────────────

    public function distributeDividend(string $assetId, string $totalAmount): void {
        $holders = $this->db->fetchAll(
            'SELECT account, balance FROM assets_balance WHERE asset = ? AND balance > 0',
            [$assetId]
        );
        $asset    = $this->get($assetId);
        $supply   = (float)($asset['max_supply'] ?? 0);
        if ($supply <= 0 || empty($holders)) return;

        $this->db->transaction(function () use ($holders, $totalAmount, $supply) {
            foreach ($holders as $holder) {
                $share = number_format(
                    ((float)$holder['balance'] / $supply) * (float)$totalAmount,
                    8, '.', ''
                );
                $this->db->execute(
                    'UPDATE accounts SET balance = balance + ? WHERE id = ?',
                    [$share, $holder['account']]
                );
            }
        });
    }

    public function processAutoDividends(): void {
        $assets = $this->db->fetchAll(
            "SELECT * FROM assets WHERE auto_dividend = 1 AND tradable = 1"
        );
        foreach ($assets as $asset) {
            // Collect fee pool for this asset from recent transactions
            $feePool = $this->db->fetchColumn(
                'SELECT COALESCE(SUM(fee),0) FROM transactions
                 WHERE version = ? AND date > ?',
                [STx::VERSION_ASSET_TRANSFER, time() - 86400]
            );
            if ((float)$feePool > 0) {
                $this->distributeDividend($asset['id'], (string)$feePool);
            }
        }
    }

    // ─── Balance Helpers ─────────────────────────────────────

    private function creditBalance(string $account, string $assetId, string $amount): void {
        $this->db->upsert('assets_balance',
            ['account' => $account, 'asset' => $assetId, 'balance' => $amount],
            []
        );
        $this->db->execute(
            'UPDATE assets_balance SET balance = balance + ? WHERE account = ? AND asset = ?',
            [$amount, $account, $assetId]
        );
    }

    private function debitBalance(string $account, string $assetId, string $amount): void {
        $this->db->execute(
            'UPDATE assets_balance SET balance = balance - ? WHERE account = ? AND asset = ?',
            [$amount, $account, $assetId]
        );
    }
}
