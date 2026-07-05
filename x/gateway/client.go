package gateway

import (
	"context"
	"encoding/hex"
	"fmt"
	"math/big"
	"strings"

	rpcclient "github.com/cometbft/cometbft/rpc/client/http"
	coretypes "github.com/cometbft/cometbft/rpc/core/types"
	"google.golang.org/grpc"
	"google.golang.org/grpc/credentials/insecure"

	"github.com/cosmos/cosmos-sdk/codec"
	codectypes "github.com/cosmos/cosmos-sdk/codec/types"
	cryptocodec "github.com/cosmos/cosmos-sdk/crypto/codec"
	sdk "github.com/cosmos/cosmos-sdk/types"
	"github.com/cosmos/cosmos-sdk/types/query"
	bankquery "github.com/cosmos/cosmos-sdk/x/bank/types"
	stakingquery "github.com/cosmos/cosmos-sdk/x/staking/types"
	txtypes "github.com/cosmos/cosmos-sdk/types/tx"

	steroidaddress "github.com/beepxtra/steroid-core4.0/app/address"
)

// NodeClient wraps a CometBFT RPC client and gRPC connection so all
// gateway handlers share a single set of connections.
type NodeClient struct {
	rpc   *rpcclient.HTTP
	grpc  *grpc.ClientConn
	cdc   codec.Codec
}

// NewNodeClient dials nodeAddr (e.g. "tcp://localhost:26657") and grpcAddr
// (e.g. "localhost:9090") and returns a ready NodeClient.
func NewNodeClient(nodeAddr, grpcAddr string) (*NodeClient, error) {
	rpc, err := rpcclient.New(nodeAddr, "/websocket")
	if err != nil {
		return nil, fmt.Errorf("dial CometBFT RPC %s: %w", nodeAddr, err)
	}

	grpcConn, err := grpc.Dial(
		grpcAddr,
		grpc.WithTransportCredentials(insecure.NewCredentials()),
	)
	if err != nil {
		return nil, fmt.Errorf("dial gRPC %s: %w", grpcAddr, err)
	}

	ir := codectypes.NewInterfaceRegistry()
	cryptocodec.RegisterInterfaces(ir)
	cdc := codec.NewProtoCodec(ir)

	return &NodeClient{rpc: rpc, grpc: grpcConn, cdc: cdc}, nil
}

func (c *NodeClient) Close() { _ = c.grpc.Close() }

// LatestBlock returns the latest committed block.
func (c *NodeClient) LatestBlock(ctx context.Context) (*coretypes.ResultBlock, error) {
	return c.rpc.Block(ctx, nil)
}

// BlockByHeight returns the block at a given height.
func (c *NodeClient) BlockByHeight(ctx context.Context, height int64) (*coretypes.ResultBlock, error) {
	return c.rpc.Block(ctx, &height)
}

// Status returns the node status.
func (c *NodeClient) Status(ctx context.Context) (*coretypes.ResultStatus, error) {
	return c.rpc.Status(ctx)
}

// MempoolSize returns the number of txs in the unconfirmed tx pool.
func (c *NodeClient) MempoolSize(ctx context.Context) (int, error) {
	res, err := c.rpc.NumUnconfirmedTxs(ctx)
	if err != nil {
		return 0, err
	}
	return res.Count, nil
}

// Tx fetches a transaction by its hex hash.
func (c *NodeClient) Tx(ctx context.Context, hashHex string) (*coretypes.ResultTx, error) {
	hashHex = strings.TrimPrefix(strings.ToUpper(hashHex), "0X")
	b, err := hex.DecodeString(hashHex)
	if err != nil {
		return nil, fmt.Errorf("invalid tx hash: %w", err)
	}
	return c.rpc.Tx(ctx, b, false)
}

// TxsByAddress returns committed transactions that involve addr (base58 or bech32).
// It searches both as sender and receiver.
func (c *NodeClient) TxsByAddress(ctx context.Context, addr string, page, perPage int) ([]*coretypes.ResultTx, int64, error) {
	bech32, err := addrToBech32(addr)
	if err != nil {
		return nil, 0, err
	}

	// Search as sender and receiver, merge results.
	var all []*coretypes.ResultTx
	seen := map[string]bool{}
	var totalCount int64

	queries := []string{
		fmt.Sprintf("transfer.sender='%s'", bech32),
		fmt.Sprintf("transfer.recipient='%s'", bech32),
	}
	for _, q := range queries {
		res, err := c.rpc.TxSearch(ctx, q, false, &page, &perPage, "desc")
		if err != nil {
			continue
		}
		totalCount += int64(res.TotalCount)
		for _, tx := range res.Txs {
			key := hex.EncodeToString(tx.Hash)
			if !seen[key] {
				seen[key] = true
				all = append(all, tx)
			}
		}
	}
	return all, totalCount, nil
}

