<?php

declare(strict_types=1);

final class SemanticTermLinksImporter
{
    public function __construct(
        private ApiClient $client,
        private SemanticMapRepository $semanticRepo
    ) {}

    /**
     * Fetch and map all source defTermsLinks rows to target-local term IDs.
     *
     * No target data is modified here. The complete mapped set is prepared first
     * so the caller can replace defTermsLinks transactionally only after every
     * source API request has succeeded.
     *
     * @param array<int,array{server:string,dbName:string,registeredId:int,title:string}> $databaseRefs
     * @return array<int,array{parentId:int,termId:int}>
     */
    public function collectMappedLinks(array $databaseRefs): array
    {
        logLine('Term links: fetching and mapping source defTermsLinks rows');

        $trmTargetMap = $this->semanticRepo->loadTargetIdMap('trm');
        $mapped = [];
        $sourceCount = 0;
        $unresolved = 0;

        foreach ($databaseRefs as $dbRef) {
            $termRows = $this->client->fetchRows($dbRef['server'], $dbRef['dbName'], 'trm', []);
            $linkRows = $this->client->fetchRows($dbRef['server'], $dbRef['dbName'], 'termlinks', []);
            $localOrigins = $this->buildLocalOriginMap($termRows, $dbRef['registeredId']);

            $mappedForDatabase = 0;
            foreach ($linkRows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $sourceCount++;
                $parentLocalId = toInt($row['trl_ParentID'] ?? 0);
                $termLocalId = toInt($row['trl_TermID'] ?? 0);
                if ($parentLocalId <= 0 || $termLocalId <= 0) {
                    $unresolved++;
                    continue;
                }

                $parentOrigin = $localOrigins[$parentLocalId] ?? null;
                $termOrigin = $localOrigins[$termLocalId] ?? null;
                $parentTargetId = $parentOrigin ? ($trmTargetMap[$this->originKey($parentOrigin)] ?? null) : null;
                $termTargetId = $termOrigin ? ($trmTargetMap[$this->originKey($termOrigin)] ?? null) : null;

                if ($parentTargetId === null || $termTargetId === null || $parentTargetId === $termTargetId) {
                    $unresolved++;
                    continue;
                }

                $key = $parentTargetId . '-' . $termTargetId;
                if (!isset($mapped[$key])) {
                    $mapped[$key] = [
                        'parentId' => (int)$parentTargetId,
                        'termId' => (int)$termTargetId,
                    ];
                    $mappedForDatabase++;
                }
            }

            logLine(
                "  {$dbRef['dbName']}: fetched " . count($linkRows) .
                " term links; added {$mappedForDatabase} mapped links"
            );
        }

        logLine(
            'Term links: source rows ' . $sourceCount .
            '; unique mapped links ' . count($mapped) .
            '; unresolved/skipped ' . $unresolved
        );

        return array_values($mapped);
    }

    /**
     * @param array<int,array> $rows
     * @return array<int,array{db:int,id:int}>
     */
    private function buildLocalOriginMap(array $rows, int $registeredId): array
    {
        $spec = ENTITY_SPECS['TRM'];
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
