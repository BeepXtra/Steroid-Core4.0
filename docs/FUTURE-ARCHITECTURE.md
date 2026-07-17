# Steroid — Next-Generation Core: Architecture & Upgrade Plan

> Steroid has been built as a **staged roadmap since its 2018 inception**: first a
> proven, rapidly-deployable node to launch the network and validate the retail +
> loyalty model, then — once real adoption pushed the first stage toward its known
> limits — a purpose-built, horizontally-scalable high-performance core. This
> document plans that next stage: keep the first-stage chain healthy while building
> the next-generation core in parallel (a controlled "strangler" migration, not a
> risky big-bang rewrite). The core technology is chosen against the target
> architecture and consensus design defined below.

---

# PART I — Current stack & Phase 0 stabilization

## 0. Why this document exists

Steroid is a retail-payments + loyalty blockchain (BeepXtra is its first
use-case). From inception in 2018 the roadmap was deliberately **staged**: ship a
proven, rapidly-deployable **first-stage node** (PHP/MySQL) to launch the network,
prove out the retail/loyalty model, and grow real integrations — then graduate to
a **purpose-built high-performance core** as adoption approached the first stage's
designed limits.

The first stage has done exactly its job: a live chain past **2M blocks**, with
masternodes, an on-chain asset/DEX layer, dividends, aliases, cold staking and
on-chain governance, plus real third-party integrations (BeepWallet, outlets,
MerchD). As anticipated, sustained growth is now bringing it to the performance
and scaling ceiling inherent to a single-database design (detailed in §1). That
milestone is the planned trigger — foreseen from day one — to execute the
next-generation upgrade.

This document (a) keeps the first-stage chain fast and healthy in the meantime,
and (b) defines the next-generation core and the path to it.

---

## 1. Root-cause diagnosis (current stack)

| # | Root cause | Where | Effect at 17M rows |
|---|-----------|-------|--------------------|
| 1 | **Global `LOCK TABLES … WRITE`** on every block | `SBlock::add` L68; `delete` L1299; `delete_id` L1360 | Whole-DB exclusive lock held across dividend loops + DEX matching + per-tx validation. All readers/writers queue → metadata locks, "stuck" queries. **#1 production pain.** |
| 2 | **Random `varbinary(128)` PK** on `transactions` | `schema.inc.php` L63–75, L105 | Random B-tree inserts (page splits); every secondary index stores the 128-byte PK → huge indexes, slow inserts. |
| 3 | **Index bloat + FK CASCADE** | `schema.inc.php` L105–113, L147, L225–226, L298–320 | ~8 secondary indexes incl. `FULLTEXT(message)`; FK cascade `transactions↔blocks↔accounts` interacts badly with full-table locks. |
| 4 | **Full-table aggregations in the hot path** | `SBlock::masternode_votes` L334/L374 (`GROUP BY message`); `parse_block` per-tx `COUNT(1)` L1194 | Full scans / per-tx point lookups executed *while holding the global lock*. |
| 5 | **No separation of concerns / no archival** | whole DB | One MySQL is ledger + world-state + index + query layer. No partitioning, no cold storage, no read replica. |

### Measured on galileo — production `S4QL` chain (height 2,046,403)

Read-only diagnosis, confirming the table above with real numbers:

| table | rows | data | **index** | note |
|-------|------|------|-----------|------|
| `transactions` | **17.16M** | 15.7 GB | **23.0 GB** | indexes are **1.5× the data** |
| `blocks` | 1.66M | 780 MB | 343 MB | avg **~8.4 tx/block** |
| `logs` | 784k | 267 MB | 150 MB | reverse-log (block rollback) |
| `accounts` | 37.9k | 9 MB | 22 MB | small |

`transactions` PK = `varbinary(128)` (random base58 `id`). InnoDB appends that
128-byte PK to **all 7 secondary indexes** (`block_id`, `height`, `version`,
`idx_pubkey_height(public_key(130),height)`, `idx_dst_height(dst,height)`,
`idx_version_height(version,height)`, `FULLTEXT(message)`) + FK `height→blocks`.
Every insert mutates 8 B-trees keyed on a 128-byte random value → page splits →
long-held global lock. **Index bloat is the dominant cost, and it is structural.**

