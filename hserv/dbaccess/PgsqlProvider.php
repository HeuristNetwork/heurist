<?php
namespace hserv\dbaccess;

use PDO;

class PgsqlProvider extends AbstractDbProvider
{
    
    public function serverConnect(string $serverName, int $port, string $user, string $password): void
    {
        $this->pdo = new PDO("pgsql:host=$serverName;port=$port", $user, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function dbUse(string $dbName): void
    {
        if (!$this->checkDbname($dbName)) {
            throw new InvalidArgumentException("Invalid database name");
        }
        $dsn = $this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        $this->pdo = new PDO("pgsql:host=$dsn;dbname=$dbName");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->currentDb = $dbName;
    }

    public function dbCreate(string $dbName): void
    {
        if (!$this->checkDbname($dbName)) {
            throw new InvalidArgumentException("Invalid database name");
        }
        $this->pdo->exec("CREATE DATABASE \"$dbName\"");
    }

    public function dbDrop(string $dbName): void
    {
        if (!$this->checkDbname($dbName)) {
            throw new InvalidArgumentException("Invalid database name");
        }
        $this->pdo->exec("DROP DATABASE \"$dbName\"");
    }

    public function dbSize(string $dbName): array
    {
        $stmt = $this->pdo->query("SELECT pg_database_size('$dbName') AS size");
        $size = $stmt->fetch(PDO::FETCH_ASSOC)['size'];
        return ['size' => $size, 'rows_count' => null];
    }

    public function getDatabases(string $startsWith): array
    {
        $stmt = $this->pdo->query("SELECT datname FROM pg_database WHERE datistemplate = false");
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
        if (!isset($record[$pkField])) {
            throw new \InvalidArgumentException("Missing primary key $pkField");
        }

        $fields = [];
        $placeholders = [];
        $updates = [];
        $params = [];

        foreach ($record as $field => $value) {
            if (str_starts_with($field, $tablePrefix . '_')) {
                $fields[] = $field;
                $placeholders[] = ":$field";
                $updates[] = "$field = EXCLUDED.$field";
                $params[":$field"] = $value;
            }
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s",
            $tableName,
            implode(', ', $fields),
            implode(', ', $placeholders),
            $pkField,
            implode(', ', $updates)
        );

        $stmt = $this->select($sql, $params);
        return $stmt->rowCount();
    }
}
