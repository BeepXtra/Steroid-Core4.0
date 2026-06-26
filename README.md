# Steroid Core 4.0

A retail payments and loyalty blockchain rebuilding its live production core in Go + Cosmos SDK.

> **Branch:** `lars/rebuild` — the new Go/Cosmos core, under active development.  
> **`master`** runs the original PHP/MySQL chain, live in production until cutover.

---

## The Problem

Retail payments on general-purpose blockchains are a bad fit. Card networks settle in 1–3 days
and extract 1.5–3% per transaction; merchants absorb chargebacks with no recourse. Most chains
that try to fix this either require customers to hold a gas token, lack native lock-then-settle
payment semantics, or are fast enough at launch but hit throughput ceilings as load grows.

Loyalty and rewards have the same mismatch. Merchant-funded reward programs require
protocol-level guarantees that farming is unprofitable — something you cannot bolt on with a
smart contract after the fact without significant complexity and attack surface.

Steroid was built for this from day one: native `total / available / locked` balance accounting,
a 0.3% flat fee, on-chain governance for chain parameters, and a proof-of-usage reward design
where every check is O(1) and farming is structurally net-negative.

---

## Production History

The first-stage chain (PHP/MySQL, `master`) has been live since 2018. It was designed
deliberately for fast, reliable launch and correctness — not maximum throughput. That ceiling
was always the planned trigger for this rebuild, foreseen from inception.

| Metric | Value |
|--------|-------|
| Chain height | 2M+ blocks |
| Transactions | 17M+ |
| Consensus | Argon2 PoW + round-robin masternode selection |
| Block time | ~60 seconds target |
| Average tx/block | ~8.4 |
| Active features | Masternodes, on-chain assets/DEX, dividends, aliases, cold staking, governance |

Third-party integrations in production: **BeepWallet**, **BeepXtra outlets**, **MerchD AI**.

The two chains run in parallel during migration (strangler pattern); the PHP chain stays live
until cutover.

---

## What's Being Built

The rebuild migrates the core to **Go + Cosmos SDK + CometBFT**, with the first-stage chain
kept healthy until a fast snapshot-and-cutover migration.

| Layer | Choice |
|-------|--------|
| Language | Go |
| Framework | Cosmos SDK |
| Consensus | CometBFT BFT-PoS — validators = masternodes, instant finality |
| Leader selection | VRF proposer rotation; entropy from user transactions |
| Execution | Parallel / optimistic on a single high-throughput chain |
| State | RocksDB + IAVL authenticated state tree (light-client proofs) |
| Addresses | base58 (secp256k1), preserved via custom codec |

**Cosmos SDK modules reused:** balances/transfer, `x/staking` (validator bonding ↔ 250k BPC
masternode stake), `x/gov` (on-chain votes), `x/authz`, `x/feegrant`.

**Custom modules:** assets (permissionless token launch), alias (human-readable names),
proof-of-usage rewards, AnyData.

---

## Key Technical Differentiators

### BFT Finality

CometBFT consensus over the full bonded masternode set gives **sub-second deterministic
finality**. A payment is final when the block is committed — no probabilistic waiting, no
reorg risk. Retail requires this; PoW chains do not provide it.

### Parallel / Optimistic Execution

Transactions touching disjoint account pairs run concurrently within a block. Single chain;
no sharding required at v1/v2 throughput targets. Account-space sharding exists as a v3
option if a single chain saturates, but the design goal is to avoid needing it.

### Native Payment Semantics

Balance state is `total / available / locked`. On payment: validate `available`, atomically
move the amount to `locked`, settle on block finality. No external coordination, no
double-spend window between acceptance and settlement.

### Proof-of-Usage Rewards

On-chain rewards funded by merchants, not by protocol inflation:

- **Merchant-funded pools.** Each merchant locks two on-chain pots: a slashable bond and a
  bounded reward pool. The pool is the only source of customer rewards — the protocol emits
  nothing.
- **Reward = `r × fee_paid`, `r < 1`, always.** Any self-dealing loop is net-negative per
  iteration. Farming cannot be made profitable by construction.
- **Same-pair diminishing returns.** A per-`(payer, payee)` counter decays `r` toward ~0 for
  repeated loops over the same pair.
- **O(1) checks.** Single state lookup per reward evaluation. No identity system, no
  Sybil-ring detection.
- **Misbehaviour** → slash + automatic on-chain delisting.

### VRF Proposer Rotation

A VRF beacon drives block proposer selection. Entropy is fed by ordinary user transactions —
the chain's own activity drives randomness without a separate oracle.

### Base58 Address Preservation

