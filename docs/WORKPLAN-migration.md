# Migration & History Import — Workplan

## Participants
- **G4L1L3O** — https://github.com/angelexevior
- **TheRealGofre** (Nick) — https://github.com/TheRealGofre
- **LARS** — a custom-built AI agentic orchestrator built by G4L1L3O and trained to
  assist in this overhaul build.

---

## Context

Decision on how to bring the existing Steroid chain (~17M transactions, ~2M blocks)
into the new Go/Cosmos core. Two separable goals were identified and must not be
conflated:

1. **End state** — current balances, assets, aliases (what cutover must carry).
2. **History** — the full 17M txs / 2M blocks, for explorers and the permanent record.

## Decision: hybrid approach

Use snapshot for state, replay for validation and testing, and an anchored archive
for history. Do **not** replay full history into live consensus state.

| Goal | Mechanism |
|------|-----------|
| Carry balances/assets/aliases to launch | Snapshot → genesis (D10) |
| Prove the snapshot is correct | Full-history replay in a test environment; assert it reproduces the snapshot |
| Preserve 17M txs / 2M blocks for explorers | Historical archive on archive nodes; hash committed in genesis; served via D7/D10 bridge |
| Strong test environment | Same replay harness; soak on real data, then let the chain continue live |

## Workstreams

### 1. Production cutover — snapshot → genesis
- Snapshot S4QL state (balances, assets + asset balances, masternodes,
  governance/votes, aliases) into new-core genesis; base58 addresses preserved
  (D3/D10).
- Fast snapshot + cutover while usage is low.

### 2. Replay as validation / reconciliation harness
- Approach proposed by G4L1L3O: replay real historical transactions
  (1k → 10k → 1M → all 17M) through the new chain's transfer/fee/asset logic.
- Assert resulting state matches the known historical state — independent,
  first-principles validation of the snapshot (the D10 §8 reconciliation/audit step).
- Doubles as the primary testing environment: replay N txs/blocks, then confirm the
  chain continues on its own; bug and edge-case testing on years of real activity.

### 3. History preservation for explorers
- Keep the full 17M txs / 2M blocks as an immutable historical archive in its own
  keyspace/store.
- Carry the archive on archive/explorer nodes, not on lean validators (archive-node
  vs validator-node split) — keeps validator state lean and protects D2 throughput
  and the D6b anti-bloat design.
- Commit a hash/merkle root of the full historical dataset into genesis →
  tamper-evident, cryptographically anchored, zero live-state bloat.
- Serve via the D10 read-only historical bridge + D7 gateway so explorers show one
  seamless ledger: pre-migration history stitched to post-migration activity.

### 4. Import-layer legacy handling
- Old data will contain deprecated crypto and legacy quirks not supported by the new
  chain.
- Handle with explicit, height-bounded, commented rules in the import/archive layer,
  e.g. `# origin Steroid data, block < 2,000,000: legacy field Z ignored`.
- Critical fields (amounts, sources, destinations) must be recorded and retrievable;
  unsupported/legacy cryptographic details may be ignored and annotated as origin
  data.
- Keep all such special-cases in the import/archive layer — never in live consensus
  code.

## Rejected: replay into live consensus state

Replaying old blocks/txs directly into the running chain's state was considered and
rejected:
- Old blocks are not valid new-core (CometBFT) blocks — different structure, hashing,
  signatures, consensus; the new chain produces its own blocks from height 1. This is
  data import, not syncing.
- Users' private keys are not held, so replayed "txs" would be authority-only imports,
  not genuine signed transactions — archival records, not txs.
- Forcing 17M txs / 2M blocks into the active IAVL state tree bloats every validator
  forever, slows node sync, and contradicts the D6b/AnyData anti-bloat design and D2
  throughput goals.
- Replay recomputes already-known balances, adding bug surface for no gain.
