# x/ — Custom Cosmos SDK Modules

Custom modules for the Steroid chain will live here, one directory per module.

## v2 modules (planned)

| Module | Architecture ref | Status |
|--------|-----------------|--------|
| `x/assets` | D5 — permissionless token launch + per-asset fee pool | Pending G4L1L3O spec |
| `x/alias` | D5 — human-readable account names | Pending G4L1L3O spec |

Proof-of-usage rewards (D5a) and AnyData (D6b) are also custom modules; their
exact packaging (standalone module vs extension of x/assets) is a build-time
decision.

## Handoff rule

No module starts without a written spec from G4L1L3O first.
See `docs/WORKPLAN-build.md`.
