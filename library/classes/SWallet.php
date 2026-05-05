<?php

defined('_SECURED') or die('Restricted access');

/**
 * Class for steroid platform
 *
 * @author exevior
 */
class SWallet {

    public $address;
    public $public_key;
    public $private_key;

    public function add($public_key, $block) {
        global $db;
        $id = $this->get_address($public_key);
        $bind = [":id" => $id, ":public_key" => $public_key, ":block" => $block, ":public_key2" => $public_key];

        $db->run(
                "INSERT INTO accounts SET id=:id, public_key=:public_key, block=:block, balance=0 ON DUPLICATE KEY UPDATE public_key=if(public_key='',:public_key2,public_key)",
                $bind
        );
    }

    // inserts just the account without public key
    public function add_id($id, $block) {
        global $db;
        $bind = [":id" => $id, ":block" => $block];
        $db->run("INSERT ignore INTO accounts SET id=:id, public_key='', block=:block, balance=0", $bind);
    }

    // generates Account's address from the public key
    public function get_address($public_key) {
        // hashes 9 times in sha512 (binary) and encodes in base58
        for ($i = 0; $i < 9; $i++) {
            $public_key = hash('sha512', $public_key, true);
        }
        return base58_encode($public_key);
    }

    // checks the ecdsa secp256k1 signature for a specific public key
    public function check_signature($data, $signature, $public_key) {
        return ec_verify($data, $signature, $public_key);
    }

    // generates a new account and a public/private key pair
    public function generate_account() {
        // using secp256k1 curve for ECDSA
        $args = [
            "curve_name" => "secp256k1",
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        ];

        // generates a new key pair
        $key1 = openssl_pkey_new($args);

        // exports the private key encoded as PEM
        openssl_pkey_export($key1, $pvkey);

        // converts the PEM to a base58 format
        $private_key = pem2coin($pvkey);

        // exports the private key encoded as PEM
        $pub = openssl_pkey_get_details($key1);

        // converts the PEM to a base58 format
        $public_key = pem2coin($pub['key']);

        // generates the account's address based on the public key
        $address = $this->get_address($public_key);
        return ["address" => $address, "public_key" => $public_key, "private_key" => $private_key];
    }

