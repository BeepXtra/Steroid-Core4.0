<?php
defined('_SECURED') or die('Restricted access');

class SWallet {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    // ── Key Generation ───────────────────────────────────────────────────────

    public function generateKeyPair(): array {
        $privateKey = bin2hex(random_bytes(32));
        $publicKey  = $this->privateKeyToPublic($privateKey);
        $address    = $this->publicKeyToAddress($publicKey);
        return compact('privateKey', 'publicKey', 'address');
    }

    public function privateKeyToPublic(string $privateKeyHex): string {
        // secp256k1 via OpenSSL
        $privBin = hex2bin($privateKeyHex);
        $pem     = $this->privToPem($privBin);
        $key     = openssl_pkey_get_private($pem);
        $details = openssl_pkey_get_details($key);
        return base64_encode($details['key']);
    }

    public function publicKeyToAddress(string $publicKey): string {
        // sha512 x9 → ripemd160 → base58check
        $hash = $publicKey;
        for ($i = 0; $i < 9; $i++) {
            $hash = hash('sha512', $hash, true);
        }
        $hash    = hash('ripemd160', $hash, true);
        $payload = "\x3f" . $hash; // version byte 0x3f → addresses start with 'S'
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
        return $this->base58encode($payload . $checksum);
    }

    // ── Signing / Verification ───────────────────────────────────────────────

    public function sign(string $data, string $privateKeyHex): string {
        $privBin = hex2bin($privateKeyHex);
        $pem     = $this->privToPem($privBin);
        $key     = openssl_pkey_get_private($pem);
        openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    public function verify(string $data, string $signature, string $publicKeyPem): bool {
        $key = openssl_pkey_get_public($publicKeyPem);
        if (!$key) return false;
        return openssl_verify($data, base64_decode($signature), $key, OPENSSL_ALGO_SHA256) === 1;
    }

    // ── Account Helpers ──────────────────────────────────────────────────────

    public function getBalance(string $address): string {
        $row = $this->db->row(
            "SELECT balance FROM accounts WHERE address=?", [$address]
        );
        return $row ? $row['balance'] : '0.00000000';
    }

    public function getPendingBalance(string $address): string {
        $row = $this->db->row(
            "SELECT COALESCE(SUM(val+fee),0) AS pending FROM mempool WHERE src=?", [$address]
        );
        return $row ? $row['pending'] : '0.00000000';
    }

    public function getAccount(string $address): ?array {
        return $this->db->row(
            "SELECT * FROM accounts WHERE address=?", [$address]
        );
    }

    public function getOrCreateAccount(string $address, string $publicKey): array {
        $account = $this->getAccount($address);
        if (!$account) {
            $this->db->insert('accounts', [
                'address'    => $address,
                'public_key' => $publicKey,
                'balance'    => '0.00000000',
                'pending'    => '0.00000000',
                'alias'      => '',
                'first_seen' => time(),
                'last_seen'  => time(),
            ]);
            $account = $this->getAccount($address);
        }
        return $account;
    }

    public function resolveAlias(string $alias): ?string {
        $row = $this->db->row(
            "SELECT address FROM accounts WHERE alias=?", [$alias]
        );
        return $row ? $row['address'] : null;
    }

    public function setAlias(string $address, string $alias): bool {
        // Alias must be unique
        if ($this->resolveAlias($alias)) return false;
        $this->db->update('accounts', ['alias' => $alias], 'address=?', [$address]);
        return true;
    }

    public function creditBalance(string $address, string $amount): void {
        $this->db->query(
            "UPDATE accounts SET balance=balance+?, last_seen=? WHERE address=?",
            [$amount, time(), $address]
        );
    }

    public function debitBalance(string $address, string $amount): void {
        $this->db->query(
            "UPDATE accounts SET balance=balance-?, last_seen=? WHERE address=?",
            [$amount, time(), $address]
        );
    }

    // ── Base58 ───────────────────────────────────────────────────────────────

    public function base58encode(string $data): string {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $decimal  = gmp_init(bin2hex($data), 16);
        $output   = '';
        while (gmp_cmp($decimal, 0) > 0) {
            [$decimal, $rem] = gmp_div_qr($decimal, 58);
            $output = $alphabet[gmp_intval($rem)] . $output;
        }
        for ($i = 0; $i < strlen($data) && $data[$i] === "\x00"; $i++) {
            $output = '1' . $output;
        }
        return $output;
    }

    public function base58decode(string $data): string {
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

    // ── Internal ─────────────────────────────────────────────────────────────

    private function privToPem(string $privBin): string {
        $der  = "\x30\x77\x02\x01\x01\x04\x20" . $privBin
              . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
              . "\xa1\x44\x03\x42\x00";
        // Minimal valid secp256k1 private key DER wrapper for OpenSSL
        $b64  = base64_encode($der);
        return "-----BEGIN EC PRIVATE KEY-----\n"
             . chunk_split($b64, 64, "\n")
             . "-----END EC PRIVATE KEY-----\n";
    }
}
