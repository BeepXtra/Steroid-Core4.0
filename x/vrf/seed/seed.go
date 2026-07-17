// Package seed implements the D1a VRF seed construction for v2:
//
//	seed = SHA256(prev_vrf_output || block_height)
//
// Entropy source: validator VRF outputs only. User transactions are
// permanently excluded — tx-fed entropy is grindable by the block proposer.
// This is a pure, standalone function with no dependency on the SDK or keeper
// state so it can be unit-tested in isolation from consensus wiring.
package seed

import (
	"crypto/sha256"
	"encoding/binary"
)

// ComputeSeed computes the VRF seed for a block at the given height.
// prevVRFOutput is the winning proposer's VRF output from the previous block
// (empty for the first block after genesis). height is the current block
// height. User transaction hashes are not included — see package doc.
func ComputeSeed(prevVRFOutput []byte, height int64) []byte {
	var heightBytes [8]byte
	binary.BigEndian.PutUint64(heightBytes[:], uint64(height))

	buf := make([]byte, 0, len(prevVRFOutput)+len(heightBytes))
	buf = append(buf, prevVRFOutput...)
	buf = append(buf, heightBytes[:]...)

	sum := sha256.Sum256(buf)
	return sum[:]
}
