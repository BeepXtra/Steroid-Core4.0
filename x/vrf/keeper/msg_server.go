package keeper

import (
	"context"

	sdk "github.com/cosmos/cosmos-sdk/types"

	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

var _ types.MsgServer = Keeper{}

// RegisterVRFKey registers or rotates the calling validator's VRF public key
// (D1a, Decision 2). A second call from the same validator overwrites the
// existing entry — no cooldown is enforced in v1 (flagged as a possible
// follow-up in the spec, not implemented here).
func (k Keeper) RegisterVRFKey(goCtx context.Context, msg *types.MsgRegisterVRFKey) (*types.MsgRegisterVRFKeyResponse, error) {
	if err := msg.ValidateBasic(); err != nil {
		return nil, err
	}
	ctx := sdk.UnwrapSDKContext(goCtx)
	if err := k.RegisterKey(ctx, msg.ValidatorAddress, msg.VrfPubKey, ctx.BlockHeight()); err != nil {
		return nil, err
	}
	return &types.MsgRegisterVRFKeyResponse{}, nil
}
