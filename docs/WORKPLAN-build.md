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

## [DONE] — D7 complete: REST compatibility gateway (completed 2026-07-05, claude/d7-rest-gateway → lars/rebuild)

Maps all 34 legacy `/api/*` endpoints to CometBFT RPC + Cosmos SDK gRPC, providing
drop-in HTTP compatibility for existing wallets and block explorers.

### Files built

| File | Description |
|------|-------------|
| `x/gateway/types.go` | Response envelope (`{"status":"ok","data":...}`), all JSON types: `blockData`, `txData`, `validatorData`, `nodeInfoData`, `walletData`; `ubpcPerBPC = 100_000_000`; `AppVersion = "4.0.0"` |
| `x/gateway/client.go` | `NodeClient` wrapping CometBFT HTTP RPC + gRPC; methods: `LatestBlock`, `BlockByHeight`, `Status`, `MempoolSize`, `Tx`, `TxsByAddress`, `TxsInBlock`, `BroadcastTxGRPC`, `Balance`, `TotalSupply`, `Validators`, `PeerCount`; helpers: `addrToBech32` (base58/bech32 → bech32 for CometBFT queries), `ubpcToBPC`, `blockHash` |
| `x/gateway/utils.go` | `compressPublicKey` — 65-byte uncompressed secp256k1 → 33-byte compressed via `github.com/decred/dcrd/dcrec/secp256k1/v4` |
| `x/gateway/handlers.go` | All 34 HTTP handler functions; helpers: `toBlockData`, `parseTx`, `parseEventAmount`, `parseBPC`, `validatorStatus`, `parseHeightVar`, `decodePubKeyString`, `decodeB64OrHex` |
| `x/gateway/server.go` | `StartGateway(ctx, listen, node, grpc)` — gorilla/mux router, graceful shutdown; `registerRoutes` wires all 34 routes + D5 stubs |
| `x/gateway/gateway_test.go` | 7 unit tests: info, version, checkaddress, generate_wallet, D5 stubs → 501, send missing params, ubpcToBPC table |
| `cmd/stereodd/cmd/gateway.go` | `GatewayCmd()` cobra subcommand: `stereodd gateway --listen :8080 --node tcp://localhost:26657 --grpc localhost:9090` |
| `cmd/stereodd/cmd/root.go` | `GatewayCmd()` added to root command |

### Endpoint mapping

| Legacy endpoint | Implementation |
|-----------------|----------------|
| `GET /api` | version/info string |
| `GET /api/version` | `AppVersion` |
| `GET /api/sanity` | CometBFT `Status` health check |
| `GET /api/node-info` | hostname, height, peers, mempool, validators, system info |
| `GET /api/currentblock` | latest committed block |
| `GET /api/getblock/:height` | block by height |
| `GET /api/getblocktransactions/:height` | all txs in a block |
| `GET /api/gettransaction/:id` | single tx by hash |
| `GET /api/gettransactions/:address` | paginated txs for an address |
| `GET /api/getbalance/:address` | confirmed ubpc balance |
| `GET /api/getpendingbalance` | same as confirmed (mempool deduction deferred to D4) |
| `GET /api/getaddress/:public_key` | base58 address from secp256k1 public key |
| `GET /api/base58/:string` | raw base58 encoding |
| `GET /api/checkaddress` | validate address format |
| `GET /api/checksignature/:pk/:sig/:data` | secp256k1 signature verification |
| `GET /api/generate_wallet` | generate new secp256k1 keypair + base58 address |
| `GET /api/getpublickey` | 501 (requires on-chain lookup; use `/api/getaddress` instead) |
| `GET /api/masternodes` | bonded validators list |
| `GET /api/mempoolsize` | unconfirmed tx count |
| `GET /api/totalsupply` | total ubpc supply |
| `GET /api/circsupply` | total − bonded (approximate) |
| `GET /api/randomnumber` | deterministic RNG from block hash |
| `GET /api/send` | broadcast Cosmos SDK tx (base64 `tx_bytes`); rejects legacy PHP format with 501 |
| `GET /api/getaliasaddress` et al. | 501 (D5 — aliases/assets, not yet implemented) |

