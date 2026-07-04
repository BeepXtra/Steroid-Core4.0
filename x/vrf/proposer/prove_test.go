package proposer_test

import (
	"crypto/rand"
	"encoding/hex"
	"testing"

	"github.com/ProtonMail/go-ecvrf/ecvrf"
	"github.com/stretchr/testify/require"

	"github.com/beepxtra/steroid-core4.0/x/vrf/proposer"
)

func decodeHex(t *testing.T, s string) []byte {
	t.Helper()
	b, err := hex.DecodeString(s)
	require.NoError(t, err)
	return b
}

// RFC 9381 Appendix A.3, Example 7 — same fixture used to verify the
// underlying library against the RFC before adoption (see x/vrf/keeper's
// history). Exercised again here through our own Prove/Verify wrapper.
func TestProveVerify_RFCVector(t *testing.T) {
	seed := decodeHex(t, "9d61b19deffd5a60ba844af492ec2cc44449c5697b326919703bac031cae7f60")
	pk := decodeHex(t, "d75a980182b10ab7d54bfed3c964073a0ee172f3daa62325af021a68f707511a")
	// ecvrf.NewPrivateKey expects the standard RFC 8032 64-byte private key
	// form: 32-byte seed || 32-byte public key.
	sk := append(append([]byte{}, seed...), pk...)
	alpha := []byte{} // empty message, per the RFC vector
	wantPi := decodeHex(t, "8657106690b5526245a92b003bb079ccd1a92130477671f6fc01ad16f26f723f5e8bd1839b414219e8626d393787a192241fc442e6569e96c462f62b8079b9ed83ff2ee21c90c7c398802fdeebea4001")
	wantBeta := decodeHex(t, "90cf1df3b703cce59e2a35b925d411164068269d7b2d29f3301c03dd757876ff66b71dda49d2de59d03450451af026798e8f81cd2e333de5cdf4f3e140fdd8ae")

	output, proof, err := proposer.Prove(sk, alpha)
	require.NoError(t, err)
	require.Equal(t, wantPi, proof)
	require.Equal(t, wantBeta, output)

	verifiedOutput, err := proposer.Verify(pk, alpha, proof)
	require.NoError(t, err)
	require.Equal(t, wantBeta, verifiedOutput)
}

func genKeypair(t *testing.T) (sk, pk []byte) {
	t.Helper()
	priv, err := ecvrf.GenerateKey(rand.Reader)
	require.NoError(t, err)
	pub, err := priv.Public()
	require.NoError(t, err)
	return priv.Bytes(), pub.Bytes()
}

func TestProveVerify_RoundTrip(t *testing.T) {
	sk, pk := genKeypair(t)
	seed := []byte("some vrf seed")

	output, proof, err := proposer.Prove(sk, seed)
	require.NoError(t, err)

	verifiedOutput, err := proposer.Verify(pk, seed, proof)
	require.NoError(t, err)
	require.Equal(t, output, verifiedOutput)
}

func TestVerify_WrongSeedFails(t *testing.T) {
	sk, pk := genKeypair(t)
	_, proof, err := proposer.Prove(sk, []byte("seed A"))
	require.NoError(t, err)

	_, err = proposer.Verify(pk, []byte("seed B"), proof)
	require.Error(t, err)
}

func TestVerify_TamperedProofFails(t *testing.T) {
	sk, pk := genKeypair(t)
	seed := []byte("some vrf seed")
	_, proof, err := proposer.Prove(sk, seed)
	require.NoError(t, err)

	tampered := append([]byte{}, proof...)
	tampered[0] ^= 0xFF

	_, err = proposer.Verify(pk, seed, tampered)
	require.Error(t, err)
}

func TestVerify_MalformedPubKeyRejected(t *testing.T) {
	sk, _ := genKeypair(t)
	seed := []byte("some vrf seed")
	_, proof, err := proposer.Prove(sk, seed)
	require.NoError(t, err)

	malformedPubKey := make([]byte, ecvrf.PublicKeySize)
	for i := range malformedPubKey {
		malformedPubKey[i] = 0x02 // not a valid edwards25519 point encoding
	}

	_, err = proposer.Verify(malformedPubKey, seed, proof)
	require.Error(t, err)
}

func TestProve_InvalidPrivateKeyRejected(t *testing.T) {
	_, _, err := proposer.Prove([]byte{1, 2, 3}, []byte("seed"))
	require.Error(t, err)
}
