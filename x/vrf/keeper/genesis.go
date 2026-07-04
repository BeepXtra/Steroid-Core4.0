package keeper

import (
	sdk "github.com/cosmos/cosmos-sdk/types"

	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

// InitGenesis loads the registered VRF keys from genesis state.
func (k Keeper) InitGenesis(ctx sdk.Context, genState types.GenesisState) {
	for _, entry := range genState.ValidatorVrfKeys {
		if err := k.RegisterKey(ctx, entry.ValidatorAddress, entry.VrfPubKey, entry.RegisteredAtHeight); err != nil {
			panic(err)
		}
	}
}

// ExportGenesis dumps all registered VRF keys into genesis state.
func (k Keeper) ExportGenesis(ctx sdk.Context) *types.GenesisState {
	var entries []*types.ValidatorVRFKey
	iter, err := k.ValidatorVRFKeys.Iterate(ctx, nil)
	if err != nil {
		panic(err)
	}
	defer iter.Close()

	for ; iter.Valid(); iter.Next() {
		kv, err := iter.KeyValue()
		if err != nil {
			panic(err)
		}
		entry := kv.Value
		entries = append(entries, &entry)
	}

	return &types.GenesisState{ValidatorVrfKeys: entries}
}
