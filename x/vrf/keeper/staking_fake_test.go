package keeper_test

import (
	"context"
	"fmt"

	sdk "github.com/cosmos/cosmos-sdk/types"
	stakingtypes "github.com/cosmos/cosmos-sdk/x/staking/types"
)

// fakeStakingKeeper is a minimal in-memory stand-in for x/vrf/keeper.StakingKeeper,
// used so x/vrf's own tests don't need a real staking module wired up.
type fakeStakingKeeper struct {
	bonded     []stakingtypes.Validator
	byConsAddr map[string]stakingtypes.Validator
}

func newFakeStakingKeeper() *fakeStakingKeeper {
	return &fakeStakingKeeper{byConsAddr: make(map[string]stakingtypes.Validator)}
}

func (f *fakeStakingKeeper) addValidator(operator string, consAddr sdk.ConsAddress) {
	v := stakingtypes.Validator{OperatorAddress: operator}
	f.bonded = append(f.bonded, v)
	f.byConsAddr[consAddr.String()] = v
}

func (f *fakeStakingKeeper) GetBondedValidatorsByPower(_ context.Context) ([]stakingtypes.Validator, error) {
	return f.bonded, nil
}

func (f *fakeStakingKeeper) GetValidatorByConsAddr(_ context.Context, consAddr sdk.ConsAddress) (stakingtypes.Validator, error) {
	v, ok := f.byConsAddr[consAddr.String()]
	if !ok {
		return stakingtypes.Validator{}, fmt.Errorf("validator not found for cons addr %s", consAddr)
	}
	return v, nil
}
