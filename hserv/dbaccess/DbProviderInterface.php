<?php

namespace hserv\contract;

use PDOStatement;

interface DbProviderInterface
{
    public function select(string $query, array $params = []): PDOStatement;
    public function fetchValue(string $query, mixed $default = null, array $params = []): mixed;
    public function fetchAll(string $query, bool $keyIndex = false, ?callable $callback = null, array $params = []): array;
    public function fetchAllAsObject(string $query, string $className = 'stdClass', array $ctorArgs = [], array $params = []): array;
    public function fetchColumn(string $query, int $columnIndex = 0, mixed $default = null, array $params = []): mixed;
    public function insertUpdate(string $tableName, string $tablePrefix, array $record): int;
    public function delete(string $tableName, string $tablePrefix, array $rec_ID): int;
}
?>
