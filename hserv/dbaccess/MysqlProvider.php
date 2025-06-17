<?php
namespace hserv\dbaccess;

use PDO;

class MysqlProvider extends AbstractDbProvider
{
    public function serverConnect(string $serverName, int $port, string $user, string $password): void
    {
        $this->pdo = new PDO("mysql:host=$serverName;port=$port", $user, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function dbUse(string $dbName): void
    {
        if (!$this->checkDbname($dbName)) {
            throw new InvalidArgumentException("Invalid database name");
        }
        $this->pdo->exec("USE `$dbName`");
        $this->currentDb = $dbName;
    }

    public function dbCreate(string $dbName): void
    {
        if (!$this->checkDbname($dbName)) {
            throw new InvalidArgumentException("Invalid database name");
        }
        $this->pdo->exec("CREATE DATABASE `$dbName`");
    }

    public function dbDrop(string $dbName): void
    {
        if (!$this->checkDbname($dbName)) {
            throw new InvalidArgumentException("Invalid database name");
        }
        $this->pdo->exec("DROP DATABASE `$dbName`");
    }

    public function dbSize(string $dbName): array
    {
        $stmt = $this->pdo->query("SELECT table_schema, SUM(data_length + index_length) AS size, SUM(table_rows) AS rows_count FROM information_schema.tables WHERE table_schema = '$dbName'");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDatabases(string $startsWith): array
    {
        $stmt = $this->pdo->query("SHOW DATABASES");
        $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_filter($dbs, fn($db) => str_starts_with($db, $startsWith));
    }
    
    public function upsert(string $tableName, string $tablePrefix, array $record): int
    {
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
        $placeholders = [];
        $updates = [];
        $params = [];

        foreach ($record as $field => $value) {
            if (str_starts_with($field, $tablePrefix . '_')) {
                $fields[] = $field;
                $placeholders[] = ":$field";
                $updates[] = "$field = VALUES($field)";
                $params[":$field"] = $value;
            }
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s",
            $tableName,
            implode(', ', $fields),
            implode(', ', $placeholders),
            implode(', ', $updates)
        );

        $stmt = $this->select($sql, $params);
        return (int)$this->pdo->lastInsertId();
    }
}
