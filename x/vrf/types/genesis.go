package types

import (
	"fmt"

	"filippo.io/edwards25519"
	"github.com/ProtonMail/go-ecvrf/ecvrf"

	"github.com/beepxtra/steroid-core4.0/x/vrf/seed"
)

// DefaultGenesis returns the default x/vrf genesis state: no registered
// keys, and the Decision 3 baseline seed-continuity state (empty VRF output,
// the empty-block tx accumulator). LastAcceptedTimeUnixNano is left at zero;
// InitGenesis fills it in with the actual genesis time.
func DefaultGenesis() *GenesisState {
	return &GenesisState{
		ValidatorVrfKeys:  []*ValidatorVRFKey{},
		LastVrfOutput:     []byte{},
		LastTxAccumulator: seed.ComputeTxAccumulator(nil),
	}
}

// Validate checks that the genesis state is well-formed: every entry has a
// non-empty validator address, a structurally valid VRF public key, and no
// validator address is registered more than once.
func (gs GenesisState) Validate() error {
	seen := make(map[string]bool, len(gs.ValidatorVrfKeys))
	for _, entry := range gs.ValidatorVrfKeys {
		if entry.ValidatorAddress == "" {
			return ErrInvalidValidatorAddress.Wrap("empty validator address in genesis")
		}
		if seen[entry.ValidatorAddress] {
			return fmt.Errorf("duplicate VRF key entry for validator %s", entry.ValidatorAddress)
		}
		seen[entry.ValidatorAddress] = true

		if len(entry.VrfPubKey) != ecvrf.PublicKeySize {
			return ErrInvalidVRFPubKey.Wrapf("validator %s: expected %d bytes, got %d",
				entry.ValidatorAddress, ecvrf.PublicKeySize, len(entry.VrfPubKey))
		}
		if _, err := new(edwards25519.Point).SetBytes(entry.VrfPubKey); err != nil {
			return ErrInvalidVRFPubKey.Wrapf("validator %s: not a valid edwards25519 point: %s", entry.ValidatorAddress, err.Error())
		}
	}
	return nil
}
