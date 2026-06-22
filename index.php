<?php
define('_SECURED', true);
define('BASE_PATH', __DIR__);

// ─── Autoloader ──────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $paths = [
        BASE_PATH . '/core/',
        BASE_PATH . '/controllers/',
        BASE_PATH . '/models/',
        BASE_PATH . '/library/classes/',
        BASE_PATH . '/library/sdk/',
    ];
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ─── Config ──────────────────────────────────────────────────
require_once BASE_PATH . '/strdconfig.php';

// ─── Bootstrap ───────────────────────────────────────────────
$app = SCore::boot();

// ─── Handle Request ──────────────────────────────────────────
// CLI: skip HTTP routing (used by cron/miner)
if (PHP_SAPI === 'cli') return;

$app->handleRequest();