Quantified quick wins (measured):
- `version` index is **fully covered** by `idx_version_height` → drop (safe).
- `FULLTEXT(message)` is large and likely low-value → confirm usage, then drop.
- Re-keying to a `BIGINT` surrogate PK shrinks the per-row secondary-index
  overhead from ~128 bytes to 8 bytes → multi-GB reduction across all 7 indexes
  and far faster inserts. High impact; online migration.

(Phase 0 status of these is recorded in §2d.)

### The first-stage ceiling (by design)
The first-stage node was optimised for **fast, reliable launch and correctness**,
not ultimate throughput — planetary scale was always earmarked for the
next-generation core. Its limits are therefore **expected design boundaries, not
defects**, and they're the signal that it's time to graduate:
- **~60s target block time** × a deliberately modest per-block tx cap → throughput
  ideal for bootstrapping a network, but not for world-scale.
- **Argon2 PoW + round-robin masternode** production → simple and robust to launch,
  but probabilistic settlement; retail at scale wants **sub-second deterministic
  finality** — a next-generation goal (PART II).
- **Single-database, full-replica** model → easy and dependable to operate, but it
  scales vertically only. **The next-gen design scales via a dual-path design:
  consensus-less payment certificates for retail + a BFT consensus chain**
  (PART II, D2a/D2b).

---

## 2. Phase 0 — First-stage stabilization (current stack, low risk)

Goal: stop metadata locks, restore headroom, buy 12–18 months of runway. **No
consensus rule changes.** Every item is validated against galileo (read-only
diagnostics first) before deployment, because lock-semantics changes on a live
chain can fork/corrupt it if done blind.

