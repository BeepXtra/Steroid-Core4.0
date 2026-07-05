package gateway

import (
	"fmt"

	secp "github.com/decred/dcrd/dcrec/secp256k1/v4"
)

// compressPublicKey converts a 65-byte uncompressed secp256k1 public key
// (0x04 || X || Y) to a 33-byte compressed form.
func compressPublicKey(uncompressed []byte) ([]byte, error) {
	if len(uncompressed) != 65 || uncompressed[0] != 0x04 {
		return nil, fmt.Errorf("expected 65-byte uncompressed key starting with 0x04")
	}
	parsed, err := secp.ParsePubKey(uncompressed)
	if err != nil {
		return nil, fmt.Errorf("parse secp256k1 public key: %w", err)
	}
	return parsed.SerializeCompressed(), nil
}
