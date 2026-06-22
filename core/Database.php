<?php
defined('_SECURED') or die('Restricted access');

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct(Config $cfg) {
        $dsn = "mysql:host={$cfg->db_hostname};port={$cfg->db_port};dbname={$cfg->db_name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci,
                                             time_zone = '+00:00',
                                             sql_mode  = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE'",
        ];
        $this->pdo = new PDO($dsn, $cfg->db_username, $cfg->db_password, $options);
    }

    public static function getInstance(Config $cfg = null): self {
        if (self::$instance === null) {
            if ($cfg === null) throw new RuntimeException('Database: Config required on first call');
            self::$instance = new self($cfg);
        }
        return self::$instance;
    }

    // ─── Query helpers ───────────────────────────────────────

    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $r = $this->query($sql, $params)->fetch();
        return $r ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [], int $col = 0): mixed {
        return $this->query($sql, $params)->fetchColumn($col);
    }

    public function insert(string $table, array $data): string {
        $cols   = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
        $places = implode(',', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO `$table` ($cols) VALUES ($places)", array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function upsert(string $table, array $data, array $updateCols = []): void {
        $cols    = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
        $places  = implode(',', array_fill(0, count($data), '?'));
        $updates = empty($updateCols)
            ? implode(',', array_map(fn($k) => "`$k`=VALUES(`$k`)", array_keys($data)))
            : implode(',', array_map(fn($k) => "`$k`=VALUES(`$k`)", $updateCols));
        $this->query(
            "INSERT INTO `$table` ($cols) VALUES ($places) ON DUPLICATE KEY UPDATE $updates",
            array_values($data)
        );
    }

    public function execute(string $sql, array $params = []): int {
        return $this->query($sql, $params)->rowCount();
    }

    // ─── Transactions ────────────────────────────────────────

    public function beginTransaction(): void   { $this->pdo->beginTransaction(); }
    public function commit(): void             { $this->pdo->commit(); }
    public function rollback(): void           { $this->pdo->rollBack(); }

    public function transaction(callable $fn): mixed {
        $this->beginTransaction();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function getPdo(): PDO { return $this->pdo; }
}
