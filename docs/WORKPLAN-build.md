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

## [DONE] — D1a VRF key registration + seed function (proposer enforcement not yet built)

Implements the registration/seed half of D1a per the resolved implementation
spec (see `docs/FUTURE-ARCHITECTURE.md` §6 D1a): the on-chain VRF key registry
and the standalone seed-computation function. Proposer-side proof generation
and `ProcessProposal` enforcement are a separate, later piece.

### Files built / modified

| File | Change |
|------|--------|
| `proto/steroid/vrf/v1/*.proto` | `ValidatorVRFKey` state, `MsgRegisterVRFKey` tx, genesis, query definitions |
| `proto/{cosmos_proto,cosmos/msg/v1,gogoproto,google/api}/*.proto` | Vendored third-party proto deps (no BSR/network dependency for codegen) |
| `proto/buf.yaml` / `proto/buf.gen.yaml` | buf + `protoc-gen-gocosmos` config, mapping `google/api/*` to the existing `gogo/googleapis` Go package |
| `scripts/protocgen.sh` | Reproducible codegen: installs buf/gocosmos if missing, runs `buf generate`, relocates output |
| `Makefile` | `proto` target now runs `scripts/protocgen.sh` (was a stub) |
| `x/vrf/types/` | Generated pb.go + hand-written `keys.go`, `errors.go`, `msgs.go` (`ValidateBasic`), `genesis.go` (`DefaultGenesis`/`Validate`), `codec.go` |
| `x/vrf/keeper/` | `Keeper` (collections-based validator-address → VRF-key map), `msg_server.go`, `grpc_query.go`, `genesis.go` |
| `x/vrf/module.go` | `AppModuleBasic`/`AppModule` wiring (genesis, services, consensus version) |
| `x/vrf/seed/seed.go` | Standalone `ComputeSeed`/`ComputeTxAccumulator` implementing Decision 3 exactly (empty-block case is the running hash's zero-iteration base case, not a special branch) |
| `app/app.go` | `x/vrf` store key, keeper construction, module manager registration, init-genesis ordering |
| `go.mod` | Added `github.com/ProtonMail/go-ecvrf` (ECVRF-EDWARDS25519-SHA512-TAI, RFC 9381) |

### Library selection note

Two other ed25519 VRF libraries were evaluated and rejected before landing on
`ProtonMail/go-ecvrf`: `vechain/go-ecvrf` only implements secp256k1/P256 (wrong
curve entirely), and `yoseplee/vrf` predates the finalized RFC 9381 with no
compliance test vectors of its own. `ProtonMail/go-ecvrf` was verified against
the RFC's own Appendix A.3 test vectors (key generation, hash-to-curve, nonce
generation, prove, verify) before adoption.

Also found during implementation: `ecvrf.NewPublicKey` never validates its
input — it stores raw bytes unconditionally. `ValidateBasic`/genesis
`Validate` do real curve-point validation via `filippo.io/edwards25519`
instead, so a malformed key is rejected at registration time rather than only
failing later inside proposer verification.

### What works now

- `go build ./...`, `go vet ./...` — clean
- `go test ./x/vrf/...` — all unit tests pass (keeper CRUD + rotation + genesis round-trip, `ValidateBasic`/genesis `Validate` edge cases, seed function known-answer + order-sensitivity + replay-prevention tests against independently-computed SHA-256 fixtures)
- `make build` + `scripts/devnet-setup.sh` + `stereodd start` — node still starts and produces blocks with `x/vrf` wired into the module manager (blocks 1–4 finalized, all crisis invariants passing)

### Known limitations / not yet done

- Proposer-side VRF proof generation — not started; needs a VRF privkey file alongside `priv_validator_key.json`
- `ProcessProposal` verification enforcing the direct-index winner — not started
- No cooldown on key rotation (spec flagged this as a "consider," not a requirement — a second `MsgRegisterVRFKey` overwrites immediately)
- REST gateway routes for `x/vrf` queries — not registered (gRPC/CLI only for now)

