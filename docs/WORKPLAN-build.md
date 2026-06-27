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

## [DONE] — v1 Scaffolding (completed 2026-06-27, claude/galileo-scaffolding-w1w9sj → lars/rebuild)

### Files built

| File | Description |
|------|-------------|
| `go.mod` / `go.sum` | Module `github.com/beepxtra/steroid-core4.0`, Go 1.22, Cosmos SDK v0.50.10, CometBFT v0.38.12 |
| `app/params/encoding.go` | `EncodingConfig` struct (InterfaceRegistry, Codec, TxConfig, Amino) |
| `app/codec.go` | `MakeEncodingConfig()` — registers auth/bank/staking/gov/crypto interfaces; bech32 placeholder (D3: swap for base58) |
| `app/app.go` | Full `App` struct: BaseApp embed, keeper field stubs, store key allocation, params keeper, module manager skeleton, all `servertypes.Application` interface methods |
| `app/params/encoding.go` | EncodingConfig type |
| `cmd/stereodd/main.go` | Entry point — `svrcmd.Execute` |
| `cmd/stereodd/cmd/root.go` | Full CLI: `stereodd init`, `keys`, `debug`, `pruning`, `snapshot`, `server`, `genesis` sub-commands; bech32 prefix config |
| `Makefile` | `build`, `install`, `test`, `test-race`, `lint`, `lint-fix`, `mod-tidy`, `proto` (stub), `clean`, `help` |
| `.golangci.yml` | errcheck, govet, staticcheck, unused, gofmt, goimports, gocritic, misspell; SA1019 suppressed for deprecated SDK patterns |
| `.github/workflows/ci.yml` | CI on push/PR to `lars/rebuild` and `claude/**`: build → test → lint |
| `proto/README.md` | Placeholder — buf toolchain + custom module proto layout for D1a/D5 |
| `x/README.md` | Placeholder — x/assets, x/alias planned for v2; handoff rule |
| `README.md` | Running Locally section updated with actual build targets + honest status note |
| `.gitignore` | PHP dirs suppressed; `/stereodd`, `*.test` excluded |
| `SECURITY.md` | BFT-PoS intro paragraph added |

### What works now

- `go build ./...` — compiles clean, zero errors, zero warnings
- `go vet ./...` — clean
- `make build` — produces `./stereodd` binary
- `stereodd --help` — CLI tree renders correctly
- `stereodd init <moniker>` — initialises node home directory, writes `config.toml` / `app.toml` / genesis skeleton
- `stereodd keys` — keyring sub-commands work (add, list, show, delete, import, export)
- GitHub Actions CI — triggers on push to `lars/rebuild` and `claude/**` branches; runs build, test, golangci-lint

### What does NOT work yet

- `stereodd start` — cannot start a node. All keeper fields in `App` are zero-value stubs. `ModuleManager` is an empty skeleton with no modules registered. No routes, no ante-handler, no ABCI logic beyond the BaseApp default stubs.
- `stereodd genesis export` — requires `ModuleManager.ExportGenesisForModules` with real modules registered.
- Block production — blocked entirely pending keeper init and module wiring.
- VRF proposer rotation (D1a), base58 address codec (D3), emission curve (D4), x/assets + x/alias (D5), S4QL migration tool (D10) — all scaffolded as TODO markers, not implemented.

### Next task

**Keeper initialisation and module manager wiring** (owner: G4L1L3O / TheRealGofre per spec):

1. Initialise all v1 keepers in `app/app.go`: `AccountKeeper`, `BankKeeper`, `StakingKeeper`, `DistrKeeper`, `SlashingKeeper`, `MintKeeper`, `GovKeeper`, `CrisisKeeper` — with correct dependency order, authority addresses, and parameter wiring.
2. Register all v1 modules in `ModuleManager`: auth, bank, staking, gov, distribution, slashing, mint, params, crisis, genutil.
3. Set `BeginBlocker` / `EndBlocker` module order, `InitChainer` module order, `AnteHandler`.
4. Register codec for each module (`RegisterInterfaces`, `RegisterLegacyAminoCodec`).
5. Add `ModuleBasics` entries for all modules so `DefaultGenesis` and CLI genesis commands work.

After this task, `stereodd start` will be able to run a single-validator devnet.

---

## v1 Build — Go/Cosmos SDK core

**G4L1L3O**
- ~~Scaffold `lars/rebuild` branch: Go module init, Cosmos SDK + CometBFT wiring, CI/lint setup~~ ✅ DONE (see [DONE] section above)
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
