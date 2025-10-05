<?php

/**
 * viewRecord.php (unchanged UI; batched data)
 *
 * This file remains the entry point that assembles the page. We keep your
 * existing includes for header/footer/JS; the only change is that we now
 * include renderRecordData.php which fetches data in batched queries and
 * renders the same HTML structure.
 */

// --- Project bootstrap (keep your originals here) -----------------------------
// Examples; adapt to your project:
// require_once __DIR__.'/../../common/connect/applyCredentials.php';
// require_once __DIR__.'/../include/html/header.php';

// If your bootstrap already outputs <html>… etc., keep it. Otherwise:
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Record Viewer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Keep your existing CSS/JS includes here -->
  <!-- <link rel="stylesheet" href="record.css"> -->
  <!-- <script src="record.js" defer></script> -->
  <style>
    /* Minimal safety styles; your existing stylesheet will override */
    .h-record-view { max-width: 1100px; margin: 0 auto; padding: 1rem; }
    .h-field { border-bottom: 1px solid #eee; padding: .5rem 0; }
    .h-field-label { font-weight: 600; margin-bottom: .25rem; }
    .h-field-values .h-pointer { text-decoration: none; }
    .h-empty { opacity: .6; }
  </style>
</head>
<body>

<?php
// Delegate the heavy lifting (and rendering) to renderRecordData.php.
// It will read recID from the query string, do batched queries, and print HTML.
require_once __DIR__ . '/renderRecordData.php';
?>

</body>
</html>


