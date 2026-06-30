<?php
/**
* IiifAnnotationImportWriter.php - fast direct writer for IIIF annotation import.
*/
namespace hserv\records\import;

use hserv\entity\DbAnnotations;

/**
 * Import-only persistence helper for IIIF annotation records.
 *
 * This class deliberately bypasses generic recordSave() and writes Records / recDetails
 * directly. It should be used only by the IIIF Manifest import workflow where
 * DbAnnotations has already parsed and normalised the incoming Web Annotation.
 */
class IiifAnnotationImportWriter
{
    /** @var \hserv\System */
    private $system;

    /** @var DbAnnotations */
    private $dbAnno;

    /** @var bool */
    private $ready = false;

    /** @var array|null dty_ID => dty_Type */
    private $detailTypes = null;

    /** @var array|null */
    private $recordDefaults = null;

    /** @var \mysqli_stmt|null */
    private $insertRecordStmt = null;

    /** @var \mysqli_stmt|null */
    private $updateRecordStmt = null;

    /** @var \mysqli_stmt|null */
    private $deleteDetailsStmt = null;

    /** @var \mysqli_stmt|null */
    private $insertDetailValueStmt = null;

    /** @var \mysqli_stmt|null */
    private $insertDetailFileStmt = null;

    /** @var \mysqli_stmt|null */
    private $deleteOneDetailStmt = null;

    public function __construct($system, DbAnnotations $dbAnno)
    {
        $this->system = $system;
        $this->dbAnno = $dbAnno;
    }

    /** Prepare permission/definitions and reusable SQL statements once before a bulk loop. */
    public function begin(bool $checkReady=true): bool
    {
        if($this->ready){
            return true;
        }

        if($checkReady && !$this->dbAnno->ensureImportReady()){
            return false;
        }

        $this->detailTypes = null;
        $this->recordDefaults = null;

        if(!$this->prepareStatements()){
            $this->end();
            return false;
        }

        $this->ready = true;
        return true;
    }

    /** Close reusable SQL statements after a bulk loop. */
    public function end(): void
    {
        foreach(array(
            'insertRecordStmt',
            'updateRecordStmt',
            'deleteDetailsStmt',
            'insertDetailValueStmt',
            'insertDetailFileStmt',
            'deleteOneDetailStmt'
        ) as $prop){
            if($this->$prop){
                $this->$prop->close();
                $this->$prop = null;
            }
        }

        $this->ready = false;
        $this->detailTypes = null;
        $this->recordDefaults = null;
    }

    /** Parse and save one annotation context. Used by IiifManifestImporter. */
    public function save(array $annotationContext, bool $createThumbnail=false)
    {
        if(!$this->begin(false)){
            return false;
        }

        $prepared = $this->dbAnno->prepareImportedAnnotation($annotationContext, $createThumbnail, 0, true);
        if($prepared===false){
            return false;
        }
        if(!empty($prepared['response'])){
            return $prepared['response'];
        }

        return $this->savePreparedAnnotation($prepared);
    }

    /** Persist a prepared annotation package returned by DbAnnotations::prepareImportedAnnotation(). */
    public function savePreparedAnnotation(array $prepared)
    {
        if(!$this->begin(false)){
            return false;
        }

        $recordId = intval($prepared['record_id'] ?? 0);
        $details = $prepared['details'] ?? null;
        if(!is_array($details)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Prepared annotation details are not defined');
            return false;
        }

        $isNew = ($recordId < 1);
        $rectype = intval($this->dbAnno->annotationRecordTypeId());
        if($rectype < 1){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'IIIF Annotation record type is not defined');
            return false;
        }

        $title = $this->annotationTitle($details);
        $now = date(DATE_8601);
        $mysqli = $this->system->getMysqli();

        if($isNew){
            $defaults = $this->recordDefaults();
            $addedBy = intval($this->system->getUserId());
            $owner = intval($defaults['owner']);
            $access = (string)$defaults['access'];

            $this->insertRecordStmt->bind_param(
                'iiissss',
                $addedBy,
                $rectype,
                $owner,
                $access,
                $now,
                $now,
                $title
            );

            if(!$this->insertRecordStmt->execute()){
                $this->system->addError(HEURIST_DB_ERROR, 'Cannot insert annotation record', $this->insertRecordStmt->error ?: $mysqli->error);
                return false;
            }

            $recordId = intval($this->insertRecordStmt->insert_id);
            if($recordId < 1){
                $this->system->addError(HEURIST_DB_ERROR, 'Cannot insert annotation record', $mysqli->error);
                return false;
            }
        }else{
            $this->updateRecordStmt->bind_param('ssi', $now, $title, $recordId);
            if(!$this->updateRecordStmt->execute()){
                $this->system->addError(HEURIST_DB_ERROR, 'Cannot update annotation record', $this->updateRecordStmt->error ?: $mysqli->error);
                return false;
            }

            $this->deleteDetailsStmt->bind_param('i', $recordId);
            if(!$this->deleteDetailsStmt->execute()){
                $this->system->addError(HEURIST_DB_ERROR, 'Cannot delete old annotation details', $this->deleteDetailsStmt->error ?: $mysqli->error);
                return false;
            }
        }

