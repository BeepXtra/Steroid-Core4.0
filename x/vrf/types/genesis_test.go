package types_test

import (
	"testing"

	"github.com/stretchr/testify/require"

	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

func TestDefaultGenesis_Validates(t *testing.T) {
	require.NoError(t, types.DefaultGenesis().Validate())
}

func TestGenesisState_Validate(t *testing.T) {
	validKey := validPubKey(t)

	t.Run("valid entries", func(t *testing.T) {
		gs := types.GenesisState{
			ValidatorVrfKeys: []*types.ValidatorVRFKey{
				{ValidatorAddress: "steroidvaloper1a", VrfPubKey: validKey, RegisteredAtHeight: 1},
			},
		}
		require.NoError(t, gs.Validate())
	})

	t.Run("empty validator address", func(t *testing.T) {
		gs := types.GenesisState{
			ValidatorVrfKeys: []*types.ValidatorVRFKey{
				{ValidatorAddress: "", VrfPubKey: validKey, RegisteredAtHeight: 1},
			},
		}
		require.Error(t, gs.Validate())
	})

	t.Run("duplicate validator address", func(t *testing.T) {
		gs := types.GenesisState{
			ValidatorVrfKeys: []*types.ValidatorVRFKey{
				{ValidatorAddress: "steroidvaloper1a", VrfPubKey: validKey, RegisteredAtHeight: 1},
				{ValidatorAddress: "steroidvaloper1a", VrfPubKey: validPubKey(t), RegisteredAtHeight: 2},
			},
		}
		require.Error(t, gs.Validate())
	})

	t.Run("invalid pubkey length", func(t *testing.T) {
		gs := types.GenesisState{
			ValidatorVrfKeys: []*types.ValidatorVRFKey{
				{ValidatorAddress: "steroidvaloper1a", VrfPubKey: []byte{1, 2, 3}, RegisteredAtHeight: 1},
			},
		}
		require.Error(t, gs.Validate())
	})
}
