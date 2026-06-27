// Package app wires the Steroid chain application onto Cosmos SDK + CometBFT.
//
// v1 core module set: auth, bank, staking, gov, distribution, slashing, mint,
// params, crisis, genutil.
//
// Additional modules (authz, feegrant, evidence, upgrade) are included in the
// struct stubs and will be fully wired once the keeper initialisation is written
// (TheRealGofre, per workplan spec from G4L1L3O).
//
// Design decisions that are scaffolded here but implemented later:
//   - TODO(D1a): VRF proposer rotation — custom PrepareProposal/ProcessProposal handlers.
//   - TODO(D3):  Custom base58 address codec (see app/codec.go).
//   - TODO(D4):  Emission curve, reward splits, min-bond economic parameters.
//   - TODO(D5):  x/assets and x/alias custom modules.
//   - TODO(D10): S4QL → genesis migration tool.
package app

import (
	"encoding/json"
	"io"
	"os"

	dbm "github.com/cosmos/cosmos-db"

	"cosmossdk.io/log"
	storetypes "cosmossdk.io/store/types"

	abci "github.com/cometbft/cometbft/abci/types"

	"github.com/cosmos/cosmos-sdk/baseapp"
	"github.com/cosmos/cosmos-sdk/client"
	"github.com/cosmos/cosmos-sdk/codec"
	codectypes "github.com/cosmos/cosmos-sdk/codec/types"
	"github.com/cosmos/cosmos-sdk/server/api"
	serverconfig "github.com/cosmos/cosmos-sdk/server/config"
	servertypes "github.com/cosmos/cosmos-sdk/server/types"
	sdk "github.com/cosmos/cosmos-sdk/types"
	"github.com/cosmos/cosmos-sdk/types/module"
	"github.com/cosmos/cosmos-sdk/version"
	authtx "github.com/cosmos/cosmos-sdk/x/auth/tx"
	cmtservice "github.com/cosmos/cosmos-sdk/client/grpc/cmtservice"
	nodeservice "github.com/cosmos/cosmos-sdk/client/grpc/node"

	authkeeper "github.com/cosmos/cosmos-sdk/x/auth/keeper"
	authtypes "github.com/cosmos/cosmos-sdk/x/auth/types"

	bankkeeper "github.com/cosmos/cosmos-sdk/x/bank/keeper"
	banktypes "github.com/cosmos/cosmos-sdk/x/bank/types"

	crisiskeeper "github.com/cosmos/cosmos-sdk/x/crisis/keeper"
	crisistypes "github.com/cosmos/cosmos-sdk/x/crisis/types"

	distrkeeper "github.com/cosmos/cosmos-sdk/x/distribution/keeper"
	distrtypes "github.com/cosmos/cosmos-sdk/x/distribution/types"

	govkeeper "github.com/cosmos/cosmos-sdk/x/gov/keeper"
	govtypes "github.com/cosmos/cosmos-sdk/x/gov/types"
	govv1 "github.com/cosmos/cosmos-sdk/x/gov/types/v1"
	govv1beta1 "github.com/cosmos/cosmos-sdk/x/gov/types/v1beta1"

	mintkeeper "github.com/cosmos/cosmos-sdk/x/mint/keeper"
	minttypes "github.com/cosmos/cosmos-sdk/x/mint/types"

	paramskeeper "github.com/cosmos/cosmos-sdk/x/params/keeper"
	paramstypes "github.com/cosmos/cosmos-sdk/x/params/types"
	paramproposal "github.com/cosmos/cosmos-sdk/x/params/types/proposal"

	slashingkeeper "github.com/cosmos/cosmos-sdk/x/slashing/keeper"
	slashingtypes "github.com/cosmos/cosmos-sdk/x/slashing/types"

	stakingkeeper "github.com/cosmos/cosmos-sdk/x/staking/keeper"
	stakingtypes "github.com/cosmos/cosmos-sdk/x/staking/types"

	"github.com/cosmos/cosmos-sdk/x/genutil"
	genutiltypes "github.com/cosmos/cosmos-sdk/x/genutil/types"
)

const Name = "steroid"

// DefaultNodeHome is the default home directory for stereodd.
var DefaultNodeHome = os.ExpandEnv("$HOME/.stereodd")

// maccPerms maps module account names to their permissions.
var maccPerms = map[string][]string{
	authtypes.FeeCollectorName:     nil,
	distrtypes.ModuleName:          nil,
	minttypes.ModuleName:           {authtypes.Minter},
	stakingtypes.BondedPoolName:    {authtypes.Burner, authtypes.Staking},
	stakingtypes.NotBondedPoolName: {authtypes.Burner, authtypes.Staking},
	govtypes.ModuleName:            {authtypes.Burner},
}

// ModuleBasics defines the module codec for the CLI and genesis.
var ModuleBasics = module.NewBasicManager(
	genutil.NewAppModuleBasic(genutiltypes.DefaultMessageValidator),
)