    // check the validity of a base58 encoded key. At the moment, it checks only the characters to be base58.
    public function valid_key($id) {
        return (bool) preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]*$/', $id);
    }

    //check alias validity
    public function free_alias($id) {
        global $db;
        $orig = strtoupper($id);
        $id = strtoupper($id);
        $id = san($id);
        if (strlen($id) < 4 || strlen($id) > 25) {
            return false;
        }
        if ($orig != $id) {
            return false;
        }
        // making sure the same alias can only be used in one place
        if ($db->single("SELECT COUNT(1) FROM accounts WHERE alias=:alias", [":alias" => $id]) == 0) {
            return true;
        } else {
            return false;
        }
    }

    //check if an account already has an alias
    public function has_alias($public_key) {
        global $db;
        $public_key = san($public_key);
        $res = $db->single("SELECT COUNT(1) FROM accounts WHERE public_key=:public_key AND alias IS NOT NULL", [":public_key" => $public_key]);
        if ($res != 0) {
            return true;
        } else {
            return false;
        }
    }

    //check alias validity
    public function valid_alias($id) {
        global $db;
        $orig = strtoupper($id);
        $banned = ["MERCURY", "DEVS", "DEVELOPMENT", "MARKETING", "MERCURY80", "DEVBPC", "DEVELOPER", "DEVELOPERS", "BPCDEV", "DONATION", "MERCATOX", "OCTAEX", "MERCURY", "STEROID", "STEROID4", "BEEP", "SBPC", "BEEPXCOIN", "BEEPIQ", "ESCROW", "OKEX", "BINANCE", "CRYPTOPIA", "HUOBI", "BITFINEX", "HITBTC", "UPBIT", "COINBASE", "KRAKEN", "BITSTAMP", "BITTREX", "POLONIEX","BEEPCOIN","BEEPXTRA","BXTRA"];
        $id = strtoupper($id);
        $id = san($id);
        if (in_array($id, $banned)) {
            return false;
        }
        if (strlen($id) < 4 || strlen($id) > 25) {
            return false;
        }
        if ($orig != $id) {
            return false;
        }
        return $db->single("SELECT COUNT(1) FROM accounts WHERE alias=:alias", [":alias" => $id]);
    }

    //returns the account of an alias
    public function alias2account($alias) {
        global $db;
        $alias = strtoupper($alias);
        $res = $db->single("SELECT id FROM accounts WHERE alias=:alias LIMIT 1", [":alias" => $alias]);
        return $res;
    }

    //returns the alias of an account
    public function account2alias($id) {
        global $db;
        $id = san($id);
        $res = $db->single("SELECT alias FROM accounts WHERE id=:id LIMIT 1", [":id" => $id]);
        return $res;
    }

    // check the validity of an address. At the moment, it checks only the characters to be base58 and the length to be >=70 and <=128.
    public function valid($id) {
        $len = strlen($id);
        if ($len < 70 || $len > 128) {
            return false;
        }
        return (bool) preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]+$/', $id);
    }

    // returns the current account balance
    public function balance($id) {
        global $db;
        $res = false;
        if ($this->valid_alias($id)) {
            //Check using alias
            $id = strtoupper($id);
            $res = $db->single("SELECT balance FROM accounts WHERE alias=:id", [":id" => $id]);
        } elseif (strlen($id) >= 89 && $this->valid_key($id)) {
            //Check using public_key
            $res = $db->single("SELECT balance FROM accounts WHERE public_key=:id", [":id" => $id]);
        } elseif ($this->valid($id)) {
            //Check using address
            $res = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $id]);
        }

        if ($res === false) {
            $res = "0.00000000";
        }

        return number_format($res, 8, ".", "");
    }

    // returns the account balance - any pending debits from the mempool
    public function pending_balance($id) {
        global $db;
        $res = $db->single(
            "SELECT (IFNULL(SUM(val),0) - IFNULL((SELECT SUM(val+fee) FROM mempool WHERE src=:src),0)) FROM mempool WHERE dst=:dst",
            [":src" => $id, ":dst" => $id]
        );
        return $res !== false ? $res : "0.00000000";
    }

    // returns all the transactions of a specific address
    public function get_transactions($id, $limit = 100) {
        global $db;
        $block   = new SBlock();
        $current = $block->current();
        $public_key = $this->public_key($id);
        $alias   = $this->account2alias($id);
        $limit   = max(1, min(100, intval($limit)));

        // A single OR across 17M+ rows prevents the query planner from using
        // any index efficiently.  Three separate indexed sub-queries combined
        // with UNION (which deduplicates) are orders of magnitude faster.
        // Each sub-query limits independently so we pull at most 3×$limit rows
        // before the outer ORDER+LIMIT collapses them to the final $limit.
        $bind = [
            ":dst"    => $id,
            ":src"    => $public_key,
            ":limit1" => $limit,
            ":limit2" => $limit,
            ":limit3" => $limit,
        ];

        $alias_clause = "";
        if (!empty($alias)) {
            $bind[":alias"]  = $alias;
            $bind[":limit4"] = $limit;
            $alias_clause = "
            UNION
            (SELECT * FROM transactions WHERE dst=:alias AND version < 111 ORDER BY height DESC LIMIT :limit4)";
        }

        $sql = "SELECT * FROM (
            (SELECT * FROM transactions WHERE dst=:dst AND version < 111 ORDER BY height DESC LIMIT :limit1)
            UNION
            (SELECT * FROM transactions WHERE public_key=:src AND version < 111 ORDER BY height DESC LIMIT :limit2)
            $alias_clause
        ) AS combined
        ORDER BY height DESC
        LIMIT :limit3";

        $res = $db->run($sql, $bind);

        $transactions = [];
        foreach ($res as $x) {
            // Skip automated dividend payouts (internal, not user-visible)
            if ($x['version'] == 57 || $x['version'] > 110) {
                continue;
            }
            $trans = [
                "block"      => $x['block'],
                "height"     => $x['height'],
                "id"         => $x['id'],
                "dst"        => $x['dst'],
                "val"        => $x['val'],
                "fee"        => $x['fee'],
                "signature"  => $x['signature'],
                "message"    => $x['message'],
                "version"    => $x['version'],
                "date"       => $x['date'],
                "public_key" => $x['public_key'],
            ];
            $trans['src']           = $this->get_address($x['public_key']);
            $trans['confirmations'] = $current['height'] - $x['height'];

            if ($x['version'] == 0) {
                $trans['type'] = "mining";
            } elseif ($x['version'] == 1 || $x['version'] == 2) {
                $trans['type'] = ($x['dst'] == $id) ? "credit" : "debit";
            } else {
                $trans['type'] = "other";
            }
            ksort($trans);
            $transactions[] = $trans;
        }

        return $transactions;
    }

    // returns the transactions from the mempool
    public function get_mempool_transactions($id) {
        global $db;
        $transactions = [];
        $res = $db->run(
                "SELECT * FROM mempool WHERE src=:src ORDER by height DESC LIMIT 100",
                [":src" => $id, ":dst" => $id]
        );
        foreach ($res as $x) {
            $trans = [
                "block" => $x['block'],
                "height" => $x['height'],
                "id" => $x['id'],
                "src" => $x['src'],
                "dst" => $x['dst'],
                "val" => $x['val'],
                "fee" => $x['fee'],
                "signature" => $x['signature'],
                "message" => $x['message'],
                "version" => $x['version'],
                "date" => $x['date'],
                "public_key" => $x['public_key'],
            ];
            $trans['type'] = "mempool";
            // they are unconfirmed, so they will have -1 confirmations.
            $trans['confirmations'] = -1;
            ksort($trans);
            $transactions[] = $trans;
        }
        return $transactions;
    }

    // returns the public key for a specific account
    public function public_key($id) {
        global $db;
        $res = $db->single("SELECT public_key FROM accounts WHERE id=:id", [":id" => $id]);
        return $res;
    }

    public function get_masternode($public_key) {
        global $db;
        $res = $db->row("SELECT * FROM masternode WHERE public_key=:public_key", [":public_key" => $public_key]);
        if (empty($res['public_key'])) {
            return false;
        }
        return $res;
    }

}
