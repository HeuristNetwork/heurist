

<?php
/**
 * harvest_concepts_to_semantic_refdb.php
 *
 * Harvest locally-defined semantic concepts from all Heurist databases reachable
 * from the current server and configured remote servers, and copy them into
 * hdb_Heurist_Concept_Definitions.
 *
 * Written by ChatGPT 8 April 2026, prompt design and feedback by Ian Johnson
 * 
 * This is intended for running only on HeuristRef.net with connections to other MySQL servers in the Heuriverse.
 * First time it may be run on Huma-Num server for speed, the nthe database and config file transferred to HeuristRef.net for subsequent updates
 * 
 * Configuration of server access in /var/www/html/HEURIST/harvest_concepts_to_semantic_refdb.cfg * 
 * A template for this file is included in the source code in the /admin/describe directory
 * 
 * Run from command line:
 *   php /var/www/html/HEURIST/admin/describe/harvest_concepts_to_semantic_refdb.php
 *
 * Assumptions from the specification:
 * - harvest_concepts_source_dbs.txt is correctly formatted; minimal validation only.
 * - Remote servers are reachable through SSH local forwarding or equivalent, so a
 *   normal MySQL TCP connection to the configured host/port works.
 * - Target database has tables structurally compatible with source tables, with any
 *   extra columns only appearing at the end and having defaults / nullability that
 *   allow INSERTs which omit them.
 * - We do not attempt, in this script, to resolve composite reference codes back to
 *   local IDs in the target database. We only encode them as RTYxxxx-yyyy / TRMxxxx-yyyy.
 */

declare(strict_types=1);

const CONFIG_FILE = '/var/www/html/HEURIST/harvest_concepts_to_semantic_refdb.cfg';
const LOG_FILE    = __DIR__ . '/harvest_concepts_to_semantic_refdb.log';
const TARGET_DB   = 'hdb_Heurist_Concept_Definitions';

const TABLE_SPECS = [
    'defRecTypes' => [
        'prefix' => 'rty_',
        'pk' => 'rty_ID',
        'origin_db' => 'rty_OriginatingDBID',
        'origin_id' => 'rty_IDInOriginatingDB',
        'origin_name' => 'rty_NameInOriginatingDB',
        'name_field' => 'rty_Name',
        'special_transform' => null,
    ],
    'defDetailTypes' => [
        'prefix' => 'dty_',
        'pk' => 'dty_ID',
        'origin_db' => 'dty_OriginatingDBID',
        'origin_id' => 'dty_IDInOriginatingDB',
        'origin_name' => 'dty_NameInOriginatingDB',
        'name_field' => 'dty_Name',
        'special_transform' => 'transformDetailTypeRow',
    ],
    'defRecStructure' => [
        'prefix' => 'rst_',
        'pk' => 'rst_ID',
        'origin_db' => 'rst_OriginatingDBID',
        'origin_id' => 'rst_IDInOriginatingDB',
        'origin_name' => null,
        'name_field' => null,
        'special_transform' => null,
    ],
    'defTerms' => [
        'prefix' => 'trm_',
        'pk' => 'trm_ID',
        'origin_db' => 'trm_OriginatingDBID',
        'origin_id' => 'trm_IDInOriginatingDB',
        'origin_name' => 'trm_NameInOriginatingDB',
        'name_field' => 'trm_Label',
        'special_transform' => null,
    ],
];

main($argv);

