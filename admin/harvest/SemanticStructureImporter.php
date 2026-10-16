<?php

declare(strict_types=1);

final class SemanticStructureImporter
{
    public function __construct(
        private TargetRepository $targetRepo,
        private SemanticMapRepository $semanticRepo
    ) {}

    /**
     * Import structures for record types whose origin DB is the current source DB.
     * This keeps the original record type definition when it is available.
     */
    public function importOriginStructures(array $dbRef, array $rtyRows, array $dtyRows, array $rstRows): void
    {
        logLine("RST origin structures: {$dbRef['dbName']} / registered ID {$dbRef['registeredId']}");
        $this->importStructures($dbRef, $rtyRows, $dtyRows, $rstRows, originOnly: true);
    }

    /**
     * Import additional RTY+DTY structural memberships encountered elsewhere.
     * Existing composites are intentionally ignored; variants of labels/order/requirements are not preserved here.
     */
    public function importDerivedStructures(array $dbRef, array $rtyRows, array $dtyRows, array $rstRows): void
    {
        logLine("RST derived/additional structures: {$dbRef['dbName']} / registered ID {$dbRef['registeredId']}");
        $this->importStructures($dbRef, $rtyRows, $dtyRows, $rstRows, originOnly: false);
    }

    private function importStructures(array $dbRef, array $rtyRows, array $dtyRows, array $rstRows, bool $originOnly): void
    {
        $rtyByLocal = $this->indexRowsByPk($rtyRows, 'rty_ID');
        $dtyByLocal = $this->indexRowsByPk($dtyRows, 'dty_ID');

        foreach ($rstRows as $rstRow) {
            if (!is_array($rstRow)) {
                continue;
            }

            $sourceRtyLocalId = toInt($rstRow['rst_RecTypeID'] ?? 0);
            $sourceDtyLocalId = toInt($rstRow['rst_DetailTypeID'] ?? 0);
            if ($sourceRtyLocalId <= 0 || $sourceDtyLocalId <= 0) {
                continue;
            }

            $sourceRty = $rtyByLocal[$sourceRtyLocalId] ?? null;
            $sourceDty = $dtyByLocal[$sourceDtyLocalId] ?? null;
            if (!is_array($sourceRty) || !is_array($sourceDty)) {
                logWarning("{$dbRef['dbName']}: skipping RST with unresolved local RTY/DTY {$sourceRtyLocalId}/{$sourceDtyLocalId}");
                continue;
            }

            $rtyOrigin = sourceOrigin($sourceRty, ENTITY_SPECS['RTY'], $dbRef['registeredId']);
            $dtyOrigin = sourceOrigin($sourceDty, ENTITY_SPECS['DTY'], $dbRef['registeredId']);
            if (!$rtyOrigin || !$dtyOrigin) {
                continue;
            }

            $isOriginStructure = $rtyOrigin['db'] === $dbRef['registeredId'];
            if ($originOnly && !$isOriginStructure) {
                continue;
            }

            $targetRtyId = $this->semanticRepo->findSemanticTargetId('rty', $rtyOrigin['db'], $rtyOrigin['id']);
            $targetDtyId = $this->semanticRepo->findSemanticTargetId('dty', $dtyOrigin['db'], $dtyOrigin['id']);
            if ($targetRtyId === null || $targetDtyId === null) {
                logWarning(sprintf(
                    '%s: skipping RST because target RTY/DTY concept is unresolved: RTY%d-%d DTY%d-%d',
                    $dbRef['dbName'],
                    $rtyOrigin['db'],
                    $rtyOrigin['id'],
                    $dtyOrigin['db'],
                    $dtyOrigin['id']
                ));
                continue;
            }

            $existingRstId = $this->targetRepo->findExistingRecStructureId($targetRtyId, $targetDtyId);
            if ($existingRstId !== null) {
                $this->semanticRepo->recordSemanticStructure(
                    $existingRstId,
                    $targetRtyId,
                    $targetDtyId,
                    $rtyOrigin,
                    $dtyOrigin,
                    $dbRef['registeredId'],
                    $dbRef['dbName'],
                    toInt($rstRow['rst_ID'] ?? 0) ?: null,
                    $sourceRtyLocalId,
                    $sourceDtyLocalId,
                    $isOriginStructure,
                    !$isOriginStructure
                );
                continue;
            }

            $prepared = normaliseApiRowForInsert($rstRow);
            unset($prepared['rst_ID']);
            $prepared['rst_RecTypeID'] = $targetRtyId;
            $prepared['rst_DetailTypeID'] = $targetDtyId;
            unset($prepared['rst_CalcFunctionID']);

            if (!$isOriginStructure) {
                $prepared['rst_Status'] = 'pending';
                $prepared['rst_DisplayOrder'] = $this->semanticRepo->getMaxRecStructureOrder($targetRtyId) + 10;
            }

            $targetRstId = $this->targetRepo->insertRow('defRecStructure', $prepared);
            $this->semanticRepo->recordSemanticStructure(
                $targetRstId,
                $targetRtyId,
                $targetDtyId,
                $rtyOrigin,
                $dtyOrigin,
                $dbRef['registeredId'],
                $dbRef['dbName'],
                toInt($rstRow['rst_ID'] ?? 0) ?: null,
                $sourceRtyLocalId,
                $sourceDtyLocalId,
                $isOriginStructure,
                !$isOriginStructure
            );
            logConceptAction($dbRef['dbName'], 'RST', ENTITY_SPECS['RST'], $prepared, $isOriginStructure ? 'INSERTED' : 'INSERTED PENDING');
        }
    }

    /** @return array<int,array> */
    private function indexRowsByPk(array $rows, string $pk): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = toInt($row[$pk] ?? 0);
            if ($id > 0) {
                $indexed[$id] = $row;
            }
        }
        return $indexed;
    }
}