**P0.1 — Remove the global `LOCK TABLES` on the block path.**
The block-add path already runs inside `beginTransaction()`/`commit()`, so the
explicit `LOCK TABLES` is redundant *if* writes are correctly ordered and state
updates use row locks (`SELECT … FOR UPDATE`) on the specific accounts touched.
This is the single biggest win and the most consensus-sensitive change.
**STATUS: shipped to master (#32) as the conservative first step — a single
serializing *advisory lock* (`GET_LOCK`) replacing `LOCK TABLES`**, which removes
the metadata-lock stalls without yet relying on per-account row locking. Moving to
true row-level (`FOR UPDATE`) concurrency remains a follow-up.

**P0.2 — Re-key `transactions`.** Add surrogate `BIGINT UNSIGNED AUTO_INCREMENT`
PK; keep the base58 `id` as a `UNIQUE` (or covered) key. Converts random-insert
hotspots into sequential appends; shrinks every secondary index.
**STATUS: implemented on the Phase-0 branch (surrogate `seq` PK + online-migration
runbook); not yet merged to master; live DB state not re-verified here.**

**P0.3 — Trim & partition.** Drop `FULLTEXT(message)` and any redundant indexes
(audit with `pt-index-usage`); **partition `transactions` by `height` range**;
move blocks older than the cold horizon to an `transactions_archive` table /
replica. Reassess FK CASCADE (consider app-level integrity instead).
**STATUS: index drops implemented on the Phase-0 branch (reported applied to live
S4QL per change log; not merged to master, live state unverified here);
partitioning + archival + FK-CASCADE rework still PENDING.**

**P0.4 — De-hot-path aggregations.** Precompute vote tallies & dividend
candidates into small summary tables updated incrementally; the block path reads
the summary instead of scanning `transactions`.

**P0.5 — Read replica + query routing.** Point explorer/API/wallet reads at a
replica so block production never competes with read traffic.

**Exit criteria:** no metadata-lock stalls under production load; p99 block-add
time bounded; API read latency decoupled from block production.

### 2b. Phase 0 progress log (history)

- **2026-06-22 — P0.3 (partial), DONE on live S4QL, zero disruption.** Dropped the
  unused `FULLTEXT(message)` index (verified no `MATCH…AGAINST` anywhere in code)
  and the redundant `version` index (covered by `idx_version_height`). Online
  (`ALGORITHM=INPLACE, LOCK=NONE`). `transactions` secondary indexes 7→5; B-trees
  per insert 8→6 (~25% less write amplification → shorter global-lock hold).
  Chain continued forging throughout (height advanced, last block ~12s after).
- **2026-06-22 — formalized as migration `dbversion 14`** in
  `library/includes/schema.inc.php` (idempotent: only drops if the index exists),
  so every node in the cluster receives the fix via Git rather than manual DDL.
- **2026-06-22 — P0.1 AUTHORED (advisory-lock approach), awaiting operator deploy.**
  Replaced the global `LOCK TABLES … WRITE` in `SBlock::add/delete/delete_id`
  (and the disabled `util.php resync-accounts`) with a MySQL advisory lock
  (`GET_LOCK('steroid_block_apply', 30)` / `RELEASE_LOCK`). Writers (forge / sync /
  pop / delete) still serialize, but **readers are no longer blocked** — they see
  committed state via InnoDB MVCC. Advisory lock is not dropped by
  `START TRANSACTION`, so writer serialization is stronger than the old table
  lock. Per-MySQL-server scope = correct per-node behavior in a cluster. Lint
  clean; every exit path releases the lock; no re-entrancy.
- **2026-06-22 — P0.2/P0.3 AUTHORED.** Re-key `transactions` to an 8-byte
  `seq` BIGINT auto-increment PK, keeping `id` as `UNIQUE` — shrinks every
  secondary index (the 23 GB bloat) and makes inserts sequential. No app code
  change (all inserts are named `SET col=...`; `seq` never hashed/sent).
  Delivered as `scripts/migrations/rekey_transactions.sql` +
  `docs/runbooks/P0-rekey-transactions.md` (sample-rehearsal first, then online
  `pt-online-schema-change` = no downtime, or a maintenance window). Cold-archival
  follow-on included. Operator runs it (NOT an auto inline migration — too heavy).

### 2c. Working model

- **LARS** (beta): used for **read-only** info-gathering on live infra (server +
  DB inspection) and for the **new-build sandbox** — **never** to mutate live
  production files. All live DDL/index work already applied manually is now
  back-filled into versioned migrations.
- **Phase 0 fixes:** delivered via **GitHub** (code + versioned SQL migrations).
  **Operator deploys to the galileo masternode.**
- **Rebuild:** may be test-driven on LARS (new build only).

### 2d. Phase 0 status

1. ✅ Diagnosis on galileo (numbers in §1).
2. ✅ Index trim shipped + formalized as `dbversion 14`.
3. ✅ **P0.1** advisory-lock de-freeze — **PR #32 merged to master**.
4. ◻ **P0.2/P0.3** `transactions` re-key to BIGINT `seq` PK — implemented on the
   Phase-0 branch (online `pt-online-schema-change` runbook); not yet merged to
   master; live state not re-verified here.
5. ◻ **P0.3** partition-by-height + cold archival + FK-CASCADE rework — PENDING.
6. ◻ **P0.4** de-hot-path aggregations, **P0.5** read replica — PENDING.

---
---

# PART II — Next-generation core (decided architecture)

> Authoritative spec for building the next-generation Steroid core on the
> `lars/rebuild` branch. The first-stage chain stays in production until cutover.
> Infra note: galileo runs custom-compiled MySQL 5.7.25 at `/usr/local/mysql`; the
> first-stage node is the git checkout at `/data/wwwroot/g4l1l3o` (tracks `master`).

## 3. Goals (non-negotiable)

1. **Speed + transaction capacity are premium.**
2. **Enterprise quality.**
3. **Scale from the first store to the entire planet.**
4. **Retail payments are first-class.**
5. **Full feature parity** with the first stage (§5) — nothing is lost.
6. **Scale via dual execution paths, not chain heroics:**
   - **Retail fast path (D2a):** consensus-less **payment certificates**
     (FastPay/Sui model) — every tap verified by a 2/3+ validator quorum
     **before** approval, final in ~200–400ms, **zero double-spend risk, zero
     merchant risk, no offline mode**. Capacity scales with validator hardware,
     not consensus: **100k+ TPS is real on this path** (FastPay measured ~160k
     TPS with 20 authorities; Sui runs the model in production).
   - **Consensus path (D2b):** ordinary CometBFT blocks for smart contracts,
     mass-pay/multi-output, and anything concurrent — funds verified at
     execution, ~1–2s finality. Slower is acceptable here by design.
   - Shard only as a last resort (D2).

> **Capacity framing (honest, for all external material):** "100k+ TPS" =
> the retail fast path (D2a), where every transaction is quorum-verified and
> final before the merchant accepts. Consensus-path (block) throughput target
> is 3–5k TPS sustained. Both numbers are defensible; never conflate them.

## 4. Architecture

A **dual-path payment network** on one validator set:

1. **Retail fast path (D2a)** — consensus-less payment certificates. Merchant
   edge node broadcasts the customer-signed transfer to all validators in
   parallel; 2/3+ signatures = an irrevocable certificate. Verified on the
   spot, final in ~200–400ms, no blocks involved. Certificates settle into
   checkpoint blocks via the `total`/`available`/`locked` model.
2. **Consensus path (D2b)** — BFT proof-of-stake blocks, today's
   **masternodes are the validator set** (**cap: 70 active validators**),
   **~400–500ms blocks**, instant deterministic finality, 3–5k TPS sustained.
   Carries smart contracts, assets, mass-pay, governance, and fast-path
   checkpoints. No PoW, no probabilistic settlement.

**Stack: Go + Cosmos SDK (v0.50+) + CometBFT, ABCI 2.0.**
- **CometBFT BFT-PoS** → instant finality; validators = masternodes.
- **Cosmos SDK modules** reused: `x/bank` (balances/transfer), `x/staking`
  (validator bonding ↔ the 250k masternode stake), `x/gov` (↔ on-chain votes),
  `x/authz`, `x/feegrant`. Custom modules: assets, alias, `x/fastpay` (D2a).
- **Storage:** RocksDB backend + **memiavl / store/v2** commitment layer (NOT
  legacy on-disk IAVL — it is the known state-throughput bottleneck). Light-client
  proofs preserved.

## 5. Feature parity — nothing is lost

Inventoried from the first-stage code; the next-generation core MUST carry all of
these forward:

- **Accounts/wallet:** base58 ECDSA keys/addresses, balances, **aliases** (tx v2/v3).
- **Transactions:** standard transfer (v1), 0.3% fee, emission/reward schedule
  (`SBlock::reward`), mempool with fee-priority ordering.
- **Masternodes:** 250k-BPC stake (v100–v104), pause/resume, IP update, blacklist
  logic, selection, **cold staking** rewards.
- **Governance:** masternode voting (v105 vote-key, v106 MN-blacklist votes, v107
  blockchain-parameter votes), `votes` table params (emission30,
  masternodereward50, endless10reward, coldstacking).
- **Assets / tokens (v50–v59):** creation, transfer, and **optional declarable
  params** (manual/auto-dividends, dividend-only, inflatable supply, fixed-price).
  See D5.
- **Platform:** REST API + SDK (`sdk/php`), apidoc (`doc/`), block explorer,
  peer/propagation protocol, sanity/sync, checkpoints, and a REST compatibility
  surface for existing external clients (D7).

## 6. Decisions (final)

### D1 — Core stack & framework
**Go + Cosmos SDK + CometBFT.** Basis of all consensus/feature decisions below.

### D1a — Consensus & participation model
**Consensus:** **CometBFT BFT-PoS over the bonded masternode set, capped at 70
active validators** (CometBFT gossip degrades past ~100) → instant finality.
**No PoW.**

**Proposer rotation:**
- **v1: stock CometBFT weighted round-robin.** Deterministic is acceptable — the
  set is bonded, slashable, and publicly known. Zero custom consensus code in v1.
- **v2: ECVRF (RFC 9381, ed25519) proposer rotation**, enforced via ABCI 2.0
  `ProcessProposal`. **Entropy source: validator VRF outputs only. User
  transactions NEVER feed the beacon** (tx-fed entropy is grindable by the block
  proposer — permanently excluded).

**"Participate by using" model:**
- **Merchant edge nodes:** each merchant's Steroid instance also runs a light
  edge/relay node → every store adds ingress/read capacity.
- **Proof-of-usage rewards** (→ D5a).
- **Phone light clients:** consumer devices verify their own balances/txs trustlessly.
- **Excluded:** consumer-phone compute, storage/CDN, hashrate, and tx-fed
  VRF entropy (grindable — see above).

### D2 — Throughput & scaling strategy
**Dual execution paths on one validator set (D2a fast path + D2b consensus
path).**
- Consensus path: single global state, instant BFT finality, ABCI 2.0 mempool
  lanes, edge read-replicas. **3–5k TPS sustained is the target — no Block-STM /
  optimistic-execution work** (revisit only if the consensus path actually
  saturates).
- Retail per-tap capacity comes from D2a, which involves **no consensus round at
  all** — validators verify independently and in parallel; capacity scales with
  validator hardware and account-space sharding *inside* each validator.
- **Native `total` / `available` / `locked` accounting spans both paths:**
  fast path locks at certificate time and settles at checkpoint; consensus path
  locks and settles within block execution.

**Chain-level sharding — last resort only** (v3), by **account-space with
load-aware rebalancing** — not geography, not IBC at the till. With the fast
path absorbing retail load, it is unlikely to ever trigger.

### D2a — Retail fast path: payment certificates (the capacity answer)
**Consensus-less, quorum-verified transfers (FastPay model; run in production
by Sui as its single-owner fast path). Every payment is verified by validators
BEFORE the merchant approves. No offline mode. No merchant risk. Funds are
final.**

Per tap:
1. **Intent:** customer wallet signs `{payer, payee, amount, nonce}`.
2. **Broadcast:** the merchant edge node sends the intent to **all active
   validators in parallel** (online-only — no uplink, no sale).
3. **Independent verification:** each validator checks signature + `available`
   balance + nonce, moves the amount `available → locked`, and returns its
   signature. A validator NEVER signs two transfers with the same
   `(account, nonce)`.
4. **Certificate:** the edge node aggregates **2/3+ validator signatures** into
   a certificate. By quorum intersection, a conflicting certificate for the same
   `(account, nonce)` **cannot exist** — double-spend is impossible, not
   "risk-managed". The certificate is the merchant's proof of final,
   irrevocable payment.
5. **Latency:** one parallel round trip → **~200–400ms** verified finality.
6. **Settlement:** certificates are folded into periodic **checkpoint blocks**
   on the consensus path (D2b): `locked` clears, payee `total`/`available`
   credit, fees applied, state committed to the authenticated store.

**Rules & recovery:**
- **One in-flight transfer per account** (per nonce). Retail-fine; wallets
  enforce sequencing.
- A client that equivocates (signs two intents with one nonce) can wedge only
  **its own account** until the next checkpoint reconciles it. Nobody else is
  affected; funds are never double-spent.
- Certificate format: canonical deterministic encoding; verifiable by anyone
  (including on the consensus path) against the known validator set for that
  epoch.
- Validator-set changes (epoch boundaries) gate the fast path: certificates are
  valid against the epoch's registered set; checkpoints carry set updates.

**Capacity:** no global ordering step → validators process transfers
independently, parallel across cores/machines (account-space partitioning
inside the validator). FastPay's published benchmark: **~160k TPS at 20
authorities**; this is the honest, verifiable basis of the "100k+ TPS" claim
(§3). New custom module + sidecar service: `x/fastpay` + validator fast-path
daemon.

### D2b — Consensus path: contracts, mass-pay, concurrency
Everything that is not a simple single-payer retail transfer goes through
normal CometBFT block consensus — **this mode is stock chain behaviour, zero
extra machinery:**
- Smart-contract calls (CosmWasm, D6a), asset ops (D5), governance, staking.
- **Mass-pay:** one transaction, N outputs (batch-transfer msg) — 10k payouts =
  one consensus tx, funds verified once against the payer's balance at
  execution.
- Wallets needing **concurrent sends** (contracts, payout engines): submit to
  the consensus path; block ordering resolves concurrency; validators verify
  funds directly at execution. ~1–2s finality — acceptable by design; balance
  verification, not speed, is the requirement here.

**Routing is per-transaction, not per-wallet:** simple transfer → D2a;
multi-output, contract call, or concurrent stream → D2b. The same account may
use both (D2a lock rules apply while a certificate is in flight).

### D3 — Address / key / wallet compatibility
**Preserve base58 addresses via a custom address codec** (secp256k1 keys
unchanged). Existing addresses, balances and wallets keep working; enables a clean
state migration from S4QL.

### D4 — Economics: PoS
Masternodes = **bonded validators** (250k = min self-bond); **cold staking →
delegation**; **emission → staking rewards**; keep the **0.3% fee**;
governance via `x/gov` + existing vote semantics. **No PoW.** Exact numbers
(emission curve, reward splits, min bond) finalized at build (§8).

### D5 — Feature-parity modules
Reuse built-in balances/staking/governance modules. Build custom modules:
- **assets — permissionless token launch.** Any BPC wallet can create a token: pay
  a **creation fee**, declare **parameters + supply**, and fund a **per-asset fee
  pool** that covers the fees for transacting in that token (so holders don't need
  BPC to move it). Optional declarable params: manual/auto-dividends, dividend-only,
  inflatable supply, fixed-price.
- **alias** — human-readable names.

The chain exposes only payments + token primitives. Proof-of-usage rewards → D5a.

### D5a — Proof-of-usage rewards (on-chain)
A native on-chain mechanism that rewards users for **genuine** activity.

**Principle: unprofitable to farm by construction; every check is O(1) (single
state lookup); no identity, no Sybil-ring detection.**

- **Merchants fund rewards, not the network.** A merchant locks two on-chain pots:
  a slashable **stake/bond**, and a **bounded reward pool** that is the *only*
  source of their customers' rewards. No emission/inflation funds this.
- **Reward = `r` × fee paid, `r` < 1, always** → any self-dealing loop is
  net-negative per iteration.
- **Same-pair diminishing returns:** a per-`(payer, payee)` counter decays `r`
  toward ~0 for repeated loops over the same pair. **Counters live in
  epoch-bucketed state that expires and is pruned** (e.g. rolling N epochs) —
  state growth is bounded by construction, never open-ended per-pair rows.
- **Counterparty diversity** weighting via a bounded per-account decaying tally
  (same epoch-bucket + pruning rule).
- **Misbehaviour → slash + automatic on-chain delisting.**

### D6 — State & storage
**RocksDB backend + memiavl / store/v2 commitment layer** — no from-scratch
engine, and **no legacy on-disk IAVL** (known state-throughput bottleneck; picking
it now repeats the "aging default" mistake). Deliver Steroid's AnyData +
smart-contract capabilities as **modules + a deliberate data model on top**
(D6a/D6b).

### D6a — Smart contracts / DApps
First-class DApp support via **CosmWasm** (mature Wasm contracts for this stack);
contract state lives in the standard store. Gas/integration model finalized at build (§8).

### D6b — "AnyData" on-chain data — **v2 ships AnyData-lite**
**Designed to avoid chain bloat:** small data inline on-chain; large data =
**on-chain hash/commitment + blob pinned on ≥3 merchant edge nodes** (D8).

**v2 scope (AnyData-lite):** simple retrievability check — validators
periodically **fetch-and-verify** a random pinned blob against its on-chain hash;
failure → withhold that node's rewards. No erasure coding, no custom PoR
protocol. (~1 week of work, covers the real need.)

**Deferred (v3+ / only if demand proves it):** erasure-coded k-of-n replication,
cryptographic proof-of-retrievability, or an external DA layer
(Celestia/IPFS+Filecoin/Arweave). This is a Celestia-scale project — explicitly
out of v2 scope. Size limits + fee-by-size finalized at build (§8).

### D7 — API & SDK compatibility
Ship a **generic REST compatibility gateway** mirroring today's endpoints so
existing external clients survive the migration; expose the new native API
alongside for new development. (`doc/` apidoc + `sdk/` are the API-parity contract.)

### D8 — Edge / self-managed routing
**Control plane is separate from consensus — nodes never write proxy config.**
Membership = the chain's **signed, quorum-agreed validator set** (single source of
truth). The edge uses **dynamic upstreams** (HAProxy **Data Plane API** or Envoy
**xDS**): add/drain servers at runtime, **no rewrite-and-reload**. Edges are
**read-only consumers** of signed membership → a compromised node can't poison
routing; updates are **debounced/epoch-batched**. Reads + tx ingress are
load-balanced; writes route by account-space (only relevant if/when sharded, D2).

