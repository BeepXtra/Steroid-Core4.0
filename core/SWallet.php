<?php
defined('_SECURED') or die('Restricted access');

/**
 * SWallet — Cryptographic wallet operations
 * ECDSA secp256k1, base58check address derivation, signature verify/sign.
 * Compatible with existing Steroid address format.
 */
class SWallet {
    private Database $db;

    // secp256k1 curve parameters
    const BASE58_CHARS = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public function __construct(Database $db) {
        $this->db = $db;
    }

    // ─── Key Generation ──────────────────────────────────────

    public function generateKeyPair(): array {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'secp256k1',
        ];
        $res = openssl_pkey_new($config);
        if (!$res) throw new RuntimeException('SWallet: failed to generate key pair');

        openssl_pkey_export($res, $privateKeyPem);
        $details  = openssl_pkey_get_details($res);
        $pubKeyPem = $details['key'];

        $address = $this->publicKeyToAddress($pubKeyPem);

        return [
            'private_key' => $privateKeyPem,
            'public_key'  => $pubKeyPem,
            'address'     => $address,
        ];
    }

    // ─── Address Derivation ──────────────────────────────────

    public function publicKeyToAddress(string $pubKeyPem): string {
        // Extract raw public key bytes
        $der    = $this->pemToDer($pubKeyPem);
        $hash   = hash('sha256', $der, true);
        $hash   = hash('ripemd160', $hash, true);
        // Version byte 0x00 (mainnet)
        $versioned = "\x00" . $hash;
        $checksum  = substr(hash('sha256', hash('sha256', $versioned, true), true), 0, 4);
        return $this->base58Encode($versioned . $checksum);
    }

    // ─── Signing & Verification ──────────────────────────────

    public function sign(string $data, string $privateKeyPem): string {
        openssl_sign($data, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    public function verify(string $data, string $signatureB64, string $pubKeyPem): bool {
        $signature = base64_decode($signatureB64);
        $result    = openssl_verify($data, $signature, $pubKeyPem, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    // ─── Transaction Signature String ────────────────────────

    public function txSignatureString(array $tx): string {
        return implode('-', [
            $tx['src']     ?? '',
            $tx['dst']     ?? '',
            $tx['val']     ?? '',
            $tx['fee']     ?? '',
            $tx['message'] ?? '',
            $tx['version'] ?? '',
            $tx['date']    ?? '',
        ]);
    }

    // ─── Account Lookup ──────────────────────────────────────

    public function getAccount(string $address): ?array {
        return $this->db->fetchOne(
            'SELECT * FROM accounts WHERE id = ?',
            [$address]
        );
    }

    public function getBalance(string $address): string {
        return $this->db->fetchColumn(
            'SELECT balance FROM accounts WHERE id = ?',
            [$address]
        ) ?: '0.00000000';
    }

    public function accountExists(string $address): bool {
        return (bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM accounts WHERE id = ?',
            [$address]
        );
    }

    public function createAccount(string $address, string $pubKey, string $blockId): void {
        $this->db->upsert('accounts', [
            'id'         => $address,
            'public_key' => $pubKey,
            'block'      => $blockId,
            'balance'    => '0.00000000',
        ]);
    }

    public function updateBalance(string $address, string $delta): void {
        $this->db->execute(
            'UPDATE accounts SET balance = balance + ? WHERE id = ?',
            [$delta, $address]
        );
    }

    // ─── Base58 ──────────────────────────────────────────────

    public function base58Encode(string $data): string {
        $chars   = self::BASE58_CHARS;
        $leadingZeros = 0;
        for ($i = 0; $i < strlen($data) && $data[$i] === "\x00"; $i++) {
            $leadingZeros++;
        }
        $num = gmp_import($data);
        $result = '';
        $base   = gmp_init(58);
        while (gmp_cmp($num, 0) > 0) {
            [$num, $rem] = gmp_div_qr($num, $base);
            $result = $chars[gmp_intval($rem)] . $result;
        }
        return str_repeat('1', $leadingZeros) . $result;
    }

    public function base58Decode(string $data): string {
        $chars  = self::BASE58_CHARS;
        $num    = gmp_init(0);
        $base   = gmp_init(58);
        $leadingZeros = 0;
        for ($i = 0; $i < strlen($data) && $data[$i] === '1'; $i++) {
            $leadingZeros++;
        }
        for ($i = 0; $i < strlen($data); $i++) {
            $pos = strpos($chars, $data[$i]);
            if ($pos === false) throw new InvalidArgumentException('Invalid base58 character');
            $num = gmp_add(gmp_mul($num, $base), gmp_init($pos));
        }
        $decoded = gmp_export($num);
        return str_repeat("\x00", $leadingZeros) . $decoded;
    }

    public function validateAddress(string $address): bool {
        try {
            $decoded  = $this->base58Decode($address);
            $payload  = substr($decoded, 0, -4);
            $checksum = substr($decoded, -4);
            $expected = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
            return hash_equals($expected, $checksum);
        } catch (Throwable) {
            return false;
        }
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function pemToDer(string $pem): string {
        $pem = preg_replace('/-----[^-]+-----/', '', $pem);
        return base64_decode(preg_replace('/\s+/', '', $pem));
    }
}