// App is the Steroid blockchain application. It embeds baseapp.BaseApp and
// wires Cosmos SDK modules onto CometBFT BFT-PoS consensus.
type App struct {
	*baseapp.BaseApp

	cdc               codec.Codec
	interfaceRegistry codectypes.InterfaceRegistry
	amino             *codec.LegacyAmino

	// store keys
	keys  map[string]*storetypes.KVStoreKey
	tkeys map[string]*storetypes.TransientStoreKey

	// ── Core SDK module keepers (v1 set) ─────────────────────────────────────

	AccountKeeper  authkeeper.AccountKeeper
	BankKeeper     bankkeeper.BaseKeeper
	StakingKeeper  *stakingkeeper.Keeper
	GovKeeper      *govkeeper.Keeper
	DistrKeeper    distrkeeper.Keeper
	SlashingKeeper slashingkeeper.Keeper
	MintKeeper     mintkeeper.Keeper
	ParamsKeeper   paramskeeper.Keeper
	CrisisKeeper   *crisiskeeper.Keeper

	// ── Extended SDK module keepers (to be wired per module spec) ─────────────
	// These are declared so the struct is ready; keeper init is in a follow-on task.
	// UpgradeKeeper  upgradekeeper.Keeper  // TODO: wire x/upgrade
	// EvidenceKeeper evidencekeeper.Keeper // TODO: wire x/evidence
	// AuthzKeeper    authzkeeper.Keeper    // TODO: wire x/authz
	// FeeGrantKeeper feegrantkeeper.Keeper // TODO: wire x/feegrant

	// ── Custom module keepers (v2 set — added per workplan spec) ─────────────
	// TODO(D5): AssetsKeeper assetskeeper.Keeper
	// TODO(D5): AliasKeeper  aliaskeeper.Keeper

	ModuleManager *module.Manager
	configurator  module.Configurator
}

// New creates and initialises the App.
func New(
	logger log.Logger,
	db dbm.DB,
	traceStore io.Writer,
	loadLatest bool,
	appOpts servertypes.AppOptions,
	baseAppOptions ...func(*baseapp.BaseApp),
) *App {
	encodingConfig := MakeEncodingConfig()

	bApp := baseapp.NewBaseApp(
		Name,
		logger,
		db,
		encodingConfig.TxConfig.TxDecoder(),
		baseAppOptions...,
	)
	bApp.SetCommitMultiStoreTracer(traceStore)
	bApp.SetVersion(version.Version)
	bApp.SetInterfaceRegistry(encodingConfig.InterfaceRegistry)
	bApp.SetTxEncoder(encodingConfig.TxConfig.TxEncoder())

	app := &App{
		BaseApp:           bApp,
		cdc:               encodingConfig.Codec,
		interfaceRegistry: encodingConfig.InterfaceRegistry,
		amino:             encodingConfig.Amino,
	}

	// ── Store keys ───────────────────────────────────────────────────────────
	app.keys = storetypes.NewKVStoreKeys(
		authtypes.StoreKey,
		banktypes.StoreKey,
		stakingtypes.StoreKey,
		govtypes.StoreKey,
		distrtypes.StoreKey,
		slashingtypes.StoreKey,
		minttypes.StoreKey,
		paramstypes.StoreKey,
		crisistypes.StoreKey,
	)
	app.tkeys = storetypes.NewTransientStoreKeys(paramstypes.TStoreKey)

	// ── Params keeper (legacy subspace) ──────────────────────────────────────
	app.ParamsKeeper = initParamsKeeper(
		app.cdc,
		app.amino,
		app.keys[paramstypes.StoreKey],
		app.tkeys[paramstypes.TStoreKey],
	)
	// NOTE(D4): x/consensus keeper + bApp.SetParamStore wiring left for keeper
	// initialisation task. New chains receive consensus params automatically in
	// InitChain — no legacy params subspace migration needed.

	// ── Module keeper initialisation (TODO per module spec) ───────────────────
	// Each keeper will be fully initialised when its module spec is finalised and
	// handed off. The struct fields above keep the types visible.

	// TODO(D1a): after keepers are wired, register VRF proposer rotation:
	//   bApp.SetPrepareProposal(vrfprepare.Handler(app.StakingKeeper, ...))
	//   bApp.SetProcessProposal(vrfprocess.Handler(app.StakingKeeper, ...))

	// ── Store mounting ────────────────────────────────────────────────────────
	app.MountKVStores(app.keys)
	app.MountTransientStores(app.tkeys)

	app.ModuleManager = module.NewManager()

	if loadLatest {
		if err := app.LoadLatestVersion(); err != nil {
			logger.Error("error loading latest version", "error", err)
			os.Exit(1)
		}
	}
	return app
}

// ── Interface methods (servertypes.Application) ──────────────────────────────

