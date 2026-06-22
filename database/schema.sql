-- Steroid V2 Schema — clean rebuild, no LOCK TABLES
-- All writes use PDO transactions only

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `blocks` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `height` bigint(20) UNSIGNED NOT NULL,
  `hash` varchar(128) NOT NULL,
  `prev_hash` varchar(128) NOT NULL DEFAULT '',
  `generator` varchar(64) NOT NULL DEFAULT '',
  `signature` text NOT NULL,
  `nonce` varchar(64) NOT NULL DEFAULT '',
  `difficulty` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `argon` text NOT NULL,
  `transactions` int(11) NOT NULL DEFAULT 0,
  `date` bigint(20) UNSIGNED NOT NULL,
  `reward` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `masternode_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `version` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `height` (`height`),
  UNIQUE KEY `hash` (`hash`),
  KEY `generator` (`generator`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `block` bigint(20) UNSIGNED NOT NULL,
  `height` bigint(20) UNSIGNED NOT NULL,
  `type` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `src` varchar(64) NOT NULL DEFAULT '',
  `dst` varchar(64) NOT NULL DEFAULT '',
  `val` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `fee` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `signature` text NOT NULL,
  `message` text NOT NULL,
  `version` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `date` bigint(20) UNSIGNED NOT NULL,
  `public_key` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `block` (`block`),
  KEY `height` (`height`),
  KEY `src` (`src`),
  KEY `dst` (`dst`),
  KEY `type` (`type`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `accounts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `address` varchar(64) NOT NULL,
  `public_key` text NOT NULL,
  `balance` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `pending` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `alias` varchar(128) NOT NULL DEFAULT '',
  `first_seen` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `last_seen` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `address` (`address`),
  UNIQUE KEY `alias` (`alias`),
  KEY `balance` (`balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mempool` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `src` varchar(64) NOT NULL DEFAULT '',
  `dst` varchar(64) NOT NULL DEFAULT '',
  `val` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `fee` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `signature` text NOT NULL,
  `message` text NOT NULL,
  `version` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `date` bigint(20) UNSIGNED NOT NULL,
  `public_key` text NOT NULL,
  `peer` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `src` (`src`),
  KEY `type` (`type`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `masternode` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `address` varchar(64) NOT NULL,
  `public_key` text NOT NULL,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `collateral` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_seen` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `last_won` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `fails` int(11) NOT NULL DEFAULT 0,
  `vote_key` varchar(64) NOT NULL DEFAULT '',
  `cold_address` varchar(64) NOT NULL DEFAULT '',
  `height` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `address` (`address`),
  KEY `status` (`status`),
  KEY `last_won` (`last_won`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `assets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_id` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL,
  `owner` varchar(64) NOT NULL,
  `total_supply` decimal(30,8) NOT NULL DEFAULT 0.00000000,
  `circulating` decimal(30,8) NOT NULL DEFAULT 0.00000000,
  `description` text NOT NULL,
  `max_supply` decimal(30,8) NOT NULL DEFAULT 0.00000000,
  `inflatable` tinyint(1) NOT NULL DEFAULT 0,
  `fixed_price` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `dividend_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `height` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `date` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_id` (`asset_id`),
  KEY `owner` (`owner`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `assets_balance` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_id` varchar(64) NOT NULL,
  `address` varchar(64) NOT NULL,
  `balance` decimal(30,8) NOT NULL DEFAULT 0.00000000,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_address` (`asset_id`,`address`),
  KEY `address` (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `assets_market` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_id` varchar(64) NOT NULL,
  `type` enum('bid','ask') NOT NULL,
  `address` varchar(64) NOT NULL,
  `amount` decimal(30,8) NOT NULL DEFAULT 0.00000000,
  `price` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `filled` decimal(30,8) NOT NULL DEFAULT 0.00000000,
  `status` enum('open','filled','cancelled') NOT NULL DEFAULT 'open',
  `height` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `date` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `type` (`type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `votes` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `address` varchar(64) NOT NULL,
  `vote_key` varchar(64) NOT NULL,
  `param` varchar(64) NOT NULL,
  `value` varchar(128) NOT NULL,
  `height` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `date` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `param` (`param`),
  KEY `address` (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `peers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `address` varchar(128) NOT NULL,
  `port` int(11) NOT NULL DEFAULT 8080,
  `last_seen` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `fails` int(11) NOT NULL DEFAULT 0,
  `blacklisted` tinyint(1) NOT NULL DEFAULT 0,
  `version` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `address` (`address`),
  KEY `blacklisted` (`blacklisted`),
  KEY `last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `config` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cfg_key` varchar(128) NOT NULL,
  `cfg_val` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cfg_key` (`cfg_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` varchar(32) NOT NULL DEFAULT 'info',
  `message` text NOT NULL,
  `context` text NOT NULL,
  `date` bigint(20) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `apilog` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `endpoint` varchar(128) NOT NULL,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `method` varchar(8) NOT NULL DEFAULT 'GET',
  `params` text NOT NULL,
  `response_code` smallint(5) UNSIGNED NOT NULL DEFAULT 200,
  `date` bigint(20) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `endpoint` (`endpoint`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `heartbeat` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `address` varchar(64) NOT NULL,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `height` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `date` bigint(20) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `address` (`address`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Views
CREATE OR REPLACE VIEW `blockstats` AS
  SELECT
    COUNT(*) AS total_blocks,
    MAX(height) AS current_height,
    AVG(transactions) AS avg_tx_per_block,
    SUM(reward) AS total_issued,
    MAX(date) AS last_block_time
  FROM blocks;

CREATE OR REPLACE VIEW `wallet_stats` AS
  SELECT
    COUNT(*) AS total_accounts,
    SUM(balance) AS total_balance,
    MAX(balance) AS richest_balance
  FROM accounts;

CREATE OR REPLACE VIEW `masternode_rewards` AS
  SELECT
    m.address,
    COUNT(b.id) AS blocks_won,
    SUM(b.reward * 0.5) AS total_earned
  FROM masternode m
  LEFT JOIN blocks b ON b.masternode_id = m.id
  GROUP BY m.id;

-- Genesis config seed
INSERT IGNORE INTO config (cfg_key, cfg_val) VALUES
  ('version', '4.0.0'),
  ('genesis_hash', '0000000000000000000000000000000000000000000000000000000000000000'),
  ('block_reward', '50.00000000'),
  ('masternode_reward_pct', '50'),
  ('fee_pct', '0.3'),
  ('min_masternode_collateral', '250000.00000000'),
  ('difficulty', '1000000'),
  ('block_time', '60'),
  ('max_supply', '21000000.00000000');
