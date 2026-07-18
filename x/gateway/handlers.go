package gateway

import (
	"context"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"fmt"
	"math/big"
	"net/http"
	"os"
	"runtime"
	"strconv"
	"strings"
	"time"

	"github.com/gorilla/mux"

	coretypes "github.com/cometbft/cometbft/rpc/core/types"

	"github.com/cosmos/cosmos-sdk/crypto/keys/secp256k1"
	sdk "github.com/cosmos/cosmos-sdk/types"
	stakingtypes "github.com/cosmos/cosmos-sdk/x/staking/types"

	appaddress "github.com/beepxtra/steroid-core4.0/app/address"
)

const requestTimeout = 10 * time.Second

func newCtx() (context.Context, context.CancelFunc) {
	return context.WithTimeout(context.Background(), requestTimeout)
}

func writeOK(w http.ResponseWriter, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	writeJSON(w, ok(data))
}

func writeErr(w http.ResponseWriter, status int, msg string) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	writeJSON(w, apiErr(msg))
}

// handleInfo serves GET /api
func handleInfo(_ *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		writeOK(w, map[string]string{
			"info":    "Steroid Core REST Gateway",
			"version": AppVersion,
		})
	}
}

// handleVersion serves GET /api/version
func handleVersion(_ *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		writeOK(w, AppVersion)
	}
}

// handleSanity serves GET /api/sanity
func handleSanity(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := newCtx()
		defer cancel()
		if _, err := nc.Status(ctx); err != nil {
			writeErr(w, http.StatusServiceUnavailable, "node unreachable: "+err.Error())
			return
		}
		writeOK(w, map[string]bool{"ok": true})
	}
}

// handleNodeInfo serves GET /api/node-info
func handleNodeInfo(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := newCtx()
		defer cancel()

		st, err := nc.Status(ctx)
		if err != nil {
			writeErr(w, http.StatusServiceUnavailable, err.Error())
			return
		}
		peers, _ := nc.PeerCount(ctx)
		mempool, _ := nc.MempoolSize(ctx)
		validators, _ := nc.Validators(ctx)
		hostname, _ := os.Hostname()

		writeOK(w, nodeInfoData{
			Hostname:       hostname,
			Version:        AppVersion,
			DBVersion:      "cosmos-sdk/v0.50",
			Mempool:        mempool,
			Masternodes:    len(validators),
			Peers:          peers,
			Height:         st.SyncInfo.LatestBlockHeight,
			PassivePeering: false,
			PublicKey:      hex.EncodeToString(st.ValidatorInfo.PubKey.Bytes()),
			Coin:           "BPC",
			System:         runtime.GOOS + "/" + runtime.GOARCH,
			WebServer:      "stereodd-gateway/" + AppVersion,
			DBEngine:       "iavl/cosmos-sdk",
		})
	}
}

// handleCurrentBlock serves GET /api/currentblock
func handleCurrentBlock(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := newCtx()
		defer cancel()

		blk, err := nc.LatestBlock(ctx)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, err.Error())
			return
		}
		writeOK(w, toBlockData(blk))
	}
}

// handleGetBlock serves GET /api/getblock/{height}
func handleGetBlock(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		h, err := parseHeightVar(mux.Vars(r), r)
		if err != nil {
			writeErr(w, http.StatusBadRequest, err.Error())
			return
		}
		ctx, cancel := newCtx()
		defer cancel()

		blk, err := nc.BlockByHeight(ctx, h)
		if err != nil {
			writeErr(w, http.StatusNotFound, "block not found: "+err.Error())
			return
		}
		writeOK(w, toBlockData(blk))
	}
}

// handleGetBlockTransactions serves GET /api/getblocktransactions/{height}
func handleGetBlockTransactions(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		h, err := parseHeightVar(mux.Vars(r), r)
		if err != nil {
			writeErr(w, http.StatusBadRequest, err.Error())
			return
		}
		ctx, cancel := newCtx()
		defer cancel()

		blk, err := nc.BlockByHeight(ctx, h)
		if err != nil {
			writeErr(w, http.StatusNotFound, "block not found: "+err.Error())
			return
		}
		st, _ := nc.Status(ctx)
		currentHeight := int64(0)
		if st != nil {
			currentHeight = st.SyncInfo.LatestBlockHeight
		}

		txs, err := nc.TxsInBlock(ctx, h)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, err.Error())
			return
		}

		blkHash := blockHash(blk)
		blkTime := blk.Block.Header.Time.Unix()
		data := make([]txData, 0, len(txs))
		for _, tx := range txs {
			data = append(data, parseTx(tx, blkHash, blkTime, currentHeight))
		}
		writeOK(w, data)
	}
}

