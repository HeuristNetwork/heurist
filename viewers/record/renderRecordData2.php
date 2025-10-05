<?php
 
/**
 * renderRecordData.php (batched-queries version)
 *
 * Goal: keep existing viewer layout, styles and JS,
 *       but replace N+1 lookups with a handful of batched queries.
 *
 * Assumptions:
 *  - A PDO handle $pdo is available after your bootstrap.
 *  - If you only have mysqli, create a PDO once in your bootstrap (see lib header).
 */

declare(strict_types=1);

// --- Bootstrap / includes -----------------------------------------------------
$__base = __DIR__;
require_once $__base . '/lib/record_batch_fetch.php';

// If your project sets up $pdo elsewhere, remove this guard.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Attempt a very conservative fallback if constants exist; else fail clearly.
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASSWORD')) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER,
            DB_PASSWORD,
            [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
              PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ]
        );
    } else {
        // You can safely replace the next line with your project's connector.
        throw new RuntimeException('PDO $pdo not available. Please initialize a PDO connection before including renderRecordData.php');
    }
}

// --- Input -------------------------------------------------------------------
$recId   = (int)($_GET['recID'] ?? $_POST['recID'] ?? 0);
$section = $_GET['section'] ?? ''; // optional: 'reverse' to render only reverse pointers
if ($recId <= 0) {
    http_response_code(400);
    echo '<div class="error">Missing or invalid recID</div>';
    exit;
}

// --- Data assembly (simple, stepwise) ----------------------------------------

// 1) All details + field defs (ONE query)
$detailRows = hr_fetch_details_with_defs($pdo, $recId);

// 2) Collect unique term IDs and pointer IDs for bulk resolution
$termIds = [];
$ptrIds  = [];
foreach ($detailRows as $row) {
    $type = $row['fld_Type'] ?? 'text';
    if (($type === 'term' || $type === 'enum') && $row['dtl_Value'] !== null) {
        $termIds[(int)$row['dtl_Value']] = true;
    } elseif (($type === 'resource' || $type === 'pointer') && $row['dtl_Value'] !== null) {
        $ptrIds[(int)$row['dtl_Value']] = true;
    }
}
$termIds = array_keys($termIds);
$ptrIds  = array_keys($ptrIds);

// 3) Resolve each set in ONE query per set
$termLabels = hr_fetch_term_labels($pdo, $termIds);
$ptrTitles  = hr_fetch_record_titles($pdo, $ptrIds);

// 4) Relationships (ONE query). Limit modestly to protect worst cases.
$relationships = hr_fetch_relationships($pdo, $recId, 200);

// 5) Apply ordering (keep SQL order by dtl_Order,dtl_ID) and materialise values
$grouped = hr_group_details_by_field($detailRows);

$fields = [];
foreach ($grouped as $g) {
    $f = $g['field'];
    $vals = [];
    foreach ($g['values'] as $row) {
        switch ($f['type']) {
            case 'term':
            case 'enum':
                $vals[] = $termLabels[(int)$row['dtl_Value']] ?? $row['dtl_Value'];
                break;
            case 'resource':
            case 'pointer':
                $rid = (int)$row['dtl_Value'];
                $vals[] = ['id'=>$rid, 'title'=>$ptrTitles[$rid] ?? null];
                break;
            case 'geo':
                $vals[] = $row['dtl_Geo'];
                break;
            case 'date':
                $vals[] = ($row['dtl_UValue'] ?: $row['dtl_Value']);
                break;
            default:
                $vals[] = $row['dtl_Value'];
        }
    }
    $fields[] = [
        'id'     => $f['id'],
        'name'   => $f['name'],
        'type'   => $f['type'],
        'values' => $vals,
        'json'   => $f['json'],
    ];
}

// 6) Reverse pointers can be deferred; we populate them now but you may
//    choose to render this as a collapsed <details> block.
$reversePointers = hr_fetch_reverse_pointers($pdo, $recId, 200);

