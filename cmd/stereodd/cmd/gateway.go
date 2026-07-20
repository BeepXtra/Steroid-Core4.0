package cmd

import (
	"context"
	"fmt"
	"os"
	"os/signal"
	"syscall"

	"github.com/spf13/cobra"

	"github.com/beepxtra/steroid-core4.0/x/gateway"
)

func GatewayCmd() *cobra.Command {
	var (
		listenAddr string
		nodeAddr   string
		grpcAddr   string
	)

	cmd := &cobra.Command{
		Use:   "gateway",
		Short: "Run the REST compatibility gateway",
		Long: `Starts an HTTP server that exposes the legacy /api/* endpoints,
translating them to CometBFT RPC and Cosmos SDK gRPC calls.`,
		RunE: func(cmd *cobra.Command, _ []string) error {
			ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
			defer stop()

			_, _ = fmt.Fprintf(cmd.OutOrStdout(), "Gateway listening on %s (node=%s grpc=%s)\n",
				listenAddr, nodeAddr, grpcAddr)

			if err := gateway.StartGateway(ctx, listenAddr, nodeAddr, grpcAddr); err != nil &&
				err.Error() != "http: Server closed" {
				return err
			}
			return nil
		},
	}

	cmd.Flags().StringVar(&listenAddr, "listen", ":8080", "address to listen on")
	cmd.Flags().StringVar(&nodeAddr, "node", "tcp://localhost:26657", "CometBFT RPC address")
	cmd.Flags().StringVar(&grpcAddr, "grpc", "localhost:9090", "gRPC server address")

	return cmd
}
