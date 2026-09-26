<?php

namespace hserv\synchronisation;

/**
 * Shared additions-only record writer. Call inside a SyncTransaction.
 *
 * A snapshot is evidence of values to retain, never authority to remove values.
 * Existing detail IDs (including file references) are left untouched. Matching
 * uses a multiset: two identical source values remain two values, but replaying
 * the same snapshot does not grow that count. Local IDs must be resolved first.
 */
final class SyncAdditiveRecord
{
    /** Reject truncated/malformed snapshots instead of treating missing details as empty. */
    public static function validate(array $records): void
    {
        $seen = [];
        foreach ($records as $record) {
            if (!is_array($record) || (int)($record['rec_ID'] ?? 0) < 1
                || !isset($record['details']) || !is_array($record['details'])) {
                throw new \RuntimeException('An incoming record is incomplete; no change cursor may be advanced.');
            }
            $id = (int)$record['rec_ID'];
            if (isset($seen[$id])) throw new \RuntimeException("Duplicate incoming record ID $id.");
            $seen[$id] = true;
            foreach ($record['details'] as $detail) {
                if (!is_array($detail) || empty($detail['detailTypeConceptID']) || empty($detail['dty_Type'])
                    || !array_key_exists('dtl_Value', $detail)) {
                    throw new \RuntimeException("An incoming field in record $id is incomplete.");
                }
                if (in_array($detail['dty_Type'], ['enum', 'relationtype'], true)
                    && (int)$detail['dtl_Value'] > 0 && empty($detail['termConceptID'])) {
                    throw new \RuntimeException("A term in record $id has no portable concept identity.");
                }
                if (!empty($detail['dtl_UploadedFileID']) && empty($detail['satelliteFileID'])) {
                    throw new \RuntimeException("A file in record $id has no portable file identity.");
                }
            }
        }
    }

    /** Lock before comparing, serialising sync writers and normal record-header saves. */
    public static function lock(\mysqli $mysqli, int $recordID): array
    {
        $result = $mysqli->query('SELECT * FROM Records WHERE rec_ID='.$recordID.' FOR UPDATE');
        if (!$result) throw new \RuntimeException('Unable to lock synchronised record: '.$mysqli->error);
        $row = $result->fetch_assoc();
        $result->close();
        if (!$row) throw new \RuntimeException("Synchronised record $recordID no longer exists.");
        return $row;
    }

    /** Fill reservations; for established records only fill empty descriptive headers. */
    public static function header(\mysqli $mysqli, array $record): void
    {
        $id = (int)$record['rec_ID'];
        $current = self::lock($mysqli, $id);
        $updates = [];
        $temporary = (int)$current['rec_FlagTemporary'] === 1;
        foreach (['rec_URL', 'rec_Title', 'rec_ScratchPad', 'rec_Added', 'rec_AddedByImport', 'rec_NonOwnerVisibility'] as $column) {
            if (!array_key_exists($column, $record)) continue;
            $incoming = $record[$column];
            if ($temporary || (in_array($column, ['rec_URL', 'rec_Title', 'rec_ScratchPad'], true)
                && ($current[$column] === null || $current[$column] === '') && $incoming !== null && $incoming !== '')) {
                $updates[] = '`'.$column.'`='.($incoming === null ? 'NULL' : "'".$mysqli->real_escape_string((string)$incoming)."'");
            }
        }
        if ($temporary) $updates[] = 'rec_FlagTemporary=0';
        if ($updates && !$mysqli->query('UPDATE Records SET '.implode(',', $updates)
            .',rec_Modified=UTC_TIMESTAMP() WHERE rec_ID='.$id)) {
            throw new \RuntimeException("Unable to fill record $id: ".$mysqli->error);
        }
    }

    /** Exact stored value and metadata identity; SQL numeric strings are normalised. */
    public static function key(array $detail): string
    {
        return hash('sha256', serialize([
            (int)$detail['dtl_DetailTypeID'],
            isset($detail['dtl_Value']) ? (string)$detail['dtl_Value'] : null,
            empty($detail['dtl_UploadedFileID']) ? null : (int)$detail['dtl_UploadedFileID'],
            $detail['dtl_Geo'] ?? null,
            isset($detail['dtl_Certainty']) ? (float)$detail['dtl_Certainty'] : null,
            $detail['dtl_Annotation'] ?? null,
            isset($detail['dtl_HideFromPublic']) ? (int)$detail['dtl_HideFromPublic'] : null
        ]));
    }

    /** Append missing occurrences without updating/deleting any existing detail row. */
    public static function details(\mysqli $mysqli, int $recordID, array $details, ?callable $onInsertError = null): int
    {
        self::lock($mysqli, $recordID);
        $result = $mysqli->query('SELECT dtl_DetailTypeID,dtl_Value,dtl_UploadedFileID,ST_AsText(dtl_Geo) AS dtl_Geo,'
            .'dtl_Certainty,dtl_Annotation,dtl_HideFromPublic FROM recDetails WHERE dtl_RecID='.$recordID.' FOR UPDATE');
        if (!$result) throw new \RuntimeException('Unable to read existing record fields: '.$mysqli->error);
        $available = [];
        while ($row = $result->fetch_assoc()) {
            $key = self::key($row);
            $available[$key] = ($available[$key] ?? 0) + 1;
        }
        $result->close();
        $added = 0;
        foreach ($details as $detail) {
            $key = self::key($detail);
            if (($available[$key] ?? 0) > 0) { $available[$key]--; continue; }
            $stmt = $mysqli->prepare('INSERT INTO recDetails '
                .'(dtl_RecID,dtl_DetailTypeID,dtl_Value,dtl_UploadedFileID,dtl_Geo,dtl_Certainty,dtl_Annotation,dtl_HideFromPublic,dtl_AddedByImport) '
                .'VALUES (?,?,?,?,IF(? IS NULL,NULL,ST_GeomFromText(?)),?,?,?,1)');
            if (!$stmt) throw new \RuntimeException('Unable to prepare additive field insert: '.$mysqli->error);
            $dtyID = (int)$detail['dtl_DetailTypeID']; $value = $detail['dtl_Value'] ?? null;
            $fileID = $detail['dtl_UploadedFileID'] ?? null; $geo = $detail['dtl_Geo'] ?? null;
            $certainty = isset($detail['dtl_Certainty']) ? (float)$detail['dtl_Certainty'] : null;
            $annotation = $detail['dtl_Annotation'] ?? null; $hidden = $detail['dtl_HideFromPublic'] ?? null;
            $stmt->bind_param('iisissdsi', $recordID, $dtyID, $value, $fileID, $geo, $geo, $certainty, $annotation, $hidden);
            try {
                if (!$stmt->execute()) throw new \RuntimeException("Unable to append field $dtyID to record $recordID: ".$stmt->error);
            } catch (\Throwable $error) {
                // Preserve caller-specific field and record diagnostics before rollback.
                if ($onInsertError !== null) $onInsertError($detail, $stmt->errno, $stmt->error ?: $error->getMessage());
                throw $error;
            } finally { $stmt->close(); }
            $added++;
        }
        if ($added > 0 && !$mysqli->query('UPDATE Records SET rec_Modified=UTC_TIMESTAMP() WHERE rec_ID='.$recordID)) {
            throw new \RuntimeException('Unable to mark the synchronised record modification time.');
        }
        return $added;
    }
}
