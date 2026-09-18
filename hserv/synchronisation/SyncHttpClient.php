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
        $error = curl_error($curl);
        curl_close($curl);
        if ($bodyResult === false || $status < 200 || $status >= 300) {
            return $this->system->addError(
                HEURIST_ERROR,
                'The master database could not be contacted.',
                $error !== '' ? $error : 'HTTP '.$status
            );
        }
        $response = json_decode($bodyResult, true);
        if (!is_array($response)) {
            return $this->system->addError(HEURIST_ERROR, 'The master returned an invalid synchronisation response.');
        }
        return $response;
    }
}