// handleGetTransaction serves GET /api/gettransaction/{id}
func handleGetTransaction(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		vars := mux.Vars(r)
		id := vars["id"]
		if id == "" {
			id = r.URL.Query().Get("id")
		}
		if id == "" {
			writeErr(w, http.StatusBadRequest, "missing tx id")
			return
		}
		ctx, cancel := newCtx()
		defer cancel()

		tx, err := nc.Tx(ctx, id)
		if err != nil {
			writeErr(w, http.StatusNotFound, "transaction not found: "+err.Error())
			return
		}

		blk, _ := nc.BlockByHeight(ctx, tx.Height)
		st, _ := nc.Status(ctx)
		currentHeight := int64(0)
		if st != nil {
			currentHeight = st.SyncInfo.LatestBlockHeight
		}

		blkHash, blkTime := "", int64(0)
		if blk != nil {
			blkHash = blockHash(blk)
			blkTime = blk.Block.Header.Time.Unix()
		}
		writeOK(w, parseTx(tx, blkHash, blkTime, currentHeight))
	}
}

// handleGetTransactions serves GET /api/gettransactions/{address}
func handleGetTransactions(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		vars := mux.Vars(r)
		addr := vars["address"]
		if addr == "" {
			addr = r.URL.Query().Get("account")
		}
		if addr == "" {
			writeErr(w, http.StatusBadRequest, "missing address")
			return
		}

		page, perPage := 1, 50
		if v := r.URL.Query().Get("page"); v != "" {
			if n, e := strconv.Atoi(v); e == nil && n > 0 {
				page = n
			}
		}
		if v := r.URL.Query().Get("limit"); v != "" {
			if n, e := strconv.Atoi(v); e == nil && n > 0 && n <= 200 {
				perPage = n
			}
		}

		ctx, cancel := newCtx()
		defer cancel()

		txs, total, err := nc.TxsByAddress(ctx, addr, page, perPage)
		if err != nil {
			writeErr(w, http.StatusBadRequest, err.Error())
			return
		}

		st, _ := nc.Status(ctx)
		currentHeight := int64(0)
		if st != nil {
			currentHeight = st.SyncInfo.LatestBlockHeight
		}

		data := make([]txData, 0, len(txs))
		for _, tx := range txs {
			blk, _ := nc.BlockByHeight(ctx, tx.Height)
			blkHash, blkTime := "", int64(0)
			if blk != nil {
				blkHash = blockHash(blk)
				blkTime = blk.Block.Header.Time.Unix()
			}
			data = append(data, parseTx(tx, blkHash, blkTime, currentHeight))
		}

		writeOK(w, map[string]interface{}{
			"total":        total,
			"transactions": data,
		})
	}
}

// handleGetBalance serves GET /api/getbalance/{address}
func handleGetBalance(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		vars := mux.Vars(r)
		addr := vars["address"]
		if addr == "" {
			addr = r.URL.Query().Get("account")
		}
		if addr == "" {
			addr = r.URL.Query().Get("public_key")
		}
		if addr == "" {
			writeErr(w, http.StatusBadRequest, "missing address or public_key")
			return
		}
		ctx, cancel := newCtx()
		defer cancel()

		_, bpc, err := nc.Balance(ctx, addr)
		if err != nil {
			writeErr(w, http.StatusBadRequest, err.Error())
			return
		}
		writeOK(w, bpc)
	}
}

// handleGetPendingBalance serves GET /api/getpendingbalance
// Returns confirmed balance (mempool deduction is approximate; D4 scope).
func handleGetPendingBalance(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		addr := r.URL.Query().Get("account")
		if addr == "" {
			addr = r.URL.Query().Get("public_key")
		}
		if addr == "" {
			writeErr(w, http.StatusBadRequest, "missing account or public_key")
			return
		}
		ctx, cancel := newCtx()
		defer cancel()

		_, bpc, err := nc.Balance(ctx, addr)
		if err != nil {
			writeErr(w, http.StatusBadRequest, err.Error())
			return
		}
		writeOK(w, bpc)
	}
}

