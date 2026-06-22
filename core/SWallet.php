<?php
defined('_SECURED') or die('Restricted access');

/**
 * SWallet — ECDSA secp256k1, base58, address derivation.
 */
class SWallet {
    private $db;
    const BASE58_CHARS = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public function __construct($db) {
        $this->db = $db;
    }

    public function generateKeyPair() {
        $config = array(
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'secp256k1',
        );
        $res = openssl_pkey_new($config);
        if (!$res) throw new RuntimeException('SWallet: failed to generate key pair');
        openssl_pkey_export($res, $privateKeyPem);
        $details   = openssl_pkey_get_details($res);
        $pubKeyPem = $details['key'];
        $address   = $this->publicKeyToAddress($pubKeyPem);
        return array(
            'private_key' => $privateKeyPem,
            'public_key'  => $pubKeyPem,
            'address'     => $address,
        );
    }

    public function publicKeyToAddress($pubKeyPem) {
        $der       = $this->pemToDer($pubKeyPem);
        $hash      = hash('sha256', $der, true);
        $hash      = hash('ripemd160', $hash, true);
        $versioned = "\x00" . $hash;
        $checksum  = substr(hash('sha256', hash('sha256', $versioned, true), true), 0, 4);
        return $this->base58Encode($versioned . $checksum);
    }

    public function sign($data, $privateKeyPem) {
        openssl_sign($data, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    public function verify($data, $signatureB64, $pubKeyPem) {
        $signature = base64_decode($signatureB64);
        $result    = openssl_verify($data, $signature, $pubKeyPem, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    public function txSignatureString($tx) {
        return implode('-', array(
            isset($tx['src'])     ? $tx['src']     : '',
            isset($tx['dst'])     ? $tx['dst']     : '',
            isset($tx['val'])     ? $tx['val']     : '',
            isset($tx['fee'])     ? $tx['fee']     : '',
            isset($tx['message']) ? $tx['message'] : '',
            isset($tx['version']) ? $tx['version'] : '',
            isset($tx['date'])    ? $tx['date']    : '',
        ));
    }

    public function getAccount($address) {
        return $this->db->fetchOne('SELECT * FROM accounts WHERE id = ?', array($address));
    }

    public function getBalance($address) {
        $r = $this->db->fetchColumn('SELECT balance FROM accounts WHERE id = ?', array($address));
        return $r !== null ? $r : '0.00000000';
    }

    public function accountExists($address) {
        return (bool)$this->db->fetchColumn('SELECT COUNT(*) FROM accounts WHERE id = ?', array($address));
    }

    public function createAccount($address, $pubKey, $blockId) {
        $this->db->upsert('accounts', array(
            'id'         => $address,
            'public_key' => $pubKey,
            'block'      => $blockId,
            'balance'    => '0.00000000',
        ));
    }

    public function updateBalance($address, $delta) {
        $this->db->execute('UPDATE accounts SET balance = balance + ? WHERE id = ?', array($delta, $address));
    }

    public function base58Encode($data) {
        $chars        = self::BASE58_CHARS;
        $leadingZeros = 0;
        for ($i = 0; $i < strlen($data) && $data[$i] === "\x00"; $i++) $leadingZeros++;
        $num    = gmp_import($data);
        $result = '';
        $base   = gmp_init(58);
        while (gmp_cmp($num, 0) > 0) {
            list($num, $rem) = array(
                gmp_div_q($num, $base),
                gmp_div_r($num, $base),
            );
            $result = $chars[gmp_intval($rem)] . $result;
        }
        return str_repeat('1', $leadingZeros) . $result;
    }

    public function base58Decode($data) {
        $chars        = self::BASE58_CHARS;
        $num          = gmp_init(0);
        $base         = gmp_init(58);
        $leadingZeros = 0;
        for ($i = 0; $i < strlen($data) && $data[$i] === '1'; $i++) $leadingZeros++;
        for ($i = 0; $i < strlen($data); $i++) {
            $pos = strpos($chars, $data[$i]);
            if ($pos === false) throw new InvalidArgumentException('Invalid base58 character');
            $num = gmp_add(gmp_mul($num, $base), gmp_init($pos));
        }
        $decoded = gmp_export($num);
        return str_repeat("\x00", $leadingZeros) . $decoded;
    }

    public function validateAddress($address) {
        try {
            $decoded  = $this->base58Decode($address);
            $payload  = substr($decoded, 0, -4);
            $checksum = substr($decoded, -4);
            $expected = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
            return hash_equals($expected, $checksum);
        } catch (Exception $e) {
            return false;
        }
    }

    private function pemToDer($pem) {
        $pem = preg_replace('/-----[^-]+-----/', '', $pem);
        return base64_decode(preg_replace('/\s+/', '', $pem));
    }
}