        if(!$this->insertDetails($recordId, $details)){
            return false;
        }

        if(!$this->assignIiifId($recordId)){
            return false;
        }

        return array(
            'status' => HEURIST_OK,
            'data' => intval($recordId),
            'rec_Title' => $title,
            'is_new' => $isNew
        );
    }

    private function prepareStatements(): bool
    {
        $mysqli = $this->system->getMysqli();

        $this->insertRecordStmt = $mysqli->prepare(
            'INSERT INTO Records '
            .'(rec_AddedByUGrpID, rec_RecTypeID, rec_OwnerUGrpID, rec_NonOwnerVisibility, '
            .'rec_Added, rec_Modified, rec_AddedByImport, rec_FlagTemporary, rec_Title) '
            .'VALUES (?, ?, ?, ?, ?, ?, 1, 0, ?)'
        );
        if(!$this->insertRecordStmt){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot prepare annotation record insert', $mysqli->error);
            return false;
        }

        $this->updateRecordStmt = $mysqli->prepare(
            'UPDATE Records SET rec_Modified=?, rec_Title=? WHERE rec_ID=?'
        );
        if(!$this->updateRecordStmt){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot prepare annotation record update', $mysqli->error);
            return false;
        }

        $this->deleteDetailsStmt = $mysqli->prepare(
            'DELETE FROM recDetails WHERE dtl_RecID=?'
        );
        if(!$this->deleteDetailsStmt){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot prepare annotation detail delete', $mysqli->error);
            return false;
        }

        $this->insertDetailValueStmt = $mysqli->prepare(
            'INSERT INTO recDetails '
            .'(dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport, dtl_UploadedFileID) '
            .'VALUES (?, ?, ?, 1, NULL)'
        );
        if(!$this->insertDetailValueStmt){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot prepare annotation detail insert', $mysqli->error);
            return false;
        }

        $this->insertDetailFileStmt = $mysqli->prepare(
            'INSERT INTO recDetails '
            .'(dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport, dtl_UploadedFileID) '
            .'VALUES (?, ?, NULL, 1, ?)'
        );
        if(!$this->insertDetailFileStmt){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot prepare annotation file detail insert', $mysqli->error);
            return false;
        }

        $this->deleteOneDetailStmt = $mysqli->prepare(
            'DELETE FROM recDetails WHERE dtl_RecID=? AND dtl_DetailTypeID=?'
        );
        if(!$this->deleteOneDetailStmt){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot prepare annotation detail replacement', $mysqli->error);
            return false;
        }

        return true;
    }

    private function insertDetails(int $recordId, array $details): bool
    {
        $types = $this->detailTypes();

        foreach($details as $dtyID => $values){
            $dtyID = intval($dtyID);
            if($dtyID < 1){
                continue;
            }
            if(!is_array($values)){
                $values = array($values);
            }

            $dtyType = strtolower((string)($types[$dtyID] ?? ''));
            foreach($values as $value){
                if(is_array($value)){
                    $value = $value['dtl_Value'] ?? $value['value'] ?? reset($value);
                }
                if($value === null || $value === ''){
                    continue;
                }

                if($dtyType === 'file'){
                    $uploadedFileId = intval($value);
                    if($uploadedFileId < 1){
                        continue;
                    }
                    $this->insertDetailFileStmt->bind_param('iii', $recordId, $dtyID, $uploadedFileId);
                    if(!$this->insertDetailFileStmt->execute()){
                        $this->system->addError(HEURIST_DB_ERROR, 'Cannot insert annotation file detail', $this->insertDetailFileStmt->error ?: $this->system->getMysqli()->error);
                        return false;
                    }
                }else{
                    $dtlValue = trim((string)$value);
                    if($dtlValue === ''){
                        continue;
                    }
                    $this->insertDetailValueStmt->bind_param('iis', $recordId, $dtyID, $dtlValue);
                    if(!$this->insertDetailValueStmt->execute()){
                        $this->system->addError(HEURIST_DB_ERROR, 'Cannot insert annotation detail', $this->insertDetailValueStmt->error ?: $this->system->getMysqli()->error);
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /** Replace DT_IIIF_ID with canonical Heurist Annotation API URL after rec_ID exists. */
    private function assignIiifId(int $recordId): bool
    {
        if($recordId < 1 || !defined('DT_IIIF_ID')){
            return true;
        }

        $dtyID = intval(DT_IIIF_ID);
        $this->deleteOneDetailStmt->bind_param('ii', $recordId, $dtyID);
        if(!$this->deleteOneDetailStmt->execute()){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot replace annotation IIIF id', $this->deleteOneDetailStmt->error ?: $this->system->getMysqli()->error);
            return false;
        }

        $iiifId = $this->annotationApiUrl($recordId);
        $this->insertDetailValueStmt->bind_param('iis', $recordId, $dtyID, $iiifId);
        if(!$this->insertDetailValueStmt->execute()){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot insert annotation IIIF id', $this->insertDetailValueStmt->error ?: $this->system->getMysqli()->error);
            return false;
        }

        return true;
    }

    private function detailTypes(): array
    {
        if(is_array($this->detailTypes)){
            return $this->detailTypes;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', array(
            defined('DT_NAME') ? DT_NAME : 0,
            defined('DT_SHORT_SUMMARY') ? DT_SHORT_SUMMARY : 0,
            defined('DT_EXTENDED_DESCRIPTION') ? DT_EXTENDED_DESCRIPTION : 0,
            defined('DT_URL') ? DT_URL : 0,
            defined('DT_IIIF_ID') ? DT_IIIF_ID : 0,
            defined('DT_ORIGINAL_IIIF_ID') ? DT_ORIGINAL_IIIF_ID : 0,
            defined('DT_ANNOTATION_INFO') ? DT_ANNOTATION_INFO : 0,
            defined('DT_ANNOTATION_MANIFEST') ? DT_ANNOTATION_MANIFEST : 0,
            defined('DT_IIIF_CANVAS') ? DT_IIIF_CANVAS : 0,
            defined('DT_ANNOTATION_STATE') ? DT_ANNOTATION_STATE : 0,
            defined('DT_ANNOTATION_MOTIVATION') ? DT_ANNOTATION_MOTIVATION : 0,
            defined('DT_ANNOTATION_SELECTOR_TYPE') ? DT_ANNOTATION_SELECTOR_TYPE : 0,
            defined('DT_ANNOTATION_SELECTOR_VALUE') ? DT_ANNOTATION_SELECTOR_VALUE : 0,
            defined('DT_LANGUAGE') ? DT_LANGUAGE : 0,
            defined('DT_THUMBNAIL') ? DT_THUMBNAIL : 0,
            defined('DT_FILE_RESOURCE') ? DT_FILE_RESOURCE : 0
        )))));

        $this->detailTypes = array();
        if(!empty($ids)){
            $query = 'SELECT dty_ID, dty_Type FROM defDetailTypes WHERE dty_ID IN ('.implode(',', $ids).')';
            $this->detailTypes = mysql__select_assoc2($this->system->getMysqli(), $query) ?: array();
        }
        return $this->detailTypes;
    }

    private function recordDefaults(): array
    {
        if(is_array($this->recordDefaults)){
            return $this->recordDefaults;
        }

        $owner = intval($this->system->settings->get('sys_NewRecOwnerGrpID'));
        if($owner < 1){
            $owner = intval($this->system->getUserId());
        }
        if($owner < 1){
            $owner = 2;
        }

        $access = (string)$this->system->settings->get('sys_NewRecAccess');
        if($access === ''){
            $access = 'viewable';
        }

        $this->recordDefaults = array('owner'=>$owner, 'access'=>$access);
        return $this->recordDefaults;
    }

    private function annotationTitle(array $details): string
    {
        $title = trim(strip_tags((string)$this->firstDetailValue($details, defined('DT_NAME') ? DT_NAME : 0)));
        if($title === ''){
            $title = trim(strip_tags((string)$this->firstDetailValue($details, defined('DT_SHORT_SUMMARY') ? DT_SHORT_SUMMARY : 0)));
        }
        if($title === ''){
            $title = 'IIIF Annotation';
        }
        if(mb_strlen($title) > 1023){
            $title = mb_substr($title, 0, 1023);
        }
        return $title;
    }

    private function firstDetailValue(array $details, int $dtyID)
    {
        if($dtyID < 1 || empty($details[$dtyID])){
            return null;
        }
        $values = $details[$dtyID];
        if(!is_array($values)){
            return $values;
        }
        $value = reset($values);
        if(is_array($value)){
            return $value['dtl_Value'] ?? $value['value'] ?? reset($value);
        }
        return $value;
    }

    private function annotationApiUrl(int $recID): string
    {
        $baseUrl = rtrim(defined('HEURIST_BASE_URL_PRO') ? HEURIST_BASE_URL_PRO : HEURIST_BASE_URL, '/');
        return $baseUrl
            .'/api/'.$this->system->dbname()
            .'/annotations/'.intval($recID);
    }
}
?>
