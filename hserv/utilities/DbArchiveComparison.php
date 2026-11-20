<?php
/**
 * Read-only comparison of records with a Heurist database archive.
 *
 * @project Heurist academic knowledge management system
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
namespace hserv\utilities;

use RuntimeException;

final class DbArchiveComparison
{
    private const TYPES = [
        'RECORD DELETED', 'ALL VALUES REMOVED', 'VALUE REMOVED',
        'VALUE CHANGED', 'VALUE ADDED', 'RECORD ADDED'
    ];

    public static function download(\mysqli $db, string $database, string $archive): void
    {
        $archiveRows = tmpfile();
        if ($archiveRows === false) {
            throw new RuntimeException('Cannot create temporary storage for the archived records.');
        }

        $reports = [];
        try {
            [$records, $details, $utcTimestamps] = self::readArchive($archive, $archiveRows);
            ksort($records, SORT_NUMERIC);
            foreach (self::TYPES as $type) {
                $stream = tmpfile();
                if ($stream === false) {
                    throw new RuntimeException('Cannot create a temporary comparison report.');
                }
                $reports[$type] = $stream;
            }

            foreach (self::currentRecords($db, $utcTimestamps) as $id => [$record, $values]) {
                while (key($records) !== null && key($records) < $id) {
                    $deletedId = key($records);
                    self::writeDeleted($reports, $deletedId, self::readArchivedRow($archiveRows, current($records))[1],
                        self::archivedValues($archiveRows, $details[$deletedId] ?? null));
                    next($records);
                }
                if (key($records) === $id) {
                    [, $previous] = self::readArchivedRow($archiveRows, current($records));
                    self::compareRecord($reports, $id, $previous, $record,
                        self::archivedValues($archiveRows, $details[$id] ?? null), $values);
                    next($records);
                } else {
                    self::writeDifference($reports, 'RECORD ADDED', $id, $record['rec_Title'], '', '',
                        self::recordValue($record, $values));
                }
            }
            while (key($records) !== null) {
                $deletedId = key($records);
                self::writeDeleted($reports, $deletedId, self::readArchivedRow($archiveRows, current($records))[1],
                    self::archivedValues($archiveRows, $details[$deletedId] ?? null));
                next($records);
            }

            $date = getNow();
            $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $database);
            header('Content-Type: text/tab-separated-values; charset=UTF-8');
            header('Content-Disposition: attachment; filename="'.$filename.'_archive_comparison_'.$date->format('Y-m-d_His').'.tsv"');
            header('Cache-Control: no-store');

            $output = fopen('php://output', 'wb');
            try {
                self::writeRow($output, ['Database', $database]);
                self::writeRow($output, ['Date', $date->format(DATE_ATOM)]);
                self::writeRow($output, ['Archive', basename($archive)]);
                self::writeRow($output, ['Difference type', 'Record ID', 'Constructed title', 'Field', 'Old value', 'New value']);
                foreach ($reports as $stream) {
                    rewind($stream);
                    fpassthru($stream);
                }
            } finally {
                fclose($output);
            }
        } finally {
            fclose($archiveRows);
            foreach ($reports as $stream) {
                fclose($stream);
            }
        }
    }

    private static function currentRecords(\mysqli $db, bool $utcTimestamps): \Generator
    {
        // MySQL dumps normally write TIMESTAMP values in UTC. DATETIME columns
        // already contain literal values and must retain their original timezone.
        $timestamp = $utcTimestamps
            ? ", TIMESTAMPADD(SECOND, UNIX_TIMESTAMP(r.rec_Modified), '1970-01-01 00:00:00') AS rec_Modified"
            : '';
        $query = 'SELECT r.*'.$timestamp.', d.dtl_ID, d.dtl_DetailTypeID, d.dtl_Value, d.dtl_UploadedFileID,
                         HEX(d.dtl_Geo) AS dtl_Geo
                  FROM Records r LEFT JOIN recDetails d ON d.dtl_RecID = r.rec_ID
                  ORDER BY r.rec_ID, d.dtl_ID';
        $result = $db->query($query, MYSQLI_USE_RESULT);
        if ($result === false) {
            throw new RuntimeException('Cannot read the current database records.');
        }

        $id = null;
        $values = [];
        try {
            while (is_array($row = $result->fetch_assoc())) {
                $nextId = (int)$row['rec_ID'];
                if ($nextId !== $id) {
                    if ($id !== null) {
                        yield $id => [$record, $values];
                    }
                    $id = $nextId;
                    $record = array_filter($row, static function ($column) {
                        return strpos($column, 'rec_') === 0;
                    }, ARRAY_FILTER_USE_KEY);
                    $values = [];
                }
                if ($row['dtl_ID'] !== null) {
                    self::addDetail($values, $row, $row['dtl_Geo']);
                }
            }
            if ($row === false) {
                throw new RuntimeException('Cannot finish reading the current database records.');
            }
            if ($id !== null) {
                yield $id => [$record, $values];
            }
        } finally {
            $result->free();
        }
    }

    private static function addDetail(array &$values, array $row, ?string $geometry): void
    {
        $value = $row['dtl_Value'] ?? null;
        $file = $row['dtl_UploadedFileID'] ?? null;
        if ($value !== null || $file !== null || $geometry !== null) {
            $values[(int)$row['dtl_DetailTypeID']][(int)$row['dtl_ID']] = [
                $value, $file === null ? null : (string)$file, $geometry
            ];
        }
    }

    private static function compareRecord(array $reports, int $id, array $old, array $new, array $oldValues, array $newValues): void
    {
        $title = $new['rec_Title'];
        foreach (array_intersect_key($new, $old) as $field => $value) {
            $previous = $old[$field];
            $value = $value === null ? null : (string)$value;
            if ($previous === $value) {
                continue;
            }
            $type = $value === null ? 'VALUE REMOVED' : ($previous === null ? 'VALUE ADDED' : 'VALUE CHANGED');
            self::writeDifference($reports, $type, $id, $title, $field, $previous, $value ?? 'VALUE REMOVED');
        }

        if ($oldValues && !$newValues) {
            foreach ($oldValues as $field => $values) {
                $previous = array_map([self::class, 'displayValue'], array_values($values));
                self::writeDifference($reports, 'ALL VALUES REMOVED', $id, $title, 'Detail type '.$field,
                    json_encode($previous, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'ALL VALUES REMOVED');
            }
            return;
        }

        $fields = array_keys($oldValues + $newValues);
        sort($fields, SORT_NUMERIC);
        foreach ($fields as $field) {
            self::compareField($reports, $id, $title, $field, $oldValues[$field] ?? [], $newValues[$field] ?? []);
        }
    }

    private static function compareField(array $reports, int $id, string $title, int $field, array $old, array $new): void
    {
        // Retain unchanged values even if an edit recreated or reordered the detail rows.
        foreach ($old as $detail => $value) {
            if (($new[$detail] ?? null) === $value) {
                unset($old[$detail], $new[$detail]);
            }
        }
        $matches = [];
        foreach ($new as $detail => $value) {
            $matches[serialize($value)][] = $detail;
        }
        foreach ($old as $detail => $value) {
            $key = serialize($value);
            if (!empty($matches[$key])) {
                unset($old[$detail], $new[array_pop($matches[$key])]);
            }
        }

        $label = 'Detail type '.$field;
        foreach (array_intersect_key($old, $new) as $detail => $value) {
            self::writeDifference($reports, 'VALUE CHANGED', $id, $title, $label,
                self::displayValue($value), self::displayValue($new[$detail]));
            unset($old[$detail], $new[$detail]);
        }
        // Heurist can replace the underlying detail row when saving a field.
        // A single unmatched old and new value form an unambiguous replacement.
        if (count($old) === 1 && count($new) === 1) {
            self::writeDifference($reports, 'VALUE CHANGED', $id, $title, $label,
                self::displayValue(reset($old)), self::displayValue(reset($new)));
            return;
        }
        foreach ($old as $value) {
            self::writeDifference($reports, 'VALUE REMOVED', $id, $title, $label, self::displayValue($value), 'VALUE REMOVED');
        }
        foreach ($new as $value) {
            self::writeDifference($reports, 'VALUE ADDED', $id, $title, $label, '', self::displayValue($value));
        }
    }

    private static function displayValue(array $value): string
    {
        [$text, $file, $geometry] = $value;
        if ($file === null && $geometry === null) {
            return $text ?? '';
        }
        return json_encode(array_filter(['Text' => $text, 'File ID' => $file, 'Geometry (hex)' => $geometry],
            static function ($item) { return $item !== null; }), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function archivedValues($archiveRows, ?int $offset): array
    {
        $values = [];
        while ($offset !== null) {
            [$offset, $row] = self::readArchivedRow($archiveRows, $offset);
            self::addDetail($values, $row, $row['dtl_Geo']);
        }
        ksort($values, SORT_NUMERIC);
        foreach ($values as &$fieldValues) {
            ksort($fieldValues, SORT_NUMERIC);
        }
        return $values;
    }

    private static function recordValue(array $record, array $values): string
    {
        unset($record['rec_ID']);
        foreach ($values as $field => $fieldValues) {
            $record['Details'][$field] = array_map([self::class, 'displayValue'], array_values($fieldValues));
        }
        return json_encode($record, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function writeDeleted(array $reports, int $id, array $record, array $values): void
    {
        self::writeDifference($reports, 'RECORD DELETED', $id, $record['rec_Title'], '',
            self::recordValue($record, $values), 'RECORD DELETED');
    }

    private static function writeDifference(array $reports, string $type, int $id, string $title, string $field, ?string $old, ?string $new): void
    {
        self::writeRow($reports[$type], [$type, $id, $title, $field, $old, $new]);
    }

    private static function writeRow($stream, array $row): void
    {
        foreach ($row as &$cell) {
            // Keep spreadsheet applications from interpreting record text as formulas.
            if (is_string($cell) && !is_numeric($cell) && preg_match('/^\s*[=+@-]|^[\t\r\n]/', $cell)) {
                $cell = "'".$cell;
            }
        }
        if (fputcsv($stream, $row, "\t", '"', '') === false) {
            throw new RuntimeException('Cannot write the comparison report.');
        }
    }

    private static function readArchive(string $archive, $archiveRows): array
    {
        $zip = null;
        $stream = null;
        try {
            if (preg_match('/\.sql\.bz2(?:\.|$)|\.bz2$/i', $archive)) {
                if (!extension_loaded('bz2')) {
                    throw new RuntimeException('The PHP bz2 extension is required to read this archive.');
                }
                $stream = @fopen('compress.bzip2://'.$archive, 'rb');
            } else {
                if (!class_exists(\ZipArchive::class)) {
                    throw new RuntimeException('The PHP ZIP extension is required to read this archive.');
                }
                $zip = new \ZipArchive();
                if ($zip->open($archive, \ZipArchive::RDONLY) !== true) {
                    $zip = null;
                    throw new RuntimeException('Cannot open the selected ZIP archive.');
                }
                $dumps = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->getNameIndex($i);
                    if (preg_match('~^(?:\./)?[^/\x5c]+\.sql$~i', $entry)) {
                        $dumps[] = $entry;
                    }
                }
                if (count($dumps) !== 1) {
                    throw new RuntimeException('The archive must contain one SQL dump at its top level.');
                }
                $stream = $zip->getStream($dumps[0]);
            }
            if (!$stream) {
                throw new RuntimeException('Cannot read the SQL dump in the selected archive.');
            }
            return self::readDump($stream, $archiveRows);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if ($zip !== null) {
                $zip->close();
            }
        }
    }

    /** Read mysqldump/PHP-dumper literals. SQL from an archive is never executed. */
    private static function readDump($stream, $archiveRows): array
    {
        $columns = [];
        $creating = null;
        $records = [];
        $details = [];
        $utcTimestamps = false;
        while (($line = fgets($stream)) !== false) {
            $line = ltrim($line);
            if (preg_match("/^(?:\/\*!\d+\s+)?SET\s+TIME_ZONE\s*=\s*'\+00:00'/i", $line)) {
                $utcTimestamps = true;
            } elseif (preg_match('/^CREATE TABLE(?: IF NOT EXISTS)?\s+\x60?(Records|recDetails)\b\x60?\s*\(/i', $line, $m)) {
                $creating = strtolower($m[1]);
                $columns[$creating] = [];
            } elseif ($creating !== null) {
                if (preg_match('/^(\x60\w+\x60|(?:rec|dtl)_\w+)\s+/i', $line, $m)) {
                    $columns[$creating][] = trim($m[1], chr(96));
                } elseif (strpos($line, ')') === 0) {
                    $creating = null;
                }
            } elseif (preg_match('/^(?:INSERT(?:\s+IGNORE)?|REPLACE)\s+INTO\s+\x60?(Records|recDetails)\b\x60?\s*(?:\(([\w\x60,\s]+)\)\s*)?VALUES\s*/i', $line, $m)) {
                $table = strtolower($m[1]);
                $fields = empty($m[2]) ? ($columns[$table] ?? []) : preg_split('/[\s,\x60]+/', $m[2], -1, PREG_SPLIT_NO_EMPTY);
                $offset = strlen($m[0]);
                while (!str_ends_with(rtrim($line), ';')) {
                    $next = fgets($stream);
                    if ($next === false) {
                        throw new RuntimeException('The archived record data is incomplete.');
                    }
                    $line .= $next;
                }
                foreach (self::readRows($line, $offset, $fields) as $row) {
                    if ($table === 'records') {
                        $id = self::id($row, 'rec_ID');
                        if (isset($records[$id]) || !isset($row['rec_Title'])) {
                            throw new RuntimeException('The archive has invalid or duplicate records.');
                        }
                        $records[$id] = self::storeArchivedRow($archiveRows, null, $row);
                    } else {
                        $id = self::id($row, 'dtl_RecID');
                        self::id($row, 'dtl_ID');
                        self::id($row, 'dtl_DetailTypeID');
                        $geometry = $row['dtl_Geo'] ?? null;
                        $row = array_intersect_key($row, array_flip([
                            'dtl_ID', 'dtl_DetailTypeID', 'dtl_Value', 'dtl_UploadedFileID'
                        ]));
                        $row['dtl_Geo'] = $geometry === null ? null : strtoupper(bin2hex($geometry));
                        $details[$id] = self::storeArchivedRow($archiveRows, $details[$id] ?? null, $row);
                    }
                }
            } elseif (preg_match('/^(?:INSERT(?:\s+IGNORE)?|REPLACE)\s+INTO\s+\x60?(?:Records|recDetails)\b/i', $line)) {
                throw new RuntimeException('The archive uses an unsupported record INSERT format.');
            }
        }

        if (!feof($stream) || $creating !== null
            || array_diff(['rec_ID', 'rec_Title'], $columns['records'] ?? [])
            || array_diff(['dtl_ID', 'dtl_RecID', 'dtl_DetailTypeID', 'dtl_Value'], $columns['recdetails'] ?? [])) {
            throw new RuntimeException('The SQL dump must contain the Records and recDetails tables.');
        }
        return [$records, $details, $utcTimestamps];
    }

    // Keep archived rows on disk. Only the record offsets and the last
    // detail offset for each record need to stay in memory during comparison.
    private static function storeArchivedRow($stream, ?int $previous, array $row): int
    {
        $offset = ftell($stream);
        $encoded = base64_encode(serialize([$previous, $row]))."\n";
        if ($offset === false || fwrite($stream, $encoded) !== strlen($encoded)) {
            throw new RuntimeException('Cannot store the archived record data.');
        }
        return $offset;
    }

    private static function readArchivedRow($stream, int $offset): array
    {
        if (fseek($stream, $offset) !== 0 || ($encoded = fgets($stream)) === false) {
            throw new RuntimeException('Cannot read the extracted archive data.');
        }
        return unserialize(base64_decode($encoded), ['allowed_classes' => false]);
    }

    private static function id(array $row, string $column): int
    {
        if (!isset($row[$column]) || !ctype_digit($row[$column]) || (int)$row[$column] <= 0) {
            throw new RuntimeException('The archive contains an invalid record or detail ID.');
        }
        return (int)$row[$column];
    }

    private static function readRows(string $sql, int $offset, array $fields): \Generator
    {
        $literal = <<<'REGEX'
~\G\s*(?:(?:_\w+\s*)?'((?:[^'\\]++|\\.|'')*)'|(?:0x([a-f0-9]+)|x'([a-f0-9]*)')|(-?\d+(?:\.\d+)?(?:e[+-]?\d+)?|NULL))\s*([,)])~is
REGEX;
        $escapes = ['0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a"];
        $fieldCount = count($fields);
        if ($fieldCount === 0) {
            throw new RuntimeException('The archived record INSERT is missing its column definitions.');
        }
        while (preg_match('/\G\s*\(/', $sql, $m, 0, $offset)) {
            $offset += strlen($m[0]);
            $row = [];
            foreach ($fields as $index => $field) {
                if (!preg_match($literal, $sql, $m, PREG_UNMATCHED_AS_NULL, $offset)
                    || $m[5] !== ($index === $fieldCount - 1 ? ')' : ',')) {
                    throw new RuntimeException('Cannot decode a row in the archived record data.');
                }
                $offset += strlen($m[0]);
                if ($m[1] !== null) {
                    $row[$field] = preg_replace_callback('~\x5c(.)|\'\'~s', static function ($match) use ($escapes) {
                        return isset($match[1]) ? ($escapes[$match[1]] ?? $match[1]) : "'";
                    }, $m[1]);
                } elseif ($m[2] !== null || $m[3] !== null) {
                    $hex = $m[2] ?? $m[3];
                    $row[$field] = hex2bin((strlen($hex) % 2 ? '0' : '').$hex);
                } else {
                    $row[$field] = strcasecmp($m[4], 'NULL') === 0 ? null : $m[4];
                }
            }
            yield $row;
            if (preg_match('/\G\s*;\s*$/D', $sql, $m, 0, $offset)) {
                return;
            }
            if (!preg_match('/\G\s*,/', $sql, $m, 0, $offset)) {
                break;
            }
            $offset += strlen($m[0]);
        }
        throw new RuntimeException('Cannot decode the archived record INSERT statement.');
    }
}
