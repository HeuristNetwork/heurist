<?php

declare(strict_types=1);

/**
 * harvest_concepts_to_semantic_refdb_api.php
 *
 * Harvest semantic concepts from configured Heurist servers via API and copy them
 * into the local target database hdb_Heurist_Concept_Definitions.
 *
 * Source access is via Heurist API only. Target writes are direct local MySQL/PDO.
 *
 * Expected config file: /var/www/html/HEURIST/harvest_concepts_to_semantic_refdb_cfg.php
 *
 * Recommended config shape:
 *
 * return [
 *     'target' => [
 *         'dbHost' => '127.0.0.1',
 *         'dbPort' => 3306,
 *         'dbAdminUsername' => 'heurist',
 *         'dbAdminPassword' => '<password>',
 *         // 'database' => 'hdb_Heurist_Concept_Definitions', // optional
 *     ],
 *     'sources' => [
 *         [
 *             'server' => 'http://127.0.0.1/heurist/',
 *             'registryDatabase' => 'Heurist_Concept_Definitions',
 *   'username' => '<api-login>',
 *   'password' => '<api-password>',
 *   // optional:
 *   // 'jwt' => '<already-issued-token>',
 *   
 *             ],
 *         [
 *             'server' => 'https://heurist.huma-num.fr/h7-alpha/',
 *             'registryDatabase' => 'Heurist_Concept_Definitions',
 *         ],
 *     ],
 * ];
 */
const CONFIG_FILE = __DIR__ . '/harvest_concepts_to_semantic_refdb_cfg.php';
const LOG_FILE    = __DIR__ . '/harvest_concepts_to_semantic_refdb.log';
const TARGET_DB   = 'hdb_osmak_core'; //'hdb_Heurist_Concept_Definitions';
const API_LIMIT   = 1000;
const HTTP_TIMEOUT_SECONDS = 120;

const ENTITY_SPECS = [
    'RTY' => [
        'api' => 'rty',
        'table' => 'defRecTypes',
        'pk' => 'rty_ID',
        'origin_db' => 'rty_OriginatingDBID',
        'origin_id' => 'rty_IDInOriginatingDB',
        'origin_name' => 'rty_NameInOriginatingDB',
        'name_field' => 'rty_Name',
        'group_field' => 'rty_RecTypeGroupID',
    ],
    'DTY' => [
        'api' => 'dty',
        'table' => 'defDetailTypes',
        'pk' => 'dty_ID',
        'origin_db' => 'dty_OriginatingDBID',
        'origin_id' => 'dty_IDInOriginatingDB',
        'origin_name' => 'dty_NameInOriginatingDB',
        'name_field' => 'dty_Name',
        'group_field' => 'dty_DetailTypeGroupID',
    ],
    'TRM' => [
        'api' => 'trm',
        'table' => 'defTerms',
        'pk' => 'trm_ID',
        'origin_db' => 'trm_OriginatingDBID',
        'origin_id' => 'trm_IDInOriginatingDB',
        'origin_name' => 'trm_NameInOriginatingDB',
        'name_field' => 'trm_Label',
        'group_field' => 'trm_VocabularyGroupID',
    ],
    'RST' => [
        'api' => 'rst',
        'table' => 'defRecStructure',
        'pk' => 'rst_ID',
        'origin_db' => 'rst_OriginatingDBID',
        'origin_id' => 'rst_IDInOriginatingDB',
        'origin_name' => null,
        'name_field' => null,
        'group_field' => null,
    ],
];

main();

function main(): void
{
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "This script must be run from the command line.\n");
        exit(1);
    }

    initialiseLogFile();

    try {
        $cfg = loadConfig(CONFIG_FILE);
        $targetPdo = connectTarget($cfg['target']);
        $targetRepo = new TargetRepository($targetPdo);
        $client = new ApiClient();
    } catch (Throwable $e) {
        logError('Initialisation failed: ' . $e->getMessage());
        exit(1);
    }

    $summary = new Summary();
    logRunHeader($cfg['sources']);

    foreach ($cfg['sources'] as $sourceCfg) {
        
        $summary->servers++;
        $server = normaliseServerUrl((string)$sourceCfg['server']);
        $registryDb = (string)$sourceCfg['registryDatabase'];

        logLine(str_repeat('=', 90));
        logLine("SOURCE SERVER: {$server} registry DB {$registryDb}");

        try {
            $client->authenticateSource($sourceCfg);
            $databases = $client->fetchRegisteredDatabases($server, $registryDb);
        } catch (Throwable $e) {
            $summary->errors++;
            logError("Unable to authenticate/fetch registered databases from {$server}: " . $e->getMessage());
            continue;
        }

        foreach ($databases as $dbInfo) {
            $summary->databasesSeen++;
            $dbName = (string)($dbInfo['sys_Database'] ?? '');
            $registeredId = toInt($dbInfo['sys_dbRegisteredID'] ?? 0);
            $dbTitle = (string)($dbInfo['sys_dbName'] ?? '');

            if ($dbName === '') {
                logWarning("Skipping database entry with no sys_Database on {$server}");
                continue;
            }
            if ($registeredId <= 0) {
                $summary->databasesSkippedUnregistered++;
                logLine("Skipping unregistered database {$dbName}");
                continue;
            }
            
            if (isLocalSourceServer($server) && isTargetDatabaseName($dbName)) {
                $summary->databasesSkippedTarget++;
                logLine("Skipping local target database {$dbName}");
                continue;
            }            

            logLine(str_repeat('-', 90));
            logLine("Fetching {$dbName} (Registered ID {$registeredId})" . ($dbTitle !== '' ? " - {$dbTitle}" : ''));

            try {
                $sourceSet = fetchSourceDataset($client, $server, $dbName, $registeredId);
            } catch (Throwable $e) {
                $summary->errors++;
                logError("Fetch failed for {$server} / {$dbName}: " . $e->getMessage());
                continue;
            }

            try {
                $targetPdo->beginTransaction();
                processSourceDatabase($sourceSet, $targetRepo, $summary);
                $targetPdo->commit();
                $summary->databasesProcessed++;
            } catch (Throwable $e) {
                if ($targetPdo->inTransaction()) {
                    $targetPdo->rollBack();
                }
                $summary->errors++;
                logError("Import failed and rolled back for {$server} / {$dbName} / registered ID {$registeredId}: " . $e->getMessage());
                continue;
            }
        }
    }

    logLine(str_repeat('=', 90));
    logLine('SUMMARY');
    logLine('Servers processed: ' . $summary->servers);
    logLine('Databases seen: ' . $summary->databasesSeen);
    logLine('Databases processed: ' . $summary->databasesProcessed);
    logLine('Databases skipped (target): ' . $summary->databasesSkippedTarget);
    logLine('Databases skipped (unregistered): ' . $summary->databasesSkippedUnregistered);
    foreach ($summary->inserted as $table => $count) {
        logLine("Inserted {$table}: {$count}");
    }
    foreach ($summary->reused as $table => $count) {
        logLine("Reused duplicates {$table}: {$count}");
    }
    foreach ($summary->updated as $table => $count) {
        logLine("Updated references {$table}: {$count}");
    }
    logLine('Warnings: ' . $summary->warnings);
    logLine('Errors: ' . $summary->errors);
}