// handleGetAddress serves GET /api/getaddress/{public_key}
func handleGetAddress(_ *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		vars := mux.Vars(r)
		pk := vars["public_key"]
		if pk == "" {
			pk = r.URL.Query().Get("public_key")
		}
		if pk == "" {
			writeErr(w, http.StatusBadRequest, "missing public_key")
			return
		}

		pkBytes, err := decodePubKeyString(pk)
		if err != nil {
			writeErr(w, http.StatusBadRequest, err.Error())
			return
		}
		addrBytes, err := appaddress.DeriveAddress(pkBytes)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, err.Error())
			return
		}
		writeOK(w, appaddress.Base58Encode(addrBytes))
	}
}

// handleBase58 serves GET /api/base58/{string}
func handleBase58(_ *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		vars := mux.Vars(r)
		s := vars["string"]
		if s == "" {
			s = r.URL.Query().Get("data")
		}
		writeOK(w, appaddress.Base58Encode([]byte(s)))
	}
}

// handleCheckAddress serves GET /api/checkaddress
func handleCheckAddress(_ *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		addr := r.URL.Query().Get("address")
		if addr == "" {
			addr = r.URL.Query().Get("account")
		}
		_, err := appaddress.Codec{}.StringToBytes(addr)
		writeOK(w, map[string]bool{"valid": err == nil})
	}
}

// handleCheckSignature serves GET /api/checksignature/{public_key}/{signature}/{data}
func handleCheckSignature(_ *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		vars := mux.Vars(r)
		pkStr := vars["public_key"]
		sigStr := vars["signature"]
		dataStr := vars["data"]

		pkBytes, err := decodePubKeyString(pkStr)
		if err != nil {
			writeErr(w, http.StatusBadRequest, "invalid public_key: "+err.Error())
			return
		}
		sigBytes, err := decodeB64OrHex(sigStr)
		if err != nil {
			writeErr(w, http.StatusBadRequest, "invalid signature encoding")
			return
		}

		pubKey := &secp256k1.PubKey{Key: pkBytes}
		hash := sha256.Sum256([]byte(dataStr))
		writeOK(w, map[string]bool{"valid": pubKey.VerifySignature(hash[:], sigBytes)})
	}
}

// handleGenerateWallet serves GET /api/generate_wallet
func handleGenerateWallet(_ *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		privKey := secp256k1.GenPrivKey()
		pubKey := privKey.PubKey().(*secp256k1.PubKey)

		addrBytes, err := appaddress.DeriveAddress(pubKey.Key)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, err.Error())
			return
		}
		writeOK(w, walletData{
			Address:    appaddress.Base58Encode(addrBytes),
			PublicKey:  base64.StdEncoding.EncodeToString(pubKey.Key),
			PrivateKey: base64.StdEncoding.EncodeToString(privKey.Key),
		})
	}
}

// handleGetPublicKey serves GET /api/getpublickey
// The public key is only stored on-chain after the account's first signed tx.
func handleGetPublicKey(_ *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		writeErr(w, http.StatusNotImplemented,
			"public key lookup requires the account to have submitted at least one tx; "+
				"derive the address from a public key via /api/getaddress/{public_key} instead")
	}
}

// handleMasternodes serves GET /api/masternodes
func handleMasternodes(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := newCtx()
		defer cancel()

		validators, err := nc.Validators(ctx)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, err.Error())
			return
		}

		codec := appaddress.Codec{}
		result := make([]validatorData, 0, len(validators))
		for _, v := range validators {
			opAddr, _ := sdk.ValAddressFromBech32(v.OperatorAddress)
			addrStr, _ := codec.BytesToString(opAddr)

			pkHex := ""
			if pk, err := v.ConsPubKey(); err == nil {
				pkHex = hex.EncodeToString(pk.Bytes())
			}

			result = append(result, validatorData{
				Address:     addrStr,
				PubKey:      pkHex,
				VotingPower: v.ConsensusPower(sdk.DefaultPowerReduction),
				Moniker:     v.Description.Moniker,
				Status:      validatorStatus(v.Status),
				Jailed:      v.Jailed,
				Tokens:      ubpcToBPC(v.Tokens.Int64()),
			})
		}
		writeOK(w, result)
	}
}