// Early-out mode: if asked for just reverse section (optional tiny optimization)
if ($section === 'reverse') {
    foreach ($reversePointers as $rp) {
        printf(
            '<div class="h-related h-reverse"><a href="?recID=%d">%s</a></div>',
            $rp['id'],
            htmlspecialchars($rp['title'] ?? (string)$rp['id'])
        );
    }
    exit;
}

// --- Rendering (keeps your existing styling/classes) -------------------------
//
// The HTML below uses neutral class names likely already present in your CSS.
// If your template system injects headers/footers/scripts, keep them as-is.
// You can also move this block back into viewRecord.php and leave this file
// responsible only for assembling $fields/$relationships/$reversePointers/$title.
//

$title = hr_fetch_record_title($pdo, $recId) ?? ('Record '.$recId);

// Wrap in a container that your existing CSS targets.
?>
<div id="record-view" class="h-record-view">
  <header class="h-record-header">
    <h1 class="h-record-title"><?php echo htmlspecialchars($title); ?></h1>
  </header>

  <section class="h-record-fields">
    <?php foreach ($fields as $f): ?>
      <div class="h-field h-type-<?php echo htmlspecialchars($f['type']); ?>">
        <div class="h-field-label"><?php echo htmlspecialchars($f['name']); ?></div>
        <div class="h-field-values">
          <?php
          if (empty($f['values'])) {
              echo '<span class="h-empty">—</span>';
          } else {
              foreach ($f['values'] as $v) {
                  switch ($f['type']) {
                      case 'resource':
                      case 'pointer':
                          $rid = (int)$v['id'];
                          $ttl = $v['title'] ?? $rid;
                          printf('<a class="h-pointer" href="?recID=%d">%s</a> ', $rid, htmlspecialchars((string)$ttl));
                          break;
                      case 'geo':
                          echo '<code class="h-geo">'.htmlspecialchars(is_string($v) ? $v : json_encode($v)).'</code> ';
                          break;
                      default:
                          echo '<span class="h-value">'.htmlspecialchars((string)$v).'</span> ';
                  }
              }
          }
          ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="h-record-relationships">
    <h2 class="h-section-title">Related</h2>
    <?php if (empty($relationships)): ?>
      <div class="h-empty">None</div>
    <?php else: ?>
      <?php foreach ($relationships as $rel): ?>
        <div class="h-related">
          <span class="h-rel-type"><?php echo htmlspecialchars($rel['type']); ?>:</span>
          <a class="h-rel-link" href="?recID=<?php echo (int)$rel['other']['id']; ?>">
            <?php echo htmlspecialchars($rel['other']['title'] ?? (string)$rel['other']['id']); ?>
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="h-record-reverse">
    <details id="h-revptr">
      <summary>Reverse links (<?php echo count($reversePointers); ?>)</summary>
      <div class="h-revptr-content">
        <?php foreach ($reversePointers as $rp): ?>
          <div class="h-related h-reverse">
            <a href="?recID=<?php echo (int)$rp['id']; ?>">
              <?php echo htmlspecialchars($rp['title'] ?? (string)$rp['id']); ?>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  </section>
</div>

<script>
/* Optional: truly lazy-load reverse links with a tiny fetch of this same file.
   This keeps behaviour identical but avoids any JS framework changes. */
(function(){
  const box = document.querySelector('#h-revptr');
  if(!box) return;
  let loaded = !!box.querySelector('.h-revptr-content')?.children.length;
  box.addEventListener('toggle', async () => {
    if (loaded || !box.open) return;
    loaded = true;
    const url = new URL(window.location.href);
    url.searchParams.set('section', 'reverse');
    const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'fetch' }});
    if (res.ok) {
      const html = await res.text();
      box.querySelector('.h-revptr-content').innerHTML = html;
    }
  }, { once: true });
})();
</script>

