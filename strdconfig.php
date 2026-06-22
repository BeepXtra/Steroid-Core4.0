<?php
defined('_SECURED') or die('Restricted access');

class Config {
    // ─── Database ────────────────────────────────────────────
    public $db_hostname = 'localhost';
    public $db_username = 'global';
    public $db_password = 'WwaHf8hYJrxwXxR9';
    public $db_name     = 'steroidV2';
    public $db_port     = 3306;

    // ─── Node Identity ───────────────────────────────────────
    public $hostname    = 'https://v2.steroid.io';
    public $version     = '4.0.0';
    public $node_name   = 'SteroidV2-Node';

    // ─── Mining ──────────────────────────────────────────────
    public $mining_reward      = 25;          // base block reward (STR)
    public $masternode_reward   = 33;          // % of block reward to MN
    public $fee_percent         = 0.3;         // 0.3% tx fee
    public $min_fee             = 0.00010000;
    public $block_time_target   = 240;         // seconds
    public $argon2_time         = 4;
    public $argon2_memory       = 524288;      // 512 MB
    public $argon2_threads      = 1;
    public $difficulty_retarget = 10;          // blocks between difficulty adjustments

    // ─── Masternode ──────────────────────────────────────────
    public $mn_collateral       = 100000;      // STR required to run MN
    public $mn_lock_blocks      = 720;         // blocks before MN can withdraw
    public $cold_staking_min    = 1000;        // min STR for cold staking

    // ─── Network ─────────────────────────────────────────────
    public $max_peers           = 50;
    public $peer_timeout        = 10;          // seconds
    public $propagation_timeout = 5;
    public $allowed_hosts       = [];          // empty = allow all
    public $passive_peering     = true;
    public $allow_hostname_change = false;

    // ─── Mempool ─────────────────────────────────────────────
    public $mempool_max_age     = 3600;        // seconds before tx expires
    public $mempool_max_size    = 10000;       // max pending txs

    // ─── API ─────────────────────────────────────────────────
    public $api_rate_limit      = 100;         // requests/minute per IP
    public $api_log_enabled     = true;

    // ─── Session ─────────────────────────────────────────────
    public $session_timeout     = 10800;       // 3 hours

    // ─── Paths ───────────────────────────────────────────────
    public $base_path           = '/data/wwwroot/steroidV2';
    public $log_path            = '/data/wwwlogs/steroidV2';

    // ─── BeepXtra Loyalty Integration ────────────────────────
    public $beepxtra_api        = 'http://core-l.beeplocal.net';
    public $beepxtra_enabled    = true;

    // ─── Singleton ───────────────────────────────────────────
    private static $instance = null;
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    private function __construct() {}
}
