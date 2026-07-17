<?php

declare(strict_types=1);

final class SemanticGlobalHarvester
{
    /** @var array<int,array{server:string,dbName:string,registeredId:int,title:string}> */
    private array $databaseRefs = [];


    public function __construct(
        private ApiClient $client,
        private PDO $targetPdo,
        private TargetRepository $targetRepo,
        private SemanticMapRepository $semanticRepo,
        private SemanticEntityImporter $entityImporter,
        private SemanticStructureImporter $structureImporter,
        private SemanticReferenceRepair $referenceRepair,
        private SemanticTermLinksImporter $termLinksImporter,
        private string $targetDbName
    ) {}

    public function run(array $sources): void
    {
        $this->discoverDatabases($sources);

        $this->runEntityPass('RTY', originOnly: true, neutraliseReferences: false);
        $this->runEntityPass('RTY', originOnly: false, neutraliseReferences: false);

        $this->runEntityPass('DTY', originOnly: true, neutraliseReferences: true);
        $this->runEntityPass('DTY', originOnly: false, neutraliseReferences: true);

        $this->runEntityPass('TRM', originOnly: true, neutraliseReferences: true);
        $this->runEntityPass('TRM', originOnly: false, neutraliseReferences: true);

        // Fetch and map the complete source term-link set before modifying the
        // target. If any source API request fails, the existing target links
        // remain untouched.
        $mappedTermLinks = $this->termLinksImporter->collectMappedLinks($this->databaseRefs);

        $this->targetPdo->beginTransaction();
        try {
            // Clear links before term parent repair so legacy triggers cannot
            // collide with existing rows. The complete explicit + implicit set
            // is restored below in the same transaction.
            $this->targetRepo->deleteAllTermLinks();
            $this->referenceRepair->repairDtyReferences($this->databaseRefs);
            $this->referenceRepair->repairTermReferences($this->databaseRefs);
            $this->targetRepo->replaceTermLinks($mappedTermLinks);
            $this->targetPdo->commit();
        } catch (Throwable $e) {
            if ($this->targetPdo->inTransaction()) {
                $this->targetPdo->rollBack();
            }
            throw $e;
        }

        $this->runStructurePass(originOnly: true);
        $this->runStructurePass(originOnly: false);
    }

    private function discoverDatabases(array $sources): void
    {
        logLine('Discovering registered source databases');
        foreach ($sources as $sourceCfg) {
            $server = normaliseServerUrl((string)$sourceCfg['server']);
            $databases = $this->client->fetchRegisteredDatabases($server);

            foreach ($databases as $dbInfo) {
                if (!is_array($dbInfo)) {
                    continue;
                }
                $dbName = (string)($dbInfo['sys_Database'] ?? '');
                $registeredId = toInt($dbInfo['sys_dbRegisteredID'] ?? 0);
                if ($dbName === '' || $registeredId <= 0) {
                    continue;
                }
                if (isLocalSourceServer($server) && isTargetDatabaseName($dbName, $this->targetDbName)) {
                    logLine("Skipping local target database {$dbName}");
                    continue;
                }
                $this->databaseRefs[] = [
                    'server' => $server,
                    'dbName' => $dbName,
                    'registeredId' => $registeredId,
                    'title' => (string)($dbInfo['sys_dbName'] ?? ''),
                ];
            }
        }
        
//        $this->databaseRefs = [['server'=>'http://127.0.0.1/heurist','dbName'=>'osmak_1', 'registeredId'=>1750, 'title'=>'TEST!']];        
        
        // Process lower registered IDs first. This increases the chance that
        // the origin database for a concept is encountered before derived copies
        // in later databases, while still keeping derived/fallback passes safe.
        usort($this->databaseRefs, static function (array $a, array $b): int {
            return [$a['registeredId'], $a['server'], $a['dbName']]
                <=> [$b['registeredId'], $b['server'], $b['dbName']];
        });

        logLine('Registered databases to scan: ' . count($this->databaseRefs));
    }

    private function runEntityPass(string $type, bool $originOnly, bool $neutraliseReferences): void
    {
        $label = $originOnly ? 'origin' : 'derived/fallback';
        logLine(str_repeat('=', 90));
        logLine("{$type} {$label} pass");

        foreach ($this->databaseRefs as $dbRef) {
            $this->targetPdo->beginTransaction();
            try {
                $rows = $this->getRows($dbRef, $type);
                $count = 0;
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $origin = sourceOrigin($row, ENTITY_SPECS[$type], $dbRef['registeredId']);
                    if (!$origin) {
                        continue;
                    }
                    $isOriginRow = $origin['db'] === $dbRef['registeredId'];
                    if ($originOnly && !$isOriginRow) {
                        continue;
                    }
                    if (!$originOnly && $this->semanticRepo->semanticExists(strtolower($type), $origin['db'], $origin['id'])) {
                        continue;
                    }

                    $targetId = $this->entityImporter->importEntityRow($type, $row, $dbRef['registeredId'], $dbRef['dbName'], $neutraliseReferences);
                    if ($targetId !== null) {
                        $count++;
                    }
                }
                $this->targetPdo->commit();
                if ($count > 0) {
                    logLine("  {$dbRef['dbName']}: {$count} {$type} rows processed in {$label} pass");
                }
            } catch (Throwable $e) {
                if ($this->targetPdo->inTransaction()) {
                    $this->targetPdo->rollBack();
                }
                logError("{$type} {$label} pass failed for {$dbRef['dbName']}: " . $e->getMessage());
            }
        }
    }

    private function runStructurePass(bool $originOnly): void
    {
        $label = $originOnly ? 'origin' : 'derived/additional';
        logLine(str_repeat('=', 90));
        logLine("RST {$label} pass");

        foreach ($this->databaseRefs as $dbRef) {
            $this->targetPdo->beginTransaction();
            try {
                $rtyRows = $this->getRows($dbRef, 'RTY');
                $dtyRows = $this->getRows($dbRef, 'DTY');
                $rstRows = $this->getRows($dbRef, 'RST');

                if ($originOnly) {
                    $this->structureImporter->importOriginStructures($dbRef, $rtyRows, $dtyRows, $rstRows);
                } else {
                    $this->structureImporter->importDerivedStructures($dbRef, $rtyRows, $dtyRows, $rstRows);
                }
                $this->targetPdo->commit();
            } catch (Throwable $e) {
                if ($this->targetPdo->inTransaction()) {
                    $this->targetPdo->rollBack();
                }
                logError("RST {$label} pass failed for {$dbRef['dbName']}: " . $e->getMessage());
            }
        }
    }

    /** @return array<int,array> */
    private function getRows(array $dbRef, string $type): array
    {
        // Do not retain rows across the whole run. The global-pass algorithm can
        // touch hundreds of databases; refetching is preferable to unbounded memory growth.
        $spec = ENTITY_SPECS[$type];
        $rows = $this->client->fetchRows($dbRef['server'], $dbRef['dbName'], $spec['api'], []);
        logLine("  fetched " . count($rows) . " {$type} rows from {$dbRef['dbName']}");
        return $rows;
    }
}
