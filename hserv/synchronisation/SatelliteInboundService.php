<?php

namespace hserv\synchronisation;

use hserv\structure\ConceptCode;

/** Applies master record batches to a satellite without re-journalling them. */
final class SatelliteInboundService
{
    private \hserv\System $system;
    private \mysqli $mysqli;

    public function __construct(\hserv\System $system)
    {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
    }

    public function apply(array $dependencies, array $payload, ?array $structure = null): array
    {
        if (!SyncSchema::ensure($this->mysqli)) return $this->system->getError();
        if (($dependencies['format'] ?? '') !== 'heurist-sync-dependencies-1'
            || !is_array($dependencies['terms'] ?? null) || !is_array($dependencies['files'] ?? null)
            || ($payload['format'] ?? '') !== 'heurist-hml-sync-1'
            || !is_array($payload['records'] ?? null)) {
            return $this->system->addError(HEURIST_INVALID_REQUEST, 'The master returned an invalid record batch.');
        }

        ConceptCode::setSystem($this->system);
        $createdFiles = [];
        $keepAutocommit = mysql__begin_transaction($this->mysqli);
        $this->mysqli->query('SET @HEURIST_SYNC_APPLY=1');
        $ok = true;
        $structureMaps = [];
        $definitionCount = 0;
        if ($structure !== null) {
            $baseResult = SyncStructurePayload::applyBase($this->system, $structure);
            if (($baseResult['status'] ?? null) !== HEURIST_OK) $ok = false;
            else $structureMaps = $baseResult['data']['maps'] ?? [];
        }
        $termCount = $ok ? $this->applyTerms($dependencies['terms'], $structureMaps) : false;
        if ($termCount === false) $ok = false;
        if ($ok && $structure !== null) {
            $structureMaps['terms'] = [];
            foreach ($dependencies['terms'] as $term) {
                $sourceID = (int)($term['sourceTermID'] ?? 0);
                $localID = (int)ConceptCode::getTermLocalID((string)($term['conceptID'] ?? ''));
                if ($sourceID > 0 && $localID > 0) $structureMaps['terms'][$sourceID] = $localID;
            }
            $structureResult = SyncStructurePayload::applyStructure($this->system, $structure, $structureMaps);
            if (($structureResult['status'] ?? null) !== HEURIST_OK) $ok = false;
            else $definitionCount = (int)($structureResult['data']['definitionsApplied'] ?? 0);
        }
        $shellCount = $ok ? $this->applyRecordShells(
            is_array($dependencies['recordShells'] ?? null) ? $dependencies['recordShells'] : []
        ) : false;
        if ($shellCount === false) $ok = false;
        $fileResult = $ok ? $this->applyFiles($dependencies['files'], $createdFiles) : false;
        if ($fileResult === false) $ok = false;
        $recordCount = $ok ? $this->applyRecords($payload['records'], $fileResult['mapping']) : false;
        if ($recordCount === false) $ok = false;
        mysql__end_transaction($this->mysqli, $ok, $keepAutocommit);
        $this->mysqli->query('SET @HEURIST_SYNC_APPLY=NULL');
        if (!$ok) {
            foreach ($createdFiles as $path) if (is_file($path)) @unlink($path);
            return $this->system->getError();
        }
        return ['status' => HEURIST_OK, 'data' => [
            'recordsApplied' => (int)$recordCount,
            'definitionsApplied' => $definitionCount,
            'recordShellsCreated' => (int)$shellCount,
            'termsCreated' => (int)$termCount,
            'filesCreated' => (int)$fileResult['created']
        ]];
    }

    private function resolveRecordTypeID(array $record): int
    {
        $recordTypeID = (int)ConceptCode::getRecTypeLocalID((string)($record['recordTypeConceptID'] ?? ''));
        if ($recordTypeID < 1 && (int)($record['rec_RecTypeID'] ?? 0) > 0) {
            $candidateID = (int)$record['rec_RecTypeID'];
            $candidateName = mysql__select_value($this->mysqli,
                'SELECT rty_Name FROM defRecTypes WHERE rty_ID=?', ['i', $candidateID]);
            if (is_string($candidateName) && hash_equals($candidateName, (string)($record['recordTypeName'] ?? ''))) {
                $recordTypeID = $candidateID;
            }
        }
        return $recordTypeID;
    }

