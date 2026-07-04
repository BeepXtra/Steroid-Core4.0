package keeper

import (
	"context"

	"cosmossdk.io/collections"
	addresscodec "cosmossdk.io/core/address"
	storetypes "cosmossdk.io/core/store"
	sdkerrors "cosmossdk.io/errors"

	"github.com/cosmos/cosmos-sdk/codec"

	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

// Keeper manages on-chain state for x/vrf: the registered VRF public key per
// validator (D1a, Decision 2). A validator with no entry here is skipped
// during proposer selection.
type Keeper struct {
	storeService storetypes.KVStoreService

	// validatorAddressCodec decodes/validates validator operator addresses
	// (base58 per D3) used as the map key below.
	validatorAddressCodec addresscodec.Codec

	// ValidatorVRFKeys maps a validator operator address (string form) to its
	// registered ValidatorVRFKey record.
	ValidatorVRFKeys collections.Map[string, types.ValidatorVRFKey]
}

// NewKeeper constructs a x/vrf Keeper.
func NewKeeper(
	cdc codec.BinaryCodec,
	storeService storetypes.KVStoreService,
	validatorAddressCodec addresscodec.Codec,
) Keeper {
	sb := collections.NewSchemaBuilder(storeService)
	return Keeper{
		storeService:          storeService,
		validatorAddressCodec: validatorAddressCodec,
		ValidatorVRFKeys: collections.NewMap(
			sb,
			collections.NewPrefix("ValidatorVRFKeys"),
			"validator_vrf_keys",
			collections.StringKey,
			codec.CollValue[types.ValidatorVRFKey](cdc),
		),
	}
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
