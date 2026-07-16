<?php

declare(strict_types=1);

final class SemanticEntityImporter
{
    /** @var array<string,array<string,int>> */
    private array $groupIds = [];

    public function __construct(
        private TargetRepository $targetRepo,
        private SemanticMapRepository $semanticRepo,
        private bool $isSingleGroup = false
    ) {}

    public function importEntityRow(
        string $type,
        array $row,
        int $currentRegisteredId,
        string $currentDbName,
        bool $neutraliseReferences = false
    ): ?int {
        $spec = ENTITY_SPECS[$type];
        $origin = sourceOrigin($row, $spec, $currentRegisteredId);
        if (!$origin) {
            logWarning("{$currentDbName}: skipping {$type} row with no usable origin");
            return null;
        }

        $entityType = strtolower($type);
        $existing = $this->semanticRepo->findSemanticTargetId($entityType, $origin['db'], $origin['id']);
        if ($existing !== null) {
            return $existing;
        }

        $existingTarget = $this->targetRepo->findExistingTargetId($spec, $origin['db'], $origin['id']);
        if ($existingTarget !== null) {
            $this->semanticRepo->recordSemantic(
                $entityType,
                $existingTarget,
                $origin['db'],
                $origin['id'],
                $currentRegisteredId,
                $currentDbName,
                toInt($row[$spec['pk']] ?? 0) ?: null,
                $origin['db'] === $currentRegisteredId,
                $origin['db'] !== $currentRegisteredId
            );
            return $existingTarget;
        }

        $prepared = prepareBaseRow($row, $spec, $origin, $currentRegisteredId);
        $prepared = $this->ensureUniqueConceptNameForInsert($type, $spec, $origin, $prepared, $currentDbName);

        $groupSpec = $this->resolveGroupSpec($origin, $currentRegisteredId, $currentDbName);

        if ($type === 'RTY') {
            $prepared['rty_RecTypeGroupID'] = $this->getGroupId($groupSpec['name'], $groupSpec['registeredId'], 'rty');
        } elseif ($type === 'DTY') {
            $prepared['dty_DetailTypeGroupID'] = $this->getGroupId($groupSpec['name'], $groupSpec['registeredId'], 'dty');
            if ($neutraliseReferences) {
                unset($prepared['dty_JsonTermIDTree'], $prepared['dty_PtrTargetRectypeIDs']);
            }
        } elseif ($type === 'TRM') {
            $domain = normaliseTermDomain((string)($row['trm_Domain'] ?? 'enum'));
            $prepared['trm_VocabularyGroupID'] = $this->getGroupId($groupSpec['name'], $groupSpec['registeredId'], 'trm', $domain);
            if ($neutraliseReferences) {
                $prepared['trm_ParentTermID'] = null;
                $prepared['trm_InverseTermID'] = null;
            }
        }

        $targetId = $this->targetRepo->insertRow($spec['table'], $prepared);
        $this->semanticRepo->recordSemantic(
            $entityType,
            $targetId,
            $origin['db'],
            $origin['id'],
            $currentRegisteredId,
            $currentDbName,
            toInt($row[$spec['pk']] ?? 0) ?: null,
            $origin['db'] === $currentRegisteredId,
            $origin['db'] !== $currentRegisteredId
        );

        logConceptAction($currentDbName, $type, $spec, $prepared, 'INSERTED');
        return $targetId;
    }

    /** @return array{name:string,registeredId:int} */
    private function resolveGroupSpec(array $origin, int $currentRegisteredId, string $currentDbName): array
    {
        if ($this->isSingleGroup) {
            return ['name' => 'Imported definitions', 'registeredId' => 0];
        }

        // Heurist core definitions are intentionally consolidated into one
        // group, even when a core-origin concept is first encountered through
        // another database, or a non-core-origin concept is available from the
        // core definitions database as a derived copy.
        if ($origin['db'] === 2 || $currentRegisteredId === 2 || $this->isHeuristCoreDefinitionsDbName($currentDbName)) {
            return ['name' => 'Heurist_Core_Definitions', 'registeredId' => 2];
        }

        if ($origin['db'] === $currentRegisteredId) {
            return ['name' => $currentDbName, 'registeredId' => $currentRegisteredId];
        }

        // Derived/fallback imports are grouped by the database where the
        // definition was actually found. The origin DB remains visible in the
        // group name, while the [DB###] suffix records imported-via DB ID.
        return [
            'name' => 'Origin_DB_' . $origin['db'] . '_via_' . $currentDbName,
            'registeredId' => $currentRegisteredId,
        ];
    }

    private function isHeuristCoreDefinitionsDbName(string $dbName): bool
    {
        return strcasecmp($dbName, 'Heurist_Core_Definitions') === 0
            || strcasecmp($dbName, 'hdb_Heurist_Core_Definitions') === 0;
    }

    private function getGroupId(string $dbName, int $registeredId, string $kind, ?string $domain = null): int
    {
        $domain = $kind === 'trm' ? normaliseTermDomain((string)($domain ?? 'enum')) : '';
        $key = $kind . ':' . $domain;
        $dbKey = $this->isSingleGroup ? 'single' : ($registeredId . ':' . $dbName);
        if (isset($this->groupIds[$dbKey][$key])) {
            return $this->groupIds[$dbKey][$key];
        }

        if ($this->isSingleGroup) {
            if ($kind === 'rty') {
                $id = $this->targetRepo->ensureSingleImportedRecTypeGroup();
            } elseif ($kind === 'dty') {
                $id = $this->targetRepo->ensureSingleImportedDetailTypeGroup();
            } elseif ($kind === 'trm') {
                $id = $this->targetRepo->ensureSingleImportedVocabularyGroup($domain);
            } else {
                throw new RuntimeException("Unknown group kind {$kind}");
            }
            $this->groupIds[$dbKey][$key] = $id;
            return $id;
        }

        if ($kind === 'rty') {
            $id = $this->targetRepo->ensureRecTypeGroupForSourceDatabase($dbName, $registeredId);
        } elseif ($kind === 'dty') {
            $id = $this->targetRepo->ensureDetailTypeGroupForSourceDatabase($dbName, $registeredId);
        } elseif ($kind === 'trm') {
            $id = $this->targetRepo->ensureVocabularyGroupForSourceDatabase($dbName, $registeredId, $domain);
        } else {
            throw new RuntimeException("Unknown group kind {$kind}");
        }

        $this->groupIds[$dbKey][$key] = $id;
        return $id;
    }

    private function ensureUniqueConceptNameForInsert(string $type, array $spec, array $origin, array $prepared, string $dbName): array
    {
        if (!in_array($type, ['RTY', 'DTY'], true)) {
            return $prepared;
        }

        $nameField = $spec['name_field'] ?? null;
        if (!$nameField || !isset($prepared[$nameField])) {
            return $prepared;
        }

        $baseName = trim((string)$prepared[$nameField]);
        if ($baseName === '' || !$this->targetRepo->columnValueExists($spec['table'], $nameField, $baseName)) {
            return $prepared;
        }

        $suffix = sprintf(' [%s%d-%d]', $type, $origin['db'], $origin['id']);
        $candidate = makeUniqueNameCandidate($this->targetRepo, $spec['table'], $nameField, $baseName, $suffix, conceptNameMaxLength($type));
        $prepared[$nameField] = $candidate;
        logWarning(sprintf('%s renamed %s duplicate name "%s" to "%s" for %s%d-%d', $dbName, $type, $baseName, $candidate, $type, $origin['db'], $origin['id']));

        return $prepared;
    }
}