// TxsInBlock returns all txs included in the block at height.
func (c *NodeClient) TxsInBlock(ctx context.Context, height int64) ([]*coretypes.ResultTx, error) {
	q := fmt.Sprintf("tx.height=%d", height)
	perPage := 100
	page := 1
	res, err := c.rpc.TxSearch(ctx, q, false, &page, &perPage, "asc")
	if err != nil {
		return nil, err
	}
	return res.Txs, nil
}

// BroadcastTx broadcasts a raw tx (protobuf bytes) and returns the tx hash hex.
func (c *NodeClient) BroadcastTx(ctx context.Context, txBytes []byte) (string, error) {
	res, err := c.rpc.BroadcastTxSync(ctx, txBytes)
	if err != nil {
		return "", err
	}
	if res.Code != 0 {
		return "", fmt.Errorf("tx rejected (code %d): %s", res.Code, res.Log)
	}
	return strings.ToUpper(hex.EncodeToString(res.Hash)), nil
}

// Balance returns the ubpc balance for addr (base58 or bech32) and the
// BPC-formatted string (8 decimal places).
func (c *NodeClient) Balance(ctx context.Context, addr string) (ubpc int64, bpc string, err error) {
	bech32, e := addrToBech32(addr)
	if e != nil {
		return 0, "", e
	}
	cl := bankquery.NewQueryClient(c.grpc)
	res, e := cl.Balance(ctx, &bankquery.QueryBalanceRequest{
		Address: bech32,
		Denom:   "ubpc",
	})
	if e != nil {
		return 0, "", e
	}
	if res.Balance == nil {
		return 0, "0.00000000", nil
	}
	amt := res.Balance.Amount.Int64()
	return amt, ubpcToBPC(amt), nil
}

// TotalSupply returns the total ubpc supply and its BPC string.
func (c *NodeClient) TotalSupply(ctx context.Context) (string, error) {
	cl := bankquery.NewQueryClient(c.grpc)
	res, err := cl.TotalSupply(ctx, &bankquery.QueryTotalSupplyRequest{
		Pagination: &query.PageRequest{Limit: 10},
	})
	if err != nil {
		return "", err
	}
	for _, coin := range res.Supply {
		if coin.Denom == "ubpc" {
			return ubpcToBPC(coin.Amount.Int64()), nil
		}
	}
	return "0.00000000", nil
}

// Validators returns all bonded validators.
func (c *NodeClient) Validators(ctx context.Context) ([]stakingquery.Validator, error) {
	cl := stakingquery.NewQueryClient(c.grpc)
	res, err := cl.Validators(ctx, &stakingquery.QueryValidatorsRequest{
		Status:     stakingquery.BondStatusBonded,
		Pagination: &query.PageRequest{Limit: 200},
	})
	if err != nil {
		return nil, err
	}
	return res.Validators, nil
}

// ServiceTx sends a tx via the gRPC tx service.
func (c *NodeClient) BroadcastTxGRPC(ctx context.Context, txBytes []byte) (string, error) {
	cl := txtypes.NewServiceClient(c.grpc)
	res, err := cl.BroadcastTx(ctx, &txtypes.BroadcastTxRequest{
		TxBytes: txBytes,
		Mode:    txtypes.BroadcastMode_BROADCAST_MODE_SYNC,
	})
	if err != nil {
		return "", err
	}
	if res.TxResponse.Code != 0 {
		return "", fmt.Errorf("tx rejected (code %d): %s", res.TxResponse.Code, res.TxResponse.RawLog)
	}
	return res.TxResponse.TxHash, nil
}

// NetInfo returns network peer count.
func (c *NodeClient) PeerCount(ctx context.Context) (int, error) {
	res, err := c.rpc.NetInfo(ctx)
	if err != nil {
		return 0, err
	}
	return res.NPeers, nil
}

// --- helpers ---

// addrToBech32 converts a base58 or bech32 address string to bech32.
// It's needed because CometBFT event queries use bech32.
func addrToBech32(addr string) (string, error) {
	codec := steroidaddress.Codec{}
	bz, err := codec.StringToBytes(addr)
	if err != nil {
		return "", fmt.Errorf("invalid address %q: %w", addr, err)
	}
	return sdk.AccAddress(bz).String(), nil
}

// ubpcToBPC converts an integer ubpc amount to a BPC decimal string with 8 decimal places.
func ubpcToBPC(ubpc int64) string {
	n := new(big.Int).SetInt64(ubpc)
	prec := new(big.Int).SetInt64(ubpcPerBPC)
	whole := new(big.Int).Div(n, prec)
	frac := new(big.Int).Mod(n, prec)
	return fmt.Sprintf("%s.%08d", whole.String(), frac.Int64())
}

// blockHash returns the hex-encoded SHA256 block hash from a result block.
func blockHash(b *coretypes.ResultBlock) string {
	return strings.ToUpper(hex.EncodeToString(b.BlockID.Hash))
}
