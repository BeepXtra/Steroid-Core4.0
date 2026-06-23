# Steroid Core — Next Generation (rebuild)

This branch (`lars/rebuild`) is the home of the **next-generation Steroid core**: a
horizontally-scalable, **BFT proof-of-stake**, retail-focused **payments + loyalty**
blockchain — the planned successor to the first-stage chain, on the staged roadmap
set out since Steroid's 2018 inception.

> **First-stage chain:** the PHP/MySQL node on **`master`** (database `S4QL`, node
> `galileo`) remains in production until cutover. **Do not break it.**

## 📖 Read this first
**`docs/FUTURE-ARCHITECTURE.md`** — the full handoff brief: where the first stage
stands and why it's time to graduate, owner goals, the agreed architecture, and the
complete **decision log
(D1–D11)** with rationale. It is the source of truth for this rebuild.

## Agreed design (summary — see the doc for full rationale)
- **Stack:** Go + Cosmos SDK + CometBFT.
- **Consensus:** DPoS **rotating committee** of ~5–6 staked masternodes, re-rolled
  per epoch via an unpredictable **VRF**, instant BFT finality. **No PoW.**
- **Participate-by-using:** merchant **edge nodes** + **proof-of-usage rewards** +
  phone **light-clients** + **randomness fed by user transactions**.
- **Economics:** PoS; masternodes = bonded validators (250k min self-bond); cold
  staking → delegation; 0.3% fee kept; governance via `x/gov`.
- **Storage:** proven **RocksDB/IAVL** engine; **AnyData** + **smart contracts
  (CosmWasm)** delivered as modules on top (AnyData = on-chain hash + content on
  the edge layer, to avoid bloat).
- **Compatibility:** keep existing **base58 addresses** (secp256k1) + a **REST
  gateway** mirroring today's API so BeepWallet / outlets / MerchD / the PHP SDK
  keep working.
- **Scale:** one solid chain first; **regional shards (IBC)** + the self-managing
  **HAProxy edge** later.
- **Migration:** snapshot `S4QL` → genesis (addresses preserved), short validate,
  fast cutover (chain is lightly used now — do it before growth).

## First tasks
See `docs/FUTURE-ARCHITECTURE.md` → **§II.5 "First tasks for the new code session"**.

## Kept as references (from the live chain)
- **`doc/`** — the current chain's API documentation (apidoc). This is the contract
  the compatibility gateway must match.
- **`sdk/`** — the current PHP SDK. Example client usage / endpoint shapes.

_(The PHP node's full source remains on `master` if you need to consult any
behaviour during the rebuild.)_