### What works now

- `go build ./...` — clean
- `go test ./x/gateway/...` — all 7 tests pass
- `stereodd gateway --listen :8080 --node tcp://localhost:26657 --grpc localhost:9090` — starts HTTP server
- All non-D5 endpoints return correct JSON envelope or clear error
- Both base58 and bech32 address formats accepted on all address parameters

### Known limitations / not yet done

- `/api/getpendingbalance` returns confirmed balance only — mempool deduction requires D4-level tx tracking
- `/api/send` rejects the legacy PHP tx format (different signing scheme entirely); new wallets must use Cosmos SDK tx format
- D5 endpoints (aliases, assets) return 501 — pending `x/assets` + `x/alias` modules
- VRF-related endpoints not registered on the gateway (gRPC/CLI only for now)
- No CORS headers — add a middleware if a browser-based frontend needs to call the gateway directly
- No rate limiting — add `golang.org/x/time/rate` middleware for production deployment

---

## [DONE] — D1a complete: VRF proposer rotation wired into consensus

Implements all of D1a per the resolved implementation spec (see
`docs/FUTURE-ARCHITECTURE.md` §6 D1a): on-chain VRF key registry, seed
computation, winner selection, VRF prove/verify, and — resolving the
liveness blocker found while building this (see below) — a time-based
fallback window, wired into `PrepareProposal`/`ProcessProposal`/`PreBlocker`
and verified on a live devnet.

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
| `x/vrf/proposer/winner.go` | `SelectWinner` — Decision 1b's direct-index pick, pure function over a seed + canonically-ordered candidate list |
| `x/vrf/proposer/prove.go` | `Prove`/`Verify` wrapping `ProtonMail/go-ecvrf`, re-validated against the RFC 9381 A.3 vector through our own wrapper (not just the upstream library's own tests) |
| `x/vrf/keeper/fallback.go` | `ShouldAcceptFallback` — the deterministic, timestamp-based liveness fallback (see "Blocker resolved" below) |
| `x/vrf/keeper/keeper.go` | `StakingKeeper` expected-keeper interface, `Candidates` (bonded validators × registered VRF keys, canonically sorted), `OperatorAddressByConsAddr`, plus `LastVRFOutput`/`LastTxAccumulator`/`LastAcceptedTimeUnixNano` state |
| `x/vrf/keeper/abci.go` | `EncodeProofTx`/`DecodeProofTx` (magic-prefixed pseudo-tx), `EvaluateProposal` (the full accept/reject/fallback decision), `RecordAcceptedProposal` |
| `x/vrf/keeper/handlers.go` | `PrepareProposalHandler`, `ProcessProposalHandler`, `PreBlockerHandler` — the actual ABCI wiring |
| `app/vrfkey/vrfkey.go` | Node-local VRF private key file (`config/vrf_key.json`, 0600), generated on first start, analogous to `priv_validator_key.json` but for the separate VRF keypair (D1a Decision 2) |
| `proto/steroid/vrf/v1/types.proto` | Added `VRFProposalProof` (the injected-proof message) |
| `proto/steroid/vrf/v1/genesis.proto` | Added `last_vrf_output`/`last_tx_accumulator`/`last_accepted_time_unix_nano` for seed-continuity across restarts |
| `app/app.go` | `x/vrf` store key, keeper construction (now takes `StakingKeeper`), module manager registration, init-genesis ordering, VRF key loading, `SetPrepareProposal`/`SetProcessProposal`/`SetPreBlocker` |
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

### Blocker found and resolved: `ProcessProposal`/`PrepareProposal` wiring

Found while implementing: CometBFT v0.38's ABCI gives the app no visibility
into which round it's currently validating (`RequestProcessProposal`/
`RequestPrepareProposal` have no `Round` field), and Cosmos SDK's `baseapp`
resets all app-side state on *every* `ProcessProposal`/`PrepareProposal` call
— confirmed directly in `baseapp/abci.go`. Consequences: Decision 2a's
"bound rejection to the next-K round-robin candidates" cannot be built
safely (any cross-round counter would be local, per-validator state —
different validators would compute different counts depending on their own
timeout timing, which is how you fork a BFT chain), and the unbounded
fallback-free version is a liveness bug on its own (an offline/byzantine
winning validator halts the chain at that height, forever).

