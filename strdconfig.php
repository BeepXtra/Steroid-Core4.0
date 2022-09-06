<?php

class strdconfig {

    //Database configuration
    public $db_hostname = 'localhost';
    public $db_username = 'testnet';
    public $db_password = '1QHUGrl7mCQ33uSg';
    public $strd_database = 'testnet';
    public $dbversion = 0;
    public $debug = 1;
    public $root_folder = '';
    //The time a session should be left alive (In seconds)
    //This is for security reasons. Users will be automatically logged out after the specified seconds of inactivity
    public $session_timeout = '10800';
    //Time settings
    public $timezone = 'UTC';
    //Chain configuration
    public $premine = 500000000;
    // Maximum number of connected peers
    public $max_peers = 30;
    // Enable testnet mode for development
    public $testnet = false;
    // To avoid any problems if other clones are made
    public $coin = 'bpc';
    // Allow others to connect to the node api (if set to false, only the below 'allowed_hosts' are allowed)
    public $public_api = true;
    // Hosts that are allowed to mine on this node
    public $allowed_hosts = [
        '127.0.0.1',
        '139.162.179.250',
        '139.162.212.101',
        '172.104.134.29',
        '62.228.227.198',
        '*'
    ];
    // Disable transactions and block repropagation
    public $disable_repropagation = true;

    /*
      |--------------------------------------------------------------------------
      | Peer Configuration
      |--------------------------------------------------------------------------
     */
    // The number of peers to send each new transaction to
    public $transaction_propagation_peers = 1;
    // How many new peers to check from each peer
    public $max_test_peers = 1;
    // The initial peers to sync from in sanity
    public $initial_peer_list = [
        'https://peer1.steroid.io',
    ];
    // does not peer with any of the peers. Uses the seed peers and syncs only from those peers. Requires a cronjob on sanity.php
    public $passive_peering = false;

    /*
      |--------------------------------------------------------------------------
      | Mempool Configuration
      |--------------------------------------------------------------------------
     */
    // The maximum transactions to accept from a single peer
    public $peer_max_mempool = 100;
    // The maximum number of mempool transactions to be rebroadcasted
    public $max_mempool_rebroadcast = 5000;
    // The number of blocks between rebroadcasting transactions
    public $sanity_rebroadcast_height = 30;
    // Block accepting transfers from addresses blacklisted by the Steroid devs
    public $use_official_blacklist = false;

    /*
      |--------------------------------------------------------------------------
      | Sanity Configuration
      |--------------------------------------------------------------------------
     */
    // Recheck the last blocks on sanity
    public $sanity_recheck_blocks = 10;
    // The interval to run the sanity in seconds
    public $sanity_interval = 900;
    // Enable setting a new hostname (should be used only if you want to change the hostname)
    public $allow_hostname_change = false;
    public $hostname = false;
    // Rebroadcast local transactions when running sanity
    public $sanity_rebroadcast_locals = true;
    // Get more peers?
    public $get_more_peers = false;

    /*
      |--------------------------------------------------------------------------
      | Logging Configuration
      |--------------------------------------------------------------------------
     */
    // Enable log output to the specified file
    public $enable_logging = true;
    // Log verbosity (default 0, maximum 3)
    public $log_verbosity = 3;

    /*
      |--------------------------------------------------------------------------
      | Masternode Configuration
      |--------------------------------------------------------------------------
     */
    // Enable this node as a masternode
    public $masternode = true;
    public $maintenance = false;
    // The public key for the masternode
    public $masternode_public_key = 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCxSfuVGzyQJhvgizJayys9rmiS3pu85PaYy3bGyHKSAvo75SmZJ78bgwY3gajeMXUfNbrM2Gv5WnTfrgFu9UPVckX';

    //private for test
    /*
     * H4KoERfwCfmWVsUN4uQSRzWvXqGFFN5AUFmaWVNXmghWgKkLYok4YHn2yzEL8jrwGySL3UFPY22WQJMHe39KyL9ZMoUkoQMm5EiQwiUUuQtxVCorMJaybunxM5aUqR38d8uxipKHnYYDwychsEKKogB9f6njxjt9BhVcnZZCMRzRozMnS58KXb3EwwwEn6DbQVrwcsm1GR1qgsG7uJ4EnboNsZVkoD8PnKwCAoW7ucxcwgbL4H4ZeuGQSfikMd6XBJgv1nuzsGpdW4j4S3ZfLnpU4Q1XefxsJPXZ6PVCg7H9xcpNEpW9SZdbhs5UiNzJaQww3gFGRxLMkZg5aPbX3ntshQms6epYe9HY55QZpCzbh4oEX2
     */

    function __construct() {
        $this->root_folder = dirname(__FILE__);
        
        if(isset($_SERVER['HTTP_HOST'])){
            $servername = explode('.', $_SERVER['HTTP_HOST']);
        } else {
            $servername = 'localhost';
        }
        
        $this->debug_queries = $this->debug;

        // The specified file to write to (this should not be publicly visible)
        $this->log_file = '/var/log/'.$this->coin.'.log';
    }

    public function getTld() {
        //print_r($_SERVER);
        $tldsrc = strrchr($_SERVER['HTTP_HOST'], ".");
        $tld = substr($tldsrc, 1);
        return $tld;
    }

}
?>