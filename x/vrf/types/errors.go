package types

import "cosmossdk.io/errors"

// x/vrf module sentinel errors
var (
	ErrInvalidValidatorAddress = errors.Register(ModuleName, 2, "invalid validator address")
	ErrInvalidVRFPubKey        = errors.Register(ModuleName, 3, "invalid VRF public key")
	ErrVRFKeyNotFound          = errors.Register(ModuleName, 4, "no VRF key registered for validator")
)