### Next tasks (priority order)

1. **D1a — proposer VRF proof generation + `ProcessProposal` enforcement**: the direct-index winner function (bounded to next-K round-robin candidates) and the actual consensus-layer wiring. Consensus-critical — needs careful review before merge.
2. **D4 — Emission curve**: Replace default `x/mint` params with the Steroid emission schedule. G4L1L3O to specify.
3. **D10 — S4QL migration tool**: Blocking for M1 cutover.
4. **D5 — x/assets + x/alias**: Spec from G4L1L3O first.
5. **D7 — REST gateway**: Map `doc/` apidoc endpoints → new core handlers.

---

## [DONE] — v1 Keeper Init + D3 Base58 Codec + Devnet (completed 2026-06-27, claude/keeper-init-wiring → lars/rebuild)

### Files built / modified

| File | Change |
|------|--------|
| `app/address/codec.go` | D3: `DeriveAddress` (SHA512×9 over PKIX DER, manual secp256k1 DER construction — Go's x509 doesn't support secp256k1); `StringToBytes` bech32 fallback for SDK-internal compat; `Base58Encode` / `Base58Decode` / `BytesToString` |
| `app/codec.go` | `NewInterfaceRegistryWithOptions` with `proto.HybridResolver` + `steroidaddress.Codec{}` wired into signing context (avoids `failingAddressCodec{}` panic during gentx) |
| `app/app.go` | All v1 keepers fully initialised (AccountKeeper, BankKeeper, StakingKeeper, DistrKeeper, SlashingKeeper, MintKeeper, GovKeeper, CrisisKeeper, ConsensusParamKeeper); full ModuleManager with all v1 modules; AnteHandler; `moduleAuthority()` helper for base58-encoded module authority addresses; `SetInitChainer` / `SetBeginBlocker` / `SetEndBlocker` ABCI wiring; `bApp.SetParamStore` via x/consensus |
| `cmd/stereodd/cmd/root.go` | `gentx` and `collect-gentxs` commands added with base58 codec; `txConfig` threaded through `initRootCmd` |
| `scripts/devnet-setup.sh` | Full single-validator devnet bootstrap: init → denom substitution (stake→ubpc) → key creation → base58 address derivation (Python SHA256+RIPEMD160) → add-genesis-account → gentx → collect-gentxs |

### What works now

- `go build ./...` — compiles clean
- `go vet ./...` — clean
- `go test ./app/address/...` — all three tests pass (`TestBase58RoundTrip`, `TestBase58Vectors`, `TestDeriveAddress`)
- `make build` — produces `./build/stereodd` binary
- `stereodd init / keys / gentx / collect-gentxs / add-genesis-account` — all CLI commands work
- **`stereodd start` — node starts, produces blocks** (verified: blocks 1–4 finalized, all 11 crisis invariants passing each block)
- `scripts/devnet-setup.sh` — bootstraps a working single-validator devnet in one command
- D3 `DeriveAddress` — correctly computes `base58(SHA512^9(PKIX_DER))` reproducing first-stage address derivation

### Known limitations / not yet done

- `keys show` displays `cosmos1...` bech32 addresses — SDK v0.50 `client.Context` doesn't expose `WithAddressCodec`; address output is cosmetic only, chain state uses base58 internally
- `stereodd genesis export` — untested (should work; all modules registered with `ExportGenesis`)
- VRF proposer rotation (D1a) — key registration + seed function done (see the D1a entry above); proposer-side proof generation and `ProcessProposal` enforcement not yet built, standard CometBFT round-robin still in use for now
- Emission curve (D4) — not started; default SDK mint params in use
- x/assets + x/alias (D5) — not started; v2 scope
- S4QL → genesis migration tool (D10) — not started
- REST compatibility gateway (D7) — not started

### Next tasks (priority order)

