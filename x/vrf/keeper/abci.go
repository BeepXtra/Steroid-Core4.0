package keeper

import (
	"bytes"
	"context"
	"time"

	sdk "github.com/cosmos/cosmos-sdk/types"

	"github.com/beepxtra/steroid-core4.0/x/vrf/proposer"
	"github.com/beepxtra/steroid-core4.0/x/vrf/seed"
	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

// vrfProofTxMagic prefixes the proposer-injected VRFProposalProof so it can
// be told apart from a real user transaction. This is a simplification
// versus ABCI++ vote extensions (the more idiomatic mechanism for
// proposer-attached, non-transaction data) — vote extensions need an
// ExtendVoteHandler/VerifyVoteExtensionHandler pair spanning two heights,
// a materially bigger lift. The tradeoff: this pseudo-tx is included in the
// real block and reaches the normal tx-execution pipeline in FinalizeBlock,
// where it fails to decode as a real Tx and shows up as one benign failed
// tx per block. That's a cosmetic cost, not a safety or determinism one —
// every honest validator sees the identical bytes and experiences the
// identical, deterministic decode failure.
var vrfProofTxMagic = []byte{0xDE, 0xAD, 0xC0, 0xDE, 'V', 'R', 'F', '1'}

// EncodeProofTx serialises a VRFProposalProof as a magic-prefixed pseudo-tx
// for a proposer to prepend to its proposal's tx list.
func (k Keeper) EncodeProofTx(proof *types.VRFProposalProof) []byte {
	body := k.cdc.MustMarshal(proof)
	out := make([]byte, 0, len(vrfProofTxMagic)+len(body))
	out = append(out, vrfProofTxMagic...)
	out = append(out, body...)
	return out
}

// DecodeProofTx recognises and decodes a magic-prefixed VRFProposalProof
// pseudo-tx. ok is false for anything else (including ordinary user txs).
func (k Keeper) DecodeProofTx(tx []byte) (proof *types.VRFProposalProof, ok bool) {
	if len(tx) < len(vrfProofTxMagic) || !bytes.Equal(tx[:len(vrfProofTxMagic)], vrfProofTxMagic) {
		return nil, false
	}
	var p types.VRFProposalProof
	if err := k.cdc.Unmarshal(tx[len(vrfProofTxMagic):], &p); err != nil {
		return nil, false
	}
	return &p, true
}

// currentSeed computes the VRF seed for the given height from committed keeper
// state. Entropy = prev_vrf_output || height only — user tx hashes are
// permanently excluded (grindable by the block proposer, see D1a).
func (k Keeper) currentSeed(ctx context.Context, height int64) ([]byte, error) {
	prevOutput, err := k.LastVRFOutput.Get(ctx)
	if err != nil {
		return nil, err
	}
	return seed.ComputeSeed(prevOutput, height), nil
}

// EvaluateProposal is D1a's core ProcessProposal decision, factored out as
// its own method so it can be unit-tested without a full ABCI harness. It is
// a deterministic function of: committed chain state reachable via ctx, and
// data carried by the specific proposal being validated (height, proposalTime,
// proposerConsAddr, injectedProof) — no local, per-validator state. See
// ShouldAcceptFallback's doc comment for why that determinism matters.
//
// Returns accept, and — only when a valid VRF proof was actually verified —
// the VRF output to carry forward as the next height's prev_vrf_output.
func (k Keeper) EvaluateProposal(
	ctx context.Context,
	height int64,
	proposalTime time.Time,
	proposerConsAddr []byte,
	injectedProof *types.VRFProposalProof,
	fallbackWindow time.Duration,
) (accept bool, vrfOutput []byte, err error) {
	operator, err := k.OperatorAddressByConsAddr(ctx, proposerConsAddr)
	if err != nil {
		// CometBFT only ever proposes on behalf of a bonded validator, so
		// this should not happen; treat it as unresolvable rather than panic.
		return false, nil, nil
	}

	candidates, err := k.Candidates(ctx)
	if err != nil {
		return false, nil, err
	}

	lastAcceptedNano, err := k.LastAcceptedTimeUnixNano.Get(ctx)
	if err != nil {
		return false, nil, err
	}
	lastAccepted := time.Unix(0, lastAcceptedNano)
	fallback := ShouldAcceptFallback(proposalTime, lastAccepted, fallbackWindow)

	if len(candidates) == 0 {
		// No validator has registered a VRF key at all — there is no winner
		// to compute, so every height is necessarily a fallback height.
		return true, nil, nil
	}

	s, err := k.currentSeed(ctx, height)
	if err != nil {
		return false, nil, err
	}
	winnerIdx, err := proposer.SelectWinner(s, candidates)
	if err != nil {
		return false, nil, err
	}
	winner := candidates[winnerIdx]

	// Try to extract a verified VRF output from whatever proof was attached,
	// regardless of whether this proposer is the "true" winner — a fallback
	// acceptance still benefits from fresh entropy if the proposer happens
	// to have a registered key and produced a valid proof.
	tryVerify := func() []byte {
		if injectedProof == nil || injectedProof.ValidatorAddress != operator {
			return nil
		}
		key, found, ferr := k.GetKey(ctx, operator)
		if ferr != nil || !found {
			return nil
		}
		out, verr := proposer.Verify(key.VrfPubKey, s, injectedProof.Proof)
		if verr != nil {
			return nil
		}
		return out
	}

	if winner.Address == operator {
		if out := tryVerify(); out != nil {
			return true, out, nil
		}
		// The computed winner didn't attach a valid proof of their own
		// (malformed/missing) — fall through to the fallback check below
		// rather than granting a free pass on identity alone.
	}

	if fallback {
		return true, tryVerify(), nil
	}

	return false, nil, nil
}

// RecordAcceptedProposal updates the seed-continuity/fallback-timing state
// after a block has been finalized (called from PreBlocker). It re-runs
// EvaluateProposal to extract the VRF output — same inputs, same result,
// no extra state needed.
func (k Keeper) RecordAcceptedProposal(ctx sdk.Context, height int64, blockTime time.Time, proposerConsAddr []byte, injectedProof *types.VRFProposalProof, fallbackWindow time.Duration) error {
	_, vrfOutput, err := k.EvaluateProposal(ctx, height, blockTime, proposerConsAddr, injectedProof, fallbackWindow)
	if err != nil {
		return err
	}
	if vrfOutput != nil {
		if err := k.LastVRFOutput.Set(ctx, vrfOutput); err != nil {
			return err
		}
	}
	return k.LastAcceptedTimeUnixNano.Set(ctx, blockTime.UnixNano())
}
