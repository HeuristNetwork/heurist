<?php
/**
 * Minimal batched-fetch helpers for Heurist record viewer.
 * No caching. No side effects. PDO only.
 *
 * If your environment provides mysqli ($mysqli) instead of PDO,
 * you can quickly create a PDO once during bootstrap:
 *
 *   $pdo = new PDO(
 *       'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
 *       DB_USER,
 *       DB_PASSWORD,
 *       [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
 *         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ]
 *   );
 */

declare(strict_types=1);

/**
 * Fetch all recDetails rows for a record, already joined to field defs;
 * keep it ONE query. We DON'T inline titles/term labels here so we can
 * optionally control batching/chunking below. If you prefer a single
 * mega-join, see hr_fetch_details_joined_onequery() at the bottom.
 */
function hr_fetch_details_with_defs(PDO $pdo, int $recId): array {
    $sql = <<<SQL
        SELECT
            d.dtl_ID,
            d.dtl_RecID,
            d.dtl_FieldID,
            d.dtl_Value,
            d.dtl_UValue,
            d.dtl_Geo,
            d.dtl_Order,
            f.fld_ID,
            f.fld_Name,
            f.fld_Type,
            f.fld_Json
        FROM recDetails d
        JOIN defFields f ON f.fld_ID = d.dtl_FieldID
        WHERE d.dtl_RecID = :id
        ORDER BY d.dtl_Order, d.dtl_ID
    SQL;
    $st = $pdo->prepare($sql);
    $st->execute([':id'=>$recId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Utility: build "IN" placeholders and bind array safely. */
function hr_bind_in(PDOStatement $st, string $prefix, array $ids, int $type = PDO::PARAM_INT): void {
    $i = 0;
    foreach ($ids as $v) {
        $st->bindValue(":{$prefix}{$i}", $v, $type);
        $i++;
    }
}

/** Build the "IN (:x0, :x1, ...)" list for an array. */
function hr_in_clause(string $prefix, int $count): string {
    $ph = [];
    for ($i=0; $i<$count; $i++) $ph[] = ":{$prefix}{$i}";
    return '(' . implode(',', $ph) . ')';
}

/** Small helper to chunk very large IN lists (kept tiny here for simplicity). */
function hr_chunk(array $arr, int $size = 1000): array {
    return array_chunk($arr, max(1, $size));
}

/** Bulk fetch field definitions (usually unnecessary if you joined in details). */
function hr_fetch_field_defs(PDO $pdo, array $fieldIds): array {
    if (empty($fieldIds)) return [];
    $out = [];
    foreach (hr_chunk($fieldIds, 1000) as $chunk) {
        $sql = "SELECT fld_ID,fld_Name,fld_Type,fld_Json FROM defFields WHERE fld_ID IN " . hr_in_clause('f', count($chunk));
        $st  = $pdo->prepare($sql);
        hr_bind_in($st, 'f', array_values($chunk));
        $st->execute();
        foreach ($st as $r) $out[(int)$r['fld_ID']] = $r;
    }
    return $out;
}

/** Bulk fetch terms → labels. */
function hr_fetch_term_labels(PDO $pdo, array $termIds): array {
    if (empty($termIds)) return [];
    $labels = [];
    foreach (hr_chunk($termIds, 1000) as $chunk) {
        $sql = "SELECT trm_ID, trm_Label FROM defTerms WHERE trm_ID IN " . hr_in_clause('t', count($chunk));
        $st  = $pdo->prepare($sql);
        hr_bind_in($st, 't', array_values($chunk));
        $st->execute();
        foreach ($st as $r) $labels[(int)$r['trm_ID']] = $r['trm_Label'];
    }
    return $labels;
}

/** Bulk fetch record titles for pointer/resource fields. */
function hr_fetch_record_titles(PDO $pdo, array $recIds): array {
    if (empty($recIds)) return [];
    $titles = [];
    foreach (hr_chunk($recIds, 1000) as $chunk) {
        $sql = "SELECT rec_ID, rec_Title FROM Records WHERE rec_ID IN " . hr_in_clause('r', count($chunk));
        $st  = $pdo->prepare($sql);
        hr_bind_in($st, 'r', array_values($chunk));
        $st->execute();
        foreach ($st as $r) $titles[(int)$r['rec_ID']] = $r['rec_Title'];
    }
    return $titles;
}

/** Fetch record title (single row). */
function hr_fetch_record_title(PDO $pdo, int $recId): ?string {
    $st = $pdo->prepare("SELECT rec_Title FROM Records WHERE rec_ID = :id");
    $st->execute([':id'=>$recId]);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

/** Fetch relationships in one query (typed + other side title). */
function hr_fetch_relationships(PDO $pdo, int $recId, int $limit = 200): array {
    $sql = <<<SQL
        SELECT
          rl.rel_ID,
          rl.rel_SourceID, rl.rel_TargetID,
          rl.rel_TypeID,
          rt.rty_Name AS rel_type,
          CASE WHEN rl.rel_SourceID = :id THEN rl.rel_TargetID ELSE rl.rel_SourceID END AS other_id,
          r.rec_Title AS other_title
        FROM recLinks rl
        JOIN defRelationshipTypes rt ON rt.rty_ID = rl.rel_TypeID
        JOIN Records r ON r.rec_ID = CASE WHEN rl.rel_SourceID = :id THEN rl.rel_TargetID ELSE rl.rel_SourceID END
        WHERE rl.rel_SourceID = :id OR rl.rel_TargetID = :id
        ORDER BY rl.rel_ID
        LIMIT :lim
    SQL;
    $st = $pdo->prepare($sql);
    $st->bindValue(':id',  $recId, PDO::PARAM_INT);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    $rels = [];
    foreach ($st as $r) {
        $rels[] = [
            'type'  => $r['rel_type'],
            'other' => ['id'=>(int)$r['other_id'], 'title'=>$r['other_title']],
            'relId' => (int)$r['rel_ID'],
        ];
    }
    return $rels;
}

/** Reverse pointers (who points at me?) in one query. */
function hr_fetch_reverse_pointers(PDO $pdo, int $recId, int $limit = 200): array {
    $sql = <<<SQL
        SELECT d.dtl_RecID AS other_id, r.rec_Title AS other_title, d.dtl_FieldID
        FROM recDetails d
        JOIN defFields f ON f.fld_ID = d.dtl_FieldID
        JOIN Records r   ON r.rec_ID = d.dtl_RecID
        WHERE f.fld_Type IN ('resource','pointer') AND d.dtl_Value = :id
        ORDER BY d.dtl_RecID
        LIMIT :lim
    SQL;
    $st = $pdo->prepare($sql);
    $st->bindValue(':id',  $recId, PDO::PARAM_INT);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    $list = [];
    foreach ($st as $r) {
        $list[] = [
            'id'    => (int)$r['other_id'],
            'title' => $r['other_title'],
            'field' => (int)$r['dtl_FieldID'],
        ];
    }
    return $list;
}

/**
 * If you prefer a SINGLE query that already has term labels and pointer titles,
 * use this alternative and skip the batched lookups. Simple and very fast.
 */
function hr_fetch_details_joined_onequery(PDO $pdo, int $recId): array {
    $sql = <<<SQL
        SELECT
          d.*,
          f.fld_Name, f.fld_Type, f.fld_Json,
          CASE WHEN f.fld_Type IN ('term','enum') THEN t.trm_Label ELSE NULL END AS term_label,
          CASE WHEN f.fld_Type IN ('resource','pointer') THEN p.rec_ID ELSE NULL END AS ptr_id,
          CASE WHEN f.fld_Type IN ('resource','pointer') THEN p.rec_Title ELSE NULL END AS ptr_title
        FROM recDetails d
        JOIN defFields f ON f.fld_ID = d.dtl_FieldID
        LEFT JOIN defTerms t ON (f.fld_Type IN ('term','enum') AND t.trm_ID = d.dtl_Value)
        LEFT JOIN Records  p ON (f.fld_Type IN ('resource','pointer') AND p.rec_ID = d.dtl_Value)
        WHERE d.dtl_RecID = :id
        ORDER BY d.dtl_Order, d.dtl_ID
    SQL;
    $st = $pdo->prepare($sql);
    $st->execute([':id'=>$recId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Group details by field for display, keeping original order. */
function hr_group_details_by_field(array $rows): array {
    $by = [];
    foreach ($rows as $r) {
        $fid = (int)$r['dtl_FieldID'];
        if (!isset($by[$fid])) {
            $by[$fid] = [
                'field'  => [
                    'id'   => $fid,
                    'name' => $r['fld_Name'] ?? ('Field '.$fid),
                    'type' => $r['fld_Type'] ?? 'text',
                    'json' => $r['fld_Json'] ?? null,
                ],
                'values' => [],
            ];
        }
        $by[$fid]['values'][] = $r;
    }
    return $by;
}

?>