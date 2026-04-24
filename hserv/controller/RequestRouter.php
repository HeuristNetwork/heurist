<?php
namespace hserv\controller;

/**
 * Central request router for Heurist (single-logic design).
 *
 * - route(): PURE planning. Computes a RouteResult (no headers, no include, no exit).
 * - dispatch(): executes the RouteResult (redirect/include/404) and exits.
 *
 * RouteResult shape:
 * [
 *   'mode'     => 'INTERNAL'|'REDIRECT'|'NOT_FOUND',
 *   'status'   => 200|302|404,
 *   'script'   => '/abs/path/to/script.php'|null,
 *   'location' => '/heurist/?db=...'|null,
 *   'params'   => [ 'db' => '...', ... ]   // merged into $_REQUEST on INTERNAL dispatch
 * ]
 * 
 * This class:
 *   - resolves host-based websites (own domains) + DBREF db aliases
 *   - resolves /db/* record/definition/file requests
 *   - resolves pretty URLs into the legacy parameterized request model
 *   - dispatches internally by including the correct script (default)
 *   - can optionally redirect to canonical URLs when allowed
 */
final class RequestRouter
{
    /** @var array|null */
    private static $mapping = null;
    /** @var int|null */
    private static $mappingMTime = null;

    // Adjust to add more versions
    private const ALLOWED_VERSIONS = ['heurist','h7-alpha','h7-ao','h7-bm','h7-hn'];

    // Actions supported in pretty routes
    private const ALLOWED_ACTIONS = ['website','web','hml','tpl','view','edit','adm','record'];

    // Content negotiation for /db/* when fmt not provided
    private const REQUEST_CONTENT = [
        'xml'  => 'text/xml',
        'hml'  => 'application/hml+xml',
        'json' => 'application/json',
        'rdf'  => 'application/rdf+xml',
        'html' => 'text/html',
    ];

