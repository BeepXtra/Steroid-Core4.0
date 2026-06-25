# Steroid 4.0 — Next-Generation Core

> A high-performance, **BFT proof-of-stake Layer-1** built for **planet-scale retail
> payments** — instant deterministic finality, permissionless tokens, smart
> contracts, and on-chain proof-of-usage rewards.

Steroid 4.0 is the purpose-built, horizontally-scalable core of the Steroid network —
the next stage of a roadmap planned since Steroid's **2018 inception**. The
first-stage chain proved the model in production (2M+ blocks, live masternodes,
assets, dividends, on-chain governance); Steroid 4.0 graduates that network to a
high-throughput engine designed for sub-second settlement from the first store to the
entire planet.

> ⚠️ **Project status — active rebuild.**
> This branch (`lars/rebuild`) is the home of the next-generation core, under active
> development. The first-stage chain (PHP/MySQL, database `S4QL`, node `galileo`)
> remains **in production on `master` until cutover — do not break it.**

---

## Why Steroid 4.0

Steroid was always a **staged roadmap**: ship a proven, rapidly-deployable
first-stage node to launch the network and validate the retail model, then — once
real adoption approached the first stage's designed limits — graduate to a
purpose-built high-performance core. That milestone has arrived. Steroid 4.0 is
engineered to remove the single-database ceiling of the first stage and deliver the
throughput, finality, and feature depth that retail at scale demands.

## Goals

1. **Speed and capacity are premium** — high throughput via parallel execution.
2. **Enterprise quality** — proven components, deterministic finality, auditable state.
3. **Planet scale** — from the first store to global volume.
4. **Retail payments are first-class.**
5. **Full feature parity** with the first stage — nothing is lost in migration.
6. **Scale on-chain first** — one high-throughput chain; shard only as a last resort.

## Key capabilities

- **Instant-finality consensus** — CometBFT **BFT proof-of-stake** over the **full
  bonded masternode set**; sub-second deterministic settlement. A **VRF beacon**
  drives proposer rotation, with entropy fed by everyday user transactions. No
  proof-of-work.
- **Retail payments** — native `total` / `available` / `locked` balance accounting:
  funds are validated, locked at payment, and settled on finality. The 0.3% fee model
  is carried forward.
- **Permissionless tokens / assets** — any wallet can launch a token: pay a creation
  fee, declare parameters and supply, and fund a **per-asset fee pool** so holders can
  transact without holding the base coin. Optional declarable params:
  manual/auto-dividends, dividend-only, inflatable supply, fixed-price.
- **Smart contracts / DApps** — first-class **CosmWasm** (WebAssembly) contracts.
- **AnyData** — on-chain data with an anti-bloat design: small data inline, large data
  committed on-chain by hash with content served from the merchant edge layer;
  durability via erasure-coding + proof-of-retrievability, or an external
  data-availability / permanence layer.
- **Proof-of-usage rewards** — merchant-funded, on-chain rewards for genuine activity;
  **unprofitable to farm by construction**, O(1) checks, no identity system.
- **On-chain governance** — masternode voting via `x/gov`, carrying forward existing
  vote semantics and chain parameters.
- **Human-readable aliases** — names mapped to addresses.
- **Light-client friendly** — phone clients verify their own balances and transactions
  trustlessly against the authenticated state tree.
- **Drop-in compatibility** — existing **base58 addresses** preserved, plus a **REST
  compatibility gateway** mirroring the current API, so existing wallets, merchant
  integrations, and the PHP SDK keep working through migration.

## Architecture at a glance

| Layer | Choice |
|-------|--------|
| Language / framework | **Go + Cosmos SDK** |
| Consensus | **CometBFT** BFT-PoS — validators = masternodes, instant finality |
| Leader selection | **VRF**-driven proposer rotation; entropy from user transactions |
| Execution | **Parallel / optimistic** on a single high-throughput chain |
| State & storage | **RocksDB + IAVL** authenticated state tree (light-client proofs) |
| Addresses | **base58** (secp256k1) via a custom codec — continuity with the first stage |
| Reused modules | balances/transfer, `x/staking`, `x/gov`, `x/authz`, `x/feegrant` |
| Custom modules | assets, alias, proof-of-usage rewards, AnyData |
| Smart contracts | **CosmWasm** |
| Scaling | one chain first; **account-space sharding** only as a last resort |
| Edge / routing | self-managing edge consuming the chain's **signed validator set** (nodes never write routing config) |

## Economic security & anti-abuse

Steroid 4.0 is **decentralized**, and all anti-abuse is **purely on-chain economics** —
no off-chain gatekeeping. Integrity comes from:

- **Staking / bonding** — masternodes are bonded validators (minimum self-bond);
  misbehaviour is **slashable**.
- **Cold staking → delegation** — holders delegate stake to validators.
- **Fees and bounded reward pools** — rewards (e.g. proof-of-usage) are funded by
  merchants from **bounded** pools, never by unbounded inflation.
- **Diminishing returns & counterparty diversity** — repeated self-dealing loops are
  net-negative by construction.

## Roadmap

- **v1 — Payments core:** transfers + fees, masternode validators (full set + VRF
  proposer rotation), governance, the REST compatibility gateway, and S4QL → genesis
  migration (base58 addresses preserved). A real, usable, migratable chain.
- **v2 — Feature depth:** permissionless assets/tokens (per-asset fee pool + optional
  params), CosmWasm smart contracts, AnyData, proof-of-usage rewards.
- **v3 — Scale insurance (only if needed):** account-space sharding + the dynamic
  self-managing edge.

## Migration & compatibility

State migrates by **snapshotting the first-stage chain into the new-core genesis**
with **base58 addresses preserved**, validated balance-for-balance before a fast
cutover. Existing external clients keep working via the REST compatibility gateway,
and full historical data remains available to explorers. See
[`docs/WORKPLAN-migration.md`](docs/WORKPLAN-migration.md).

## Repository layout

| Path | Purpose |
|------|---------|
| [`docs/FUTURE-ARCHITECTURE.md`](docs/FUTURE-ARCHITECTURE.md) | **Source of truth** — full architecture & decision log (D1–D11) |
| [`docs/WORKPLAN-build.md`](docs/WORKPLAN-build.md) | Build workplan — roles & phasing |
| [`docs/WORKPLAN-migration.md`](docs/WORKPLAN-migration.md) | Migration workplan — through cutover |
| `doc/` | First-stage API documentation (apidoc) — the compatibility-gateway contract |
| `sdk/` | First-stage PHP SDK — endpoint shapes / example client usage |

## Documentation

- **Architecture & decisions:** [`docs/FUTURE-ARCHITECTURE.md`](docs/FUTURE-ARCHITECTURE.md) — the authoritative spec.
- **Security policy:** [`SECURITY.md`](SECURITY.md).

## Development

- **`lars/rebuild`** — the next-generation Go/Cosmos core (this branch).
- **`master`** — the first-stage PHP/MySQL chain, **live in production until cutover**.

CI, linters, and tests are configured for the rebuild. Implementation follows the
build workplan; build-time parameters (economics, gas models, sizing) are finalized
during development per §8 of the architecture doc.

## License

To be decided by the dev community at the next dev meeting (decision D11). A `LICENSE`
file will be added then.
