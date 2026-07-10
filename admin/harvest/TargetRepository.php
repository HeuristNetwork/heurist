<?php

declare(strict_types=1);

final class TargetRepository
{
    private PDO $pdo;
    /** @var array<string,array<int,string>> */
    private array $columns = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        foreach (array_column(ENTITY_SPECS, 'table') as $table) {
            $this->columns[$table] = $this->getTableColumns($table);
        }
        foreach (['defRecTypeGroups', 'defDetailTypeGroups', 'defVocabularyGroups'] as $table) {
            $this->columns[$table] = $this->getTableColumns($table);
        }
    }

    public function ensureRecTypeGroupForSourceDatabase(string $dbName, int $registeredId): int
    {
        $groupName = $this->makeUniqueBoundedGroupName('defRecTypeGroups', 'rtg_Name', $dbName, " [DB{$registeredId}]", 40);
        return $this->ensureGroup('defRecTypeGroups', 'rtg_ID', 'rtg_Name', $groupName, [
            'rtg_Domain' => 'functionalgroup',
            'rtg_Description' => "Harvested concepts from {$dbName}",
        ]);
    }

    public function ensureDetailTypeGroupForSourceDatabase(string $dbName, int $registeredId): int
    {
        $groupName = $this->makeUniqueBoundedGroupName('defDetailTypeGroups', 'dtg_Name', $dbName, " [DB{$registeredId}]", 63);
        return $this->ensureGroup('defDetailTypeGroups', 'dtg_ID', 'dtg_Name', $groupName, [
            'dtg_Description' => "Harvested concepts from {$dbName}",
        ]);
    }

    public function ensureVocabularyGroupForSourceDatabase(string $dbName, int $registeredId, string $domain): int
    {
        $domain = normaliseTermDomain($domain);
        $suffix = $domain === 'relation' ? " [DB{$registeredId} rel]" : " [DB{$registeredId} enum]";
        $groupName = $this->makeUniqueBoundedGroupName('defVocabularyGroups', 'vcg_Name', $dbName, $suffix, 40);
        return $this->ensureGroup('defVocabularyGroups', 'vcg_ID', 'vcg_Name', $groupName, [
            'vcg_Domain' => $domain,
            'vcg_Description' => $domain === 'relation'
                ? "Harvested relation vocabularies from {$dbName}"
                : "Harvested enum vocabularies from {$dbName}",
        ]);
    }

    public function ensureGroupsForSourceDatabase(string $dbName, int $registeredId): array
    {
        $rtyGroupName = $this->makeUniqueBoundedGroupName('defRecTypeGroups', 'rtg_Name', $dbName, " [DB{$registeredId}]", 40);
        $dtyGroupName = $this->makeUniqueBoundedGroupName('defDetailTypeGroups', 'dtg_Name', $dbName, " [DB{$registeredId}]", 63);
        $enumGroupName = $this->makeUniqueBoundedGroupName('defVocabularyGroups', 'vcg_Name', $dbName, " [DB{$registeredId} enum]", 40);
        $relationGroupName = $this->makeUniqueBoundedGroupName('defVocabularyGroups', 'vcg_Name', $dbName, " [DB{$registeredId} rel]", 40);

        return [
            'rty' => $this->ensureGroup('defRecTypeGroups', 'rtg_ID', 'rtg_Name', $rtyGroupName, [
                'rtg_Domain' => 'functionalgroup',
                'rtg_Description' => "Harvested concepts from {$dbName}",
            ]),
            'dty' => $this->ensureGroup('defDetailTypeGroups', 'dtg_ID', 'dtg_Name', $dtyGroupName, [
                'dtg_Description' => "Harvested concepts from {$dbName}",
            ]),
            'trm' => [
                'enum' => $this->ensureGroup('defVocabularyGroups', 'vcg_ID', 'vcg_Name', $enumGroupName, [
                    'vcg_Domain' => 'enum',
                    'vcg_Description' => "Harvested enum vocabularies from {$dbName}",
                ]),
                'relation' => $this->ensureGroup('defVocabularyGroups', 'vcg_ID', 'vcg_Name', $relationGroupName, [
                    'vcg_Domain' => 'relation',
                    'vcg_Description' => "Harvested relation vocabularies from {$dbName}",
                ]),
            ],
        ];
    }

    public function findExistingTargetId(array $spec, int $originDb, int $originId): ?int
    {
        $sql = sprintf(
            'SELECT `%s` FROM `%s` WHERE `%s` = :originDb AND `%s` = :originId LIMIT 1',
            $spec['pk'],
            $spec['table'],
            $spec['origin_db'],
            $spec['origin_id']
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':originDb', $originDb, PDO::PARAM_INT);
        $stmt->bindValue(':originId', $originId, PDO::PARAM_INT);
        $stmt->execute();
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int)$value;
    }

    public function findExistingRecStructureId(int $recTypeId, int $detailTypeId): ?int
    {
        $sql = 'SELECT `rst_ID` FROM `defRecStructure` WHERE `rst_RecTypeID` = :rty AND `rst_DetailTypeID` = :dty LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':rty', $recTypeId, PDO::PARAM_INT);
        $stmt->bindValue(':dty', $detailTypeId, PDO::PARAM_INT);
        $stmt->execute();
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int)$value;
    }

    public function insertRow(string $table, array $row): int
    {
        $filtered = $this->filterColumns($table, $row);
        if (!$filtered) {
            throw new RuntimeException("No matching columns to insert into {$table}");
        }

        $columns = array_keys($filtered);
        $placeholders = array_map(fn(string $c): string => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn(string $c): string => "`{$c}`", $columns)),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        foreach ($filtered as $col => $val) {
            $stmt->bindValue(':' . $col, $val, pdoParamType($val));
        }
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function updateByPk(string $table, string $pk, int $pkValue, array $updates): void
    {
        $filtered = $this->filterColumns($table, $updates);
        unset($filtered[$pk]);
        if (!$filtered) {
            return;
        }

        $assignments = [];
        foreach (array_keys($filtered) as $col) {
            $assignments[] = "`{$col}` = :{$col}";
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE `%s` = :__pk', $table, implode(', ', $assignments), $pk);
        $stmt = $this->pdo->prepare($sql);
        foreach ($filtered as $col => $val) {
            $stmt->bindValue(':' . $col, $val, pdoParamType($val));
        }
        $stmt->bindValue(':__pk', $pkValue, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getColumnsByPk(string $table, string $pk, int $pkValue, array $fields): array
    {
        $allowed = array_flip($this->columns[$table] ?? []);
        if (!isset($allowed[$pk])) {
            throw new RuntimeException("Unknown primary key column {$table}.{$pk}");
        }

        $columns = [];
        foreach ($fields as $field) {
            if (!is_string($field) || !isset($allowed[$field])) {
                throw new RuntimeException("Unknown column {$table}.{$field}");
            }
            $columns[] = $field;
        }

        if (!$columns) {
            return [];
        }

        $sql = sprintf(
            'SELECT %s FROM `%s` WHERE `%s` = :__pk LIMIT 1',
            implode(', ', array_map(fn(string $field): string => "`{$field}`", $columns)),
            $table,
            $pk
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':__pk', $pkValue, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    public function columnValueExists(string $table, string $field, string $value): bool
    {
        $allowed = array_flip($this->columns[$table] ?? []);
        if (!isset($allowed[$field])) {
            throw new RuntimeException("Unknown column {$table}.{$field}");
        }

        $sql = "SELECT 1 FROM `{$table}` WHERE `{$field}` = :value LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    public function deleteAllTermLinks(): void
    {
        // Use DELETE rather than TRUNCATE so this remains safe inside the
        // transaction controlled by SemanticGlobalHarvester. MySQL TRUNCATE
        // performs an implicit commit and can leave PDO with no active
        // transaction for the following commit()/rollBack().
        $this->pdo->exec('DELETE FROM `defTermsLinks`');
    }

    public function rebuildTermLinksFromParentIds(): void
    {
        // Build defTermsLinks from the canonical target-local parent IDs stored in
        // defTerms after all TRM parent repairs have completed. This makes the
        // hierarchy table deterministic and avoids relying on legacy triggers.
        $this->deleteAllTermLinks();
        $this->pdo->exec(
            'INSERT IGNORE INTO `defTermsLinks` (`trl_ParentID`, `trl_TermID`) ' .
            'SELECT `trm_ParentTermID`, `trm_ID` FROM `defTerms` ' .
            'WHERE `trm_ParentTermID` IS NOT NULL AND `trm_ParentTermID` > 0'
        );
    }

    private function makeUniqueBoundedGroupName(string $table, string $nameField, string $baseName, string $suffix, int $maxLength): string
    {
        $baseName = trim($baseName);
        $candidate = truncateWithSuffix($baseName, $suffix, $maxLength);

        // This name is deterministic for the source DB because the suffix contains
        // the registered DB ID. On repeat runs, return the same candidate so
        // ensureGroup() can reuse the existing row instead of creating #2/#3/etc.
        if ($candidate !== $baseName && $candidate !== trim($baseName . ' ' . trim($suffix))) {
//            logWarning("Shortened group name for {$table}.{$nameField}: {$baseName} -> {$candidate}");
        }

        return $candidate;
    }

    private function ensureGroup(string $table, string $pk, string $nameField, string $name, array $extra): int
    {
        $stmt = $this->pdo->prepare("SELECT `{$pk}` FROM `{$table}` WHERE `{$nameField}` = :name LIMIT 1");
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int)$existing;
        }

        $row = array_merge([$nameField => $name], $extra);
        return $this->insertRow($table, $row);
    }

    private function filterColumns(string $table, array $row): array
    {
        $allowed = array_flip($this->columns[$table] ?? []);
        $filtered = [];
        foreach ($row as $col => $val) {
            if (isset($allowed[$col])) {
                $filtered[$col] = $val;
            }
        }
        return $filtered;
    }

    private function getTableColumns(string $table): array
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
        $cols = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols[] = $row['Field'];
        }
        return $cols;
    }
}
