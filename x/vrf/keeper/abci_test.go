package keeper_test

import (
	"testing"
	"time"

	"github.com/stretchr/testify/require"

	sdk "github.com/cosmos/cosmos-sdk/types"

	"github.com/beepxtra/steroid-core4.0/x/vrf/keeper"
	"github.com/beepxtra/steroid-core4.0/x/vrf/proposer"
	"github.com/beepxtra/steroid-core4.0/x/vrf/seed"
	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

// vrfIdentity bundles a validator's operator address, cons address, and VRF
// keypair for the EvaluateProposal tests below.
type vrfIdentity struct {
	operator string
	consAddr sdk.ConsAddress
	privKey  []byte
	pubKey   []byte
}

func newVRFIdentity(t *testing.T) vrfIdentity {
	t.Helper()
	sk, pk := genKeypair(t)
	return vrfIdentity{
		operator: genValAddr(t),
		consAddr: sdk.ConsAddress([]byte(genValAddr(t)[:20])),
		privKey:  sk,
		pubKey:   pk,
	}
}

const testFallbackWindow = 30 * time.Second

func setupEvaluateTest(t *testing.T, identities ...vrfIdentity) (keeper.Keeper, sdk.Context) {
	t.Helper()
	k, staking, ctx := setupKeeperWithStaking(t)

	for _, id := range identities {
		staking.addValidator(id.operator, id.consAddr)
		require.NoError(t, k.RegisterKey(ctx, id.operator, id.pubKey, 1))
	}

	// Genesis-equivalent baseline state.
	require.NoError(t, k.LastVRFOutput.Set(ctx, []byte{}))
	genesisTime := time.Unix(1_700_000_000, 0)
	require.NoError(t, k.LastAcceptedTimeUnixNano.Set(ctx, genesisTime.UnixNano()))

	return k, ctx
}

// computeWinner mirrors what EvaluateProposal does internally, so tests can
// know in advance which of the registered identities is the true winner for
// a given height, without depending on EvaluateProposal's own answer.
func computeWinner(t *testing.T, k keeper.Keeper, ctx sdk.Context, height int64) vrfIdentity { //nolint:unparam
	t.Helper()
	candidates, err := k.Candidates(ctx)
	require.NoError(t, err)
	prevOutput, err := k.LastVRFOutput.Get(ctx)
	require.NoError(t, err)
	s := seed.ComputeSeed(prevOutput, height)
	idx, err := proposer.SelectWinner(s, candidates)
	require.NoError(t, err)
	return vrfIdentity{operator: candidates[idx].Address}
}

func proveFor(t *testing.T, k keeper.Keeper, ctx sdk.Context, height int64, id vrfIdentity) *types.VRFProposalProof {
	t.Helper()
	prevOutput, err := k.LastVRFOutput.Get(ctx)
	require.NoError(t, err)
	s := seed.ComputeSeed(prevOutput, height)
	_, proof, err := proposer.Prove(id.privKey, s)
	require.NoError(t, err)
	return &types.VRFProposalProof{ValidatorAddress: id.operator, Proof: proof}
}

func TestEvaluateProposal_WinnerWithValidProofAccepted(t *testing.T) {
	a, b := newVRFIdentity(t), newVRFIdentity(t)
	k, ctx := setupEvaluateTest(t, a, b)

	genesisTime := time.Unix(1_700_000_000, 0)
	height := int64(2)

	winnerAddr := computeWinner(t, k, ctx, height).operator
	var winner vrfIdentity
	for _, id := range []vrfIdentity{a, b} {
		if id.operator == winnerAddr {
			winner = id
		}
	}
	require.NotEmpty(t, winner.operator, "must find the computed winner among test identities")

	proof := proveFor(t, k, ctx, height, winner)
	proposalTime := genesisTime.Add(500 * time.Millisecond) // well within the fallback window

	accept, output, err := k.EvaluateProposal(ctx, height, proposalTime, winner.consAddr, proof, testFallbackWindow)
	require.NoError(t, err)
	require.True(t, accept)
	require.NotEmpty(t, output)
}

func TestEvaluateProposal_NonWinnerRejectedBeforeFallbackWindow(t *testing.T) {
	a, b := newVRFIdentity(t), newVRFIdentity(t)
	k, ctx := setupEvaluateTest(t, a, b)

	genesisTime := time.Unix(1_700_000_000, 0)
	height := int64(2)

	winnerAddr := computeWinner(t, k, ctx, height).operator
	var loser vrfIdentity
	for _, id := range []vrfIdentity{a, b} {
		if id.operator != winnerAddr {
			loser = id
		}
	}
	require.NotEmpty(t, loser.operator)

	proof := proveFor(t, k, ctx, height, loser)
	proposalTime := genesisTime.Add(1 * time.Second) // within the fallback window

	accept, output, err := k.EvaluateProposal(ctx, height, proposalTime, loser.consAddr, proof, testFallbackWindow)
	require.NoError(t, err)
	require.False(t, accept, "a non-winning proposer must be rejected before the fallback window elapses")
	require.Nil(t, output)
}

func TestEvaluateProposal_NonWinnerAcceptedAfterFallbackWindow(t *testing.T) {
	a, b := newVRFIdentity(t), newVRFIdentity(t)
	k, ctx := setupEvaluateTest(t, a, b)

	genesisTime := time.Unix(1_700_000_000, 0)
	height := int64(2)

	winnerAddr := computeWinner(t, k, ctx, height).operator
	var loser vrfIdentity
	for _, id := range []vrfIdentity{a, b} {
		if id.operator != winnerAddr {
			loser = id
		}
	}

	proof := proveFor(t, k, ctx, height, loser)
	proposalTime := genesisTime.Add(testFallbackWindow + time.Second) // past the window

	accept, output, err := k.EvaluateProposal(ctx, height, proposalTime, loser.consAddr, proof, testFallbackWindow)
	require.NoError(t, err)
	require.True(t, accept, "the chain must not stall forever waiting for an absent winner")
	require.NotEmpty(t, output, "a valid proof from the fallback proposer should still be used for entropy continuity")
}

func TestEvaluateProposal_NonWinnerAcceptedAfterFallbackWindow_NoProof(t *testing.T) {
	a, b := newVRFIdentity(t), newVRFIdentity(t)
	k, ctx := setupEvaluateTest(t, a, b)

	genesisTime := time.Unix(1_700_000_000, 0)
	height := int64(2)

	winnerAddr := computeWinner(t, k, ctx, height).operator
	var loser vrfIdentity
	for _, id := range []vrfIdentity{a, b} {
		if id.operator != winnerAddr {
			loser = id
		}
	}

	proposalTime := genesisTime.Add(testFallbackWindow + time.Second)

	accept, output, err := k.EvaluateProposal(ctx, height, proposalTime, loser.consAddr, nil, testFallbackWindow)
	require.NoError(t, err)
	require.True(t, accept, "fallback acceptance must not require a proof at all")
	require.Nil(t, output, "with no proof, there is nothing to carry forward as fresh entropy")
}

func TestEvaluateProposal_NoRegisteredCandidatesAlwaysAccepts(t *testing.T) {
	// Nobody has registered a VRF key: there's no winner to compute, so the
	// chain must still be able to produce blocks (bootstrap safety).
	k, staking, ctx := setupKeeperWithStaking(t)
	require.NoError(t, k.LastVRFOutput.Set(ctx, []byte{}))
	genesisTime := time.Unix(1_700_000_000, 0)
	require.NoError(t, k.LastAcceptedTimeUnixNano.Set(ctx, genesisTime.UnixNano()))

	operator := genValAddr(t)
	consAddr := sdk.ConsAddress([]byte(operator[:20]))
	staking.addValidator(operator, consAddr)

	accept, output, err := k.EvaluateProposal(ctx, 2, genesisTime.Add(time.Second), consAddr, nil, testFallbackWindow)
	require.NoError(t, err)
	require.True(t, accept)
	require.Nil(t, output)
}

func TestEvaluateProposal_WinnerWithBadProofRejectedBeforeFallback(t *testing.T) {
	a, b := newVRFIdentity(t), newVRFIdentity(t)
	k, ctx := setupEvaluateTest(t, a, b)

	genesisTime := time.Unix(1_700_000_000, 0)
	height := int64(2)

	winnerAddr := computeWinner(t, k, ctx, height).operator
	var winner vrfIdentity
	for _, id := range []vrfIdentity{a, b} {
		if id.operator == winnerAddr {
			winner = id
		}
	}

	badProof := &types.VRFProposalProof{ValidatorAddress: winner.operator, Proof: []byte("not a real proof")}
	proposalTime := genesisTime.Add(time.Second)

	accept, output, err := k.EvaluateProposal(ctx, height, proposalTime, winner.consAddr, badProof, testFallbackWindow)
	require.NoError(t, err)
	require.False(t, accept, "matching identity alone must not substitute for a valid proof")
	require.Nil(t, output)
}
