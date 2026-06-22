<?php
defined('_SECURED') or die('Restricted access');

class SWallet {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function generateKeyPair() {
        $privateKey = bin2hex(random_bytes(32));
        $publicKey  = $this->privateKeyToPublic($privateKey);
        $address    = $this->publicKeyToAddress($publicKey);
        return array('privateKey' => $privateKey, 'publicKey' => $publicKey, 'address' => $address);
    }

    public function privateKeyToPublic($privateKeyHex) {
        $privBin = hex2bin($privateKeyHex);
        $pem     = $this->privToPem($privBin);
        $key     = openssl_pkey_get_private($pem);
        $details = openssl_pkey_get_details($key);
        return base64_encode($details['key']);
    }

    public function publicKeyToAddress($publicKey) {
        $hash = $publicKey;
        for ($i = 0; $i < 9; $i++) {
            $hash = hash('sha512', $hash, true);
        }
        $hash     = hash('ripemd160', $hash, true);
        $payload  = "\x3f" . $hash;
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
        return $this->base58encode($payload . $checksum);
    }

    public function sign($data, $privateKeyHex) {
        $privBin = hex2bin($privateKeyHex);
        $pem     = $this->privToPem($privBin);
        $key     = openssl_pkey_get_private($pem);
        openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    public function verify($data, $signature, $publicKeyPem) {
        $key = openssl_pkey_get_public($publicKeyPem);
        if (!$key) return false;
        return openssl_verify($data, base64_decode($signature), $key, OPENSSL_ALGO_SHA256) === 1;
    }

    public function getBalance($address) {
        $row = $this->db->row("SELECT balance FROM accounts WHERE address=?", array($address));
        return $row ? $row['balance'] : '0.00000000';
    }

    public function getPendingBalance($address) {
        $row = $this->db->row(
            "SELECT COALESCE(SUM(val+fee),0) AS pending FROM mempool WHERE src=?", array($address)
        );
        return $row ? $row['pending'] : '0.00000000';
    }

    public function getAccount($address) {
        return $this->db->row("SELECT * FROM accounts WHERE address=?", array($address));
    }

    public function getOrCreateAccount($address, $publicKey) {
        $account = $this->getAccount($address);
        if (!$account) {
            $this->db->insert('accounts', array(
                'address'    => $address,
                'public_key' => $publicKey,
                'balance'    => '0.00000000',
                'pending'    => '0.00000000',
                'alias'      => '',
                'first_seen' => time(),
                'last_seen'  => time(),
            ));
            $account = $this->getAccount($address);
        }
        return $account;
    }

    public function resolveAlias($alias) {
        $row = $this->db->row("SELECT address FROM accounts WHERE alias=?", array($alias));
        return $row ? $row['address'] : null;
    }

    public function setAlias($address, $alias) {
        if ($this->resolveAlias($alias)) return false;
        $this->db->update('accounts', array('alias' => $alias), 'address=?', array($address));
        return true;
    }

    public function creditBalance($address, $amount) {
        $this->db->query(
            "UPDATE accounts SET balance=balance+?, last_seen=? WHERE address=?",
            array($amount, time(), $address)
        );
    }

    public function debitBalance($address, $amount) {
        $this->db->query(
            "UPDATE accounts SET balance=balance-?, last_seen=? WHERE address=?",
            array($amount, time(), $address)
        );
    }

    public function base58encode($data) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $decimal  = gmp_init(bin2hex($data), 16);
        $output   = '';
        $zero     = gmp_init(0);
        $base     = gmp_init(58);
        while (gmp_cmp($decimal, $zero) > 0) {
            $rem     = gmp_mod($decimal, $base);
            $decimal = gmp_div_q($decimal, $base);
            $output  = $alphabet[gmp_intval($rem)] . $output;
        }
        for ($i = 0; $i < strlen($data) && $data[$i] === "\x00"; $i++) {
            $output = '1' . $output;
        }
        return $output;
    }

    public function base58decode($data) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $decimal  = gmp_init(0);
        for ($i = 0; $i < strlen($data); $i++) {
            $decimal = gmp_add(gmp_mul($decimal, 58), strpos($alphabet, $data[$i]));
        }
        $hex = gmp_strval($decimal, 16);
        if (strlen($hex) % 2) $hex = '0' . $hex;
        $bytes = hex2bin($hex);
        for ($i = 0; $i < strlen($data) && $data[$i] === '1'; $i++) {
            $bytes = "\x00" . $bytes;
        }
        return $bytes;
    }

    private function privToPem($privBin) {
        $der  = "\x30\x77\x02\x01\x01\x04\x20" . $privBin
              . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
              . "\xa1\x44\x03\x42\x00";
        $b64  = base64_encode($der);
        return "-----BEGIN EC PRIVATE KEY-----\n"
             . chunk_split($b64, 64, "\n")
             . "-----END EC PRIVATE KEY-----\n";
    }
}
