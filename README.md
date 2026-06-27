# Steroid Core 4.0

BFT proof-of-stake Layer-1 blockchain — Go/Cosmos SDK rewrite of the Steroid (BPC) network.

> **Branch layout**
> - `master` — live PHP/MySQL first-stage chain (do not modify)
> - `lars/rebuild` — this Go core; feature branches PR here
> - `claude/galileo-scaffolding-*` — current scaffolding work in progress

## Stack

| Component | Version |
|-----------|---------|
| Go | 1.22+ |
| Cosmos SDK | v0.50.10 |
| CometBFT (ABCI 2.0) | v0.38.12 |
| Store / IAVL | cosmossdk.io/store v1.1.1 |

## Build

```sh
# requires Go 1.22+
make build          # → ./stereodd binary
make install        # installs to $GOPATH/bin
make test           # unit tests
make lint           # golangci-lint (requires golangci-lint v1.61+)
```

## Run a local node

```sh
stereodd init moniker --chain-id steroid-local-1
stereodd start
```

## Repository layout

```
app/              Cosmos SDK application wiring
  params/         EncodingConfig
  codec.go        MakeEncodingConfig (bech32 → base58 at D3)
  app.go          App struct, keeper stubs, ABCI interface
cmd/stereodd/     CLI entry point
proto/            Protobuf definitions (planned — buf toolchain)
x/                Custom modules (planned — x/assets, x/alias at D5)
doc/              First-stage REST API reference (parity contract for D7)
sdk/php/          PHP SDK (first-stage reference)
docs/             Architecture spec and workplans
```

## Design decisions (open TODOs)

| ID | Decision |
|----|----------|
| D1a | VRF proposer rotation via custom PrepareProposal/ProcessProposal |
| D3 | Custom base58 address codec to preserve first-stage addresses |
| D4 | Emission curve, reward splits, min-bond (250k BPC) |
| D5 | x/assets and x/alias custom modules |
| D7 | REST compatibility gateway mirroring `doc/` apidoc |
| D10 | S4QL → genesis migration tool |

See [`docs/FUTURE-ARCHITECTURE.md`](docs/FUTURE-ARCHITECTURE.md) for the full spec.

## Links

- [Official website](https://www.steroid.io)
- [Block explorer](https://explorer.steroid.io)
- [BeepXtra Loyalty Systems](https://outlets.beepxtra.com/)
- Security issues: devteam@steroid.io (see [SECURITY.md](SECURITY.md))
