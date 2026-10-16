<?php
/**
 * Scheduled whole-database safeguard deposits.
 *
 * The browser only schedules work. The CLI worker checks for changes, creates the
 * existing self-documenting archive and publishes a version to each selected account.
 * State is per repository so a failed upload never advances another repository.
 */
namespace hserv\utilities;

require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';
require_once dirname(__FILE__).'/UFile.php';

class SafeguardBackup
{
    /** Use the same strict server-level experimental gate as other features. */
    public static function isEnabled()
    {
        global $experimental;
        return isset($experimental) && $experimental === true;
    }

    public static function requireEnabled($system)
    {
        if (self::isEnabled()) {
            return true;
        }
        $system->addError(HEURIST_ACTION_BLOCKED, HEURIST_EXPERIMENTAL_UNAVAILABLE_MESSAGE);
        return false;
    }

    public static function settings($system)
    {
        $config = $system->settings->getDatabaseSetting('Safeguard backups');
        return is_array($config) ? $config : [];
    }

    public static function saveConfig($system, $days, $accounts, $email)
    {
        if (!self::requireEnabled($system)) {
            throw new \RuntimeException(HEURIST_EXPERIMENTAL_UNAVAILABLE_MESSAGE);
        }
        if (!$system->isAdmin() || $days < 1 || $days > 365 || !is_array($accounts)) {
            throw new \RuntimeException('Database manager permissions and an interval of 1–365 days are required');
        }
        $accounts = array_values(array_unique(array_filter($accounts, 'is_string')));
        $visible = array_column(\user_getRepositoryList($system, $system->getUserId(), true), 0);
        foreach ($accounts as $account) {
            if (!preg_match('/^(nakala|zenodo)_[0-9]+$/', $account) || !in_array($account, $visible, true)) {
                throw new \RuntimeException('Unsupported repository account');
            }
            $credentials = \user_getRepositoryCredentials2($system, $account);
            if (empty($credentials[$account]['params']['writeApiKey'])) {
                throw new \RuntimeException('A write API key is required for '.$account);
            }
        }
        $config = self::settings($system);
        $config['interval_days'] = (int)$days;
        $config['accounts'] = $accounts;
        $config['email_file'] = (bool)$email;
        if (!$system->settings->setDatabaseSetting('Safeguard backups', $config)) {
            throw new \RuntimeException('Could not save safeguard settings');
        }
    }

    /** Stable fingerprint of the tables included in a safeguard, checked only when due. */
    public static function fingerprint($system)
    {
        $mysqli = $system->getMysqli();
        $tables = $mysqli->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        if (!$tables) {
            throw new \RuntimeException('Cannot list database tables');
        }
        $parts = [];
        while ($row = $tables->fetch_row()) {
            $table = $row[0];
            if (!preg_match('/^(Records|rec[A-Za-z0-9_]*|def[A-Za-z0-9_]*|sysIdentification)$/', $table)) {
                continue;
            }
            $quoted = '`'.str_replace('`', '``', $table).'`';
            $schema = $mysqli->query('SHOW CREATE TABLE '.$quoted);
            $check = $mysqli->query('CHECKSUM TABLE '.$quoted.' EXTENDED');
            if (!$schema || !$check || !($s = $schema->fetch_row()) || !($c = $check->fetch_row()) || $c[1] === null) {
                throw new \RuntimeException('Cannot inspect table '.$table);
            }
            $parts[$table] = [$s[1], $c[1]];
        }
        if (!isset($parts['Records'], $parts['recDetails'], $parts['recUploadedFiles'])) {
            throw new \RuntimeException('A required safeguard table is missing');
        }
        ksort($parts);
        return hash('sha256', json_encode($parts));
    }

