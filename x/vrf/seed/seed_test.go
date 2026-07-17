package seed

import (
	"bytes"
	"encoding/hex"
	"testing"
)

// Expected values below were computed independently via Python's hashlib,
// not derived from this package's own code, so they serve as an external
// oracle for the seed construction.
func hexBytes(t *testing.T, s string) []byte {
	t.Helper()
	b, err := hex.DecodeString(s)
	if err != nil {
		t.Fatalf("invalid hex fixture: %v", err)
	}
	return b
}

func TestComputeSeed_KnownAnswer(t *testing.T) {
	prev := bytes.Repeat([]byte{0xAA}, 32)
	want := hexBytes(t, "665f8c3a3b1a90089c37ab122c90b46f746103cd7843892c35f059c7b7885432")
	got := ComputeSeed(prev, 100)
	if !bytes.Equal(got, want) {
		t.Fatalf("seed mismatch:\n got  %x\n want %x", got, want)
	}
}

func TestComputeSeed_HeightPreventsReplay(t *testing.T) {
	prev := bytes.Repeat([]byte{0xAA}, 32)

	seed100 := ComputeSeed(prev, 100)
	seed101 := ComputeSeed(prev, 101)
	if bytes.Equal(seed100, seed101) {
		t.Fatal("seed must differ across heights (replay prevention), got identical seeds")
	}

	want101 := hexBytes(t, "410369a6d81262f3edb152b6da04ffd206f0ee359fb3b259ddbed32d908f5c2a")
	if !bytes.Equal(seed101, want101) {
		t.Fatalf("height=101 seed mismatch:\n got  %x\n want %x", seed101, want101)
	}
}

func TestComputeSeed_DependsOnPrevVRFOutput(t *testing.T) {
	prevA := bytes.Repeat([]byte{0xAA}, 32)
	prevB := bytes.Repeat([]byte{0xBB}, 32)

	seedA := ComputeSeed(prevA, 100)
	seedB := ComputeSeed(prevB, 100)
	if bytes.Equal(seedA, seedB) {
		t.Fatal("seed must depend on prev_vrf_output, got identical seeds for different prev outputs")
	}
}

func TestComputeSeed_Deterministic(t *testing.T) {
	prev := bytes.Repeat([]byte{0xAA}, 32)

	s1 := ComputeSeed(prev, 42)
	s2 := ComputeSeed(prev, 42)
	if !bytes.Equal(s1, s2) {
		t.Fatal("ComputeSeed must be a pure function of its inputs")
	}
}
