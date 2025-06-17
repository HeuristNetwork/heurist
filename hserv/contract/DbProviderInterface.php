<?php

namespace hserv\contract;

use PDOStatement;

interface DbProviderInterface
{
    public function connect(string $dsn, string $user, string $password): void;
    public function serverConnect(string $serverName, int $port, string $user, string $password): void;
    public function disconnect(): void;

    public function dbUse(string $dbName): void;
    public function dbCreate(string $dbName): void;
    public function dbDrop(string $dbName): void;
    public function dbSize(string $dbName): array;
    public function getDatabases(string $startsWith): array;
    public function getQueryLog(): array;
    public function setLogPath(string $path): void;
    public function clearQueryLog(): void;

    public function select(string $query, array $params = []): PDOStatement;
    public function fetchValue(string $query, mixed $default = null, array $params = []): mixed;
    public function fetchAll(string $query, bool $keyIndex = false, ?callable $callback = null, array $params = []): array;
    public function fetchAllAsObject(string $query, string $className = 'stdClass', array $ctorArgs = [], array $params = []): array;
    public function fetchColumn(string $query, int $columnIndex = 0, mixed $default = null, array $params = []): mixed;
    public function upsert(string $tableName, string $tablePrefix, array $record): int;
    public function insertUpdate(string $tableName, string $tablePrefix, array $record): int;
    public function delete(string $tableName, string $tablePrefix, array $rec_ID): int;
}
