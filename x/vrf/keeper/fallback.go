package keeper

import "time"

// DefaultFallbackWindow is how long the chain waits, past the last accepted
// block, before accepting a block from a validator other than the
// seed-selected winner. This is a build-time parameter (like the other
// values flagged in docs/FUTURE-ARCHITECTURE.md §8) — 30s is a placeholder
// giving comfortable headroom over the sub-second target block time; tune
// once real network round-trip/timeout numbers are available.
const DefaultFallbackWindow = 30 * time.Second

// ShouldAcceptFallback is the sole piece of D1a's liveness-safety argument:
// it decides whether to accept a block from someone other than the
// computed VRF winner, evaluated as a pure function of two timestamps that
// every honest validator agrees on independently — proposalTime is part of
// the specific proposal being validated (agreed via the BFT process itself,
// not observed locally), and lastAcceptedTime comes from previously
// committed chain state. No validator-local counter or round number is
// involved, so two validators evaluating the same proposal always compute
// the same answer — see docs/FUTURE-ARCHITECTURE.md D1a for why every other
// approach considered was unsafe.
func ShouldAcceptFallback(proposalTime, lastAcceptedTime time.Time, window time.Duration) bool {
	return proposalTime.Sub(lastAcceptedTime) > window
}
