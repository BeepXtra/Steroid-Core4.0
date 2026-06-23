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
- `version` index is **fully covered** by `idx_version_height` → drop now (safe).
- `FULLTEXT(message)` is large and likely low-value → confirm usage, then drop.
- Re-keying to a `BIGINT` surrogate PK shrinks the per-row secondary-index
  overhead from ~128 bytes to 8 bytes → multi-GB reduction across all 7 indexes
  and far faster inserts. High impact; requires online migration on a replica.

### The first-stage ceiling (by design)
The first-stage node was optimised for **fast, reliable launch and correctness**,
not ultimate throughput — planetary scale was always earmarked for the
next-generation clustered core. Its limits are therefore **expected design
boundaries, not defects**, and they're the signal that it's time to graduate:
- **~60s target block time** × a deliberately modest per-block tx cap → throughput
  ideal for bootstrapping a network, but not for world-scale on a single chain.
- **Argon2 PoW + round-robin masternode** production → simple and robust to launch,
  but probabilistic settlement; retail at scale wants **sub-second deterministic
  finality** — a next-generation goal (§3).
- **Single-database, full-replica** model → easy and dependable to operate, but it
  scales vertically only. **Horizontal/sharded scale is the next-gen design** (§3b).

---

## 2. Phase 0 — First-stage stabilization (current stack, low risk)

Goal: stop metadata locks, restore headroom, buy 12–18 months of runway. **No
consensus rule changes.** Every item is validated against galileo (read-only
diagnostics first) before deployment, because lock-semantics changes on a live
chain can fork/corrupt it if done blind.

**P0.1 — Replace global `LOCK TABLES` with InnoDB row-level transactions.**
The block-add path already runs inside `beginTransaction()`/`commit()`. The
explicit `LOCK TABLES` is redundant *if* writes are correctly ordered and the
mempool/state updates use row locks (`SELECT … FOR UPDATE`) on the specific
accounts touched. This is the single biggest win and the most consensus-sensitive
change — sequence it first, test on a forked copy of galileo state, deploy to one
node, observe, then roll out.

**P0.2 — Re-key `transactions`.** Add surrogate `BIGINT UNSIGNED AUTO_INCREMENT`
PK; keep the base58 `id` as a `UNIQUE` (or covered) key. Converts random-insert
hotspots into sequential appends; shrinks every secondary index.

**P0.3 — Trim & partition.** Drop `FULLTEXT(message)` and any redundant indexes
(audit with `pt-index-usage`); **partition `transactions` by `height` range**;
move blocks older than the cold horizon to an `transactions_archive` table /
replica. Reassess FK CASCADE (consider app-level integrity instead).

**P0.4 — De-hot-path aggregations.** Precompute vote tallies & dividend
candidates into small summary tables updated incrementally; the block path reads
the summary instead of scanning `transactions`.

**P0.5 — Read replica + query routing.** Point explorer/API/wallet reads at a
replica so block production never competes with read traffic.

**Exit criteria:** no metadata-lock stalls under production load; p99 block-add
time bounded; API read latency decoupled from block production.

---

## 3. Phase 1 — New core engine (built in parallel)

Design principles (language-agnostic by decision):

- **Storage:** embedded **LSM / key-value store** (e.g. RocksDB-class), *not* a
  relational DB. State as an authenticated key-value tree (Merkle/IAVL-style) for
  light-client proofs.
- **Consensus:** **BFT/PoS-style deterministic finality** (fast, final,
  payment-appropriate) replacing Argon2 PoW. Masternodes become the validator
  set — preserves the existing economic/governance model.
- **Execution:** account/balance state machine with the existing transaction
  versions as native operations (see §4). Deterministic, parallelizable where
  the access sets are disjoint.
- **Networking:** structured p2p (libp2p-class) with gossip + fast block sync /
  state snapshots.
