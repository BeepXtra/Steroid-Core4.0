<?php
defined('_SECURED') or die('Restricted access');

class SCore {
    private static array $registry = [];
    private static bool  $booted   = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        // Error handling
        set_error_handler([self::class, 'errorHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);

        // Register core services
        self::bind('db', fn() => Database::getInstance());
        self::bind('wallet',     fn() => new SWallet(self::db()));
        self::bind('chain',      fn() => new SChain(self::db()));
        self::bind('tx',         fn() => new STx(self::db()));
        self::bind('block',      fn() => new SBlock(self::db()));
        self::bind('peers',      fn() => new SPeers(self::db()));
        self::bind('mine',       fn() => new SMine(self::db()));
        self::bind('assets',     fn() => new SAssets(self::db()));
        self::bind('masternode', fn() => new SMasternode(self::db()));
        self::bind('governance', fn() => new SGovernance(self::db()));
    }

    public static function bind(string $key, callable $factory): void {
        self::$registry[$key] = $factory;
    }

    public static function make(string $key) {
        if (!isset(self::$registry[$key])) {
            throw new RuntimeException("Service not registered: $key");
        }
        return (self::$registry[$key])();
    }

    // Singleton accessor shortcuts
    private static array $singletons = [];

    public static function __callStatic(string $name, array $args) {
        if (!isset(self::$singletons[$name])) {
            self::$singletons[$name] = self::make($name);
        }
        return self::$singletons[$name];
    }

    // ── Error Handling ───────────────────────────────────────────────────────

    public static function errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool {
        self::log('error', "$errstr in $errfile:$errline", ['errno' => $errno]);
        return true;
    }

    public static function exceptionHandler(Throwable $e): void {
        self::log('exception', $e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Internal server error']);
    }

    public static function log(string $type, string $message, array $context = []): void {
        try {
            Database::getInstance()->insert('logs', [
                'type'    => $type,
                'message' => $message,
                'context' => json_encode($context),
                'date'    => time(),
            ]);
        } catch (Throwable $e) {
            // Silent fail — logging must never crash the node
            error_log("[SCore] Log write failed: " . $e->getMessage());
        }
    }

    public static function config(string $key, $default = null) {
        $row = Database::getInstance()->row(
            "SELECT cfg_val FROM config WHERE cfg_key=?", [$key]
        );
        return $row ? $row['cfg_val'] : $default;
    }

    public static function setConfig(string $key, string $value): void {
        Database::getInstance()->query(
            "INSERT INTO config (cfg_key, cfg_val) VALUES (?,?) ON DUPLICATE KEY UPDATE cfg_val=?",
            [$key, $value, $value]
        );
    }

    public static function response(bool $ok, $data = null, string $error = ''): string {
        header('Content-Type: application/json');
        if ($ok) return json_encode(['ok' => true, 'data' => $data]);
        return json_encode(['ok' => false, 'error' => $error]);
    }
}
