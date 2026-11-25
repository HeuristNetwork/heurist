#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Heurist maintenance runner. PHP CLI 8.1+ with PDO MySQL and ZipArchive.
 *
 * Install under a root-controlled directory. Set these entries in
 * heuristConfigIni.php (the defaults below apply when omitted):
 *
 *   $monitoringBackupDir = '/data/BACKUP';
 *   $monitoringDir = '/data/MONITORING';
 *   $sysAdminEmail = 'admin@example.org'; // or existing administrator mailing group
 *   $monitoringEmailFrom = 'heurist@example.org'; // defaults to first admin
 *   $monitoringMysqlLoginPath = 'heurist-maintenance'; // optional root CLI login path for mysqldump
 *   $monitoringHeuristCodeDir = '/var/www/html/HEURIST/heurist'; // set for versioned installs
 *   $monitoringMembershipScript = '/absolute/path/to/refreshMembershipList.php'; // optional override
 *   $monitoringMembershipBatchScript = '/absolute/path/to/checkMembershipBatch.php'; // optional override
 *   $monitoringMembershipBatchDatabase = 'Heurist_Job_Tracker'; // optional
 *   $monitoringMembershipOutput = '/var/www/html/HEURIST/association_members.txt'; // optional
 *   $monitoringPackageScript = '/absolute/path/to/buildArchivePackagesCMD.php';
 *   $monitoringPackageDatabases = ['dbname']; // or 'ALL' (potentially very expensive)
 *   $monitoringBookStackDir = '/data/HEURIST_FILESTORE/_DBS_FROM_REMOTES/BookStack'; // receiver
 *   $monitoringBookStackBook = '7_Understanding_Heurist_2026_Vsn_7'; // receiver check target
 *   $monitoringBookStack = [ // producer, on HeuristRef only; requires PHP cURL
 *       'backupDir' => '/srv/BACKUP/BookStack',
 *       'mysqlLoginPath' => 'bookstack',
 *       'token' => 'TOKEN_ID:TOKEN_SECRET',
 *       'remote' => 'dbtunnel@heurist.huma-num.fr:/data/HEURIST_FILESTORE/_DBS_FROM_REMOTES/BookStack',
 *       'sshKey' => '/home/tunnel/.ssh/dbtunnel_ed25519',
 *       'gitRepo' => '/srv/BACKUP/BookStack_github_staging/heurist', // optional
 *   ];
 *   $monitoringWebsiteBaseUrl = 'https://heurist.example.org/heurist/';
 *   $monitoringPublishWebsiteList = true; // opt in to legacy public HTML
 *   $monitoringServerUrls = ['https://heurist.example.org/heurist/']; // optional inventory
 *   $monitoringPauseSeconds = 20;
 *
 * Existing config settings used: serverName, defaultRootFileUploadPath,
 * dbHost, dbPort, dbAdminUsername, dbAdminPassword and sysAdminEmail.
 *
 * Install at /var/www/html/HEURIST/heurist/server_managment/nightly_procedures.php
 * (The upstream directory is normally spelled server_management.) Run with
 * the server's PHP CLI from root's crontab. The runner finds heuristConfigIni.php
 * in an ancestor directory. Example HeuristRef selection:
 *   --runList=BOOKSTACK,STANDARD,MEMBERSHIP
 * Schedule ALERTSERVER independently every five minutes. On Huma-Num use
 * BOOKSTACK_CHECK after the expected transfer, without configuring the producer.
 *
 * CLI also accepts '?runList=STANDARD,BOOKSTACK,MEMBERSHIP' as one quoted
 * argument. HTTP execution is disabled. Use --list to inspect the job order
 * or combine --dry-run with a selection to inspect it without loading config.
 * All orchestration is PHP. External commands use argv arrays, without a shell.
 */

// Job registration defines execution order and documents every runnable task.
const JOBS = [
    'alertServer'         => 'Check one-minute load and disk usage and throttle repeated alerts.',
    'bookStackWorkflow'   => 'Export, verify and publish the HeuristRef BookStack backups.',
    'backupDatabases'     => 'Stream each database dump through bzip2 and replace successful backups atomically.',
    'archiveDatabases'    => 'Archive changed dumps and preserve the superseded copies.',
    'pruneArchiveMonthly' => 'Keep recent dated database dumps and the latest dated copy per month.',
    'pruneArchiveLatest'  => 'Keep four dated backups per database under the legacy cleanup policy.',
    'checkRequiredTables' => 'Detect missing essential base tables across all visible Heurist schemas.',
    'listDatabases'       => 'List owners, record counts, last changes, registration and format versions.',
    'listRecordTypes'     => 'List distinct record-type names.',
    'listConceptIds'      => 'List record types with originating database and concept ID.',
    'listFields'          => 'List distinct field labels and help text.',
    'listUsersWithDbs'    => 'List users and activity by database.',
    'listUniqueUsers'     => 'Update the cumulative users by email and its monthly ZIP.',
    'listWebsites'        => 'List configured CMS homepages and links in TSV and HTML.',
    'listFilestoreSizes'  => 'Measure allocated space of each top-level filestore entry.',
    'checkBookStack'      => 'Check the age, layout and file counts of BookStack backups.',
    'refreshMembership'   => 'Publish and verify the current list of association members on HeuristRef.',
    'checkMembershipBatch'=> 'Refresh the membership status recorded on Heurist job tracker tickets.',
    'scanLegacyLinks'     => 'Find old hN-alpha references in text files in the filestore.',
    'buildArchivePackages'=> 'Run the Heurist archive package builder for configured databases.',
    'listHeuristServers'  => 'Write the configured Heurist server URL inventory.',
];

// Add a callable to JOBS, then include it in one or more profiles here.
const RUN_LISTS = [
    'STANDARD' => [
        'backupDatabases', 'archiveDatabases', 'pruneArchiveMonthly',
        'checkRequiredTables', 'listDatabases', 'listRecordTypes', 'listConceptIds',
        'listFields', 'listUsersWithDbs', 'listUniqueUsers', 'listWebsites',
    ],
    'ALERTSERVER' => ['alertServer'],
    'BOOKSTACK' => ['bookStackWorkflow'],
    'BOOKSTACK_CHECK' => ['checkBookStack'],
    'MEMBERSHIP' => ['refreshMembership', 'checkMembershipBatch'],
    'MEMBERSHIP_REFRESH' => ['refreshMembership'],
    'WEEKLY' => ['listFilestoreSizes'],
    'MONTHLY' => ['scanLegacyLinks'],
    'PACKAGES' => ['buildArchivePackages'],
    'SERVERS' => ['listHeuristServers'],
    'ARCHIVE_LATEST4' => ['pruneArchiveLatest'],
];

const REQUIRED_TABLES = [
    'defCalcFunctions', 'defCrosswalk', 'defDetailTypeGroups', 'defDetailTypes',
    'defFileExtToMimetype', 'defOntologies', 'defRecStructure', 'defRecTypeGroups',
    'defRecTypes', 'defRelationshipConstraints', 'defTerms', 'defTermsLinks',
    'defTranslations', 'defURLPrefixes', 'defVocabularyGroups', 'Records',
    'recDetails', 'recDetailsDateIndex', 'recForwarding', 'recLinks',
    'recSimilarButNotDupes', 'recThreadedComments', 'recUploadedFiles', 'sysArchive',
    'sysDashboard', 'sysDocumentation', 'sysIdentification', 'sysLocks', 'sysUGrps',
    'sysUsrGrpLinks', 'sysWorkflowRules', 'usrBookmarks', 'usrHyperlinkFilters',
    'usrRecPermissions', 'usrRecTagLinks', 'usrReminders', 'usrRemindersBlockList',
    'usrReportSchedule', 'usrSavedSearches', 'usrTags', 'usrWorkingSubsets',
];

final class Result
{
    public string $status = 'Success';
    public string $summary = '';
    public array $details = [];
    public int $issueCount = 0;
    public float $seconds = 0;

    public function issue(string $message, string $level = 'Failure'): void
    {
        if ($level === 'Failure' || $this->status === 'Success') {
            $this->status = $level;
        }
        ++$this->issueCount;
        if (count($this->details) < 100) {
            $this->details[] = "$level: $message";
        }
    }
}

