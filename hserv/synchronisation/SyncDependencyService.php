<?php

namespace hserv\synchronisation;

use hserv\structure\ConceptCode;

/** Master-side application of terms and files required by an incoming record batch. */
final class SyncDependencyService
{
    private \hserv\System $system;
    private \mysqli $mysqli;

    public function __construct(\hserv\System $system)
    {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
    }

    public function apply(string $sessionID, int $satelliteDBID, array $payload): array
    {
        if (!SyncSchema::ensure($this->mysqli)) return $this->system->getError();
        if (!mysql__select_value($this->mysqli,
            'SELECT ssy_ID FROM sysSyncSessions WHERE ssy_ID=? AND ssy_SatelliteDBID=?',
            ['si', $sessionID, $satelliteDBID])) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'Unknown synchronisation session.');
        }
        if (($payload['format'] ?? '') !== 'heurist-sync-dependencies-1'
            || !is_array($payload['recordIDs'] ?? null) || !is_array($payload['terms'] ?? null)
            || !is_array($payload['files'] ?? null)) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid synchronisation dependency payload.');
        }
        if (count($payload['recordIDs']) > 100 || count($payload['terms']) > 5000 || count($payload['files']) > 500) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'The synchronisation dependency batch exceeds its size limit.');
        }
        foreach ($payload['recordIDs'] as $recordID) {
            if (!mysql__select_value($this->mysqli,
                'SELECT srm_MasterRecID FROM sysSyncRecordMap WHERE srm_SatelliteDBID=? AND srm_MasterRecID=? AND srm_SessionID=?',
                ['iis', $satelliteDBID, (int)$recordID, $sessionID])) {
                return $this->system->addError(HEURIST_INVALID_REQUEST, 'A dependency batch refers to a record not reserved by this session.');
            }
        }

        ConceptCode::setSystem($this->system);
        $keepAutocommit = mysql__begin_transaction($this->mysqli);
        $this->mysqli->query('SET @HEURIST_SYNC_APPLY=1');
        $createdFiles = [];
        $ok = true;
        $termCount = $this->applyTerms($satelliteDBID, $payload['terms']);
        if ($termCount === false) $ok = false;
        $fileCount = $ok ? $this->applyFiles($satelliteDBID, $payload['files'], $createdFiles) : false;
        if ($fileCount === false) $ok = false;
        $this->mysqli->query('SET @HEURIST_SYNC_APPLY=0');
        mysql__end_transaction($this->mysqli, $ok, $keepAutocommit);
        if (!$ok) {
            foreach ($createdFiles as $path) if (is_file($path)) @unlink($path);
            return $this->system->getError();
        }
        return ['status' => HEURIST_OK, 'data' => [
            'termsCreated' => (int)$termCount,
            'filesCreated' => (int)$fileCount
        ]];
    }

    private function applyTerms(int $satelliteDBID, array $terms)
    {
        $pending = [];
        $resolved = [];
        foreach ($terms as $term) {
            $concept = (string)($term['conceptID'] ?? '');
            $sourceID = (int)($term['sourceTermID'] ?? 0);
            if ($concept === '' || $sourceID < 1 || trim((string)($term['label'] ?? '')) === '') {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'A synchronised term is incomplete.');
                return false;
            }
            $localID = (int)ConceptCode::getTermLocalID($concept);
            // Cloned databases can share safe local IDs while registration changes their generated concept codes.
            if ($localID < 1) {
                $candidateLabel = mysql__select_value($this->mysqli,
                    'SELECT trm_Label FROM defTerms WHERE trm_ID=?', ['i', $sourceID]);
                if (is_string($candidateLabel) && hash_equals($candidateLabel, (string)$term['label'])) $localID = $sourceID;
            }
            if ($localID > 0) $resolved[$concept] = $localID;
            else $pending[$concept] = $term;
        }

        $created = 0;
        $createdConcepts = [];
        while ($pending) {
            $progress = false;
            foreach ($pending as $concept => $term) {
                $parts = explode('-', $concept, 2);
                if (count($parts) !== 2 || (int)$parts[0] !== $satelliteDBID || (int)$parts[1] !== (int)$term['sourceTermID']) {
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Unrecognised term concept $concept.");
                    return false;
                }
                $parentConcept = (string)($term['parentConceptID'] ?? '');
                $parentID = $parentConcept !== '' ? ($resolved[$parentConcept] ?? (int)ConceptCode::getTermLocalID($parentConcept)) : 0;
                if ($parentID < 1 && $parentConcept !== '' && isset($pending[$parentConcept])) continue;
                if ($parentID < 1) {
                    $this->system->addError(HEURIST_ACTION_BLOCKED,
                        'New top-level vocabularies are controlled by the master; term '.(string)$term['label'].' cannot be added.');
                    return false;
                }
                $parent = mysql__select_row($this->mysqli,
                    'SELECT trm_Domain,trm_OntID FROM defTerms WHERE trm_ID=?', ['i', $parentID]);
                if (!$parent || !in_array((string)$term['domain'], ['enum', 'relation'], true)
                    || (string)$parent[0] !== (string)$term['domain']) {
                    $this->system->addError(HEURIST_INVALID_REQUEST, 'The parent vocabulary does not accept term '.(string)$term['label'].'.');
                    return false;
                }
                $stmt = $this->mysqli->prepare('INSERT INTO defTerms '
                    .'(trm_Label,trm_Description,trm_Status,trm_OriginatingDBID,trm_NameInOriginatingDB,trm_IDInOriginatingDB,'
                    .'trm_AddedByImport,trm_IsLocalExtension,trm_Domain,trm_OntID,trm_ParentTermID) '
                    ."VALUES (?,?,'pending',?,?,?,1,1,?,?,?)");
                $label = (string)$term['label']; $description = $term['description'] ?? null;
                $sourceID = (int)$term['sourceTermID']; $domain = (string)$term['domain']; $ontologyID = (int)$parent[1];
                $stmt->bind_param('ssisisii', $label, $description, $satelliteDBID, $label, $sourceID, $domain, $ontologyID, $parentID);
                $ok = $stmt->execute();
                $newID = (int)$stmt->insert_id;
                if (!$ok) $this->system->addError(HEURIST_DB_ERROR, 'Unable to add synchronised term '.$label.'.', $stmt->error);
                $stmt->close();
                if (!$ok) return false;
                $resolved[$concept] = $newID;
                unset($pending[$concept]);
                $created++;
                $createdConcepts[$concept] = true;
                $progress = true;
            }
            if (!$progress) {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'The synchronised term hierarchy contains an unresolved parent.');
                return false;
            }
        }

        foreach ($terms as $term) {
            $inverseConcept = (string)($term['inverseConceptID'] ?? '');
            if ($inverseConcept === '' || empty($createdConcepts[(string)$term['conceptID']])) continue;
            $termID = $resolved[(string)$term['conceptID']] ?? 0;
            $inverseID = $resolved[$inverseConcept] ?? (int)ConceptCode::getTermLocalID($inverseConcept);
            if ($termID > 0 && $inverseID > 0 && !$this->mysqli->query(
                'UPDATE defTerms SET trm_InverseTermID='.(int)$inverseID.' WHERE trm_ID='.(int)$termID)) {
                $this->system->addError(HEURIST_DB_ERROR, 'Unable to link inverse synchronised terms.', $this->mysqli->error);
                return false;
            }
        }
        return $created;
    }

    private function applyFiles(int $satelliteDBID, array $files, array &$createdFiles)
    {
        $created = 0;
        $uploadDir = $this->system->getSysDir(DIR_FILEUPLOADS);
        if (!$uploadDir || (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true))) {
            $this->system->addError(HEURIST_SYSTEM_CONFIG, 'The master file-upload directory is unavailable.');
            return false;
        }
        $encodedBytes = 0;
        foreach ($files as $file) {
            $sourceID = (int)($file['ulf_ID'] ?? 0);
            if ($sourceID < 1) {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'A synchronised file has no source ID.');
                return false;
            }
            if (mysql__select_value($this->mysqli,
                'SELECT sfm_MasterFileID FROM sysSyncFileMap WHERE sfm_SatelliteDBID=? AND sfm_SatelliteFileID=?',
                ['ii', $satelliteDBID, $sourceID])) continue;

            $content = $file['contentBase64'] ?? null;
            if (is_string($content)) {
                $encodedBytes += strlen($content);
                if (strlen($content) > 28 * 1024 * 1024 || $encodedBytes > 55 * 1024 * 1024) {
                    $this->system->addError(HEURIST_INVALID_REQUEST, 'The synchronised file payload exceeds its size limit.');
                    return false;
                }
            }
            $hash = (string)($file['contentHash'] ?? '');
            $storedName = null;
            $storedPath = null;
            if (is_string($content) && $content !== '') {
                $bytes = base64_decode($content, true);
                if ($bytes === false || $hash === '' || !hash_equals($hash, hash('sha256', $bytes))) {
                    $this->system->addError(HEURIST_INVALID_REQUEST, 'A synchronised file failed its content hash check.');
                    return false;
                }
                $extension = preg_replace('/[^A-Za-z0-9]/', '', (string)($file['ulf_MimeExt'] ?? ''));
                $storedName = 'sync_'.$satelliteDBID.'_'.$sourceID.'_'.substr($hash, 0, 16).($extension !== '' ? '.'.$extension : '');
                $fullPath = $uploadDir.$storedName;
                if (file_put_contents($fullPath, $bytes, LOCK_EX) === false) {
                    $this->system->addError(HEURIST_SYSTEM_CONFIG, 'Unable to store a synchronised file on the master.');
                    return false;
                }
                $createdFiles[] = $fullPath;
                $storedPath = DIR_FILEUPLOADS;
            }
            $nonce = sha1($satelliteDBID.':'.$sourceID.':'.bin2hex(random_bytes(16)));
            $stmt = $this->mysqli->prepare('INSERT INTO recUploadedFiles '
                .'(ulf_OrigFileName,ulf_UploaderUGrpID,ulf_Added,ulf_ObfuscatedFileID,ulf_ExternalFileReference,ulf_PreferredSource,'
                .'ulf_Caption,ulf_Description,ulf_Copyright,ulf_Copyowner,ulf_MimeExt,ulf_AddedByImport,ulf_FileSizeKB,ulf_FilePath,'
                .'ulf_FileName,ulf_Parameters,ulf_WhoCanView,ulf_MD5Checksum) '
                .'VALUES (?,1,UTC_TIMESTAMP(),?,?,?,?,?,?,?,?,1,?,?,?,?,?,?)');
            $original = basename((string)($file['ulf_OrigFileName'] ?? 'synchronised-file'));
            $external = $file['ulf_ExternalFileReference'] ?? null; $preferred = (string)($file['ulf_PreferredSource'] ?? 'local');
            $caption = $file['ulf_Caption'] ?? null; $description = $file['ulf_Description'] ?? null;
            $copyright = $file['ulf_Copyright'] ?? null; $copyowner = $file['ulf_Copyowner'] ?? null;
            $mime = $file['ulf_MimeExt'] ?? null; $sizeKB = isset($file['ulf_FileSizeKB']) ? (int)$file['ulf_FileSizeKB'] : null;
            $parameters = $file['ulf_Parameters'] ?? null; $visibility = $file['ulf_WhoCanView'] ?? null;
            $md5 = isset($bytes) ? md5($bytes) : null;
            $stmt->bind_param('sssssssssisssss', $original, $nonce, $external, $preferred, $caption, $description,
                $copyright, $copyowner, $mime, $sizeKB, $storedPath, $storedName, $parameters, $visibility, $md5);
            $ok = $stmt->execute();
            $masterID = (int)$stmt->insert_id;
            if (!$ok) $this->system->addError(HEURIST_DB_ERROR, 'Unable to register a synchronised file.', $stmt->error);
            $stmt->close();
            if (!$ok) return false;
            $stmt = $this->mysqli->prepare('INSERT INTO sysSyncFileMap '
                .'(sfm_SatelliteDBID,sfm_SatelliteFileID,sfm_MasterFileID,sfm_ContentHash) VALUES (?,?,?,?)');
            $stmt->bind_param('iiis', $satelliteDBID, $sourceID, $masterID, $hash);
            $ok = $stmt->execute();
            if (!$ok) $this->system->addError(HEURIST_DB_ERROR, 'Unable to save the synchronised file mapping.', $stmt->error);
            $stmt->close();
            if (!$ok) return false;
            $created++;
            unset($bytes);
        }
        return $created;
    }
}
