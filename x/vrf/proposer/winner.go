// Package proposer implements the pure, standalone pieces of D1a Decision 4
// (VRF-based proposer selection): picking the winning validator from a seed
// (Decision 1b's "direct index pick"), and generating/verifying the VRF
// proofs that back that selection. It has no dependency on the SDK, the
// keeper, or CometBFT, so it can be unit-tested in isolation.
//
// This package does NOT wire ProcessProposal/PrepareProposal into the
// consensus path. That wiring is a separate, unresolved piece — see
// docs/FUTURE-ARCHITECTURE.md D1a for why: CometBFT v0.38's ABCI gives the
// app no visibility into which round it is currently validating, and
// Cosmos SDK's baseapp resets all app-side state on every ProcessProposal/
// PrepareProposal call, so there is no safe way to bound how many rounds get
// rejected before falling back — and rejecting every non-winning proposer
// with no fallback risks halting the chain if the winner is offline.
package proposer

import (
	"encoding/binary"
	"errors"
)

// ErrNoCandidates is returned by SelectWinner when given an empty candidate list.
var ErrNoCandidates = errors.New("proposer: no eligible candidates")

// Candidate is an eligible proposer: a validator with a registered VRF key.
type Candidate struct {
	// Address is the validator's consensus (or operator) address string.
	Address string
	// VRFPubKey is the validator's registered ECVRF-EDWARDS25519-SHA512-TAI
	// public key.
	VRFPubKey []byte
}

// SelectWinner deterministically picks one candidate from seed, per D1a
// Decision 1b (direct index pick): the seed alone selects a winner index,
// with no comparison between candidates' own VRF outputs. candidates must be
// supplied in a stable, canonical order (e.g. sorted by address) so every
// validator that calls this with the same seed and candidate set computes
// the same winner independently.
func SelectWinner(seed []byte, candidates []Candidate) (int, error) {
	if len(candidates) == 0 {
		return -1, ErrNoCandidates
	}
	if len(seed) < 8 {
		// Pad rather than reject: callers always pass a 32-byte SHA-256
		// seed (see x/vrf/seed), so this only guards against misuse.
		padded := make([]byte, 8)
		copy(padded, seed)
		seed = padded
	}
	idx := binary.BigEndian.Uint64(seed[:8]) % uint64(len(candidates))
	return int(idx), nil
}