function loadSettings(string $file): array
{
    if (!is_file($file) || !is_readable($file)) {
        throw new RuntimeException("Cannot read Heurist configuration: $file");
    }
    require $file;
    $recipients = is_array($sysAdminEmail ?? null)
        ? $sysAdminEmail : preg_split('/[,;\s]+/', trim($sysAdminEmail ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $recipients = array_values(array_unique(array_map('trim', $recipients)));
    if (!$recipients) {
        throw new RuntimeException('Define $sysAdminEmail in heuristConfigIni.php.');
    }
    $sender = $monitoringEmailFrom ?? $recipients[0];
    foreach (array_merge($recipients, [$sender]) as $address) {
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid mail address in Heurist configuration.');
        }
    }
    $root = dirname($file);
    $code = rtrim($monitoringHeuristCodeDir ?? "$root/heurist", '/');
    $backup = rtrim($monitoringBackupDir ?? '/data/BACKUP', '/');
    $monitor = rtrim($monitoringDir ?? '/data/MONITORING', '/');
    foreach ([$backup, $monitor] as $directory) {
        if (!str_starts_with($directory, '/')) {
            throw new RuntimeException('Configure absolute, non-root backup and monitoring directories.');
        }
    }
    $filestore = rtrim(($defaultRootFileUploadPath ?? '') ?: "$root/HEURIST_FILESTORE", '/');
    if (!str_starts_with($filestore, '/')) {
        throw new RuntimeException('Configure an absolute, non-root filestore directory.');
    }
    return [
        'server' => ($serverName ?? "") ?: (gethostname() ?: 'Heurist'),
        'root' => $root,
        'backup' => $backup,
        'monitor' => $monitor,
        'filestore' => $filestore,
        'host' => ($dbHost ?? "") ?: 'localhost',
        'port' => (int) (($dbPort ?? 0) ?: 3306),
        'user' => $dbAdminUsername ?? '',
        'password' => $dbAdminPassword ?? '',
        'socket' => $monitoringMysqlSocket ?? '',
        'dumpLogin' => $monitoringMysqlLoginPath ?? '',
        'mailTo' => implode(', ', $recipients),
        'mailFrom' => $sender,
        'website' => $monitoringWebsiteBaseUrl ?? ('https://' . (($serverName ?? "") ?: gethostname()) . '/heurist/'),
        'publishWebsites' => $monitoringPublishWebsiteList ?? false,
        'servers' => $monitoringServerUrls ?? [],
        'bookstack' => $monitoringBookStackDir ?? "$filestore/_DBS_FROM_REMOTES/BookStack",
        'bookstackBook' => $monitoringBookStackBook ?? '7_Understanding_Heurist_2026_Vsn_7',
        'bookstackProducer' => ($monitoringBookStack ?? []) + [
            'backupDir' => '/srv/BACKUP/BookStack', 'appDir' => '/var/www/bookstack',
            'database' => 'bookstack', 'mysqlLoginPath' => '', 'url' => 'https://docs.heuristref.net',
            'token' => '', 'remote' => '', 'sshKey' => '/home/tunnel/.ssh/dbtunnel_ed25519',
            'gitRepo' => '', 'gitBranch' => 'h7dev', 'gitBook' => '7_Understanding_Heurist_2026_Vsn_7',
        ],
        'membership' => $monitoringMembershipScript ?? "$code/admin/utilities/refreshMembershipList.php",
        'membershipBatch' => $monitoringMembershipBatchScript ?? "$code/admin/utilities/checkMembershipBatch.php",
        'membershipDb' => $monitoringMembershipBatchDatabase ?? 'Heurist_Job_Tracker',
        'membershipOutput' => $monitoringMembershipOutput ?? "$root/association_members.txt",
        'package' => $monitoringPackageScript ?? "$code/export/dbbackup/buildArchivePackagesCMD.php",
        'packageDbs' => $monitoringPackageDatabases ?? [],
        'pause' => max(0, (int) ($monitoringPauseSeconds ?? 20)),
        'disk' => $monitoringDiskPath ?? (getenv('DISK_PATH') ?: '/srv'),
        'loadLimit' => (float) ($monitoringLoadThreshold ?? (getenv('CPU_THRESHOLD') ?: 1.0)),
        'diskLimit' => (int) ($monitoringDiskThreshold ?? (getenv('DISK_THRESHOLD') ?: 92)),
        'reminder' => max(0, (int) ($monitoringReminderSeconds ?? (getenv('REMINDER_SECONDS') ?: 3600))),
    ];
}

final class Context
{
    private ?PDO $pdo = null;
    private ?array $schemas = null;
    public array $newDumps = [];
    public array $alertStamps = [];
    public bool $backupSelected = false;
    public string $results;

    public function __construct(public array $config)
    {
        $this->results = $config['monitor'] . '/RESULTS';
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $c = $this->config;
            if ($c['user'] === '') {
                throw new RuntimeException('Set $dbAdminUsername in Heurist configuration.');
            }
            $connection = $c['socket'] !== '' ? 'unix_socket=' . $c['socket']
                : 'host=' . $c['host'] . ';port=' . $c['port'];
            $this->pdo = new PDO("mysql:$connection;charset=utf8mb4", $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ]);
        }
        return $this->pdo;
    }

    public function schemas(): array
    {
        if ($this->schemas === null) {
            $schemas = $this->pdo()->query(
                "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA
                 WHERE SCHEMA_NAME LIKE 'hdb!_%' ESCAPE '!' ORDER BY CAST(SCHEMA_NAME AS BINARY)"
            )->fetchAll(PDO::FETCH_COLUMN);
            if (!$schemas) {
                throw new RuntimeException('No hdb_ databases visible to the configured MySQL account.');
            }
            $this->schemas = $schemas;
        }
        return $this->schemas;
    }

    public function rows(string $schema, string $sql): Generator
    {
        $quoted = str_replace('`', '``', $schema);
        $this->pdo()->exec("USE `$quoted`");
        $stmt = $this->pdo()->query($sql);
        try {
            while (($row = $stmt->fetch()) !== false) {
                yield $row;
            }
        } finally {
            $stmt->closeCursor();
        }
    }
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create directory $dir");
    }
}

function tempIn(string $dir): string
{
    $path = tempnam($dir, '.heurist-');
    if ($path === false || dirname($path) !== realpath($dir)) {
        if ($path !== false) {
            unlink($path);
        }
        throw new RuntimeException("Cannot create a temporary file in $dir");
    }
    return $path;
}

function atomicWrite(string $path, callable $write): void
{
    $tmp = tempIn(dirname($path));
    try {
        $write($tmp);
        if (!rename($tmp, $path)) {
            throw new RuntimeException("Cannot replace $path");
        }
    } finally {
        if (is_file($tmp)) {
            unlink($tmp);
        }
    }
}

function writeText(string $path, string $data): void
{
    if (file_put_contents($path, $data) !== strlen($data)) {
        throw new RuntimeException("Cannot write $path");
    }
}

function putLine($file, string $line): void
{
    $line .= "\n";
    if (fwrite($file, $line) !== strlen($line)) {
        throw new RuntimeException('Output write failed.');
    }
}

function rowLine(array $row): string
{
    return implode("\t", array_map(static fn($v) => strtr((string) $v, [
        "\\" => "\\\\", "\t" => '\\t', "\r" => '\\r', "\n" => '\\n', "\0" => '\\0',
    ]), $row));
}

// Disk-backed command output avoids pipe deadlocks and unbounded PHP buffers.
function command(array $argv, string $dir, ?string $output = null, array $ok = [0], ?string $cwd = null): string
{
    $stderr = tempIn($dir);
    $stdout = $output ?? tempIn($dir);
    try {
        $process = proc_open($argv, [
            0 => ['file', '/dev/null', 'r'], 1 => ['file', $stdout, 'w'],
            2 => ['file', $stderr, 'w'],
        ], $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start ' . basename($argv[0]));
        }
        $exit = proc_close($process);
        if (!in_array($exit, $ok, true)) {
            $error = trim((string) file_get_contents($stderr, false, null, 0, 3000));
            $extra = trim((string) file_get_contents($stdout, false, null, 0, 500));
            throw new RuntimeException(basename($argv[0]) . " exited $exit: " . ($error ?: $extra));
        }
        return $output === null ? (string) file_get_contents($stdout, false, null, 0, 8192) : '';
    } finally {
        unlink($stderr);
        if ($output === null) {
            unlink($stdout);
        }
    }
}

function lowPriority(array $argv): array
{
    return ['nice', '-n', '15', 'ionice', '-c', '2', '-n', '7', ...$argv];
}

function mysqlOption(string $text): string
{
    return '"' . strtr($text, [
        "\\" => "\\\\", '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t',
    ]) . '"';
}

function dumpOne(array $argv, string $target, Result $result, string $schema, string $compressor = 'bzip2'): void
{
    $errors = tempIn(dirname($target));
    $compressErrors = tempIn(dirname($target));
    try {
        $dump = proc_open(lowPriority($argv), [
            0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errors, 'w'],
        ], $pipes);
        if (!is_resource($dump)) {
            throw new RuntimeException('Cannot start mysqldump.');
        }
        try {
            $zip = proc_open(lowPriority([$compressor, '-c']), [
                0 => $pipes[1], 1 => ['file', $target, 'w'], 2 => ['file', $compressErrors, 'w'],
            ], $zipPipes);
        } finally {
            fclose($pipes[1]);
            if (!isset($zip) || !is_resource($zip)) {
                proc_close($dump);
            }
        }
        if (!is_resource($zip)) {
            throw new RuntimeException("Cannot start $compressor.");
        }

        $zipExit = proc_close($zip);
        $dumpExit = proc_close($dump);
        $diagnostics = trim((string) file_get_contents($errors, false, null, 0, 3000));
        if ($dumpExit !== 0 || $zipExit !== 0 || filesize($target) <= 14) {
            $zipError = trim((string) file_get_contents($compressErrors, false, null, 0, 1000));
            throw new RuntimeException("mysqldump exit $dumpExit, $compressor exit $zipExit. $diagnostics $zipError");
        }
        if ($diagnostics !== '') {
            $result->issue("$schema: $diagnostics", 'Warning');
        }
    } finally {
        unlink($errors);
        unlink($compressErrors);
    }
}

