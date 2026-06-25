# Build Workplan — Roles & Phasing

## Participants
- **G4L1L3O** — https://github.com/angelexevior
- **TheRealGofre** (Nick) — https://github.com/TheRealGofre
- **LARS** — a custom-built AI agentic orchestrator built by G4L1L3O and trained to
  assist in this overhaul build.

---

## Phase 0 — Complete first-stage stabilization

**G4L1L3O**
- P0.2: Deploy `rekey_transactions.sql` via `pt-online-schema-change` on live S4QL — this is the highest-risk item (17M rows, live chain), needs G4L1L3O's oversight
- P0.3: Design the partition-by-height scheme + FK-CASCADE removal (architectural decision)
- P0.4: Design the summary tables for vote tallies / dividend aggregations

**TheRealGofre** (implements from spec)
- P0.3: Implement `PARTITION BY RANGE(height)` migration SQL + archival logic from G4L1L3O's design
- P0.4: Write the incremental-update triggers/hooks for summary tables
- P0.5: Set up MySQL read replica + query routing config (straightforward sysadmin + PHP routing layer)

**LARS**
- Monitor live chain health throughout P0.2 deploy (block height advancing, no lock stalls)
- Run diagnostics post-P0.2 (index sizes, p99 block-add time)

---

## v1 Build — Go/Cosmos SDK core

**G4L1L3O**
- Scaffold `lars/rebuild` branch: Go module init, Cosmos SDK + CometBFT wiring, CI/lint setup
- Custom address codec (base58 ECDSA — D3) — cryptographic precision required
- VRF proposer rotation (D1a) — non-trivial, consensus-critical
- S4QL → genesis migration tool (D10): balance reconciliation/audit proof — G4L1L3O knows the DB schema intimately
- Economic parameters: emission curve, reward splits, min bond (D4)

**TheRealGofre** (implements from spec, guided by G4L1L3O)
- `x/gov` module wiring + vote-semantics mapping from existing PHP logic
- REST compatibility gateway (D7): map `doc/` apidoc endpoints → new core handlers — this is grunt work, well-defined contract
- Genesis file validation tooling (balance-for-balance check)

**LARS**
- Spin up a sandbox Go environment on the VOYAGER and WAYFINDER masternodes for build/test
- Run migration dry-runs against a S4QL snapshot
- CI automation (build/test on push to `lars/rebuild`)

---

## v2 Build — Assets, CosmWasm, AnyData, PoU rewards

**G4L1L3O**
- Per-asset fee-pool mechanics design (D5) — economic design
- Proof-of-usage reward math: `r`, decay curves, same-pair diminishing returns (D5a)
- CosmWasm integration decision: gas model, contract-chain interface (D6a)
- AnyData: inline-vs-hash boundary, DA layer choice (D6b)

**TheRealGofre** (implements from spec)
- Assets module: permissionless token launch + transfer + optional params (D5) from G4L1L3O's spec
- Alias module (straightforward, low-risk)
- AnyData module implementation once D6b params are fixed by G4L1L3O

**LARS**
- Testnet node orchestration for v2 feature testing
- Automated regression: forging blocks, asset creation, alias resolution

---

## Key handoff rule

TheRealGofre should never start a module without a written spec from G4L1L3O first. The doc already flags §8 items as build-time decisions — those all land on G4L1L3O before TheRealGofre touches code. LARS executes and monitors, never decides.
