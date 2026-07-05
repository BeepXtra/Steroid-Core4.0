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
  scales vertically only. **High-throughput parallel execution is the next-gen
  design** (PART II).

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
6. **Scale on-chain first:** a single high-throughput chain (parallel execution +
   instant finality); shard only as a last resort (§6, D2).

## 4. Architecture

A **BFT proof-of-stake** chain where today's **masternodes are the validator set**,
giving **instant (sub-second) deterministic finality** — what retail needs, not
probabilistic PoW. Throughput comes from **parallel/optimistic execution on a
single high-throughput chain**, not from geographic sharding.

**Stack: Go + Cosmos SDK + CometBFT.**
- **CometBFT (Tendermint) BFT-PoS** → instant finality; validators = masternodes.
- **Cosmos SDK modules** reused: a balances/transfer module, `x/staking` (validator
  bonding ↔ the 250k masternode stake), `x/gov` (↔ on-chain votes), `x/authz`,
  `x/feegrant`. Custom modules for the rest (assets, alias).
- **Storage:** RocksDB + IAVL authenticated state tree → light-client proofs.

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
**Consensus:** **CometBFT BFT-PoS over the FULL bonded masternode set** → instant
finality. **No PoW.** A **VRF beacon** drives **proposer/leader rotation**;
everyday user transactions feed entropy into the beacon.

**"Participate by using" model:**
- **Merchant edge nodes:** each merchant's Steroid instance also runs a light
  edge/relay node → every store adds ingress/read capacity.
- **Proof-of-usage rewards** (→ D5a).
- **Phone light clients:** consumer devices verify their own balances/txs trustlessly.
- **Randomness from usage:** user transactions feed the VRF beacon.
- **Excluded:** consumer-phone compute, storage/CDN, hashrate.

**D1a implementation spec (resolved):**
- **VRF algorithm:** ECVRF-EDWARDS25519-SHA512-TAI (RFC 9381), via
  `github.com/ProtonMail/go-ecvrf` — verified against the RFC's own Appendix
  A.3 test vectors before adoption (two other ed25519 VRF libraries were
  evaluated and rejected: one implements the wrong curve entirely, the other
  predates the finalized RFC with no compliance test vectors of its own).
- **VRF key:** a separate keypair per validator, registered on-chain via
  `MsgRegisterVRFKey` (`x/vrf` module); a second registration from the same
  validator rotates the key. A validator with no registered key is skipped
  from proposer selection.
- **Seed construction:** `SHA256(prev_vrf_output || block_height ||
  tx_accumulator_hash)`, where `tx_accumulator_hash` is a running SHA-256 over
  the previous block's tx hashes in order; the empty-block case
  (`SHA256([]byte{})`) falls out of the running hash's zero-iteration base
  case rather than needing a special branch.
- **Winner selection:** direct index pick — the seed alone deterministically
  selects one winner index into the validator set; the VRF proof establishes
  that validator's identity/eligibility rather than "winning" a comparison
  against other candidates' outputs.
- **Round-latency bound — revised from "next-K round-robin candidates" to a
  time-based fallback window.** CometBFT v0.38's ABCI gives the app no
  visibility into which round it is currently validating
  (`RequestProcessProposal`/`RequestPrepareProposal` carry no `Round` field),
  and Cosmos SDK's `baseapp` resets all app-side state on *every*
  `ProcessProposal`/`PrepareProposal` call — so a cross-round rejection
  counter is not implementable without introducing per-validator
  non-determinism (different validators' local timeouts would produce
  different counts, which is how you fork a BFT chain). Implemented instead:
  **`ShouldAcceptFallback(proposalTime, lastAcceptedTime, window)`** —
  accept a non-winning proposer once `proposalTime` (part of the specific
  proposal being validated, agreed via the BFT process itself) is more than
  `window` (`vrfkeeper.DefaultFallbackWindow`, a build-time parameter, 30s
  placeholder) past `lastAcceptedTime` (previously committed chain state).
  Both inputs are agreed-upon, not locally observed, so every honest
  validator evaluating the same proposal computes the same accept/reject
  decision. This also closes the liveness hole in Decision 4's literal text
  (reject every non-winner, forever, if the true winner never proposes) —
  without a fallback, an offline/byzantine winning validator would halt the
  chain at that height with no recovery path.
- **Status: implemented end-to-end and wired into consensus.** `x/vrf`
  module (key registration, keeper, genesis), seed computation, winner
  selection, VRF prove/verify, the fallback-window function, `Candidates`
  (bonded validators × registered VRF keys, via `x/staking`),
  `EvaluateProposal` (the full accept/reject/fallback decision), and the
  `PrepareProposal`/`ProcessProposal`/`PreBlocker` handlers are all
  implemented, unit-tested (including the six core accept/reject/fallback
  scenarios), and verified on a live single-validator devnet. A VRF proof is
  carried as a magic-prefixed pseudo-tx prepended to the block rather than
  via ABCI++ vote extensions (the more idiomatic mechanism, but a
  materially bigger lift needing `ExtendVoteHandler`/
  `VerifyVoteExtensionHandler` across two heights) — the cost is one benign,
  always-failing tx-decode entry per block in the ABCI response, not a
  correctness or determinism issue, since every validator sees identical
  bytes. Flagged as a clean follow-up refinement, not a blocker.

