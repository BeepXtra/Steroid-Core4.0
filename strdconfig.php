<?php
defined('_SECURED') or die('Restricted access');

// ─── Database ────────────────────────────────────────────────────────────────
define('DB_HOST',   'localhost');
define('DB_SOCKET', '/tmp/mysql.sock');
define('DB_NAME',   'steroidV2');
define('DB_USER',   'global');
define('DB_PASS',   'WwaHf8hYJrxwXxR9');
define('DB_CHARSET','utf8mb4');

// ─── Node Identity ───────────────────────────────────────────────────────────
define('NODE_VERSION',  '4.0.0');
define('NODE_HOST',     'https://steroidv2.steroid.io');
define('NODE_PORT',     8080);
define('CHAIN_NAME',    'SteroidV2');

// ─── Blockchain Parameters ───────────────────────────────────────────────────
define('BLOCK_TIME',             60);        // seconds
define('BLOCK_REWARD',           '50.00000000');
define('MAX_SUPPLY',             '21000000.00000000');
define('FEE_PCT',                0.003);     // 0.3%
define('MIN_FEE',                '0.00100000');
define('MASTERNODE_COLLATERAL',  '250000.00000000');
define('MASTERNODE_REWARD_PCT',  50);        // % of block reward
define('MINER_REWARD_PCT',       50);        // % of block reward
define('DIFFICULTY_RETARGET',    10);        // blocks
define('MAX_MEMPOOL_AGE',        3600);      // seconds
define('MAX_PEERS',              50);
define('PEER_FAIL_LIMIT',        5);

// ─── Argon2 Mining ───────────────────────────────────────────────────────────
define('ARGON_MEMORY',    512);
define('ARGON_TIME',      1);
define('ARGON_THREADS',   1);
define('ARGON_PREFIX',    '$argon2i$');

// ─── Transaction Versions ────────────────────────────────────────────────────
define('TX_REWARD',          0);
define('TX_TRANSFER',        1);
define('TX_ALIAS_PAY',       2);
define('TX_ALIAS_SET',       3);
define('TX_ASSET_CREATE',    50);
define('TX_ASSET_TRANSFER',  51);
define('TX_ASSET_BID',       52);
define('TX_ASSET_ASK',       53);
define('TX_ASSET_CANCEL',    54);
define('TX_ASSET_INFLATE',   55);
define('TX_ASSET_DIVIDEND',  56);
define('TX_ASSET_FIXED',     57);
define('TX_ASSET_FILL',      58);
define('TX_MN_REGISTER',     100);
define('TX_MN_PAUSE',        101);
define('TX_MN_RESUME',       102);
define('TX_MN_BLACKLIST',    103);
define('TX_MN_UNBLACKLIST',  104);
define('TX_GOV_PROPOSAL',    105);
define('TX_GOV_VOTE',        106);
define('TX_GOV_RESULT',      107);
define('TX_COLD_STAKE',      108);
define('TX_MN_FAIL',         111);

// ─── CDN / External ──────────────────────────────────────────────────────────
define('CDN_URL',    'https://cdn.beepxtra.com');
define('API_URL',    NODE_HOST . '/api');

// ─── Paths ───────────────────────────────────────────────────────────────────
define('BASE_PATH',  '/data/wwwroot/steroidV2');
define('LOG_PATH',   BASE_PATH . '/logs');
