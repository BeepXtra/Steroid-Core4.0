<?php
defined('_SECURED') or die('Restricted access');

class SAssets {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function applyTx(array $tx, int $height): void {
        $type = (int)$tx['type'];
        $msg  = json_decode($tx['message'] ?? '{}', true) ?? [];

        switch ($type) {
            case TX_ASSET_CREATE:   $this->create($tx, $msg, $height); break;
            case TX_ASSET_TRANSFER: $this->transfer($tx, $msg); break;
            case TX_ASSET_BID:      $this->placeBid($tx, $msg, $height); break;
            case TX_ASSET_ASK:      $this->placeAsk($tx, $msg, $height); break;
            case TX_ASSET_CANCEL:   $this->cancelOrder($tx, $msg); break;
            case TX_ASSET_INFLATE:  $this->inflate($tx, $msg); break;
            case TX_ASSET_DIVIDEND: $this->distributeDividend($tx, $msg); break;
            case TX_ASSET_FILL:     $this->fillOrder($tx, $msg, $height); break;
        }
    }

    // ── Create ───────────────────────────────────────────────────────────────

    private function create(array $tx, array $msg, int $height): void {
        $assetId = hash('sha256', $tx['src'] . $msg['name'] . $height);
        $this->db->insert('assets', [
            'asset_id'         => $assetId,
            'name'             => $msg['name'],
            'owner'            => $tx['src'],
            'total_supply'     => $msg['supply'] ?? '0',
            'circulating'      => $msg['supply'] ?? '0',
            'description'      => $msg['description'] ?? '',
            'max_supply'       => $msg['max_supply'] ?? '0',
            'inflatable'       => (int)($msg['inflatable'] ?? 0),
            'fixed_price'      => $msg['fixed_price'] ?? '0',
            'dividend_enabled' => (int)($msg['dividend'] ?? 0),
            'height'           => $height,
            'date'             => time(),
        ]);
        // Assign initial supply to creator
        $this->creditAsset($assetId, $tx['src'], $msg['supply'] ?? '0');
    }

    // ── Transfer ─────────────────────────────────────────────────────────────

    private function transfer(array $tx, array $msg): void {
        $assetId = $msg['asset_id'];
        $amount  = $msg['amount'];
        $this->debitAsset($assetId, $tx['src'], $amount);
        $this->creditAsset($assetId, $tx['dst'], $amount);
    }

    // ── DEX ──────────────────────────────────────────────────────────────────

    private function placeBid(array $tx, array $msg, int $height): void {
        // Buyer places bid: BPC locked, waiting for ask match
        $this->db->insert('assets_market', [
            'asset_id' => $msg['asset_id'],
            'type'     => 'bid',
            'address'  => $tx['src'],
            'amount'   => $msg['amount'],
            'price'    => $msg['price'],
            'filled'   => '0',
            'status'   => 'open',
            'height'   => $height,
            'date'     => time(),
        ]);
        $this->tryMatch($msg['asset_id'], $height);
    }

    private function placeAsk(array $tx, array $msg, int $height): void {
        // Seller places ask: assets locked
        $this->debitAsset($msg['asset_id'], $tx['src'], $msg['amount']);
        $this->db->insert('assets_market', [
            'asset_id' => $msg['asset_id'],
            'type'     => 'ask',
            'address'  => $tx['src'],
            'amount'   => $msg['amount'],
            'price'    => $msg['price'],
            'filled'   => '0',
            'status'   => 'open',
            'height'   => $height,
            'date'     => time(),
        ]);
        $this->tryMatch($msg['asset_id'], $height);
    }

    private function tryMatch(string $assetId, int $height): void {
        // Match highest bid vs lowest ask
        $bid = $this->db->row(
            "SELECT * FROM assets_market WHERE asset_id=? AND type='bid' AND status='open' ORDER BY price DESC, id ASC LIMIT 1",
            [$assetId]
        );
        $ask = $this->db->row(
            "SELECT * FROM assets_market WHERE asset_id=? AND type='ask' AND status='open' ORDER BY price ASC, id ASC LIMIT 1",
            [$assetId]
        );

        if (!$bid || !$ask) return;
        if ((float)$bid['price'] < (float)$ask['price']) return;

        $matchAmt = min((float)$bid['amount'] - (float)$bid['filled'], (float)$ask['amount'] - (float)$ask['filled']);
        if ($matchAmt <= 0) return;

        // Transfer asset to buyer
        $this->creditAsset($assetId, $bid['address'], (string)$matchAmt);

        // Transfer BPC to seller
        $bpcAmt = number_format($matchAmt * (float)$ask['price'], 8, '.', '');
        (new SWallet($this->db))->creditBalance($ask['address'], $bpcAmt);

        // Update fill amounts
        $this->db->query("UPDATE assets_market SET filled=filled+? WHERE id=?", [$matchAmt, $bid['id']]);
        $this->db->query("UPDATE assets_market SET filled=filled+? WHERE id=?", [$matchAmt, $ask['id']]);

        // Mark filled if complete
        $this->db->query("UPDATE assets_market SET status='filled' WHERE id=? AND filled>=amount", [$bid['id']]);
        $this->db->query("UPDATE assets_market SET status='filled' WHERE id=? AND filled>=amount", [$ask['id']]);
    }

