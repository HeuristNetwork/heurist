<?php
/**
* PresentationRecordRepository.php - Read-only presentation record access
*
* Loads Dataset, Map Document, Map Layer and linked source records directly
* from Records and recDetails without DbEntityBase inheritance.
*
* @project     Heurist academic knowledge management system
* @package     Records\Presentation
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Records\Presentation;

use Heurist\Database\DatabaseInterface;
use Heurist\Runtime\RuntimeContext;
use Heurist\Runtime\SystemCode;

/** Provides the minimal read operations shared by presentation services. */
final class PresentationRecordRepository
{
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private SystemCode $codes;

    /** Initialise direct, read-only presentation record access. */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        SystemCode $codes
    ) {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->codes = $codes;
    }

    /** Load one visible record and all of its ordered detail values. */
    public function getPublicRecord(int $recordId, ?string $recordTypeCode = null): ?array
    {
        if($recordId < 1){ return null; }
        $parameters = array($recordId);
        $access = 'rec_FlagTemporary=0';
        if($this->runtime->userId < 1){
            $access .= ' AND rec_NonOwnerVisibility IN ("public","pending")';
        }elseif(!$this->runtime->isDbOwner){
            $groups = $this->runtime->groupIds;
            $groups[] = $this->runtime->userId;
            $groups = array_values(array_unique(array_filter(array_map('intval', $groups))));
            $visible = array('rec_NonOwnerVisibility IN ("public","pending")');
            if(!empty($groups)){
                $placeholders = implode(',', array_fill(0, count($groups), '?'));
                $visible[] = 'rec_OwnerUGrpID IN ('.$placeholders.')';
                array_push($parameters, ...$groups);
                $visible[] = '(rec_NonOwnerVisibility="viewable" AND ('
                    .'NOT EXISTS (SELECT 1 FROM usrRecPermissions rp0 WHERE rp0.rcp_RecID=rec_ID) OR '
                    .'EXISTS (SELECT 1 FROM usrRecPermissions rp WHERE rp.rcp_RecID=rec_ID '
                    .'AND rp.rcp_UGrpID IN ('.$placeholders.'))))';
                array_push($parameters, ...$groups);
            }
            $access .= ' AND ('.implode(' OR ', $visible).')';
        }
        $rows = $this->database->fetchAll(
            'SELECT rec_ID,rec_RecTypeID,rec_Title FROM Records WHERE rec_ID=? AND '.$access.' LIMIT 1',
            $parameters
        );
        if(empty($rows)){ return null; }
        $record = $rows[0];
        if($recordTypeCode !== null){
            $requiredType = $this->codes->id($recordTypeCode);
            if($requiredType < 1 || intval($record['rec_RecTypeID']) !== $requiredType){ return null; }
        }
        $record['details'] = array();
        foreach($this->database->fetchRows(
            'SELECT dtl_DetailTypeID,COALESCE(ST_AsText(dtl_Geo),dtl_Value,CAST(dtl_UploadedFileID AS CHAR)) '
            .'FROM recDetails WHERE dtl_RecID=? ORDER BY dtl_ID', array($recordId)
        ) as $row){
            $record['details'][intval($row[0])][] = $row[1];
        }
        return $record;
    }

    /** Resolve a Dataset query source or return the Dataset itself. */
    public function getQuerySource(array $dataset): ?array
    {
        $sourceId = intval($this->value($dataset, 'DT_DATA_SOURCE'));
        return $sourceId < 1 ? $dataset : $this->getPublicRecord($sourceId, 'RT_QUERY_SOURCE');
    }

    /** Resolve a Map Layer data source or return the layer itself. */
    public function getDataSource(array $layer): ?array
    {
        $sourceId = intval($this->value($layer, 'DT_DATA_SOURCE'));
        return $sourceId < 1 ? $layer : $this->getPublicRecord($sourceId);
    }

    /** Return the first value for a symbolic or numeric detail type. */
    public function value(array $record, $field)
    {
        $values = $this->values($record, $field);
        return $values[0] ?? null;
    }

    /** Return all ordered values for a symbolic or numeric detail type. */
    public function values(array $record, $field): array
    {
        $fieldId = is_numeric($field) ? intval($field) : $this->codes->id((string)$field);
        return $fieldId > 0 ? array_values($record['details'][$fieldId] ?? array()) : array();
    }

    /** Return the term code stored in the current definition schema. */
    public function getTermCode(int $termId): ?string
    {
        $value = $this->database->fetchValue(
            'SELECT trm_Code FROM defTerms WHERE trm_ID=? LIMIT 1', array($termId)
        );
        return $value === null ? null : (string)$value;
    }

    /** Return the term label stored in the current definition schema. */
    public function getTermLabel(int $termId): ?string
    {
        $value = $this->database->fetchValue(
            'SELECT trm_Label FROM defTerms WHERE trm_ID=? LIMIT 1', array($termId)
        );
        return $value === null ? null : (string)$value;
    }

    /** Return the file metadata required by tile and image presentation. */
    public function getUploadedFile($fileId): ?array
    {
        if(!is_numeric($fileId) || intval($fileId) < 1){ return null; }
        $rows = $this->database->fetchAll(
            'SELECT ulf_ID,ulf_ObfuscatedFileID,ulf_ExternalFileReference,ulf_MimeExt '
            .'FROM recUploadedFiles WHERE ulf_ID=? LIMIT 1', array(intval($fileId))
        );
        return $rows[0] ?? null;
    }

    /** Resolve a symbolic system code for source-type dispatch. */
    public function codeId(string $name): int
    {
        return $this->codes->id($name);
    }
}