### D9 — Phased delivery
- **v1:** transfers + fees, masternode validators (**stock CometBFT proposer
  rotation**, 70-cap, D1a), governance, the API/wallet **compatibility gateway**
  (D7), and **state migration from S4QL** (balances + base58 addresses carry
  over, D3/D10). A real, usable, migratable chain. **Zero custom consensus code.**
- **v1.5:** the **retail fast path** (`x/fastpay` + validator fast-path daemon
  + merchant edge node, D2a). This is the retail-capacity release.
- **v2:** assets/tokens (permissionless launch + per-asset fee pool; optional
  dividends/inflatable/fixed-price), **smart contracts (CosmWasm, D6a)**,
  **AnyData-lite (D6b)**, **proof-of-usage rewards (D5a)**, **ECVRF proposer
  rotation (D1a)**.
- **v3 (only if needed):** **account-space sharding** (last resort, D2), full
  AnyData durability (D6b deferred items), + the **dynamic self-managing edge**
  (D8).

### D10 — Migration / cutover
Snapshot S4QL state (accounts/balances, assets + asset balances,
masternodes, governance/votes, aliases) → new-core **genesis**, **base58 addresses
preserved** (D3). Validate against live, then cut over; keep a read-only historical
bridge for the explorer. The live chain is **lightly used now** → favour a **fast
snapshot + cutover** over a long dual-run, and do it **before usage grows**.

