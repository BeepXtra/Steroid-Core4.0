// Package cmd wires the stereodd CLI commands onto the Cosmos SDK server.
package cmd

import (
	"os"

	"github.com/spf13/cobra"

	"cosmossdk.io/log/v2"

	dbm "github.com/cosmos/cosmos-db"

	cmtcfg "github.com/cometbft/cometbft/config"

	"github.com/cosmos/cosmos-sdk/client"
	clientconfig "github.com/cosmos/cosmos-sdk/client/config"
	"github.com/cosmos/cosmos-sdk/client/debug"
	"github.com/cosmos/cosmos-sdk/client/flags"
	"github.com/cosmos/cosmos-sdk/client/keys"
	"github.com/cosmos/cosmos-sdk/client/pruning"
	"github.com/cosmos/cosmos-sdk/client/snapshot"
	"github.com/cosmos/cosmos-sdk/server"
	servertypes "github.com/cosmos/cosmos-sdk/server/types"
	banktypes "github.com/cosmos/cosmos-sdk/x/bank/types"
	genutilcli "github.com/cosmos/cosmos-sdk/x/genutil/client/cli"
	genutiltypes "github.com/cosmos/cosmos-sdk/x/genutil/types"

	steroidaddress "github.com/beepxtra/steroid-core4.0/app/address"

	"github.com/beepxtra/steroid-core4.0/app"
)

// NewRootCmd creates the root command for the stereodd node.
func NewRootCmd() *cobra.Command {
	encodingConfig := app.MakeEncodingConfig()

	initClientCtx := client.Context{}.
		WithCodec(encodingConfig.Codec).
		WithInterfaceRegistry(encodingConfig.InterfaceRegistry).
		WithTxConfig(encodingConfig.TxConfig).
		WithLegacyAmino(encodingConfig.Amino).
		WithInput(os.Stdin).
		WithHomeDir(app.DefaultNodeHome).
		WithViper("STEREODD")

	rootCmd := &cobra.Command{
		Use:   "stereodd",
		Short: "Steroid blockchain node",
		PersistentPreRunE: func(cmd *cobra.Command, _ []string) error {
			initClientCtx, err := client.ReadPersistentCommandFlags(initClientCtx, cmd.Flags())
			if err != nil {
				return err
			}
			initClientCtx, err = clientconfig.ReadFromClientConfig(initClientCtx)
			if err != nil {
				return err
			}
			if err := client.SetCmdClientContextHandler(initClientCtx, cmd); err != nil {
				return err
			}
			return server.InterceptConfigsPreRunHandler(cmd, "", nil, cmtcfg.DefaultConfig())
		},
	}

	// D3: base58 addresses — no bech32 prefix configuration needed.
	// The sdk.Config bech32 prefixes are unused; our address.Codec handles
	// all string ↔ bytes conversion.

	initRootCmd(rootCmd, encodingConfig.TxConfig)
	return rootCmd
}

func initRootCmd(rootCmd *cobra.Command, txConfig client.TxConfig) {
	rootCmd.AddCommand(
		genutilcli.InitCmd(app.ModuleBasics, app.DefaultNodeHome),
		debug.Cmd(),
		pruning.Cmd(newApp, app.DefaultNodeHome),
		snapshot.Cmd(newApp),
	)

	server.AddCommands(rootCmd, app.DefaultNodeHome, newApp, appExport, addModuleInitFlags)

	rootCmd.AddCommand(
		server.StatusCommand(),
		genutilcli.ValidateGenesisCmd(app.ModuleBasics),
		genutilcli.AddGenesisAccountCmd(app.DefaultNodeHome, steroidaddress.Codec{}),
		genutilcli.GenTxCmd(
			app.ModuleBasics,
			txConfig,
			banktypes.GenesisBalancesIterator{},
			app.DefaultNodeHome,
			steroidaddress.Codec{},
		),
		genutilcli.CollectGenTxsCmd(
			banktypes.GenesisBalancesIterator{},
			app.DefaultNodeHome,
			genutiltypes.DefaultMessageValidator,
			steroidaddress.Codec{},
		),
		keys.Commands(),
		GatewayCmd(),
	)

	_ = flags.FlagHome // ensure flags package init() runs
}

func addModuleInitFlags(_ *cobra.Command) {}

func newApp(
	logger log.Logger,
	db dbm.DB,
	appOpts servertypes.AppOptions,
) servertypes.Application {
	return app.New(
		logger,
		db,
		appOpts,
		server.DefaultBaseappOptions(appOpts)...,
	)
}

func appExport(
	logger log.Logger,
	db dbm.DB,
	height int64,
	forZeroHeight bool,
	jailAllowedAddrs []string,
	appOpts servertypes.AppOptions,
	modulesToExport []string,
) (servertypes.ExportedApp, error) {
	a := app.New(
		logger,
		db,
		appOpts,
		server.DefaultBaseappOptions(appOpts)...,
	)
	if height != -1 {
		if err := a.LoadVersion(height); err != nil {
			return servertypes.ExportedApp{}, err
		}
	}
	return a.ExportAppStateAndValidators(forZeroHeight, jailAllowedAddrs, modulesToExport)
}
