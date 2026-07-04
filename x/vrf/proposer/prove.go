package proposer

import (
	"fmt"

	"filippo.io/edwards25519"
	"github.com/ProtonMail/go-ecvrf/ecvrf"
)

// Prove computes a VRF proof and output over the given seed using a
// validator's raw ECVRF-EDWARDS25519-SHA512-TAI private key (as registered
// via MsgRegisterVRFKey's corresponding keypair). output is the VRF output
// (Decision 3's prev_vrf_output for the following height); proof is what
// gets attached to the block for other validators to verify.
func Prove(privKeyBytes, seed []byte) (output, proof []byte, err error) {
	sk, err := ecvrf.NewPrivateKey(privKeyBytes)
	if err != nil {
		return nil, nil, fmt.Errorf("proposer: invalid VRF private key: %w", err)
	}
	output, proof, err = sk.Prove(seed)
	if err != nil {
		return nil, nil, fmt.Errorf("proposer: VRF prove failed: %w", err)
	}
	return output, proof, nil
}

// Verify checks a VRF proof against a validator's registered public key and
// the seed it was supposedly computed over, and returns the VRF output
// encoded in the proof (which must match what the prover claims, and is what
// callers should feed forward as the next height's prev_vrf_output).
//
// pubKeyBytes is validated as a well-formed edwards25519 point first:
// ecvrf.NewPublicKey never validates its input (see x/vrf/types.ValidateBasic
// for the same finding), so this catches a malformed registered key here too
// rather than only inside the library's Verify call.
func Verify(pubKeyBytes, seed, proof []byte) (output []byte, err error) {
	if _, err := new(edwards25519.Point).SetBytes(pubKeyBytes); err != nil {
		return nil, fmt.Errorf("proposer: invalid VRF public key: %w", err)
	}
	pk, err := ecvrf.NewPublicKey(pubKeyBytes)
	if err != nil {
		return nil, fmt.Errorf("proposer: invalid VRF public key: %w", err)
	}
	verified, output, err := pk.Verify(seed, proof)
	if err != nil {
		return nil, fmt.Errorf("proposer: VRF verify failed: %w", err)
	}
	if !verified {
		return nil, fmt.Errorf("proposer: VRF proof did not verify")
	}
	return output, nil
}