### D11 — Repo & project setup
The `lars/rebuild` branch is the home of the new Go/Cosmos core (PHP `master` stays
the live chain). Branch cleaned to a fresh starting point; `doc/` (apidoc) + `sdk/`
kept as **API-parity references** for D7. The build session sets up CI, linters,
tests, and license. **License choice is left to the dev community — to be discussed
and decided at the next dev meeting.**

## 7. Build sequence

1. Confirm Go + Cosmos SDK (v0.50+) + CometBFT scaffolding on the cleaned
   `lars/rebuild`; storage wired to **memiavl / store/v2** from day one (D6).
2. Build **v1** (D9): balances/transfers + fees; CometBFT over the bonded
   masternode set (**stock proposer rotation, 70-cap**, D1a); `x/gov`; the
   **API/wallet compatibility gateway** (D7, using `doc/` apidoc + `sdk/` as the
   contract); and the **S4QL → genesis migration tool** (D10) with base58
   addresses preserved (D3).
3. Build **v1.5**: `x/fastpay` module + validator fast-path daemon + merchant
   edge node (D2a).
4. Build **v2**: assets (permissionless launch + per-asset fee pool; optional
   dividends/inflatable/fixed-price), CosmWasm (D6a), AnyData-lite (D6b),
   proof-of-usage rewards (D5a), ECVRF proposer rotation (D1a).