- **Compatibility (non-negotiable):** **same address format, same tx semantics &
  signing scheme, same 0.3% fee model, same asset/loyalty/governance features,
  and a compatible REST API surface** so BeepWallet, outlets, MerchD and the SDK
  keep working with minimal changes.
- **Scale path:** **horizontal by design** — throughput grows by adding nodes to
  a cluster, never by making one node bigger. See §3b.

**Language decision (deferred):** to be made *after* this architecture is signed
off. Candidates on the table: Rust (max performance/safety), Go (gentler curve,
proven in Cosmos/geth), or a PHP-fronted native hot-path module (least
disruption, lower ceiling). Decision criteria: target TPS/finality SLA, team
ramp, hiring, ecosystem (consensus & p2p libs).

---

## 3b. Horizontal scale — masternode cluster + self-managed HAProxy

**Decision:** the 100k-TPS / world-scale target is met by a **cluster**, not a
single node. The new core is horizontally scalable from day one, and the
Steroid4.0 codebase itself manages the cluster topology (HAProxy edges, node
discovery) — no external orchestration required.

**Tiers:**

1. **Ingress / API edge (stateless, behind HAProxy).** Many edge nodes accept
   transactions and serve reads. They verify signatures, do cheap validation, and
   route. HAProxy config is **generated and reloaded by the Steroid4.0 codebase**
   as nodes join/leave (service discovery + health checks). HAProxy round-robins
   *reads and tx ingress* freely — these are stateless.

2. **Consensus / validator tier (masternodes).** BFT-style finality among
   masternodes. A single BFT group has a practical ceiling (~few thousand TPS) due
   to all-to-all voting messaging — so one group alone is *not* enough.

3. **Sharded consensus for linear scale.** Run **multiple consensus groups
   (shards)**, each owning a slice of state, coordinated by a lightweight beacon
   layer. Throughput then scales ~linearly with shard count:
   *~3–5k TPS/shard × ~20–30 shards ≈ 100k TPS*, and you add shards to grow.

**Retail-aware sharding (the key fit):** shard by **merchant / store / region**.
Retail traffic is overwhelmingly *local* (customer ↔ store), so the vast majority
of transactions are **single-shard and fast**. Only cross-region transfers are
cross-shard. This maps the BeepXtra use-case onto the architecture almost
perfectly and keeps the expensive cross-shard path rare.

**Important design nuances (so the cluster is correct, not just fast):**

- **Writes are shard-routed, not load-balanced.** HAProxy LB is for the stateless
  edge (ingress + reads). A *write* must reach the validators that own that
  account's shard — the edge routes by shard key (address/merchant), it does not
  round-robin writes across consensus groups.
- **Cross-shard atomicity:** inter-shard transfers need a 2-phase / lock-then-
  commit protocol (or a beacon-ordered receipt model) to preserve no-double-spend
  across shards. This is the main complexity to design carefully.
- **Global invariants:** total supply, emission, masternode registry and
  governance are global state — kept on a coordination shard / beacon and cached
  read-only at the edges.
- **Read replicas at the edge** absorb explorer/wallet/API load so reads never
  touch the consensus hot path.

