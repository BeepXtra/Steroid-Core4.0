package keeper

import (
	"context"

	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"

	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

var _ types.QueryServer = Keeper{}

// ValidatorVRFKey returns the registered VRF key for a validator, if any.
func (k Keeper) ValidatorVRFKey(ctx context.Context, req *types.QueryValidatorVRFKeyRequest) (*types.QueryValidatorVRFKeyResponse, error) {
	if req == nil || req.ValidatorAddress == "" {
		return nil, status.Error(codes.InvalidArgument, "validator_address cannot be empty")
	}
	key, found, err := k.GetKey(ctx, req.ValidatorAddress)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}
	if !found {
		return nil, status.Error(codes.NotFound, types.ErrVRFKeyNotFound.Error())
	}
	return &types.QueryValidatorVRFKeyResponse{ValidatorVrfKey: key}, nil
}
