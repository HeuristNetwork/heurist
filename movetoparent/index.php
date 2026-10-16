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
 // Default version used when no version is specified in the URL.
 $defaultVersion = 'heurist';
 // Base path containing all versioned code directories.
 $codePath = '/'; // or /HEURIST 

// Load the PHP class files with core routing components.
// These classes implement the routing and resolution logic used below.
require_once __DIR__ . $codePath . $defaultVersion . '/hserv/controller/RequestRouter.php';
require_once __DIR__ . $codePath . $defaultVersion . '/hserv/controller/RecordResolver.php';
require_once __DIR__ . $codePath . $defaultVersion . '/hserv/controller/FileResolver.php';

// Import class names into the current namespace letting us refer to the classes by their short names below.
use hserv\controller\RequestRouter;
use hserv\controller\RecordResolver;
use hserv\controller\FileResolver;

// Execute routing: analyse the incoming request (host name, path, and query string) 
// and return a routing decision structure.

$route = RequestRouter::route([
    // Tell the router which version to assume when none is stated in the URL.
    'default_version' => $defaultVersion,
    // JSON file containing custom domain and DB mappings.
    // This lets the router turn incoming host names into the right database / website target.
    'mapping_file' => __DIR__ . $codePath . 'domainWebsites.json',
    // Allow the router to send the browser to a cleaner or more standard URL.
    'allow_canonical_redirects' => true,
]);

// Debug mode for developers: output routing decision and stop normal execution.
// If the URL contains ?routerDebug=1, do not continue with normal execution.
// Instead, print the routing decision as plain text so a developer can inspect it.
if (!empty($_GET['routerDebug'])) {
    header('Content-Type: text/plain; charset=utf-8');
    print_r($route);
    exit;
} 

// If the router decided this request should be redirected,
// send an HTTP Location header back to the browser and stop.
if (($route['mode'] ?? '') === 'REDIRECT') {
    header('Location: ' . $route['location'], true, (int)($route['status'] ?? 302));
    exit;
}

// If the router concluded that nothing valid matches this request,
// return an HTTP 404 response and show a static error page if available.
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
// Some request types are better handled by redirecting the browser to the exact legacy script
// that already knows how to serve that kind of resource.
// This preserves older behaviour and reduces the risk of subtle breakage.
$serverRoot = __DIR__;
if ($r = RecordResolver::resolve($version, $_REQUEST, $serverRoot)) {
    /*if(!empty($r['error'])){
        RecordResolver::renderResolverError($r['error'], $_REQUEST);
    }*/
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

// This global flag tells downstream code that execution came through the root router.
// Legacy code can use this to adjust behaviour if needed.
$GLOBALS['HEURIST_ROUTED_VIA_ROOT'] = true;

$oldCwd = getcwd();
@chdir(dirname($script));
require $script;
@chdir($oldCwd);