5. **v3 only if the settlement chain saturates:** account-space sharding +
   dynamic edge + full AnyData durability.

## 8. Design detail to finalize during build

These are build-time parameters, not open architecture questions:
- **D2a** — checkpoint interval, certificate canonical encoding, intent expiry,
  fast-path fee mechanics (charged at checkpoint), equivocation-reconciliation
  rule, epoch/validator-set rotation gating, validator fast-path hardware
  baseline (cores/RAM per N TPS).
- **D4** — emission curve, reward splits, minimum bond.
- **D5** — asset creation fee; per-asset fee-pool mechanics (funding, draw-down,
  top-up, exhaustion behaviour); optional-param definitions.
- **D5a** — exact `r`, decay curves, epoch-bucket length + retention N, pool
  sizing/refill, diversity-tally shape.
- **D6a** — CosmWasm gas model + integration.
- **D6b** — inline-vs-hash size limits, fee-by-size, pin-count (default 3),
  fetch-and-verify challenge frequency.
- **D1a (v2)** — ECVRF integration details (RFC 9381, ed25519, ABCI 2.0
  `ProcessProposal` enforcement); (if v3 sharding is ever triggered) per-shard
  committee sizing, per-epoch rotation, overlap + automatic fallback to the full
  set if 2/3 isn't reached within N rounds.