function main(array $argv): void
{
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "This script must be run from the command line.\n");
        exit(1);
    }

    $servers = parseServerConfig(CONFIG_FILE);
    if (!$servers) {
        fwrite(STDERR, "No servers found in config file: " . CONFIG_FILE . "\n");
        exit(1);
    }

    $summary = [
        'servers' => 0,
        'databases_seen' => 0,
        'databases_processed' => 0,
        'databases_skipped_unregistered' => 0,
        'databases_skipped_target' => 0,
        'rows_inserted' => [
            'defRecTypes' => 0,
            'defDetailTypes' => 0,
            'defRecStructure' => 0,
            'defTerms' => 0,
        ],
        'rows_skipped_derivative' => [
            'defRecTypes' => 0,
            'defDetailTypes' => 0,
            'defRecStructure' => 0,
            'defTerms' => 0,
        ],
        'errors' => 0,
    ];

    initialiseLogFile();
    logRunHeader($servers);

    foreach ($servers as $serverIndex => $serverCfg) {
        $summary['servers']++;
        logLine(str_repeat('=', 90));
        logLine('SERVER ' . ($serverIndex + 1) . ': ' . describeServer($serverCfg));

        try {
            $adminPdo = connectServerAdmin($serverCfg);
            $targetPdo = connectToDatabase($serverCfg, TARGET_DB);
            $targetColumnsCache = getTargetColumnsCache($targetPdo);
            $databases = listHeuristDatabases($adminPdo);
        } catch (Throwable $e) {
            $summary['errors']++;
            logError('Unable to initialise server ' . describeServer($serverCfg) . ': ' . $e->getMessage());
            continue;
        }

        foreach ($databases as $dbName) {
            $summary['databases_seen']++;

            if ($dbName === TARGET_DB) {
                $summary['databases_skipped_target']++;
                logLine("Skipping target database {$dbName}");
                continue;
            }

            try {
                processDatabase($serverCfg, $dbName, $targetPdo, $targetColumnsCache, $summary);
            } catch (Throwable $e) {
                $summary['errors']++;
                logError("Unhandled error while processing {$dbName}: " . $e->getMessage());
            }
        }
    }

    logLine(str_repeat('=', 90));
    logLine('SUMMARY');
    logLine('Servers processed: ' . $summary['servers']);
    logLine('Databases seen: ' . $summary['databases_seen']);
    logLine('Databases processed: ' . $summary['databases_processed']);
    logLine('Databases skipped (target): ' . $summary['databases_skipped_target']);
    logLine('Databases skipped (unregistered): ' . $summary['databases_skipped_unregistered']);
    foreach ($summary['rows_inserted'] as $table => $count) {
        logLine("Inserted {$table}: {$count}");
    }
    foreach ($summary['rows_skipped_derivative'] as $table => $count) {
        logLine("Skipped derivative {$table}: {$count}");
    }
    logLine('Errors: ' . $summary['errors']);
}

function processDatabase(array $serverCfg, string $dbName, PDO $targetPdo, array $targetColumnsCache, array &$summary): void
{
    $sourcePdo = connectToDatabase($serverCfg, $dbName);

    $registeredId = getRegisteredDbId($sourcePdo);
    if (!$registeredId) {
        $summary['databases_skipped_unregistered']++;
        logLine("Skipping unregistered database {$dbName}");
        return;
    }

    $summary['databases_processed']++;
    logLine(str_repeat('-', 90));
    logLine("Processing database {$dbName} (Registered ID {$registeredId})");

    $resolver = new ConceptOriginResolver($sourcePdo);

    foreach (TABLE_SPECS as $tableName => $spec) {
        $inserted = 0;
        $skippedDerivative = 0;
        $skippedDuplicate = 0;

        $sql = "SELECT * FROM `{$tableName}`";
        $stmt = $sourcePdo->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rowOriginDbId = (int)($row[$spec['origin_db']] ?? 0);
            if ($rowOriginDbId !== 0 && $rowOriginDbId !== $registeredId) {
                $skippedDerivative++;
                continue;
            }

            $preparedRow = prepareRowForTarget($row, $spec, $registeredId, $resolver);
            $didInsert = insertRowIntoTarget($targetPdo, $tableName, $preparedRow, $targetColumnsCache[$tableName]);
            if ($didInsert) {
                logConceptAction($dbName, $tableName, $spec, $preparedRow);
                $inserted++;
            } else {
                $skippedDuplicate++;
            }
        }

        $summary['rows_inserted'][$tableName] += $inserted;
        $summary['rows_skipped_derivative'][$tableName] += $skippedDerivative;
        logLine("  {$tableName}: inserted {$inserted}, skipped derivative {$skippedDerivative}, skipped duplicate {$skippedDuplicate}");
    }
}

