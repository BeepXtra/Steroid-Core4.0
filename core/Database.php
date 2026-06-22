<?php
defined('_SECURED') or die('Restricted access');

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct($cfg) {
        $dsn = "mysql:host={$cfg->db_hostname};port={$cfg->db_port};dbname={$cfg->db_name};charset=utf8mb4";
        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'",
        );
        $this->pdo = new PDO($dsn, $cfg->db_username, $cfg->db_password, $options);
    }

    public static function getInstance($cfg = null) {
        if (self::$instance === null) {
            if ($cfg === null) throw new RuntimeException('Database: Config required on first call');
            self::$instance = new self($cfg);
        }
        return self::$instance;
    }

    public function query($sql, $params = array()) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne($sql, $params = array()) {
        $r = $this->query($sql, $params)->fetch();
        return $r ?: null;
    }

    public function fetchAll($sql, $params = array()) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn($sql, $params = array(), $col = 0) {
        $r = $this->query($sql, $params)->fetchColumn($col);
        return $r !== false ? $r : null;
    }

    public function insert($table, $data) {
        $cols   = implode(',', array_map(function($k) { return "`$k`"; }, array_keys($data)));
        $places = implode(',', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO `$table` ($cols) VALUES ($places)", array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function upsert($table, $data, $updateCols = array()) {
        $cols    = implode(',', array_map(function($k) { return "`$k`"; }, array_keys($data)));
        $places  = implode(',', array_fill(0, count($data), '?'));
        if (empty($updateCols)) {
            $updates = implode(',', array_map(function($k) { return "`$k`=VALUES(`$k`)"; }, array_keys($data)));
        } else {
            $updates = implode(',', array_map(function($k) { return "`$k`=VALUES(`$k`)"; }, $updateCols));
        }
        $this->query(
            "INSERT INTO `$table` ($cols) VALUES ($places) ON DUPLICATE KEY UPDATE $updates",
            array_values($data)
        );
    }

    public function execute($sql, $params = array()) {
        return $this->query($sql, $params)->rowCount();
    }

    public function beginTransaction() { $this->pdo->beginTransaction(); }
    public function commit()           { $this->pdo->commit(); }
    public function rollback()         { $this->pdo->rollBack(); }

    public function transaction($fn) {
        $this->beginTransaction();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getPdo() { return $this->pdo; }
}
