// Package address implements the Steroid chain's custom address codec.
//
// First-stage address format (preserved through migration per D3):
//
//	address_string = base58(SHA512^9(SubjectPublicKeyInfo_DER_bytes))
//
// The DER bytes are the raw content of a PEM "BEGIN PUBLIC KEY" block
// (PKIX SubjectPublicKeyInfo), identical to what PHP/OpenSSL produces via
// openssl_pkey_get_details()['key']. SHA512 is applied 9 times in binary;
// the final 64-byte hash is base58-encoded using the standard Bitcoin alphabet
// (no checksum, no version byte).
//
// SDK v0.50 sets MaxAddrLen = 255, so 64-byte addresses are valid.
//
// For new accounts created entirely within the Go chain (not migrated), the
// internal address bytes are whatever secp256k1.PubKey.Address() returns
// (20-byte RIPEMD160-SHA256). The codec base58-encodes those too, giving
// shorter (~27-char) but valid strings. The genesis migration tool (D10) will
// use DeriveAddress to reproduce first-stage addresses for migrated accounts.
package address

import (
	"crypto/sha512"
	"errors"
	"fmt"
	"math/big"

	secp "github.com/decred/dcrd/dcrec/secp256k1/v4"
	sdkbech32 "github.com/cosmos/cosmos-sdk/types/bech32"
)

// alphabet is the standard Bitcoin base58 alphabet, identical to the
// first-stage chain (stephen-hill/base58php).
const alphabet = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz"

// Codec satisfies cosmossdk.io/core/address.Codec using base58 encoding.
// It is used for account addresses, validator operator addresses, and
// consensus node addresses throughout the Steroid chain.
type Codec struct{}

// StringToBytes decodes an address string to raw bytes.
// Accepts base58 (Steroid native format) or bech32 (SDK internal format).
// Bech32 support is required for SDK compatibility: several SDK modules
// (auth, bank, staking) hardcode sdk.AccAddressFromBech32 inside proto types
// (e.g. BaseAccount.GetAddress), so genesis JSON produced by SDK tools contains
// bech32 strings that must round-trip through this codec.
func (Codec) StringToBytes(text string) ([]byte, error) {
	if text == "" {
		return []byte{}, nil
	}
	// Try base58 first — the Steroid canonical format.
	b, err := Base58Decode(text)
	if err == nil && len(b) > 0 {
		return b, nil
	}
	// Fall back to bech32 for SDK-internal addresses (module accounts, genesis).
	_, addr, bech32Err := sdkbech32.DecodeAndConvert(text)
	if bech32Err == nil {
		return addr, nil
	}
	return nil, fmt.Errorf("invalid steroid address %q: not valid base58 or bech32", text)
}

// BytesToString base58-encodes a raw address byte slice.
func (Codec) BytesToString(bz []byte) (string, error) {
	if len(bz) == 0 {
		return "", nil
	}
	return Base58Encode(bz), nil
}

// DeriveAddress computes a Steroid address from a compressed secp256k1 public
// key (33 bytes), reproducing the first-stage get_address($public_key) logic.
//
// Algorithm:
//  1. Wrap the compressed key as a PKIX SubjectPublicKeyInfo (DER), matching
//     what PHP/OpenSSL exports as "BEGIN PUBLIC KEY".
//  2. Apply SHA512 nine times, each over the previous binary output.
//  3. Return the 64-byte result (the caller base58-encodes it for display).
//
// Used by the S4QL → genesis migration tool (D10) to reproduce first-stage
// addresses. Not called during normal chain operation.
func DeriveAddress(compressedPubKey []byte) ([]byte, error) {
	if len(compressedPubKey) != 33 {
		return nil, fmt.Errorf("expected 33-byte compressed secp256k1 pubkey, got %d bytes", len(compressedPubKey))
	}

	// Parse compressed point then build PKIX SubjectPublicKeyInfo DER manually.
	// Go's crypto/x509 does not support secp256k1 (non-NIST), so we hardcode
	// the fixed prefix that OpenSSL emits for this curve and key type.
	//
	// Structure (88 bytes total):
	//   SEQUENCE {
	//     SEQUENCE {
	//       OID id-ecPublicKey  (1.2.840.10045.2.1)
	//       OID secp256k1       (1.3.132.0.10)
	//     }
	//     BIT STRING 0x00 0x04 <X 32 bytes> <Y 32 bytes>
	//   }
	parsed, err := secp.ParsePubKey(compressedPubKey)
	if err != nil {
		return nil, fmt.Errorf("parse secp256k1 pubkey: %w", err)
	}
	// secp256k1 SubjectPublicKeyInfo DER prefix (23 bytes), identical to
	// what PHP/OpenSSL exports via openssl_pkey_get_details()['key'].
	prefix := []byte{
		0x30, 0x56, // SEQUENCE, 86 bytes
		0x30, 0x10, // SEQUENCE, 16 bytes (algorithm identifier)
		0x06, 0x07, 0x2a, 0x86, 0x48, 0xce, 0x3d, 0x02, 0x01, // OID id-ecPublicKey
		0x06, 0x05, 0x2b, 0x81, 0x04, 0x00, 0x0a, // OID secp256k1
		0x03, 0x42, 0x00, 0x04, // BIT STRING (66 bytes payload, no unused bits, uncompressed prefix)
	}
	uncompressed := parsed.SerializeUncompressed() // 0x04 || X(32) || Y(32)
	derBytes := append(prefix, uncompressed[1:]...)  // skip the 0x04 prefix byte

	// SHA512 × 9 (binary).
	h := derBytes
	for range 9 {
		sum := sha512.Sum512(h)
		h = sum[:]
	}
	return h, nil
}

// Base58Encode encodes b using the Bitcoin base58 alphabet.
// Matches the PHP stephen-hill/base58php library used in the first-stage chain.
func Base58Encode(b []byte) string {
	if len(b) == 0 {
		return ""
	}

	n := new(big.Int).SetBytes(b)
	base := big.NewInt(58)
	zero := big.NewInt(0)
	mod := new(big.Int)

	var result []byte
	for n.Cmp(zero) > 0 {
		n.DivMod(n, base, mod)
		result = append(result, alphabet[mod.Int64()])
	}

	// Leading zero bytes → '1' (alphabet[0]).
	for _, byt := range b {
		if byt != 0 {
			break
		}
		result = append(result, alphabet[0])
	}

	// Reverse.
	for i, j := 0, len(result)-1; i < j; i, j = i+1, j-1 {
		result[i], result[j] = result[j], result[i]
	}
	return string(result)
}

// Base58Decode decodes a base58 string using the Bitcoin alphabet.
func Base58Decode(s string) ([]byte, error) {
	if s == "" {
		return []byte{}, nil
	}

	n := big.NewInt(0)
	base := big.NewInt(58)

	for _, c := range s {
		idx := indexInAlphabet(byte(c))
		if idx < 0 {
			return nil, errors.New("invalid base58 character")
		}
		n.Mul(n, base)
		n.Add(n, big.NewInt(int64(idx)))
	}

	decoded := n.Bytes()

	// Preserve leading '1' characters as zero bytes.
	nLeadingZeros := 0
	for _, c := range s {
		if c != rune(alphabet[0]) {
			break
		}
		nLeadingZeros++
	}

	result := make([]byte, nLeadingZeros+len(decoded))
	copy(result[nLeadingZeros:], decoded)
	return result, nil
}

func indexInAlphabet(c byte) int {
	for i := 0; i < len(alphabet); i++ {
		if alphabet[i] == c {
			return i
		}
	}
	return -1
}
