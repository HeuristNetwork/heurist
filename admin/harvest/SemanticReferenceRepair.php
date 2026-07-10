<?php

declare(strict_types=1);

final class SemanticReferenceRepair
{
    public function __construct(
        private ApiClient $client,
        private TargetRepository $targetRepo,
        private SemanticMapRepository $semanticRepo
    ) {}

    /**
     * Rescan source rows and repair references after all RTY/DTY/TRM rows have target-local IDs.
     *
     * This intentionally resolves source-local references from the full row set fetched for the
     * same source database. Do not fetch each referenced RTY/TRM separately: some API filters are
     * not reliable for single-definition lookups, and row-by-row HTTP calls are very slow.
     */
    public function repairDtyReferences(array $databaseRefs): void
    {
        logLine('Reference repair: DTY pointer targets and term trees');

        $rtyTargetMap = $this->semanticRepo->loadTargetIdMap('rty');
        $dtyTargetMap = $this->semanticRepo->loadTargetIdMap('dty');
        $trmTargetMap = $this->semanticRepo->loadTargetIdMap('trm');
        $updated = 0;

        foreach ($databaseRefs as $dbRef) {
            $rtyRows = $this->client->fetchRows($dbRef['server'], $dbRef['dbName'], 'rty', [], 0);
            $dtyRows = $this->client->fetchRows($dbRef['server'], $dbRef['dbName'], 'dty', [], 0);
            $trmRows = $this->client->fetchRows($dbRef['server'], $dbRef['dbName'], 'trm', [], 0);

            $localRtyOrigins = $this->buildLocalOriginMap('RTY', $rtyRows, $dbRef['registeredId']);
            $localTrmOrigins = $this->buildLocalOriginMap('TRM', $trmRows, $dbRef['registeredId']);

            foreach ($dtyRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $origin = sourceOrigin($row, ENTITY_SPECS['DTY'], $dbRef['registeredId']);
                if (!$origin) {
                    continue;
                }
                $targetDtyId = $dtyTargetMap[$this->originKey($origin)] ?? null;
                if ($targetDtyId === null) {
                    continue;
                }

                $updates = [];

                $ptrTargets = cleanNullable($row['dty_PtrTargetRectypeIDs'] ?? null);
                if ($ptrTargets !== null) {
                    $mapped = rewriteCsvIds($ptrTargets, function (int $localId) use ($localRtyOrigins, $rtyTargetMap): ?int {
                        $origin = $localRtyOrigins[$localId] ?? null;
                        return $origin ? ($rtyTargetMap[$this->originKey($origin)] ?? null) : null;
                    });
                    if ($mapped !== '') {
                        $updates['dty_PtrTargetRectypeIDs'] = $mapped;
                    }
                }

                $termTree = cleanNullable($row['dty_JsonTermIDTree'] ?? null);
                if ($termTree !== null) {
                    $mappedTree = rewriteIntegerTokens($termTree, function (int $localId) use ($localTrmOrigins, $trmTargetMap): ?int {
                        $origin = $localTrmOrigins[$localId] ?? null;
                        return $origin ? ($trmTargetMap[$this->originKey($origin)] ?? null) : null;
                    });
                    $updates['dty_JsonTermIDTree'] = $mappedTree;
                }

                if ($updates) {
                    $this->targetRepo->updateByPk('defDetailTypes', 'dty_ID', (int)$targetDtyId, $updates);
                    $updated++;
                }
            }
        }

        logLine("Reference repair: DTY rows updated {$updated}");
    }

    public function repairTermReferences(array $databaseRefs): void
    {
        logLine('Reference repair: TRM parent and inverse links');

        // The target hierarchy table is rebuilt deterministically at the end of
        // the run from defTerms.trm_ParentTermID. Clear it before parent repairs
        // so legacy triggers, if present, cannot fail on duplicate link rows
        // while defTerms is being updated.
        $this->targetRepo->deleteAllTermLinks();

        $trmTargetMap = $this->semanticRepo->loadTargetIdMap('trm');
        $updated = 0;
        $resolvedParents = 0;
        $resolvedInverses = 0;
        $unresolvedParents = 0;
        $unresolvedInverses = 0;

        foreach ($databaseRefs as $dbRef) {
            $rows = $this->client->fetchRows($dbRef['server'], $dbRef['dbName'], 'trm', [], 0);
            $localTrmOrigins = $this->buildLocalOriginMap('TRM', $rows, $dbRef['registeredId']);

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $origin = sourceOrigin($row, ENTITY_SPECS['TRM'], $dbRef['registeredId']);
                if (!$origin) {
                    continue;
                }
                $targetTrmId = $trmTargetMap[$this->originKey($origin)] ?? null;
                if ($targetTrmId === null) {
                    continue;
                }

                $updates = [];

                $parentLocalId = toInt($row['trm_ParentTermID'] ?? 0);
                if ($parentLocalId > 0) {
                    $parentOrigin = $localTrmOrigins[$parentLocalId] ?? null;
                    $parentTargetId = $parentOrigin ? ($trmTargetMap[$this->originKey($parentOrigin)] ?? null) : null;
                    if ($parentTargetId !== null && $parentTargetId !== (int)$targetTrmId) {
                        $updates['trm_ParentTermID'] = $parentTargetId;
                        $resolvedParents++;
                    } else {
                        $unresolvedParents++;
                    }
                }

                $inverseLocalId = toInt($row['trm_InverseTermID'] ?? 0);
                if ($inverseLocalId > 0) {
                    $inverseOrigin = $localTrmOrigins[$inverseLocalId] ?? null;
                    $inverseTargetId = $inverseOrigin ? ($trmTargetMap[$this->originKey($inverseOrigin)] ?? null) : null;
                    if ($inverseTargetId !== null && $inverseTargetId !== (int)$targetTrmId) {
                        $updates['trm_InverseTermID'] = $inverseTargetId;
                        $resolvedInverses++;
                    } else {
                        $unresolvedInverses++;
                    }
                }

                if (!$updates) {
                    continue;
                }

                $current = $this->targetRepo->getColumnsByPk('defTerms', 'trm_ID', (int)$targetTrmId, ['trm_ParentTermID', 'trm_InverseTermID']);
                $changed = [];
                foreach ($updates as $field => $value) {
                    // First resolvable source row wins. Do not overwrite a value
                    // already set by an earlier database in registered-ID order.
                    if (normaliseDbNullableInt($current[$field] ?? null) === null) {
                        $changed[$field] = $value;
                    }
                }

                if ($changed) {
                    $this->targetRepo->updateByPk('defTerms', 'trm_ID', (int)$targetTrmId, $changed);
                    $updated++;
                }
            }
        }

        logLine(
            "Reference repair: TRM rows updated {$updated}; " .
            "parents resolved {$resolvedParents}, unresolved {$unresolvedParents}; " .
            "inverses resolved {$resolvedInverses}, unresolved {$unresolvedInverses}"
        );
    }

    /**
     * @param array<int,array> $rows
     * @return array<int,array{db:int,id:int}>
     */
    private function buildLocalOriginMap(string $type, array $rows, int $registeredId): array
    {
        $spec = ENTITY_SPECS[$type];
        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $localId = toInt($row[$spec['pk']] ?? 0);
            if ($localId <= 0) {
                continue;
            }
            $origin = sourceOrigin($row, $spec, $registeredId);
            if ($origin) {
                $map[$localId] = $origin;
            }
        }
        return $map;
    }

    /** @param array{db:int,id:int} $origin */
    private function originKey(array $origin): string
    {
        return (int)$origin['db'] . '-' . (int)$origin['id'];
    }
}
