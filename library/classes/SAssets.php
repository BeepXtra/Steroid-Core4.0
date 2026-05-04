<?php
defined('_SECURED') or die('Restricted access');

/**
 * Asset management class for Steroid platform.
 * Handles querying asset data, balances, market orders, and dividend history.
 * Asset creation/transfer is handled through STx/SBlock (transaction pipeline).
 */
class SAssets {

    /**
     * Retrieve a single asset record with its account alias.
     * $id may be an address, public key, or alias.
     */
    public function get($id) {
        global $db;
        $acc = new SWallet();

        if ($acc->valid_alias($id)) {
            $id = strtoupper(san($id));
            $id = $db->single("SELECT id FROM accounts WHERE alias=:id", [":id" => $id]);
        } elseif (strlen($id) >= 89 && $acc->valid_key($id)) {
            $id = $acc->get_address($id);
        } elseif ($acc->valid($id)) {
            $id = san($id);
        } else {
            return false;
        }

        if (empty($id)) {
            return false;
        }

        $row = $db->row(
            "SELECT a.*, b.alias FROM assets AS a
             LEFT JOIN accounts AS b ON a.id = b.id
             WHERE a.id = :id",
            [":id" => $id]
        );

        if (!$row) {
            return false;
        }

        $row['circulating_supply'] = $this->circulating_supply($id);
        $row['holders']            = $this->holder_count($id);
        return $row;
    }

    /**
     * List assets with optional pagination.
     */
    public function list_assets($limit = 50, $offset = 0) {
        global $db;
        $limit  = max(1, min(200, intval($limit)));
        $offset = max(0, intval($offset));

        return $db->run(
            "SELECT a.*, b.alias FROM assets AS a
             LEFT JOIN accounts AS b ON a.id = b.id
             ORDER BY a.height DESC
             LIMIT :offset, :limit",
            [":offset" => $offset, ":limit" => $limit]
        );
    }

    /**
     * Get the asset balance for a specific account.
     */
    public function get_balance($asset_id, $account_id) {
        global $db;
        $acc = new SWallet();

        $asset_id   = $this->resolve_address($asset_id, $acc);
        $account_id = $this->resolve_address($account_id, $acc);

        if (!$asset_id || !$account_id) {
            return false;
        }

        $balance = $db->single(
            "SELECT balance FROM assets_balance WHERE asset=:asset AND account=:account",
            [":asset" => $asset_id, ":account" => $account_id]
        );

        return [
            "asset"   => $asset_id,
            "account" => $account_id,
            "balance" => $balance !== false ? (int) $balance : 0,
        ];
    }

    /**
     * Get all holders of an asset with their balances.
     */
    public function get_holders($asset_id, $limit = 100, $offset = 0) {
        global $db;
        $acc = new SWallet();

        $asset_id = $this->resolve_address($asset_id, $acc);
        if (!$asset_id) {
            return false;
        }

        $limit  = max(1, min(500, intval($limit)));
        $offset = max(0, intval($offset));

        return $db->run(
            "SELECT ab.account, ab.balance, ac.alias
             FROM assets_balance AS ab
             LEFT JOIN accounts AS ac ON ac.id = ab.account
             WHERE ab.asset = :asset AND ab.balance > 0
             ORDER BY ab.balance DESC
             LIMIT :offset, :limit",
            [":asset" => $asset_id, ":offset" => $offset, ":limit" => $limit]
        );
    }

    /**
     * Get open/filled market orders for an asset.
     * $type: 'bid', 'ask', or '' for all.
     * $status: 0=open, 1=filled, 2=cancelled, or -1 for all.
     */
    public function get_market_orders($asset_id, $type = '', $status = 0, $limit = 100, $offset = 0) {
        global $db;
        $acc = new SWallet();

        $asset_id = $this->resolve_address($asset_id, $acc);
        if (!$asset_id) {
            return false;
        }

        $limit  = max(1, min(500, intval($limit)));
        $offset = max(0, intval($offset));
        $bind   = [":asset" => $asset_id, ":offset" => $offset, ":limit" => $limit];
        $where  = "WHERE asset = :asset";

        if ($type === 'bid' || $type === 'ask') {
            $where       .= " AND type = :type";
            $bind[":type"] = $type;
        }

        if ($status >= 0 && $status <= 2) {
            $where         .= " AND status = :status";
            $bind[":status"] = intval($status);
        }

        return $db->run(
            "SELECT * FROM assets_market $where ORDER BY date DESC LIMIT :offset, :limit",
            $bind
        );
    }

