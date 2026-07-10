<?php

declare(strict_types=1);

const CONFIG_FILE = __DIR__ . '/harvest_concepts_to_semantic_refdb_cfg.php';
const LOG_FILE    = __DIR__ . '/harvest_concepts_to_semantic_refdb.log';
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

function isTargetDatabaseName(string $dbName, string $targetDbName): bool
{
    $targetDbName = trim($targetDbName);
    if ($targetDbName === '') {
        return false;
    }

    $targetWithPrefix = $targetDbName;
    $targetWithoutPrefix = preg_replace('/^hdb_/i', '', $targetDbName) ?? $targetDbName;

    return strcasecmp($dbName, $targetWithPrefix) === 0
        || strcasecmp($dbName, $targetWithoutPrefix) === 0;
}

function isLocalSourceServer(string $server): bool
{
    $parts = parse_url(normaliseServerUrl($server));
    $host = strtolower((string)($parts['host'] ?? ''));

    return in_array($host, ['localhost', '127.0.0.1', '::1', 'heuristref.net'], true);
}

//
// loads definitions for given database
//

function loadHeuristCredentialDefaults(): array
{
    $candidateIniFiles = [
        // New location when this harvester is installed in /admin/harvest.
        __DIR__ . "/../../heuristConfigIni.php",
        // Legacy location used by the previous single-file script location.
        __DIR__ . "/../../../heuristConfigIni.php",
    ];

    $parentIni = null;
    foreach ($candidateIniFiles as $candidateIni) {
        if (is_file($candidateIni)) {
            $parentIni = $candidateIni;
            break;
        }
    }

    if ($parentIni === null) {
        return [];
    }

    include $parentIni;

    $defaults = [];
    foreach (['dbAdminUsername', 'dbAdminPassword', 'passwordForDatabaseAccess'] as $name) {
        if (isset(${$name}) && ${$name} !== '') {
            $defaults[$name] = (string)${$name};
        }
    }

    return $defaults;
}

function loadConfig(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Config file not found: {$path}");
    }

    $credentialDefaults = loadHeuristCredentialDefaults();

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

    $targetDatabase = trim((string)($target['database'] ?? ''));
    if ($targetDatabase === '') {
        throw new RuntimeException("Config target.database is required");
    }

    $target['database'] = $targetDatabase;
    $target['dbAdminUsername'] = (string)(
        $target['dbAdminUsername']
        ?? $target['user']
        ?? $credentialDefaults['dbAdminUsername']
        ?? ''
    );
    $target['dbAdminPassword'] = (string)(
        $target['dbAdminPassword']
        ?? $target['password']
        ?? $credentialDefaults['dbAdminPassword']
        ?? ''
    );

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

        $sourceUsername = (string)($source['username'] ?? $source['login'] ?? '');
        $sourcePassword = (string)($source['password'] ?? '');

        if ($sourceUsername === '' && $sourcePassword === '') {
            // If source credentials are omitted completely, use the standard
            // database-access user and password from heuristConfigIni.php.
            $sourceUsername = '2';
            $sourcePassword = (string)($credentialDefaults['passwordForDatabaseAccess'] ?? '');
        } elseif ($sourcePassword === '' && $sourceUsername !== '') {
            $sourcePassword = (string)($credentialDefaults['passwordForDatabaseAccess'] ?? '');
        }

        if ($sourceUsername !== '') {
            $normalised['username'] = $sourceUsername;
        }
        if ($sourcePassword !== '') {
            $normalised['password'] = $sourcePassword;
        }

        foreach (['jwt', 'token', 'accessToken', 'authEndpoint'] as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                $normalised[$key] = (string)$source[$key];
            }
        }

        $normalisedSources[] = $normalised;
    }

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
    $dbName = trim((string)($cfg['database'] ?? ''));

    if ($dbName === '') {
        throw new RuntimeException('Target database is required');
    }
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
