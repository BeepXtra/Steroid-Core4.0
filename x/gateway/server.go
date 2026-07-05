package gateway

import (
	"context"
	"fmt"
	"net/http"
	"time"

	"github.com/gorilla/mux"
)

// StartGateway creates a gorilla/mux router, registers all /api/* routes,
// and serves on listenAddr until ctx is cancelled.
func StartGateway(ctx context.Context, listenAddr, nodeAddr, grpcAddr string) error {
	nc, err := NewNodeClient(nodeAddr, grpcAddr)
	if err != nil {
		return fmt.Errorf("gateway: %w", err)
	}
	defer nc.Close()

	r := mux.NewRouter()
	registerRoutes(r, nc)

	srv := &http.Server{
		Addr:         listenAddr,
		Handler:      r,
		ReadTimeout:  30 * time.Second,
		WriteTimeout: 30 * time.Second,
		IdleTimeout:  120 * time.Second,
	}

	errCh := make(chan error, 1)
	go func() { errCh <- srv.ListenAndServe() }()

	select {
	case <-ctx.Done():
		shutCtx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer cancel()
		return srv.Shutdown(shutCtx)
	case err := <-errCh:
		return err
	}
}

func registerRoutes(r *mux.Router, nc *NodeClient) {
	api := r.PathPrefix("/api").Subrouter()

	// node / meta
	api.HandleFunc("", handleInfo(nc)).Methods(http.MethodGet, http.MethodOptions)
	api.HandleFunc("/", handleInfo(nc)).Methods(http.MethodGet, http.MethodOptions)
	api.HandleFunc("/version", handleVersion(nc)).Methods(http.MethodGet)
	api.HandleFunc("/sanity", handleSanity(nc)).Methods(http.MethodGet)
	api.HandleFunc("/node-info", handleNodeInfo(nc)).Methods(http.MethodGet)

	// blocks
	api.HandleFunc("/currentblock", handleCurrentBlock(nc)).Methods(http.MethodGet)
	api.HandleFunc("/getblock/{height:[0-9]+}", handleGetBlock(nc)).Methods(http.MethodGet)
	api.HandleFunc("/getblocktransactions/{height:[0-9]+}", handleGetBlockTransactions(nc)).Methods(http.MethodGet)

	// transactions
	api.HandleFunc("/gettransaction/{id}", handleGetTransaction(nc)).Methods(http.MethodGet)
	api.HandleFunc("/gettransactions/{address}", handleGetTransactions(nc)).Methods(http.MethodGet)
	api.HandleFunc("/gettransactions", handleGetTransactions(nc)).Methods(http.MethodGet)

	// balances
	api.HandleFunc("/getbalance/{address}", handleGetBalance(nc)).Methods(http.MethodGet)
	api.HandleFunc("/getbalance", handleGetBalance(nc)).Methods(http.MethodGet)
	api.HandleFunc("/getpendingbalance", handleGetPendingBalance(nc)).Methods(http.MethodGet)

	// address / key utilities
	api.HandleFunc("/getaddress/{public_key}", handleGetAddress(nc)).Methods(http.MethodGet)
	api.HandleFunc("/getaddress", handleGetAddress(nc)).Methods(http.MethodGet)
	api.HandleFunc("/base58/{string}", handleBase58(nc)).Methods(http.MethodGet)
	api.HandleFunc("/base58", handleBase58(nc)).Methods(http.MethodGet)
	api.HandleFunc("/checkaddress", handleCheckAddress(nc)).Methods(http.MethodGet)
	api.HandleFunc("/checksignature/{public_key}/{signature}/{data}", handleCheckSignature(nc)).Methods(http.MethodGet)
	api.HandleFunc("/generate_wallet", handleGenerateWallet(nc)).Methods(http.MethodGet)
	api.HandleFunc("/getpublickey", handleGetPublicKey(nc)).Methods(http.MethodGet)

	// network stats
	api.HandleFunc("/masternodes", handleMasternodes(nc)).Methods(http.MethodGet)
	api.HandleFunc("/mempoolsize", handleMempoolSize(nc)).Methods(http.MethodGet)
	api.HandleFunc("/totalsupply", handleTotalSupply(nc)).Methods(http.MethodGet)
	api.HandleFunc("/circsupply", handleCircSupply(nc)).Methods(http.MethodGet)

	// misc
	api.HandleFunc("/randomnumber", handleRandomNumber(nc)).Methods(http.MethodGet)
	api.HandleFunc("/send", handleSend(nc)).Methods(http.MethodGet, http.MethodPost)

	// D5 stubs (aliases / assets — not yet implemented)
	for _, path := range []string{
		"/getaliasaddress",
		"/getaliasaddress/{alias}",
		"/getalias",
		"/getalias/{address}",
		"/getassets",
		"/getassets/{address}",
		"/getasset/{asset_id}",
		"/createasset",
	} {
		api.HandleFunc(path, handleD5Stub(nc)).Methods(http.MethodGet, http.MethodPost)
	}
}