function backupDatabases(Context $ctx, Result $result): void
{
    $c = $ctx->config;
    $schemas = $ctx->schemas();
    ensureDir($c['backup']);
    $options = tempIn($c['monitor']);
    try {
        if ($c['dumpLogin'] !== '') {
            $mysql = ['mysqldump', '--login-path=' . $c['dumpLogin']];
        } else {
            $settings = ['host' => $c['host'], 'port' => (string) $c['port'],
                'user' => $c['user'], 'password' => $c['password']];
            if ($c['socket'] !== '') {
                $settings['socket'] = $c['socket'];
            }
            $contents = "[client]\n";
            foreach ($settings as $key => $value) {
                $contents .= $key . '=' . mysqlOption($value) . "\n";
            }
            writeText($options, $contents);
            $mysql = ['mysqldump', '--defaults-file=' . $options];
        }
        foreach ($schemas as $index => $schema) {
            try {
                $file = $c['backup'] . '/' . $schema . '.sql.bz2';
                atomicWrite($file, static function ($tmp) use ($mysql, $schema, $result): void {
                    dumpOne([...$mysql, '--single-transaction', '--quick', '--no-tablespaces',
                        '--set-gtid-purged=OFF', '--column-statistics=0', '--add-drop-table',
                        '--skip-dump-date', '--triggers', '--routines', '--events', '--', $schema],
                        $tmp, $result, $schema);
                });
                $ctx->newDumps[] = $file;
            } catch (Throwable $e) {
                $result->issue("$schema: " . $e->getMessage());
            }
            if (($index + 1) % 25 === 0 && $index + 1 < count($schemas)) {
                sleep(min(5, $c['pause']));
            }
        }
    } finally {
        unlink($options);
    }
    $result->summary = count($ctx->newDumps) . '/' . count($schemas) . ' database dumps updated';
}

function archiveDatabases(Context $ctx, Result $result): void
{
    $base = $ctx->config['backup'];
    $dir = "$base/ARCHIVE";
    ensureDir($dir);
    $files = $ctx->backupSelected ? $ctx->newDumps : (glob("$base/hdb_*.sql.bz2") ?: []);
    $changed = $created = $same = 0;
    foreach ($files as $file) {
        if (!is_file($file) || is_link($file)) {
            continue;
        }
        $name = basename($file);
        $current = "$dir/$name";
        try {
            if (filesize($file) <= 14 || is_link($current)) {
                throw new RuntimeException('Empty source dump or symbolic link at archive destination.');
            }
            if (is_file($current)) {
                if (filesize($current) === filesize($file)) {
                    $previousHash = hash_file('sha256', $current);
                    $hash = hash_file('sha256', $file);
                    if ($previousHash === false || $hash === false) {
                        throw new RuntimeException('Cannot compare dump fingerprints.');
                    }
                    if ($previousHash === $hash) {
                        ++$same;
                        continue;
                    }
                }
                $date = date('Y-m-d');
                $history = "$current.$date";
                for ($suffix = 1; file_exists($history) || is_link($history); ++$suffix) {
                    $history = sprintf('%s.%s.%04d', $current, $date, $suffix);
                }
                if (!link($current, $history)) {
                    throw new RuntimeException('Cannot preserve previous archive.');
                }
            }
            $existed = is_file($current);
            atomicWrite($current, static function ($tmp) use ($file): void {
                if (!copy($file, $tmp)) {
                    throw new RuntimeException('Cannot copy compressed dump.');
                }
            });
            if ($existed) {
                ++$changed;
            } else {
                ++$created;
            }
        } catch (Throwable $e) {
            $result->issue("$name: " . $e->getMessage());
        }
    }
    if (!$files) {
        $result->issue('No source dumps available to archive.', 'Warning');
    }
    $result->summary = "$created new, $changed changed, $same unchanged";
}

function pruneDatedDumps(Context $ctx, Result $result, bool $latestFour): void
{
    $dir = $ctx->config['backup'] . '/ARCHIVE';
    if (!is_dir($dir)) {
        throw new RuntimeException("Archive directory is missing: $dir");
    }
    $files = glob("$dir/hdb_*.sql.bz2.*");
    if ($files === false) {
        throw new RuntimeException("Cannot list $dir");
    }
    rsort($files, SORT_STRING);
    $kept = [];
    $cutoff = (new DateTimeImmutable('today'))->modify('-10 days')->format('Y-m-d');
    $deleted = 0;
    foreach ($files as $file) {
        if (is_link($file) || !is_file($file) || !preg_match(
            '/^(hdb_.+\.sql\.bz2)\.(\d{4}-\d{2}-\d{2})(?:\.\d{4,})?$/D', basename($file), $match
        )) {
            continue;
        }
        [, $name, $date] = $match;
        $key = $latestFour ? $name : $name . '.' . substr($date, 0, 7);
        $kept[$key] = ($kept[$key] ?? 0) + 1;
        if ($latestFour ? $kept[$key] <= 4 : ($date >= $cutoff || $kept[$key] === 1)) {
            continue;
        }
        if (unlink($file)) {
            ++$deleted;
        } else {
            $result->issue("Cannot remove $file");
        }
    }
    $result->summary = "$deleted dated database archives removed";
}

function pruneArchiveMonthly(Context $ctx, Result $result): void
{
    pruneDatedDumps($ctx, $result, false);
}

function pruneArchiveLatest(Context $ctx, Result $result): void
{
    pruneDatedDumps($ctx, $result, true);
}

function checkRequiredTables(Context $ctx, Result $result): void
{
    $schemaCount = count($ctx->schemas());
    atomicWrite($ctx->results . '/required_tables_check.txt', static function ($tmp) use ($ctx, $result, $schemaCount): void {
        $pdo = $ctx->pdo();
        $pdo->exec('SET SESSION group_concat_max_len = 8192');
        $stmt = $pdo->prepare(<<<'SQL'
WITH expected AS (
    SELECT table_name FROM JSON_TABLE(
        ?, '$[*]' COLUMNS (table_name VARCHAR(64) PATH '$')
    ) AS names
)
SELECT CONCAT(s.SCHEMA_NAME, ' (',
       GROUP_CONCAT(e.table_name ORDER BY e.table_name SEPARATOR ', '), ')')
FROM information_schema.SCHEMATA AS s
CROSS JOIN expected AS e
LEFT JOIN information_schema.TABLES AS t
    ON t.TABLE_SCHEMA = s.SCHEMA_NAME AND t.TABLE_NAME = e.table_name
    AND t.TABLE_TYPE = 'BASE TABLE'
    AND (@@lower_case_table_names <> 0 OR (
        CAST(t.TABLE_NAME AS BINARY) = CAST(e.table_name AS BINARY)
        AND CAST(t.TABLE_SCHEMA AS BINARY) = CAST(s.SCHEMA_NAME AS BINARY)
    ))
WHERE s.SCHEMA_NAME LIKE 'hdb!_%' ESCAPE '!' AND t.TABLE_NAME IS NULL
GROUP BY s.SCHEMA_NAME, CAST(s.SCHEMA_NAME AS BINARY)
ORDER BY CAST(s.SCHEMA_NAME AS BINARY)
SQL);
        $stmt->execute([json_encode(REQUIRED_TABLES, JSON_THROW_ON_ERROR)]);
        $out = fopen($tmp, 'wb');
        try {
            while (($line = $stmt->fetchColumn()) !== false) {
                putLine($out, $line);
                $result->issue($line, 'Warning');
            }
        } finally {
            fclose($out);
            $stmt->closeCursor();
        }
        $result->summary = "$result->issueCount/$schemaCount databases missing required tables";
    });
}

/**
 * Stream per-database rows to disk, with optional external merge sort.
 * The previous report survives a total query failure.
 */
function inventory(Context $ctx, Result $result, string $name, array $header,
                   string $sql, callable $map, bool $unique = false, bool $sort = true): void
{
    $schemas = $ctx->schemas();
    $raw = tempIn($ctx->results);
    $ordered = null;
    $count = $checked = 0;
    try {
        $out = fopen($raw, 'wb');
        try {
            foreach ($schemas as $schema) {
                try {
                    foreach ($ctx->rows($schema, $sql) as $row) {
                        putLine($out, rowLine($map($row, $schema)));
                        ++$count;
                    }
                    ++$checked;
                } catch (Throwable $e) {
                    $result->issue("$schema: " . $e->getMessage());
                }
            }
        } finally {
            fclose($out);
        }
        if (!$checked) {
            throw new RuntimeException('No databases were queried successfully.');
        }
        if ($sort) {
            $ordered = tempIn($ctx->results);
            command(lowPriority(['sort', '-S', '32M', '--parallel=1', '-T', $ctx->results,
                ...($unique ? ['-u'] : []), '--', $raw]), $ctx->results, $ordered);
        }
        atomicWrite("$ctx->results/$name", static function ($tmp) use ($header, $ordered, $raw, &$count): void {
            $out = fopen($tmp, 'wb');
            $in = fopen($ordered ?? $raw, 'rb');
            $count = 0;
            try {
                putLine($out, rowLine($header));
                while (($line = fgets($in)) !== false) {
                    putLine($out, rtrim($line, "\n"));
                    ++$count;
                }
                if (!feof($in)) {
                    throw new RuntimeException('Could not read complete inventory.');
                }
            } finally {
                fclose($in);
                fclose($out);
            }
        });
    } finally {
        unlink($raw);
        if ($ordered !== null) {
            unlink($ordered);
        }
    }
    $result->summary = "$count rows from $checked/" . count($schemas) . " databases ($name)";
}

function listDatabases(Context $ctx, Result $r): void
{
    inventory($ctx, $r, 'list_of_dbs.tsv',
        ['Database', 'Owner email', 'Family name', 'Given name', 'Record count',
            'Last modified', 'Year', 'Month', 'Registration ID', 'Version', 'Subversion'],
        'SELECT (SELECT ugr_eMail FROM sysUGrps WHERE ugr_ID=2),
            (SELECT ugr_LastName FROM sysUGrps WHERE ugr_ID=2),
            (SELECT ugr_FirstName FROM sysUGrps WHERE ugr_ID=2),
            n, modified, YEAR(modified), MONTH(modified),
            (SELECT sys_dbRegisteredID FROM sysIdentification LIMIT 1),
            (SELECT sys_dbVersion FROM sysIdentification LIMIT 1),
            (SELECT sys_dbSubVersion FROM sysIdentification LIMIT 1)
         FROM (SELECT COUNT(*) n, MAX(rec_Modified) modified FROM Records) AS stats',
        static fn($row, $db) => [$db, ...$row], false, false);
}

