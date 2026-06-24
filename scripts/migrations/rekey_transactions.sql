-- =====================================================================
-- P0.2/P0.3 — Re-key the `transactions` table (shrink indexes, fast inserts)
-- =====================================================================
-- WHY: the PRIMARY KEY is currently the random 128-byte base58 `id`. InnoDB
-- copies that 128-byte key into EVERY secondary index, so the indexes are ~23 GB
-- (bigger than the 15.7 GB of actual data), and every insert lands at a random
-- spot in the tree (page splits = slow writes + long lock holds).
--
-- FIX: add a small 8-byte auto-increment `seq` as the PRIMARY KEY and keep `id`
-- as a UNIQUE key. Result: inserts become sequential appends, and every
-- secondary index shrinks from carrying 128 bytes to 8 bytes per row.
--
-- SAFETY: the new `seq` column is purely a local DB optimisation. It is NEVER
-- hashed, signed, or sent to peers — block/transaction hashing uses field VALUES
-- only. All app inserts are `INSERT ... SET col=...` (named), so `seq` is
-- auto-filled and ignored. No application code change is required.
--
-- DO NOT run this as an inline schema migration: on 17M rows it rebuilds the
-- whole 39 GB table and would block the node. Run it via the runbook
-- (docs/runbooks/P0-rekey-transactions.md) using pt-online-schema-change
-- (no downtime) or a planned maintenance window.
-- =====================================================================

-- ---- The change (this is what the runbook applies) -------------------
-- Equivalent ALTER (used directly in a maintenance window, or fed to
-- pt-online-schema-change as the --alter argument):
--
--   ALTER TABLE transactions
--     ADD COLUMN seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
--     DROP PRIMARY KEY,
--     ADD PRIMARY KEY (seq),
--     ADD UNIQUE KEY uniq_id (id);
--
-- ---------------------------------------------------------------------

ALTER TABLE `transactions`
  ADD COLUMN `seq` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`seq`),
  ADD UNIQUE KEY `uniq_id` (`id`);

-- ---- Target structure after the change (for reference) ---------------
-- PRIMARY KEY (`seq`)            <- 8-byte sequential key
-- UNIQUE KEY  `uniq_id` (`id`)   <- keeps fast WHERE id=... lookups + uniqueness
-- KEY `block_id` (`block`)       <- now carries seq(8) instead of id(128)
-- KEY `height` (`height`)
-- KEY `idx_pubkey_height` (`public_key`(130),`height`)
-- KEY `idx_dst_height` (`dst`,`height`)
-- KEY `idx_version_height` (`version`,`height`)
-- CONSTRAINT `height` FOREIGN KEY (`height`) REFERENCES `blocks` (`height`)