function prepareRowForTarget(array $row, array $spec, int $registeredId, ConceptOriginResolver $resolver): array
{
    $pk = $spec['pk'];
    $originDbField = $spec['origin_db'];
    $originIdField = $spec['origin_id'];
    $originNameField = $spec['origin_name'];
    $nameField = $spec['name_field'];

    $originalId = $row[$pk];

    unset($row[$pk]); // let target allocate a new local ID

    $row[$originDbField] = $registeredId;
    $row[$originIdField] = $originalId;

    if ($originNameField && $nameField && array_key_exists($nameField, $row)) {
        $row[$originNameField] = $row[$nameField];
    }

    $row = applyFixedTargetValues($row, $spec);

    if ($spec['prefix'] === 'trm_') {
        $row = transformTermRow($row, $resolver);
    }

    if ($spec['prefix'] === 'rst_') {
        $row = transformRecStructureRow($row, $resolver);
    }

    if (!empty($spec['special_transform'])) {
        $fn = $spec['special_transform'];
        $row = $fn($row, $resolver);
    }

    return $row;
}

function transformDetailTypeRow(array $row, ConceptOriginResolver $resolver): array
{
    if (array_key_exists('dty_JsonTermIDTree', $row) && $row['dty_JsonTermIDTree'] !== null && $row['dty_JsonTermIDTree'] !== '') {
        $row['dty_JsonTermIDTree'] = rewriteTermTreeReferences((string)$row['dty_JsonTermIDTree'], $resolver);
    }

    if (array_key_exists('dty_PtrTargetRectypeIDs', $row) && $row['dty_PtrTargetRectypeIDs'] !== null && trim((string)$row['dty_PtrTargetRectypeIDs']) !== '') {
        $row['dty_PtrTargetRectypeIDs'] = rewriteRectypeReferenceList((string)$row['dty_PtrTargetRectypeIDs'], $resolver);
    }

    return $row;
}

function transformTermRow(array $row, ConceptOriginResolver $resolver): array
{
    if (array_key_exists('trm_ParentTermID', $row) && $row['trm_ParentTermID'] !== null && (string)$row['trm_ParentTermID'] !== '') {
        $origin = $resolver->resolveTerm((int)$row['trm_ParentTermID']);
        $row['trm_ParentTermID'] = formatCompositeCode('TRM', $origin['dbid'], $origin['id']);
    }

    if (array_key_exists('trm_InverseTermID', $row) && $row['trm_InverseTermID'] !== null && (string)$row['trm_InverseTermID'] !== '') {
        $origin = $resolver->resolveTerm((int)$row['trm_InverseTermID']);
        $row['trm_InverseTermID'] = formatCompositeCode('TRM', $origin['dbid'], $origin['id']);
    }

    return $row;
}

function transformRecStructureRow(array $row, ConceptOriginResolver $resolver): array
{
    if (array_key_exists('rst_RecTypeID', $row) && $row['rst_RecTypeID'] !== null && (string)$row['rst_RecTypeID'] !== '') {
        $origin = $resolver->resolveRectype((int)$row['rst_RecTypeID']);
        $row['rst_RecTypeID'] = formatCompositeCode('RTY', $origin['dbid'], $origin['id']);
    }
    if (array_key_exists('rst_DetailTypeID', $row) && $row['rst_DetailTypeID'] !== null && (string)$row['rst_DetailTypeID'] !== '') {
        $origin = $resolver->resolveDetailType((int)$row['rst_DetailTypeID']);
        $row['rst_DetailTypeID'] = formatCompositeCode('DTY', $origin['dbid'], $origin['id']);
    }

    return $row;
}

function applyFixedTargetValues(array $row, array $spec): array
{
    switch ($spec['prefix']) {
        case 'dty_':
            $row['dty_DetailTypeGroupID'] = 1;
            break;

        case 'rty_':
            $row['rty_RecTypeGroupID'] = 1;
            break;

        case 'trm_':
            $row['trm_VocabularyGroupID'] = 1;
            break;

        case 'rst_':
            $row['rst_CalcFunctionID'] = null;
            break;
    }

    return $row;
}