// handleMempoolSize serves GET /api/mempoolsize
func handleMempoolSize(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := newCtx()
		defer cancel()

		n, err := nc.MempoolSize(ctx)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, err.Error())
			return
		}
		writeOK(w, map[string]int{"size": n})
	}
}

// handleTotalSupply serves GET /api/totalsupply
func handleTotalSupply(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := newCtx()
		defer cancel()

		supply, err := nc.TotalSupply(ctx)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, err.Error())
			return
		}
		writeOK(w, map[string]string{"total_supply": supply})
	}
}

// handleCircSupply serves GET /api/circsupply
// Approximation: total supply minus bonded (staked) tokens.
func handleCircSupply(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := newCtx()
		defer cancel()

		totalStr, err := nc.TotalSupply(ctx)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, err.Error())
			return
		}
		validators, err := nc.Validators(ctx)
		if err != nil {
			writeErr(w, http.StatusInternalServerError, err.Error())
			return
		}

		bonded := new(big.Int)
		for _, v := range validators {
			bonded.Add(bonded, v.Tokens.BigInt())
		}

		total := parseBPC(totalStr)
		circ := new(big.Int).Sub(total, bonded)
		if circ.Sign() < 0 {
			circ.SetInt64(0)
		}
		writeOK(w, map[string]string{"circulating_supply": ubpcToBPC(circ.Int64())})
	}
}

// handleRandomNumber serves GET /api/randomnumber
func handleRandomNumber(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		q := r.URL.Query()
		height, _ := strconv.ParseInt(q.Get("height"), 10, 64)
		minVal, _ := strconv.ParseInt(q.Get("min"), 10, 64)
		maxVal, _ := strconv.ParseInt(q.Get("max"), 10, 64)
		seed := q.Get("seed")

		if height < 1 || maxVal <= minVal {
			writeErr(w, http.StatusBadRequest, "required: height (>0), min, max (max > min)")
			return
		}

		ctx, cancel := newCtx()
		defer cancel()

		blk, err := nc.BlockByHeight(ctx, height)
		if err != nil {
			writeErr(w, http.StatusNotFound, fmt.Sprintf("block %d not found (may be a future block)", height))
			return
		}

		h := sha256.New()
		h.Write(blk.BlockID.Hash)
		h.Write([]byte(seed))
		digest := h.Sum(nil)

		n := new(big.Int).SetBytes(digest)
		rangeSize := new(big.Int).SetInt64(maxVal - minVal + 1)
		result := new(big.Int).Mod(n, rangeSize).Int64() + minVal

		writeOK(w, map[string]int64{"number": result})
	}
}

// handleSend serves GET /api/send (and POST)
// Accepts tx_bytes (base64-encoded raw Cosmos SDK tx). Legacy PHP tx format is rejected.
func handleSend(nc *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		_ = r.ParseForm()

		txBytesB64 := r.FormValue("tx_bytes")
		if txBytesB64 == "" {
			txBytesB64 = r.URL.Query().Get("tx_bytes")
		}

		if txBytesB64 != "" {
			txBytes, err := base64.StdEncoding.DecodeString(txBytesB64)
			if err != nil {
				writeErr(w, http.StatusBadRequest, "tx_bytes must be base64 encoded")
				return
			}
			ctx, cancel := newCtx()
			defer cancel()
			hash, err := nc.BroadcastTxGRPC(ctx, txBytes)
			if err != nil {
				writeErr(w, http.StatusBadRequest, err.Error())
				return
			}
			writeOK(w, hash)
			return
		}

		if r.FormValue("dst") != "" || r.URL.Query().Get("dst") != "" {
			writeErr(w, http.StatusNotImplemented,
				"legacy transaction format is not supported; "+
					"update your wallet to submit a Cosmos SDK tx via the tx_bytes parameter")
			return
		}

		writeErr(w, http.StatusBadRequest, "missing tx_bytes parameter")
	}
}

