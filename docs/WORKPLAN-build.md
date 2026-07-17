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

## [DONE] — x/vrf module: ECVRF key registry + seed + winner selection (v2 scope)

> **Scope update (2026-07-17):** Per the revised architecture (D1a), v1 uses stock
> CometBFT weighted round-robin — zero custom consensus code. This module is v2 scope.
> The ABCI wiring (`PrepareProposal`/`ProcessProposal`/`PreBlocker`) and the node-local
> VRF key generation have been removed from v1. The `x/vrf` code itself is complete and
> correct for v2; the seed was also fixed in this same commit (see below).

### Files built / modified

| File | Change |
|------|--------|
| `proto/steroid/vrf/v1/*.proto` | `ValidatorVRFKey` state, `MsgRegisterVRFKey` tx, genesis, query definitions |
| `proto/{cosmos_proto,cosmos/msg/v1,gogoproto,google/api}/*.proto` | Vendored third-party proto deps |
| `x/vrf/types/` | Generated pb.go + hand-written `keys.go`, `errors.go`, `msgs.go`, `genesis.go`, `codec.go` |
| `x/vrf/keeper/` | `Keeper` (collections-based), `msg_server.go`, `grpc_query.go`, `genesis.go` |
| `x/vrf/module.go` | `AppModuleBasic`/`AppModule` wiring |
| `x/vrf/seed/seed.go` | `ComputeSeed(prevVRFOutput, height)` — user tx hashes permanently excluded (grindable by proposer) |
| `x/vrf/proposer/winner.go` | `SelectWinner` — direct-index pick over canonical candidate list |
| `x/vrf/proposer/prove.go` | `Prove`/`Verify` via `ProtonMail/go-ecvrf` (RFC 9381 A.3 vectors verified) |
| `x/vrf/keeper/fallback.go` | `ShouldAcceptFallback` — deterministic timestamp-based liveness fallback |
| `x/vrf/keeper/handlers.go` | `PrepareProposalHandler`, `ProcessProposalHandler`, `PreBlockerHandler` (not wired in v1) |
| `app/app.go` | VRFKeeper init + module registration; ABCI handlers removed from v1 wiring |

### Seed fix (2026-07-17)

Old seed: `SHA256(prevVRFOutput || height || txAccumulatorHash)` — grindable. A
block proposer who sees the mempool can filter tx inclusion to steer the accumulated
hash toward a favourable output.

New seed: `SHA256(prevVRFOutput || height)` — validator outputs only. User
transaction hashes are permanently excluded from entropy (D1a).

Removed: `ComputeTxAccumulator`, `LastTxAccumulator` keeper state, `userTxHashes`
parameter from `RecordAcceptedProposal`, corresponding genesis field.

### What works now (v2 code, not wired in v1)

- `go build ./...`, `go vet ./...` — clean
- `go test ./x/vrf/...` — all unit tests pass (seed, winner, prove/verify, six EvaluateProposal scenarios)
- ABCI handlers are implemented and tested but not registered in v1 app wiring

### What v2 must do to activate VRF

1. Register `SetPrepareProposal` / `SetProcessProposal` / `SetPreBlocker` in `app/app.go`
2. Add node-local VRF key file generation back (`app/vrfkey`)
3. Confirm `DefaultFallbackWindow` (currently 30s placeholder) against real network timing
4. Clean up the magic-prefixed pseudo-tx approach (replace with ABCI++ vote extensions — see handler comment)

---

## [DONE] — v1 Keeper Init + D3 Base58 Codec + Devnet (completed 2026-06-27, claude/keeper-init-wiring → lars/rebuild)

### Files built / modified

| File | Change |
|------|--------|
| `app/address/codec.go` | D3: `DeriveAddress` (SHA512×9 over PKIX DER, manual secp256k1 DER construction); `StringToBytes` bech32 fallback; `Base58Encode` / `Base58Decode` / `BytesToString` |
| `app/codec.go` | `NewInterfaceRegistryWithOptions` with `proto.HybridResolver` + `steroidaddress.Codec{}` |
| `app/app.go` | All v1 keepers; full ModuleManager; AnteHandler; `moduleAuthority()`; ABCI wiring |
| `cmd/stereodd/cmd/root.go` | `gentx` + `collect-gentxs` with base58 codec |
| `scripts/devnet-setup.sh` | Single-validator devnet bootstrap |

### What works now

- `go build ./...` — compiles clean
- `go test ./app/address/...` — all three tests pass
- `make build` — produces `./build/stereodd`
- `stereodd start` — node starts, produces blocks

### Known limitations / not yet done

- `keys show` displays bech32 addresses (cosmetic; chain state uses base58 internally)
- Emission curve (D4) — not started; default SDK mint params in use
- x/assets + x/alias (D5) — not started; v2 scope
- S4QL → genesis migration tool (D10) — not started
- 70-validator cap — not yet set in staking genesis params
- memiavl/store-v2 storage — not yet wired; default IAVL in use

---

## [DONE] — v1 Scaffolding (completed 2026-06-27, claude/galileo-scaffolding-w1w9sj → lars/rebuild)

### Files built

