<?php
require_once __DIR__ . '/autoload.php';

use hserv\controller\RequestRouter;

// Choose mapping file explicitly to avoid DOCUMENT_ROOT ambiguity
$mappingFile = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/HEURIST/domainWebsites.json';

RequestRouter::dispatch([
  'default_version' => 'heurist',
  'mapping_file' => $mappingFile,
  'allow_canonical_redirects' => false // turn on later if you decide to canonicalize
]);