// handleD5Stub serves all D5-dependent endpoints with a clear not-implemented response.
func handleD5Stub(_ *NodeClient) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusNotImplemented)
		writeJSON(w, notImpl("aliases and assets"))
	}
}

// --- shared helpers ---

func toBlockData(b *coretypes.ResultBlock) blockData {
	codec := appaddress.Codec{}
	proposerStr, _ := codec.BytesToString(b.Block.Header.ProposerAddress)

	sig := ""
	if sigs := b.Block.LastCommit.Signatures; len(sigs) > 0 {
		sig = base64.StdEncoding.EncodeToString(sigs[0].Signature)
	}

	return blockData{
		ID:         blockHash(b),
		Generator:  proposerStr,
		Height:     b.Block.Header.Height,
		Date:       b.Block.Header.Time.Unix(),
		Nonce:      "0",
		Signature:  sig,
		Difficulty: 0,
		Argon:      "",
		TxCount:    len(b.Block.Data.Txs),
	}
}

// parseTx maps a CometBFT ResultTx to the legacy txData shape using tx events.
func parseTx(tx *coretypes.ResultTx, blkHash string, blkTime, currentHeight int64) txData {
	d := txData{
		ID:            strings.ToUpper(hex.EncodeToString(tx.Hash)),
		Block:         blkHash,
		Height:        tx.Height,
		Confirmations: currentHeight - tx.Height,
		Date:          blkTime,
		Type:          "debit",
		Version:       1,
	}
	for _, evt := range tx.TxResult.Events {
		for _, attr := range evt.Attributes {
			key := evt.Type + "." + attr.Key
			val := attr.Value
			switch key {
			case "transfer.sender":
				d.Src = val
			case "transfer.recipient":
				d.Dst = val
			case "transfer.amount":
				d.Val = parseEventAmount(val)
			case "tx.fee":
				d.Fee = parseEventAmount(val)
			}
		}
	}
	return d
}

func parseEventAmount(s string) string {
	s = strings.TrimSuffix(s, "ubpc")
	n, err := strconv.ParseInt(s, 10, 64)
	if err != nil {
		return s
	}
	return ubpcToBPC(n)
}

func parseBPC(s string) *big.Int {
	parts := strings.SplitN(s, ".", 2)
	whole := new(big.Int)
	whole.SetString(parts[0], 10)
	result := new(big.Int).Mul(whole, big.NewInt(ubpcPerBPC))
	if len(parts) == 2 {
		fracStr := parts[1]
		for len(fracStr) < 8 {
			fracStr += "0"
		}
		frac := new(big.Int)
		frac.SetString(fracStr[:8], 10)
		result.Add(result, frac)
	}
	return result
}

func validatorStatus(s stakingtypes.BondStatus) string {
	switch s {
	case stakingtypes.Bonded:
		return "active"
	case stakingtypes.Unbonding:
		return "unbonding"
	case stakingtypes.Unbonded:
		return "inactive"
	default:
		return "unknown"
	}
}

// parseHeightVar extracts the "height" path variable, falling back to query param.
func parseHeightVar(vars map[string]string, r *http.Request) (int64, error) {
	s := vars["height"]
	if s == "" {
		s = r.URL.Query().Get("height")
	}
	h, err := strconv.ParseInt(s, 10, 64)
	if err != nil || h < 1 {
		return 0, fmt.Errorf("invalid block height %q", s)
	}
	return h, nil
}

// decodePubKeyString decodes a secp256k1 public key from base64 or hex,
// then ensures it is in 33-byte compressed form.
func decodePubKeyString(s string) ([]byte, error) {
	b, err := decodeB64OrHex(s)
	if err != nil {
		return nil, fmt.Errorf("invalid public key encoding: %w", err)
	}
	switch len(b) {
	case 33:
		return b, nil
	case 65:
		return compressPublicKey(b)
	default:
		return nil, fmt.Errorf("unexpected public key length %d (want 33 or 65 bytes)", len(b))
	}
}

func decodeB64OrHex(s string) ([]byte, error) {
	if b, err := base64.StdEncoding.DecodeString(s); err == nil {
		return b, nil
	}
	return hex.DecodeString(s)
}
