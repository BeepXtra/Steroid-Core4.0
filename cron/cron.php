<?php
define('_SECURED', true);

// Load bootstrap
require_once __DIR__ . '/../index.php';

// Run cron tasks
$app = SCore::boot();

$task = $argv[1] ?? 'all';

switch ($task) {
    case 'heartbeat':
        $app->make('peers')->heartbeat();
        echo 'heartbeat OK' . PHP_EOL;
        break;

    case 'mempool':
        $purged = $app->make('tx')->purgeExpiredMempool();
        echo "Purged $purged expired mempool txs" . PHP_EOL;
        break;

    case 'peer_sync':
        $peers = $app->make('peers')->getActive(10);
        $chain = $app->make('chain');
        $total = 0;
        foreach ($peers as $peer) {
            $added = $chain->syncWithPeer($peer['hostname']);
            $total += $added;
            echo "Synced {$added} blocks from {$peer['hostname']}" . PHP_EOL;
        }
        echo "Total new blocks: $total" . PHP_EOL;
        break;

    case 'peer_discover':
        $peers = $app->make('peers');
        $active = $peers->getActive(5);
        $found  = 0;
        foreach ($active as $peer) {
            $found += $peers->discoverFromPeer($peer['hostname']);
        }
        echo "Discovered $found new peers" . PHP_EOL;
        break;

    case 'dividends':
        $app->make('assets')->processAutoDividends();
        echo 'Auto-dividends processed' . PHP_EOL;
        break;

    case 'all':
    default:
        $app->make('peers')->heartbeat();
        $app->make('tx')->purgeExpiredMempool();
        $peers = $app->make('peers');
        $chain = $app->make('chain');
        foreach ($peers->getActive(5) as $peer) {
            $chain->syncWithPeer($peer['hostname']);
        }
        $app->make('assets')->processAutoDividends();
        echo '[' . date('Y-m-d H:i:s') . '] Cron complete' . PHP_EOL;
        break;
}
