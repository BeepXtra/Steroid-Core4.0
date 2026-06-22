<?php
defined('_SECURED') or die('Restricted access');

/**
 * SCore — Dependency Injection Container & Application Bootstrap
 */
class SCore {
    private static $instance = null;
    private $bindings = array();
    private $singletons = array();

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind($abstract, $factory) {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton($abstract, $factory) {
        $app = $this;
        $this->bind($abstract, function() use ($abstract, $factory, $app) {
            if (!isset($app->singletons[$abstract])) {
                $app->singletons[$abstract] = $factory($app);
            }
            return $app->singletons[$abstract];
        });
    }

    public function make($abstract) {
        if (!isset($this->bindings[$abstract])) {
            throw new RuntimeException("SCore: no binding for [$abstract]");
        }
        $fn = $this->bindings[$abstract];
        return $fn($this);
    }

    public static function boot() {
        $app = self::getInstance();

        $app->singleton('config', function($a) { return Config::getInstance(); });
        $app->singleton('db',     function($a) { return Database::getInstance($a->make('config')); });

        $app->singleton('wallet',     function($a) { return new SWallet($a->make('db')); });
        $app->singleton('chain',      function($a) { return new SChain($a->make('db'), $a->make('config')); });
        $app->singleton('tx',         function($a) { return new STx($a->make('db'), $a->make('config'), $a->make('wallet')); });
        $app->singleton('block',      function($a) { return new SBlock($a->make('db'), $a->make('config'), $a->make('tx')); });
        $app->singleton('peers',      function($a) { return new SPeers($a->make('db'), $a->make('config')); });
        $app->singleton('mine',       function($a) { return new SMine($a->make('db'), $a->make('config'), $a->make('block'), $a->make('chain')); });
        $app->singleton('assets',     function($a) { return new SAssets($a->make('db'), $a->make('config')); });
        $app->singleton('masternode', function($a) { return new SMasternode($a->make('db'), $a->make('config')); });
        $app->singleton('governance', function($a) { return new SGovernance($a->make('db'), $a->make('config')); });

        return $app;
    }

    public function handleRequest() {
        $page = isset($_GET['request']) ? $_GET['request'] : (isset($_GET['page']) ? $_GET['page'] : 'status');

        if (isset($_GET['request']) && $_GET['request'] === 'ajax') {
            $this->routeApi(isset($_GET['action']) ? $_GET['action'] : '');
            return;
        }
        $this->routePage($page);
    }

    private function routeApi($action) {
        header('Content-Type: application/json');
        $controller = new ApiController($this);
        $controller->handle($action);
    }

    private function routePage($page) {
        $map = array(
            'status'       => 'StatusController',
            'blocks'       => 'BlocksController',
            'transactions' => 'TransactionsController',
            'peers'        => 'PeersController',
            'masternode'   => 'MasternodeController',
            'assets'       => 'AssetsController',
            'governance'   => 'GovernanceController',
            'explorer'     => 'ExplorerController',
        );
        $class = isset($map[$page]) ? $map[$page] : 'StatusController';
        $ctrl  = new $class($this);
        $ctrl->index();
    }
}
