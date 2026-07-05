// Package vrfkey manages a validator's ECVRF-EDWARDS25519-SHA512-TAI private
// key file, analogous to CometBFT's own priv_validator_key.json but for the
// separate VRF keypair (D1a, Decision 2 — the VRF key is intentionally not
// derived from the consensus key). Generating the file does not register the
// key on-chain; that's a separate MsgRegisterVRFKey transaction the operator
// submits using the public key this package derives.
package vrfkey

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"errors"
	"os"
	"path/filepath"

	"github.com/ProtonMail/go-ecvrf/ecvrf"
)

// FileName is the VRF private key file's name within a node's config directory.
const FileName = "vrf_key.json"

type fileFormat struct {
	PrivKeyHex string `json:"priv_key_hex"`
	PubKeyHex  string `json:"pub_key_hex"`
}

// LoadOrGenerate reads the VRF private key from <configDir>/vrf_key.json,
// generating and persisting a new one (0600 permissions) if it doesn't exist
// yet. Returns the raw 64-byte private key (RFC 8032 seed||pubkey form, as
// used by x/vrf/proposer.Prove).
func LoadOrGenerate(configDir string) (privKey, pubKey []byte, err error) {
	path := filepath.Join(configDir, FileName)

	data, err := os.ReadFile(path)
	switch {
	case err == nil:
		var f fileFormat
		if jerr := json.Unmarshal(data, &f); jerr != nil {
			return nil, nil, jerr
		}
		privKey, err = hex.DecodeString(f.PrivKeyHex)
		if err != nil {
			return nil, nil, err
		}
		pubKey, err = hex.DecodeString(f.PubKeyHex)
		if err != nil {
			return nil, nil, err
		}
		return privKey, pubKey, nil

	case errors.Is(err, os.ErrNotExist):
		sk, genErr := ecvrf.GenerateKey(rand.Reader)
		if genErr != nil {
			return nil, nil, genErr
		}
		pk, genErr := sk.Public()
		if genErr != nil {
			return nil, nil, genErr
		}
		privKey, pubKey = sk.Bytes(), pk.Bytes()

		out, merr := json.MarshalIndent(fileFormat{
			PrivKeyHex: hex.EncodeToString(privKey),
			PubKeyHex:  hex.EncodeToString(pubKey),
		}, "", "  ")
		if merr != nil {
			return nil, nil, merr
		}
		if werr := os.MkdirAll(configDir, 0o755); werr != nil {
			return nil, nil, werr
		}
		if werr := os.WriteFile(path, out, 0o600); werr != nil {
			return nil, nil, werr
		}
		return privKey, pubKey, nil

	default:
		return nil, nil, err
	}
}