**Resolution shipped:** `keeper.ShouldAcceptFallback(proposalTime,
lastAcceptedTime, window)` — accept a non-winning proposer once the current
proposal's own timestamp (agreed via the BFT process itself, not locally
observed) is more than `window` past the last *committed* block's timestamp.
Both inputs are agreed-upon data, not local observations, so every honest
validator computes the identical accept/reject decision — this closes the
determinism gap that made round-counting unsafe. `window` is
`vrfkeeper.DefaultFallbackWindow` (30s placeholder — a build-time parameter
per the pattern already used elsewhere in this doc; needs real numbers once
network round-trip/timeout behaviour is measured).

### What works now

- `go build ./...`, `go vet ./...` — clean
- `go test ./x/vrf/...`, `go test ./app/vrfkey/...` — all unit tests pass, including the six core `EvaluateProposal` scenarios: winner-with-valid-proof accepted, non-winner rejected before the fallback window, non-winner accepted after it (with and without a proof of their own), no-registered-candidates always accepts (bootstrap safety), and winner-with-a-bad-proof still rejected before fallback (identity match alone isn't enough)
- `make build` + `scripts/devnet-setup.sh` + `stereodd start` — **full consensus wiring verified live**: node starts, generates its VRF key file (`config/vrf_key.json`, 0600), and produces blocks through the real `PrepareProposal`→`ProcessProposal`→`PreBlocker` path (exercising the "no registered candidates yet" bootstrap-accept case, since no `MsgRegisterVRFKey` tx has been submitted on this devnet)

### Known limitations / not yet done

- The VRF proof is carried as a magic-prefixed pseudo-tx prepended to the block, not via ABCI++ vote extensions (the more idiomatic mechanism, but a materially bigger lift spanning `ExtendVoteHandler`/`VerifyVoteExtensionHandler` across two heights). Cost: one benign, always-failing tx-decode entry per block in the ABCI response — cosmetic, not a correctness/determinism issue, since every validator sees identical bytes and fails identically. Worth revisiting, not blocking.
- `DefaultFallbackWindow` (30s) is a placeholder — needs real tuning once multi-validator network timing is measured
- Multi-validator/byzantine-winner scenarios are only verified by unit test, not a live multi-node network — the single-validator devnet only exercises the "no candidates registered" path, not real winner/non-winner/fallback behavior under real network timing
- No cooldown on key rotation (spec flagged this as a "consider," not a requirement — a second `MsgRegisterVRFKey` overwrites immediately)
- REST gateway routes for `x/vrf` queries — not registered (gRPC/CLI only for now)
- No CLI command wraps `app/vrfkey` for an operator to view/rotate their VRF key independent of node startup

### Next tasks (priority order)

1. **D1a — multi-validator network test.** Stand up a multi-node testnet, register VRF keys, and actually observe: correct winner selection, non-winner rejection, and fallback-window acceptance when a winner is taken offline. This is the one thing a single-node devnet can't verify — recommended before treating this as production-ready.
2. **D4 — Emission curve**: Replace default `x/mint` params with the Steroid emission schedule. G4L1L3O to specify.
3. **D10 — S4QL migration tool**: Blocking for M1 cutover.
4. **D5 — x/assets + x/alias**: Spec from G4L1L3O first.
5. ~~**D7 — REST gateway**: Map `doc/` apidoc endpoints → new core handlers.~~ ✅ DONE (see [DONE] section below)

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
- ~~REST compatibility gateway (D7): map `doc/` apidoc endpoints → new core handlers~~ ✅ DONE — `x/gateway` package + `stereodd gateway` subcommand (see [DONE] section above)
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