    /**
     * PURE: compute routing result based on current globals ($_SERVER).
     *
     * @param array $options
     *   - default_version (string) default "heurist"
     *   - mapping_file (string) absolute path to domainWebsites.json
     *   - allow_canonical_redirects (bool) default false
     *   - reserved_paths (array) optional override
     */
    public static function route(array $options = []): array
    {
        $defaultVersion = $options['default_version'] ?? 'heurist';
        $allowRedirects = (bool)($options['allow_canonical_redirects'] ?? false);

        $mappingFile = $options['mapping_file'] ?? self::defaultMappingFile();
        $mapping = self::loadMapping($mappingFile);

        $host = self::getHost();
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = self::stripQueryString($uri);
        $segments = self::splitPath($path);

        // Host mapping (own domain)
        $hostMap = $mapping['domains'][strtolower($host)] ?? null;
        $mustPreserveHost = $hostMap !== null;

        $mappedDb      = $hostMap['db'] ?? null;
        $mappedWebsite = $hostMap['website'] ?? null;
        $mappedVersion = $hostMap['version'] ?? null;

        // Detect version prefix
        $versionPrefix = null;
        if (!empty($segments) && in_array($segments[0], self::ALLOWED_VERSIONS, true)) {
            $versionPrefix = array_shift($segments);
        }
        $activeVersion = $mappedVersion ?: ($versionPrefix ?: $defaultVersion);

        
        // Normalize "/index.php" (and optionally "/index.html") to "/"
        if (count($segments) === 1) {
            $s0 = strtolower($segments[0]);
            if ($s0 === 'index.php' || $s0 === 'index.html') {
                $segments = [];
            }
        }        
        
        // If host is not mapped and request is root "/", show welcome page
        if ($hostMap === null && empty($segments)) {
            $welcome = self::serverRoot() . "/index.html";
            if (is_file($welcome)) {
                return self::resultInternal($welcome, []);
            }
            // fallback: go to startup
            return self::resultRedirect("/{$activeVersion}/startup/", 302, []);
            //or 404 self::result404();
        }        
        
        $query = self::queryParamsFromRequestUri();

        // Parameterized entry: /<version>/?db=...
        if (!empty($query['db']) && !self::isApiRoute($segments)) {
            // normalize / apply DBREF if you want:
            $dbResolved = self::applyDbRef($mapping, (string)$query['db']);
            $query['db'] = $dbResolved;

            $script = self::serverRoot() . "/{$activeVersion}/index.php";
            return self::resultInternal($script, $query);
        }        
        
        // Reserved paths (either from options or mapping file)
        $reserved = $options['reserved_paths']
            ?? ($mapping['reserved_paths'] ?? ['heurist','h7-alpha','h7-ao','h7-bm','startup','matomo','errors','db','api']);

        // NOTE: route() does not attempt to "serve" physical files; Apache should bypass router for -f/-d.
        // But even if it doesn't, we keep safe fallbacks.

        // ---- 1) Startup
        // Canonicalise /<version>/startup/index.php -> /<version>/startup/
        // (startup expects directory-style URL for PDIR logic)
        if (self::isStartupRoute($segments)) {
            $q  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
            $qs = $q ? ('?' . $q) : '';
            return self::resultRedirect("/{$activeVersion}/startup/{$qs}", 302, []);
        }

        // ---- 2) API
        // IMPORTANT: API must be executed as an entry script (do not go via index.php)
        if (self::isApiRoute($segments)) {
            $script = self::serverRoot() . "/{$activeVersion}/hserv/controller/api.php";
            return self::resultInternal($script, $query);
        }

        // ---- 3) /db/* pretty namespace (record/def/file)
        // Map /db/* URLs into canonical params, then continue to version/index.php.
        if (self::isDbResolverRoute($segments)) {
            $params = self::paramsFromDbResolverPath($segments);

            // /db/file/<id> should behave like old resolver.php: mode=page
            if (!empty($params['file']) && empty($params['mode'])) {
                $params['mode'] = 'page';
            }

            // Query wins: only set fmt if missing from query AND missing from path-derived params
            // (negotiateFmtIfMissing also checks $_GET['fmt'] if your implementation includes that)
            $params = self::negotiateFmtIfMissing($params);

            // Query wins over path-derived params
            $params = array_merge($params, $query);

            $script = self::serverRoot() . "/{$activeVersion}/index.php";
            return self::resultInternal($script, $params);
        }

        // ---- 4) Own-domain website handling (must preserve host -> never redirect)
        if ($mustPreserveHost) {
            return self::routeOwnDomain($activeVersion, $mappedDb, $mappedWebsite, $segments);
        }

        // ---- 5) Version root (/<version>)
        if ($versionPrefix !== null && empty($segments)) {
            return self::resultInternal(self::serverRoot() . "/{$activeVersion}/index.php", []);
        }

        // ---- 6) Empty path (rare if root is rewritten): fall into app
        if (empty($segments)) {
            return self::resultInternal(self::serverRoot() . "/{$activeVersion}/index.php", []);
        }

        // ---- 7) Versionless /<db> and /<db>/<action>/...
        $dbCandidate = $segments[0];

        // Avoid treating reserved prefixes as db
        if (in_array($dbCandidate, $reserved, true)) {
            return self::resultInternal(self::serverRoot() . "/{$activeVersion}/index.php", []);
        }

        if (!self::isValidDbToken($dbCandidate)) {
            return self::result404();
        }

        // Apply DBREF (MBH -> Manuscripta_Bibliae_Hebraicae)
        $dbResolved = self::applyDbRef($mapping, $dbCandidate);
        $defaultWebsite = self::getDefWebsite($mapping, $dbCandidate);

        // /<db>  => canonical redirect to /<db>/web/ (or /<version>/<db>/web/ if version prefix was used)
        if (count($segments) === 1) {
            // Own-domain mapped hosts must preserve host and generally treat "/" as homepage.
            // For unmapped hosts, canonicalize to a pretty /web/ URL.
            if ($mustPreserveHost) {
                $params = ['db' => $dbResolved, 'website' => (int)($mappedWebsite ?? 0)];
                return self::resultInternal(self::serverRoot() . "/{$activeVersion}/index.php", array_merge($params, $query));
            }

            $q  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
            $qs = $q ? ('?' . $q) : '';

            if ($versionPrefix !== null) {
                return self::resultRedirect("/{$versionPrefix}/{$dbCandidate}/web/{$qs}", 302, []);
            }
            return self::resultRedirect("/{$dbCandidate}/web/{$qs}", 302, []);
        }

        // /<db>/<action>/...
        $action = $segments[1] ?? null;
        if ($action !== null && in_array($action, self::ALLOWED_ACTIONS, true)) {
            $params = self::paramsFromPrettyRoute($dbResolved, $action, array_slice($segments, 2), $defaultWebsite);
            // Query wins over path-derived params
            $params = array_merge($params, $query);
            return self::resultInternal(self::serverRoot() . "/{$activeVersion}/index.php", $params);
        }

        // If it looks like /<db>/<something> and <something> is not a known action, treat as 404.
        return self::result404();
        // Fallback: let app decide (db browser / 404 / etc.)
        //return self::resultInternal(self::serverRoot() . "/{$activeVersion}/index.php", []);
        
    }

