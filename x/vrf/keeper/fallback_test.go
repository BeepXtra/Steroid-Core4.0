package keeper_test

import (
	"testing"
	"time"

	"github.com/stretchr/testify/require"

	"github.com/beepxtra/steroid-core4.0/x/vrf/keeper"
)

func TestShouldAcceptFallback(t *testing.T) {
	last := time.Unix(1000, 0)
	window := 30 * time.Second

	require.False(t, keeper.ShouldAcceptFallback(last.Add(1*time.Second), last, window), "well within window")
	require.False(t, keeper.ShouldAcceptFallback(last.Add(window), last, window), "exactly at the boundary must not yet trigger fallback")
	require.True(t, keeper.ShouldAcceptFallback(last.Add(window+time.Nanosecond), last, window), "one nanosecond past the window must trigger fallback")
	require.True(t, keeper.ShouldAcceptFallback(last.Add(10*time.Minute), last, window), "well past the window")
}

func TestShouldAcceptFallback_Deterministic(t *testing.T) {
	// Same two inputs must always produce the same answer regardless of how
	// many times, or in what order, it's evaluated — this is the whole
	// safety property the function exists to provide.
	last := time.Unix(2000, 0)
	proposal := last.Add(45 * time.Second)
	window := 30 * time.Second

	for i := 0; i < 5; i++ {
		require.True(t, keeper.ShouldAcceptFallback(proposal, last, window))
	}
}