function isTargetDatabaseName(string $dbName): bool
{
    $targetWithPrefix = TARGET_DB;
    $targetWithoutPrefix = preg_replace('/^hdb_/i', '', TARGET_DB) ?? TARGET_DB;

    return strcasecmp($dbName, $targetWithPrefix) === 0
        || strcasecmp($dbName, $targetWithoutPrefix) === 0;
}

function isLocalSourceServer(string $server): bool
{
    $parts = parse_url(normaliseServerUrl($server));
    $host = strtolower((string)($parts['host'] ?? ''));

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function fetchSourceDataset(ApiClient $client, string $server, string $dbName, int $registeredId): SourceDataset
{
    $set = new SourceDataset($server, $dbName, $registeredId);

    // 1. Fetch rows actually meant to be harvested from this database.
    foreach (ENTITY_SPECS as $type => $spec) {
        $originField = $spec['origin_db'];
        $rows = $client->fetchRows($server, $dbName, $spec['api'], [$originField => (string)$registeredId], 0);
        foreach ($rows as $row) {
            $set->addHarvestRow($type, $row);
        }
        logLine("  {$type}: fetched " . count($rows) . ' harvest rows');
    }

    // 2. Collect direct references from harvest rows.
    $collector = new ReferenceCollector($set);
    $collector->collectFromHarvestRows();

    // 3. Fetch referenced RTY/DTY rows. Referenced DTY rows may themselves
    // contain vocabulary/target-rectype references, so collect those after fetch.
    fetchMissingReferencedRows($client, $set, 'RTY');
    fetchMissingReferencedRows($client, $set, 'DTY');
    $collector->collectFromAllRows('DTY');
    fetchMissingReferencedRows($client, $set, 'RTY');

    // 4. Fetch referenced terms, expanding parent/inverse chain until stable.
    do {
        $before = count($set->getNeededIds('TRM'));
        fetchMissingReferencedRows($client, $set, 'TRM');
        $collector->collectFromAllRows('TRM');
        $after = count($set->getNeededIds('TRM'));
    } while ($after > $before);

    return $set;
}

function fetchMissingReferencedRows(ApiClient $client, SourceDataset $set, string $type): void
{
    $spec = ENTITY_SPECS[$type];
    $missing = $set->getMissingNeededLocalIds($type);
    if (!$missing) {
        return;
    }

    foreach (array_chunk($missing, API_LIMIT) as $chunk) {
        $rows = $client->fetchRows($set->server, $set->dbName, $spec['api'], [
            $spec['pk'] => implode(',', $chunk),
        ], count($chunk));
        foreach ($rows as $row) {
            $set->addReferenceRow($type, $row);
        }
    }

    $stillMissing = $set->getMissingNeededLocalIds($type);
    if ($stillMissing) {
        logWarning($set->label() . " unresolved source {$type} IDs: " . implode(',', array_slice($stillMissing, 0, 30)) . (count($stillMissing) > 30 ? ' ...' : ''));
    }
}

function processSourceDatabase(SourceDataset $set, TargetRepository $repo, Summary $summary): void
{
    logLine("Importing {$set->dbName} in one transaction");

    $groupIds = $repo->ensureGroupsForSourceDatabase($set->dbName);
    $targetMap = new TargetIdMap($repo);

    // First insert/reuse all RTY/DTY/TRM rows needed either as harvest rows or dependencies.
    importConceptRows($set, $repo, $targetMap, $summary, 'RTY', $groupIds);
    importConceptRows($set, $repo, $targetMap, $summary, 'DTY', $groupIds, neutraliseReferences: true);
    importConceptRows($set, $repo, $targetMap, $summary, 'TRM', $groupIds, neutraliseReferences: true);

    // Now that target IDs are known, update DTY and TRM references.
    updateDetailTypeReferences($set, $repo, $targetMap, $summary);
    updateTermReferences($set, $repo, $targetMap, $summary);

    // RST is inserted after RTY and DTY maps exist.
    importRecStructureRows($set, $repo, $targetMap, $summary);
}

function importConceptRows(
    SourceDataset $set,
    TargetRepository $repo,
    TargetIdMap $targetMap,
    Summary $summary,
    string $type,
    array $groupIds,
    bool $neutraliseReferences = false
): void {
    $spec = ENTITY_SPECS[$type];
    foreach ($set->getAllRows($type) as $row) {
        $origin = sourceOrigin($row, $spec, $set->registeredId);
        if (!$origin) {
            logWarning($set->label() . " skipping {$type} row with no usable origin");
            $summary->warnings++;
            continue;
        }

        $existingId = $repo->findExistingTargetId($spec, $origin['db'], $origin['id']);
        if ($existingId !== null) {
            $targetMap->put($type, $origin['db'], $origin['id'], $existingId);
            $summary->reused[$spec['table']]++;
            continue;
        }

        $prepared = prepareBaseRow($row, $spec, $origin, $set->registeredId);
        $prepared = ensureUniqueConceptNameForInsert($repo, $summary, $set, $type, $spec, $origin, $prepared);

        if ($type === 'RTY') {
            $prepared['rty_RecTypeGroupID'] = $groupIds['rty'];
        } elseif ($type === 'DTY') {
            $prepared['dty_DetailTypeGroupID'] = $groupIds['dty'];
            if ($neutraliseReferences) {
                unset($prepared['dty_JsonTermIDTree'], $prepared['dty_PtrTargetRectypeIDs']);
            }
        } elseif ($type === 'TRM') {
            $domain = normaliseTermDomain((string)($row['trm_Domain'] ?? 'enum'));
            $prepared['trm_VocabularyGroupID'] = $groupIds['trm'][$domain];
            if ($neutraliseReferences) {
                $prepared['trm_ParentTermID'] = 0;
                $prepared['trm_InverseTermID'] = null;
            }
        }

        $newId = $repo->insertRow($spec['table'], $prepared);
        $targetMap->put($type, $origin['db'], $origin['id'], $newId);
        $summary->inserted[$spec['table']]++;
        logConceptAction($set->dbName, $type, $spec, $prepared, 'INSERTED');
    }
}

function updateDetailTypeReferences(SourceDataset $set, TargetRepository $repo, TargetIdMap $targetMap, Summary $summary): void
{
    $spec = ENTITY_SPECS['DTY'];
    foreach ($set->getAllRows('DTY') as $row) {
        $origin = sourceOrigin($row, $spec, $set->registeredId);
        if (!$origin) {
            continue;
        }
        $targetId = $targetMap->get('DTY', $origin['db'], $origin['id']);
        if ($targetId === null) {
            continue;
        }

        $updates = [];
        $termTree = cleanNullable($row['dty_JsonTermIDTree'] ?? null);
        if ($termTree !== null) {
            $updates['dty_JsonTermIDTree'] = rewriteIntegerTokens($termTree, function (int $localId) use ($set, $targetMap): ?int {
                $origin = $set->resolveSourceOrigin('TRM', $localId);
                return $origin ? $targetMap->get('TRM', $origin['db'], $origin['id']) : null;
            });
        }

        $ptrTargets = cleanNullable($row['dty_PtrTargetRectypeIDs'] ?? null);
        if ($ptrTargets !== null) {
            $updates['dty_PtrTargetRectypeIDs'] = rewriteCsvIds($ptrTargets, function (int $localId) use ($set, $targetMap): ?int {
                $origin = $set->resolveSourceOrigin('RTY', $localId);
                return $origin ? $targetMap->get('RTY', $origin['db'], $origin['id']) : null;
            });
        }

        if ($updates) {
            $repo->updateByPk($spec['table'], $spec['pk'], $targetId, $updates);
            $summary->updated[$spec['table']]++;
        }
    }
}

function updateTermReferences(SourceDataset $set, TargetRepository $repo, TargetIdMap $targetMap, Summary $summary): void
{
    $spec = ENTITY_SPECS['TRM'];
    foreach ($set->getAllRows('TRM') as $row) {
        $origin = sourceOrigin($row, $spec, $set->registeredId);
        if (!$origin) {
            continue;
        }
        $targetId = $targetMap->get('TRM', $origin['db'], $origin['id']);
        if ($targetId === null) {
            continue;
        }

        $updates = [];
        foreach (['trm_ParentTermID', 'trm_InverseTermID'] as $field) {
            $localRef = toInt($row[$field] ?? 0);
            if ($localRef <= 0) {
                $updates[$field] = ($field === 'trm_ParentTermID') ? 0 : null;
                continue;
            }
            $refOrigin = $set->resolveSourceOrigin('TRM', $localRef);
            $mapped = $refOrigin ? $targetMap->get('TRM', $refOrigin['db'], $refOrigin['id']) : null;
            if ($mapped === null) {
                throw new RuntimeException($set->label() . " cannot resolve {$field} source term {$localRef}");
            }
            $updates[$field] = $mapped;
        }

        $repo->updateByPk($spec['table'], $spec['pk'], $targetId, $updates);
        $summary->updated[$spec['table']]++;
    }
}

function importRecStructureRows(SourceDataset $set, TargetRepository $repo, TargetIdMap $targetMap, Summary $summary): void
{
    $spec = ENTITY_SPECS['RST'];
    foreach ($set->getHarvestRows('RST') as $row) {
        $origin = sourceOrigin($row, $spec, $set->registeredId);
        if (!$origin) {
            logWarning($set->label() . ' skipping RST row with no usable origin');
            $summary->warnings++;
            continue;
        }

        $existingId = $repo->findExistingTargetId($spec, $origin['db'], $origin['id']);
        if ($existingId !== null) {
            $targetMap->put('RST', $origin['db'], $origin['id'], $existingId);
            $summary->reused[$spec['table']]++;
            continue;
        }

        $prepared = prepareBaseRow($row, $spec, $origin, $set->registeredId);

        $localRty = toInt($row['rst_RecTypeID'] ?? 0);
        $rtyOrigin = $set->resolveSourceOrigin('RTY', $localRty);
        $targetRty = $rtyOrigin ? $targetMap->get('RTY', $rtyOrigin['db'], $rtyOrigin['id']) : null;
        if ($targetRty === null) {
            throw new RuntimeException($set->label() . " cannot resolve rst_RecTypeID {$localRty}");
        }
        $prepared['rst_RecTypeID'] = $targetRty;

        $localDty = toInt($row['rst_DetailTypeID'] ?? 0);
        $dtyOrigin = $set->resolveSourceOrigin('DTY', $localDty);
        $targetDty = $dtyOrigin ? $targetMap->get('DTY', $dtyOrigin['db'], $dtyOrigin['id']) : null;
        if ($targetDty === null) {
            throw new RuntimeException($set->label() . " cannot resolve rst_DetailTypeID {$localDty}");
        }
        $prepared['rst_DetailTypeID'] = $targetDty;

        // Not implemented in this phase.
        unset($prepared['rst_CalcFunctionID']);

        $newId = $repo->insertRow($spec['table'], $prepared);
        $targetMap->put('RST', $origin['db'], $origin['id'], $newId);
        $summary->inserted[$spec['table']]++;
        logConceptAction($set->dbName, 'RST', $spec, $prepared, 'INSERTED');
    }
}

function prepareBaseRow(array $row, array $spec, array $origin, int $currentRegisteredId): array
{
    $prepared = normaliseApiRowForInsert($row);
    unset($prepared[$spec['pk']]);

    $prepared[$spec['origin_db']] = $origin['db'];
    $prepared[$spec['origin_id']] = $origin['id'];

    if ($spec['origin_name'] && $spec['name_field'] && array_key_exists($spec['name_field'], $row)) {
        $prepared[$spec['origin_name']] = cleanNullable($row[$spec['name_field']]) ?? '';
    }

    return $prepared;
}

function ensureUniqueConceptNameForInsert(
    TargetRepository $repo,
    Summary $summary,
    SourceDataset $set,
    string $type,
    array $spec,
    array $origin,
    array $prepared
): array {
    if (!in_array($type, ['RTY', 'DTY'], true)) {
        return $prepared;
    }

    $nameField = $spec['name_field'] ?? null;
    if (!$nameField || !isset($prepared[$nameField])) {
        return $prepared;
    }

    $baseName = trim((string)$prepared[$nameField]);
    if ($baseName === '') {
        return $prepared;
    }

    if (!$repo->columnValueExists($spec['table'], $nameField, $baseName)) {
        return $prepared;
    }

    $maxLength = conceptNameMaxLength($type);
    $suffix = sprintf(' [%s%d-%d]', $type, $origin['db'], $origin['id']);
    $candidate = makeUniqueNameCandidate($repo, $spec['table'], $nameField, $baseName, $suffix, $maxLength);

    $prepared[$nameField] = $candidate;
    $summary->warnings++;
    logWarning(sprintf(
        '%s renamed %s duplicate name "%s" to "%s" for %s%d-%d',
        $set->label(),
        $type,
        $baseName,
        $candidate,
        $type,
        $origin['db'],
        $origin['id']
    ));

    return $prepared;
}

function conceptNameMaxLength(string $type): int
{
    return $type === 'RTY' ? 63 : 255;
}

function makeUniqueNameCandidate(TargetRepository $repo, string $table, string $field, string $baseName, string $suffix, int $maxLength): string
{
    $candidate = truncateWithSuffix($baseName, $suffix, $maxLength);
    if (!$repo->columnValueExists($table, $field, $candidate)) {
        return $candidate;
    }

    for ($i = 2; $i <= 999; $i++) {
        $numberedSuffix = preg_replace('/\]$/', " #{$i}]", $suffix) ?? ($suffix . " #{$i}");
        $candidate = truncateWithSuffix($baseName, $numberedSuffix, $maxLength);
        if (!$repo->columnValueExists($table, $field, $candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException("Unable to create unique {$table}.{$field} value for {$baseName}");
}

function truncateWithSuffix(string $baseName, string $suffix, int $maxLength): string
{
    $baseName = trim($baseName);
    $suffix = trim($suffix);

    if ($maxLength <= 0) {
        return $baseName . ' ' . $suffix;
    }

    $suffixLength = strLengthUtf8($suffix);
    if ($suffixLength >= $maxLength) {
        return strSubstringUtf8($suffix, 0, $maxLength);
    }

    $available = $maxLength - $suffixLength;
    $truncatedBase = rtrim(strSubstringUtf8($baseName, 0, $available));

    if ($truncatedBase === '') {
        return strSubstringUtf8($suffix, 0, $maxLength);
    }

    return $truncatedBase . $suffix;
}

function strLengthUtf8(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function strSubstringUtf8(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($value, $start, null, 'UTF-8') : mb_substr($value, $start, $length, 'UTF-8');
    }
    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}

function sourceOrigin(array $row, array $spec, int $currentRegisteredId): ?array
{
    $localId = toInt($row[$spec['pk']] ?? 0);
    if ($localId <= 0) {
        return null;
    }

    $originDb = toInt($row[$spec['origin_db']] ?? 0);
    $originId = toInt($row[$spec['origin_id']] ?? 0);

    if ($originDb > 0 && $originId > 0) {
        return ['db' => $originDb, 'id' => $originId];
    }

    if ($originDb > 0 && $originId <= 0) {
        return ['db' => $originDb, 'id' => $localId];
    }

    return ['db' => $currentRegisteredId, 'id' => $localId];
}

function normaliseApiRowForInsert(array $row): array
{
    $out = [];
    foreach ($row as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }
        if ($value === '') {
            // API may return SQL NULL as ''. Omit fields with empty value and let target defaults/nullability apply.
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

function rewriteCsvIds(string $csv, callable $mapper): string
{
    $parts = preg_split('/\s*,\s*/', trim($csv), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return '';
    }
    $mapped = [];
    foreach ($parts as $part) {
        if (!ctype_digit($part)) {
            $mapped[] = $part;
            continue;
        }
        $target = $mapper((int)$part);
        if ($target !== null) {
            $mapped[] = (string)$target;
        }
    }
    return implode(',', $mapped);
}

function rewriteIntegerTokens(string $value, callable $mapper): string
{
    return preg_replace_callback('/\b\d+\b/', function (array $m) use ($mapper): string {
        $target = $mapper((int)$m[0]);
        return $target === null ? $m[0] : (string)$target;
    }, $value) ?? $value;
}

function loadConfig(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Config file not found: {$path}");
    }

    $cfg = include $path;
    if (!is_array($cfg)) {
        throw new RuntimeException("Config file must return an array: {$path}");
    }

    $target = $cfg['target'] ?? null;
    $sources = $cfg['sources'] ?? $cfg['servers'] ?? null;

    if (!is_array($target)) {
        throw new RuntimeException("Config is missing target array");
    }
    if (!is_array($sources) || !$sources) {
        throw new RuntimeException("Config is missing sources array");
    }

    $normalisedSources = [];
    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }
        $server = (string)($source['server'] ?? $source['url'] ?? '');
        $registryDb = (string)($source['registryDatabase'] ?? $source['database'] ?? $source['db'] ?? '');
        if ($server === '' || $registryDb === '') {
            throw new RuntimeException('Each source requires server and registryDatabase');
        }
        $normalised = [
            'server' => normaliseServerUrl($server),
            'registryDatabase' => normaliseDbNameForApi($registryDb),
        ];

        foreach (['login', 'username', 'password', 'jwt', 'token', 'accessToken', 'authEndpoint'] as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                $normalised[$key] = (string)$source[$key];
            }
        }

        $normalisedSources[] = $normalised;
    }

    $target['database'] = (string)($target['database'] ?? TARGET_DB);

    return [
        'target' => $target,
        'sources' => $normalisedSources,
    ];
}

function connectTarget(array $cfg): PDO
{
    $host = trim((string)($cfg['dbHost'] ?? $cfg['host'] ?? '127.0.0.1'));
    $port = (int)($cfg['dbPort'] ?? $cfg['port'] ?? 3306);
    $user = (string)($cfg['dbAdminUsername'] ?? $cfg['user'] ?? '');
    $pass = (string)($cfg['dbAdminPassword'] ?? $cfg['password'] ?? '');
    $dbName = (string)($cfg['database'] ?? TARGET_DB);

    if ($user === '') {
        throw new RuntimeException('Target dbAdminUsername/user is required');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function normaliseServerUrl(string $server): string
{
    $server = trim($server);
    if ($server === '') {
        throw new RuntimeException('Empty source server URL');
    }
    return rtrim($server, '/') . '/';
}

function normaliseDbNameForApi(string $dbName): string
{
    $dbName = trim($dbName);
    return preg_replace('/^hdb_/i', '', $dbName) ?? $dbName;
}

function toInt(mixed $value): int
{
    if ($value === null || $value === '') {
        return 0;
    }
    return (int)$value;
}

function cleanNullable(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    $str = trim((string)$value);
    return $str === '' ? null : $str;
}

function normaliseTermDomain(string $domain): string
{
    return $domain === 'relation' ? 'relation' : 'enum';
}

function pdoParamType(mixed $value): int
{
    return match (true) {
        $value === null => PDO::PARAM_NULL,
        is_int($value) => PDO::PARAM_INT,
        is_bool($value) => PDO::PARAM_BOOL,
        default => PDO::PARAM_STR,
    };
}

function initialiseLogFile(): void
{
    if (!file_exists(LOG_FILE)) {
        touch(LOG_FILE);
    }
}

function logRunHeader(array $sources): void
{
    $lines = [];
    $lines[] = str_repeat('=', 90);
    $lines[] = 'Run started: ' . date('Y-m-d H:i:s');
    $lines[] = 'Sources accessed:';
    foreach ($sources as $index => $sourceCfg) {
        $lines[] = '  ' . ($index + 1) . '. ' . $sourceCfg['server'] . ' registry DB ' . $sourceCfg['registryDatabase'];
    }
    $lines[] = str_repeat('-', 90);

    $block = implode(PHP_EOL, $lines) . PHP_EOL;
    fwrite(STDOUT, $block);
    file_put_contents(LOG_FILE, $block, FILE_APPEND);
}

function logLine(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    fwrite(STDOUT, $line);
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

function logWarning(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] WARNING: ' . $message . PHP_EOL;
    fwrite(STDERR, $line);
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

function logError(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $message . PHP_EOL;
    fwrite(STDERR, $line);
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

function logConceptAction(string $dbName, string $type, array $spec, array $preparedRow, string $action): void
{
    $name = '';
    if (!empty($spec['name_field']) && array_key_exists($spec['name_field'], $preparedRow)) {
        $name = (string)$preparedRow[$spec['name_field']];
    } elseif ($type === 'RST') {
        $parts = [];
        if (isset($preparedRow['rst_RecTypeID'])) {
            $parts[] = 'RecType=' . $preparedRow['rst_RecTypeID'];
        }
        if (isset($preparedRow['rst_DetailTypeID'])) {
            $parts[] = 'DetailType=' . $preparedRow['rst_DetailTypeID'];
        }
        if (isset($preparedRow['rst_DisplayName']) && $preparedRow['rst_DisplayName'] !== '') {
            $parts[] = 'DisplayName=' . $preparedRow['rst_DisplayName'];
        }
        $name = implode('; ', $parts);
    }

    logLine(sprintf(
        '  %s | %s | %s | OriginDBID=%s | IDInOriginatingDB=%s | %s',
        $action,
        $dbName,
        $type,
        (string)($preparedRow[$spec['origin_db']] ?? ''),
        (string)($preparedRow[$spec['origin_id']] ?? ''),
        $name
    ));
}

final class Summary
{
    public int $servers = 0;
    public int $databasesSeen = 0;
    public int $databasesProcessed = 0;
    public int $databasesSkippedUnregistered = 0;
    public int $databasesSkippedTarget = 0;
    public int $warnings = 0;
    public int $errors = 0;
    public array $inserted = [
        'defRecTypes' => 0,
        'defDetailTypes' => 0,
        'defRecStructure' => 0,
        'defTerms' => 0,
    ];
    public array $reused = [
        'defRecTypes' => 0,
        'defDetailTypes' => 0,
        'defRecStructure' => 0,
        'defTerms' => 0,
    ];
    public array $updated = [
        'defRecTypes' => 0,
        'defDetailTypes' => 0,
        'defRecStructure' => 0,
        'defTerms' => 0,
    ];
}

final class ApiClient
{
    /** @var array<string,string> */
    private array $bearerTokensByServer = [];

    /**
     * Authenticate once per configured source server. If a pre-issued token is
     * supplied in config, use it. Otherwise, if login/password are supplied,
     * POST JSON credentials to the server login endpoint and extract JWT/token
     * from the JSON response.
     */
    public function authenticateSource(array $sourceCfg): void
    {
        $server = normaliseServerUrl((string)$sourceCfg['server']);

        $preissued = (string)($sourceCfg['jwt'] ?? $sourceCfg['token'] ?? $sourceCfg['accessToken'] ?? '');
        if ($preissued !== '') {
            $this->bearerTokensByServer[$server] = $preissued;
            logLine("  Auth: using pre-issued bearer token for {$server}");
            return;
        }

        
        $username = (string)($sourceCfg['username'] ?? '');
        $password = (string)($sourceCfg['password'] ?? '');
        if ($username === '' && $password === '') {
            logLine("  Auth: no username/password configured for {$server}; requests will be anonymous/sessionless");
            return;
        }
        if ($username === '' || $password === '') {
            throw new RuntimeException("Both username and password are required for JWT authentication on {$server}");
        }
        $registryDb = normaliseDbNameForApi((string)$sourceCfg['registryDatabase']);
        /*
        $endpoint = (string)($sourceCfg['authEndpoint'] ?? '');
        if ($endpoint === '') {
            $url = normaliseServerUrl($server) . 'api/' . rawurlencode($registryDb) . '/login';
        } elseif (preg_match('~^https?://~i', $endpoint)) {
            $url = $endpoint;
        } else {
            $url = normaliseServerUrl($server) . ltrim($endpoint, '/');
        }*/

        $url = normaliseServerUrl($server) . 'hserv/controller/auth.php';

        $json = $this->postJson($url, [
            'username' => $username,
            'password' => $password,
            'db' => $registryDb,
        ]);

        $token = $this->extractAuthToken($json);
        if ($token === null) {
            throw new RuntimeException("Login succeeded but no JWT/token was found in response from {$url}");
        }

        $this->bearerTokensByServer[$server] = $token;
        logLine("  Auth: acquired bearer token for {$server}");
    }

    public function fetchRegisteredDatabases(string $server, string $registryDb): array
    {
        $url = $this->buildUrl($server, $registryDb, 'dbs', [
            'details' => 'raw',
            'sys_dbRegisteredID' => '>0',
        ]);
        $json = $this->getJson($url, $server);
        return $this->extractRecords($json);
    }

    public function fetchRows(string $server, string $dbName, string $entity, array $filters, int $totalCount): array
    {
        $all = [];
        $offset = 0;

        if ($totalCount === 0) {
            $countParams = array_merge(['details' => 'id', 'limit' => 1, 'offset' => 0], $filters);
            $countUrl = $this->buildUrl($server, $dbName, $entity, $countParams);
            $countJson = $this->getJson($countUrl, $server);
            $totalCount = $this->extractTotalCount($countJson);
        }

        while ($offset < $totalCount) {
            $params = array_merge(['details' => 'raw', 'limit' => API_LIMIT, 'offset' => $offset], $filters);
            $url = $this->buildUrl($server, $dbName, $entity, $params);
            $json = $this->getJson($url, $server);
            $records = $this->extractRecords($json);
            $reccount = $this->extractReccount($json, count($records));

            foreach ($records as $record) {
                if (is_array($record)) {
                    $all[] = $record;
                }
            }

            if ($reccount <= 0) {
                break;
            }
            $offset += $reccount;
        }

        return $all;
    }

    private function buildUrl(string $server, string $dbName, string $entity, array $params): string
    {
        $dbName = normaliseDbNameForApi($dbName);
        return normaliseServerUrl($server)
            . 'api/' . rawurlencode($dbName)
            . '/' . rawurlencode($entity)
            . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function getJson(string $url, ?string $server = null): array
    {
        $body = $this->requestJson('GET', $url, null, $server);
        return $this->decodeJsonBody($url, $body);
    }

    private function postJson(string $url, array $payload): array
    {
        $body = $this->requestJson('POST', $url, json_encode($payload, JSON_UNESCAPED_SLASHES), null);
        return $this->decodeJsonBody($url, $body);
    }

    private function requestJson(string $method, string $url, ?string $body, ?string $server): string
    {
        $headers = [
            'Accept: application/json',
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($body);
        }

        $token = $server !== null ? ($this->bearerTokensByServer[normaliseServerUrl($server)] ?? null) : null;
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => HTTP_TIMEOUT_SECONDS,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body ?? '',
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException("HTTP request failed: {$url}");
        }

        $status = $this->extractHttpStatus($http_response_header ?? []);
        if ($status >= 400) {
            throw new RuntimeException("HTTP {$status} for {$url}: " . substr($responseBody, 0, 500));
        }

        return $responseBody;
    }

    private function decodeJsonBody(string $url, string $body): array
    {
        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new RuntimeException("Invalid JSON from {$url}: " . substr($body, 0, 500));
        }
        return $json;
    }

    private function extractAuthToken(array $json): ?string
    {
        foreach (['jwt', 'token', 'access_token', 'auth_token', 'bearer_token'] as $key) {
            if (isset($json[$key]) && is_string($json[$key]) && $json[$key] !== '') {
                return $json[$key];
            }
        }

        foreach (['data', 'response', 'result'] as $containerKey) {
            if (isset($json[$containerKey]) && is_array($json[$containerKey])) {
                $token = $this->extractAuthToken($json[$containerKey]);
                if ($token !== null) {
                    return $token;
                }
            }
        }

        return null;
    }

    private function extractHttpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
                return (int)$m[1];
            }
        }
        return 200;
    }

    private function extractRecords(array $json): array
    {
        if (array_key_exists('records', $json) && is_array($json['records'])) {
            return $json['records'];
        }
        // Backward tolerance for bare raw arrays.
        if (array_is_list($json)) {
            return $json;
        }
        return [];
    }

    private function extractTotalCount(array $json): int
    {
        if (array_key_exists('count', $json)) {
            return (int)$json['count'];
        }
        if (array_key_exists('reccount', $json)) {
            return (int)$json['reccount'];
        }
        $records = $this->extractRecords($json);
        return count($records);
    }

    private function extractReccount(array $json, int $fallback): int
    {
        if (array_key_exists('reccount', $json)) {
            return (int)$json['reccount'];
        }
        return $fallback;
    }

}

final class SourceDataset
{
    public string $server;
    public string $dbName;
    public int $registeredId;

    /** @var array<string,array<int,array>> */
    private array $harvestRows = ['RTY' => [], 'DTY' => [], 'TRM' => [], 'RST' => []];

    /** @var array<string,array<int,array>> */
    private array $allRowsByLocalId = ['RTY' => [], 'DTY' => [], 'TRM' => [], 'RST' => []];

    /** @var array<string,array<int,bool>> */
    private array $neededIds = ['RTY' => [], 'DTY' => [], 'TRM' => [], 'RST' => []];

    public function __construct(string $server, string $dbName, int $registeredId)
    {
        $this->server = $server;
        $this->dbName = $dbName;
        $this->registeredId = $registeredId;
    }

    public function label(): string
    {
        return "{$this->server} / {$this->dbName} / registered ID {$this->registeredId}";
    }

    public function addHarvestRow(string $type, array $row): void
    {
        $this->harvestRows[$type][] = $row;
        $this->addReferenceRow($type, $row);
    }

    public function addReferenceRow(string $type, array $row): void
    {
        $pk = ENTITY_SPECS[$type]['pk'];
        $id = toInt($row[$pk] ?? 0);
        if ($id > 0) {
            $this->allRowsByLocalId[$type][$id] = $row;
        }
    }

    public function need(string $type, int $localId): void
    {
        if ($localId > 0) {
            $this->neededIds[$type][$localId] = true;
        }
    }

    public function getNeededIds(string $type): array
    {
        return array_map('intval', array_keys($this->neededIds[$type]));
    }

    public function getMissingNeededLocalIds(string $type): array
    {
        $missing = [];
        foreach ($this->neededIds[$type] as $id => $_) {
            if (!isset($this->allRowsByLocalId[$type][(int)$id])) {
                $missing[] = (int)$id;
            }
        }
        sort($missing, SORT_NUMERIC);
        return $missing;
    }

    public function getHarvestRows(string $type): array
    {
        return $this->harvestRows[$type];
    }

    public function getAllRows(string $type): array
    {
        return array_values($this->allRowsByLocalId[$type]);
    }

    public function getRowByLocalId(string $type, int $localId): ?array
    {
        return $this->allRowsByLocalId[$type][$localId] ?? null;
    }

    public function resolveSourceOrigin(string $type, int $localId): ?array
    {
        $row = $this->getRowByLocalId($type, $localId);
        if (!$row) {
            return null;
        }
        return sourceOrigin($row, ENTITY_SPECS[$type], $this->registeredId);
    }
}

final class ReferenceCollector
{
    public function __construct(private SourceDataset $set) {}

    public function collectFromHarvestRows(): void
    {
        foreach ($this->set->getHarvestRows('DTY') as $row) {
            $this->collectFromDty($row);
        }
        foreach ($this->set->getHarvestRows('RST') as $row) {
            $this->collectFromRst($row);
        }
        foreach ($this->set->getHarvestRows('TRM') as $row) {
            $this->collectFromTrm($row);
        }
    }

    public function collectFromAllRows(string $type): void
    {
        foreach ($this->set->getAllRows($type) as $row) {
            if ($type === 'TRM') {
                $this->collectFromTrm($row);
            } elseif ($type === 'DTY') {
                $this->collectFromDty($row);
            } elseif ($type === 'RST') {
                $this->collectFromRst($row);
            }
        }
    }

    private function collectFromDty(array $row): void
    {
        foreach (extractIntegerTokens((string)($row['dty_JsonTermIDTree'] ?? '')) as $id) {
            $this->set->need('TRM', $id);
        }
        foreach (extractCsvIntegerIds((string)($row['dty_PtrTargetRectypeIDs'] ?? '')) as $id) {
            $this->set->need('RTY', $id);
        }
    }

    private function collectFromRst(array $row): void
    {
        $this->set->need('RTY', toInt($row['rst_RecTypeID'] ?? 0));
        $this->set->need('DTY', toInt($row['rst_DetailTypeID'] ?? 0));
    }

    private function collectFromTrm(array $row): void
    {
        $this->set->need('TRM', toInt($row['trm_ParentTermID'] ?? 0));
        $this->set->need('TRM', toInt($row['trm_InverseTermID'] ?? 0));
    }
}

function extractCsvIntegerIds(string $csv): array
{
    $ids = [];
    foreach (preg_split('/\s*,\s*/', trim($csv), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
        if (ctype_digit($part)) {
            $ids[] = (int)$part;
        }
    }
    return array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
}

function extractIntegerTokens(string $value): array
{
    if ($value === '') {
        return [];
    }
    preg_match_all('/\b\d+\b/', $value, $matches);
    $ids = array_map('intval', $matches[0] ?? []);
    return array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
}

final class TargetIdMap
{
    /** @var array<string,array<string,int>> */
    private array $map = ['RTY' => [], 'DTY' => [], 'TRM' => [], 'RST' => []];

    public function __construct(private TargetRepository $repo) {}

    public function put(string $type, int $originDb, int $originId, int $targetId): void
    {
        $this->map[$type][$this->key($originDb, $originId)] = $targetId;
    }

    public function get(string $type, int $originDb, int $originId): ?int
    {
        $key = $this->key($originDb, $originId);
        if (isset($this->map[$type][$key])) {
            return $this->map[$type][$key];
        }
        $spec = ENTITY_SPECS[$type];
        $id = $this->repo->findExistingTargetId($spec, $originDb, $originId);
        if ($id !== null) {
            $this->put($type, $originDb, $originId, $id);
        }
        return $id;
    }

    private function key(int $originDb, int $originId): string
    {
        return $originDb . '-' . $originId;
    }
}

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

    public function ensureGroupsForSourceDatabase(string $dbName): array
    {
        return [
            'rty' => $this->ensureGroup('defRecTypeGroups', 'rtg_ID', 'rtg_Name', $dbName, [
                'rtg_Domain' => 'functionalgroup',
                'rtg_Description' => "Harvested concepts from {$dbName}",
            ]),
            'dty' => $this->ensureGroup('defDetailTypeGroups', 'dtg_ID', 'dtg_Name', $dbName, [
                'dtg_Description' => "Harvested concepts from {$dbName}",
            ]),
            'trm' => [
                'enum' => $this->ensureGroup('defVocabularyGroups', 'vcg_ID', 'vcg_Name', "{$dbName} - enum", [
                    'vcg_Domain' => 'enum',
                    'vcg_Description' => "Harvested enum vocabularies from {$dbName}",
                ]),
                'relation' => $this->ensureGroup('defVocabularyGroups', 'vcg_ID', 'vcg_Name', "{$dbName} - relation", [
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
