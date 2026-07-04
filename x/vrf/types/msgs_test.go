package types_test

import (
	"crypto/rand"
	"testing"

	"github.com/ProtonMail/go-ecvrf/ecvrf"
	"github.com/stretchr/testify/require"

	"github.com/beepxtra/steroid-core4.0/x/vrf/types"
)

func bytesOf(v byte, n int) []byte {
	b := make([]byte, n)
	for i := range b {
		b[i] = v
	}
	return b
}

func validPubKey(t *testing.T) []byte {
	t.Helper()
	sk, err := ecvrf.GenerateKey(rand.Reader)
	require.NoError(t, err)
	pk, err := sk.Public()
	require.NoError(t, err)
	return pk.Bytes()
}

func TestMsgRegisterVRFKey_ValidateBasic(t *testing.T) {
	cases := []struct {
		name    string
		msg     types.MsgRegisterVRFKey
		wantErr bool
	}{
		{
			name: "valid",
			msg: types.MsgRegisterVRFKey{
				ValidatorAddress: "steroidvaloper1xyz",
				VrfPubKey:        validPubKey(t),
			},
			wantErr: false,
		},
		{
			name: "empty validator address",
			msg: types.MsgRegisterVRFKey{
				ValidatorAddress: "",
				VrfPubKey:        validPubKey(t),
			},
			wantErr: true,
		},
		{
			name: "wrong length pubkey",
			msg: types.MsgRegisterVRFKey{
				ValidatorAddress: "steroidvaloper1xyz",
				VrfPubKey:        []byte{1, 2, 3},
			},
			wantErr: true,
		},
		{
			name: "malformed 32-byte pubkey (not a valid curve point)",
			msg: types.MsgRegisterVRFKey{
				ValidatorAddress: "steroidvaloper1xyz",
				// Every byte 0x02: fails edwards25519 point decoding (invalid
				// point encoding), unlike e.g. an all-zero buffer, which
				// happens to decode to a legitimate (if degenerate) point.
				VrfPubKey: bytesOf(0x02, ecvrf.PublicKeySize),
			},
			wantErr: true,
		},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			err := tc.msg.ValidateBasic()
			if tc.wantErr {
				require.Error(t, err)
			} else {
				require.NoError(t, err)
			}
		})
	}
}
