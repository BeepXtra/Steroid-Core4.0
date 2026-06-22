<?php
defined('_SECURED') or die('Restricted access');

class SAssets {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function applyTx($tx, $height) {
        $type = (int)$tx['type'];
        $msg  = json_decode(isset($tx['message']) ? $tx['message'] : '{}', true);
        if (!$msg) $msg = array();

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

    private function create($tx, $msg, $height) {
        $assetId = hash('sha256', $tx['src'] . $msg['name'] . $height);
        $this->db->insert('assets', array(
            'asset_id'         => $assetId,
            'name'             => $msg['name'],
            'owner'            => $tx['src'],
            'total_supply'     => isset($msg['supply']) ? $msg['supply'] : '0',
            'circulating'      => isset($msg['supply']) ? $msg['supply'] : '0',
            'description'      => isset($msg['description']) ? $msg['description'] : '',
            'max_supply'       => isset($msg['max_supply']) ? $msg['max_supply'] : '0',
            'inflatable'       => (int)(isset($msg['inflatable']) ? $msg['inflatable'] : 0),
            'fixed_price'      => isset($msg['fixed_price']) ? $msg['fixed_price'] : '0',
            'dividend_enabled' => (int)(isset($msg['dividend']) ? $msg['dividend'] : 0),
            'height'           => $height,
            'date'             => time(),
        ));
        $this->creditAsset($assetId, $tx['src'], isset($msg['supply']) ? $msg['supply'] : '0');
    }

    private function transfer($tx, $msg) {
        $this->debitAsset($msg['asset_id'], $tx['src'], $msg['amount']);
        $this->creditAsset($msg['asset_id'], $tx['dst'], $msg['amount']);
    }

    private function placeBid($tx, $msg, $height) {
        $this->db->insert('assets_market', array(
            'asset_id' => $msg['asset_id'],
            'type'     => 'bid',
            'address'  => $tx['src'],
            'amount'   => $msg['amount'],
            'price'    => $msg['price'],
            'filled'   => '0',
            'status'   => 'open',
            'height'   => $height,
            'date'     => time(),
        ));
        $this->tryMatch($msg['asset_id'], $height);
    }

    private function placeAsk($tx, $msg, $height) {
        $this->debitAsset($msg['asset_id'], $tx['src'], $msg['amount']);
        $this->db->insert('assets_market', array(
            'asset_id' => $msg['asset_id'],
            'type'     => 'ask',
            'address'  => $tx['src'],
            'amount'   => $msg['amount'],
            'price'    => $msg['price'],
            'filled'   => '0',
            'status'   => 'open',
            'height'   => $height,
            'date'     => time(),
        ));
        $this->tryMatch($msg['asset_id'], $height);
    }

    private function tryMatch($assetId, $height) {
        $bid = $this->db->row(
            "SELECT * FROM assets_market WHERE asset_id=? AND type='bid' AND status='open' ORDER BY price DESC, id ASC LIMIT 1",
            array($assetId)
        );
        $ask = $this->db->row(
            "SELECT * FROM assets_market WHERE asset_id=? AND type='ask' AND status='open' ORDER BY price ASC, id ASC LIMIT 1",
            array($assetId)
        );

        if (!$bid || !$ask) return;
        if ((float)$bid['price'] < (float)$ask['price']) return;

        $matchAmt = min((float)$bid['amount'] - (float)$bid['filled'], (float)$ask['amount'] - (float)$ask['filled']);
        if ($matchAmt <= 0) return;

        $this->creditAsset($assetId, $bid['address'], (string)$matchAmt);
        $bpcAmt = number_format($matchAmt * (float)$ask['price'], 8, '.', '');
        (new SWallet($this->db))->creditBalance($ask['address'], $bpcAmt);

        $this->db->query("UPDATE assets_market SET filled=filled+? WHERE id=?", array($matchAmt, $bid['id']));
        $this->db->query("UPDATE assets_market SET filled=filled+? WHERE id=?", array($matchAmt, $ask['id']));
        $this->db->query("UPDATE assets_market SET status='filled' WHERE id=? AND filled>=amount", array($bid['id']));
        $this->db->query("UPDATE assets_market SET status='filled' WHERE id=? AND filled>=amount", array($ask['id']));
    }

    private function fillOrder($tx, $msg, $height) {
        $this->tryMatch($msg['asset_id'], $height);
    }

    private function cancelOrder($tx, $msg) {
        $order = $this->db->row(
            "SELECT * FROM assets_market WHERE id=? AND address=? AND status='open'",
            array($msg['order_id'], $tx['src'])
        );
        if (!$order) return;
        if ($order['type'] === 'ask') {
            $remaining = (float)$order['amount'] - (float)$order['filled'];
            $this->creditAsset($order['asset_id'], $order['address'], (string)$remaining);
        }
        $this->db->update('assets_market', array('status' => 'cancelled'), 'id=?', array($order['id']));
    }

    private function inflate($tx, $msg) {
        $asset = $this->db->row(
            "SELECT * FROM assets WHERE asset_id=? AND owner=? AND inflatable=1",
            array($msg['asset_id'], $tx['src'])
        );
        if (!$asset) return;
        $newSupply = bcadd($asset['total_supply'], $msg['amount'], 8);
        $maxSupply = (float)$asset['max_supply'];
        if ($maxSupply > 0 && (float)$newSupply > $maxSupply) return;
        $this->db->update('assets', array(
            'total_supply' => $newSupply,
            'circulating'  => bcadd($asset['circulating'], $msg['amount'], 8),
        ), 'asset_id=?', array($msg['asset_id']));
        $this->creditAsset($msg['asset_id'], $tx['src'], $msg['amount']);
    }

    private function distributeDividend($tx, $msg) {
        $asset = $this->db->row(
            "SELECT * FROM assets WHERE asset_id=? AND owner=? AND dividend_enabled=1",
            array($msg['asset_id'], $tx['src'])
        );
        if (!$asset) return;
        $holders = $this->db->rows(
            "SELECT address, balance FROM assets_balance WHERE asset_id=? AND balance>0",
            array($msg['asset_id'])
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

    public function creditAsset($assetId, $address, $amount) {
        $this->db->query(
            "INSERT INTO assets_balance (asset_id, address, balance) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE balance=balance+?",
            array($assetId, $address, $amount, $amount)
        );
    }

    public function debitAsset($assetId, $address, $amount) {
        $this->db->query(
            "UPDATE assets_balance SET balance=balance-? WHERE asset_id=? AND address=?",
            array($amount, $assetId, $address)
        );
    }

    public function getAssetBalance($assetId, $address) {
        $row = $this->db->row(
            "SELECT balance FROM assets_balance WHERE asset_id=? AND address=?",
            array($assetId, $address)
        );
        return $row ? $row['balance'] : '0.00000000';
    }

    public function getAsset($assetId) {
        return $this->db->row("SELECT * FROM assets WHERE asset_id=?", array($assetId));
    }

    public function listAssets($limit = 50) {
        return $this->db->rows("SELECT * FROM assets ORDER BY id DESC LIMIT ?", array($limit));
    }

    public function getOrderBook($assetId) {
        return array(
            'bids' => $this->db->rows(
                "SELECT * FROM assets_market WHERE asset_id=? AND type='bid' AND status='open' ORDER BY price DESC LIMIT 20",
                array($assetId)
            ),
            'asks' => $this->db->rows(
                "SELECT * FROM assets_market WHERE asset_id=? AND type='ask' AND status='open' ORDER BY price ASC LIMIT 20",
                array($assetId)
            ),
        );
    }
}
