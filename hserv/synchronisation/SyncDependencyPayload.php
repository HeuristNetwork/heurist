<?php

namespace hserv\synchronisation;

use hserv\structure\ConceptCode;

/** Exports terms and files required before a record-content batch can be applied. */
final class SyncDependencyPayload
{
    private const MAX_FILE_BYTES = 20 * 1024 * 1024;
    private const MAX_BATCH_BYTES = 40 * 1024 * 1024;

    public static function export(\hserv\System $system, array $recordIDs): array
    {
        $mysqli = $system->getMysqli();
        $recordIDs = array_values(array_unique(array_filter(array_map('intval', $recordIDs))));
        if (!$recordIDs) return ['format' => 'heurist-sync-dependencies-1', 'recordIDs' => [],
            'recordShells' => [], 'terms' => [], 'files' => []];
        ConceptCode::setSystem($system);
        $ids = implode(',', $recordIDs);
        $termIDs = mysql__select_list2($mysqli,
            "SELECT DISTINCT CAST(d.dtl_Value AS UNSIGNED) FROM recDetails d JOIN defDetailTypes t ON t.dty_ID=d.dtl_DetailTypeID "
            ."WHERE d.dtl_RecID IN ($ids) AND t.dty_Type IN ('enum','relationtype') AND CAST(d.dtl_Value AS UNSIGNED)>0");
        $fileIDs = mysql__select_list2($mysqli,
            "SELECT DISTINCT d.dtl_UploadedFileID FROM recDetails d WHERE d.dtl_RecID IN ($ids) AND d.dtl_UploadedFileID IS NOT NULL");
        $pointerIDs = mysql__select_list2($mysqli,
            "SELECT DISTINCT CAST(d.dtl_Value AS UNSIGNED) FROM recDetails d "
            ."JOIN defDetailTypes t ON t.dty_ID=d.dtl_DetailTypeID "
            ."WHERE d.dtl_RecID IN ($ids) AND t.dty_Type='resource' AND CAST(d.dtl_Value AS UNSIGNED)>0");

        $recordShells = [];
        $pointerIDs = array_values(array_diff(array_unique(array_map('intval', $pointerIDs)), $recordIDs));
        if ($pointerIDs) {
            $pointerList = implode(',', $pointerIDs);
            $result = $mysqli->query('SELECT rec_ID,rec_RecTypeID,rec_Title FROM Records '
                ."WHERE rec_ID IN ($pointerList) AND rec_FlagTemporary=0");
            while ($result && ($row = $result->fetch_assoc())) {
                $recordTypeID = (int)$row['rec_RecTypeID'];
                $recordShells[] = [
                    'rec_ID' => (int)$row['rec_ID'],
                    'rec_RecTypeID' => $recordTypeID,
                    'recordTypeConceptID' => ConceptCode::getRecTypeConceptID($recordTypeID),
                    'recordTypeName' => (string)mysql__select_value($mysqli,
                        'SELECT rty_Name FROM defRecTypes WHERE rty_ID=?', ['i', $recordTypeID]),
                    'rec_Title' => (string)$row['rec_Title']
                ];
            }
            if ($result) $result->close();
        }

        $terms = self::exportTerms($mysqli, array_map('intval', $termIDs));

        $files = [];
        $totalBytes = 0;
        foreach (array_map('intval', $fileIDs) as $fileID) {
            $result = mysql__select_param_query($mysqli,
                'SELECT ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference,ulf_PreferredSource,ulf_Caption,ulf_Description,'
                .'ulf_Copyright,ulf_Copyowner,ulf_MimeExt,ulf_FileSizeKB,ulf_FilePath,ulf_FileName,ulf_Parameters,ulf_WhoCanView '
                .'FROM recUploadedFiles WHERE ulf_ID=?', ['i', $fileID]);
            $file = $result ? $result->fetch_assoc() : null;
            if ($result) $result->close();
            if (!$file) throw new \RuntimeException("File registration $fileID was not found.");
            $content = null;
            $hash = null;
            if (!empty($file['ulf_FilePath']) && !empty($file['ulf_FileName'])) {
                $root = realpath($system->getSysDir());
                $fullPath = realpath($system->getSysDir().$file['ulf_FilePath'].$file['ulf_FileName']);
                $rootPrefix = $root ? rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR : '';
                if (!$root || !$fullPath || strpos($fullPath, $rootPrefix) !== 0 || !is_file($fullPath)) {
                    throw new \RuntimeException('The local file for '.$file['ulf_OrigFileName'].' is missing or outside the database filestore.');
                }
                $size = filesize($fullPath);
                if ($size > self::MAX_FILE_BYTES || $totalBytes + $size > self::MAX_BATCH_BYTES) {
                    throw new \RuntimeException('The file batch is too large for direct synchronisation. Maximum file size is 20 MB and maximum batch size is 40 MB.');
                }
                $bytes = file_get_contents($fullPath);
                if ($bytes === false) throw new \RuntimeException('Unable to read '.$file['ulf_OrigFileName'].'.');
                $totalBytes += $size;
                $hash = hash('sha256', $bytes);
                $content = base64_encode($bytes);
            }
            if ($content === null && trim((string)($file['ulf_ExternalFileReference'] ?? '')) === '') {
                throw new \RuntimeException('File '.$file['ulf_OrigFileName'].' has neither readable local content nor an external reference.');
            }
            unset($file['ulf_FilePath'], $file['ulf_FileName']);
            $file['contentHash'] = $hash;
            $file['contentBase64'] = $content;
            $files[] = $file;
        }
        return ['format' => 'heurist-sync-dependencies-1', 'recordIDs' => $recordIDs,
            'recordShells' => $recordShells, 'terms' => $terms, 'files' => $files];
    }