    /**
     * Get the dividend distribution history for an asset.
     * Includes both manual (v54) and automated (v57) distributions.
     */
    public function get_dividend_history($asset_id, $limit = 50, $offset = 0) {
        global $db;
        $acc = new SWallet();

        $asset_id = $this->resolve_address($asset_id, $acc);
        if (!$asset_id) {
            return false;
        }

        $limit  = max(1, min(200, intval($limit)));
        $offset = max(0, intval($offset));

        return $db->run(
            "SELECT id, height, val, fee, date, version,
                    CASE version WHEN 54 THEN 'manual' ELSE 'automated' END AS distribution_type
             FROM transactions
             WHERE (version = 54 OR version = 57)
               AND public_key = (SELECT public_key FROM accounts WHERE id = :asset)
             ORDER BY height DESC
             LIMIT :offset, :limit",
            [":asset" => $asset_id, ":offset" => $offset, ":limit" => $limit]
        );
    }

    /**
     * Aggregate statistics for an asset.
     */
    public function get_stats($asset_id) {
        global $db;
        $acc = new SWallet();

        $asset_id = $this->resolve_address($asset_id, $acc);
        if (!$asset_id) {
            return false;
        }

        $asset = $db->row(
            "SELECT a.*, b.alias FROM assets AS a
             LEFT JOIN accounts AS b ON a.id = b.id
             WHERE a.id = :id",
            [":id" => $asset_id]
        );
        if (!$asset) {
            return false;
        }

        $circulating  = $this->circulating_supply($asset_id);
        $holders      = $this->holder_count($asset_id);
        $open_bids    = $db->single(
            "SELECT COUNT(1) FROM assets_market WHERE asset=:a AND type='bid' AND status=0",
            [":a" => $asset_id]
        );
        $open_asks    = $db->single(
            "SELECT COUNT(1) FROM assets_market WHERE asset=:a AND type='ask' AND status=0",
            [":a" => $asset_id]
        );
        $last_price   = $db->single(
            "SELECT price FROM assets_market WHERE asset=:a AND status=1 ORDER BY date DESC LIMIT 1",
            [":a" => $asset_id]
        );
        $total_divs   = $db->single(
            "SELECT COUNT(1) FROM transactions
             WHERE (version=54 OR version=57)
               AND public_key=(SELECT public_key FROM accounts WHERE id=:a)",
            [":a" => $asset_id]
        );

        return [
            "id"                 => $asset_id,
            "alias"              => $asset['alias'],
            "max_supply"         => $asset['max_supply'],
            "circulating_supply" => $circulating,
            "holders"            => $holders,
            "tradable"           => (bool) $asset['tradable'],
            "price"              => $asset['price'],
            "dividend_only"      => (bool) $asset['dividend_only'],
            "auto_dividend"      => (bool) $asset['auto_dividend'],
            "allow_bid"          => (bool) $asset['allow_bid'],
            "created_at_height"  => $asset['height'],
            "open_bids"          => (int) $open_bids,
            "open_asks"          => (int) $open_asks,
            "last_trade_price"   => $last_price !== false ? $last_price : null,
            "total_distributions" => (int) $total_divs,
        ];
    }

    /**
     * Get all assets held by a specific account.
     */
    public function get_account_assets($account_id) {
        global $db;
        $acc = new SWallet();

        $account_id = $this->resolve_address($account_id, $acc);
        if (!$account_id) {
            return false;
        }

        return $db->run(
            "SELECT ab.asset, ab.balance, ac.alias AS asset_alias,
                    ast.max_supply, ast.tradable, ast.price
             FROM assets_balance AS ab
             LEFT JOIN accounts AS ac ON ac.id = ab.asset
             LEFT JOIN assets AS ast ON ast.id = ab.asset
             WHERE ab.account = :account AND ab.balance > 0
             ORDER BY ab.balance DESC",
            [":account" => $account_id]
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Total units currently in circulation for an asset (sum of all non-zero balances).
     */
    private function circulating_supply($asset_id) {
        global $db;
        $total = $db->single(
            "SELECT COALESCE(SUM(balance), 0) FROM assets_balance WHERE asset = :id",
            [":id" => $asset_id]
        );
        return (int) $total;
    }

    /**
     * Number of unique accounts holding > 0 units of an asset.
     */
    private function holder_count($asset_id) {
        global $db;
        return (int) $db->single(
            "SELECT COUNT(1) FROM assets_balance WHERE asset = :id AND balance > 0",
            [":id" => $asset_id]
        );
    }

    /**
     * Resolve an identifier (address / public key / alias) to a blockchain address.
     * Returns the address string, or false on failure.
     */
    private function resolve_address($id, SWallet $acc) {
        if ($acc->valid_alias($id)) {
            global $db;
            $id = strtoupper(san($id));
            return $db->single("SELECT id FROM accounts WHERE alias=:id", [":id" => $id]);
        }
        if (strlen($id) >= 89 && $acc->valid_key($id)) {
            return $acc->get_address($id);
        }
        if ($acc->valid($id)) {
            return san($id);
        }
        return false;
    }
}
?>
