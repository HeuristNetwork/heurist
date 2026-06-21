<?php
/**
* DbIiifCanvas.php - Record-type-backed entity for RT_IIIF_CANVAS.
*/
namespace hserv\entity;

use hserv\structure\ConceptCode;
use hserv\iiif\IiifMediaHelper;

require_once dirname(__FILE__).'/DbRecordTypeEntity.php';
require_once dirname(__FILE__).'/DbRecUploadedFiles.php';
require_once dirname(__FILE__).'/../iiif/IiifMediaHelper.php';

/**
 * Manages IIIF Canvas records stored as user records of RT_IIIF_CANVAS.
 *
 * Imports Canvas identity/order/metadata and registers external painting/thumbnail
 * resources as recUploadedFiles external resources where possible.
 */
class DbIiifCanvas extends DbRecordTypeEntity
{
    protected function initRecordTypeEntity(): void
    {
        $this->recordTypeConst = 'RT_IIIF_CANVAS';
        $this->recordTypeConceptCode = '2-111';

        $this->requiredConstants = array(
            'RT_IIIF_CANVAS',
            'DT_NAME',
            'DT_SHORT_SUMMARY',
            'DT_FILE_RESOURCE',
            'DT_THUMBNAIL',
            'DT_IIIF_ID',
            'DT_ORIGINAL_IIIF_ID',
            'DT_ANNOTATION_STATE'
        );

        // Reuse the existing IIIF state vocabulary until DT_ANNOTATION_STATE is renamed to DT_IIIF_STATE.
        $this->requiredTermConstants = array(
            'TRM_ANNOTATION_STATE_IMPORTED' => '2-10426',
            'TRM_ANNOTATION_STATE_MIRADOR'  => '2-10427',
            'TRM_ANNOTATION_STATE_HEURIST'  => '2-10428',
            'TRM_ANNOTATION_STATE_MODIFIED' => '2-10429',
            'TRM_ANNOTATION_STATE_OBSOLETE' => '2-10430',
            'TRM_ANNOTATION_STATE_REMOVED'  => '2-10431'
        );
    }

    /** Quietly check only the constants needed for lookup/read helpers. */
    private function lookupDefinitionsReady(array $requiredConstants): bool
    {
        $this->defineRequiredConstants(false);
        foreach($requiredConstants as $name){
            if(!defined($name) || intval(constant($name))<1){
                return false;
            }
        }
        return true;
    }

    /**
     * Create or update one RT_IIIF_CANVAS record from a v2/v3 Canvas JSON object.
     *
     * Canvas identity rules:
     * - DT_IIIF_ID is always the canonical Heurist Canvas API URL based on DT_FILE_RESOURCE.
     * - DT_ORIGINAL_IIIF_ID stores the source Canvas id from an imported Manifest.
     * - matching during import is by DT_FILE_RESOURCE first, then DT_ORIGINAL_IIIF_ID.
     *
     * Returns an array compatible with the annotation import reporting flags:
     *   recID, is_new, is_retained, is_preserved_local
     */
    public function ensureFromCanvas(array $canvas, bool $preserveLocal=true)
    {
        if(!$this->ensureDefinitionsReady($this->system->isAdmin())){
            return false;
        }

        $originalCanvasId = $this->getJsonId($canvas);
        if(!$originalCanvasId){
            $originalCanvasId = 'heurist-import-canvas-'.md5(json_encode($canvas));
        }

        $mediaUrl = $this->extractPrimaryPaintingBodyUrl($canvas);
        $mediaUlfID = $mediaUrl ? $this->registerExternalUrl($mediaUrl) : 0;

        $recordId = $this->findCanvasRecord($mediaUlfID, $originalCanvasId);
        $details = $recordId>0 ? $this->loadRecordDetails($recordId) : array();

        if($recordId>0 && $preserveLocal && $this->isProtectedFromReimport($details)){
            return array('recID'=>$recordId, 'is_preserved_local'=>true);
        }

        $oldDetails = $details;
        $this->fillDetailsFromCanvas($details, $canvas, $originalCanvasId, $mediaUlfID);

        if($recordId>0 && $oldDetails == $details){
            return array('recID'=>$recordId, 'is_retained'=>true);
        }

        $res = $this->saveCanvasRecordDetails($recordId, $details, 0);
        if(!is_array($res) || @$res['status']!=HEURIST_OK || intval(@$res['data'])<1){
            if(is_array($res) && @$res['message']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, $res['message']);
            }
            return false;
        }

