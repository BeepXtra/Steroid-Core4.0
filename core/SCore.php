<?php
defined('_SECURED') or die('Restricted access');

/**
 * SCore — Dependency Injection Container & Application Bootstrap
 * Boots Config, Database, and all core services.
 */
class SCore {
    private static ?SCore $instance = null;
    private array $bindings = [];
    private array $singletons = [];

    private function __construct() {}

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─── DI Container ────────────────────────────────────────

    public function bind(string $abstract, callable $factory): void {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, callable $factory): void {
        $this->bind($abstract, function () use ($abstract, $factory) {
            if (!isset($this->singletons[$abstract])) {
                $this->singletons[$abstract] = $factory($this);
            }
            return $this->singletons[$abstract];
        });
    }

    public function make(string $abstract): mixed {
        if (!isset($this->bindings[$abstract])) {
            throw new RuntimeException("SCore: no binding for [$abstract]");
        }
        return ($this->bindings[$abstract])($this);
    }

    // ─── Bootstrap ───────────────────────────────────────────

    public static function boot(): self {
        $app = self::getInstance();

        // Config
        $app->singleton('config', fn() => Config::getInstance());

        // Database
        $app->singleton('db', fn($a) => Database::getInstance($a->make('config')));

        // Core services
        $app->singleton('wallet',     fn($a) => new SWallet($a->make('db')));
        $app->singleton('chain',      fn($a) => new SChain($a->make('db'), $a->make('config')));
        $app->singleton('tx',         fn($a) => new STx($a->make('db'), $a->make('config'), $a->make('wallet')));
        $app->singleton('block',      fn($a) => new SBlock($a->make('db'), $a->make('config'), $a->make('tx')));
        $app->singleton('peers',      fn($a) => new SPeers($a->make('db'), $a->make('config')));
        $app->singleton('mine',       fn($a) => new SMine($a->make('db'), $a->make('config'), $a->make('block'), $a->make('chain')));
        $app->singleton('assets',     fn($a) => new SAssets($a->make('db'), $a->make('config')));
        $app->singleton('masternode', fn($a) => new SMasternode($a->make('db'), $a->make('config')));
        $app->singleton('governance', fn($a) => new SGovernance($a->make('db'), $a->make('config')));

        return $app;
    }

    // ─── Request Routing ─────────────────────────────────────

    public function handleRequest(): void {
        $page = $_GET['page'] ?? $_GET['request'] ?? 'status';

        // API routing: /api/*
        if (isset($_GET['request']) && $_GET['request'] === 'ajax') {
            $this->routeApi($_GET['action'] ?? '');
            return;
        }

        $this->routePage($page);
    }

    private function routeApi(string $action): void {
        header('Content-Type: application/json');
        $controller = new ApiController($this);
        $controller->handle($action);
    }

    private function routePage(string $page): void {
        $map = [
            'status'      => 'StatusController',
            'blocks'      => 'BlocksController',
            'transactions'=> 'TransactionsController',
            'peers'       => 'PeersController',
            'masternode'  => 'MasternodeController',
            'assets'      => 'AssetsController',
            'governance'  => 'GovernanceController',
            'explorer'    => 'ExplorerController',
        ];
        $class = $map[$page] ?? 'StatusController';
        $ctrl  = new $class($this);
        $ctrl->index();
    }
}