    /** HTTP requests never send tokens in URLs or logs. */
    private static function request($url, $token, $method = 'GET', $data = null, $file = null, $nakala = false)
    {
        if (!preg_match('~^https://(zenodo\.org|sandbox\.zenodo\.org|api\.nakala\.fr|apitest\.nakala\.fr)/~', $url)) {
            throw new \RuntimeException('Unexpected repository endpoint');
        }
        $headers = [$nakala ? 'X-API-KEY: '.$token : 'Authorization: Bearer '.$token];
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($file !== null) {
            $stream = fopen($file, 'rb');
            if (!$stream) {
                throw new \RuntimeException('Cannot read safeguard archive');
            }
            $headers[] = 'Content-Type: application/zip';
            curl_setopt($curl, CURLOPT_UPLOAD, true);
            curl_setopt($curl, CURLOPT_INFILE, $stream);
            curl_setopt($curl, CURLOPT_INFILESIZE, filesize($file));
        } elseif ($data !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($curl);
        $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (isset($stream)) {
            fclose($stream);
        }
        if ($body === false || $code < 200 || $code >= 300) {
            $remote = json_decode((string)$body, true);
            $detail = is_array($remote) ? ($remote['message'] ?? $remote['error'] ?? '') : '';
            throw new \RuntimeException('Repository returned HTTP '.$code.': '.substr((string)($detail ?: $error), 0, 200));
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function zenodo($archive, $token, $state, $title, $creator)
    {
        $base = 'https://zenodo.org/api/deposit/depositions';
        $published = (int)($state['deposition_id'] ?? 0);
        if (!empty($state['draft_id'])) {
            $draft = self::request($base.'/'.(int)$state['draft_id'], $token);
            if (!empty($draft['submitted']) && !empty($draft['conceptdoi'])) {
                // Publishing succeeded but the previous response/checkpoint was lost.
                unset($state['draft_id']);
                $state['deposition_id'] = (int)$draft['id'];
                $state['doi'] = $draft['conceptdoi'];
                $state['version_doi'] = $draft['doi'];
                unset($state['_checkpoint']);
                return $state;
            }
        } elseif ($published) {
            $previous = self::request($base.'/'.$published.'/actions/newversion', $token, 'POST');
            $url = $previous['links']['latest_draft'] ?? '';
            $draft = self::request($url, $token);
        } else {
            $draft = self::request($base, $token, 'POST', []);
        }
        $state['draft_id'] = (int)($draft['id'] ?? 0);
        if (!$state['draft_id']) {
            throw new \RuntimeException('Zenodo did not return a draft identifier');
        }
        // Draft state is returned to the caller through the callback before any further requests.
        $checkpoint = $state['_checkpoint'];
        unset($state['_checkpoint']);
        $checkpoint($state);
        $id = $state['draft_id'];
        self::request($base.'/'.$id, $token, 'PUT', ['metadata' => [
            'title' => $title, 'upload_type' => 'dataset',
            'description' => 'Self-documenting Heurist database safeguard archive.',
            'creators' => [['name' => $creator]], 'access_right' => 'restricted',
            'access_conditions' => 'Request access from the database owner. The full archive may contain private data and credentials.'
        ]]);
        // Zenodo copies files to a new draft; remove them so each version is one complete snapshot.
        foreach ($draft['files'] ?? [] as $old) {
            self::request($base.'/'.$id.'/files/'.rawurlencode($old['id']), $token, 'DELETE');
        }
        $bucket = $draft['links']['bucket'] ?? '';
        if (!preg_match('~^https://(zenodo\.org|sandbox\.zenodo\.org)/api/files/~', $bucket)) {
            throw new \RuntimeException('Zenodo did not provide a valid upload bucket');
        }
        self::request(rtrim($bucket, '/').'/'.rawurlencode(basename($archive)), $token, 'PUT', null, $archive);
        $result = self::request($base.'/'.$id.'/actions/publish', $token, 'POST');
        if (empty($result['conceptdoi']) || empty($result['doi'])) {
            throw new \RuntimeException('Zenodo published without returning a concept and version DOI');
        }
        unset($state['draft_id']);
        $state['deposition_id'] = (int)$result['id'];
        $state['doi'] = $result['conceptdoi'];
        $state['version_doi'] = $result['doi'];
        return $state;
    }

    public static function zenodoDetails($token, $identifier)
    {
        if (!ctype_digit((string)$identifier)) {
            throw new \RuntimeException('Invalid Zenodo deposition identifier');
        }
        $result = self::request('https://zenodo.org/api/deposit/depositions/'.(int)$identifier, $token);
        return [
            'identifier' => (string)$result['id'],
            'doi' => $result['conceptdoi'] ?? ($result['doi'] ?? ''),
            'doiRegistered' => !empty($result['doi']) && !empty($result['submitted']),
            'status' => !empty($result['submitted']) ? 'published' : 'draft',
            'link' => $result['links']['record_html'] ?? ''
        ];
    }

    private static function nakala($system, $archive, $token, $state, $title, $creator, $checkpoint)
    {
        $urls = \getNakalaBaseUrls($token);
        $base = $urls['api'];
        if (!empty($state['pending_initial'])) {
            $details = \getNakalaDataDetails($system, $token, $state['doi']);
            if (!$details || empty($details['doiRegistered'])) {
                throw new \RuntimeException('Waiting for Nakala to confirm DOI registration');
            }
            unset($state['pending_initial']);
            return $state;
        }
        if (empty($state['doi'])) {
            $meta = [
                'title' => ['value' => $title, 'lang' => null,
                    'typeUri' => W3_XML_SCHEMA_STRING, 'propertyUri' => NAKALA_REPO.'terms#title'],
                'creator' => ['value' => $creator, 'lang' => null,
                    'typeUri' => W3_XML_SCHEMA_STRING, 'propertyUri' => 'http://purl.org/dc/terms/creator'],
                'created' => ['value' => date('Y-m-d'), 'lang' => null,
                    'typeUri' => null, 'propertyUri' => NAKALA_REPO.'terms#created'],
                'type' => ['value' => 'http://purl.org/coar/resource_type/c_ddb1', 'lang' => null,
                    'typeUri' => PURL_TERM_URI, 'propertyUri' => NAKALA_REPO.'terms#type']
            ];
            $result = \uploadFileToNakala($system, [
                'apiKey' => $token, 'status' => 'published', 'returnType' => 'editor+id',
                'file' => ['path' => $archive, 'type' => 'application/zip', 'name' => basename($archive),
                    'embargoed' => 'indefinite'],
                'meta' => $meta
            ]);
            if (!$result || empty($result['DOI'])) {
                throw new \RuntimeException('Nakala initial deposit failed: '.$system->getErrorMsg());
            }
            $state['doi'] = $result['DOI'];
            $state['pending_initial'] = true;
            $checkpoint($state);
            $details = \getNakalaDataDetails($system, $token, $result['DOI']);
            if (!$details || empty($details['doiRegistered'])) {
                throw new \RuntimeException('Nakala did not confirm publication of the first deposit');
            }
            unset($state['pending_initial']);
            return $state;
        }
        // Nakala versions a public data record when files are added. Keep the old
        // safeguard file: prior snapshots remain retrievable under the same DOI.
        $existing = self::request($base.'/'.rawurlencode($state['doi']).'/files', $token, 'GET', null, null, true);
        $sha1 = sha1_file($archive);
        foreach ($existing as $remoteFile) {
            if (($remoteFile['sha1'] ?? '') === $sha1) {
                return $state; // Response lost on a previous attempt; do not add twice.
            }
        }
        $upload = curl_init($base.'/uploads');
        curl_setopt_array($upload, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['file' => new \CURLFile($archive, 'application/zip', basename($archive))],
            CURLOPT_HTTPHEADER => ['X-API-KEY: '.$token],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 0
        ]);
        $body = curl_exec($upload);
        $code = (int)curl_getinfo($upload, CURLINFO_HTTP_CODE);
        curl_close($upload);
        $file = json_decode((string)$body, true);
        if ($code !== 201 || ($file['sha1'] ?? '') !== $sha1) {
            throw new \RuntimeException('Nakala could not stage the new safeguard file');
        }
        self::request($base.'/'.rawurlencode($state['doi']).'/files', $token, 'POST',
            ['sha1' => $sha1, 'name' => basename($archive), 'embargoed' => null], null, true);
        return $state;
    }

    public static function deposit($system, $account, $archive, $state, $checkpoint)
    {
        $credentials = \user_getRepositoryCredentials2($system, $account);
        $entry = $credentials[$account] ?? [];
        $service = $entry['service'] ?? '';
        $token = $entry['params']['writeApiKey'] ?? '';
        if (!in_array($service, ['nakala', 'zenodo'], true) || !$token) {
            throw new \RuntimeException('Repository credentials are unavailable for '.$account);
        }
        $owner = \user_getDbOwner($system->getMysqli());
        $creator = trim(($owner['ugr_LastName'] ?? '').', '.($owner['ugr_FirstName'] ?? ''), ' ,');
        $title = 'Heurist database '.$system->dbname();
        $state['_checkpoint'] = $checkpoint;
        if ($service === 'nakala') {
            unset($state['_checkpoint']);
            return self::nakala($system, $archive, $token, $state, $title, $creator ?: 'Heurist database owner', $checkpoint);
        }
        return self::zenodo($archive, $token, $state, $title, $creator ?: 'Heurist database owner');
    }
}
