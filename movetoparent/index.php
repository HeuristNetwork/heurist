<?php
/**
* Root entrypoint (webroot /index.php)
*
* PURPOSE
* ---
* This file is the single PHP entrypoint for the whole installation.
* It replaces the old mix of Apache RewriteRules, resolver.php, and CloudFlare workers.
*
* Apache rewrites (almost) all requests to this script. The router then decides whether to:
* * redirect to a canonical URL (preferred for record export/edit/view, file delivery, etc.)
* * serve a static welcome page
* * include a version-specific entry script (/<version>/index.php) for UI flows
*
*
* APACHE CONFIGURATION
* ---
* Place these rules in the VirtualHost config (preferred) or .htaccess (if AllowOverride enabled).
* IMPORTANT: Keep the "real files/dirs bypass" first, otherwise CSS/JS/images will be routed through PHP.
*
* RewriteEngine On
*
* # 0) Do not rewrite existing files or directories
* RewriteCond %{REQUEST_FILENAME} -f [OR]
* RewriteCond %{REQUEST_FILENAME} -d
* RewriteRule ^ - [L]
*
* # 1) Send all other requests to the root router
* RewriteRule ^.*$ /index.php [L,QSA]
*
* Notes:
* * This supports pretty URLs for ALL versions:
*   /<db>/web/...,
*   /<version>/<db>/web/...,
*   /db/record/...,
*   /<version>/api/...,
*   and own-domain mappings (domainWebsites.json).
*
* * If you temporarily remove these rewrite rules, direct legacy requests to /<version>/index.php?... still work
* because version/index.php keeps a small compatibility surface (isalive, file, asset, logo, disclaimer, etc).
*
*
* DIRECTORY / FILE LAYOUT EXPECTATIONS
* ---
* Webroot: /var/www/html  (example)
*
* /index.php                         (this file)
* /index.html                        (welcome page)
* /errors/...                        (shared static assets and error pages)
*
* /heurist/...                        (production code)
* /h7-alpha/...                       (alpha code)
* /h7-xxx/...                         (other versions)
*
* /HEURIST/domainWebsites.json        (domain -> db/website mapping, DBREF mapping)
*
*
* ROUTING FLOW
* ---
* 1. RequestRouter::route() returns a plan:
* 
*   - mode=REDIRECT   -> send Location immediately
*   - mode=INTERNAL   -> continue below
*   - mode=NOT_FOUND  -> render 404
*
* 2. If INTERNAL:
*
*   - Attempt RecordResolver and FileResolver.
*     These convert "meaning" (params) into canonical, VERSIONED script URLs and return a redirect URL.
*     We prefer redirects here because the legacy scripts behave most reliably when executed as entry scripts.
*
* 3. If neither resolver applies:
*   - Include the selected version entrypoint at TOP-LEVEL scope:
*       /<version>/index.php
*   Running version/index.php at top-level is critical because legacy code uses global variables and defines.
*
*
* WHY WE CHDIR BEFORE REQUIRE
* ---
* Many legacy includes use relative paths (e.g. "hserv/consts.php").
* When root/index.php requires /<version>/index.php, PHP's working directory stays at webroot by default.
* We temporarily chdir() into the target script folder so those relative includes keep working.
*
*
* CANONICAL URL RULES (SUMMARY)
* ---
* * /<db>                 -> 302 to /<db>/web/
* * /<version>/<db>       -> 302 to /<version>/<db>/web/
* * /db/...               -> mapped to params, then RecordResolver/FileResolver may redirect to scripts
* * /<version>/api/...    -> routed to /<version>/hserv/controller/api.php (entry script)
*
* * Unknown host + "/"    -> serve /index.html (welcome)
* * "/<db>/<unknown>"     -> 404 (prevents accidental fall-through into UI)
*
*
* HOW TO DEBUG
* ---
* Routing can be debugged by adding the parameter:
*
*
*  ?routerDebug=1
*
*
* to any request URL.
*
* When enabled, the router will output a structured diagnostic report instead of executing
* redirects or including scripts. This shows:
* - detected host and version
* - parsed path segments
* - routing decision (REDIRECT / INTERNAL / NOT_FOUND)
* - target script or redirect URL
* - resolved parameters passed to the application
*
* This mode is intended for developers only and should not be enabled in production links.
*/

 $defaultVersion = 'heurist';
 $codePath = '/HEURIST/'; // or /
 
require_once __DIR__ . $codePath . $defaultVersion . '/hserv/controller/RequestRouter.php';
require_once __DIR__ . $codePath . $defaultVersion . '/hserv/controller/RecordResolver.php';
require_once __DIR__ . $codePath . $defaultVersion . '/hserv/controller/FileResolver.php';

use hserv\controller\RequestRouter;
use hserv\controller\RecordResolver;
use hserv\controller\FileResolver;

$route = RequestRouter::route([
    'default_version' => $defaultVersion,
    'mapping_file' => __DIR__ . $codePath . 'domainWebsites.json',
    'allow_canonical_redirects' => true,
]);

if (!empty($_GET['routerDebug'])) {
    header('Content-Type: text/plain; charset=utf-8');
    print_r($route);
    exit;
} 

if (($route['mode'] ?? '') === 'REDIRECT') {
    header('Location: ' . $route['location'], true, (int)($route['status'] ?? 302));
    exit;
}

if (($route['mode'] ?? '') === 'NOT_FOUND') {
    http_response_code(404);
    $err = __DIR__ . '/errors/404.html';
    if (is_file($err)) readfile($err); else echo '404 Not Found';
    exit;
}

$script = $route['script'] ?? null;
$params = $route['params'] ?? [];

// Ensure router params are visible to legacy code
$GLOBALS['HEURIST_ROUTE_PARAMS'] = $params;
foreach ($params as $k => $v) {
    $_REQUEST[$k] = $v;
}

// Determine version for canonical redirects: preserve version prefix when present
$version = $route['version'] ?? null;
if (!$version) {
    // Best effort: infer from script path (/.../<version>/index.php)
    if (is_string($script) && preg_match('~/([^/]+)/index\.php$~', str_replace('\\', '/', $script), $m)) {
        $version = $m[1];
    } else {
        $version = $defaultVersion;
    }
}

// Resource endpoints: redirect to concrete scripts (most reliable)
$serverRoot = __DIR__;
if ($r = RecordResolver::resolve($version, $_REQUEST, $serverRoot)) {
    header('Location: ' . $r['url'], true, (int)($r['status'] ?? 302));
    exit;
}
if ($r = FileResolver::resolve($version, $_REQUEST)) {
    header('Location: ' . $r['url'], true, (int)($r['status'] ?? 302));
    exit;
}

// Finally, run the selected version index.php (UI flows)
if (!$script || !is_file($script)) {
    http_response_code(500);
    echo 'Router target missing';
    exit;
}

$GLOBALS['HEURIST_ROUTED_VIA_ROOT'] = true;

$oldCwd = getcwd();
@chdir(dirname($script));
require $script;
@chdir($oldCwd);