function listRecordTypes(Context $ctx, Result $r): void
{
    inventory($ctx, $r, 'list_of_rectypes_on_all_dbs.tsv', ['Record type'],
        'SELECT rty_Name FROM defRecTypes', static fn($row) => $row, true);
}

function listConceptIds(Context $ctx, Result $r): void
{
    inventory($ctx, $r, 'list_of_rectypes_on_all_dbs_with_concept_IDs.tsv',
        ['Record type', 'Origin database ID', 'Origin record type ID', 'Database'],
        'SELECT rty_Name, rty_OriginatingDBID, rty_IDInOriginatingDB FROM defRecTypes',
        static fn($row, $db) => [...$row, $db], true);
}

function listFields(Context $ctx, Result $r): void
{
    inventory($ctx, $r, 'list_of_fields_on_all_dbs.tsv', ['Display name', 'Help text'],
        'SELECT rst_DisplayName, rst_DisplayHelpText FROM defRecStructure',
        static fn($row) => $row, true);
}

const USER_FILTER = " FROM sysUGrps WHERE ugr_Type='user'
    AND TRIM(ugr_eMail) NOT IN ('', 'guest@null', 'info@heuristscholar.org', 'info@heuristnetwork.org')";

function listUsersWithDbs(Context $ctx, Result $r): void
{
    inventory($ctx, $r, 'list_of_users_on_all_dbs.tsv',
        ['Email', 'FirstName', 'LastName', 'Database', 'LastLoginTime', 'LoginCount', 'Organisation', 'Interests'],
        'SELECT ugr_eMail, ugr_FirstName, ugr_LastName, ugr_LastLoginTime, ugr_LoginCount, ugr_organisation, ugr_interests'
        . USER_FILTER,
        static fn($row, $db) => [...array_slice($row, 0, 3), $db, ...array_slice($row, 3)]);
}

function openZip(string $path): ZipArchive
{
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create ZIP package $path");
    }
    return $zip;
}

function listUniqueUsers(Context $ctx, Result $r): void
{
    $path = "$ctx->results/cumulative_list_of_users_unique.tsv";
    $raw = tempIn($ctx->results);
    $count = $checked = 0;
    $schemas = $ctx->schemas();
    try {
        $out = fopen($raw, 'wb');
        try {
            foreach ($schemas as $schema) {
                try {
                    foreach ($ctx->rows($schema, 'SELECT LOWER(TRIM(ugr_eMail)), ugr_FirstName, ugr_LastName' . USER_FILTER) as $row) {
                        putLine($out, rowLine($row));
                    }
                    ++$checked;
                } catch (Throwable $e) {
                    $r->issue("$schema: " . $e->getMessage());
                }
            }
            if (!$checked) {
                throw new RuntimeException('No databases returned user lists.');
            }
            // Append previous entries after current rows. Stable sort preserves
            // the first row for each email, while retaining users no longer present.
            if (is_file($path)) {
                $old = fopen($path, 'rb');
                try {
                    while (($line = fgets($old)) !== false) {
                        $fields = explode("\t", rtrim($line, "\r\n"), 2);
                        $email = strtolower(trim($fields[0]));
                        if (filter_var($email, FILTER_VALIDATE_EMAIL) && isset($fields[1])) {
                            putLine($out, $email . "\t" . $fields[1]);
                        }
                    }
                    if (!feof($old)) {
                        throw new RuntimeException('Cannot read complete cumulative list.');
                    }
                } finally {
                    fclose($old);
                }
            }
        } finally {
            fclose($out);
        }
        atomicWrite($path, static function ($tmp) use ($raw, $ctx, &$count): void {
            command(lowPriority(['sort', '--stable', '-u', '-t', "\t", '-k1,1',
                '-S', '32M', '--parallel=1', '-T', $ctx->results, '--', $raw]), $ctx->results, $tmp);
            $sorted = fopen($tmp, 'rb');
            try {
                while (fgets($sorted) !== false) {
                    ++$count;
                }
            } finally {
                fclose($sorted);
            }
        });
    } finally {
        unlink($raw);
    }
    $zipPath = $ctx->results . '/' . date('Y-m') . '_archive_cumulative_list_of_users_unique.zip';
    atomicWrite($zipPath, static function ($tmp) use ($path): void {
        $zip = openZip($tmp);
        if (!$zip->addFile($path, basename($path)) || !$zip->close()) {
            throw new RuntimeException('Cannot save cumulative users ZIP.');
        }
    });
    $r->summary = "$count cumulative unique users; $checked/" . count($schemas) . ' databases read';
}

function escapeHtml(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function listWebsites(Context $ctx, Result $r): void
{
    $tsv = 'list_of_websites_on_all_dbs.tsv';
    $base = $ctx->config['website'];
    inventory($ctx, $r, $tsv, ['Database', 'Record ID', 'URL', 'Title'],
        "SELECT rec_ID, rec_Title FROM Records WHERE NOT rec_FlagTemporary
         AND rec_Title NOT LIKE '%Enter a website name here%'
         AND rec_Title NOT LIKE '%UNCONFIGURED SITE TITLE%'
         AND rec_RecTypeID IN (SELECT rty_ID FROM defRecTypes
             WHERE rty_OriginatingDBID=99 AND rty_IDInOriginatingDB=51)",
        static fn($row, $db) => [$db, $row[0], $base . '?' . http_build_query([
            'db' => $db, 'website' => '', 'id' => $row[0],
        ]), $row[1]]);
    $html = "$ctx->results/list_of_websites_on_all_dbs.html";
    atomicWrite($html, static function ($tmp) use ($ctx, $tsv): void {
        $in = fopen("$ctx->results/$tsv", 'rb');
        $out = fopen($tmp, 'wb');
        try {
            putLine($out, '<!doctype html><meta charset="utf-8"><title>Heurist websites</title>');
            putLine($out, '<h1>Heurist websites on ' . escapeHtml($ctx->config['server']) . '</h1><ul>');
            fgets($in);
            while (($line = fgets($in)) !== false) {
                [$db, , $url, $title] = explode("\t", rtrim($line, "\n"), 4);
                putLine($out, '<li><a href="' . escapeHtml($url) . '">' . escapeHtml($db)
                    . '</a> ' . escapeHtml($title) . '</li>');
            }
            putLine($out, '</ul>');
        } finally {
            fclose($in);
            fclose($out);
        }
    });
    if ($ctx->config['publishWebsites']) {
        atomicWrite($ctx->config['root'] . '/' . basename($html), static function ($tmp) use ($html): void {
            if (!copy($html, $tmp) || !chmod($tmp, 0644)) {
                throw new RuntimeException('Cannot publish website list.');
            }
        });
    }
}

function listFilestoreSizes(Context $ctx, Result $r): void
{
    $dir = $ctx->config['filestore'];
    $sizes = [];
    foreach (new FilesystemIterator($dir) as $entry) {
        if ($entry->isLink() || str_starts_with($entry->getFilename(), '.')) {
            continue;
        }
        try {
            $output = command(lowPriority(['du', '-sk', '--', $entry->getPathname()]), $ctx->results);
            if (!preg_match('/^(\d+)\t/', $output, $m)) {
                throw new RuntimeException('Cannot read du output.');
            }
            $sizes[$entry->getPathname()] = (int) $m[1];
        } catch (Throwable $e) {
            $r->issue($entry->getFilename() . ': ' . $e->getMessage());
        }
    }
    arsort($sizes, SORT_NUMERIC);
    atomicWrite("$ctx->results/filestore_sizes.txt", static function ($tmp) use ($sizes): void {
        $out = fopen($tmp, 'wb');
        try {
            foreach ($sizes as $path => $kib) {
                putLine($out, rowLine([$kib, $path]));
            }
        } finally {
            fclose($out);
        }
    });
    $r->summary = count($sizes) . ' entries measured in KiB';
}

function regularFiles(string $dir, bool $recursive = true): Generator
{
    $iterator = $recursive ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $dir, FilesystemIterator::SKIP_DOTS
    )) : new FilesystemIterator($dir);
    foreach ($iterator as $entry) {
        if (!$entry->isLink() && $entry->isFile()) {
            yield $entry;
        }
    }
}

function latestBookStackExport(string $path, string $day): string
{
    $latest = '';
    foreach (new FilesystemIterator($path) as $entry) {
        $name = $entry->getFilename();
        if (!$entry->isLink() && $entry->isDir() && str_starts_with($name, $day . '_')
            && preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/D', $name)
            && strcmp($name, $latest) > 0) {
            $latest = $name;
        }
    }
    if ($latest === '') {
        throw new RuntimeException("No dated export for $day under $path");
    }
    return "$path/$latest";
}

function countBookStackFiles(string $path, string $day, Result $r, int $minimum = 1,
                            bool $recursive = true, bool $checkDate = true): int
{
    $count = $stale = 0;
    $examples = [];
    foreach (regularFiles($path, $recursive) as $file) {
        ++$count;
        if ($checkDate && date('Y-m-d', $file->getMTime()) !== $day) {
            ++$stale;
            if (count($examples) < 5) {
                $examples[] = $file->getPathname();
            }
        }
    }
    if ($stale) {
        $r->issue("$stale files under $path are out of date. Examples: " . implode(', ', $examples));
    }
    if ($count < $minimum) {
        $r->issue("$path has $count files; expected at least $minimum.");
    }
    return $count;
}

