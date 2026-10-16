<?php

declare(strict_types=1);

final class ApiClient
{
    /**
     * Retrieves all registered databases exposed by the server-level public
     * /api/databases endpoint. The endpoint uses the standard collection
     * envelope and may paginate, so follow pagination.next until exhausted.
     */
    public function fetchRegisteredDatabases(string $server): array
    {
        $url = $this->buildUrl($server, null, 'databases', [
            'details' => 'full',
            'sys_dbRegisteredID' => '>0',
            'limit' => API_LIMIT,
            'offset' => 0,
        ]);

        $all = [];
        $visitedUrls = [];

        while ($url !== null) {
            if (isset($visitedUrls[$url])) {
                throw new RuntimeException("Database API pagination loop detected for {$url}");
            }
            $visitedUrls[$url] = true;

            $json = $this->getJson($url);
            $items = $this->extractCollectionItems($json, $url, 'Database');
            foreach ($items as $item) {
                if (is_array($item)) {
                    $all[] = $item;
                }
            }
            $url = $this->extractNextPageUrl($json, $url);
        }

        return $all;
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

            $json = $this->getJson($url);
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

    private function buildUrl(string $server, ?string $dbName, string $entity, array $params): string
    {
        $path = 'api/';
        if ($dbName !== null && $dbName !== '') {
            $path .= rawurlencode(normaliseDbNameForApi($dbName)) . '/';
        }
        $path .= rawurlencode($entity);

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return normaliseServerUrl($server) . $path . ($query !== '' ? '?' . $query : '');
    }

    private function getJson(string $url): array
    {
        $body = $this->requestJson('GET', $url);
        return $this->decodeJsonBody($url, $body);
    }

    private function requestJson(string $method, string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => HTTP_TIMEOUT_SECONDS,
                'header' => "Accept: application/json
",
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

    private function extractHttpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
                return (int)$m[1];
            }
        }
        return 200;
    }

    private function extractDefinitionItems(array $json, string $url): array
    {
        return $this->extractCollectionItems($json, $url, 'Definition');
    }

    private function extractCollectionItems(array $json, string $url, string $label): array
    {
        if (!array_key_exists('items', $json) || !is_array($json['items'])) {
            throw new RuntimeException("{$label} API response from {$url} does not contain an items array");
        }
        if (!array_key_exists('pagination', $json) || !is_array($json['pagination'])) {
            throw new RuntimeException("{$label} API response from {$url} does not contain pagination metadata");
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
