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
$codePath = '/HEURIST/'; // or /
 
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
    // Send an HTTP header telling the browser this is plain text, not HTML.
    header('Content-Type: text/plain; charset=utf-8');
    // simple PHP debugging function dumps arrays in readable form.
    print_r($route);
    // Stop here so no redirect, resolver, or application code runs afterward.
    exit;
} 

// If the router decided this request should be redirected,
// send an HTTP Location header back to the browser and stop.
if (($route['mode'] ?? '') === 'REDIRECT') {
    // The browser receives this and immediately makes a new request to the given URL.
    // 302 is the normal temporary redirect status unless another one was supplied.
    header('Location: ' . $route['location'], true, (int)($route['status'] ?? 302));
    exit;
}

// If the router concluded that nothing valid matches this request,
// return an HTTP 404 response and show a static error page if available.
if (($route['mode'] ?? '') === 'NOT_FOUND') {
    http_response_code(404);
    $err = __DIR__ . '/errors/404.html';
    if (is_file($err)) readfile($err); else echo '404 Not Found'; // fallback if error file not found
    exit;
}

// INTERNAL mode continues here: execute or refine the resolved request.
// Instead of redirecting we continue server-side.

// Target script selected by the router, usually something like /<version>/index.php
$script = $route['script'] ?? null;

// Extra parameters the router derived from the pretty URL.
// These may not have existed in the original query string, so we inject them below.
$params = $route['params'] ?? [];

// Expose router parameters to legacy code via globals and $_REQUEST.
// Legacy code may expect values to be in global places such as $_REQUEST,
// rather than being passed cleanly as function arguments.
// So we copy the router's parsed parameters into those older shared locations.
$GLOBALS['HEURIST_ROUTE_PARAMS'] = $params;
foreach ($params as $k => $v) {
    // $_REQUEST is a standard PHP superglobal that older code often reads from.
    // By copying values in here, old code can behave as though the user originally
    // requested these parameters directly.
    $_REQUEST[$k] = $v;
}

// Determine version for canonical redirects: preserve version prefix when present
// The router may already have told us which code version is in play.
// If not, we try to infer it from the target script path.
$version = $route['version'] ?? null;
if (!$version) {
    // Best effort: infer from script path (/.../<version>/index.php)
    // preg_match tries to pull the folder name immediately before "/index.php".
    if (is_string($script) && preg_match('~/([^/]+)/index\.php$~', str_replace('\\', '/', $script), $m)) {
        // If the pattern matched, the version name is in $m[1].
        $version = $m[1];
    } else {
        // Final fallback: use the default version.
        $version = $defaultVersion;
    }
}

// Resource endpoints: redirect to concrete scripts (most reliable)
// Some request types are better handled by redirecting the browser to the exact legacy script
// that already knows how to serve that kind of resource.
// This preserves older behaviour and reduces the risk of subtle breakage.
$serverRoot = __DIR__;
if ($r = RecordResolver::resolve($version, $_REQUEST, $serverRoot)) {
    // If RecordResolver understands this request, it returns redirect info. Send and stop.
    header('Location: ' . $r['url'], true, (int)($r['status'] ?? 302));
    exit;
}

// Same idea for file requests such as delivered files or related assets.
if ($r = FileResolver::resolve($version, $_REQUEST)) {
    header('Location: ' . $r['url'], true, (int)($r['status'] ?? 302));
    exit;
}

// Fallback: execute the selected version entrypoint: run the selected version index.php (UI flows)
// If no resolver claimed the request, we fall back to running the main entry script
// for the selected Heurist version.
if (!$script || !is_file($script)) {
    // This is a server-side problem, not a user "not found" problem.
    // The router said there should be a target script, but it is missing.
    http_response_code(500);
    echo 'Router target missing';
    exit;
}

// This global flag tells downstream code that execution came through the root router.
// Legacy code can use this to adjust behaviour if needed.
$GLOBALS['HEURIST_ROUTED_VIA_ROOT'] = true;


// Save the current working directory so it can be restored afterward.
// The current working directory is the "base folder" PHP uses for many relative paths.
$oldCwd = getcwd();

// Switch temporarily to target script directory for compatibility with relative includes.
// This helps older include statements that assume they are being run from that folder.
@chdir(dirname($script));

// require actually executes the target PHP file here.
// In effect, control is now handed over to that version's own index.php.
require $script;

// Restore original working directory.
@chdir($oldCwd);