    /**
     * Execute the route (side effects happen here only).
     */
    public static function dispatch(array $options = []): void
    {
        $res = self::route($options);
        
        if (!empty($_GET['routerDebug'])) {
            header('Content-Type: text/plain; charset=utf-8');
            print_r($res);
            exit;
        }        

        switch ($res['mode']) {
            case 'REDIRECT':
                header('Location: ' . $res['location'], true, $res['status'] ?? 302);
                exit;

            case 'NOT_FOUND':
                http_response_code(404);
                $err = self::serverRoot() . "/errors/404.html";
                if (is_file($err)) {
                    readfile($err);
                } else {
                    echo "404 Not Found";
                }
                exit;

            case 'INTERNAL':
            default:
                $GLOBALS['HEURIST_ROUTE_PARAMS'] = $res['params'] ?? [];
                foreach (($res['params'] ?? []) as $k => $v) {
                    $_REQUEST[$k] = $v;    
                }

                $script = $res['script'];
                if (!is_file($script)) {
                    http_response_code(404);
                    echo "Router target not found: " . htmlspecialchars($script, ENT_QUOTES, 'UTF-8');
                    exit;
                }
                require $script;
                exit;
        }
    }

    /**
    * wrapper around route() to be called in testRouter.php
    */
    public static function resolve(array $server, array $options = []): array
    {
        $serverBak  = $_SERVER;
        $getBak     = $_GET;
        $requestBak = $_REQUEST;

        // Apply injected server environment
        $_SERVER = $server;

        // Populate $_GET from REQUEST_URI query string (so fmt and other qs are visible)
        $_GET = [];
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $qpos = strpos($uri, '?');
        if ($qpos !== false) {
            parse_str(substr($uri, $qpos + 1), $_GET);
        }

        // Ensure $_REQUEST doesn't leak between runs
        $_REQUEST = [];

        try {
            return self::route($options);
        } finally {
            $_SERVER  = $serverBak;
            $_GET     = $getBak;
            $_REQUEST = $requestBak;
        }
    }
    
