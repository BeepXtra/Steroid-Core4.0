package keeper

import (
	"context"
	"sort"

	"cosmossdk.io/collections"
	addresscodec "cosmossdk.io/core/address"
	storetypes "cosmossdk.io/core/store"
	sdkerrors "cosmossdk.io/errors"

	"github.com/cosmos/cosmos-sdk/codec"
	sdk "github.com/cosmos/cosmos-sdk/types"
	stakingtypes "github.com/cosmos/cosmos-sdk/x/staking/types"

	"github.com/beepxtra/steroid-core4.0/x/vrf/proposer"
	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

// StakingKeeper is the subset of x/staking's keeper that x/vrf needs to
// assemble the eligible-proposer candidate set and to resolve a CometBFT
// consensus address back to an operator address.
type StakingKeeper interface {
	GetBondedValidatorsByPower(ctx context.Context) ([]stakingtypes.Validator, error)
	GetValidatorByConsAddr(ctx context.Context, consAddr sdk.ConsAddress) (stakingtypes.Validator, error)
}

// Keeper manages on-chain state for x/vrf: the registered VRF public key per
// validator (D1a, Decision 2), and the seed-continuity/fallback-timing state
// consumed by proposer selection (Decision 3, Decision 4).
type Keeper struct {
	storeService storetypes.KVStoreService
	cdc          codec.BinaryCodec

	// validatorAddressCodec decodes/validates validator operator addresses
	// (base58 per D3) used as the map key below.
	validatorAddressCodec addresscodec.Codec

	stakingKeeper StakingKeeper

	// ValidatorVRFKeys maps a validator operator address (string form) to its
	// registered ValidatorVRFKey record.
	ValidatorVRFKeys collections.Map[string, types.ValidatorVRFKey]

	// LastVRFOutput is the most recently accepted proposer's VRF output —
	// used as prev_vrf_output in the next height's seed.
	LastVRFOutput collections.Item[[]byte]

	// LastAcceptedTimeUnixNano is the timestamp of the most recently accepted
	// block, used to evaluate the fallback window (see ShouldAcceptFallback).
	LastAcceptedTimeUnixNano collections.Item[int64]
}

// NewKeeper constructs a x/vrf Keeper.
func NewKeeper(
	cdc codec.BinaryCodec,
	storeService storetypes.KVStoreService,
	validatorAddressCodec addresscodec.Codec,
	stakingKeeper StakingKeeper,
) Keeper {
	sb := collections.NewSchemaBuilder(storeService)
	return Keeper{
		storeService:          storeService,
		cdc:                   cdc,
		validatorAddressCodec: validatorAddressCodec,
		stakingKeeper:         stakingKeeper,
		ValidatorVRFKeys: collections.NewMap(
			sb,
			collections.NewPrefix("ValidatorVRFKeys"),
			"validator_vrf_keys",
			collections.StringKey,
			codec.CollValue[types.ValidatorVRFKey](cdc),
		),
		LastVRFOutput: collections.NewItem(
			sb, collections.NewPrefix("LastVRFOutput"), "last_vrf_output", collections.BytesValue,
		),
		LastAcceptedTimeUnixNano: collections.NewItem(
			sb, collections.NewPrefix("LastAcceptedTimeUnixNano"), "last_accepted_time_unix_nano", collections.Int64Value,
		),
	}
}

// Candidates returns the eligible proposer set for the current height: every
// bonded validator that has a registered VRF key, in a stable canonical
// order (sorted by operator address) so every validator computes the same
// list independently.
func (k Keeper) Candidates(ctx context.Context) ([]proposer.Candidate, error) {
	validators, err := k.stakingKeeper.GetBondedValidatorsByPower(ctx)
	if err != nil {
		return nil, err
	}

	candidates := make([]proposer.Candidate, 0, len(validators))
	for _, v := range validators {
		operator := v.GetOperator()
		key, found, err := k.GetKey(ctx, operator)
		if err != nil {
			return nil, err
		}
		if !found {
			continue
		}
		candidates = append(candidates, proposer.Candidate{Address: operator, VRFPubKey: key.VrfPubKey})
	}
	sort.Slice(candidates, func(i, j int) bool { return candidates[i].Address < candidates[j].Address })
	return candidates, nil
}

// OperatorAddressByConsAddr resolves a raw CometBFT consensus address (as
// seen in ABCI's ProposerAddress) to the validator's operator address (as
// used in x/vrf's key registration).
func (k Keeper) OperatorAddressByConsAddr(ctx context.Context, consAddr []byte) (string, error) {
	val, err := k.stakingKeeper.GetValidatorByConsAddr(ctx, sdk.ConsAddress(consAddr))
	if err != nil {
		return "", err
	}
	return val.GetOperator(), nil
}

// RegisterKey stores (or overwrites, i.e. rotates — D1a Decision 2) the VRF
// public key for the given validator address.
func (k Keeper) RegisterKey(ctx context.Context, validatorAddress string, vrfPubKey []byte, height int64) error {
	if _, err := k.validatorAddressCodec.StringToBytes(validatorAddress); err != nil {
		return types.ErrInvalidValidatorAddress.Wrap(err.Error())
	}
	return k.ValidatorVRFKeys.Set(ctx, validatorAddress, types.ValidatorVRFKey{
		ValidatorAddress:   validatorAddress,
		VrfPubKey:          vrfPubKey,
		RegisteredAtHeight: height,
	})
}

// GetKey returns the registered VRF key for a validator, or false if none is
// registered (per D1a Decision 2, such a validator is skipped from selection).
func (k Keeper) GetKey(ctx context.Context, validatorAddress string) (types.ValidatorVRFKey, bool, error) {
	key, err := k.ValidatorVRFKeys.Get(ctx, validatorAddress)
	if err != nil {
		if sdkerrors.IsOf(err, collections.ErrNotFound) {
			return types.ValidatorVRFKey{}, false, nil
		}
		return types.ValidatorVRFKey{}, false, err
	}
	return key, true, nil
}

// HasKey reports whether a validator has a registered VRF key.
func (k Keeper) HasKey(ctx context.Context, validatorAddress string) (bool, error) {
	return k.ValidatorVRFKeys.Has(ctx, validatorAddress)
}
