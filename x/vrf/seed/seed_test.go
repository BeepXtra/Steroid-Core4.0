package seed

import (
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"testing"
)

// Expected values below were computed independently via `sha256sum`/`openssl
// dgst -sha256` (and cross-checked with Python's hashlib), not derived from
// this package's own code, so they serve as an external oracle for Decision 3.
func hexBytes(t *testing.T, s string) []byte {
	t.Helper()
	b, err := hex.DecodeString(s)
	if err != nil {
		t.Fatalf("invalid hex fixture: %v", err)
	}
	return b
}

func TestComputeTxAccumulator_EmptyBlock(t *testing.T) {
	// Decision 3's explicit empty-block rule: tx_accumulator_hash = SHA256([]byte{}).
	want := hexBytes(t, "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855")
	got := ComputeTxAccumulator(nil)
	if !bytes.Equal(got, want) {
		t.Fatalf("empty-block accumulator mismatch:\n got  %x\n want %x", got, want)
	}
}

func TestComputeTxAccumulator_OneTx(t *testing.T) {
	tx1 := sha256.Sum256([]byte("tx1"))
	want := hexBytes(t, "c4f3b3f4915a1e68a39ff03942df6fc09e8d4378be4483177bedb748f173de51")
	got := ComputeTxAccumulator([][]byte{tx1[:]})
	if !bytes.Equal(got, want) {
		t.Fatalf("one-tx accumulator mismatch:\n got  %x\n want %x", got, want)
	}
}

func TestComputeTxAccumulator_TwoTx(t *testing.T) {
	tx1 := sha256.Sum256([]byte("tx1"))
	tx2 := sha256.Sum256([]byte("tx2"))
	want := hexBytes(t, "1b14a43124801fc3ecf27e45b210867ce62fdc60e57bd445f7e1b64331ec5da4")
	got := ComputeTxAccumulator([][]byte{tx1[:], tx2[:]})
	if !bytes.Equal(got, want) {
		t.Fatalf("two-tx accumulator mismatch:\n got  %x\n want %x", got, want)
	}
}

func TestComputeTxAccumulator_OrderSensitive(t *testing.T) {
	tx1 := sha256.Sum256([]byte("tx1"))
	tx2 := sha256.Sum256([]byte("tx2"))
	forward := ComputeTxAccumulator([][]byte{tx1[:], tx2[:]})
	reversed := ComputeTxAccumulator([][]byte{tx2[:], tx1[:]})
	if bytes.Equal(forward, reversed) {
		t.Fatal("accumulator must be sensitive to tx order, got identical results for reversed tx order")
	}
}

func TestComputeSeed_KnownAnswer(t *testing.T) {
	prev := bytes.Repeat([]byte{0xAA}, 32)
	accEmpty := ComputeTxAccumulator(nil)

	want := hexBytes(t, "2ca92d1625172e15a516863e7143c09a40f6ca96833e03f9fb96870ad3795b66")
	got := ComputeSeed(prev, 100, accEmpty)
	if !bytes.Equal(got, want) {
		t.Fatalf("seed mismatch:\n got  %x\n want %x", got, want)
	}
}

func TestComputeSeed_HeightPreventsReplay(t *testing.T) {
	prev := bytes.Repeat([]byte{0xAA}, 32)
	accEmpty := ComputeTxAccumulator(nil)

	seed100 := ComputeSeed(prev, 100, accEmpty)
	seed101 := ComputeSeed(prev, 101, accEmpty)
	if bytes.Equal(seed100, seed101) {
		t.Fatal("seed must differ across heights (replay prevention), got identical seeds")
	}

	want101 := hexBytes(t, "896710edc66797454ec3d2c044d193c667ae3d81118ae7fac186a8bcacdd7d32")
	if !bytes.Equal(seed101, want101) {
		t.Fatalf("height=101 seed mismatch:\n got  %x\n want %x", seed101, want101)
	}
}

func TestComputeSeed_DependsOnPrevVRFOutput(t *testing.T) {
	accEmpty := ComputeTxAccumulator(nil)
	prevA := bytes.Repeat([]byte{0xAA}, 32)
	prevB := bytes.Repeat([]byte{0xBB}, 32)

	seedA := ComputeSeed(prevA, 100, accEmpty)
	seedB := ComputeSeed(prevB, 100, accEmpty)
	if bytes.Equal(seedA, seedB) {
		t.Fatal("seed must depend on prev_vrf_output, got identical seeds for different prev outputs")
	}
}

func TestComputeSeed_Deterministic(t *testing.T) {
	prev := bytes.Repeat([]byte{0xAA}, 32)
	acc := ComputeTxAccumulator([][]byte{sha256.New().Sum([]byte("tx1"))})

	s1 := ComputeSeed(prev, 42, acc)
	s2 := ComputeSeed(prev, 42, acc)
	if !bytes.Equal(s1, s2) {
		t.Fatal("ComputeSeed must be a pure function of its inputs")
	}
}