function rewriteTermTreeReferences(string $value, ConceptOriginResolver $resolver): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return $value;
    }

    // Heurist stores this as JSON-ish content in many cases. We replace all standalone
    // integer tokens with TRM<originDB>-<originID>. This intentionally preserves the
    // surrounding JSON / punctuation for the later resolution phase.
    return preg_replace_callback('/\b\d+\b/', function (array $m) use ($resolver) {
        $localId = (int)$m[0];
        $origin = $resolver->resolveTerm($localId);
        return formatCompositeCode('TRM', $origin['dbid'], $origin['id']);
    }, $value) ?? $value;
}

function rewriteRectypeReferenceList(string $value, ConceptOriginResolver $resolver): string
{
    $parts = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return $value;
    }

    $mapped = [];
    foreach ($parts as $part) {
        if (ctype_digit($part)) {
            $origin = $resolver->resolveRectype((int)$part);
            $mapped[] = formatCompositeCode('RTY', $origin['dbid'], $origin['id']);
        } else {
            $mapped[] = $part;
        }
    }

    return implode(',', $mapped);
}

function formatCompositeCode(string $prefix, int $originatingDbId, int $idInOriginatingDb): string
{
    return sprintf('%s%d-%d', $prefix, $originatingDbId, $idInOriginatingDb);
}

function insertRowIntoTarget(PDO $targetPdo, string $tableName, array $row, array $targetColumns): bool
{
    if (rowAlreadyExistsInTarget($targetPdo, $tableName, $row)) {
        return false;
    }

    $filtered = [];
    foreach ($targetColumns as $col) {
        if ($col === null) {
            continue;
        }
        if (array_key_exists($col, $row)) {
            $filtered[$col] = $row[$col];
        }
    }

    if (!$filtered) {
        throw new RuntimeException("No matching columns to insert into {$tableName}");
    }

    $columns = array_keys($filtered);
    $placeholders = array_map(fn($c) => ':' . $c, $columns);

    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s)',
        $tableName,
        implode(', ', array_map(fn($c) => "`{$c}`", $columns)),
        implode(', ', $placeholders)
    );

    $stmt = $targetPdo->prepare($sql);
    foreach ($filtered as $col => $val) {
        $stmt->bindValue(':' . $col, $val, pdoParamType($val));
    }

    if (!$stmt->execute()) {
        $err = $stmt->errorInfo();
        throw new RuntimeException("Insert failed for {$tableName}: " . implode(' | ', $err));
    }

    return true;
}