    /**
    * Detects database for own domain and DBREF 
    */
    public static function detectDb(array $server, array $opts = []): array
    {
        $defaultVersion = $opts['default_version'] ?? 'heurist';
        $mappingFile = $opts['mapping_file'] ?? self::defaultMappingFile();
        $mapping = self::loadMapping($mappingFile);

        // 1) DOMAIN mapping by host (highest priority)
        $host = strtolower($server['HTTP_HOST'] ?? '');

        // Host mapping (own domain)
        $hostMap = $mapping['domains'][strtolower($host)] ?? null;

        if (is_array($hostMap) && !empty($hostMap['db'])) {
            return [
                'db' => (string)$hostMap['db'],
                //'website' => isset($hostMap['website']) ? (int)$hostMap['website'] : null,
                //'version' => $hostMap['version'] ?? $defaultVersion,
                'source' => 'domain',
            ];
        }

        // 2) DB param mapping (DBREF)
        $qstr = parse_url($server['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        $q = [];
        if ($qstr) { parse_str($qstr, $q); }
        $dbCandidate = $q['db'] ?? null;

        if (is_string($dbCandidate) && $dbCandidate !== '') {
            $dbResolved = self::applyDbRef($mapping, $dbCandidate);

            if (is_string($dbResolved) && $dbResolved !== '' && $dbResolved !== $dbCandidate) {
                return [
                    'db' => $dbResolved,
                    'source' => 'dbref',
                ];
            }
        }

        return [
            'db' => null,
            //'website' => null,
            //'version' => $defaultVersion,
            'source' => 'none',
        ];
    }
    
    // ----------------- Route builders -----------------

    private static function resultInternal(string $script, array $params): array
    {
        return [
            'mode'     => 'INTERNAL',
            'status'   => 200,
            'script'   => $script,
            'location' => null,
            'params'   => $params,
        ];
    }

    private static function resultRedirect(string $location, int $status, array $params = []): array
    {
        return [
            'mode'     => 'REDIRECT',
            'status'   => $status,
            'script'   => null,
            'location' => $location,
            'params'   => $params,
        ];
    }

    private static function result404(): array
    {
        return [
            'mode'     => 'NOT_FOUND',
            'status'   => 404,
            'script'   => null,
            'location' => null,
            'params'   => [],
        ];
    }

    // ----------------- Specific route handlers -----------------

    private static function routeOwnDomain(string $version, ?string $db, $website, array $segments): array
    {
        if (!$db) return self::result404();

        $params = ['db' => $db];

        // Home: /
        if (empty($segments)) {
            $params['website'] = ($website !== null) ? $website : 0;
            return self::resultInternal(self::serverRoot() . "/{$version}/index.php", $params);
        }

        // /<websiteid>
        if (count($segments) === 1 && ctype_digit($segments[0])) {
            
            $n = (int)$segments[0];

            if ($website !== null && $website !== 0) {
                // Fixed website mapping: /<pageid>
                $params['website'] = (int)$website;
                $params['pageid']  = $n;
            } else {
                // No fixed website mapping: /<websiteid>
                $params['website'] = $n;
                // pageid intentionally not set
            }            
            return self::resultInternal(self::serverRoot() . "/{$version}/index.php", $params);
        }

        // /<websiteid>/<pageid>
        if (count($segments) >= 2 && ctype_digit($segments[0]) && ctype_digit($segments[1])) {
            $params['website'] = (int)$segments[0];
            $params['pageid']  = (int)$segments[1];
            return self::resultInternal(self::serverRoot() . "/{$version}/index.php", $params);
        }

        // Allow /web/... etc on own-domain too
        $action = $segments[0] ?? null;
        if ($action !== null && in_array($action, self::ALLOWED_ACTIONS, true)) {
            $params = self::paramsFromPrettyRoute($db, $action, array_slice($segments, 1), $website);
            return self::resultInternal(self::serverRoot() . "/{$version}/index.php", $params);
        }

        return self::resultInternal(self::serverRoot() . "/{$version}/index.php", $params);
    }

    private static function paramsFromDbResolverPath(array $segments): array
    {
        // patterns:
        // /db/record/2312-123 OR /db/record/2312/123
        // /db/rty/1376-10
        // /db/file/<fileid>
        $params = [];

        $kind = $segments[1] ?? '';

        if ($kind === 'file') {
            $fileid = $segments[2] ?? '';
            if ($fileid !== '') $params['file'] = $fileid;
            return $params;
        }

        // Build concept id
        $concept = '';
        if (isset($segments[3])) {
            $concept = $segments[2] . '-' . $segments[3];
        } else {
            $concept = $segments[2] ?? '';
        }
        
        [$concept, $ext] = self::stripKnownExtensions($concept);
        
        if(empty($params['fmt']) && !empty($ext) && !empty(self::REQUEST_CONTENT[$ext])){
            $params['fmt'] = $ext;
        }

        if (in_array($kind, ['rty','dty','rst','trm'], true)) {
            $params[$kind] = $concept;
        } else {
            $params['recID'] = $concept;
        }

        return $params;
    }

    private static function paramsFromPrettyRoute(string $db, string $action, array $rest, ?int $defaultWebsite = null): array
    {
        $params = ['db' => $db];

        switch ($action) {
            case 'web':
            case 'website': // also covers "web" if you map it here
                // /<db>/web/[websiteId]/[pageId]
                if (isset($rest[0]) && ctype_digit($rest[0])) {
                    $params['website'] = (int)$rest[0];
                    if (isset($rest[1]) && ctype_digit($rest[1])) {
                        $params['pageid'] = (int)$rest[1];
                    }
                } else {
                    // websiteId not present in URL => use mapping default if provided
                    $params['website'] = ($defaultWebsite !== null) ? (int)$defaultWebsite : 0;

                    // In this form, the first segment (if numeric) is the page id
                    if (isset($rest[0]) && ctype_digit($rest[0])) {
                        $params['pageid'] = (int)$rest[0];
                    }
                }
                break;

            case 'view':
                if (isset($rest[0])) { $params['recid'] = $rest[0]; }
                $params['fmt'] = $params['fmt'] ?? 'html';
                break;
            case 'hml':
                $params['fmt'] = 'hml';
            case 'record':
                $params['fmt'] = $params['fmt'] ?? 'html';
                if (isset($rest[0])) { $params['recid'] = $rest[0]; }
                /*
                if (isset($rest[0])){
                    if(isPositiveInt($rest[0]) || isConceptCode($rest[0])){
                        $params['recid'] = $rest[0];    
                    }else{
                        $params['q'] = $rest[0];    
                    }
                }*/
                if (isset($rest[1]) && ctype_digit($rest[1])) $params['depth'] = (int)$rest[1];
                break;


            case 'edit':
                if (isset($rest[0])) $params['recid'] = $rest[0];
                $params['edit'] = 1;
                break;

            case 'tpl': {
                // Support:
                //  1) /tpl/<templateId>/<query>
                //  2) /tpl/<templateName>/<query>
                //
                // Query normalization:
                //  - if query looks like record ids -> q=ids:...
                //  - else q=<raw>

                if (!isset($rest[0]) || $rest[0] === '') break;

                $first = $rest[0];

                // /tpl/<templateId>/...
                if (ctype_digit($first)) {
                    $params['template_id'] = (int)$first;
                } else {
                    // /tpl/<templateName>/...
                    $params['template'] = $first;
                }

                // Optional query segment(s)
                // If there are multiple segments, join them back with "/" to preserve original query
                if (isset($rest[1]) && $rest[1] !== '') {
                    $rawQuery = implode('/', array_slice($rest, 1));

                    // prepareIds may accept comma/space/semicolon separated ids etc.
                    $ids = self::prepareIds($rawQuery);
                    if (!empty($ids)) {
                        $params['q'] = 'ids:' . implode(',', $ids);
                    } else {
                        $params['q'] = $rawQuery;
                    }
                }

                break;
            }
            case 'adm':
                $params['adm'] = 1;
                break;
        }

        return $params;
    }

    // ----------------- Mapping + parsing helpers -----------------

    private static function prepareIds($ids, bool $canBeZero=false): array
    {
        if ($ids === null) return [];

        if (!is_array($ids)) {
            $ids = (string)$ids;

            // If a single numeric token, treat as one id
            if (is_numeric($ids)) {
                $ids = [$ids];
            } else {
                // Support commas + whitespace/semicolon/plus (more forgiving for URLs)
                $ids = preg_split('~[,\s;+]+~', $ids) ?: [];
            }
        }

        $res = [];
        foreach ($ids as $v) {
            $v = trim((string)$v);
            if ($v === '') continue;

            if (is_numeric($v) && ((float)$v > 0 || ($canBeZero && (float)$v == 0))) {
                $res[] = (int)$v;
            }
        }
        return $res;
    }
    
    private static function defaultMappingFile(): string
    {
        // This class lives under .../<version>/hserv/controller/
        // and /HEURIST is one level above version directory.
        return self::serverRoot() . "/HEURIST/domainWebsites.json";
    }

    private static function serverRoot(): string
    {
        // var/www/html
        // If deployed elsewhere, set mapping_file explicitly.
        return rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    }

    private static function loadMapping(string $file): array
    {
        if (!is_file($file)) {
            return ['domains' => [], 'dbref' => [], 'reserved_paths' => []];
        }

        $mtime = @filemtime($file) ?: 0;
        if (self::$mapping !== null && self::$mappingMTime === $mtime) {
            return self::$mapping;
        }

        $raw = @file_get_contents($file);
        $json = $raw ? json_decode($raw, true) : null;
        if (!is_array($json)) {
            $json = ['domains' => [], 'dbref' => [], 'reserved_paths' => []];
        }

        self::$mapping = $json;
        self::$mappingMTime = $mtime;
        return $json;
    }

    private static function getHost(): string
    {
        // Prefer X-Forwarded-Host when behind CF/Proxy, but do not blindly trust multiple hosts.
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
        // If multiple forwarded hosts, take the first
        if (strpos($host, ',') !== false) {
            $host = trim(explode(',', $host)[0]);
        }
        // strip port
        $host = preg_replace('/:\d+$/', '', $host);
        return strtolower(trim($host));
    }

    private static function stripQueryString(string $uri): string
    {
        $qpos = strpos($uri, '?');
        return $qpos === false ? $uri : substr($uri, 0, $qpos);
    }

    private static function splitPath(string $path): array
    {
        $path = trim($path);
        if ($path === '' || $path === '/') return [];
        $path = trim($path, '/');
        if ($path === '') return [];
        $parts = explode('/', $path);
        // Keep raw segments (do not urldecode blindly); decode only safe tokens later if needed
        return array_values(array_filter($parts, static fn($s) => $s !== ''));
    }

    private static function isStartupRoute(array $segments): bool
    {
        return !empty($segments) && $segments[0] === 'startup';
    }

    private static function isApiRoute(array $segments): bool
    {
        return !empty($segments) && $segments[0] === 'api';
    }

    private static function isDbResolverRoute(array $segments): bool
    {
        if (count($segments) < 2) return false;
        if ($segments[0] !== 'db') return false;
        return in_array($segments[1], ['record','rec','rty','dty','rst','trm','file'], true);
    }

    private static function stripKnownExtensions(string $token): array
    {
        // allow accidental .rdf etc on the last segment
        $ext = null;
        if (strpos($token, '.') !== false) {
            $token = explode('.', $token, 2);
            $ext = $token[1]??null;
            $token = $token[0];
        }
        return [$token, $ext];
    }

    private static function negotiateFmtIfMissing(array $params): array
    {
        if (!empty($params['fmt'])) return $params;

        // if query already contains fmt, honor it
        if (!empty($_GET['fmt'])) {
            $params['fmt'] = (string)$_GET['fmt'];
            return $params;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if ($accept === '') return $params;

        foreach (self::REQUEST_CONTENT as $fmt => $mime) {
            if (strpos($accept, $mime) !== false) {
                $params['fmt'] = $fmt;
                return $params;
            }
        }

        return $params;
    }

    private static function applyDbRef(array $mapping, string $dbCandidate): string
    {
        $dbref = $mapping['dbref'][$dbCandidate] ?? null;
        if (is_array($dbref) && !empty($dbref['db'])) {
            return (string)$dbref['db'];
        }
        return $dbCandidate;
    }
    
    private static function getDefWebsite(array $mapping, string $dbCandidate): ?int
    {
        $dbref = $mapping['dbref'][$dbCandidate] ?? null;

        if (is_array($dbref) && isset($dbref['website']) && ctype_digit((string)$dbref['website'])) {
            return (int)$dbref['website'];
        }

        return null;
    }    

    private static function isValidDbToken(string $db): bool
    {
        return $db !== '' && preg_match('/^[A-Za-z0-9_\-\$]+$/', $db) === 1;
    }
    
    private static function queryParamsFromRequestUri(): array
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $q = parse_url($uri, PHP_URL_QUERY);
        if (!$q) return [];
        $out = [];
        parse_str($q, $out);
        return is_array($out) ? $out : [];
    }    
}