        $newId = intval($res['data']);
        return array('recID'=>$newId, 'is_new'=>($recordId==0));
    }

    /** Return canonical Heurist Canvas API URL for an RT_IIIF_CANVAS record. */
    public function canonicalCanvasUrlForCanvasRecord(int $canvasRecID): ?string
    {
        if($canvasRecID<1 || !$this->lookupDefinitionsReady(array('RT_IIIF_CANVAS', 'DT_FILE_RESOURCE'))){
            return null;
        }
        $details = $this->loadRecordDetails($canvasRecID, array('DT_FILE_RESOURCE'));
        $ulfID = intval($this->getFirstDetailValue($details, 'DT_FILE_RESOURCE'));
        return $this->canonicalCanvasUrlForFileID($ulfID);
    }

    /** Return canonical Heurist Canvas API URL for a recUploadedFiles row. */
    public function canonicalCanvasUrlForFileID(int $ulfID): ?string
    {
        if($ulfID<1){
            return null;
        }
        $fileID = $this->obfuscatedFileIDForUlfID($ulfID);
        return $fileID ? $this->canonicalCanvasUrlForObfuscatedID($fileID) : null;
    }

    /** Return canonical Heurist Canvas API URL for a file obfuscated id. */
    public function canonicalCanvasUrlForObfuscatedID(string $fileID): string
    {
        return rtrim(HEURIST_BASE_URL, '/')
            .'/api/'.$this->system->dbname()
            .'/iiif/canvas/'.rawurlencode($fileID);
    }

    /** Extract the registered-file obfuscated id from a canonical Heurist Canvas API URL. */
    public function fileObfuscatedIDFromCanonicalCanvasUrl(string $canvasUrl): ?string
    {
        $path = parse_url($canvasUrl, PHP_URL_PATH);
        if(!$path){
            return null;
        }
        if(preg_match('~/api/[^/]+/iiif/canvas/([^/?#]+)$~', $path, $m)){
            return rawurldecode($m[1]);
        }
        return null;
    }

    /** Find RT_IIIF_CANVAS records by their canonical Heurist Canvas API URL stored in DT_IIIF_ID. */
    public function canvasRecordsForCanonicalUrl(string $canvasUrl): array
    {
        $canvasUrl = trim($canvasUrl);
        if($canvasUrl==='' || !$this->lookupDefinitionsReady(array('RT_IIIF_CANVAS', 'DT_IIIF_ID'))){
            return array();
        }

        $mysqli = $this->system->getMysqli();
        $query = 'SELECT DISTINCT r.rec_ID FROM Records r, recDetails d '
            .'WHERE r.rec_ID=d.dtl_RecID AND r.rec_RecTypeID='.intval($this->recordTypeId())
            .' AND d.dtl_DetailTypeID='.intval(DT_IIIF_ID)
            .' AND d.dtl_Value="'.$mysqli->real_escape_string($canvasUrl).'"'
            .' ORDER BY r.rec_ID';
        return mysql__select_list2($mysqli, $query) ?: array();
    }

    /** Find RT_IIIF_CANVAS records that use the given registered file. */
    public function canvasRecordsForFileID(int $ulfID): array
    {
        if($ulfID<1 || !$this->lookupDefinitionsReady(array('RT_IIIF_CANVAS', 'DT_FILE_RESOURCE'))){
            return array();
        }
        $mysqli = $this->system->getMysqli();
        $query = 'SELECT DISTINCT r.rec_ID FROM Records r, recDetails d '
            .'WHERE r.rec_ID=d.dtl_RecID AND r.rec_RecTypeID='.intval($this->recordTypeId())
            .' AND d.dtl_DetailTypeID='.intval(DT_FILE_RESOURCE)
            .' AND (d.dtl_UploadedFileID='.intval($ulfID).' OR d.dtl_Value="'.intval($ulfID).'")'
            .' ORDER BY r.rec_ID';
        return mysql__select_list2($mysqli, $query) ?: array();
    }


    /** Return the first RT_IIIF_CANVAS record details that describe the given registered file. */
    public function canvasMetadataForFileID(int $ulfID): ?array
    {
        $ids = $this->canvasRecordsForFileID($ulfID);
        if(empty($ids)){
            return null;
        }

        $recID = intval($ids[0]);
        $details = $this->loadRecordDetails($recID, array(
            'DT_NAME',
            'DT_SHORT_SUMMARY',
            'DT_FILE_RESOURCE',
            'DT_THUMBNAIL',
            'DT_IIIF_ID',
            'DT_ORIGINAL_IIIF_ID',
            $this->detailId('DT_WIDTH', '3-1040'),
            $this->detailId('DT_HEIGHT', '3-1041'),
            $this->detailId('DT_DURATION', '2-66')
        ));

        $thumbnailUlfID = intval($this->getFirstDetailValue($details, 'DT_THUMBNAIL'));
        $thumbnailInfo = $thumbnailUlfID>0 ? IiifMediaHelper::registeredFileInfoForUlfID($this->system, $thumbnailUlfID) : null;

        return array(
            'recID' => $recID,
            'label' => $this->getFirstDetailValue($details, 'DT_NAME'),
            'summary' => $this->getFirstDetailValue($details, 'DT_SHORT_SUMMARY'),
            'thumbnail_ulf_id' => $thumbnailUlfID,
            'thumbnail_fileinfo' => $thumbnailInfo,
            'canvas_url' => $this->canonicalCanvasUrlForFileID($ulfID),
            'dimensions' => $this->dimensionValuesFromDetails($details)
        );
    }

    /** Return width/height/duration values stored on an RT_IIIF_CANVAS record detail array. */
    public function dimensionValuesFromDetails(array $details): array
    {
        return array(
            'width' => $this->getNumericDetailValueByConstOrCode($details, 'DT_WIDTH', '3-1040'),
            'height' => $this->getNumericDetailValueByConstOrCode($details, 'DT_HEIGHT', '3-1041'),
            'duration' => $this->getNumericDetailValueByConstOrCode($details, 'DT_DURATION', '2-66')
        );
    }

    private function getNumericDetailValueByConstOrCode(array $details, string $constName, string $conceptCode): ?float
    {
        $dtID = $this->constId($constName);

        if(!$dtID && $conceptCode !== ''){
            $dtID = ConceptCode::getDetailTypeLocalID($conceptCode);
        }

        if(!$dtID || empty($details[$dtID])){
            return null;
        }

        $value = $details[$dtID][0];

        if($value === null || $value === '' || !is_numeric($value)){
            return null;
        }

        return floatval($value);
    }

    /** Find a registered-file ulf_ID by its obfuscated file id. */
    public function ulfIDFromObfuscatedID(string $fileID): int
    {
        $fileID = trim($fileID);
        if($fileID===''){
            return 0;
        }
        $mysqli = $this->system->getMysqli();
        $ulfID = mysql__select_value($mysqli,
            'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ObfuscatedFileID="'
            .$mysqli->real_escape_string($fileID).'" LIMIT 1');
        return $ulfID ? intval($ulfID) : 0;
    }

    private function obfuscatedFileIDForUlfID(int $ulfID): ?string
    {
        if($ulfID<1){
            return null;
        }
        $mysqli = $this->system->getMysqli();
        $fileID = mysql__select_value($mysqli,
            'SELECT ulf_ObfuscatedFileID FROM recUploadedFiles WHERE ulf_ID='.intval($ulfID).' LIMIT 1');
        return $fileID ? (string)$fileID : null;
    }

    private function findCanvasRecord(int $mediaUlfID=0, ?string $originalCanvasId=null): int
    {
        if(!$this->ensureDefinitionsReady(false)){
            return 0;
        }

        if($mediaUlfID>0){
            $ids = $this->canvasRecordsForFileID($mediaUlfID);
            if(!empty($ids)){
                return intval($ids[0]);
            }
        }

        $originalCanvasId = trim((string)$originalCanvasId);
        if($originalCanvasId!=='' && defined('DT_ORIGINAL_IIIF_ID')){
            return $this->findRecordByField('DT_ORIGINAL_IIIF_ID', $originalCanvasId);
        }

        return 0;
    }

    private function fillDetailsFromCanvas(array &$details, array $canvas, string $originalCanvasId, int $mediaUlfID=0): void
    {
        $labelValues = $this->normaliseLangValues(@$canvas['label']);
        if(empty($labelValues)){
            $labelValues = array(basename(parse_url($originalCanvasId, PHP_URL_PATH) ?: $originalCanvasId));
        }

        $summaryValues = $this->normaliseLangValues(@$canvas['summary']);
        if(empty($summaryValues)){
            $summaryValues = $this->normaliseLangValues(@$canvas['description']);
        }

        $this->setField($details, 'DT_NAME', $labelValues);
        $this->setField($details, 'DT_SHORT_SUMMARY', $summaryValues);
        if($mediaUlfID>0){
            $this->setField($details, 'DT_FILE_RESOURCE', intval($mediaUlfID));
            $canonicalCanvasUrl = $this->canonicalCanvasUrlForFileID($mediaUlfID);
            if($canonicalCanvasUrl){
                $this->setField($details, 'DT_IIIF_ID', $canonicalCanvasUrl);
            }
        }
        $this->setField($details, 'DT_ORIGINAL_IIIF_ID', $originalCanvasId);
        $this->setField($details, 'DT_ANNOTATION_STATE', $this->getTermId('TRM_ANNOTATION_STATE_IMPORTED'));

        if(isset($canvas['width'])){
            $this->setField($details, $this->detailId('DT_WIDTH', '3-1040'), intval($canvas['width']));
        }
        if(isset($canvas['height'])){
            $this->setField($details, $this->detailId('DT_HEIGHT', '3-1041'), intval($canvas['height']));
        }
        if(isset($canvas['duration'])){
            $this->setField($details, $this->detailId('DT_DURATION', '2-66'), floatval($canvas['duration']));
        }

        $thumbUrl = $this->extractThumbnailUrl($canvas);
        if($thumbUrl){
            $ulfID = $this->registerExternalUrl($thumbUrl);
            if($ulfID>0){
                $this->setField($details, 'DT_THUMBNAIL', intval($ulfID));
            }
        }
    }

    private function detailId(string $constName, string $conceptCode): ?int
    {
        $id = $this->constId($constName);
        if($id){
            return $id;
        }
        $id = ConceptCode::getDetailTypeLocalID($conceptCode);
        return $id>0 ? intval($id) : null;
    }

    /** Register an external media/thumbnail URL once and return its ulf_ID. */
    private function registerExternalUrl(?string $url): int
    {
        $url = trim((string)$url);
        if($url==='' || !preg_match('/^https?:\/\//i', $url)){
            return 0;
        }

        $fileEntity = new DbRecUploadedFiles($this->system);
        $ulfID = $fileEntity->findRegistrationByUrl($url);
        if($ulfID>0){
            return intval($ulfID);
        }

        $ulfID = $fileEntity->registerURL($url);
        return $ulfID>0 ? intval($ulfID) : 0;
    }

    /** Extract the primary painting body URL from a IIIF v3/v2 Canvas. */
    private function extractPrimaryPaintingBodyUrl(array $canvas): ?string
    {
        // IIIF Presentation v3: Canvas.items[] -> AnnotationPage.items[] -> painting Annotation.body.id
        foreach((array)@$canvas['items'] as $page){
            if(!is_array($page)){
                continue;
            }
            foreach((array)@$page['items'] as $anno){
                if(!is_array($anno)){
                    continue;
                }
                $motivation = @$anno['motivation'];
                if(is_array($motivation)){
                    $motivation = reset($motivation);
                }
                if($motivation && $this->stripIiifPrefix((string)$motivation)!=='painting'){
                    continue;
                }
                $url = $this->extractBodyUrl(@$anno['body']);
                if($url){
                    return $url;
                }
            }
        }

        // IIIF Presentation v2: Canvas.images[] -> Annotation.resource.@id/id
        foreach((array)@$canvas['images'] as $anno){
            if(!is_array($anno)){
                continue;
            }
            $url = $this->extractBodyUrl(@$anno['resource']);
            if($url){
                return $url;
            }
        }

        return null;
    }

    /** Extract a Canvas thumbnail URL, when the Manifest has a dedicated thumbnail entry. */
    private function extractThumbnailUrl(array $canvas): ?string
    {
        return $this->extractBodyUrl(@$canvas['thumbnail']);
    }

    /** Extract URL from IIIF body/thumbnail object, array, or string. */
    private function extractBodyUrl($body): ?string
    {
        if(is_string($body)){
            return preg_match('/^https?:\/\//i', $body) ? $body : null;
        }

        if(!is_array($body)){
            return null;
        }

        if(array_keys($body)===range(0, count($body)-1)){
            foreach($body as $item){
                $url = $this->extractBodyUrl($item);
                if($url){
                    return $url;
                }
            }
            return null;
        }

        foreach(array('id', '@id') as $key){
            if(!empty($body[$key]) && is_string($body[$key]) && preg_match('/^https?:\/\//i', $body[$key])){
                return $body[$key];
            }
        }

        // Some IIIF Image API entries expose the service URL but not a direct body URL.
        // Use the service id only as a fallback.
        $service = @$body['service'];
        if(is_array($service)){
            $url = $this->extractBodyUrl($service);
            if($url){
                return $url;
            }
        }

        return null;
    }

    private function stripIiifPrefix(string $value): string
    {
        $pos = strrpos($value, ':');
        return $pos!==false ? substr($value, $pos+1) : $value;
    }


    /** Save Canvas details during import without marking the record as a Heurist-editor edit. */
    private function saveCanvasRecordDetails(int $recordId, array $details, int $updateMode=0)
    {
        $record = $this->makeRecord($details, $recordId);
        $record['skip_iiif_canvas_state_update'] = true;
        return recordSave($this->system, $record, false, true, $updateMode);
    }

    /**
     * Called after a normal Heurist record-editor save of an RT_IIIF_CANVAS record.
     * Updates DT_IIIF_ID from DT_FILE_RESOURCE every time because Canvas identity is file-based.
     * Updates only DT_ANNOTATION_STATE directly in recDetails to avoid a second recordSave().
     * Until DT_ANNOTATION_STATE is renamed, Canvas records reuse the IIIF state vocabulary.
     */
    public function markSavedFromHeuristEditor(int $recID): bool
    {
        if($recID<1 || !$this->ensureDefinitionsReady(false)){
            return false;
        }

        $details = $this->loadRecordDetails($recID, array('DT_FILE_RESOURCE', 'DT_ANNOTATION_STATE'));

        $ulfID = intval($this->getFirstDetailValue($details, 'DT_FILE_RESOURCE'));
        $canonical = $this->canonicalCanvasUrlForFileID($ulfID);
        $idOk = $canonical
            ? $this->updateSingleDetailDirect($recID, 'DT_IIIF_ID', $canonical)
            : true;

        $oldState = intval($this->getFirstDetailValue($details, 'DT_ANNOTATION_STATE'));

        $imported = $this->getTermId('TRM_ANNOTATION_STATE_IMPORTED');
        $mirador  = $this->getTermId('TRM_ANNOTATION_STATE_MIRADOR');
        $heurist  = $this->getTermId('TRM_ANNOTATION_STATE_HEURIST');
        $modified = $this->getTermId('TRM_ANNOTATION_STATE_MODIFIED');
        $obsolete = $this->getTermId('TRM_ANNOTATION_STATE_OBSOLETE');
        $removed  = $this->getTermId('TRM_ANNOTATION_STATE_REMOVED');

        if($oldState === $obsolete || $oldState === $removed){
            return $idOk;
        }

        if($oldState === $imported || $oldState === $mirador || $oldState === $modified){
            return $this->updateSingleDetailDirect($recID, 'DT_ANNOTATION_STATE', intval($modified)) && $idOk;
        }

        if($oldState < 1){
            return $this->updateSingleDetailDirect($recID, 'DT_ANNOTATION_STATE', intval($heurist)) && $idOk;
        }

        return $idOk;
    }

    private function isProtectedFromReimport(array $details): bool
    {
        $state = intval($this->getFirstDetailValue($details, 'DT_ANNOTATION_STATE'));
        if($state<1){
            return false;
        }

        $protectedStates = array_filter(array(
            $this->getTermId('TRM_ANNOTATION_STATE_MIRADOR'),
            $this->getTermId('TRM_ANNOTATION_STATE_HEURIST'),
            $this->getTermId('TRM_ANNOTATION_STATE_MODIFIED'),
            $this->getTermId('TRM_ANNOTATION_STATE_OBSOLETE'),
            $this->getTermId('TRM_ANNOTATION_STATE_REMOVED')
        ));

        return in_array($state, $protectedStates, true);
    }
}
