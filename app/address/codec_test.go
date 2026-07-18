package address_test

import (
	"testing"

	"github.com/beepxtra/steroid-core4.0/app/address"
)

// TestBase58RoundTrip verifies BytesToString and StringToBytes are inverses.
func TestBase58RoundTrip(t *testing.T) {
	cases := [][]byte{
		{0x00},
		{0x00, 0x00, 0x01},
		make([]byte, 20), // 20-byte address (cosmos-style)
		make([]byte, 32), // 32-byte address
		make([]byte, 64), // 64-byte address (first-stage SHA512^9)
	}
	for i := 1; i < len(cases[3]); i++ {
		cases[3][i] = byte(i)
	}
	for i := range cases[4] {
		cases[4][i] = byte(i * 3)
	}

	c := address.Codec{}
	for _, bz := range cases {
		str, err := c.BytesToString(bz)
		if err != nil {
			t.Fatalf("BytesToString(%x): %v", bz, err)
		}
		got, err := c.StringToBytes(str)
		if err != nil {
			t.Fatalf("StringToBytes(%q): %v", str, err)
		}
		if len(bz) != len(got) {
			t.Fatalf("round-trip length mismatch: input %d bytes, got %d bytes", len(bz), len(got))
		}
		for i := range bz {
			if bz[i] != got[i] {
				t.Fatalf("round-trip mismatch at byte %d: want %02x got %02x", i, bz[i], got[i])
			}
		}
	}
}

// TestBase58Vectors checks known encode/decode pairs using the Bitcoin alphabet.
func TestBase58Vectors(t *testing.T) {
	// "Hello World" in base58 per the bitcoin alphabet (canonical reference vectors).
	cases := []struct {
		hex string
		b58 string
	}{
		{"48656c6c6f20576f726c64", "JxF12TrwUP45BMd"},
		{"00010203", "1Ldp"},
		{"000000", "111"},
	}
	for _, tc := range cases {
		raw := mustDecodeHex(tc.hex)
		got := address.Base58Encode(raw)
		if got != tc.b58 {
			t.Errorf("Base58Encode(%s) = %q, want %q", tc.hex, got, tc.b58)
		}
		back, err := address.Base58Decode(tc.b58)
		if err != nil {
			t.Fatalf("Base58Decode(%q): %v", tc.b58, err)
		}
		if string(back) != string(raw) {
			t.Errorf("Base58Decode(%q) round-trip mismatch", tc.b58)
		}
	}
}

// TestDeriveAddress verifies the SHA512^9 derivation produces a 64-byte result.
// Full compatibility against a known first-stage address requires a reference
// secp256k1 key from the live chain — that test lives in the migration tool (D10).
func TestDeriveAddress(t *testing.T) {
	// Use a deterministic compressed secp256k1 pubkey (33 bytes).
	// This is the generator point G for secp256k1 in compressed form.
	gx := []byte{
		0x02,
		0x79, 0xbe, 0x66, 0x7e, 0xf9, 0xdc, 0xbb, 0xac,
		0x55, 0xa0, 0x62, 0x95, 0xce, 0x87, 0x0b, 0x07,
		0x02, 0x9b, 0xfc, 0xdb, 0x2d, 0xce, 0x28, 0xd9,
		0x59, 0xf2, 0x81, 0x5b, 0x16, 0xf8, 0x17, 0x98,
	}

	addr, err := address.DeriveAddress(gx)
	if err != nil {
		t.Fatalf("DeriveAddress: %v", err)
	}
	if len(addr) != 64 {
		t.Fatalf("expected 64-byte address, got %d", len(addr))
	}

	// The result should be a valid base58 string of ~87 characters.
	str := address.Base58Encode(addr)
	if len(str) < 70 || len(str) > 128 {
		t.Errorf("address string length %d out of expected range [70,128]: %s", len(str), str)
	}
	t.Logf("generator point address: %s (%d chars)", str, len(str))
}

func mustDecodeHex(h string) []byte {
	b := make([]byte, len(h)/2)
	for i := 0; i < len(h); i += 2 {
		var v byte
		for _, c := range h[i : i+2] {
			v <<= 4
			switch {
			case c >= '0' && c <= '9':
				v |= byte(c - '0')
			case c >= 'a' && c <= 'f':
				v |= byte(c-'a') + 10
			}
		}
		b[i/2] = v
	}
	return b
}
