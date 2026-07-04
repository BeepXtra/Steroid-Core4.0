package proposer_test

import (
	"crypto/sha256"
	"testing"

	"github.com/stretchr/testify/require"

	"github.com/beepxtra/steroid-core4.0/x/vrf/proposer"
)

func TestSelectWinner_NoCandidates(t *testing.T) {
	_, err := proposer.SelectWinner(make([]byte, 32), nil)
	require.ErrorIs(t, err, proposer.ErrNoCandidates)
}

func TestSelectWinner_Deterministic(t *testing.T) {
	seed := sha256.Sum256([]byte("some seed"))
	candidates := []proposer.Candidate{
		{Address: "val-a"}, {Address: "val-b"}, {Address: "val-c"},
	}

	idx1, err := proposer.SelectWinner(seed[:], candidates)
	require.NoError(t, err)
	idx2, err := proposer.SelectWinner(seed[:], candidates)
	require.NoError(t, err)
	require.Equal(t, idx1, idx2, "same seed + same candidate set must pick the same winner")
	require.GreaterOrEqual(t, idx1, 0)
	require.Less(t, idx1, len(candidates))
}

func TestSelectWinner_DifferentSeedsCanDiffer(t *testing.T) {
	candidates := []proposer.Candidate{
		{Address: "val-a"}, {Address: "val-b"}, {Address: "val-c"}, {Address: "val-d"}, {Address: "val-e"},
	}

	seen := make(map[int]bool)
	for i := 0; i < 20; i++ {
		seed := sha256.Sum256([]byte{byte(i)})
		idx, err := proposer.SelectWinner(seed[:], candidates)
		require.NoError(t, err)
		seen[idx] = true
	}
	require.Greater(t, len(seen), 1, "varying the seed should reach more than one winner across enough samples")
}

func TestSelectWinner_SingleCandidateAlwaysWins(t *testing.T) {
	candidates := []proposer.Candidate{{Address: "only-one"}}
	for i := 0; i < 5; i++ {
		seed := sha256.Sum256([]byte{byte(i)})
		idx, err := proposer.SelectWinner(seed[:], candidates)
		require.NoError(t, err)
		require.Equal(t, 0, idx)
	}
}
