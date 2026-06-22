<?php
defined('_SECURED') or die('Restricted access');

/**
 * BeepXtraController — BeepXtra loyalty platform integration.
 * Hooks for transaction events, reward distribution, outlet callbacks.
 */
class BeepXtraController {
    private SCore  $app;
    private Config $cfg;

    public function __construct(SCore $app) {
        $this->app = $app;
        $this->cfg = $app->make('config');
    }

    // ─── Transaction Hook ─────────────────────────────────────

    /**
     * Called after a transaction is confirmed in a block.
     * Notifies BeepXtra core API for loyalty point processing.
     */
    public function onTransactionConfirmed(array $tx, array $block): void {
        if (!$this->cfg->beepxtra_enabled) return;

        $payload = [
            'event'      => 'tx_confirmed',
            'tx_id'      => $tx['id'],
            'src'        => $tx['src'],
            'dst'        => $tx['dst'],
            'val'        => $tx['val'],
            'fee'        => $tx['fee'],
            'version'    => $tx['version'],
            'block_id'   => $block['id'],
            'height'     => $block['height'],
            'timestamp'  => $block['date'],
        ];
        $this->post('/steroid/tx_confirmed', $payload);
    }

    // ─── Block Hook ──────────────────────────────────────────

    public function onBlockConfirmed(array $block): void {
        if (!$this->cfg->beepxtra_enabled) return;

        $payload = [
            'event'      => 'block_confirmed',
            'block_id'   => $block['id'],
            'height'     => $block['height'],
            'generator'  => $block['generator'],
            'timestamp'  => $block['date'],
        ];
        $this->post('/steroid/block_confirmed', $payload);
    }

    // ─── Reward Hook ─────────────────────────────────────────

    public function onRewardDistributed(string $address, float $amount, string $type): void {
        if (!$this->cfg->beepxtra_enabled) return;

        $payload = [
            'event'   => 'reward_distributed',
            'address' => $address,
            'amount'  => $amount,
            'type'    => $type, // 'mining' | 'masternode' | 'dividend'
        ];
        $this->post('/steroid/reward', $payload);
    }

    // ─── Outlet Lookup ───────────────────────────────────────

    /**
     * Lookup a BeepXtra outlet by Steroid address.
     * Returns outlet data or null if not found.
     */
    public function lookupOutlet(string $address): ?array {
        $response = $this->get('/steroid/outlet?address=' . urlencode($address));
        if (!$response) return null;
        $data = json_decode($response, true);
        return $data['outlet'] ?? null;
    }

    // ─── Wallet Lookup ───────────────────────────────────────

    public function lookupWallet(string $address): ?array {
        $response = $this->get('/steroid/wallet?address=' . urlencode($address));
        if (!$response) return null;
        $data = json_decode($response, true);
        return $data['wallet'] ?? null;
    }

    // ─── HTTP Helpers ────────────────────────────────────────

    private function post(string $path, array $data): bool {
        $url = rtrim($this->cfg->beepxtra_api, '/') . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            error_log("BeepXtraController: {$path} returned HTTP {$code}");
            return false;
        }
        return true;
    }

    private function get(string $path): string|false {
        $url = rtrim($this->cfg->beepxtra_api, '/') . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200 ? $body : false;
    }
}
