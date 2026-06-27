// Package app wires the Steroid chain application onto Cosmos SDK + CometBFT.
//
// v1 core module set: auth, bank, staking, gov, distribution, slashing, mint,
// params, crisis, genutil.
//
// Design decisions scaffolded here but implemented later:
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
	"github.com/cosmos/cosmos-sdk/runtime"

	steroidaddress "github.com/beepxtra/steroid-core4.0/app/address"
	"github.com/cosmos/cosmos-sdk/server/api"
	serverconfig "github.com/cosmos/cosmos-sdk/server/config"
	servertypes "github.com/cosmos/cosmos-sdk/server/types"
	sdk "github.com/cosmos/cosmos-sdk/types"
	"github.com/cosmos/cosmos-sdk/types/module"
	"github.com/cosmos/cosmos-sdk/version"
	authtx "github.com/cosmos/cosmos-sdk/x/auth/tx"
	cmtservice "github.com/cosmos/cosmos-sdk/client/grpc/cmtservice"
	nodeservice "github.com/cosmos/cosmos-sdk/client/grpc/node"

	"github.com/cosmos/cosmos-sdk/x/auth"
	"github.com/cosmos/cosmos-sdk/x/auth/ante"
	authkeeper "github.com/cosmos/cosmos-sdk/x/auth/keeper"
	authtypes "github.com/cosmos/cosmos-sdk/x/auth/types"

	"github.com/cosmos/cosmos-sdk/x/bank"
	bankkeeper "github.com/cosmos/cosmos-sdk/x/bank/keeper"
	banktypes "github.com/cosmos/cosmos-sdk/x/bank/types"

	"github.com/cosmos/cosmos-sdk/x/crisis"
	crisiskeeper "github.com/cosmos/cosmos-sdk/x/crisis/keeper"
	crisistypes "github.com/cosmos/cosmos-sdk/x/crisis/types"

	distr "github.com/cosmos/cosmos-sdk/x/distribution"
	distrkeeper "github.com/cosmos/cosmos-sdk/x/distribution/keeper"
	distrtypes "github.com/cosmos/cosmos-sdk/x/distribution/types"

	"github.com/cosmos/cosmos-sdk/x/genutil"
	genutiltypes "github.com/cosmos/cosmos-sdk/x/genutil/types"

	"github.com/cosmos/cosmos-sdk/x/gov"
	govclient "github.com/cosmos/cosmos-sdk/x/gov/client"
	govkeeper "github.com/cosmos/cosmos-sdk/x/gov/keeper"
	govtypes "github.com/cosmos/cosmos-sdk/x/gov/types"
	govv1 "github.com/cosmos/cosmos-sdk/x/gov/types/v1"
	govv1beta1 "github.com/cosmos/cosmos-sdk/x/gov/types/v1beta1"

	"github.com/cosmos/cosmos-sdk/x/mint"
	mintkeeper "github.com/cosmos/cosmos-sdk/x/mint/keeper"
	minttypes "github.com/cosmos/cosmos-sdk/x/mint/types"

	"github.com/cosmos/cosmos-sdk/x/params"
	paramsclient "github.com/cosmos/cosmos-sdk/x/params/client"
	paramskeeper "github.com/cosmos/cosmos-sdk/x/params/keeper"
	paramstypes "github.com/cosmos/cosmos-sdk/x/params/types"
	paramproposal "github.com/cosmos/cosmos-sdk/x/params/types/proposal"

	"github.com/cosmos/cosmos-sdk/x/slashing"
	slashingkeeper "github.com/cosmos/cosmos-sdk/x/slashing/keeper"
	slashingtypes "github.com/cosmos/cosmos-sdk/x/slashing/types"

	"github.com/cosmos/cosmos-sdk/x/staking"
	stakingkeeper "github.com/cosmos/cosmos-sdk/x/staking/keeper"
	stakingtypes "github.com/cosmos/cosmos-sdk/x/staking/types"

	"github.com/cosmos/cosmos-sdk/x/consensus"
	consensuskeeper "github.com/cosmos/cosmos-sdk/x/consensus/keeper"
	consensusparamtypes "github.com/cosmos/cosmos-sdk/x/consensus/types"
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
	auth.AppModuleBasic{},
	bank.AppModuleBasic{},
	staking.AppModuleBasic{},
	distr.AppModuleBasic{},
	slashing.AppModuleBasic{},
	mint.AppModuleBasic{},
	gov.NewAppModuleBasic([]govclient.ProposalHandler{
		paramsclient.ProposalHandler,
	}),
	params.AppModuleBasic{},
	crisis.AppModuleBasic{},
	consensus.AppModuleBasic{},
	genutil.NewAppModuleBasic(genutiltypes.DefaultMessageValidator),
)

