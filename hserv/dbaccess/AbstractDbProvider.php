<?php
namespace hserv\dbaccess;

use hserv\contract;
use PDO;
use PDOStatement;
use PDOException;
use RuntimeException;
use InvalidArgumentException;
use LogicException;

abstract class AbstractDbProvider implements DbProviderInterface
{
    protected PDO $pdo;
    protected ?string $currentDb = null;
    protected QueryLogger $logger;

    abstract public function serverConnect(string $serverName, int $port, string $user, string $password): void;
    abstract public function dbUse(string $dbName): void;
    abstract public function dbCreate(string $dbName): void;
    abstract public function dbDrop(string $dbName): void;
    abstract public function dbSize(string $dbName): array;
    abstract public function getDatabases(string $startsWith): array;

    public function __construct(QueryLogger $logger)
    {
        $this->logger = new QueryLogger();
    }
        
    protected function checkDbname(string $dbName): bool
    {
        return strlen($dbName) < 64 && preg_match('/^[A-Za-z0-9_\$]+$/', $dbName);
    }
    
    public function connect(string $dsn, string $user, string $password): void
    {
        try {
            $this->pdo = new PDO($dsn, $user, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if (preg_match('/(?:dbname|database)=([^;]+)/i', $dsn, $matches)) {
                $this->currentDb = $matches[1];
            } elseif (str_starts_with($dsn, 'sqlite:')) {
                $this->currentDb = basename(parse_url($dsn, PHP_URL_PATH));
            }            
            
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to connect to database: " . $e->getMessage(), 0, $e);
        }
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->currentDb = null;
        $this->logger->clear();
    }

    protected function logQuery(string $query, float $duration = 0): void
    {
        $this->logger->log($this->currentDb, $query, $duration);
    }

    public function getQueryLog(): array
    {
        return $this->logger->get();
    }

    public function setLogPath(string $path): void
    {
        $this->logger->setLogFile($path);
    }

    public function select(string $query, array $params = []): PDOStatement
    {
        $start = microtime(true);
        try {
            if (empty($params)) {
                $stmt = $this->pdo->query($query);
            } else {
                $stmt = $this->pdo->prepare($query);
                $stmt->execute($params);
            }
            $duration = microtime(true) - $start;
            $this->logQuery($query, $duration);
            return $stmt;
        } catch (PDOException $e) {
            $duration = microtime(true) - $start;
            $message = sprintf("[ERROR] (%s) %s -- QUERY: %s", $e->getCode(), $e->getMessage(), $query);
            $this->logQuery($message, $duration);
            throw new RuntimeException("Query failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function fetchValue(string $query, mixed $default = null, array $params = []): mixed
    {
        $stmt = $this->select($query, $params);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row[0] ?? $default;
    }

    public function fetchAll(string $query, bool $keyIndex = false, ?callable $callback = null, array $params = []): array
    {
        $rows = [];
        $stmt = $this->select($ $query, $params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($callback) {
                $row = $callback($row);
            }
            if ($keyIndex) {
                $key = array_key_first($row);
                $rows[$row[$key]] = $row;
            } else {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function fetchAllAsObject(string $query, string $className = 'stdClass', array $ctorArgs = [], array $params = []): array
    {
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class '$className' does not exist.");
        }
        $rows = [];
        $stmt = $this->select($query, $params);
        $stmt->setFetchMode(PDO::FETCH_CLASS, $className, $ctorArgs);
        while ($row = $stmt->fetch()) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function fetchColumn(string $query, int $columnIndex = 0, mixed $default = null, array $params = []): mixed
    {
        $stmt = $this->select($query, $params);
        $value = $stmt->fetchColumn($columnIndex);
        return $value !== false ? $value : $default;
    }

    public function upsert(string $tableName, string $tablePrefix, array $record): int
    {
        throw new LogicException("insertUpdate must be overridden in the provider class to support UPSERT.");
    }
    
    public function insertUpdate(string $tableName, string $tablePrefix, array $record): int
    {
        // Try to auto-detect tablePrefix if empty
        if ($tablePrefix === '') {
            foreach ($record as $key => $val) {
                if (preg_match('/^(.+)_ID$/', $key, $m)) {
                    $tablePrefix = $m[1];
                    break;
                }
            }
        }

        $pkField = $tablePrefix . '_ID';
        $fields = [];
        $values = [];
        $params = [];

        foreach ($record as $field => $value) {
            if (str_starts_with($field, $tablePrefix . '_') && $field !== $pkField) {
                $fields[] = $field;
                $values[] = ":$field";
                $params[":$field"] = $value;
            }
        }

        if (!array_key_exists($pkField, $record)) {
            throw new InvalidArgumentException("Primary key field '$pkField' not found in record.");
        }

        $isInsert = ((int)$record[$pkField] === 0);

        if ($isInsert) {
            $sql = "INSERT INTO $tableName (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        } else {
            $assignments = array_map(fn($f) => "$f = :$f", $fields);
            $sql = "UPDATE $tableName SET " . implode(', ', $assignments) . " WHERE $pkField = :pk";
            $params[':pk'] = $record[$pkField];
        }

        $stmt = $this->select($sql, $params);
        return $isInsert ? (int)$this->pdo->lastInsertId() : $stmt->rowCount();
    }    

    public function delete(string $tableName, string $tablePrefix, array $rec_ID): int
    {
        $pkField = $tablePrefix . '_ID';
        if (empty($rec_ID)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($rec_ID), '?'));
        $sql = "DELETE FROM $tableName WHERE $pkField IN ($placeholders)";
        $stmt = $this->select($sql, $rec_ID);
        return $stmt->rowCount();
    }
}
