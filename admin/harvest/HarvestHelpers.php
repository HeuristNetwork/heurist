<?php

declare(strict_types=1);

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

function normaliseDbNullableInt(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    $intValue = (int)$value;
    return $intValue > 0 ? $intValue : null;
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

function isListArray(array $array): bool
{
    if ($array === []) {
        return true;
    }

    return array_keys($array) === range(0, count($array) - 1);
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