| File | Description |
|------|-------------|
| `go.mod` / `go.sum` | Module `github.com/beepxtra/steroid-core4.0`, Go 1.22, Cosmos SDK v0.50.10, CometBFT v0.38.12 |
| `app/params/encoding.go` | `EncodingConfig` struct |
| `app/codec.go` | `MakeEncodingConfig()` |
| `app/app.go` | `App` struct skeleton |
| `cmd/stereodd/main.go` | Entry point |
| `cmd/stereodd/cmd/root.go` | Full CLI |
| `Makefile` | `build`, `install`, `test`, `lint`, `proto`, `clean`, `help` |
| `.golangci.yml` | errcheck, govet, staticcheck, unused, gofmt, goimports, gocritic, misspell |
| `.github/workflows/ci.yml` | CI on push/PR to `lars/rebuild` and `claude/**` |

---

## v1 Build — Go/Cosmos SDK core

**G4L1L3O**
- ~~Scaffold `lars/rebuild` branch~~ ✅ DONE
- ~~Custom address codec (base58 ECDSA — D3)~~ ✅ DONE
- ~~Keeper init + module manager wiring + ABCI handlers + devnet~~ ✅ DONE
- Wire **memiavl / store/v2** as the commit store; add `cosmossdk.io/store/v2` to `go.mod` (D6 — required from day one, replaces default IAVL bottleneck) — **NEXT**
- Set **70-validator MaxValidators cap** in staking genesis params (D1a)
- **x/steroidbank**: total/available/locked balance accounting on top of `x/bank`; 0.3% fee; emission curve port from `SBlock::reward` (WS-B)
- S4QL → genesis migration tool (D10): balance reconciliation/audit proof — G4L1L3O knows the DB schema
- Economic parameters: emission curve, reward splits, min bond (D4) — specify before TheRealGofre implements

**TheRealGofre** (implements from spec)
- `x/gov` module wiring + vote-semantics mapping from existing PHP logic (v105–v107)
- ~~REST compatibility gateway (D7)~~ ✅ DONE
- Genesis file validation tooling (balance-for-balance check)
- `x/alias` module (from G4L1L3O spec)

**LARS**
- Spin up a sandbox Go environment on the VOYAGER and WAYFINDER masternodes for build/test
- Run migration dry-runs against a S4QL snapshot
- ~~CI automation (build/test on push to `lars/rebuild`)~~ ✅ DONE

---

## v1.5 Build — Retail fast path (D2a)

> `x/fastpay` is the hardest module in the build: novel protocol, consensus-adjacent,
> mandatory Opus-only session, second-session review before merge.

**G4L1L3O**
- Finalize D2a build-time parameters (§8): checkpoint interval, certificate encoding,
  intent expiry, fast-path fee timing (charged at checkpoint), equivocation-reconciliation
  rule, epoch/validator-set rotation gating, validator hardware baseline

**TheRealGofre / dedicated Opus session**
- `x/fastpay`: validator fast-path daemon, certificate aggregation, checkpoint settlement,
  merchant edge node (D2a) — per WS-G in the architecture workstream table

**Acceptance criteria (M4):**
- Certificate round-trip p99 < 500ms on geo-distributed localnet
- Scripted double-spend attempts NEVER yield two certificates for one (account, nonce)
- Equivocating account wedges only itself; reconciles at next checkpoint
- Checkpoint settlement clears `locked` correctly
- Sustained ≥ 50k fast-path TPS on reference hardware

---

## v2 Build — Assets, CosmWasm, AnyData-lite, PoU rewards, ECVRF

**G4L1L3O**
- Per-asset fee-pool mechanics design (D5)
- Proof-of-usage reward math: `r`, decay curves, epoch-bucket pruning (D5a)
- CosmWasm integration: gas model, contract-chain interface (D6a)
- AnyData-lite: inline-vs-hash boundary, pin count (default 3), fetch-and-verify frequency (D6b)
- Fix VRF seed entropy if not already done — **DONE (seed fixed 2026-07-17, tx hashes removed)**
- Activate ECVRF proposer rotation: re-wire `SetPrepareProposal`/`SetProcessProposal`/`SetPreBlocker` in `app/app.go`; restore node-local VRF key generation; tune `DefaultFallbackWindow`

**TheRealGofre** (implements from spec)
- `x/assets`: permissionless token launch + transfer + optional params (D5)
- `x/alias` (if not done in v1)
- AnyData-lite module implementation (D6b)
- CosmWasm wiring (D6a)

**LARS**
- Testnet orchestration for v2 feature testing
- Automated regression: block production, asset creation, alias resolution, PoU farming attack simulation

---

## Workstream table (parallelizable, from PART III §10)

| WS | Deliverable | Depends on | Phase |
|----|-------------|-----------|-------|
| A | Chain scaffold: memiavl/store-v2, localnet, CI | — | v1 |
| B | `x/steroidbank`: total/available/locked; 0.3% fee; emission | A | v1 |
| C | Staking/governance: 250k bond, 70-cap, pause/resume, blacklist, cold-staking, vote parity | A | v1 |
| D | `x/alias` | A, B | v1 |
| E | Migration tool: S4QL → genesis; base58 codec; reconciliation report | B, C | v1 |
| F | D7 gateway: REST endpoints from `doc/` apidoc (~~done~~) | A, B | v1 ✅ |
| G | `x/fastpay`: validator daemon, cert aggregation, checkpoint, edge node (D2a) | B, C | v1.5 |
| H | `x/assets` (D5), CosmWasm (D6a) | B | v2 |
| I | AnyData-lite (D6b), proof-of-usage (D5a), ECVRF rotation (D1a v2) | C, G | v2 |

---

## Key handoff rule

TheRealGofre should never start a module without a written spec from G4L1L3O first. The
doc already flags §8 items as build-time decisions — those all land on G4L1L3O before
TheRealGofre touches code. LARS executes and monitors, never decides.
