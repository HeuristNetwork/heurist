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

    public function fetchRows(string $server, string $dbName, string $entity, array $filters, int $totalCount): array
    {
        $all = [];
        $offset = 0;

        if ($totalCount === 0) {
            $countParams = array_merge(['details' => 'id', 'limit' => 1, 'offset' => 0], $filters);
            $countUrl = $this->buildUrl($server, $dbName, $entity, $countParams);
            $countJson = $this->getJson($countUrl, $server);
            $totalCount = $this->extractTotalCount($countJson);
        }

        while ($offset < $totalCount) {
            $params = array_merge(['details' => 'raw', 'limit' => API_LIMIT, 'offset' => $offset], $filters);
            $url = $this->buildUrl($server, $dbName, $entity, $params);
            $json = $this->getJson($url, $server);
            $records = $this->extractRecords($json);
            $reccount = $this->extractReccount($json, count($records));

            foreach ($records as $record) {
                if (is_array($record)) {
                    $all[] = $record;
                }
            }

            if ($reccount <= 0) {
                break;
            }
            $offset += $reccount;
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

    private function extractTotalCount(array $json): int
    {
        if (array_key_exists('count', $json)) {
            return (int)$json['count'];
        }
        if (array_key_exists('reccount', $json)) {
            return (int)$json['reccount'];
        }
        $records = $this->extractRecords($json);
        return count($records);
    }

    private function extractReccount(array $json, int $fallback): int
    {
        if (array_key_exists('reccount', $json)) {
            return (int)$json['reccount'];
        }
        return $fallback;
    }

}
