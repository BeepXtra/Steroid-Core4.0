package keeper

import (
	sdk "github.com/cosmos/cosmos-sdk/types"

	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

// InitGenesis loads the registered VRF keys and the Decision 3 seed-
// continuity state from genesis state. LastAcceptedTimeUnixNano of zero
// (the DefaultGenesis case, i.e. a fresh chain rather than a restart from an
// export) is replaced with the actual genesis/current block time.
func (k Keeper) InitGenesis(ctx sdk.Context, genState types.GenesisState) {
	for _, entry := range genState.ValidatorVrfKeys {
		if err := k.RegisterKey(ctx, entry.ValidatorAddress, entry.VrfPubKey, entry.RegisteredAtHeight); err != nil {
			panic(err)
		}
	}

	if err := k.LastVRFOutput.Set(ctx, genState.LastVrfOutput); err != nil {
		panic(err)
	}
	if err := k.LastTxAccumulator.Set(ctx, genState.LastTxAccumulator); err != nil {
		panic(err)
	}
	lastAccepted := genState.LastAcceptedTimeUnixNano
	if lastAccepted == 0 {
		lastAccepted = ctx.BlockTime().UnixNano()
	}
	if err := k.LastAcceptedTimeUnixNano.Set(ctx, lastAccepted); err != nil {
		panic(err)
	}
}

// ExportGenesis dumps all registered VRF keys plus the current seed-
// continuity state into genesis state.
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

	lastVRFOutput, err := k.LastVRFOutput.Get(ctx)
	if err != nil {
		panic(err)
	}
	lastTxAccumulator, err := k.LastTxAccumulator.Get(ctx)
	if err != nil {
		panic(err)
	}
	lastAcceptedTimeUnixNano, err := k.LastAcceptedTimeUnixNano.Get(ctx)
	if err != nil {
		panic(err)
	}

	return &types.GenesisState{
		ValidatorVrfKeys:         entries,
		LastVrfOutput:            lastVRFOutput,
		LastTxAccumulator:        lastTxAccumulator,
		LastAcceptedTimeUnixNano: lastAcceptedTimeUnixNano,
	}
}
