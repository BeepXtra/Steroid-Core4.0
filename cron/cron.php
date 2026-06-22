<?php
define('_SECURED', true);
define('CRON_MODE', true);

require_once dirname(__DIR__) . '/strdconfig.php';
require_once dirname(__DIR__) . '/library/classes/Database.php';
require_once dirname(__DIR__) . '/library/classes/SCore.php';
require_once dirname(__DIR__) . '/library/classes/SWallet.php';
require_once dirname(__DIR__) . '/library/classes/STx.php';
require_once dirname(__DIR__) . '/library/classes/SBlock.php';
require_once dirname(__DIR__) . '/library/classes/SChain.php';
require_once dirname(__DIR__) . '/library/classes/SPeers.php';
require_once dirname(__DIR__) . '/library/classes/SMine.php';
require_once dirname(__DIR__) . '/library/classes/SAssets.php';
require_once dirname(__DIR__) . '/library/classes/SMasternode.php';
require_once dirname(__DIR__) . '/library/classes/SGovernance.php';

SCore::boot();
$db = Database::getInstance();

$task = $argv[1] ?? 'all';

switch ($task) {
    case 'mempool':
        $cleaned = (new STx($db))->cleanMempool();
        echo "Mempool: cleaned $cleaned stale transactions\n";
        break;

    case 'peers':
        $peers = new SPeers($db);
        $all   = $peers->getActive(50);
        $ok = $fail = 0;
        foreach ($all as $peer) {
            $peers->ping($peer['address'], (int)$peer['port']) ? $ok++ : $fail++;
        }
        $peers->sweepDead();
        echo "Peers: $ok OK, $fail failed\n";
        break;

    case 'masternodes':
        $swept = (new SMasternode($db))->sweepInactive();
        echo "Masternodes: swept $swept inactive\n";
        break;

    case 'heartbeat':
        (new SPeers($db))->heartbeat();
        echo "Heartbeat recorded\n";
        break;

    case 'governance':
        $gov    = new SGovernance($db);
        $params = SGovernance::PARAMS;
        foreach ($params as $param) {
            if ($gov->checkAndApply($param)) {
                echo "Governance: applied param $param\n";
            }
        }
        break;

    case 'all':
    default:
        // Mempool
        $cleaned = (new STx($db))->cleanMempool();
        echo "Mempool: cleaned $cleaned stale\n";

        // Peers
        $peers = new SPeers($db);
        $peers->sweepDead();
        $peers->heartbeat();
        echo "Peers: swept + heartbeat\n";

        // Masternodes
        $swept = (new SMasternode($db))->sweepInactive();
        echo "Masternodes: swept $swept\n";

        // Governance tally
        $gov = new SGovernance($db);
        foreach (SGovernance::PARAMS as $param) {
            if ($gov->checkAndApply($param)) echo "Governance: applied $param\n";
        }

        echo "Cron complete: " . date('Y-m-d H:i:s') . "\n";
        break;
}
