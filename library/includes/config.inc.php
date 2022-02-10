<?php

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
*/

// The database DSN
$_config['db_connect'] = 'mysql:host=localhost;dbname=S4QL';

// The database username
$_config['db_user'] = 'node';

// The database password
$_config['db_pass'] = 'upGlHjJdB3xXarRU';

/*
|--------------------------------------------------------------------------
| General Configuration
|--------------------------------------------------------------------------
*/

// Maximum number of connected peers
$_config['max_peers'] = 30;

// Enable testnet mode for development
$_config['testnet'] = true;

// To avoid any problems if other clones are made
$_config['coin'] = 'bpc';

// Allow others to connect to the node api (if set to false, only the below 'allowed_hosts' are allowed)
$_config['public_api'] = true;

// Hosts that are allowed to mine on this node
$_config['allowed_hosts'] = [
    '127.0.0.1',
    '139.162.179.250',
    '139.162.212.101',
    '172.104.134.29',
    '62.228.227.198'
];

// Disable transactions and block repropagation
$_config['disable_repropagation'] = true;


/*
|--------------------------------------------------------------------------
| Peer Configuration
|--------------------------------------------------------------------------
*/

// The number of peers to send each new transaction to
$_config['transaction_propagation_peers'] = 1;

// How many new peers to check from each peer
$_config['max_test_peers'] = 1;

// The initial peers to sync from in sanity
$_config['initial_peer_list'] = [
	'https://peer1.steroid.io',
];

// does not peer with any of the peers. Uses the seed peers and syncs only from those peers. Requires a cronjob on sanity.php
$_config['passive_peering'] = true;


/*
|--------------------------------------------------------------------------
| Mempool Configuration
|--------------------------------------------------------------------------
*/

// The maximum transactions to accept from a single peer
$_config['peer_max_mempool'] = 100;

// The maximum number of mempool transactions to be rebroadcasted
$_config['max_mempool_rebroadcast'] = 5000;

// The number of blocks between rebroadcasting transactions
$_config['sanity_rebroadcast_height'] = 30;

// Block accepting transfers from addresses blacklisted by the Steroid devs
$_config['use_official_blacklist'] = false;

/*
|--------------------------------------------------------------------------
| Sanity Configuration
|--------------------------------------------------------------------------
*/

// Recheck the last blocks on sanity
$_config['sanity_recheck_blocks'] = 10;

// The interval to run the sanity in seconds
$_config['sanity_interval'] = 900;

// Enable setting a new hostname (should be used only if you want to change the hostname)
$_config['allow_hostname_change'] = false;

// Rebroadcast local transactions when running sanity
$_config['sanity_rebroadcast_locals'] = true;

// Get more peers?
$_config['get_more_peers'] = true;

/*
|--------------------------------------------------------------------------
| Logging Configuration
|--------------------------------------------------------------------------
*/

// Enable log output to the specified file
$_config['enable_logging'] = true;

// The specified file to write to (this should not be publicly visible)
$_config['log_file'] = '/var/log/bpc.log';

// Log verbosity (default 0, maximum 3)
$_config['log_verbosity'] = 1;

/*
|--------------------------------------------------------------------------
| Masternode Configuration
|--------------------------------------------------------------------------
*/

// Enable this node as a masternode
$_config['masternode'] = true;
$_config['maintenance'] = false;
// The public key for the masternode
$_config['masternode_public_key'] = 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyxMpqsADqXaXVUgzpq3iSM6TCq3uju8cN7wWmLuj9Ly9u6tes84jX4mBfgr8FqPC5gG1L3nnTGXL9q6tRMryb8Wh';
//private for test
/*
 * H4KoERfwCfmWVsUN4uQSRzWvXqGFFN5AUFmaWVNXmghWgKkLYok4YHn2yzEL8jrwGySL3UFPY22WQJMHe39KyL9ZMoUkoQMm5EiQwiUUuQtxVCorMJaybunxM5aUqR38d8uxipKHnYYDwychsEKKogB9f6njxjt9BhVcnZZCMRzRozMnS58KXb3EwwwEn6DbQVrwcsm1GR1qgsG7uJ4EnboNsZVkoD8PnKwCAoW7ucxcwgbL4H4ZeuGQSfikMd6XBJgv1nuzsGpdW4j4S3ZfLnpU4Q1XefxsJPXZ6PVCg7H9xcpNEpW9SZdbhs5UiNzJaQww3gFGRxLMkZg5aPbX3ntshQms6epYe9HY55QZpCzbh4oEX2
 */
