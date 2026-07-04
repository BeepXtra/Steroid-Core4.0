// Package seed implements the D1a Decision 3 VRF seed construction:
//
//	seed = SHA256(prev_vrf_output || block_height || tx_accumulator_hash)
//
// where tx_accumulator_hash is a running SHA-256 hash over the previous
// block's transaction hashes, computed in transaction order. It is a pure,
// standalone function with no dependency on the SDK or keeper state so it
// can be unit-tested in isolation from consensus wiring.
package seed

import (
	"crypto/sha256"
	"encoding/binary"
)

// ComputeTxAccumulator folds a block's transaction hashes (in tx order) into
// a single running SHA-256 accumulator. For a block with zero transactions,
// the loop below never executes and the result is exactly SHA256of(nil) —
// the empty-block case mandated by Decision 3, so it needs no special case.
func ComputeTxAccumulator(txHashes [][]byte) []byte {
	acc := sha256.Sum256(nil)
	for _, h := range txHashes {
		buf := make([]byte, 0, len(acc)+len(h))
		buf = append(buf, acc[:]...)
		buf = append(buf, h...)
		acc = sha256.Sum256(buf)
	}
	return acc[:]
}

// ComputeSeed computes the VRF seed for a block at the given height, per
// Decision 3. prevVRFOutput is the winning proposer's VRF output from the
// previous block (empty for the first block after genesis); height is the
// current block height; txAccumulatorHash is the previous block's
// tx-accumulator (see ComputeTxAccumulator), which must already reflect the
// empty-block rule if that block had no transactions.
func ComputeSeed(prevVRFOutput []byte, height int64, txAccumulatorHash []byte) []byte {
	var heightBytes [8]byte
	binary.BigEndian.PutUint64(heightBytes[:], uint64(height))

	buf := make([]byte, 0, len(prevVRFOutput)+len(heightBytes)+len(txAccumulatorHash))
	buf = append(buf, prevVRFOutput...)
	buf = append(buf, heightBytes[:]...)
	buf = append(buf, txAccumulatorHash...)

	sum := sha256.Sum256(buf)
	return sum[:]
}