/** BookStack producer steps. The receiver uses checkBookStack independently. */
function bookStackWorkflow(Context $ctx, Result $result): void
{
    $settings = $ctx->config['bookstackProducer'];
    $base = rtrim($settings['backupDir'], '/');
    if (!str_starts_with($base, '/')) {
        throw new RuntimeException('BookStack backupDir must be an absolute non-root directory.');
    }
    ensureDir($base);
    $ctx->config['bookstack'] = $base;
    $ctx->config['bookstackBook'] = $settings['gitBook'];
    $runTime = date('Y-m-d_H-i-s');
    $sql = "$base/SQL_and_Files_retain_1_month";
    $api = "$base/API_exports_retain_1_month";
    $snapshot = "$base/daily_MD_JSon_files_snapshot";
    $done = [];
    $steps = [
        'SQL and files' => fn() => bookStackSql($ctx, $settings, $sql, $runTime, $result),
        'API exports' => fn() => bookStackApi($settings, $api, $runTime),
        'stable snapshot' => fn() => bookStackSnapshot("$api/$runTime", $snapshot, $settings['gitBook']),
        'local verification' => function () use ($ctx): string {
            $check = new Result();
            checkBookStack($ctx, $check);
            if ($check->status !== 'Success') {
                throw new RuntimeException(implode("\n", $check->details));
            }
            return $check->summary;
        },
    ];
    foreach ($steps as $name => $step) {
        try {
            if ($done) {
                sleep($ctx->config['pause']);
            }
            $done[] = "$name: " . $step();
        } catch (Throwable $error) {
            $result->issue("$name: " . $error->getMessage());
            $result->summary = count($done) . '/4 local stages completed; publishing skipped';
            array_push($result->details, ...$done);
            return;
        }
    }

    // Retain today's validated backup before pruning older dated directories.
    $uploads = [
        '30-day retention' => fn() => bookStackRetention([$sql, $api]),
        'Huma-Num' => fn() => bookStackRemote($ctx, $settings, $base),
        'GitHub' => fn() => bookStackGit($ctx, $settings, $snapshot),
    ];
    foreach ($uploads as $name => $upload) {
        try {
            $done[] = "$name: " . $upload();
        } catch (Throwable $error) {
            $result->issue("$name: " . $error->getMessage());
        }
    }
    array_push($result->details, ...$done);
    $result->summary = "4/4 local stages completed; $result->issueCount issue(s)";
    foreach ($done as $line) {
        if (str_starts_with($line, 'Huma-Num:') || str_starts_with($line, 'GitHub:')) {
            $result->summary .= '; ' . $line;
        }
    }
}

/** Build a complete directory privately before publishing it on the same filesystem. */
function bookStackPublish(string $destination, callable $build, bool $replace = false): string
{
    $parent = dirname($destination);
    ensureDir($parent);
    $stage = "$parent/.bookstack-stage-" . bin2hex(random_bytes(12));
    ensureDir($stage);
    $old = null;
    try {
        $summary = $build($stage);
        if (file_exists($destination) || is_link($destination)) {
            if (!$replace || is_link($destination) || !is_dir($destination)) {
                throw new RuntimeException("Destination already exists: $destination");
            }
            $old = $stage . '-previous';
            if (!rename($destination, $old)) {
                $old = null;
                throw new RuntimeException('Cannot preserve previous snapshot.');
            }
        }
        if (!rename($stage, $destination)) {
            if ($old !== null && !rename($old, $destination)) {
                throw new RuntimeException("Cannot publish snapshot. Previous snapshot preserved at $old.");
            }
            throw new RuntimeException('Cannot publish completed BookStack output.');
        }
        if ($old !== null) {
            bookStackRemoveTree($old);
        }
        return $summary;
    } finally {
        if (is_dir($stage)) {
            bookStackRemoveTree($stage);
        }
    }
}

function bookStackRemoveTree(string $path): void
{
    $tree = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($tree as $item) {
        $file = $item->getPathname();
        $ok = !$item->isLink() && $item->isDir() ? rmdir($file) : unlink($file);
        if (!$ok) {
            throw new RuntimeException("Cannot remove $file");
        }
    }
    if (!rmdir($path)) {
        throw new RuntimeException("Cannot remove $path");
    }
}

function bookStackSql(Context $ctx, array $config, string $parent, string $stamp, Result $r): string
{
    if ($config['mysqlLoginPath'] === '') {
        throw new RuntimeException('Configure BookStack mysqlLoginPath in heuristConfigIni.php.');
    }
    return bookStackPublish("$parent/$stamp", static function ($stage) use ($ctx, $config, $r, $stamp): string {
        $database = $config['database'];
        dumpOne(['mysqldump', '--login-path=' . $config['mysqlLoginPath'], '--single-transaction',
            '--quick', '--skip-lock-tables', '--no-tablespaces', '--column-statistics=0',
            '--set-gtid-purged=OFF', '--', $database], "$stage/bookstack.sql.gz", $r, $database, 'gzip');
        command(lowPriority(['tar', '-czf', "$stage/bookstack-files.tar.gz", '-C', $config['appDir'],
            '--', '.env', 'public/uploads', 'storage/uploads', 'themes']), $ctx->results);
        writeText("$stage/manifest.txt", "BookStack backup\nDate: $stamp\nDatabase: $database\n"
            . 'SQL bytes: ' . filesize("$stage/bookstack.sql.gz") . "\n"
            . 'Application files bytes: ' . filesize("$stage/bookstack-files.tar.gz") . "\n");
        return '1 SQL dump and 1 application archive';
    });
}

/** Download via PHP cURL without exposing the API token in a process argument or following redirects. */
function bookStackDownload(array $config, string $route, $stream, string $accept): void
{
    $curl = curl_init(rtrim($config['url'], '/') . $route);
    try {
        curl_setopt_array($curl, [
            CURLOPT_FILE => $stream, CURLOPT_FAILONERROR => true,
            CURLOPT_HTTPHEADER => ['Authorization: Token ' . $config['token'], 'Accept: ' . $accept],
            CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 600,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($ok === false || $status !== 200 || ftell($stream) === 0) {
            throw new RuntimeException("BookStack API $route returned HTTP $status (cURL " . curl_errno($curl) . ').');
        }
    } finally {
        curl_close($curl);
    }
}

function bookStackItems(array $config, string $type): Generator
{
    $offset = 0;
    do {
        $json = fopen('php://temp', 'w+b');
        try {
            bookStackDownload($config, "/api/$type?count=100&offset=$offset", $json, 'application/json');
            rewind($json);
            $page = json_decode(stream_get_contents($json), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            fclose($json);
        }
        if (!isset($page['data'], $page['total']) || !is_array($page['data'])) {
            throw new RuntimeException("Unexpected BookStack $type listing.");
        }
        if (!$page['data'] && $offset < (int) $page['total']) {
            throw new RuntimeException("Incomplete BookStack $type listing at offset $offset.");
        }
        foreach ($page['data'] as $item) {
            yield $item;
        }
        $offset += count($page['data']);
    } while ($offset < (int) $page['total']);
}

function bookStackSafeName(string $name): string
{
    $name = preg_replace('/[\x00-\x20\x7F\x2F\x5C:*?"<>|]+/', '_', $name);
    $name = substr(preg_replace('/_+/', '_', $name), 0, 160);
    while (preg_match('//u', $name) !== 1) {
        $name = substr($name, 0, -1);
    }
    return $name === '' ? '_' : $name;
}

function bookStackApi(array $config, string $parent, string $stamp): string
{
    if (!str_starts_with($config['url'], 'https://') || $config['token'] === ''
        || preg_match('/[\r\n]/', $config['token'])) {
        throw new RuntimeException('Configure BookStack HTTPS url and API token ID:secret.');
    }
    return bookStackPublish("$parent/$stamp", static function ($stage) use ($config): string {
        $index = [];
        foreach (['books' => ['zip', 'markdown'], 'chapters' => ['markdown'], 'pages' => ['markdown']] as $type => $formats) {
            foreach ($formats as $format) {
                ensureDir("$stage/{$type}_$format");
            }
            $index[$type] = [];
            foreach (bookStackItems($config, $type) as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id < 1 || isset($index[$type][$id]) || !isset($item['name'])) {
                    throw new RuntimeException("Invalid or duplicate BookStack $type ID.");
                }
                $name = bookStackSafeName($item['name']);
                $index[$type][$id] = ['name' => $name, 'book' => (int) ($item['book_id'] ?? 0)];
                foreach ($formats as $format) {
                    $ext = $format === 'zip' ? 'zip' : 'md';
                    $file = fopen("$stage/{$type}_$format/{$id}_$name.$ext", 'wb');
                    try {
                        bookStackDownload($config, "/api/$type/$id/export/$format", $file, '*/*');
                    } finally {
                        fclose($file);
                    }
                }
            }
        }
        foreach ($index as $type => $items) {
            if (!$items) {
                throw new RuntimeException("BookStack returned no accessible $type.");
            }
        }
        writeText("$stage/export-index.json", json_encode($index, JSON_THROW_ON_ERROR));
        return sprintf('%d books, %d chapters, %d pages', count($index['books']),
            count($index['chapters']), count($index['pages']));
    });
}

/** Extract only normal files and directories within the target; never create links. */
function bookStackExtract(string $archive, string $dir): void
{
    $zip = new ZipArchive();
    if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException("Invalid BookStack ZIP: $archive");
    }
    try {
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if ($name === false || $name === '' || str_contains($name, '\\')
                || str_contains($name, ':') || preg_match('~(^/|(?:^|/)\.{1,2}(?:/|$))~', $name)) {
                throw new RuntimeException('Invalid ZIP entry path in BookStack export.');
            }
            $target = "$dir/$name";
            if (str_ends_with($name, '/')) {
                ensureDir($target);
                continue;
            }
            ensureDir(dirname($target));
            $in = $zip->getStream($name);
            if ($in === false) {
                throw new RuntimeException('Cannot read BookStack ZIP entry.');
            }
            try {
                $out = fopen($target, 'xb');
                if (!$out) {
                    throw new RuntimeException("Cannot extract BookStack ZIP entry $name");
                }
                try {
                    if (stream_copy_to_stream($in, $out) !== $zip->statIndex($i)['size']) {
                        throw new RuntimeException("Incomplete BookStack ZIP entry $name");
                    }
                } finally {
                    fclose($out);
                }
            } finally {
                fclose($in);
            }
        }
    } finally {
        $zip->close();
    }
}

