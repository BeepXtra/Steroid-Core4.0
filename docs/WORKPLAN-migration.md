# Migration Workplan — S4QL → New-Core Cutover

## Participants
- **G4L1L3O** — https://github.com/angelexevior
- **TheRealGofre** (Nick) — https://github.com/TheRealGofre
- **LARS** — a custom-built AI agentic orchestrator built by G4L1L3O and trained to
  assist in this overhaul build.

> **Scope:** migration up to and including cutover — the moment the switch is flipped
> to the new core. Post-cutover operation is out of scope here.

---

## Approach (decided)

Bring the existing Steroid chain (~17M txs, ~2M blocks) into the new Go/Cosmos core
via a hybrid path. Two separable goals, handled by different mechanisms:

| Goal | Mechanism |
|------|-----------|
| Carry balances/assets/aliases to launch | Snapshot → genesis (D10) |
| Prove the snapshot is correct | Full-history replay in a test env; assert it reproduces the snapshot |
| Preserve history (17M txs / 2M blocks) for explorers | Archive on archive nodes; hash committed in genesis; served via D7/D10 bridge |

**Rejected:** replaying old blocks/txs into live consensus state — old blocks are not
valid new-core (CometBFT) blocks, users' private keys are not held, it bloats
validator state (against D6b/D2), and it recomputes already-known balances.

---

## Stage M1 — Migration tooling

**G4L1L3O**
- Build the S4QL → genesis migration tool (D10): snapshot balances, assets + asset
  balances, masternodes, governance/votes, aliases; base58 addresses preserved (D3) —
  G4L1L3O knows the DB schema intimately.
- Define the reconciliation/audit method (balance-for-balance proof, §8).

**TheRealGofre** (implements from spec)
- Genesis file validation tooling (balance-for-balance check) from G4L1L3O's spec.

**LARS**
- Run migration dry-runs against S4QL snapshots; report diffs and timings.

---

## Stage M2 — Validation via full-history replay

**G4L1L3O**
- Design the replay harness: re-run real historical txs (1k → 10k → 1M → all 17M)
  through the new core's transfer/fee/asset logic; assert the resulting state matches
  the known historical state (independent, first-principles proof of the snapshot).

**TheRealGofre** (implements from spec)
- Implement the replay/import pipeline from G4L1L3O's spec, with height-bounded,
  commented legacy rules in the import layer (e.g. `# origin Steroid data, block <
  2,000,000: legacy field Z ignored`). Critical fields (amounts, sources,
  destinations) recorded and retrievable; legacy crypto may be ignored and annotated
  as origin data; never in live consensus code.

**LARS**
- Orchestrate replay runs; report state-match diffs and p99 timings.

---

## Stage M3 — History archive + anchoring

**G4L1L3O**
- Decide the archive keyspace/store layout and the genesis commitment (hash/merkle
  root of the full historical dataset).

**TheRealGofre** (implements from spec)
- Build the historical archive (archive-node vs validator-node split) and wire it to
  the D10 read-only historical bridge + D7 gateway so explorers show one seamless
  ledger: pre-migration history stitched to post-migration activity.

**LARS**
- Stand up an archive/explorer node; verify the history stitches seamlessly.

---

## Stage M4 — Cutover (flip the switch)

**G4L1L3O**
- Final reconciliation/audit: confirm genesis matches live S4QL balance-for-balance.
- Authorize cutover.

**TheRealGofre** (implements from spec)
- Execute the cutover runbook: brief tx-freeze on the old chain → final snapshot →
  build and seal genesis → launch the new core.

**LARS**
- Monitor the freeze window and new-core launch (block height advancing, no stalls);
  confirm the switch.

— End of migration scope: the new core is live. —
