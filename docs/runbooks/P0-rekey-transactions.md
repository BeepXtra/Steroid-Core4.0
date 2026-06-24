# Runbook — Shrink the giant `transactions` table (re-key)

## In plain words

The big `transactions` table is slow because of how row IDs are stored. We swap in
a small, fast ID as the table's primary key and keep the old ID for lookups.

- **Result:** much smaller indexes, much faster saves, shorter lock times.
- **App code:** no changes needed.
- **Risk control:** we practise on a small sample first to measure the time, then
  run the real one **with the node still online** (no downtime).

There are two ways to run the real change:

- **Method A (recommended): online, no downtime.** Uses a tool called
  `pt-online-schema-change`. The node keeps producing/serving the whole time.
- **Method B (fallback): short maintenance window.** Simpler, but block-writing
  pauses while it runs (galileo is the only active masternode, so use this only
  if the rehearsal shows it finishes quickly and a pause is acceptable).

Facts gathered from galileo: table ≈ 39 GB, disk free ≈ 65 GB (enough for the
online run), only 1 active masternode, online tool not yet installed.

---

## Step 1 — Rehearse on a SMALL sample (always do this first)

Full-table cloning would need another ~39 GB and risks filling the disk, so we
measure speed on a sample and extrapolate.

```bash
DBP='-uglobal -pWwaHf8hYJrxwXxR9'

# fresh staging db + a 1,000,000-row sample with the SAME structure
mysql $DBP -e "DROP DATABASE IF EXISTS S4QL_stage; CREATE DATABASE S4QL_stage;"
mysql $DBP S4QL_stage -e "CREATE TABLE transactions LIKE S4QL.transactions;"
mysql $DBP S4QL_stage -e "INSERT INTO transactions SELECT * FROM S4QL.transactions LIMIT 1000000;"

# time the re-key on the sample
time mysql $DBP S4QL_stage -e "
  ALTER TABLE transactions
    ADD COLUMN seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (seq),
    ADD UNIQUE KEY uniq_id (id);"

# verify: same row count, lookups still work, structure correct
mysql $DBP S4QL_stage -e "SELECT COUNT(*) FROM transactions;"
mysql $DBP S4QL_stage -t -e "SHOW CREATE TABLE transactions\G"
# size before/after for the sample
mysql $DBP -t -e "SELECT ROUND((data_length+index_length)/1024/1024,1) total_mb, ROUND(index_length/1024/1024,1) index_mb FROM information_schema.tables WHERE table_schema='S4QL_stage' AND table_name='transactions';"

# clean up the sample
mysql $DBP -e "DROP DATABASE S4QL_stage;"
```

**Extrapolate:** real table is ~17.2M rows. If the sample (1M rows) took `T`
seconds, the full COPY-style rebuild is roughly `17 × T`. Use that to pick Method
A vs B and to plan the window if you choose B.

---

## Step 2A — Run it online (recommended, no downtime)

```bash
# one-time install of the online tool (Ubuntu/Debian)
apt-get update && apt-get install -y percona-toolkit   # provides pt-online-schema-change

# check free disk first: need ~40 GB free (we have ~65 GB)
df -h /

# online re-key (node stays up; live writes are captured via triggers)
pt-online-schema-change \
  --alter "ADD COLUMN seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY (seq), ADD UNIQUE KEY uniq_id (id)" \
  --no-check-alter \
  --chunk-time=0.5 \
  --max-load "Threads_running=40" --critical-load "Threads_running=120" \
  --host=localhost --user=global --password='WwaHf8hYJrxwXxR9' \
  D=S4QL,t=transactions \
  --print --execute
```

Notes:
- `--no-check-alter` is required because we change the primary key (pt-osc chunks
  the copy on the existing `id`, which we keep, so this is safe).
- pt-osc builds a shadow table, copies in small chunks, keeps it in sync with
  triggers, then swaps atomically. If anything looks wrong you can Ctrl-C before
  the swap and nothing is lost.

---

## Step 2B — Run it in a maintenance window (fallback)

Only if rehearsal shows it finishes fast enough and a write-pause is acceptable.

```bash
# 1. stop this node's block production + sync (cron jobs / miner / sanity loop)
#    -- operator-specific; ensure mine.php / sanity.php are not running.
# 2. apply the change directly
mysql -uglobal -pWwaHf8hYJrxwXxR9 S4QL < scripts/migrations/rekey_transactions.sql
# 3. restart the node; it will sync any blocks it missed during the window.
```

During this ALTER, reads still work but block WRITES are blocked until it finishes.

---

## Step 3 — Verify after the real run

```bash
DBP='-uglobal -pWwaHf8hYJrxwXxR9'
# structure: PRIMARY KEY(seq) + UNIQUE uniq_id(id)
mysql $DBP S4QL -t -e "SHOW CREATE TABLE transactions\G"
# index size should be well down from 23,054 MB
mysql $DBP S4QL -t -e "SELECT ROUND(data_length/1024/1024,0) data_mb, ROUND(index_length/1024/1024,0) index_mb FROM information_schema.tables WHERE table_schema='S4QL' AND table_name='transactions';"
# chain still advancing + a known tx still looks up fast
mysql $DBP S4QL -t -e "SELECT MAX(height) FROM blocks;"
```

Then watch a few blocks get produced/synced normally before rolling this out to
any other nodes.

---

## Optional follow-on — archive cold history (extra breathing room)

After the re-key, move very old rows out of the hot table to shrink it further.
Keep it simple and reversible:

```sql
-- create an archive table once (same structure)
CREATE TABLE transactions_archive LIKE transactions;
-- move rows below a cold horizon (example: keep last ~500k blocks hot)
-- run in batches to avoid long locks; pick @cold from current height - 500000
-- INSERT INTO transactions_archive SELECT * FROM transactions WHERE height < @cold LIMIT 50000;
-- DELETE FROM transactions WHERE height < @cold LIMIT 50000;  -- repeat until done
```

(The explorer can read both tables with a UNION when showing deep history.)