function bookStackImageNames(string $dir): void
{
    $jsonPath = "$dir/data.json";
    $data = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
    $names = [];
    $rename = static function (string $old, string $prefix) use ($dir, &$names): string {
        if ($old === '' || $old !== basename($old) || !is_file("$dir/files/$old")) {
            return $old;
        }
        $extension = pathinfo($old, PATHINFO_EXTENSION);
        $new = $prefix . ($extension === '' ? '' : ".$extension");
        if ($old !== $new) {
            if (file_exists("$dir/files/$new") || !rename("$dir/files/$old", "$dir/files/$new")) {
                throw new RuntimeException("Cannot normalise BookStack image $old.");
            }
            $names[$old] = $new;
        }
        return $new;
    };
    $collect = static function (array $node) use (&$collect, $rename, &$names): void {
        if (isset($node['type'], $node['id'], $node['file'])
            && in_array($node['type'], ['gallery', 'drawio'], true)
            && preg_match('/^[0-9]+$/D', (string) $node['id']) === 1 && !isset($names[$node['file']])) {
            $rename($node['file'], 'image-' . $node['id']);
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                $collect($child);
            }
        }
    };
    $collect($data);
    $cover = $data['book']['cover'] ?? '';
    if (is_string($cover) && $cover !== '' && $cover === basename($cover) && is_file("$dir/files/$cover")) {
        $data['book']['cover'] = $rename($cover, 'cover-' . substr(hash_file('sha256', "$dir/files/$cover"), 0, 16));
    }
    $replace = static function (array &$node) use (&$replace, $names): void {
        if (is_string($node['file'] ?? null) && isset($names[$node['file']])) {
            $node['file'] = $names[$node['file']];
        }
        foreach ($node as &$child) {
            if (is_array($child)) {
                $replace($child);
            }
        }
    };
    $replace($data);
    unset($data['exported_at']);
    writeText($jsonPath, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

function bookStackSnapshot(string $api, string $snapshot, string $requiredBook): string
{
    $index = json_decode(file_get_contents("$api/export-index.json"), true, 512, JSON_THROW_ON_ERROR);
    return bookStackPublish($snapshot, static function ($stage) use ($api, $index, $requiredBook): string {
        foreach ($index['books'] as $bookId => $book) {
            $stem = $bookId . '_' . $book['name'];
            $dir = "$stage/$stem";
            ensureDir($dir);
            bookStackExtract("$api/books_zip/$stem.zip", $dir);
            bookStackImageNames($dir);
            foreach (['books' => 'book', 'chapters' => 'chapter', 'pages' => 'page'] as $type => $suffix) {
                foreach ($index[$type] as $id => $item) {
                    if (($type === 'books' ? $id : $item['book']) !== $bookId) {
                        continue;
                    }
                    $name = $id . '_' . $item['name'];
                    if (!copy("$api/{$type}_markdown/$name.md", "$dir/$name.$suffix.md")) {
                        throw new RuntimeException("Cannot copy BookStack Markdown for $name.");
                    }
                }
            }
        }
        if ($requiredBook !== basename($requiredBook) || $requiredBook === '.' || $requiredBook === '..') {
            throw new RuntimeException('Invalid BookStack snapshot directory name.');
        }
        $check = new Result();
        $day = date('Y-m-d');
        countBookStackFiles("$stage/$requiredBook", $day, $check, 28, false, false);
        countBookStackFiles("$stage/$requiredBook/files", $day, $check, 200, true, false);
        if ($check->status !== 'Success') {
            throw new RuntimeException(implode("\n", $check->details));
        }
        return count($index['books']) . ' books';
    }, true);
}

function bookStackRetention(array $parents): string
{
    $cutoff = (new DateTimeImmutable())->modify('-30 days')->format('Y-m-d_H-i-s');
    $removed = 0;
    foreach ($parents as $parent) {
        foreach (new FilesystemIterator($parent) as $item) {
            $name = $item->getFilename();
            if (!$item->isLink() && $item->isDir() && $name < $cutoff
                && preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/D', $name)) {
                bookStackRemoveTree($item->getPathname());
                ++$removed;
            }
        }
    }
    return "$removed dated directories removed";
}

function bookStackRemote(Context $ctx, array $config, string $base): string
{
    $remote = $config['remote'];
    if ($remote === '') {
        return 'disabled';
    }
    if (!preg_match('~^[a-zA-Z0-9_-]+@[a-zA-Z0-9.-]+:/[a-zA-Z0-9_./-]+/BookStack/?$~D', $remote)
        || str_contains($remote, '..') || !is_readable($config['sshKey'])) {
        throw new RuntimeException('Configure the dedicated BookStack remote path and a readable SSH identity.');
    }
    // rsync parses -e as a command specification. Restrict the identity value to one argument.
    if (!preg_match('~^/[a-zA-Z0-9_./-]+$~D', $config['sshKey'])) {
        throw new RuntimeException('BookStack SSH key path must be absolute and contain no spaces.');
    }
    $ssh = 'ssh -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=20 -o ServerAliveInterval=30 -o ServerAliveCountMax=3 -o IdentitiesOnly=yes -i ' . $config['sshKey'];
    command(lowPriority(['rsync', '-rtz', '--no-perms', '--omit-dir-times', '--delete-delay',
        '--delay-updates', '--exclude=.bookstack-*', '--include=/SQL_and_Files_retain_1_month/***',
        '--include=/API_exports_retain_1_month/***', '--include=/daily_MD_JSon_files_snapshot/***',
        '--exclude=/*', '--mkpath', '-e', $ssh, '--',
        "$base/", rtrim($remote, '/') . '/']), $ctx->results);
    return 'mirror transferred';
}

function bookStackGit(Context $ctx, array $config, string $snapshot): string
{
    $repo = rtrim($config['gitRepo'], '/');
    if ($repo === '') {
        return 'disabled';
    }
    if (!is_dir("$repo/.git") || realpath($repo) === realpath(dirname(__DIR__))) {
        throw new RuntimeException('Configure a dedicated BookStack Git staging clone.');
    }
    $git = static fn(array $args) => command(
        ['env', 'GIT_TERMINAL_PROMPT=0', 'git', '-C', $repo, ...$args], $ctx->results
    );
    $branch = $config['gitBranch'];
    $folder = 'documentation/Understanding_Heurist_Vsn_7';
    if (trim($git(['branch', '--show-current'])) !== $branch || trim($git(['status', '--porcelain'])) !== '') {
        throw new RuntimeException("The BookStack staging clone must be clean and on $branch.");
    }
    $git(['pull', '--ff-only', 'origin', $branch]);
    $source = "$snapshot/" . $config['gitBook'];
    if (!is_file("$source/data.json") || !is_dir("$repo/$folder") || is_link("$repo/$folder")) {
        throw new RuntimeException('Cannot find the selected BookStack snapshot or Git documentation directory.');
    }
    command(lowPriority(['rsync', '-r', '--delete', '--exclude=/scripts/', '--chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r',
        '--', "$source/", "$repo/$folder/"]), $ctx->results);
    $git(['add', '--', $folder]);
    $changed = trim($git(['diff', '--cached', '--name-only', '--', $folder])) !== '';
    if ($changed) {
        $git(['commit', '-m', 'Understanding Heurist (BookStack)', '--', $folder]);
    }
    $git(['push', 'origin', $branch]);
    return $changed ? 'documentation committed and pushed' : 'no new documentation changes';
}

function checkBookStack(Context $ctx, Result $r): void
{
    $path = $ctx->config['bookstack'];
    $day = date('Y-m-d');
    $counts = [];
    $snapshot = "$path/daily_MD_JSon_files_snapshot";
    $book = "$snapshot/" . ($ctx->config['bookstackBook'] ?? '7_Understanding_Heurist_2026_Vsn_7');
    $checks = [
        'snapshot' => fn() => countBookStackFiles($snapshot, $day, $r),
        'book documents' => fn() => countBookStackFiles($book, $day, $r, 28, false, false),
        'book files' => fn() => countBookStackFiles("$book/files", $day, $r, 200, true, false),
        'API export' => static function () use ($path, $day, $r): int {
            $api = latestBookStackExport("$path/API_exports_retain_1_month", $day);
            $count = countBookStackFiles($api, $day, $r);
            foreach (['pages_markdown', 'chapters_markdown', 'books_zip', 'books_markdown'] as $folder) {
                try {
                    countBookStackFiles("$api/$folder", $day, $r, 1, true, false);
                } catch (Throwable $e) {
                    $r->issue($e->getMessage());
                }
            }
            return $count;
        },
        'SQL and files' => static function () use ($path, $day, $r): int {
            $sql = latestBookStackExport("$path/SQL_and_Files_retain_1_month", $day);
            $count = 0;
            foreach (['manifest.txt', 'bookstack-files.tar.gz', 'bookstack.sql.gz'] as $file) {
                $item = "$sql/$file";
                if (!is_file($item) || is_link($item) || !filesize($item) || date('Y-m-d', filemtime($item)) !== $day) {
                    $r->issue("$item is missing, empty or out of date.");
                } else {
                    ++$count;
                }
            }
            return $count;
        },
    ];
    foreach ($checks as $label => $check) {
        try {
            $counts[] = $label . ' ' . $check();
        } catch (Throwable $e) {
            $r->issue("$label: " . $e->getMessage());
        }
    }
    $r->summary = implode(', ', $counts) . " ($r->issueCount issues)";
}

function phpJob(Context $ctx, string $script, array $args, string $log): void
{
    if (!str_starts_with($script, '/') || !is_file($script) || !is_readable($script)) {
        throw new RuntimeException('Configure an absolute path to the existing PHP entry point.');
    }
    // PHP processes stay separate because the existing entry points can call exit().
    command(lowPriority([PHP_BINARY, $script, ...$args]), $ctx->results, $log, [0], dirname($script));
}

function refreshMembership(Context $ctx, Result $r): void
{
    $started = time();
    phpJob($ctx, $ctx->config['membership'], [], "$ctx->results/membership_refresh.log");
    $path = $ctx->config['membershipOutput'];
    clearstatcache(true, $path);
    if (!is_file($path) || filesize($path) === 0 || filemtime($path) < $started) {
        throw new RuntimeException("Membership refresh did not publish a current, nonempty $path.");
    }
    $r->summary = '1 membership list published (' . filesize($path) . ' bytes)';
}

function checkMembershipBatch(Context $ctx, Result $r): void
{
    $db = $ctx->config['membershipDb'];
    $log = "$ctx->results/membership_batch.log";
    phpJob($ctx, $ctx->config['membershipBatch'], ['--', '-db=' . $db], $log);
    $output = (string) file_get_contents($log, false, null, 0, 8192);
    // Some upstream error paths use exit with a string, which returns code 0.
    if (!preg_match('/^checkMembershipBatch:\s+(\d+) tickets for (\d+) members\s+(\d+) tickets for (\d+) non-members\s*$/m',
        $output, $counts)) {
        throw new RuntimeException("No completion counts for $db. See membership_batch.log.");
    }
    $r->summary = "$db: $counts[1] tickets for $counts[2] members, $counts[3] for $counts[4] non-members";
}

function buildArchivePackages(Context $ctx, Result $r): void
{
    $selection = $ctx->config['packageDbs'];
    $schemas = array_map(static fn($db) => substr($db, 4), $ctx->schemas());
    $names = $selection === 'ALL' ? $schemas : $selection;
    if (!is_array($names) || !$names) {
        throw new RuntimeException('Configure $monitoringPackageDatabases as an array of database names, or ALL.');
    }
    $script = $ctx->config['package'];
    if (!is_file($script)) {
        throw new RuntimeException('Configure $monitoringPackageScript to locate buildArchivePackagesCMD.php.');
    }
    $done = 0;
    foreach (array_unique($names) as $name) {
        $name = preg_replace('/^hdb_/', '', $name);
        if (!in_array($name, $schemas, true)) {
            $r->issue("Unknown database $name");
            continue;
        }
        try {
            $log = "$ctx->results/package-$name.log";
            phpJob($ctx, $script, ['--', '-db=' . $name], $log);
            $size = filesize($log);
            $tail = (string) file_get_contents($log, false, null, max(0, $size - 4096));
            if (!preg_match('/\bOK\s*$/D', $tail)) {
                throw new RuntimeException("Archive builder did not report completion. See package-$name.log.");
            }
            ++$done;
        } catch (Throwable $e) {
            $r->issue("$name: " . $e->getMessage());
        }
        sleep(min(5, $ctx->config['pause']));
    }
    $r->summary = "$done/" . count(array_unique($names)) . ' archive packages completed';
}

function listHeuristServers(Context $ctx, Result $r): void
{
    $urls = $ctx->config['servers'];
    if (!$urls) {
        $urls = [$ctx->config['website']];
    }
    atomicWrite("$ctx->results/list_of_heurist_servers.tsv", static function ($tmp) use ($urls): void {
        $out = fopen($tmp, 'wb');
        try {
            putLine($out, 'URL');
            foreach ($urls as $url) {
                putLine($out, rowLine([$url]));
            }
        } finally {
            fclose($out);
        }
    });
    $r->summary = count($urls) . ' configured server URLs';
}

function scanLegacyLinks(Context $ctx, Result $r): void
{
    // GNU grep handles binary detection and very long lines efficiently. File
    // selection and report lifecycle are controlled here in PHP.
    $path = $ctx->config['filestore'];
    if (!is_dir($path)) {
        throw new RuntimeException("Filestore is missing: $path");
    }
    $log = "$ctx->results/legacy_alpha_references.txt";
    $files = $matches = 0;
    atomicWrite($log, static function ($tmp) use ($ctx, $path, &$files, &$matches): void {
        command(lowPriority(['grep', '-rInHE', '--exclude=__filesearch.txt',
            '--exclude=*.[jJ][pP][gG]', '--exclude=*.[jJ][pP][eE][gG]',
            '--exclude=*.[pP][nN][gG]', '--exclude=*.[gG][iI][fF]', '--exclude=*.[tT][iI][fF]*',
            '--exclude=*.[xX][lL][sS]*', '--exclude=*.[dD][oO][cC]*', '--', 'h[0-9]-alpha', $path]), $ctx->results, $tmp, [0, 1]);
        $in = fopen($tmp, 'rb');
        $last = null;
        try {
            while (($line = fgets($in)) !== false) {
                ++$matches;
                if (preg_match('/^(.*?):\d+:/', $line, $match) && $match[1] !== $last) {
                    ++$files;
                    $last = $match[1];
                }
            }
        } finally {
            fclose($in);
        }
    });
    $r->summary = "$matches matching lines in $files files (legacy_alpha_references.txt)";
    if ($matches) {
        $r->issue('Legacy alpha references found. See the working list.', 'Warning');
    }
}

function alertServer(Context $ctx, Result $r): void
{
    $c = $ctx->config;
    $load = sys_getloadavg();
    $diskUse = null;
    try {
        $df = command(['df', '-P', '--', $c['disk']], $c['monitor']);
        $lines = explode("\n", trim($df));
        if (!preg_match('/\s(\d+)%\s/', end($lines), $match)) {
            throw new RuntimeException('Cannot parse disk usage.');
        }
        $diskUse = (int) $match[1];
    } catch (Throwable $e) {
        $r->issue($e->getMessage());
    }
    if ($load === false) {
        $r->issue('Cannot read one-minute load average.');
    }
    $r->summary = 'Load ' . ($load[0] ?? 'unknown') . ' (limit ' . $c['loadLimit'] . '), disk '
        . ($diskUse === null ? 'unknown' : "$diskUse%") . ' (limit ' . $c['diskLimit'] . '%)';
    $metrics = [
        'cpu' => [$load === false ? null : $load[0] > $c['loadLimit'], 'One-minute load exceeded the threshold.'],
        'disk' => [$diskUse === null ? null : $diskUse >= $c['diskLimit'], $c['disk'] . ' reached the disk usage threshold.'],
    ];
    foreach ($metrics as $key => [$active, $message]) {
        $stamp = $c['monitor'] . "/.alert-$key.timestamp";
        if ($active === null) {
            continue;
        }
        if (!$active) {
            if (is_file($stamp) && !unlink($stamp)) {
                $r->issue("Unable to reset $key alert reminder.");
            }
            continue;
        }
        $previous = is_file($stamp) ? (int) file_get_contents($stamp) : 0;
        if ($previous > 0 && time() >= $previous && time() - $previous < $c['reminder']) {
            $r->summary .= " ($key reminder suppressed)";
            continue;
        }
        $r->issue($message, 'Warning');
        $ctx->alertStamps[] = $stamp;
        try {
            // Use process names in the email to avoid exposing command-line credentials.
            $sample = $key === 'cpu'
                ? command(['ps', '-eo', 'pcpu,pid,user,comm', '--sort=-pcpu'], $c['monitor'])
                : command(['df', '-hP'], $c['monitor']);
            $r->details[] = implode("\n", array_slice(explode("\n", trim($sample)), 0, 11));
        } catch (Throwable $e) {
            $r->details[] = $e->getMessage();
        }
    }
}

function purgeReports(string $dir, Result $result): void
{
    $today = new DateTimeImmutable('today');
    // Clamp end-of-month arithmetic (e.g. March 31 -> February 28).
    $previousMonth = $today->modify('first day of last month');
    $cutoff = $previousMonth->setDate((int) $previousMonth->format('Y'),
        (int) $previousMonth->format('m'), min((int) $today->format('d'), (int) $previousMonth->format('t')))
        ->format('Y-m-d');
    $paths = glob("$dir/heurist-monitor-*.odt");
    if ($paths === false) {
        throw new RuntimeException('Cannot list monitoring reports.');
    }
    rsort($paths, SORT_STRING);
    $months = [];
    $removed = 0;
    foreach ($paths as $path) {
        if (!is_file($path) || is_link($path) || !preg_match(
            '/^heurist-monitor-((\d{4}-\d{2})-\d{2})_\d{6}_\d{6}-[a-z]+\.odt$/D',
            basename($path), $m
        )) {
            continue;
        }
        if ($m[1] >= $cutoff) {
            continue;
        }
        if (!isset($months[$m[2]])) {
            $months[$m[2]] = true;
        } elseif (unlink($path)) {
            ++$removed;
        } else {
            $result->issue("Cannot remove $path");
        }
    }
    $result->summary = "$removed old reports removed (latest older report per month kept)";
}

function reportText(array $results, string $server, string $selection, string $path): string
{
    $lines = ['Summary'];
    foreach ($results as $name => $result) {
        $summary = $result->summary ?: "$result->issueCount issues";
        $lines[] = sprintf('%s  %s  %s (%.1f s)', $result->status, $name, $summary, $result->seconds);
    }
    $lines[] = '';
    $lines[] = "$server | $selection | " . date('Y-m-d H:i:s T');
    $lines[] = "ODT report: $path";
    foreach ($results as $name => $result) {
        if (!$result->details) {
            continue;
        }
        $lines[] = '';
        $lines[] = $name;
        array_push($lines, ...$result->details);
        if ($result->issueCount > 100) {
            $lines[] = 'Additional issues omitted from this report. Total issue count: ' . $result->issueCount;
        }
    }
    return implode("\n", $lines) . "\n";
}

function reportOdt(string $path, string $text): void
{
    atomicWrite($path, static function ($tmp) use ($text): void {
        $mime = 'application/vnd.oasis.opendocument.text';
        $paragraphs = '';
        foreach (explode("\n", $text) as $line) {
            $valid = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $line);
            $escaped = htmlspecialchars($valid, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED, 'UTF-8');
            $paragraphs .= '<text:p>' . $escaped . '</text:p>';
        }
        $content = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
            . ' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:version="1.2">'
            . '<office:body><office:text>' . $paragraphs . '</office:text></office:body></office:document-content>';
        $manifest = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2">'
            . '<manifest:file-entry manifest:full-path="/" manifest:media-type="' . $mime . '" manifest:version="1.2"/>'
            . '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>'
            . '</manifest:manifest>';
        $zip = openZip($tmp);
        if (!$zip->addFromString('mimetype', $mime) || !$zip->setCompressionName('mimetype', ZipArchive::CM_STORE)
            || !$zip->addFromString('content.xml', $content)
            || !$zip->addFromString('META-INF/manifest.xml', $manifest) || !$zip->close()) {
            throw new RuntimeException('Cannot write ODT package.');
        }
    });
}

