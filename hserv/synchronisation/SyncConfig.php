<?php

namespace hserv\synchronisation;

/**
 * Reads and validates the database-specific synchronisation.json settings file.
 */
final class SyncConfig
{
    private \hserv\System $system;

    public function __construct(\hserv\System $system)
    {
        $this->system = $system;
    }

    public function load(bool $includeSecrets = false): array
    {
        $config = $this->system->settings->getDatabaseSetting('Synchronisation');
        if (!is_array($config)) {
            return [];
        }
        return $includeSecrets ? $config : $this->redact($config);
    }

    public function save(array $config): bool
    {
        $existing = $this->load(true);
        $normalised = $this->validateAndNormalise($config, $existing);
        if ($normalised === false) {
            return false;
        }
        return $this->system->settings->setDatabaseSetting('Synchronisation', $normalised);
    }

    public function registeredDatabaseID(): int
    {
        return (int)$this->system->settings->get('sys_dbRegisteredID');
    }

    private function validateAndNormalise(array $config, array $existing)
    {
        $registeredID = $this->registeredDatabaseID();
        if ($registeredID < 1) {
            $this->system->addError(
                HEURIST_ACTION_BLOCKED,
                'Database synchronisation requires a registered Heurist database.'
            );
            return false;
        }

        $role = strtolower(trim((string)($config['role'] ?? '')));
        if (!in_array($role, ['master', 'satellite'], true)) {
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Role must be master or satellite.');
            return false;
        }

        $result = [
            'version' => 1,
            'role' => $role,
            'databaseID' => $registeredID,
            'updatedAt' => gmdate('c')
        ];

        if ($role === 'master') {
            $result['satellites'] = $this->normaliseSatellites($config['satellites'] ?? [], $existing['satellites'] ?? []);
            if ($result['satellites'] === false) {
                return false;
            }
        } else {
            $master = is_array($config['master'] ?? null) ? $config['master'] : [];
            $masterID = (int)($master['databaseID'] ?? 0);
            $database = trim((string)($master['database'] ?? ''));
            [$url, $databaseFromUrl] = $this->normaliseHeuristUrl((string)($master['url'] ?? ''));
            if ($database === '' && $databaseFromUrl !== '') {
                $database = $databaseFromUrl;
            }
            if ($masterID < 1 || !preg_match('/^[A-Za-z0-9_$]+$/', $database)
                || !filter_var($url, FILTER_VALIDATE_URL)) {
                $this->system->addError(
                    HEURIST_INVALID_REQUEST,
                    'A satellite requires the registered ID, database name and valid HTTPS URL of its master.'
                );
                return false;
            }
            if (strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'The master URL must use HTTPS.');
                return false;
            }
            $secret = trim((string)($master['sharedSecret'] ?? ''));
            $existingKey = (string)($existing['master']['sharedKey'] ?? '');
            if ($secret === '' && $existingKey === '') {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'A satellite requires a shared secret.');
                return false;
            }
            $result['master'] = [
                'databaseID' => $masterID,
                'database' => $database,
                'url' => $url,
                'sharedKey' => $secret !== '' ? hash('sha256', $secret) : $existingKey
            ];
            $result['lastCompletedSession'] = $existing['lastCompletedSession'] ?? null;
            $result['lastMasterChangeReceived'] = (int)($existing['lastMasterChangeReceived'] ?? 0);
            $result['lastNewRecordScanChangeID'] = (int)($existing['lastNewRecordScanChangeID'] ?? 0);
            $result['initialInventorySeeded'] = !empty($existing['initialInventorySeeded']);
        }
        return $result;
    }

    private function normaliseSatellites($satellites, array $existingSatellites)
    {
        if (!is_array($satellites)) {
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Satellites must be an array.');
            return false;
        }
        $result = [];
        $seen = [];
        $existingKeys = [];
        foreach ($existingSatellites as $existing) {
            $existingKeys[(int)($existing['databaseID'] ?? 0)] = (string)($existing['sharedKey'] ?? '');
        }
        foreach ($satellites as $satellite) {
            if (!is_array($satellite)) {
                continue;
            }
            $id = (int)($satellite['databaseID'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Every satellite must have a unique registered database ID.');
                return false;
            }
            $secret = trim((string)($satellite['sharedSecret'] ?? ''));
            $existingKey = $existingKeys[$id] ?? '';
            if ($secret === '' && $existingKey === '') {
                $this->system->addError(HEURIST_INVALID_REQUEST, "Satellite $id requires a shared secret.");
                return false;
            }
            [$url] = $this->normaliseHeuristUrl((string)($satellite['url'] ?? ''));
            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL)
                || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https')) {
                $this->system->addError(HEURIST_INVALID_REQUEST, "Satellite $id has an invalid HTTPS URL.");
                return false;
            }
            $seen[$id] = true;
            $result[] = [
                'databaseID' => $id,
                'name' => trim((string)($satellite['name'] ?? '')),
                'url' => $url,
                'priority' => max(0, min(100, (int)($satellite['priority'] ?? 50))),
                'enabled' => !array_key_exists('enabled', $satellite) || (bool)$satellite['enabled'],
                // Store only the derived HMAC key. The submitted secret itself is
                // never written to disk or returned to the browser.
                'sharedKey' => $secret !== '' ? hash('sha256', $secret) : $existingKey
            ];
        }
        usort($result, static fn($a, $b) => $b['priority'] <=> $a['priority']);
        return $result;
    }

    private function redact(array $config): array
    {
        if (isset($config['master']['sharedKey'])) {
            $config['master']['hasSharedSecret'] = $config['master']['sharedKey'] !== '';
            unset($config['master']['sharedKey']);
            $config['master']['sharedSecret'] = '';
        }
        foreach ($config['satellites'] ?? [] as &$satellite) {
            $satellite['hasSharedSecret'] = !empty($satellite['sharedKey']);
            unset($satellite['sharedKey']);
            $satellite['sharedSecret'] = '';
        }
        unset($satellite);
        return $config;
    }

    /**
     * Convert a pasted Heurist database or controller URL to the codebase URL.
     * The database query parameter is returned separately when present.
     */
    private function normaliseHeuristUrl(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['', ''];
        }
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return [$value, ''];
        }
        $database = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $database = trim((string)($query['db'] ?? ''));
        }
        $path = (string)($parts['path'] ?? '/');
        $controllerAt = strpos($path, '/hserv/');
        if ($controllerAt !== false) {
            $path = substr($path, 0, $controllerAt + 1);
        }
        $authority = $parts['scheme'].'://';
        if (!empty($parts['user'])) {
            $authority .= $parts['user'].(!empty($parts['pass']) ? ':'.$parts['pass'] : '').'@';
        }
        $authority .= $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        return [rtrim($authority.'/'.ltrim($path, '/'), '/'), $database];
    }

    public function updateRuntime(array $values): bool
    {
        $allowed = [
            'lastCompletedSession',
            'lastMasterChangeReceived',
            'lastNewRecordScanChangeID',
            'initialInventorySeeded'
        ];
        $values = array_intersect_key($values, array_flip($allowed));
        if (!$values) {
            return true;
        }
        return $this->system->settings->setDatabaseSetting('Synchronisation', $values, 1);
    }
}
