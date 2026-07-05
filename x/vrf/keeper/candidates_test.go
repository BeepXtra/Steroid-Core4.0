package keeper_test

import (
	"testing"

	"github.com/stretchr/testify/require"

	sdk "github.com/cosmos/cosmos-sdk/types"
)

func TestCandidates_OnlyRegisteredValidatorsIncluded(t *testing.T) {
	k, staking, ctx := setupKeeperWithStaking(t)

	addrA, addrB, addrC := genValAddr(t), genValAddr(t), genValAddr(t)
	staking.addValidator(addrA, sdk.ConsAddress([]byte("consaddrA-consaddrA")))
	staking.addValidator(addrB, sdk.ConsAddress([]byte("consaddrB-consaddrB")))
	staking.addValidator(addrC, sdk.ConsAddress([]byte("consaddrC-consaddrC"))) // no VRF key registered

	require.NoError(t, k.RegisterKey(ctx, addrA, genVRFPubKey(t), 1))
	require.NoError(t, k.RegisterKey(ctx, addrB, genVRFPubKey(t), 2))

	candidates, err := k.Candidates(ctx)
	require.NoError(t, err)
	require.Len(t, candidates, 2, "validator C has no registered VRF key and must be excluded")

	addrs := map[string]bool{}
	for _, c := range candidates {
		addrs[c.Address] = true
	}
	require.True(t, addrs[addrA])
	require.True(t, addrs[addrB])
	require.False(t, addrs[addrC])
}

func TestCandidates_CanonicalOrder(t *testing.T) {
	k, staking, ctx := setupKeeperWithStaking(t)

	addrA, addrB := genValAddr(t), genValAddr(t)
	// Register in reverse of whatever sorted order will be, to prove the
	// output order comes from sorting, not registration/iteration order.
	staking.addValidator(addrB, sdk.ConsAddress([]byte("consaddrB-consaddrB")))
	staking.addValidator(addrA, sdk.ConsAddress([]byte("consaddrA-consaddrA")))
	require.NoError(t, k.RegisterKey(ctx, addrB, genVRFPubKey(t), 1))
	require.NoError(t, k.RegisterKey(ctx, addrA, genVRFPubKey(t), 2))

	c1, err := k.Candidates(ctx)
	require.NoError(t, err)
	c2, err := k.Candidates(ctx)
	require.NoError(t, err)
	require.Equal(t, c1, c2, "Candidates must return a stable, deterministic order across calls")

	for i := 1; i < len(c1); i++ {
		require.Less(t, c1[i-1].Address, c1[i].Address, "candidates must be sorted by address")
	}
}

func TestOperatorAddressByConsAddr(t *testing.T) {
	k, staking, ctx := setupKeeperWithStaking(t)

	addrA := genValAddr(t)
	consAddr := sdk.ConsAddress([]byte("consaddrA-consaddrA"))
	staking.addValidator(addrA, consAddr)

	operator, err := k.OperatorAddressByConsAddr(ctx, consAddr)
	require.NoError(t, err)
	require.Equal(t, addrA, operator)
}

func TestOperatorAddressByConsAddr_NotFound(t *testing.T) {
	k, _, ctx := setupKeeperWithStaking(t)
	_, err := k.OperatorAddressByConsAddr(ctx, sdk.ConsAddress([]byte("unknown-cons-addr-20")))
	require.Error(t, err)
}