    private function applyTerms(array $terms, array $structureMaps = [])
    {
        $pending = [];
        $resolved = [];
        foreach ($terms as $term) {
            $concept = (string)($term['conceptID'] ?? '');
            $sourceID = (int)($term['sourceTermID'] ?? 0);
            if ($concept === '' || $sourceID < 1 || trim((string)($term['label'] ?? '')) === '') {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'A term supplied by the master is incomplete.');
                return false;
            }
            $localID = (int)ConceptCode::getTermLocalID($concept);
            // Physical database clones normally retain matching term IDs and
            // labels, even when registration changes the generated concept ID.
            // Accept that existing term only when both values agree.
            if ($localID < 1) {
                $candidateLabel = mysql__select_value(
                    $this->mysqli,
                    'SELECT trm_Label FROM defTerms WHERE trm_ID=?',
                    ['i', $sourceID]
                );
                if (is_string($candidateLabel) && hash_equals($candidateLabel, (string)$term['label'])) {
                    $localID = $sourceID;
                }
            }
            if ($localID > 0) {
                $resolved[$concept] = $localID;
                if (!empty($term['masterControlled']) && !$this->updateMasterTerm($localID, $term, $structureMaps)) return false;
            }
            else $pending[$concept] = $term;
        }

        $created = 0;
        $createdConcepts = [];
        while ($pending) {
            $madeProgress = false;
            foreach ($pending as $concept => $term) {
                $parts = explode('-', $concept, 2);
                if (count($parts) !== 2 || (int)$parts[0] < 1 || (int)$parts[1] < 1) {
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Unrecognised term concept $concept.");
                    return false;
                }
                $parentConcept = (string)($term['parentConceptID'] ?? '');
                $parentID = $parentConcept !== ''
                    ? ($resolved[$parentConcept] ?? (int)ConceptCode::getTermLocalID($parentConcept)) : 0;
                if ($parentID < 1 && $parentConcept !== '' && isset($pending[$parentConcept])) continue;
                if ($parentID < 1 && $parentConcept !== '') {
                    $this->system->addError(
                        HEURIST_ACTION_BLOCKED,
                        'The satellite is missing the master vocabulary required for term '.(string)$term['label'].'.'
                    );
                    return false;
                }
                $parent = $parentID > 0 ? mysql__select_row($this->mysqli,
                    'SELECT trm_Domain,trm_OntID FROM defTerms WHERE trm_ID=?', ['i', $parentID]) : null;
                if ($parentID > 0 && (!$parent || (string)$parent[0] !== (string)($term['domain'] ?? ''))) {
                    $this->system->addError(HEURIST_INVALID_REQUEST, 'The term hierarchy returned by the master is invalid.');
                    return false;
                }
                $stmt = $this->mysqli->prepare('INSERT INTO defTerms '
                    .'(trm_Label,trm_Description,trm_Status,trm_OriginatingDBID,trm_NameInOriginatingDB,trm_IDInOriginatingDB,'
                    .'trm_AddedByImport,trm_IsLocalExtension,trm_Domain,trm_OntID,trm_ParentTermID) '
                    ."VALUES (?,?,'pending',?,?,?,1,1,?,?,?)");
                $label = (string)$term['label'];
                $description = $term['description'] ?? null;
                $originDBID = (int)$parts[0];
                $originTermID = (int)$parts[1];
                $domain = (string)$term['domain'];
                $ontologyID = $parent ? (int)$parent[1]
                    : (int)ConceptCode::getOntologyLocalID((string)($term['ontologyConceptID'] ?? ''));
                // Top-level vocabulary terms have no parent. Use SQL NULL rather
                // than 0 so databases enforcing fk_trm_ParentTermID accept them.
                $parentIDForInsert = $parentID > 0 ? $parentID : null;
                $stmt->bind_param('ssisisii', $label, $description, $originDBID, $label, $originTermID,
                    $domain, $ontologyID, $parentIDForInsert);
                $ok = $stmt->execute();
                $newID = (int)$stmt->insert_id;
                if (!$ok) $this->system->addError(
                    HEURIST_ACTION_BLOCKED,
                    'Unable to add term '.$label.' from the master.',
                    'MySQL '.$stmt->errno.': '.$stmt->error
                );
                $stmt->close();
                if (!$ok) return false;
                if (!empty($term['masterControlled']) && !$this->updateMasterTerm($newID, $term, $structureMaps)) return false;
                $resolved[$concept] = $newID;
                $createdConcepts[$concept] = true;
                unset($pending[$concept]);
                $created++;
                $madeProgress = true;
            }
            if (!$madeProgress) {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'The master term hierarchy contains an unresolved parent.');
                return false;
            }
        }

        foreach ($terms as $term) {
            $concept = (string)($term['conceptID'] ?? '');
            $inverseConcept = (string)($term['inverseConceptID'] ?? '');
            $termID = $resolved[$concept] ?? 0;
            $inverseID = $inverseConcept !== ''
                ? ($resolved[$inverseConcept] ?? (int)ConceptCode::getTermLocalID($inverseConcept)) : 0;
            if (!empty($term['masterControlled']) && $termID > 0) {
                $parentConcept = (string)($term['parentConceptID'] ?? '');
                $parentID = $parentConcept !== ''
                    ? ($resolved[$parentConcept] ?? (int)ConceptCode::getTermLocalID($parentConcept)) : 0;
                $ontologyID = (int)ConceptCode::getOntologyLocalID((string)($term['ontologyConceptID'] ?? ''));
                if ($ontologyID < 1 && $parentID > 0) {
                    $ontologyID = (int)mysql__select_value($this->mysqli,
                        'SELECT trm_OntID FROM defTerms WHERE trm_ID=?', ['i', $parentID]);
                }
                $this->mysqli->query('UPDATE defTerms SET trm_ParentTermID='.
                    ($parentID > 0 ? $parentID : 'NULL').',trm_InverseTermID='.
                    ($inverseID > 0 ? $inverseID : 'NULL').',trm_OntID='.$ontologyID.' WHERE trm_ID='.$termID);
            } elseif ($termID > 0 && $inverseID > 0 && !empty($createdConcepts[$concept])) {
                $this->mysqli->query('UPDATE defTerms SET trm_InverseTermID='.(int)$inverseID.' WHERE trm_ID='.(int)$termID);
            }
        }
        return $created;
    }

    private function updateMasterTerm(int $termID, array $term, array $structureMaps): bool
    {
        $groupID = 0;
        if (!empty($term['vocabularyGroupID'])) {
            $groupID = (int)($structureMaps['vocabularyGroups'][(int)$term['vocabularyGroupID']] ?? 0);
        }
        $label = (string)$term['label'];
        $description = $term['description'] ?? null;
        $status = (string)($term['status'] ?? 'open');
        $isLocal = (int)($term['isLocalExtension'] ?? 0);
        $code = $term['code'] ?? null;
        $semantic = $term['semanticReferenceURL'] ?? null;
        $illustration = $term['illustrationURL'] ?? null;
        $parts = explode('-', (string)($term['conceptID'] ?? ''), 2);
        $originDBID = (int)($parts[0] ?? 0);
        $originTermID = (int)($parts[1] ?? 0);
        $stmt = $this->mysqli->prepare('UPDATE defTerms SET trm_Label=?,trm_Description=?,trm_Status=?,'
            .'trm_IsLocalExtension=?,trm_VocabularyGroupID=?,trm_Code=?,trm_SemanticReferenceURL=?,trm_IllustrationURL=?,'
            .'trm_OriginatingDBID=?,trm_IDInOriginatingDB=?,trm_NameInOriginatingDB=? WHERE trm_ID=?');
        $stmt->bind_param('sssiisssiisi', $label, $description, $status, $isLocal, $groupID,
            $code, $semantic, $illustration, $originDBID, $originTermID, $label, $termID);
        $ok = $stmt->execute();
        if (!$ok) $this->system->addError(HEURIST_DB_ERROR, 'Unable to update master term '.$label.'.', $stmt->error);
        $stmt->close();
        return $ok;
    }

    private function applyFiles(array $files, array &$createdFiles)
    {
        $mapping = [];
        $created = 0;
        $uploadDir = $this->system->getSysDir(DIR_FILEUPLOADS);
        if (!$uploadDir || (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true))) {
            $this->system->addError(HEURIST_SYSTEM_CONFIG, 'The satellite file-upload directory is unavailable.');
            return false;
        }
        foreach ($files as $file) {
            $masterFileID = (int)($file['ulf_ID'] ?? 0);
            if ($masterFileID < 1) {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'A file supplied by the master has no ID.');
                return false;
            }
            $localFileID = (int)mysql__select_value($this->mysqli,
                'SELECT sif_LocalFileID FROM sysSyncInboundFileMap WHERE sif_MasterFileID=?', ['i', $masterFileID]);
            if ($localFileID > 0) {
                $mapping[$masterFileID] = $localFileID;
                continue;
            }

            $content = $file['contentBase64'] ?? null;
            $hash = (string)($file['contentHash'] ?? '');
            $bytes = null;
            if (is_string($content) && $content !== '') {
                $bytes = base64_decode($content, true);
                if ($bytes === false || $hash === '' || !hash_equals($hash, hash('sha256', $bytes))) {
                    $this->system->addError(HEURIST_INVALID_REQUEST, 'A file from the master failed its content hash check.');
                    return false;
                }
                $localFileID = (int)mysql__select_value($this->mysqli,
                    'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_MD5Checksum=? LIMIT 1', ['s', md5($bytes)]);
            }
            if ($localFileID < 1 && trim((string)($file['ulf_ExternalFileReference'] ?? '')) !== '') {
                $localFileID = (int)mysql__select_value($this->mysqli,
                    'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference=? LIMIT 1',
                    ['s', (string)$file['ulf_ExternalFileReference']]);
            }
            if ($localFileID < 1) {
                $storedName = null;
                $storedPath = null;
                if (is_string($bytes)) {
                    $extension = preg_replace('/[^A-Za-z0-9]/', '', (string)($file['ulf_MimeExt'] ?? ''));
                    $storedName = 'master_'.$masterFileID.'_'.substr($hash, 0, 16).($extension !== '' ? '.'.$extension : '');
                    $fullPath = $uploadDir.$storedName;
                    if (file_put_contents($fullPath, $bytes, LOCK_EX) === false) {
                        $this->system->addError(HEURIST_SYSTEM_CONFIG, 'Unable to store a file downloaded from the master.');
                        return false;
                    }
                    $createdFiles[] = $fullPath;
                    $storedPath = DIR_FILEUPLOADS;
                }
                $nonce = sha1('master:'.$masterFileID.':'.bin2hex(random_bytes(16)));
                $stmt = $this->mysqli->prepare('INSERT INTO recUploadedFiles '
                    .'(ulf_OrigFileName,ulf_UploaderUGrpID,ulf_Added,ulf_ObfuscatedFileID,ulf_ExternalFileReference,ulf_PreferredSource,'
                    .'ulf_Caption,ulf_Description,ulf_Copyright,ulf_Copyowner,ulf_MimeExt,ulf_AddedByImport,ulf_FileSizeKB,ulf_FilePath,'
                    .'ulf_FileName,ulf_Parameters,ulf_WhoCanView,ulf_MD5Checksum) '
                    .'VALUES (?,1,UTC_TIMESTAMP(),?,?,?,?,?,?,?,?,1,?,?,?,?,?,?)');
                $original = basename((string)($file['ulf_OrigFileName'] ?? 'synchronised-file'));
                $external = $file['ulf_ExternalFileReference'] ?? null;
                $preferred = (string)($file['ulf_PreferredSource'] ?? 'local');
                $caption = $file['ulf_Caption'] ?? null; $description = $file['ulf_Description'] ?? null;
                $copyright = $file['ulf_Copyright'] ?? null; $copyowner = $file['ulf_Copyowner'] ?? null;
                $mime = $file['ulf_MimeExt'] ?? null;
                $sizeKB = isset($file['ulf_FileSizeKB']) ? (int)$file['ulf_FileSizeKB'] : null;
                $parameters = $file['ulf_Parameters'] ?? null; $visibility = $file['ulf_WhoCanView'] ?? null;
                $md5 = is_string($bytes) ? md5($bytes) : null;
                $stmt->bind_param('sssssssssisssss', $original, $nonce, $external, $preferred, $caption, $description,
                    $copyright, $copyowner, $mime, $sizeKB, $storedPath, $storedName, $parameters, $visibility, $md5);
                $ok = $stmt->execute();
                $localFileID = (int)$stmt->insert_id;
                if (!$ok) $this->system->addError(HEURIST_DB_ERROR, 'Unable to register a file downloaded from the master.', $stmt->error);
                $stmt->close();
                if (!$ok) return false;
                $created++;
            }
            $stmt = $this->mysqli->prepare(
                'INSERT INTO sysSyncInboundFileMap (sif_MasterFileID,sif_LocalFileID,sif_ContentHash) VALUES (?,?,?)'
            );
            $stmt->bind_param('iis', $masterFileID, $localFileID, $hash);
            $ok = $stmt->execute();
            if (!$ok) $this->system->addError(HEURIST_DB_ERROR, 'Unable to save an inbound file mapping.', $stmt->error);
            $stmt->close();
            if (!$ok) return false;
            $mapping[$masterFileID] = $localFileID;
        }
        return ['created' => $created, 'mapping' => $mapping];
    }

    /** Create temporary pointer targets before recDetails/recLinks are inserted. */
    private function applyRecordShells(array $shells)
    {
        $created = 0;
        foreach ($shells as $record) {
            $recordID = (int)($record['rec_ID'] ?? 0);
            if ($recordID < 1 || mysql__select_value($this->mysqli,
                'SELECT rec_ID FROM Records WHERE rec_ID=?', ['i', $recordID])) continue;
            $recordTypeID = $this->resolveRecordTypeID($record);
            if ($recordTypeID < 1) {
                $this->system->addError(HEURIST_INVALID_REQUEST,
                    "Pointer target master record ID $recordID uses a record type missing from the satellite.");
                return false;
            }
            $stmt = $this->mysqli->prepare('INSERT INTO Records '
                .'(rec_ID,rec_AddedByUGrpID,rec_RecTypeID,rec_OwnerUGrpID,rec_NonOwnerVisibility,'
                .'rec_Added,rec_Modified,rec_AddedByImport,rec_FlagTemporary,rec_Title) '
                ."VALUES (?,1,?,1,'hidden',UTC_TIMESTAMP(),UTC_TIMESTAMP(),1,1,?)");
            $title = (string)($record['rec_Title'] ?? '');
            $stmt->bind_param('iis', $recordID, $recordTypeID, $title);
            $ok = $stmt->execute();
            if (!$ok) $this->system->addError(HEURIST_ACTION_BLOCKED,
                "Unable to create a temporary shell for pointer target master record ID $recordID.",
                'MySQL '.$stmt->errno.': '.$stmt->error);
            $stmt->close();
            if (!$ok) return false;
            $created++;
        }
        return $created;
    }

    private function applyRecords(array $records, array $fileMapping)
    {
        $applied = 0;
        // First create every record header in the batch. A resource field in an
        // earlier record may point to a record which occurs later in the batch.
        foreach ($records as $record) {
            $recordID = (int)($record['rec_ID'] ?? 0);
            $recordTypeID = $this->resolveRecordTypeID($record);
            if ($recordID < 1 || $recordTypeID < 1) {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'A master record uses a record type missing from the satellite.');
                return false;
            }
            if (!$this->ensureRecordExists($record, $recordTypeID)) return false;
        }
        foreach ($records as $record) {
            $recordID = (int)($record['rec_ID'] ?? 0);
            $recordTypeID = $this->resolveRecordTypeID($record);
            if ($recordID < 1 || $recordTypeID < 1) {
                $this->system->addError(HEURIST_INVALID_REQUEST, 'A master record uses a record type missing from the satellite.');
                return false;
            }
            $stmt = $this->mysqli->prepare('UPDATE Records SET rec_RecTypeID=?,rec_URL=?,rec_Added=?,rec_Title=?,rec_ScratchPad=?,'
                .'rec_AddedByImport=?,rec_NonOwnerVisibility=?,rec_FlagTemporary=0,rec_Modified=UTC_TIMESTAMP() WHERE rec_ID=?');
            $url = $record['rec_URL'] ?? null; $added = $record['rec_Added'] ?? null;
            $title = (string)($record['rec_Title'] ?? ''); $scratch = $record['rec_ScratchPad'] ?? null;
            $byImport = (int)($record['rec_AddedByImport'] ?? 1);
            $visibility = (string)($record['rec_NonOwnerVisibility'] ?? 'hidden');
            $stmt->bind_param('issssisi', $recordTypeID, $url, $added, $title, $scratch, $byImport, $visibility, $recordID);
            $ok = $stmt->execute();
            if (!$ok) $this->system->addError(HEURIST_DB_ERROR, "Unable to update master record $recordID on the satellite.", $stmt->error);
            $stmt->close();
            if (!$ok || !$this->mysqli->query('DELETE FROM recDetails WHERE dtl_RecID='.$recordID)) return false;

            foreach (($record['details'] ?? []) as $detail) {
                $dtyID = (int)ConceptCode::getDetailTypeLocalID((string)($detail['detailTypeConceptID'] ?? ''));
                if ($dtyID < 1 && (int)($detail['dtl_DetailTypeID'] ?? 0) > 0) {
                    $candidateID = (int)$detail['dtl_DetailTypeID'];
                    $candidateName = mysql__select_value($this->mysqli,
                        'SELECT dty_Name FROM defDetailTypes WHERE dty_ID=?', ['i', $candidateID]);
                    if (is_string($candidateName) && hash_equals($candidateName, (string)($detail['dty_Name'] ?? ''))) $dtyID = $candidateID;
                }
                $type = $dtyID > 0 ? mysql__select_value($this->mysqli,
                    'SELECT dty_Type FROM defDetailTypes WHERE dty_ID=?', ['i', $dtyID]) : null;
                if (!$type || $type !== ($detail['dty_Type'] ?? '')) {
                    $this->system->addError(HEURIST_INVALID_REQUEST, "A field in master record $recordID is missing from the satellite structure.");
                    return false;
                }
                $value = $detail['dtl_Value'] ?? null;
                if (in_array($type, ['enum', 'relationtype'], true) && !empty($detail['termConceptID'])) {
                    $value = (string)(int)ConceptCode::getTermLocalID((string)$detail['termConceptID']);
                    if ((int)$value < 1) {
                        $this->system->addError(HEURIST_INVALID_REQUEST, "A term in master record $recordID is missing from the satellite.");
                        return false;
                    }
                }
                $fileID = null;
                if (!empty($detail['satelliteFileID'])) {
                    $masterFileID = (int)$detail['satelliteFileID'];
                    $fileID = $fileMapping[$masterFileID] ?? (int)mysql__select_value($this->mysqli,
                        'SELECT sif_LocalFileID FROM sysSyncInboundFileMap WHERE sif_MasterFileID=?', ['i', $masterFileID]);
                    if ($fileID < 1) {
                        $this->system->addError(HEURIST_INVALID_REQUEST, "A file in master record $recordID was not downloaded.");
                        return false;
                    }
                }
                $stmt = $this->mysqli->prepare('INSERT INTO recDetails '
                    .'(dtl_RecID,dtl_DetailTypeID,dtl_Value,dtl_UploadedFileID,dtl_Geo,dtl_Certainty,dtl_Annotation,dtl_HideFromPublic,dtl_AddedByImport) '
                    .'VALUES (?,?,?,?,IF(? IS NULL,NULL,ST_GeomFromText(?)),?,?,?,1)');
                $geo = $detail['dtl_Geo'] ?? null; $certainty = (float)($detail['dtl_Certainty'] ?? 1);
                $annotation = $detail['dtl_Annotation'] ?? null;
                $hidden = isset($detail['dtl_HideFromPublic']) ? (int)$detail['dtl_HideFromPublic'] : null;
                $stmt->bind_param('iisissdsi', $recordID, $dtyID, $value, $fileID, $geo, $geo, $certainty, $annotation, $hidden);
                $ok = $stmt->execute();
                if (!$ok) {
                    $fieldName = (string)($detail['dty_Name'] ?? mysql__select_value($this->mysqli,
                        'SELECT dty_Name FROM defDetailTypes WHERE dty_ID=?', ['i', $dtyID]));
                    $recordTitle = trim((string)($record['rec_Title'] ?? ''));
                    $recordDescription = 'incoming master record ID '.$recordID
                        .($recordTitle !== '' ? ' ("'.$recordTitle.'")' : '');
                    $this->system->addError(
                        HEURIST_ACTION_BLOCKED,
                        'Unable to add field "'.$fieldName.'" to '.$recordDescription.'.',
                        'Master field ID '.(int)($detail['dtl_DetailTypeID'] ?? 0)
                            .'; local field ID '.$dtyID.'; field type '.$type
                            .($type === 'resource' ? '; pointer target master record ID '.(int)$value : '')
                            .'; MySQL '.$stmt->errno.': '.$stmt->error
                    );
                }
                $stmt->close();
                if (!$ok) return false;
            }
            $applied++;
        }
        return $applied;
    }

    private function ensureRecordExists(array $record, int $recordTypeID): bool
    {
        $recordID = (int)($record['rec_ID'] ?? 0);
        if (mysql__select_value($this->mysqli, 'SELECT rec_ID FROM Records WHERE rec_ID=?', ['i', $recordID])) return true;
        $stmt = $this->mysqli->prepare('INSERT INTO Records '
            .'(rec_ID,rec_AddedByUGrpID,rec_RecTypeID,rec_OwnerUGrpID,rec_NonOwnerVisibility,'
            .'rec_URL,rec_ScratchPad,rec_Added,rec_Modified,rec_AddedByImport,rec_FlagTemporary,rec_Title) '
            .'VALUES (?,1,?,1,?,?,?,?,UTC_TIMESTAMP(),?,0,?)');
        $visibility = (string)($record['rec_NonOwnerVisibility'] ?? 'hidden');
        $url = $record['rec_URL'] ?? null;
        $scratch = $record['rec_ScratchPad'] ?? null;
        $added = $record['rec_Added'] ?? gmdate('Y-m-d H:i:s');
        $byImport = (int)($record['rec_AddedByImport'] ?? 1);
        $title = (string)($record['rec_Title'] ?? '');
        $stmt->bind_param('iissssis', $recordID, $recordTypeID, $visibility, $url, $scratch, $added, $byImport, $title);
        $ok = $stmt->execute();
        if (!$ok) $this->system->addError(HEURIST_ACTION_BLOCKED,
            "Unable to create incoming master record ID $recordID on the satellite.",
            'MySQL '.$stmt->errno.': '.$stmt->error);
        $stmt->close();
        return $ok;
    }
}
