<?php

declare(strict_types=1);

final class ApiClient
{
    /** @var array<string,string> */
    private array $bearerTokensByServer = [];

    /**
     * Authenticate once per configured source server. If a pre-issued token is
     * supplied in config, use it. Otherwise, if login/password are supplied,
     * POST JSON credentials to the server login endpoint and extract JWT/token
     * from the JSON response.
     */
    public function authenticateSource(array $sourceCfg): void
    {
        $server = normaliseServerUrl((string)$sourceCfg['server']);

        $preissued = (string)($sourceCfg['jwt'] ?? $sourceCfg['token'] ?? $sourceCfg['accessToken'] ?? '');
        if ($preissued !== '') {
            $this->bearerTokensByServer[$server] = $preissued;
            logLine("  Auth: using pre-issued bearer token for {$server}");
            return;
        }

        
        $username = (string)($sourceCfg['username'] ?? '');
        $password = (string)($sourceCfg['password'] ?? '');
        if ($username === '' && $password === '') {
            logLine("  Auth: no username/password configured for {$server}; requests will be anonymous/sessionless");
            return;
        }
        if ($username === '' || $password === '') {
            throw new RuntimeException("Both username and password are required for JWT authentication on {$server}");
        }
        $registryDb = normaliseDbNameForApi((string)$sourceCfg['registryDatabase']);
        /*
        $endpoint = (string)($sourceCfg['authEndpoint'] ?? '');
        if ($endpoint === '') {
            $url = normaliseServerUrl($server) . 'api/' . rawurlencode($registryDb) . '/login';
        } elseif (preg_match('~^https?://~i', $endpoint)) {
            $url = $endpoint;
        } else {
            $url = normaliseServerUrl($server) . ltrim($endpoint, '/');
        }*/

        $url = normaliseServerUrl($server) . 'hserv/controller/auth.php';

        $json = $this->postJson($url, [
            'username' => $username,
            'password' => $password,
            'db' => $registryDb,
        ]);

        $token = $this->extractAuthToken($json);
        if ($token === null) {
            throw new RuntimeException("Login succeeded but no JWT/token was found in response from {$url}");
        }

        $this->bearerTokensByServer[$server] = $token;
        logLine("  Auth: acquired bearer token for {$server}");
    }

    public function fetchRegisteredDatabases(string $server, string $registryDb): array
    {
        $url = $this->buildUrl($server, $registryDb, 'dbs', [
            'details' => 'raw',
            'sys_dbRegisteredID' => '>0',
        ]);
        $json = $this->getJson($url, $server);
        return $this->extractRecords($json);
    }

    public function fetchRows(string $server, string $dbName, string $entity, array $filters): array
    {
        // The modern definition API returns a collection envelope:
        // {items: [], meta: {...}, pagination: {total, offset, limit, self, next}}.
        // Follow pagination.next instead of issuing a separate count request or
        // calculating subsequent offsets in the harvester.
        $params = array_merge(['details' => 'raw', 'limit' => API_LIMIT, 'offset' => 0], $filters);
        $url = $this->buildUrl($server, $dbName, $entity, $params);

        $all = [];
        $visitedUrls = [];

        while ($url !== null) {
            if (isset($visitedUrls[$url])) {
                throw new RuntimeException("Definition API pagination loop detected for {$url}");
            }
            $visitedUrls[$url] = true;

            $json = $this->getJson($url, $server);
            $items = $this->extractDefinitionItems($json, $url);

            foreach ($items as $item) {
                if (is_array($item)) {
                    $all[] = $item;
                }
            }

            $url = $this->extractNextPageUrl($json, $url);
        }

        return $all;
    }

    private function buildUrl(string $server, string $dbName, string $entity, array $params): string
    {
        $dbName = normaliseDbNameForApi($dbName);
        return normaliseServerUrl($server)
            . 'api/' . rawurlencode($dbName)
            . '/' . rawurlencode($entity)
            . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function getJson(string $url, ?string $server = null): array
    {
        $body = $this->requestJson('GET', $url, null, $server);
        return $this->decodeJsonBody($url, $body);
    }

    private function postJson(string $url, array $payload): array
    {
        $body = $this->requestJson('POST', $url, json_encode($payload, JSON_UNESCAPED_SLASHES), null);
        return $this->decodeJsonBody($url, $body);
    }

    private function requestJson(string $method, string $url, ?string $body, ?string $server): string
    {
        $headers = [
            'Accept: application/json',
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($body);
        }

        $token = $server !== null ? ($this->bearerTokensByServer[normaliseServerUrl($server)] ?? null) : null;
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => HTTP_TIMEOUT_SECONDS,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body ?? '',
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException("HTTP request failed: {$url}");
        }

        $status = $this->extractHttpStatus($http_response_header ?? []);
        if ($status >= 400) {
            throw new RuntimeException("HTTP {$status} for {$url}: " . substr($responseBody, 0, 500));
        }

        return $responseBody;
    }

    private function decodeJsonBody(string $url, string $body): array
    {
        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new RuntimeException("Invalid JSON from {$url}: " . substr($body, 0, 500));
        }
        return $json;
    }

    private function extractAuthToken(array $json): ?string
    {
        foreach (['jwt', 'token', 'access_token', 'auth_token', 'bearer_token'] as $key) {
            if (isset($json[$key]) && is_string($json[$key]) && $json[$key] !== '') {
                return $json[$key];
            }
        }

        foreach (['data', 'response', 'result'] as $containerKey) {
            if (isset($json[$containerKey]) && is_array($json[$containerKey])) {
                $token = $this->extractAuthToken($json[$containerKey]);
                if ($token !== null) {
                    return $token;
                }
            }
        }

        return null;
    }

    private function extractHttpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
                return (int)$m[1];
            }
        }
        return 200;
    }

    private function extractRecords(array $json): array
    {
        if (array_key_exists('records', $json) && is_array($json['records'])) {
            return $json['records'];
        }
        // Backward tolerance for bare raw arrays.
        if (isListArray($json)) { //since 8.1 array_is_list
            return $json;
        }
        return [];
    }

    private function extractDefinitionItems(array $json, string $url): array
    {
        if (!array_key_exists('items', $json) || !is_array($json['items'])) {
            throw new RuntimeException("Definition API response from {$url} does not contain an items array");
        }
        if (!array_key_exists('pagination', $json) || !is_array($json['pagination'])) {
            throw new RuntimeException("Definition API response from {$url} does not contain pagination metadata");
        }

        return $json['items'];
    }

    private function extractNextPageUrl(array $json, string $url): ?string
    {
        $pagination = $json['pagination'];

        foreach (['total', 'offset', 'limit', 'self', 'next'] as $key) {
            if (!array_key_exists($key, $pagination)) {
                throw new RuntimeException("Definition API pagination from {$url} is missing {$key}");
            }
        }

        $next = $pagination['next'];
        if ($next === null || $next === '') {
            return null;
        }
        if (!is_string($next) || !preg_match('~^https?://~i', $next)) {
            throw new RuntimeException("Definition API pagination.next from {$url} is not an absolute URL");
        }

        return $next;
    }

}