- **D10** — state reconciliation/audit step (balance-for-balance proof that the
  new-core genesis matches live S4QL state before cutover); brief tx-freeze window
  on the old chain to take a clean point-in-time snapshot.

---
---

# PART III — Build-team handoff

> Execution guide for the build team. Code sessions run on Claude (Sonnet /
> Opus); multi-session work is orchestrated by **LARS** (Opus coordinator agent
> driving parallel Opus code sessions). This part tells both humans and agents
> exactly how to work.

## 9. Repo, branch & environment

- **Repo:** the Steroid repo; **all next-gen work on `lars/rebuild` only.**
  PHP `master` is the live chain — **never** push next-gen code to `master` or
  any other branch without explicit operator approval.
- **Language/stack:** Go (latest stable), Cosmos SDK v0.50+, CometBFT, ABCI 2.0,
  RocksDB + memiavl/store-v2.
- **References in-repo:** `doc/` (apidoc) + `sdk/` = the D7 API-parity contract.
  Treat them as the spec for the compatibility gateway.
- **CI from day one:** build, `golangci-lint`, unit tests, and a 4-node
  localnet integration test on every PR to `lars/rebuild`.

## 10. Workstream breakdown (parallelizable)

Each workstream = one owned code session. Interfaces between them are the
module boundaries below; agree proto/types first, then build in parallel.

