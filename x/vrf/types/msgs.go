package types

import (
	"filippo.io/edwards25519"
	"github.com/ProtonMail/go-ecvrf/ecvrf"
)

// ValidateBasic performs stateless sanity checks on MsgRegisterVRFKey.
//
// ecvrf.NewPublicKey never fails — it just stores the raw bytes without
// checking that they decode to a valid curve point, deferring that to
// Verify(). Validating point-decodability here, at registration time,
// catches a malformed key before it ever reaches on-chain state instead of
// only failing (expensively, and later) inside ProcessProposal.
func (m MsgRegisterVRFKey) ValidateBasic() error {
	if m.ValidatorAddress == "" {
		return ErrInvalidValidatorAddress.Wrap("empty validator address")
	}
	if len(m.VrfPubKey) != ecvrf.PublicKeySize {
		return ErrInvalidVRFPubKey.Wrapf("expected %d bytes, got %d", ecvrf.PublicKeySize, len(m.VrfPubKey))
	}
	if _, err := new(edwards25519.Point).SetBytes(m.VrfPubKey); err != nil {
		return ErrInvalidVRFPubKey.Wrapf("not a valid edwards25519 point: %s", err.Error())
	}
	return nil
}