1. **D4 — Emission curve**: Replace default `x/mint` params with the Steroid emission schedule (see doc §4). G4L1L3O to specify the exact curve/schedule; TheRealGofre implements.
2. **D1a — VRF proposer rotation**: Swap CometBFT's round-robin proposer selection for deterministic VRF-based rotation. Requires consensus-layer change — G4L1L3O must design the VRF seed/epoch scheme before TheRealGofre implements.
3. **D10 — S4QL migration tool**: Snapshot balances, assets, masternodes, governance/votes, aliases from live S4QL → genesis JSON. G4L1L3O owns DB schema knowledge. Blocking for M1 cutover.
4. **D5 — x/assets + x/alias**: Custom modules for permissionless token launch + alias resolution. Spec from G4L1L3O first.
5. **D7 — REST gateway**: Map `doc/` apidoc endpoints → new core handlers (TheRealGofre implements from existing apidoc spec).

---

## [DONE] — v1 Scaffolding (completed 2026-06-27, claude/galileo-scaffolding-w1w9sj → lars/rebuild)

### Files built

| File | Description |
|------|-------------|
| `go.mod` / `go.sum` | Module `github.com/beepxtra/steroid-core4.0`, Go 1.22, Cosmos SDK v0.50.10, CometBFT v0.38.12 |
| `app/params/encoding.go` | `EncodingConfig` struct (InterfaceRegistry, Codec, TxConfig, Amino) |
| `app/codec.go` | `MakeEncodingConfig()` — registers auth/bank/staking/gov/crypto interfaces |
| `app/app.go` | `App` struct skeleton: BaseApp embed, keeper field stubs, store key allocation, params keeper, module manager skeleton, all `servertypes.Application` interface methods |
| `cmd/stereodd/main.go` | Entry point — `svrcmd.Execute` |
| `cmd/stereodd/cmd/root.go` | Full CLI: `stereodd init`, `keys`, `debug`, `pruning`, `snapshot`, `server`, `genesis` sub-commands |
| `Makefile` | `build`, `install`, `test`, `test-race`, `lint`, `lint-fix`, `mod-tidy`, `proto` (stub), `clean`, `help` |
| `.golangci.yml` | errcheck, govet, staticcheck, unused, gofmt, goimports, gocritic, misspell; SA1019 suppressed |
| `.github/workflows/ci.yml` | CI on push/PR to `lars/rebuild` and `claude/**`: build → test → lint |
| `proto/README.md` | Placeholder — buf toolchain + custom module proto layout for D1a/D5 |
| `x/README.md` | Placeholder — x/assets, x/alias planned for v2; handoff rule |
| `README.md` | Running Locally section updated with actual build targets |
| `.gitignore` | PHP dirs suppressed; `build/`, `*.test` excluded |
| `SECURITY.md` | BFT-PoS intro paragraph added |

---

## v1 Build — Go/Cosmos SDK core

**G4L1L3O**
- ~~Scaffold `lars/rebuild` branch: Go module init, Cosmos SDK + CometBFT wiring, CI/lint setup~~ ✅ DONE (see [DONE] section above)
- ~~Custom address codec (base58 ECDSA — D3): `DeriveAddress`, `StringToBytes`/`BytesToString`, bech32 fallback~~ ✅ DONE (see [DONE] section above)
- ~~Keeper init + module manager wiring + ABCI handlers + `x/consensus` + `moduleAuthority` helper~~ ✅ DONE (see [DONE] section above)
- ~~Single-validator devnet bootstrap (`scripts/devnet-setup.sh`)~~ ✅ DONE — node produces blocks
- VRF proposer rotation (D1a) — non-trivial, consensus-critical; specify VRF seed/epoch scheme
- S4QL → genesis migration tool (D10): balance reconciliation/audit proof — G4L1L3O knows the DB schema intimately
- Economic parameters: emission curve, reward splits, min bond (D4) — specify curve before TheRealGofre implements

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
