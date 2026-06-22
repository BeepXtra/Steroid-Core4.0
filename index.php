<?php
define('_SECURED', true);

require_once __DIR__ . '/strdconfig.php';
require_once __DIR__ . '/library/classes/Database.php';
require_once __DIR__ . '/library/classes/SCore.php';
require_once __DIR__ . '/library/classes/SWallet.php';
require_once __DIR__ . '/library/classes/STx.php';
require_once __DIR__ . '/library/classes/SBlock.php';
require_once __DIR__ . '/library/classes/SChain.php';
require_once __DIR__ . '/library/classes/SPeers.php';
require_once __DIR__ . '/library/classes/SMine.php';
require_once __DIR__ . '/library/classes/SAssets.php';
require_once __DIR__ . '/library/classes/SMasternode.php';
require_once __DIR__ . '/library/classes/SGovernance.php';
require_once __DIR__ . '/controllers/ApiController.php';
require_once __DIR__ . '/controllers/BeepXtraController.php';
require_once __DIR__ . '/controllers/ExplorerController.php';

SCore::boot();
$db = Database::getInstance();

$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = strtok($uri, '?');
$parts  = array_filter(explode('/', trim($uri, '/')));
$parts  = array_values($parts);

$segment0 = $parts[0] ?? '';
$segment1 = $parts[1] ?? '';
$segment2 = $parts[2] ?? '';

$params = array_merge($_GET, $_POST);

switch ($segment0) {
    case 'api':
        (new ApiController($db))->handle($segment1, $params, $method);
        break;

    case 'beepxtra':
        (new BeepXtraController($db))->handle($segment1, $params);
        break;

    case 'explorer':
        (new ExplorerController($db))->handle($segment1, $params);
        break;

    default:
        // Root — return node status
        header('Content-Type: application/json');
        echo json_encode([
            'ok'   => true,
            'node' => CHAIN_NAME,
            'version' => NODE_VERSION,
            'host' => NODE_HOST,
            'status' => (new SChain($db))->getStatus(),
        ]);
        break;
}
