# Security Policy

Steroid 4.0 is a **BFT proof-of-stake Layer-1** whose integrity rests on **on-chain
economic security** — bonded validators, slashing, bounded reward pools, and
light-client verification (see [`docs/FUTURE-ARCHITECTURE.md`](docs/FUTURE-ARCHITECTURE.md)).
We take the security of the network and its participants seriously and welcome
responsible disclosure.

---

## Supported Versions

| Component | Version | Status |
|-----------|---------|--------|
| First-stage chain (`master`, PHP/MySQL) | 1.3.x | ✅ Active support — live in production until cutover |
| First-stage chain | < 1.2.9 | ❌ Deprecated |
| Next-generation core (`lars/rebuild`, Go/Cosmos) | pre-release | 🚧 In active development — not yet production |

We recommend all node operators run the latest supported release.

---

## Reporting a Vulnerability

If you discover a security vulnerability in Steroid 4.0, please report it
**confidentially** so it can be addressed before public disclosure.

1. Email **[devteam@steroid.io](mailto:devteam@steroid.io)**.
2. Include:
   - A clear description and steps to reproduce.
   - The potential impact on users, funds, or consensus.
   - Proof-of-concept code, if available.

We aim to acknowledge reports within **48 hours** and to keep you updated through
remediation. **Please do not disclose publicly until a fix has been confirmed and
deployed.**

---

## Scope

Security-relevant areas include, but are not limited to:

- **Consensus & validators** — BFT-PoS safety/liveness, VRF proposer-rotation
  integrity, slashing conditions.
- **Funds & state** — balance accounting (`available` / `locked`), asset and
  per-asset fee-pool logic, migration/genesis integrity.
- **Smart contracts & data** — CosmWasm execution sandboxing, AnyData commitments and
  retrievability.
- **Economic security** — proof-of-usage reward bounds, reward-pool exhaustion,
  diminishing-returns / anti-farming logic.
- **Edge & APIs** — the REST compatibility gateway and edge routing (signed-membership
  consumption).

---

## Security Best Practices for Operators & Users

- Keep nodes and wallets updated to the latest supported release.
- Obtain the software only from official sources.
- Protect private keys, validator keys, and credentials; use hardware-backed storage
  where possible.
- Validators: monitor for double-sign and downtime conditions to avoid slashing.

---

## Disclaimer

Steroid 4.0 is open-source software. Operators are responsible for deploying,
configuring, and securing their own infrastructure. While we strive for a secure
codebase, we cannot be held liable for losses arising from improper use or deployment.

Thank you for helping keep Steroid 4.0 and its users safe.
