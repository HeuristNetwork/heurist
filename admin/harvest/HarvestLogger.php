<?php

declare(strict_types=1);

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
    write_out($block);
    file_put_contents(LOG_FILE, $block, FILE_APPEND);
}

function logLine(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    write_out($line);
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

function logWarning(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] WARNING: ' . $message . PHP_EOL;
    write_err($line);
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

function logError(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $message . PHP_EOL;
    write_err($line);
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

function write_out(string $message): void
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDOUT, $message);
    } else {
        echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'<br>';
    }
}

function write_err(string $message): void
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message);
    } else {
        echo 'ERROR: '.htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'<br>';
        error_log($message);
    }
}
