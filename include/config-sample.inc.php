<?php

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
*/

// The database DSN
$_config['db_connect'] = 'mysql:host=localhost;dbname=ENTER-DB-NAME';

// The database username
$_config['db_user'] = 'ENTER-DB-USER';

// The database password
$_config['db_pass'] = 'ENTER-DB-PASS';

/*
|--------------------------------------------------------------------------
| General Configuration
|--------------------------------------------------------------------------
*/

// Maximum number of connected peers
$_config['max_peers'] = 30;

// Enable testnet mode for development
$_config['testnet'] = false;

// To avoid any problems if other clones are made
$_config['coin'] = 'steroid4';

// Allow others to connect to the node api (if set to false, only the below 'allowed_hosts' are allowed)
$_config['public_api'] = true;

// Hosts that are allowed to mine on this node
$_config['allowed_hosts'] = [
    '127.0.0.1',
];

// Disable transactions and block repropagation
$_config['disable_repropagation'] = false;


/*
|--------------------------------------------------------------------------
| Peer Configuration
|--------------------------------------------------------------------------
*/

// The number of peers to send each new transaction to
$_config['transaction_propagation_peers'] = 5;

// How many new peers to check from each peer
$_config['max_test_peers'] = 5;

// The initial peers to sync from in sanity
$_config['initial_peer_list'] = [
    'http://peer1.steroid.io',
    'http://peer2.steroid.io',
    'http://peer3.steroid.io',
    'http://peer4.steroid.io',
    'http://peer5.steroid.io',
    'http://peer6.steroid.io',
    'http://peer7.steroid.io',
    'http://peer8.steroid.io',
    'http://peer9.steroid.io',
    'http://peer10.steroid.io',
    'http://peer11.steroid.io',
    'http://peer12.steroid.io',
    'http://peer13.steroid.io',
    'http://peer14.steroid.io',
    'http://peer15.steroid.io',
    'http://peer16.steroid.io',
    'http://peer17.steroid.io',
    'http://peer18.steroid.io',
    'http://peer19.steroid.io',
    'http://peer20.steroid.io',
    'http://peer21.steroid.io',
    'http://peer22.steroid.io',
    'http://peer23.steroid.io',
    'http://peer24.steroid.io',
    'http://peer25.steroid.io',
    'http://peer26.steroid.io',
    'http://peer27.steroid.io',
];

// does not peer with any of the peers. Uses the seed peers and syncs only from those peers. Requires a cronjob on sanity.php
$_config['passive_peering'] = false;


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

// Block accepting transfers from addresses blacklisted by the Steroid4 devs
$_config['use_official_blacklist'] = true;

/*
|--------------------------------------------------------------------------
| Sanity Configuration
|--------------------------------------------------------------------------
*/

// Recheck the last blocks on sanity
$_config['sanity_recheck_blocks'] = 30;

// The interval to run the sanity in seconds
$_config['sanity_interval'] = 900;

// Enable setting a new hostname (should be used only if you want to change the hostname)
$_config['allow_hostname_change'] = false;

// Rebroadcast local transactions when running sanity
$_config['sanity_rebroadcast_locals'] = true;

// Get more peers?
$_config['get_more_peers'] = true;

// Allow automated resyncs if the node is stuck. Enabled by default

$_config['auto_resync'] = true;

/*
|--------------------------------------------------------------------------
| Logging Configuration
|--------------------------------------------------------------------------
*/

// Enable log output to the specified file
$_config['enable_logging'] = false;

// The specified file to write to (this should not be publicly visible)
$_config['log_file'] = '/var/log/bpc.log';

// Log verbosity (default 0, maximum 3)
$_config['log_verbosity'] = 0;

/*
|--------------------------------------------------------------------------
| Masternode Configuration
|--------------------------------------------------------------------------
*/

// Enable this node as a masternode
$_config['masternode'] = false;

// The public key for the masternode
$_config['masternode_public_key'] = '';
$_config['masternode_voting_public_key'] = '';
$_config['masternode_voting_private_key'] = '';