Existing addresses carry over directly into the new chain's genesis. External wallets,
merchant integrations, and the PHP SDK keep working through and after migration via a REST
compatibility gateway mirroring the current API endpoints.

### Light-Client Verification

Phone clients verify their own balances and transactions trustlessly against the IAVL
authenticated state tree — no trusted intermediary required.

### Participate by Using

The network grows by being used, not by running dedicated infrastructure:

- Each merchant's Steroid instance also runs a light edge/relay node — every store adds
  ingress and read capacity.
- User transactions feed entropy into the VRF beacon.
- Consumer phones are light clients that verify their own state trustlessly.

---

## Economic Security

All anti-abuse is purely on-chain economics — no off-chain gatekeeping:

- **Staking / slashing.** Masternodes are bonded validators; double-signing and liveness
  faults are slashable.
- **Cold staking → delegation.** Holders delegate stake to validators without running a node.
- **Bounded reward pools.** Proof-of-usage rewards come from merchant-funded pools, never
  from unbounded inflation. Pool exhaustion ends rewards for that merchant.
- **Diminishing returns.** Repeated self-dealing loops are net-negative by construction.

---

## Validator / Masternode Participation

Masternodes from the first-stage chain become **bonded validators** in the new core.

| Parameter | Value |
|-----------|-------|
| Minimum self-bond | 250,000 BPC |
| Consensus role | CometBFT BFT-PoS validator |
| Rewards | Block rewards + tx fees (emission curve finalized during v1 build) |
| Slashing | Double-signing and liveness faults |
| Cold staking | Supported — delegators stake to validators without running a node |

The validator set participates in on-chain governance via `x/gov`. Existing vote semantics
(emission parameters, masternode reward splits, governance thresholds) carry forward.

**To run a validator node:** join instructions and testnet genesis will be published here when
v1 scaffolding is live. Watch this branch or the community channel (link TBD) for announcements.

---

## Roadmap

### v1 — Payments Core

- Transfers + 0.3% fee
- CometBFT BFT-PoS over the full bonded masternode set with VRF proposer rotation
- `x/gov` — on-chain governance with existing vote semantics
- REST compatibility gateway — existing wallets and integrations keep working unchanged
- S4QL → genesis migration tool: base58 addresses preserved; balance-for-balance audit proof
  validated against the live chain before a brief tx-freeze and fast cutover; historical bridge
  kept for the explorer

**Deliverable:** a real, usable, migratable chain. First-stage validators upgrade directly.

### v2 — Feature Depth

- Permissionless assets / tokens: creation fee, per-asset fee pool (token holders transact
  without holding BPC), optional params (manual/auto-dividends, dividend-only, inflatable
  supply, fixed-price)
- CosmWasm smart contracts
- AnyData: small data inline on-chain; large data committed by hash with content held on the
  merchant edge layer — erasure-coded k-of-n replication, random proof-of-retrievability
  challenges, slash/reward-withhold for nodes that can't serve
- Proof-of-usage rewards

### v3 — Scale Insurance (only if needed)

Account-space sharding with load-aware rebalancing, and a dynamic self-managing edge routing
layer. Triggered only if a single chain saturates.

---

## Running Locally

> **TODO** — Go scaffolding is not yet committed to this branch. This section will be
> completed when the module, Cosmos SDK wiring, and CI are in place.

Expected prerequisites when available:

```sh
go 1.22+
make build
make test
```

Migration tooling for testing against a local S4QL snapshot will be documented in
[`docs/WORKPLAN-migration.md`](docs/WORKPLAN-migration.md).

---

## Repository Layout

| Path | Contents |
|------|----------|
| [`docs/FUTURE-ARCHITECTURE.md`](docs/FUTURE-ARCHITECTURE.md) | Full architecture spec and decision log (D1–D11) — start here |
| [`docs/WORKPLAN-build.md`](docs/WORKPLAN-build.md) | Build phases and role assignments |
| [`docs/WORKPLAN-migration.md`](docs/WORKPLAN-migration.md) | Migration plan through cutover |
| `doc/` | First-stage API documentation (apidoc) — REST compatibility gateway contract |
| `sdk/` | First-stage PHP SDK — endpoint shapes and example client usage |

---

## Links

- Full architecture spec: [`docs/FUTURE-ARCHITECTURE.md`](docs/FUTURE-ARCHITECTURE.md)
- Security policy: [`SECURITY.md`](SECURITY.md)
- Block explorer: [explorer.steroid.io](https://explorer.steroid.io)
- Website: [steroid.io](https://www.steroid.io)
- Community / Discord: TBD
- Security disclosures: [devteam@steroid.io](mailto:devteam@steroid.io)

---

## License

To be decided by the dev community. A `LICENSE` file will be added when the decision is made.