    private function fillOrder(array $tx, array $msg, int $height): void {
        $this->tryMatch($msg['asset_id'], $height);
    }

    private function cancelOrder(array $tx, array $msg): void {
        $order = $this->db->row(
            "SELECT * FROM assets_market WHERE id=? AND address=? AND status='open'",
            [$msg['order_id'], $tx['src']]
        );
        if (!$order) return;

        // Refund locked assets/BPC
        if ($order['type'] === 'ask') {
            $remaining = (float)$order['amount'] - (float)$order['filled'];
            $this->creditAsset($order['asset_id'], $order['address'], (string)$remaining);
        }

        $this->db->update('assets_market', ['status' => 'cancelled'], 'id=?', [$order['id']]);
    }

    // ── Inflate ──────────────────────────────────────────────────────────────

    private function inflate(array $tx, array $msg): void {
        $asset = $this->db->row("SELECT * FROM assets WHERE asset_id=? AND owner=? AND inflatable=1",
            [$msg['asset_id'], $tx['src']]);
        if (!$asset) return;

        $newSupply = bcadd($asset['total_supply'], $msg['amount'], 8);
        $maxSupply = (float)$asset['max_supply'];
        if ($maxSupply > 0 && (float)$newSupply > $maxSupply) return;

        $this->db->update('assets', [
            'total_supply' => $newSupply,
            'circulating'  => bcadd($asset['circulating'], $msg['amount'], 8),
        ], 'asset_id=?', [$msg['asset_id']]);
        $this->creditAsset($msg['asset_id'], $tx['src'], $msg['amount']);
    }

    // ── Dividend ─────────────────────────────────────────────────────────────

    private function distributeDividend(array $tx, array $msg): void {
        $asset = $this->db->row("SELECT * FROM assets WHERE asset_id=? AND owner=? AND dividend_enabled=1",
            [$msg['asset_id'], $tx['src']]);
        if (!$asset) return;

        $holders = $this->db->rows(
            "SELECT address, balance FROM assets_balance WHERE asset_id=? AND balance>0",
            [$msg['asset_id']]
        );
        if (empty($holders)) return;

        $total    = (float)$asset['circulating'];
        $dividend = (float)$msg['amount'];
        $wallet   = new SWallet($this->db);

        foreach ($holders as $holder) {
            $share  = (float)$holder['balance'] / $total;
            $payout = number_format($share * $dividend, 8, '.', '');
            $wallet->creditBalance($holder['address'], $payout);
        }
    }

    // ── Balance helpers ──────────────────────────────────────────────────────

    public function creditAsset(string $assetId, string $address, string $amount): void {
        $this->db->query(
            "INSERT INTO assets_balance (asset_id, address, balance) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE balance=balance+?",
            [$assetId, $address, $amount, $amount]
        );
    }

    public function debitAsset(string $assetId, string $address, string $amount): void {
        $this->db->query(
            "UPDATE assets_balance SET balance=balance-? WHERE asset_id=? AND address=?",
            [$amount, $assetId, $address]
        );
    }

    public function getAssetBalance(string $assetId, string $address): string {
        $row = $this->db->row(
            "SELECT balance FROM assets_balance WHERE asset_id=? AND address=?",
            [$assetId, $address]
        );
        return $row ? $row['balance'] : '0.00000000';
    }

    public function getAsset(string $assetId): ?array {
        return $this->db->row("SELECT * FROM assets WHERE asset_id=?", [$assetId]);
    }

    public function listAssets(int $limit = 50): array {
        return $this->db->rows("SELECT * FROM assets ORDER BY id DESC LIMIT ?", [$limit]);
    }

    public function getOrderBook(string $assetId): array {
        return [
            'bids' => $this->db->rows(
                "SELECT * FROM assets_market WHERE asset_id=? AND type='bid' AND status='open' ORDER BY price DESC LIMIT 20",
                [$assetId]
            ),
            'asks' => $this->db->rows(
                "SELECT * FROM assets_market WHERE asset_id=? AND type='ask' AND status='open' ORDER BY price ASC LIMIT 20",
                [$assetId]
            ),
        ];
    }
}
