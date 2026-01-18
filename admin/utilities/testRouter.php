<?php
/**
* testRouter.php
*
* @fileOverview test harness (CLI PHP) that runs a list of sample URLs through 
* the router and prints the resulting target + params
*
* @project     Heurist academic knowledge management system
* @package Admin
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       7.0
*/
// admin/utilities/testRouter.php

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../../autoload.php';
use hserv\controller\RequestRouter;

$docRoot = '/var/www/html'; // adjust if needed
$docRoot = 'c:/xampp/htdocs'; 
$mappingFile = $docRoot . '/HEURIST/domainWebsites.json';
$mappingFile = $docRoot . '/domainWebsites.json';

$samples = [
  // parameterized
  ['host' => 'example.com', 'uri' => '/heurist/?db=database_name&website=1'],

  //['host' => 'example.com', 'uri' => '/heurist/hserv/record_search.php?db=database_name&q=ids:123'],
  
  // versionless db short
  ['host' => 'example.com', 'uri' => '/database_name'],

  // pretty website route
  ['host' => 'example.com', 'uri' => '/database_name/web/68/179'],

  // versioned api
  ['host' => 'example.com', 'uri' => '/h7-alpha/api/some/endpoint?x=1'],

  // /db resolvers
  ['host' => 'example.com', 'uri' => '/db/record/2312-123'],
  ['host' => 'example.com', 'uri' => '/db/trm/1376/10?fmt=json'],
  ['host' => 'example.com', 'uri' => '/db/file/abc123'],

  // DBREF example
  ['host' => 'example.com', 'uri' => '/MBH/web/1408/4'],

  // own-domain mapped
  ['host' => 'parramattafoodcultures.net', 'uri' => '/'],
  ['host' => 'parramattafoodcultures.net', 'uri' => '/179'],

  // subdomain mapped numeric pair
  ['host' => 'mbh.example.com', 'uri' => '/1408/4'],
];

foreach ($samples as $i => $s) {
    $server = [
        'DOCUMENT_ROOT' => $docRoot,
        'HTTP_HOST' => $s['host'],
        'REQUEST_URI' => $s['uri'],
        'HTTP_ACCEPT' => $s['accept'] ?? 'text/html',
        'REQUEST_METHOD' => 'GET',
    ];

    $res = RequestRouter::resolve($server, [
        'default_version' => 'heurist',
        'mapping_file' => $mappingFile,
        'allow_canonical_redirects' => false,
    ]);

    echo "=== Sample #" . ($i+1) . " ===\n";
    echo "HOST: {$s['host']}\n";
    echo "URI : {$s['uri']}\n";
    echo "MODE: {$res['mode']}  STATUS: {$res['status']}\n";
    if (!empty($res['location'])) {
        echo "LOCATION: {$res['location']}\n";
    }
    if (!empty($res['script'])) {
        echo "SCRIPT: {$res['script']}\n";
    }
    echo "PARAMS:\n";
    foreach (($res['params'] ?? []) as $k => $v) {
        if (is_array($v)) $v = json_encode($v);
        echo "  - {$k} = {$v}\n";
    }
    echo "\n";
}
