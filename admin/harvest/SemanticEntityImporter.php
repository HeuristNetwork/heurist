<?php

declare(strict_types=1);

final class SemanticEntityImporter
{
    /** @var array<string,array<string,int>> */
    private array $groupIds = [];

    public function __construct(
        private TargetRepository $targetRepo,
        private SemanticMapRepository $semanticRepo
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

        $groupDbName = $origin['db'] === $currentRegisteredId
            ? $currentDbName
            : ('Origin_DB_' . $origin['db'] . '_via_' . $currentDbName);
        $groupRegisteredId = (int)$origin['db'];

        if ($type === 'RTY') {
            $prepared['rty_RecTypeGroupID'] = $this->getGroupId($groupDbName, $groupRegisteredId, 'rty');
        } elseif ($type === 'DTY') {
            $prepared['dty_DetailTypeGroupID'] = $this->getGroupId($groupDbName, $groupRegisteredId, 'dty');
            if ($neutraliseReferences) {
                unset($prepared['dty_JsonTermIDTree'], $prepared['dty_PtrTargetRectypeIDs']);
            }
        } elseif ($type === 'TRM') {
            $domain = normaliseTermDomain((string)($row['trm_Domain'] ?? 'enum'));
            $prepared['trm_VocabularyGroupID'] = $this->getGroupId($groupDbName, $groupRegisteredId, 'trm', $domain);
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

    private function getGroupId(string $dbName, int $registeredId, string $kind, ?string $domain = null): int
    {
        $key = $kind . ':' . ($domain ?? '');
        $dbKey = (string)$registeredId;
        if (isset($this->groupIds[$dbKey][$key])) {
            return $this->groupIds[$dbKey][$key];
        }

        if ($kind === 'rty') {
            $id = $this->targetRepo->ensureRecTypeGroupForSourceDatabase($dbName, $registeredId);
        } elseif ($kind === 'dty') {
            $id = $this->targetRepo->ensureDetailTypeGroupForSourceDatabase($dbName, $registeredId);
        } elseif ($kind === 'trm') {
            $id = $this->targetRepo->ensureVocabularyGroupForSourceDatabase($dbName, $registeredId, normaliseTermDomain((string)($domain ?? 'enum')));
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