**Migration implication:** even before sharding, the *same repo running on many
masternodes behind HAProxy* (today's model) already gives read scale + ingress
scale + redundancy. Sharded *write* consensus is the Phase-1 core's headline
feature and what unlocks the throughput ceiling.

---

## 4. Feature-parity checklist (everything from the first stage carries forward)

Inventoried from the first-stage code. The next-generation core MUST carry all of
these forward:

- **Accounts/wallet:** base58 ECDSA keys/addresses, balances, **aliases** (tx v2/v3).
- **Transactions:** standard transfer (v1), 0.3% fee, emission/reward schedule
  (`SBlock::reward`), mempool with fee-priority ordering.
- **Masternodes:** 250k-BPC stake (v100–v104), pause/resume, IP update, blacklist
  logic, round-robin selection, **cold staking** rewards.
- **Governance:** masternode voting (v105 vote-key, v106 MN-blacklist votes, v107
  blockchain-parameter votes), `votes` table params (emission30,
  masternodereward50, endless10reward, coldstacking).
- **Assets / tokens (v50–v59):** creation, transfer, **on-chain DEX** (bid/ask
  matching), manual + **auto-dividends**, dividend-only assets, inflatable supply,
  fixed-price assets, tradable flag.
- **Platform:** REST API + SDK (`sdk/php`), apidoc (`doc/`), block explorer,
  peer/propagation protocol, sanity/sync, checkpoints, retail/loyalty integration
  hooks (BeepXtra outlets, BeepWallet, MerchD).

---

## 5. Phase 2 — Migration & cutover

1. Freeze a height; export an authenticated **state snapshot** (accounts, assets,
   balances, masternodes, governance state) → new-core **genesis checkpoint**.
2. Run new core in **shadow** alongside the live chain; replay/compare.
3. Cut over validators; keep a read-only bridge of historical data for the
   explorer.

---

## 5b. Phase 0 progress log

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
  **Rehearsal/deploy checklist (operator):**
  1. Pull branch on a *non-validating* spare node or a clone of galileo first.
  2. `php -l` the two files (already verified) and run a short sync to apply a few
     hundred blocks; confirm height advances and balances stay consistent.
  3. Watch `SHOW PROCESSLIST` + `SELECT IS_USED_LOCK('steroid_block_apply')` —
     confirm reads no longer queue behind block-apply.
  4. Deploy to galileo; observe one masternode through several won blocks before
     rolling cluster-wide.
- **Next:** P0.2/P0.3 surrogate BIGINT PK + partition-by-height + cold archival
  (rehearsed on a staging clone) → P0.5 read replica.
- **2026-06-22 — P0.2/P0.3 AUTHORED.** Re-key `transactions` to an 8-byte
  `seq` BIGINT auto-increment PK, keeping `id` as `UNIQUE` — shrinks every
  secondary index (the 23 GB bloat) and makes inserts sequential. No app code
  change (all inserts are named `SET col=...`; `seq` never hashed/sent).
  Delivered as `scripts/migrations/rekey_transactions.sql` +
  `docs/runbooks/P0-rekey-transactions.md` (sample-rehearsal first, then online
  `pt-online-schema-change` = no downtime, or a maintenance window). Cold-archival
  follow-on included. Operator runs it (NOT an auto inline migration — too heavy).

## 5c. Working model (agreed)

- **LARS** (beta): used for **read-only** info-gathering on live infra (server +
  DB inspection) and for the **new-build sandbox** — **never** to mutate live
  production files. All live DDL/index work already applied manually is now
  back-filled into versioned migrations.
- **Phase 0 fixes:** authored by Claude and delivered via **GitHub** (code +
  versioned SQL migrations). **Operator deploys to the galileo masternode.**
- **Rebuild:** may be test-driven on LARS (new build only).

## 6. Phase 0 status — COMPLETE (2026-06-22)

1. ✅ Diagnosis on galileo (numbers in §1).
2. ✅ Index trim shipped + formalized as `dbversion 14`.
3. ✅ **P0.1** advisory-lock de-freeze — deployed to galileo, **PR #32 merged to master**.
4. ✅ **P0.2/P0.3** `transactions` re-keyed to BIGINT `seq` PK via online
   `pt-online-schema-change` — **no downtime**, ~48 GB reclaimed, chain healthy.

The first-stage chain is now stable and fast. **The next-generation upgrade is the
forward track — see PART II below (the handoff brief for the new code session).**

---
---

# PART II — Rebuild Proposal & Decision Sequence (HANDOFF BRIEF)

> **Audience: the new code session that will build the next-generation Steroid
> core.** LARS could not execute the build, so a fresh code session takes over.
> Read PART I first (diagnosis + the now-complete Phase 0 stabilization). This part
> is the authoritative brief. Each decision is **PENDING** until the owner
> confirms; it then becomes **DECIDED** with date + rationale. Do not start
> building modules until D1–D4 are DECIDED.

## II.0 Current state (2026-06-22)
- First-stage chain `S4QL` on **galileo**, height ~2.05M, healthy after Phase 0
  stabilization (it stays in production until cutover).
- The next-generation core is **greenfield**: this `lars/rebuild` branch has been
  cleared of the first-stage PHP scaffold and seeded as a clean starting point.
- Infra note: galileo runs custom-compiled MySQL 5.7.25 at `/usr/local/mysql`;
  the first-stage node is the git checkout at `/data/wwwroot/g4l1l3o` (tracks
  `master`).

## II.1 Goals (owner-stated, non-negotiable)
1. **Speed + transaction capacity are premium.**
2. **Enterprise quality.**
3. **Scale from the first store to the entire planet** — a *cluster*, never one box.
4. **Retail payments + loyalty are first-class** (BeepXtra is the first use-case).
5. **Full feature parity** with the first stage (PART I §4) — nothing is lost.
6. **Horizontal scale** via a cluster of nodes behind **self-managed HAProxy** with
   **retail-aware sharding** (PART I §3b).

## II.2 Recommended architecture (the proposal)
A **BFT proof-of-stake** chain where today's **masternodes become the validator
set**, giving **deterministic sub-second-to-few-second finality** (what retail
needs — not probabilistic PoW). Horizontal scale via **multiple chains/zones
sharded by region/merchant**, interconnected for cross-shard settlement, with a
stateless HAProxy edge for reads/ingress and shard-routed writes.

Primary recommendation for the stack: **Go + Cosmos SDK + CometBFT**, because it
delivers the above with mature, audited building blocks and the gentlest ramp for
a PHP team:
- **CometBFT (Tendermint) BFT-PoS** → instant finality; validators = masternodes.
- **Cosmos SDK modules** give us, for free: a balances/transfer module, `x/staking`
  (validator bonding ↔ the 250k masternode stake), `x/gov` (↔ on-chain votes),
  `x/authz`, `x/feegrant`. We build custom modules for the rest (assets/DEX/
  dividends, loyalty, alias).
- **Sharding = Cosmos "zones" + IBC**: one zone per region; add zones to scale
  throughput ~linearly toward the 100k-TPS goal. Cross-shard = IBC transfers.
- Mature security, tooling, and a large hiring pool.

Trade-off to settle (see D3): Cosmos defaults to bech32 addresses + protobuf tx;
the live chain uses **base58 addresses + secp256k1 ECDSA**. The *key crypto
matches* (secp256k1), but address encoding/tx format differ → either preserve
base58 via a custom address codec (keeps existing addresses/wallets valid) or
adopt native formats and update wallets.

Alternative stacks considered (decide in D1): **Rust + Substrate** (max
performance, parachain sharding, steeper curve); **fully custom Go/Rust** (max
control, max effort/risk — not recommended).

## II.3 Decision sequence (work top-to-bottom; D1–D4 gate everything)

> Format per decision: **context → options → recommendation → STATUS**. The owner
> and Claude fill STATUS as we go; the new session treats DECIDED rows as binding.

### D1 — Core stack & framework  ⛳ keystone
Options: **(A) Go + Cosmos SDK + CometBFT** · (B) Rust + Substrate · (C) fully
custom (Go or Rust) · (D) other/discuss.
**Recommendation: A.** STATUS: **DECIDED (provisional) — Go + Cosmos SDK +
CometBFT.** Basis of all consensus/feature decisions below; owner may still veto.

### D1a — Consensus & participation model  ⛳ (DECIDED 2026-06-22)
**Consensus:** DPoS-style **rotating committee** — a small, tunable group (≈5–6)
of **staked masternodes** randomly selected to produce & finalize blocks with
**instant BFT finality**; the committee **re-rolls every epoch** (every N blocks,
N tunable — per-block rotation rejected as fragile). Selection randomness comes
from an unpredictable **VRF beacon**. Safe because validators are known, bonded
(250k) masternodes. **No PoW in the consensus path** (PoW is slow and extra
hashrate does not add throughput — it would undermine Goal #1). Open sub-params:
committee size, epoch length, exact VRF construction.

**"Participate by using" / scale-by-usage model (LOCKED — A+C+D+F):**
- **(F) Randomness from usage:** everyday user transactions feed entropy into the
  VRF beacon — users strengthen security simply by transacting.
- **(A) Merchant edge nodes:** each merchant's Steroid instance also runs a light
  edge/relay node behind the HAProxy mesh → every onboarded store adds ingress/
  read capacity. The real "scales with adoption" engine.
- **(C) Proof-of-usage loyalty:** users earn rewards for genuine activity (the
  BeepXtra loyalty engine) → adoption → fees → more masternodes/shards. Requires
  anti-Sybil (merchant stake + slashing, rate limits, counterparty diversity).
- **(D) Phone light clients:** consumer devices verify their own balances/txs
  trustlessly.
- **EXCLUDED:** consumer-phone compute, storage/CDN, and hashrate (unreliable,
  low payoff, needless complexity).
STATUS: **DECIDED.**

### D2 — Sharding strategy & timing
Options: (A) **single zone in v1, add region zones + IBC later** · (B) multi-shard
from day 1 · (C) custom shard layer.
**Recommendation: A** (ship one solid chain first; scale out once parity is proven).
STATUS: **DECIDED 2026-06-22 — (A)** single chain in v1; add region zones +
cross-shard (IBC) later as adoption demands.

### D3 — Address / key / wallet compatibility
Options: (A) **preserve base58 addresses via a custom address codec** (secp256k1
kept) so existing addresses, balances, and BeepWallet keep working · (B) adopt
native bech32 + migrate wallets · (C) discuss.
**Recommendation: A** (user-facing continuity; clean state migration). STATUS:
**DECIDED 2026-06-22 — (A)** preserve base58 addresses via a custom address codec
(secp256k1 keys unchanged). Existing addresses/balances/BeepWallet keep working;
enables clean state migration from S4QL.

### D4 — Economics: PoW → PoS mapping
Context: live chain has PoW emission, 0.3% fee, 250k masternode stake, masternode
+ cold-staking rewards, on-chain governance votes (PART I §4).
Options: (A) **map onto PoS**: masternodes = bonded validators (250k = min self-
bond), emission→staking rewards, keep 0.3% fee + governance via `x/gov`, cold
staking → delegation · (B) redesign tokenomics · (C) discuss.
**Recommendation: A.** STATUS: **DECIDED (in principle)** — PoS; masternodes =
bonded validators (250k = min self-bond); cold staking → delegation; emission →
staking/loyalty rewards; keep the 0.3% fee; governance via `x/gov` + existing
vote semantics. **No PoW.** Exact numbers to finalize.

### D5 — Feature-parity modules
STATUS: **DECIDED 2026-06-22.** Reuse built-in balances/staking/governance modules. Build custom
modules: **assets** (create/transfer/on-chain DEX/dividends/auto-dividends/
inflatable/fixed-price) and **alias**. **Loyalty stays OFF-chain** in the BeepXtra
app — the chain exposes only payments + token primitives (no on-chain loyalty
module). Proof-of-usage rewards are handled separately → D5a.

### D5a — Proof-of-usage rewards (on-chain)  ⛳ (to design)
A native on-chain mechanism that rewards users for **genuine** activity — the
scale-by-usage engine from D1a, and explicitly wanted by the owner. Distinct from
BeepXtra's off-chain loyalty program. To design: definition of "genuine usage"
(net economic activity, not self-transfers/wash loops); anti-Sybil via purely
on-chain economics (merchant stake + slashing, rate limits, counterparty
diversity, diminishing returns on repeated pairs); reward funding source
(emission allocation and/or fee redistribution) and its emission/inflation impact;
claim/redeem flow. STATUS: **PENDING DESIGN.**

### D6 — State & storage  ⛳ DECIDED 2026-06-22 (revised per owner)
**Decision:** use the **proven storage engine** (RocksDB + IAVL state tree) — **no
from-scratch engine**. Deliver Steroid's AnyData + smart-contract capabilities as
**modules + a deliberate data model on top**: smart contracts via **CosmWasm**
(D6a); **AnyData** via on-chain hash/commitment + content served from the merchant
edge layer (D6b). _(Owner adopted Claude's recommendation after reviewing the
trade-off: same features, far less risk than a custom engine.)_

### D6a — Smart contracts / DApps  ⛳ REQUIRED (core Steroid capability)
Add a smart-contract VM delivering first-class DApp support (a core Steroid
capability). Recommended:
**CosmWasm** (mature Wasm contracts for this stack); contract state lives in the
standard store. STATUS: **PENDING DESIGN** (recommend CosmWasm).

### D6b — "AnyData" on-chain data  ⛳ REQUIRED (core Steroid capability)
Store arbitrary data to deliver Steroid's "AnyData" capability, **designed to avoid chain
bloat**: small data inline on-chain; **large data = on-chain hash/commitment +
content held on the merchant edge-node layer** (D1a/D8). Define size limits,
fee-by-size, and retrieval/serving. STATUS: **PENDING DESIGN.**

### D7 — API & SDK compatibility
Options: (A) **REST gateway replicating the current endpoints** so BeepWallet /
outlets / MerchD / the PHP SDK keep working · (B) native gRPC/Cosmos API only +
update clients.
**Recommendation: A.** STATUS: **DECIDED 2026-06-22 — (A)** ship a compatibility
gateway mirroring today's REST endpoints (BeepWallet / outlets / MerchD / PHP SDK
keep working) and expose the new native API alongside for new development.

### D8 — Edge / self-managed HAProxy
Design: nodes self-register (on-chain or registry); a controller (owned by the
Steroid codebase) generates + reloads HAProxy config on join/leave; HAProxy
round-robins reads + tx ingress; writes are shard-routed by address/region.
STATUS: **DECIDED 2026-06-22 — self-managing edge.** Nodes/stores self-register; a
Steroid-owned controller auto-generates + reloads HAProxy config on join/leave;
reads + tx ingress load-balanced; payment writes shard-routed by region. Grows
and self-heals without manual config.

### D9 — MVP (v1) scope & phasing
**DECIDED 2026-06-22 — phased:**
- **v1:** transfers + fees, masternode validators (the D1a rotating committee),
  governance, the API/wallet **compatibility gateway** (D7), and **state migration
  from S4QL** (balances + base58 addresses carry over, D3/D10). A real, usable,
  migratable chain.
- **v2:** assets/tokens + on-chain DEX + dividends, **smart contracts (CosmWasm,
  D6a)**, **AnyData (D6b)**, **proof-of-usage rewards (D5a)**.
- **v3:** regional shards (IBC, D2) + the self-managing HAProxy edge (D8).

### D10 — Migration / cutover
**DECIDED 2026-06-22.** Snapshot S4QL state (accounts/balances, assets + asset
balances + market orders, masternodes, governance/votes, aliases) → new-core
**genesis**, **base58 addresses preserved** (D3). Validate against live, then cut
over; keep a read-only historical bridge for the explorer.
**Owner note:** the live chain is *lightly used right now*, so the parallel/shadow
period can be short — favour a **fast snapshot + cutover** over a long dual-run,
and do it **before usage grows**.

### D11 — Repo & project setup
**DECIDED 2026-06-22 — reuse & clean up the `lars/rebuild` branch** as the home of
the new Go/Cosmos core (PHP `master` stays the live chain). Branch cleaned to a
fresh starting point: PHP web/test cruft removed; `doc/` (apidoc) + `sdk/` kept as
**API-parity references** for D7; handoff doc + a new README added. The new session
sets up CI, linters, tests, and license.

## II.4 Decision log

**2026-06-22 — decisions (owner: angelos@exevior.com):**

1. **No PoW in the consensus path.** Speed is Goal #1; PoW is slow/probabilistic
   and extra hashrate does not raise throughput. (→ D1a)
2. **Consensus = DPoS rotating committee on BFT-PoS** — ≈5–6 staked masternodes,
   re-rolled per epoch via an unpredictable VRF, instant finality. (→ D1a)
3. **"Participate by using" model LOCKED** = merchant edge nodes + proof-of-usage
   loyalty + phone light-clients + randomness-from-usage. Consumer-phone compute/
   storage/hashrate **excluded**. This is the genuine scale-by-usage engine and
   ties directly to the BeepXtra loyalty product. (→ D1a)
4. **Stack (provisional): Go + Cosmos SDK + CometBFT** — custom module for the
   rotating-committee + VRF; reuse built-in balances/staking/governance modules; custom modules
   for assets/loyalty/alias. (→ D1; owner may veto.)
5. **Economics (in principle): PoS** — masternodes as bonded validators, cold
   staking → delegation, 0.3% fee kept, governance via `x/gov`. (→ D4)
6. **Keep existing base58 addresses** via a custom address codec (secp256k1 keys
   unchanged) — existing wallets/balances keep working; clean migration. (→ D3)
7. **Sharding: single chain first, regional shards later.** Ship one solid chain
   with full features; scale out to per-region zones (cross-shard via IBC) as
   adoption demands. (→ D2)
8. **Features:** reuse built-in balances/staking/governance; custom modules for
   assets/DEX/dividends and aliases. **Loyalty stays OFF-chain** (BeepXtra app);
   chain = payments + tokens only. (→ D5)
9. **Proof-of-usage rewards WANTED** as a separate on-chain mechanism — own design
   point. (→ D5a, pending design)
10. **Storage = proven engine** (RocksDB/IAVL) with AnyData + smart contracts as
    modules on top (owner adopted the low-risk recommendation). Smart contracts
    via **CosmWasm** (D6a); **AnyData** via on-chain hash + edge-node content to
    avoid bloat (D6b). (→ D6)
11. **API: keep current endpoints working** via a compatibility gateway (BeepWallet
    / outlets / MerchD / PHP SDK), native API alongside. (→ D7)
12. **Self-managing edge:** nodes/stores self-register; Steroid controller
    auto-generates/reloads HAProxy; reads+ingress load-balanced, writes
    shard-routed. (→ D8)
13. **Phased delivery:** v1 payments core + migration; v2 assets/DEX/dividends +
    contracts + AnyData + proof-of-usage; v3 regional shards + edge. (→ D9)
14. **Migration:** snapshot S4QL → genesis (addresses preserved), short validate,
    cut over. Chain is lightly used now → **fast cutover, do it before growth**. (→ D10)
15. **Repo:** reuse + clean up the `lars/rebuild` branch as the new core's home;
    PHP `master` stays the live chain. (→ D11)

**✅ DECISION SEQUENCE COMPLETE (D1–D11).** Remaining work is *design detail* for
the build session, not owner decisions:
- **D5a** proof-of-usage rewards — genuine-usage definition, anti-Sybil, funding.
- **D6a** smart-contract VM — recommend CosmWasm; integration + gas model.
- **D6b** AnyData — data model (inline vs hash+edge), size limits, fee-by-size.
- **D1a** sub-params — committee size, epoch length, exact VRF construction.
- **D4** exact economic numbers — emission curve, reward splits, min bond.

## II.5 First tasks for the new code session
1. Confirm Go + Cosmos SDK + CometBFT scaffolding on the cleaned `lars/rebuild`.
2. Build **v1** (D9): balances/transfers + fees, CometBFT over the **full bonded
   masternode set with VRF proposer rotation** (revised D1a — see II.6 #3), `x/gov`,
   the **API/wallet compatibility gateway** (D7, use `doc/` apidoc + `sdk/` as the
   contract), and the **S4QL → genesis migration tool** (D10) with base58 addresses
   preserved (D3).
3. Flesh out the design-detail items above as you reach them.
4. Then v2 (assets/DEX/dividends, CosmWasm, AnyData, proof-of-usage) and v3
   (load-aware sharding + self-managing edge — see II.6 #1/#2).

## II.6 Review concerns & resolutions (owner review, 2026-06-23)
These refine/supersede earlier entries; the build session treats II.6 as binding.

**#1 — Edge/HAProxy must NOT be driven by consensus nodes writing configs.**
Risk (valid): split-brain config drift, blast radius if a node is compromised,
reload churn under churn. **Resolution (revises §3b):**
- Separate **control plane** from consensus. Nodes never write proxy config.
- Membership = the chain's **signed, quorum-agreed validator set** (single source
  of truth) — no per-node pushes.
- Edge uses **dynamic upstreams** (HAProxy **Data Plane API** or Envoy **xDS**):
  add/drain servers at runtime, **no rewrite-and-reload**.
- Edges are **read-only** consumers of signed membership → compromised node can't
  poison routing. Updates **debounced/epoch-batched**.

**#2 — Sharding: no premature/geo sharding; no IBC at the till.**
Risk (valid): geo-shards hotspot on flash sales; cross-shard IBC is too slow for
checkout. **Resolution (revises D2/§3b):**
- v1/v2 = **one high-throughput chain** (parallel/optimistic execution, ABCI 2.0
  mempool lanes, edge read-replicas). Removes hotspots + cross-shard latency for
  the realistic horizon.
- Shard **only when a single chain is truly saturated**, by **account-space with
  load-aware rebalancing** (hot merchants split/migrate) — **not** geography.
- **Checkout never waits on cross-shard finality:** local = instant single-shard;
  cross-region = **instant merchant acceptance via escrow/receipt + async
  settlement** (or a fast coordinator path), never vanilla multi-block IBC.

**#3 — VRF committee liveness.**
Risk (valid): a small VRF committee can stall if it can't reach 2/3.
**Resolution (revises D1a):**
- **v1: CometBFT over the FULL bonded masternode set**; VRF used only for
  **proposer/leader rotation** → keeps the "rotating source of truth" without a
  fragile small voting set.
- If consensus is later sharded: committees **sized in the dozens+**, **per-epoch**
  rotation (not per-block), with **overlap + automatic fallback to the full set**
  if 2/3 isn't reached within N rounds. (5–6 is rejected as unsafe/non-live.)

**#4 — AnyData Data Availability + slashing (was missing).**
Risk (valid): hash-on-chain + single edge node = data lost if that node dies.
**Resolution (revises D6b):**
- Blobs **erasure-coded + replicated (k-of-n survive loss)**, not single-node.
- **Random proof-of-retrievability challenges → slash/withhold rewards** for nodes
  that can't serve (the missing economic vector).
- Or integrate an external **DA/permanence layer** (Celestia-style DA sampling, or
  IPFS+Filecoin/Arweave) for blobs needing permanence. Small/critical data inline.

**#5 — Proof-of-usage collusion / wash-trading.**
Risk (valid): merchant+user loop txs paying 0.3% fee to farm emission.
**Resolution (refines D5a) — principle: farming must cost more than it pays:**
- Reward **counterparty diversity**, not raw volume; **sharp diminishing returns**
  on repeated same-pair loops (loops → ~0).
- Merchants post a **permissionless on-chain stake/bond**; misbehaviour →
  **slash + automatic on-chain delisting**. Purely economic.
- Fund from a **bounded, merchant-funded loyalty pool**, not open-ended emission —
  so farming only drains a capped pot a merchant won't fund for self-dealing.
- **On-chain graph/anomaly heuristics** (loop/Sybil-ring) → automatic reward
  suppression. Purely on-chain economics — no off-chain authority of any kind.
- Design goal: rewards ≤ the economic cost of farming, so attacks don't pay.