### D2 — Throughput & scaling strategy
**One high-throughput chain (v1/v2).**
- **Parallel/optimistic execution** (transactions touching disjoint accounts run
  concurrently), **instant BFT finality**, ABCI 2.0 mempool lanes, edge
  read-replicas. One global state.
- **Native `total` / `available` / `locked` balance accounting:** on payment,
  validate `available`, atomically move the amount to `locked` until finality, then
  settle and update both parties.

**Sharding — last resort only**, when a single chain is truly saturated, and then
by **account-space with load-aware rebalancing** (hot merchants split/migrate) —
**not geography, not IBC at the till**. Checkout never waits on cross-shard
finality: local = instant single-shard; cross-region = **instant merchant
acceptance via escrow/receipt + async settlement**.

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
  toward ~0 for repeated loops over the same pair.
- **Counterparty diversity** weighting via a bounded per-account decaying tally.
- **Misbehaviour → slash + automatic on-chain delisting.**

### D6 — State & storage
Use the **proven storage engine** (RocksDB + IAVL state tree) — no from-scratch
engine. Deliver Steroid's AnyData + smart-contract capabilities as **modules + a
deliberate data model on top** (D6a/D6b).

### D6a — Smart contracts / DApps
First-class DApp support via **CosmWasm** (mature Wasm contracts for this stack);
contract state lives in the standard store. Gas/integration model finalized at build (§8).

### D6b — "AnyData" on-chain data
**Designed to avoid chain bloat:** small data inline on-chain; **large data =
on-chain hash/commitment + content held on the merchant edge-node layer** (D1a/D8).
For durability, blobs are **erasure-coded + replicated (k-of-n survive loss)** with
**random proof-of-retrievability challenges → slash/withhold rewards** for nodes
that can't serve; or integrate an external **DA/permanence layer** (Celestia-style
DA sampling, or IPFS+Filecoin/Arweave) for blobs needing permanence. Size limits +
fee-by-size finalized at build (§8).

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
- **v1:** transfers + fees, masternode validators (D1a full set + VRF proposer
  rotation), governance, the API/wallet **compatibility gateway** (D7), and **state
  migration from S4QL** (balances + base58 addresses carry over, D3/D10). A real,
  usable, migratable chain.
- **v2:** assets/tokens (permissionless launch + per-asset fee pool; optional
  dividends/inflatable/fixed-price), **smart contracts (CosmWasm,
  D6a)**, **AnyData (D6b)**, **proof-of-usage rewards (D5a)**.
- **v3 (only if needed):** **account-space sharding** (last resort, D2) + the
  **dynamic self-managing edge** (D8).

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

1. Confirm Go + Cosmos SDK + CometBFT scaffolding on the cleaned `lars/rebuild`.
2. Build **v1** (D9): balances/transfers + fees; CometBFT over the **full bonded
   masternode set with VRF proposer rotation** (D1a); `x/gov`; the **API/wallet
   compatibility gateway** (D7, using `doc/` apidoc + `sdk/` as the contract); and
   the **S4QL → genesis migration tool** (D10) with base58 addresses preserved (D3).
3. Build **v2**: assets (permissionless launch + per-asset fee pool; optional
   dividends/inflatable/fixed-price), CosmWasm (D6a), AnyData (D6b),
   proof-of-usage rewards (D5a).
4. **v3 only if a single chain saturates:** account-space sharding + dynamic edge.

## 8. Design detail to finalize during build

These are build-time parameters, not open architecture questions:
- **D4** — emission curve, reward splits, minimum bond.
- **D5** — asset creation fee; per-asset fee-pool mechanics (funding, draw-down,
  top-up, exhaustion behaviour); optional-param definitions.
- **D5a** — exact `r`, decay curves, pool sizing/refill, diversity-tally shape.
- **D6a** — CosmWasm gas model + integration.
- **D6b** — inline-vs-hash size limits, fee-by-size, DA parameters.
- **D1a** — exact VRF construction; (if v3 sharding is ever triggered) per-shard
  committee sizing in the dozens+, per-epoch rotation, overlap + automatic fallback
  to the full set if 2/3 isn't reached within N rounds.
- **D10** — state reconciliation/audit step (balance-for-balance proof that the
  new-core genesis matches live S4QL state before cutover); brief tx-freeze window
  on the old chain to take a clean point-in-time snapshot.