    /** Complete master vocabulary snapshot used when structure is synchronised. */
    public static function exportAllTerms(\hserv\System $system): array
    {
        ConceptCode::setSystem($system);
        $mysqli = $system->getMysqli();
        $ids = mysql__select_list2($mysqli, 'SELECT trm_ID FROM defTerms ORDER BY trm_Depth,trm_ID');
        return self::exportTerms($mysqli, array_map('intval', $ids), true);
    }

    private static function exportTerms(\mysqli $mysqli, array $termIDs, bool $masterControlled = false): array
    {
        $terms = [];
        $seen = [];
        $queue = $termIDs;
        while ($queue) {
            $termID = array_shift($queue);
            if ($termID < 1 || isset($seen[$termID])) continue;
            $seen[$termID] = true;
            $row = mysql__select_row($mysqli,
                'SELECT trm_ID,trm_Label,trm_InverseTermID,trm_Description,trm_Domain,trm_ParentTermID,trm_IsLocalExtension,'
                .'trm_Status,trm_Code,trm_SemanticReferenceURL,trm_IllustrationURL,trm_VocabularyGroupID,trm_OntID '
                .'FROM defTerms WHERE trm_ID=?', ['i', $termID]);
            if (!$row) continue;
            $parentID = (int)$row[5];
            $inverseID = (int)$row[2];
            $terms[] = [
                'sourceTermID' => (int)$row[0],
                'conceptID' => ConceptCode::getTermConceptID((int)$row[0]),
                'label' => (string)$row[1],
                'description' => $row[3],
                'domain' => (string)$row[4],
                'parentConceptID' => $parentID > 0 ? ConceptCode::getTermConceptID($parentID) : null,
                'inverseConceptID' => $inverseID > 0 ? ConceptCode::getTermConceptID($inverseID) : null,
                'isLocalExtension' => (int)$row[6],
                'status' => (string)$row[7],
                'code' => $row[8],
                'semanticReferenceURL' => $row[9],
                'illustrationURL' => $row[10],
                'vocabularyGroupID' => (int)$row[11],
                'ontologyConceptID' => (int)$row[12] > 0 ? ConceptCode::getOntologyConceptID((int)$row[12]) : null,
                'masterControlled' => $masterControlled
            ];
            if ($parentID > 0) $queue[] = $parentID;
            if ($inverseID > 0) $queue[] = $inverseID;
        }
        return $terms;
    }
}
