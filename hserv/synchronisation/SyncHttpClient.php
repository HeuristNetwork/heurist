<?php

namespace hserv\synchronisation;

/**
 * Satellite-side signed HTTPS client for the master synchronisation endpoint.
 */
final class SyncHttpClient
{
    private \hserv\System $system;
    private array $config;

    public function __construct(\hserv\System $system, array $config)
    {
        $this->system = $system;
        $this->config = $config;
    }

    public function startSession(): array
    {
        return $this->request('start_session', [
            'lastMasterChangeReceived' => (int)($this->config['lastMasterChangeReceived'] ?? 0)
        ]);
    }

    public function allocateIDs(string $sessionID, array $records): array
    {
        return $this->request('allocate_ids', ['sessionID' => $sessionID, 'records' => $records]);
    }

    public function validateStructureUsage(string $sessionID, array $usage): array
    {
        return $this->request('validate_structure_usage', ['sessionID' => $sessionID, 'usage' => $usage]);
    }

    public function uploadRecords(string $sessionID, array $payload): array
    {
        return $this->request('upload_records', ['sessionID' => $sessionID, 'payload' => $payload]);
    }

    public function uploadDependencies(string $sessionID, array $payload): array
    {
        return $this->request('upload_dependencies', ['sessionID' => $sessionID, 'payload' => $payload]);
    }

    public function downloadChanges(string $sessionID, int $afterChangeID, bool $includeStructure = false,
        string $structureHash = ''): array
    {
        return $this->request('download_changes', [
            'sessionID' => $sessionID,
            'afterChangeID' => $afterChangeID,
            'includeStructure' => $includeStructure,
            'structureHash' => $structureHash
        ]);
    }

    private function request(string $action, array $payload): array
    {
        $master = $this->config['master'] ?? [];
        $satelliteID = (int)($this->config['databaseID'] ?? 0);
        $sharedKey = (string)($master['sharedKey'] ?? '');
        if ($satelliteID < 1 || $sharedKey === '') {
            return $this->system->addError(HEURIST_SYSTEM_CONFIG, 'Satellite authentication is not configured.');
        }

        $payload = array_merge(['action' => $action], $payload);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = $satelliteID."\n".$timestamp."\n".$nonce."\n".$action."\n".hash('sha256', $body);
        $signature = hash_hmac('sha256', $canonical, $sharedKey);
        $url = rtrim((string)$master['url'], '/').'/hserv/controller/synchronisationController.php?db='.
            rawurlencode((string)$master['database']);

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Heurist-Satellite-ID: '.$satelliteID,
                'X-Heurist-Sync-Timestamp: '.$timestamp,
                'X-Heurist-Sync-Nonce: '.$nonce,
                'X-Heurist-Sync-Signature: '.$signature
            ]
        ]);
        $bodyResult = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $curlErrno = curl_errno($curl);
        $error = curl_error($curl);
        curl_close($curl);
        if ($bodyResult === false) {
            return $this->diagnosticError(
                'connection_failed',
                'The satellite could not connect to the master Heurist server.',
                'cURL '.$curlErrno.($error !== '' ? ': '.$error : '').' Endpoint: '.$url
            );
        }
        $response = json_decode($bodyResult, true);
        if ($status < 200 || $status >= 300) {
            $remoteMessage = is_array($response)
                ? trim(strip_tags((string)($response['message'] ?? $response['msg'] ?? '')))
                : '';
            return $this->diagnosticError(
                'http_error',
                'The master endpoint returned HTTP '.$status.'.'.($remoteMessage !== '' ? ' '.$remoteMessage : ''),
                'Endpoint: '.$url.'; content type: '.($contentType ?: 'not supplied')
            );
        }
        if (!is_array($response)) {
            $preview = trim(preg_replace('/\s+/', ' ', strip_tags((string)$bodyResult)));
            return $this->diagnosticError(
                'invalid_response',
                'The master endpoint was reached, but it did not return a valid synchronisation response.',
                'Endpoint: '.$url.'; HTTP '.$status.'; content type: '.($contentType ?: 'not supplied')
                    .'; response begins: '.substr($preview, 0, 500)
            );
        }
        return $response;
    }

    private function diagnosticError(string $code, string $message, string $detail): array
    {
        return [
            'status' => HEURIST_ACTION_BLOCKED,
            'message' => $message,
            'sysmsg' => $detail,
            'diagnosticCode' => $code
        ];
    }
}