| WS | Deliverable | Depends on | Phase |
|----|-------------|-----------|-------|
| A | Chain scaffold: app wiring, config, memiavl/store-v2, localnet, CI | — | v1 |
| B | `x/steroidbank`: total/available/locked balance semantics on top of `x/bank`; 0.3% fee; emission schedule port (`SBlock::reward` parity) | A | v1 |
| C | Staking/governance adaptation: 250k bond, 70-validator cap, pause/resume, blacklist, cold-staking → delegation mapping, vote-semantics parity (v105–v107) | A | v1 |
| D | `x/alias` | A, B | v1 |
| E | Migration tool: S4QL snapshot → genesis; base58 address codec (D3); balance-for-balance reconciliation report (D10) | B, C | v1 |
| F | D7 compatibility gateway: REST endpoints mirroring `doc/` apidoc, backed by the new chain's gRPC/API | A, B | v1 |
| G | `x/fastpay`: validator fast-path daemon, certificate aggregation, checkpoint settlement, merchant edge node (D2a) | B, C | v1.5 |
| H | `x/assets` (D5), CosmWasm integration (D6a) | B | v2 |
| I | AnyData-lite (D6b), proof-of-usage (D5a), ECVRF rotation (D1a v2) | C, G | v2 |

## 11. Session protocol (Claude code sessions)

- **One workstream per session.** Session receives: this document, its WS row,
  the relevant D-sections, and the current interface definitions (protos).
- **Proto-first:** define/agree message types and module interfaces before
  implementation; interface changes require coordinator sign-off (see §12).
- **Definition of done per WS:** code + unit tests (≥ the module's consensus
  paths at 100%) + integration test on the 4-node localnet + updated module
  README. Not "compiles".
- **Consensus-critical code** (anything in the state machine): deterministic
  only — no maps iterated without sorted keys, no floats, no time.Now(), no
  randomness outside the VRF module. Reviewed by a second session before merge.
- **Feature-parity tests:** every §5 item gets an explicit test that maps
  old-chain behaviour → new-chain behaviour (E's reconciliation report is the
  ultimate parity test).
- **No scope creep:** if a session believes the spec is wrong, it stops and
  reports — it does not redesign.

## 12. LARS orchestration model

- **Coordinator (Opus):** owns the WS dependency graph (§10), assigns
  workstreams to worker sessions, gatekeeps interface/proto changes, merges only
  green-CI branches into `lars/rebuild`.
- **Workers (Opus/Sonnet sessions):** one WS each; Sonnet for well-specified
  mechanical work (gateway endpoints, codecs, test scaffolding), Opus for
  consensus-adjacent and novel modules (B, C, G, I).
- **State:** the coordinator keeps WS status + interface registry in LARS
  memory; every worker session starts by reading it.
- **LARS scope rules (unchanged from §2c):** read-only against live infra;
  sandbox/new-build only for mutations; **never** touches the live chain,
  `master`, or production DBs.
- **Human gate:** merges to `lars/rebuild` mainline, any dependency addition,
  and anything touching migration/cutover (E, D10) require operator review.

## 13. Milestone acceptance criteria

- **M1 (scaffold):** 4-node localnet produces blocks at ~500ms; CI green.
- **M2 (v1 feature-complete):** all §5 v1-scope items pass parity tests;
  gateway serves the `doc/` apidoc surface; migration tool produces a genesis
  from a live S4QL snapshot with a clean balance-for-balance reconciliation.
- **M3 (v1 load):** sustained 3k TPS on reference hardware, p99 finality <1s,
  24h soak without state growth anomalies.
- **M4 (v1.5):** certificate round-trip (intent → parallel broadcast → 2/3+
  quorum → certificate) p99 <500ms on geo-distributed localnet; scripted
  double-spend attempts NEVER yield two certificates for one (account, nonce);
  equivocating account wedges only itself and reconciles at next checkpoint;
  checkpoint settlement clears `locked` correctly; sustained ≥50k fast-path TPS
  on reference validator hardware (scaling test toward 100k+).
- **M5 (v2):** asset launch + per-asset fee pool end-to-end; CosmWasm contract
  deploy/execute; AnyData-lite pin + fetch-and-verify cycle; proof-of-usage
  rewards net-negative under a scripted farming attack; ECVRF rotation active
  and enforced via `ProcessProposal`.
- **Cutover gate (D10):** M2–M4 green + reconciliation audit signed off by the
  operator; tx-freeze window rehearsed on a staging snapshot first.
