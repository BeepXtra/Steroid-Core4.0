<?php
defined('_SECURED') or die('Restricted access');

class SCore {
    private static $registry  = array();
    private static $singletons = array();
    private static $booted    = false;

    public static function boot() {
        if (self::$booted) return;
        self::$booted = true;

        set_error_handler(array(__CLASS__, 'errorHandler'));
        set_exception_handler(array(__CLASS__, 'exceptionHandler'));

        self::bind('db',         function() { return Database::getInstance(); });
        self::bind('wallet',     function() { return new SWallet(Database::getInstance()); });
        self::bind('chain',      function() { return new SChain(Database::getInstance()); });
        self::bind('tx',         function() { return new STx(Database::getInstance()); });
        self::bind('block',      function() { return new SBlock(Database::getInstance()); });
        self::bind('peers',      function() { return new SPeers(Database::getInstance()); });
        self::bind('mine',       function() { return new SMine(Database::getInstance()); });
        self::bind('assets',     function() { return new SAssets(Database::getInstance()); });
        self::bind('masternode', function() { return new SMasternode(Database::getInstance()); });
        self::bind('governance', function() { return new SGovernance(Database::getInstance()); });
    }

    public static function bind($key, $factory) {
        self::$registry[$key] = $factory;
    }

    public static function make($key) {
        if (!isset(self::$registry[$key])) {
            throw new RuntimeException("Service not registered: $key");
        }
        return call_user_func(self::$registry[$key]);
    }

    public static function __callStatic($name, $args) {
        if (!isset(self::$singletons[$name])) {
            self::$singletons[$name] = self::make($name);
        }
        return self::$singletons[$name];
    }

    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        self::log('error', "$errstr in $errfile:$errline", array('errno' => $errno));
        return true;
    }

    public static function exceptionHandler($e) {
        self::log('exception', $e->getMessage(), array(
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ));
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(array('ok' => false, 'error' => 'Internal server error'));
    }

    public static function log($type, $message, $context = array()) {
        try {
            Database::getInstance()->insert('logs', array(
                'type'    => $type,
                'message' => $message,
                'context' => json_encode($context),
                'date'    => time(),
            ));
        } catch (Throwable $e) {
            error_log("[SCore] Log write failed: " . $e->getMessage());
        }
    }

    public static function config($key, $default = null) {
        $row = Database::getInstance()->row(
            "SELECT cfg_val FROM config WHERE cfg_key=?", array($key)
        );
        return $row ? $row['cfg_val'] : $default;
    }

    public static function setConfig($key, $value) {
        Database::getInstance()->query(
            "INSERT INTO config (cfg_key, cfg_val) VALUES (?,?) ON DUPLICATE KEY UPDATE cfg_val=?",
            array($key, $value, $value)
        );
    }

    public static function response($ok, $data = null, $error = '') {
        header('Content-Type: application/json');
        if ($ok) return json_encode(array('ok' => true, 'data' => $data));
        return json_encode(array('ok' => false, 'error' => $error));
    }
}