function parseCli(array $args): array
{
    $config = null;
    $requested = 'STANDARD';
    $mode = 'run';
    foreach (array_slice($args, 1) as $argument) {
        if (str_starts_with($argument, '--config=')) {
            $config = substr($argument, 9);
        } elseif (preg_match('/^(?:--|\?)?runList=(.+)$/D', $argument, $match)) {
            $requested = trim($match[1], "\"'");
        } elseif (in_array($argument, ['--list', '--dry-run', '--test-mail'], true)) {
            $mode = substr($argument, 2);
        } else {
            throw new InvalidArgumentException('Use --runList=STANDARD,BOOKSTACK, --config=/absolute/path, --list, --dry-run or --test-mail.');
        }
    }
    $labels = array_map(static fn($s) => strtoupper(trim($s)), explode(',', $requested));
    $tasks = [];
    foreach ($labels as $label) {
        if (!isset(RUN_LISTS[$label])) {
            throw new InvalidArgumentException("Unknown runList profile: $label");
        }
        array_push($tasks, ...RUN_LISTS[$label]);
    }
    $jobs = array_values(array_intersect(array_keys(JOBS), $tasks));
    if (in_array('pruneArchiveMonthly', $jobs, true) && in_array('pruneArchiveLatest', $jobs, true)) {
        throw new InvalidArgumentException('Select either STANDARD monthly archive retention or ARCHIVE_LATEST4.');
    }
    return [$config, implode(',', array_unique($labels)), $jobs, $mode];
}