function rowAlreadyExistsInTarget(PDO $targetPdo, string $tableName, array $row): bool
{
    $spec = TABLE_SPECS[$tableName];
    $originDbField = $spec['origin_db'];
    $originIdField = $spec['origin_id'];

    if (!isset($row[$originDbField]) || !isset($row[$originIdField])) {
        throw new RuntimeException("Cannot check duplicates for {$tableName}: missing origin fields");
    }

    $sql = sprintf(
        'SELECT 1 FROM `%s` WHERE `%s` = :originDbId AND `%s` = :originId LIMIT 1',
        $tableName,
        $originDbField,
        $originIdField
    );

    $stmt = $targetPdo->prepare($sql);
    $stmt->bindValue(':originDbId', (int)$row[$originDbField], PDO::PARAM_INT);
    $stmt->bindValue(':originId', (int)$row[$originIdField], PDO::PARAM_INT);
    $stmt->execute();

    return (bool)$stmt->fetchColumn();
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

function getTargetColumnsCache(PDO $targetPdo): array
{
    $cache = [];
    foreach (array_keys(TABLE_SPECS) as $tableName) {
        $cache[$tableName] = getTableColumns($targetPdo, $tableName);
    }
    return $cache;
}

function getTableColumns(PDO $pdo, string $tableName): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
    $cols = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

function getRegisteredDbId(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT sys_dbRegisteredID FROM sysIdentification LIMIT 1');
    $value = $stmt->fetchColumn();
    return (int)($value ?: 0);
}

function listHeuristDatabases(PDO $adminPdo): array
{
    $stmt = $adminPdo->query("SHOW DATABASES LIKE 'hdb\\_%'");
    $dbs = [];
    while (($db = $stmt->fetchColumn()) !== false) {
        $dbs[] = (string)$db;
    }
    sort($dbs, SORT_NATURAL | SORT_FLAG_CASE);
    return $dbs;
}

function connectServerAdmin(array $cfg): PDO
{
    return connectPdo($cfg, null);
}

function connectToDatabase(array $cfg, string $dbName): PDO
{
    return connectPdo($cfg, $dbName);
}

function connectPdo(array $cfg, ?string $dbName): PDO
{
    $host = trim((string)($cfg['externalServer'] ?? ''));
    if ($host === '') {
        $host = '127.0.0.1';
    }

    $port = (int)($cfg['dbPort'] ?? 3306);
    $user = (string)$cfg['dbAdminUsername'];
    $pass = (string)$cfg['dbAdminPassword'];

    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    if ($dbName !== null) {
        $dsn .= ";dbname={$dbName}";
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function parseServerConfig(string $filePath): array
{
    if (!is_file($filePath)) {
        throw new RuntimeException("Config file not found: {$filePath}");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    $servers = [];
    $current = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)$/', $trimmed, $m)) {
            continue;
        }

        $key = $m[1];
        $rawValue = trim($m[2]);
        $value = unquoteConfigValue($rawValue);

        if ($key === 'externalServer') {
            if ($current) {
                $servers[] = normaliseServerConfig($current);
            }
            $current = ['externalServer' => $value];
        } else {
            $current[$key] = $value;
        }
    }

    if ($current) {
        $servers[] = normaliseServerConfig($current);
    }

    return $servers;
}

function unquoteConfigValue(string $value): string
{
    $value = trim($value);

    if (
        (str_starts_with($value, "'") && str_ends_with($value, "'")) ||
        (str_starts_with($value, '"') && str_ends_with($value, '"'))
    ) {
        $value = substr($value, 1, -1);
    }

    return trim($value);
}

function normaliseServerConfig(array $cfg): array
{
    return [
        'externalServer' => (string)($cfg['externalServer'] ?? ''),
        'dbPort' => (int)($cfg['dbPort'] ?? 3306),
        'dbAdminUsername' => (string)($cfg['dbAdminUsername'] ?? ''),
        'dbAdminPassword' => (string)($cfg['dbAdminPassword'] ?? ''),
    ];
}

function describeServer(array $cfg): string
{
    $host = trim((string)$cfg['externalServer']);
    return ($host === '' ? 'current-server' : $host) . ':' . (int)$cfg['dbPort'];
}

function initialiseLogFile(): void
{
    if (!file_exists(LOG_FILE)) {
        touch(LOG_FILE);
    }
}

function logRunHeader(array $servers): void
{
    $lines = [];
    $lines[] = str_repeat('=', 90);
    $lines[] = 'Run started: ' . date('Y-m-d H:i:s');
    $lines[] = 'Servers accessed:';
    foreach ($servers as $index => $serverCfg) {
        $lines[] = '  ' . ($index + 1) . '. ' . describeServer($serverCfg);
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

function logError(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $message . PHP_EOL;
    fwrite(STDERR, $line);
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

function logConceptAction(string $dbName, string $tableName, array $spec, array $preparedRow): void
{
    $typeCode = strtoupper(substr($spec['prefix'], 0, 3));
    $originDbField = $spec['origin_db'];
    $originIdField = $spec['origin_id'];

    $name = '';
    if (!empty($spec['name_field']) && array_key_exists($spec['name_field'], $preparedRow)) {
        $name = (string)$preparedRow[$spec['name_field']];
    } elseif ($typeCode === 'RST') {
        $nameParts = [];
        if (isset($preparedRow['rst_RecTypeID'])) {
            $nameParts[] = 'RecType=' . $preparedRow['rst_RecTypeID'];
        }
        if (isset($preparedRow['rst_DetailTypeID'])) {
            $nameParts[] = 'DetailType=' . $preparedRow['rst_DetailTypeID'];
        }
        if (isset($preparedRow['rst_DisplayName']) && $preparedRow['rst_DisplayName'] !== '') {
            $nameParts[] = 'DisplayName=' . $preparedRow['rst_DisplayName'];
        }
        $name = implode('; ', $nameParts);
    }

    logLine(sprintf(
        '  %s | %s | OriginDBID=%s | IDInOriginatingDB=%s | %s',
        $dbName,
        $typeCode,
        (string)($preparedRow[$originDbField] ?? ''),
        (string)($preparedRow[$originIdField] ?? ''),
        $name
    ));
}

final class ConceptOriginResolver
{
    private PDO $pdo;
    private array $termCache = [];
    private array $rectypeCache = [];
    private array $detailTypeCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function resolveTerm(int $localTermId): array
    {
        if (!isset($this->termCache[$localTermId])) {
            $stmt = $this->pdo->prepare(
                'SELECT trm_OriginatingDBID, trm_IDInOriginatingDB FROM defTerms WHERE trm_ID = ?'
            );
            $stmt->execute([$localTermId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException("Referenced term {$localTermId} not found in source database");
            }

            $originDb = (int)($row['trm_OriginatingDBID'] ?? 0);
            $originId = (int)($row['trm_IDInOriginatingDB'] ?? 0);

            if ($originDb > 0 && $originId > 0) {
                $this->termCache[$localTermId] = ['dbid' => $originDb, 'id' => $originId];
            } else {
                $registeredId = getRegisteredDbId($this->pdo);
                $this->termCache[$localTermId] = ['dbid' => $registeredId, 'id' => $localTermId];
            }
        }

        return $this->termCache[$localTermId];
    }

    public function resolveRectype(int $localRectypeId): array
    {
        if (!isset($this->rectypeCache[$localRectypeId])) {
            $stmt = $this->pdo->prepare(
                'SELECT rty_OriginatingDBID, rty_IDInOriginatingDB FROM defRecTypes WHERE rty_ID = ?'
            );
            $stmt->execute([$localRectypeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException("Referenced rectype {$localRectypeId} not found in source database");
            }

            $originDb = (int)($row['rty_OriginatingDBID'] ?? 0);
            $originId = (int)($row['rty_IDInOriginatingDB'] ?? 0);

            if ($originDb > 0 && $originId > 0) {
                $this->rectypeCache[$localRectypeId] = ['dbid' => $originDb, 'id' => $originId];
            } else {
                $registeredId = getRegisteredDbId($this->pdo);
                $this->rectypeCache[$localRectypeId] = ['dbid' => $registeredId, 'id' => $localRectypeId];
            }
        }

        return $this->rectypeCache[$localRectypeId];
    }

    public function resolveDetailType(int $localDetailTypeId): array
    {
        if (!isset($this->detailTypeCache[$localDetailTypeId])) {
            $stmt = $this->pdo->prepare(
                'SELECT dty_OriginatingDBID, dty_IDInOriginatingDB FROM defDetailTypes WHERE dty_ID = ?'
            );
            $stmt->execute([$localDetailTypeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException("Referenced detail type {$localDetailTypeId} not found in source database");
            }

            $originDb = (int)($row['dty_OriginatingDBID'] ?? 0);
            $originId = (int)($row['dty_IDInOriginatingDB'] ?? 0);

            if ($originDb > 0 && $originId > 0) {
                $this->detailTypeCache[$localDetailTypeId] = ['dbid' => $originDb, 'id' => $originId];
            } else {
                $registeredId = getRegisteredDbId($this->pdo);
                $this->detailTypeCache[$localDetailTypeId] = ['dbid' => $registeredId, 'id' => $localDetailTypeId];
            }
        }

        return $this->detailTypeCache[$localDetailTypeId];
    }
}