func (app *App) AppCodec() codec.Codec                           { return app.cdc }
func (app *App) InterfaceRegistry() codectypes.InterfaceRegistry { return app.interfaceRegistry }
func (app *App) LegacyAmino() *codec.LegacyAmino                 { return app.amino }

func (app *App) DefaultGenesis() map[string]json.RawMessage {
	return ModuleBasics.DefaultGenesis(app.cdc)
}

func (app *App) InitChainer(ctx sdk.Context, req *abci.RequestInitChain) (*abci.ResponseInitChain, error) {
	var genesisState GenesisState
	if err := json.Unmarshal(req.AppStateBytes, &genesisState); err != nil {
		return nil, err
	}
	return app.ModuleManager.InitGenesis(ctx, app.cdc, genesisState)
}

func (app *App) BeginBlocker(ctx sdk.Context) (sdk.BeginBlock, error) {
	return app.ModuleManager.BeginBlock(ctx)
}

func (app *App) EndBlocker(ctx sdk.Context) (sdk.EndBlock, error) {
	return app.ModuleManager.EndBlock(ctx)
}

func (app *App) ExportAppStateAndValidators(
	_ bool, _ []string, modulesToExport []string,
) (servertypes.ExportedApp, error) {
	ctx := app.NewContext(true)
	genesis, err := app.ModuleManager.ExportGenesisForModules(ctx, app.cdc, modulesToExport)
	if err != nil {
		return servertypes.ExportedApp{}, err
	}
	appState, err := json.MarshalIndent(genesis, "", "  ")
	if err != nil {
		return servertypes.ExportedApp{}, err
	}
	return servertypes.ExportedApp{
		AppState:        appState,
		Height:          app.LastBlockHeight(),
		ConsensusParams: app.BaseApp.GetConsensusParams(ctx),
	}, nil
}

// ── servertypes.Application interface completions ────────────────────────────

func (app *App) RegisterAPIRoutes(apiSvr *api.Server, _ serverconfig.APIConfig) {
	clientCtx := apiSvr.ClientCtx
	authtx.RegisterGRPCGatewayRoutes(clientCtx, apiSvr.GRPCGatewayRouter)
	cmtservice.RegisterGRPCGatewayRoutes(clientCtx, apiSvr.GRPCGatewayRouter)
	nodeservice.RegisterGRPCGatewayRoutes(clientCtx, apiSvr.GRPCGatewayRouter)
	ModuleBasics.RegisterGRPCGatewayRoutes(clientCtx, apiSvr.GRPCGatewayRouter)
}

func (app *App) RegisterTxService(clientCtx client.Context) {
	authtx.RegisterTxService(app.GRPCQueryRouter(), clientCtx, app.Simulate, app.interfaceRegistry)
}

func (app *App) RegisterTendermintService(clientCtx client.Context) {
	cmtservice.RegisterTendermintService(
		clientCtx,
		app.GRPCQueryRouter(),
		app.interfaceRegistry,
		app.Query,
	)
}

func (app *App) RegisterNodeService(clientCtx client.Context, cfg serverconfig.Config) {
	nodeservice.RegisterNodeService(clientCtx, app.GRPCQueryRouter(), cfg)
}

// ── Helpers ──────────────────────────────────────────────────────────────────

// GenesisState is the genesis state of the app, keyed by module name.
type GenesisState map[string]json.RawMessage

// GetKey returns the KVStoreKey for the given store key name.
func (app *App) GetKey(storeKey string) *storetypes.KVStoreKey {
	return app.keys[storeKey]
}

// GetSubspace returns a params subspace for a given module name.
func (app *App) GetSubspace(moduleName string) paramstypes.Subspace {
	subspace, _ := app.ParamsKeeper.GetSubspace(moduleName)
	return subspace
}

func initParamsKeeper(
	cdc codec.BinaryCodec,
	legacyAmino *codec.LegacyAmino,
	key *storetypes.KVStoreKey,
	tkey *storetypes.TransientStoreKey,
) paramskeeper.Keeper {
	pk := paramskeeper.NewKeeper(cdc, legacyAmino, key, tkey)

	pk.Subspace(authtypes.ModuleName)
	pk.Subspace(banktypes.ModuleName)
	pk.Subspace(stakingtypes.ModuleName)
	pk.Subspace(minttypes.ModuleName)
	pk.Subspace(distrtypes.ModuleName)
	pk.Subspace(slashingtypes.ModuleName)
	pk.Subspace(crisistypes.ModuleName)
	pk.Subspace(govtypes.ModuleName).WithKeyTable(
		govv1.ParamKeyTable(), //nolint:staticcheck
	)
	pk.Subspace(paramproposal.ModuleName)

	return pk
}

// Suppress lint for packages used only in type declarations or var blocks.
var (
	_ = govv1beta1.NewMsgVote
	_ = maccPerms
)