function findConfig(?string $given): string
{
    if ($given !== null) {
        if (!str_starts_with($given, '/')) {
            throw new RuntimeException('--config requires an absolute path.');
        }
        return $given;
    }
    for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
        if (is_file("$dir/heuristConfigIni.php")) {
            return "$dir/heuristConfigIni.php";
        }
    }
    return '/var/www/html/HEURIST/heuristConfigIni.php';
}

function main(array $args): int
{
    [$configFile, $selection, $jobs, $mode] = parseCli($args);
    if ($mode === 'list') {
        foreach (JOBS as $job => $description) {
            echo "$job  $description\n";
        }
        foreach (RUN_LISTS as $name => $members) {
            echo "$name = " . implode(', ', $members) . "\n";
        }
        return 0;
    }
    if ($mode === 'dry-run') {
        echo implode("\n", $jobs) . "\n";
        return 0;
    }

    $config = loadSettings(findConfig($configFile));
    if ($mode === 'run' && $jobs !== ['alertServer'] && function_exists('proc_nice')) {
        proc_nice(10);
    }
    $ctx = new Context($config);
    $monitor = $config['monitor'];
    ensureDir($monitor);
    $alertOnly = $mode === 'run' && $jobs === ['alertServer'];
    $lockPath = $monitor . ($alertOnly ? '/.alerts.lock' : '/.maintenance.lock');
    $lock = fopen($lockPath, 'c');
    if ($lock === false) {
        throw new RuntimeException("Cannot open $lockPath");
    }
    if (!flock($lock, LOCK_EX | LOCK_NB, $busy)) {
        if (!$busy) {
            throw new RuntimeException("Cannot lock $lockPath");
        }
        if (!$alertOnly) {
            fwrite(STDERR, "Heurist maintenance already running. Skipped $selection.\n");
        }
        fclose($lock);
        return $alertOnly ? 0 : 1;
    }

    // When alerts are explicitly included in a maintenance selection, use the
    // same secondary lock as the standalone five-minute invocation.
    $alertLock = null;
    $results = [];
    try {
        if (!$alertOnly) {
            ensureDir($ctx->results);
        }
        $ctx->backupSelected = in_array('backupDatabases', $jobs, true);
        if ($mode === 'test-mail') {
            $results['mailTest'] = new Result();
            $results['mailTest']->summary = '1 test report';
        } else {
            foreach ($jobs as $index => $job) {
                if ($index > 0 && $job !== 'alertServer') {
                    sleep($config['pause']);
                }
                $r = $results[$job] = new Result();
                $start = microtime(true);
                try {
                    if ($job === 'alertServer' && !$alertOnly) {
                        $alertLock = fopen("$monitor/.alerts.lock", 'c');
                        if (!$alertLock || !flock($alertLock, LOCK_EX | LOCK_NB)) {
                            throw new RuntimeException('Another alert check is in progress.');
                        }
                    }
                    if ($job === 'checkMembershipBatch' && $results['refreshMembership']->status === 'Failure') {
                        throw new RuntimeException('Skipped ticket updates because the membership refresh failed.');
                    }
                    $job($ctx, $r);
                } catch (Throwable $e) {
                    $r->issue($e->getMessage());
                } finally {
                    // In a mixed run hold the lock through the consolidated email.
                    $r->seconds = microtime(true) - $start;
                }
            }
        }
        if ($alertOnly && $results['alertServer']->status === 'Success') {
            return 0;
        }

        // Reports and their retention have a separate short lock so alert polling
        // does not have to wait for overnight dumps.
        $reportsLock = fopen("$monitor/.reports.lock", 'c');
        $type = $alertOnly ? 'alert' : ($mode === 'test-mail' ? 'test' : 'maintenance');
        $time = new DateTimeImmutable();
        $path = "$monitor/heurist-monitor-" . $time->format('Y-m-d_His_u') . "-$type.odt";
        try {
            if (!$reportsLock || !flock($reportsLock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock the reports directory.');
            }
            $retention = new Result();
            purgeReports($monitor, $retention);
            if ($retention->status !== 'Success' || !$alertOnly) {
                $results['reportRetention'] = $retention;
            }
            reportOdt($path, reportText($results, $config['server'], $selection, $path));
        } catch (Throwable $e) {
            $results['reportStorage'] = new Result();
            $results['reportStorage']->issue($e->getMessage());
            $path = 'unavailable';
        } finally {
            if (is_resource($reportsLock)) {
                fclose($reportsLock);
            }
        }

        $counts = ['Failure' => 0, 'Warning' => 0, 'Success' => 0];
        foreach ($results as $r) {
            ++$counts[$r->status];
        }
        $server = preg_replace('/[\r\n]+/', ' ', $config['server']);
        $subject = sprintf('FAILURES %d WARNINGS %d Success %d | Heurist %s | %s',
            $counts['Failure'], $counts['Warning'], $counts['Success'], $server,
            $mode === 'test-mail' ? 'mail test' : $selection);
        $body = reportText($results, $config['server'], $selection, $path);
        if (!mail($config['mailTo'], $subject, $body, [
            'From' => $config['mailFrom'], 'Content-Type' => 'text/plain; charset=UTF-8',
        ])) {
            fwrite(STDERR, "Unable to submit consolidated Heurist email. ODT report: $path\n");
            return 1;
        }
        foreach ($ctx->alertStamps as $stamp) {
            atomicWrite($stamp, static fn($tmp) => writeText($tmp, (string) time()));
        }
        return $counts['Failure'] > 0 ? 1 : 0;
    } finally {
        if (is_resource($alertLock)) {
            fclose($alertLock);
        }
        fclose($lock);
    }
}

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
umask(0077);
putenv('PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin');
putenv('LC_ALL=C');
date_default_timezone_set('Europe/Paris');
try {
    exit(main($argv));
} catch (Throwable $error) {
    fwrite(STDERR, 'Heurist monitoring: ' . $error->getMessage() . "\n");
    exit(1);
}
