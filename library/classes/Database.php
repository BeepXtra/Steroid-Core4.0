<?php
defined('_SECURED') or die('Restricted access');

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = 'mysql:unix_socket=' . DB_SOCKET . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        );
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo() {
        return $this->pdo;
    }

    public function query($sql, $params = array()) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function row($sql, $params = array()) {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public function rows($sql, $params = array()) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function val($sql, $params = array()) {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }

    public function insert($table, $data) {
        $keys   = array_keys($data);
        $cols   = implode(',', array_map(function($k) { return "`$k`"; }, $keys));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO `$table` ($cols) VALUES ($placeholders)", array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = array()) {
        $set  = implode(',', array_map(function($k) { return "`$k`=?"; }, array_keys($data)));
        $stmt = $this->query("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    public function delete($table, $where, $params = array()) {
        return $this->query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    public function begin()    { $this->pdo->beginTransaction(); }
    public function commit()   { $this->pdo->commit(); }
    public function rollback() { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }

    public function transaction($fn) {
        $this->begin();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function count($table, $where = '1', $params = array()) {
        return (int)$this->val("SELECT COUNT(*) FROM `$table` WHERE $where", $params);
    }

    public function exists($table, $where, $params = array()) {
        return $this->count($table, $where, $params) > 0;
    }
}