// App is the Steroid blockchain application.
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
	ParamsKeeper        paramskeeper.Keeper
	CrisisKeeper        *crisiskeeper.Keeper
	ConsensusParamKeeper consensuskeeper.Keeper

	// ── Custom module keepers (v2 set — added per workplan spec) ─────────────
	// TODO(D5): AssetsKeeper assetskeeper.Keeper
	// TODO(D5): AliasKeeper  aliaskeeper.Keeper

	ModuleManager *module.Manager
	configurator  module.Configurator
}

// moduleAuthority returns the base58-encoded address of a module account.
// All keepers that accept an `authority string` must receive a base58 address
// so that our steroidaddress.Codec can validate it during keeper init.
func moduleAuthority(moduleName string) string {
	addr := authtypes.NewModuleAddress(moduleName)
	s, err := steroidaddress.Codec{}.BytesToString(addr)
	if err != nil {
		panic(err)
	}
	return s
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
		consensusparamtypes.StoreKey,
	)
	app.tkeys = storetypes.NewTransientStoreKeys(paramstypes.TStoreKey)

	// ── Params keeper (legacy subspace) ──────────────────────────────────────
	app.ParamsKeeper = initParamsKeeper(
		app.cdc,
		app.amino,
		app.keys[paramstypes.StoreKey],
		app.tkeys[paramstypes.TStoreKey],
	)

	// ── AccountKeeper ─────────────────────────────────────────────────────────
	app.AccountKeeper = authkeeper.NewAccountKeeper(
		app.cdc,
		runtime.NewKVStoreService(app.keys[authtypes.StoreKey]),
		authtypes.ProtoBaseAccount,
		maccPerms,
		steroidaddress.Codec{}, // D3: base58 address codec
		"steroid",
		moduleAuthority(govtypes.ModuleName),
	)

	// ── BankKeeper ───────────────────────────────────────────────────────────
	// D3: blocked-address map keys must be base58 to match our keeper codec.
	blockedAddrs := make(map[string]bool)
	for name := range maccPerms {
		blockedAddrs[moduleAuthority(name)] = true
	}
	app.BankKeeper = bankkeeper.NewBaseKeeper(
		app.cdc,
		runtime.NewKVStoreService(app.keys[banktypes.StoreKey]),
		app.AccountKeeper,
		blockedAddrs,
		moduleAuthority(govtypes.ModuleName),
		logger,
	)

	// ── StakingKeeper ────────────────────────────────────────────────────────
	app.StakingKeeper = stakingkeeper.NewKeeper(
		app.cdc,
		runtime.NewKVStoreService(app.keys[stakingtypes.StoreKey]),
		app.AccountKeeper,
		app.BankKeeper,
		moduleAuthority(govtypes.ModuleName),
		steroidaddress.Codec{}, // D3: validator operator addresses
		steroidaddress.Codec{}, // D3: consensus node addresses
	)

	// ── DistrKeeper ──────────────────────────────────────────────────────────
	app.DistrKeeper = distrkeeper.NewKeeper(
		app.cdc,
		runtime.NewKVStoreService(app.keys[distrtypes.StoreKey]),
		app.AccountKeeper,
		app.BankKeeper,
		app.StakingKeeper,
		authtypes.FeeCollectorName,
		moduleAuthority(govtypes.ModuleName),
	)

	// ── SlashingKeeper ───────────────────────────────────────────────────────
	app.SlashingKeeper = slashingkeeper.NewKeeper(
		app.cdc,
		app.amino,
		runtime.NewKVStoreService(app.keys[slashingtypes.StoreKey]),
		app.StakingKeeper,
		moduleAuthority(govtypes.ModuleName),
	)

	// ── CrisisKeeper ─────────────────────────────────────────────────────────
	app.CrisisKeeper = crisiskeeper.NewKeeper(
		app.cdc,
		runtime.NewKVStoreService(app.keys[crisistypes.StoreKey]),
		1, // invCheckPeriod — every block during v1 dev; tune for production
		app.BankKeeper,
		authtypes.FeeCollectorName,
		moduleAuthority(govtypes.ModuleName),
		steroidaddress.Codec{}, // D3
	)

	// ── MintKeeper ───────────────────────────────────────────────────────────
	app.MintKeeper = mintkeeper.NewKeeper(
		app.cdc,
		runtime.NewKVStoreService(app.keys[minttypes.StoreKey]),
		app.StakingKeeper,
		app.AccountKeeper,
		app.BankKeeper,
		authtypes.FeeCollectorName,
		moduleAuthority(govtypes.ModuleName),
	)

	// ── GovKeeper ────────────────────────────────────────────────────────────
	app.GovKeeper = govkeeper.NewKeeper(
		app.cdc,
		runtime.NewKVStoreService(app.keys[govtypes.StoreKey]),
		app.AccountKeeper,
		app.BankKeeper,
		app.StakingKeeper,
		app.DistrKeeper,
		bApp.MsgServiceRouter(),
		govtypes.DefaultConfig(),
		moduleAuthority(govtypes.ModuleName),
	)
	govRouter := govv1beta1.NewRouter()
	govRouter.
		AddRoute(govtypes.RouterKey, govv1beta1.ProposalHandler).
		AddRoute(paramproposal.RouterKey, params.NewParamChangeProposalHandler(app.ParamsKeeper))
	app.GovKeeper.SetLegacyRouter(govRouter)

	// ── ConsensusParamKeeper — required by baseapp.SetParamStore ────────────
	app.ConsensusParamKeeper = consensuskeeper.NewKeeper(
		app.cdc,
		runtime.NewKVStoreService(app.keys[consensusparamtypes.StoreKey]),
		moduleAuthority(govtypes.ModuleName),
		runtime.EventService{},
	)
	bApp.SetParamStore(app.ConsensusParamKeeper.ParamsStore)

	// ── Staking hooks — must be set after DistrKeeper + SlashingKeeper ───────
	app.StakingKeeper.SetHooks(
		stakingtypes.NewMultiStakingHooks(
			app.DistrKeeper.Hooks(),
			app.SlashingKeeper.Hooks(),
		),
	)

	// TODO(D1a): after keepers are wired, register VRF proposer rotation:
	//   bApp.SetPrepareProposal(vrfprepare.Handler(app.StakingKeeper, ...))
	//   bApp.SetProcessProposal(vrfprocess.Handler(app.StakingKeeper, ...))

	// ── Module manager ───────────────────────────────────────────────────────
	app.ModuleManager = module.NewManager(
		genutil.NewAppModule(
			app.AccountKeeper, app.StakingKeeper, app,
			encodingConfig.TxConfig,
		),
		auth.NewAppModule(app.cdc, app.AccountKeeper, nil, app.GetSubspace(authtypes.ModuleName)),
		bank.NewAppModule(app.cdc, app.BankKeeper, app.AccountKeeper, app.GetSubspace(banktypes.ModuleName)),
		staking.NewAppModule(app.cdc, app.StakingKeeper, app.AccountKeeper, app.BankKeeper, app.GetSubspace(stakingtypes.ModuleName)),
		distr.NewAppModule(app.cdc, app.DistrKeeper, app.AccountKeeper, app.BankKeeper, app.StakingKeeper, app.GetSubspace(distrtypes.ModuleName)),
		slashing.NewAppModule(app.cdc, app.SlashingKeeper, app.AccountKeeper, app.BankKeeper, app.StakingKeeper, app.GetSubspace(slashingtypes.ModuleName), app.interfaceRegistry),
		crisis.NewAppModule(app.CrisisKeeper, false, app.GetSubspace(crisistypes.ModuleName)),
		mint.NewAppModule(app.cdc, app.MintKeeper, app.AccountKeeper, nil, app.GetSubspace(minttypes.ModuleName)),
		gov.NewAppModule(app.cdc, app.GovKeeper, app.AccountKeeper, app.BankKeeper, app.GetSubspace(govtypes.ModuleName)),
		params.NewAppModule(app.ParamsKeeper),
		consensus.NewAppModule(app.cdc, app.ConsensusParamKeeper),
	)

	// Module execution ordering. Canonical v1 set ordering from Cosmos SDK.
	app.ModuleManager.SetOrderBeginBlockers(
		minttypes.ModuleName,
		distrtypes.ModuleName,
		slashingtypes.ModuleName,
		stakingtypes.ModuleName,
		govtypes.ModuleName,
		crisistypes.ModuleName,
		genutiltypes.ModuleName,
		authtypes.ModuleName,
		banktypes.ModuleName,
		paramstypes.ModuleName,
		consensusparamtypes.ModuleName,
	)
	app.ModuleManager.SetOrderEndBlockers(
		crisistypes.ModuleName,
		govtypes.ModuleName,
		stakingtypes.ModuleName,
		genutiltypes.ModuleName,
		authtypes.ModuleName,
		banktypes.ModuleName,
		distrtypes.ModuleName,
		slashingtypes.ModuleName,
		minttypes.ModuleName,
		paramstypes.ModuleName,
		consensusparamtypes.ModuleName,
	)
	// auth must be first so accounts exist before any other module reads balances.
	// staking must precede slashing (slashing reads validator state set by staking).
	// consensus must come before genutil; genutil must be last.
	app.ModuleManager.SetOrderInitGenesis(
		authtypes.ModuleName,
		banktypes.ModuleName,
		distrtypes.ModuleName,
		stakingtypes.ModuleName,
		slashingtypes.ModuleName,
		govtypes.ModuleName,
		minttypes.ModuleName,
		crisistypes.ModuleName,
		paramstypes.ModuleName,
		consensusparamtypes.ModuleName,
		genutiltypes.ModuleName,
	)

	// ── Register module services (GRPC msg + query routes) ───────────────────
	app.configurator = module.NewConfigurator(app.cdc, bApp.MsgServiceRouter(), bApp.GRPCQueryRouter())
	if err := app.ModuleManager.RegisterServices(app.configurator); err != nil {
		panic(err)
	}

	// ── Register module invariants ────────────────────────────────────────────
	app.ModuleManager.RegisterInvariants(app.CrisisKeeper)

	// ── AnteHandler ──────────────────────────────────────────────────────────
	anteHandler, err := ante.NewAnteHandler(ante.HandlerOptions{
		AccountKeeper:   app.AccountKeeper,
		BankKeeper:      app.BankKeeper,
		SignModeHandler: encodingConfig.TxConfig.SignModeHandler(),
		SigGasConsumer:  ante.DefaultSigVerificationGasConsumer,
	})
	if err != nil {
		panic(err)
	}
	bApp.SetAnteHandler(anteHandler)

	// ── ABCI handler wiring ──────────────────────────────────────────────────
	app.SetInitChainer(app.InitChainer)
	app.SetBeginBlocker(app.BeginBlocker)
	app.SetEndBlocker(app.EndBlocker)

	// ── Store mounting ────────────────────────────────────────────────────────
	app.MountKVStores(app.keys)
	app.MountTransientStores(app.tkeys)

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
