<?php
namespace hserv\dbaccess;

use PDO;

class SqliteProvider extends AbstractDbProvider
{
    public function serverConnect(string $serverName, int $port, string $user, string $password): void
    {
        $this->pdo = new PDO("sqlite:$serverName");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->currentDb = $serverName;
    }

    public function dbUse(string $dbName): void
    {
        if (!$this->checkDbname($dbName)) {
            throw new InvalidArgumentException("Invalid database name");
        }
        $this->pdo = new PDO("sqlite:$dbName");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->currentDb = $dbName;
    }

    public function dbCreate(string $dbName): void
    {
        if (!$this->checkDbname($dbName)) {
            throw new InvalidArgumentException("Invalid database name");
        }
        new PDO("sqlite:$dbName");
    }

    public function dbDrop(string $dbName): void
    {
        if (!$this->checkDbname($dbName)) {
            throw new InvalidArgumentException("Invalid database name");
        }
        unlink($dbName);
    }

    public function dbSize(string $dbName): array
    {
        $filesize = file_exists($dbName) ? filesize($dbName) : 0;
        return ['size' => $filesize, 'rows_count' => null];
    }

    public function getDatabases(string $startsWith): array
    {
        return [];
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
                $updates[] = "$field = excluded.$field";
                $params[":$field"] = $value;
            }
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) ON CONFLICT(%s) DO UPDATE SET %s",
            $tableName,
            implode(', ', $fields),
            implode(', ', $placeholders),
            $pkField,
            implode(', ', $updates)
        );

        $stmt = $this->select($sql, $params);
        return (int)$this->pdo->lastInsertId();
    }
}
