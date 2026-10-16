<?php
/**
* DbAnnotations.php - Manages IIIF annotation records
* 
* @project     Heurist academic knowledge management system
* @package     Entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/
namespace hserv\entity;

use hserv\entity\DbRecordTypeEntity;
use hserv\entity\DbIiifCanvas;
use hserv\utilities\USanitize;
use hserv\utilities\UImage;
use hserv\iiif\IiifAnnotationJson;
use hserv\records\import\IiifAnnotationImportWriter;

require_once dirname(__FILE__).'/../records/import/IiifAnnotationImportWriter.php';

/**
* Class DbAnnotations
*
* Manages IIIF Web Annotation records used by Mirador and the IIIF annotation import workflow.
*/
class DbAnnotations extends DbRecordTypeEntity
{
    /** @var IiifAnnotationJson|null */
    private $annotationJson = null;

    /** @var array|null Cached fixed annotation term IDs used by the import hot path. */
    private $annotationTermIds = null;

    private function annotationJson(): IiifAnnotationJson
    {
        if($this->annotationJson === null){
            $this->annotationJson = new IiifAnnotationJson();
        }
        return $this->annotationJson;
    }

    protected function initRecordTypeEntity(): void
    {
        $this->recordTypeConst = 'RT_IIIF_ANNOTATION';
        $this->recordTypeConceptCode = '2-109';

        $this->requiredConstants = array(
            'RT_IIIF_ANNOTATION',
            'RT_IIIF_MANIFEST',
            'DT_NAME',
            'DT_SHORT_SUMMARY',
            'DT_EXTENDED_DESCRIPTION',
            'DT_URL',
            'DT_IIIF_ID', 
            'DT_ORIGINAL_IIIF_ID',
            'DT_ANNOTATION_INFO',
            'DT_ANNOTATION_MANIFEST',
            'DT_IIIF_CANVAS',
            'DT_ANNOTATION_STATE',
            'DT_ANNOTATION_MOTIVATION',
            'DT_ANNOTATION_SELECTOR_TYPE',
            'DT_ANNOTATION_SELECTOR_VALUE',
            'DT_LANGUAGE',
            'DT_THUMBNAIL'
        );

        // Entity-local term constants. Fill the empty placeholders when the concept codes are final.
        $this->requiredTermConstants = [
            'TRM_ANNOTATION_STATE_IMPORTED' => '2-10426',
            'TRM_ANNOTATION_STATE_MIRADOR'  => '2-10427',
            'TRM_ANNOTATION_STATE_HEURIST'  => '2-10428',
            'TRM_ANNOTATION_STATE_MODIFIED' => '2-10429',
            'TRM_ANNOTATION_STATE_OBSOLETE' => '2-10430',
            'TRM_ANNOTATION_STATE_REMOVED'  => '2-10431',

            'TRM_ANNOTATION_MOTIVATION_COMMENTING' => '2-10419',
            'TRM_SELECTOR_FRAGMENT' => '2-10433',
            'TRM_SELECTOR_SVG' => '2-10434',
            
            'TRM_VOCAB_LANGUAGE' => '2-496'
        ];        
        
    }
    
    

    /**
     * Returns either a single IIIF Annotation or an AnnotationPage for Mirador.
     *
     * Supported REST paths are parsed in api.php:
     *   /api/{db}/annotations/pages?uri={canvasUri}
     *   /api/{db}/annotations/{manifestRecID}/pages?uri={canvasUri}
     *   /api/{db}/annotations/{annotationId}
     *   /api/{db}/annotations/{manifestRecID}/{annotationId}
     */
    public function search(){

        if(@$this->data['recID']=='edit'){   //entry from Mirador - edit new annotation
            $recordId = $this->findRecIDbyUUID(@$this->data['uuid']);  
            if($recordId>0){
                $redirect = HEURIST_BASE_URL.'/hclient/framecontent/recordEdit.php?db='.$this->system->dbname().'&fmt=edit&recID='.$recordId;
                redirectURL($redirect);
            }
            exit;
        }

        if(@$this->data['recID']!='pages'){
            $recordId = $this->findAnnotationRecordID(@$this->data['recID'], intval(@$this->data['manifestRecID']));
            if($recordId>0){
                $anno = $this->buildIiifAnnotationFromRecord($recordId);
                if($anno){
                    return $anno;
                }
            }
            $this->system->addError(HEURIST_NOT_FOUND, 'Annotation record not found');
            return false;
        }

        $sjson = array('id'=>"https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]", 'type' => 'AnnotationPage', 'items' => array());

        if(!@$this->data['uri']){
            $params = USanitize::sanitizeInputArray();
            $this->data['uri'] = @$params['uri'];
        }

        $recordIds = $this->findItemsByCanvas(@$this->data['uri'], intval(@$this->data['manifestRecID']));
        if(isEmptyArray($recordIds)){
            return $sjson;
        }

        foreach($recordIds as $recordId){
            $anno = $this->buildIiifAnnotationFromRecord(intval($recordId));
            if($anno && @$anno['type']=='Annotation'){
                $sjson['items'][] = $anno;
            }
        }

        return $sjson;
    }

    private function findAnnotationRecordID($annotationId, int $manifestRecID=0): int
    {
        if(!$annotationId || !$this->ensureDefinitionsReady(false)){
            return 0;
        }

        if(is_numeric($annotationId)){
            $recordId = intval($annotationId);
            if($recordId>0 && $this->isAnnotationRecord($recordId)
                && $this->annotationMatchesManifest($recordId, $manifestRecID)){
                return $recordId;
            }
        }

        return $this->findRecIDbyIiifIdentifier($annotationId, $manifestRecID);
    }

    private function isAnnotationRecord(int $recordId): bool
    {
        if($recordId<1 || !$this->recordTypeId()){
            return false;
        }
        $recTypeId = mysql__select_value($this->system->getMysqli(),
            'SELECT rec_RecTypeID FROM Records WHERE rec_ID='.intval($recordId));
        return intval($recTypeId)===$this->recordTypeId();
    }

    private function annotationMatchesManifest(int $recordId, int $manifestRecID=0): bool
    {
        if($manifestRecID<1 || !defined('DT_ANNOTATION_MANIFEST')){
            return true;
        }
        $cnt = mysql__select_value($this->system->getMysqli(),
            'SELECT COUNT(*) FROM recDetails WHERE dtl_RecID='.intval($recordId)
            .' AND dtl_DetailTypeID='.intval(DT_ANNOTATION_MANIFEST)
            .' AND dtl_Value='.intval($manifestRecID));
        return intval($cnt)>0;
    }

    private function findItemsByCanvas($canvasUri, int $manifestRecID=0){
        if(!$canvasUri || !$this->ensureDefinitionsReady(false)){
            return array();
        }

        $recordIds = array();

        // 1. Overlay/original-target lookup. DT_URL stores the original target Canvas URL.
        $ids = $this->findItemsByOriginalCanvasUrl((string)$canvasUri, $manifestRecID);
        if(!isEmptyArray($ids)){
            $recordIds = array_merge($recordIds, $ids);
        }

        // 2. Managed/canonical lookup. Canvas DT_IIIF_ID stores the Heurist Canvas API URL.
        $dbCanvas = new DbIiifCanvas($this->system);
        $canvasRecIDs = $dbCanvas->canvasRecordsForCanonicalUrl((string)$canvasUri);
        foreach($canvasRecIDs as $canvasRecID){
            $ids = $this->findItemsByManagedCanvas(intval($canvasRecID), $manifestRecID);
            if(!isEmptyArray($ids)){
                $recordIds = array_merge($recordIds, $ids);
            }
        }

        return array_values(array_unique(array_map('intval', $recordIds)));
    }

    private function findItemsByOriginalCanvasUrl(string $canvasUri, int $manifestRecID=0): array
    {
        if($canvasUri==='' || !defined('DT_URL')){
            return array();
        }

        $mysqli = $this->system->getMysqli();
        $query = 'SELECT DISTINCT r.rec_ID FROM recDetails d1, Records r ';
        $where = 'r.rec_ID=d1.dtl_RecID AND r.rec_RecTypeID='.intval($this->recordTypeId())
            .' AND d1.dtl_DetailTypeID='.DT_URL .' AND d1.dtl_Value="'.addslashes($canvasUri).'"';

        if($manifestRecID>0 && defined('DT_ANNOTATION_MANIFEST')){
            $query .= ', recDetails dm ';
            $where .= ' AND dm.dtl_RecID=r.rec_ID AND dm.dtl_DetailTypeID='.DT_ANNOTATION_MANIFEST
                .' AND dm.dtl_Value='.intval($manifestRecID);
        }

        return mysql__select_list2($mysqli, $query.SQL_WHERE.$where.' ORDER BY r.rec_ID') ?: array();
    }

    private function findItemsByManagedCanvas(int $canvasRecID, int $manifestRecID=0): array
    {
        if($canvasRecID<1 || !defined('DT_IIIF_CANVAS')){
            return array();
        }

        $mysqli = $this->system->getMysqli();
        $query = 'SELECT DISTINCT r.rec_ID FROM recDetails dc, Records r ';
        $where = 'r.rec_ID=dc.dtl_RecID AND r.rec_RecTypeID='.intval($this->recordTypeId())
            .' AND dc.dtl_DetailTypeID='.DT_IIIF_CANVAS
            .' AND dc.dtl_Value='.intval($canvasRecID);

        if($manifestRecID>0 && defined('DT_ANNOTATION_MANIFEST')){
            $query .= ', recDetails dm ';
            $where .= ' AND dm.dtl_RecID=r.rec_ID AND dm.dtl_DetailTypeID='.DT_ANNOTATION_MANIFEST
                .' AND dm.dtl_Value='.intval($manifestRecID);
        }

        return mysql__select_list2($mysqli, $query.SQL_WHERE.$where.' ORDER BY r.rec_ID') ?: array();
    }

    private function findItembyUUID($uuid, int $manifestRecID=0){
        $recordId = $this->findRecIDbyIiifIdentifier($uuid, $manifestRecID);
        if($recordId>0 && defined('DT_ANNOTATION_INFO')){
            $mysqli = $this->system->getMysqli();
            return mysql__select_value($mysqli,
                'SELECT dtl_Value FROM recDetails WHERE dtl_RecID='.intval($recordId)
                .' AND dtl_DetailTypeID='.intval(DT_ANNOTATION_INFO).' LIMIT 1');
        }
        return null;
    }

    private function findRecIDbyUUID($uuid){
        return $this->findRecIDbyIiifIdentifier($uuid, 0);
    }

    /**
     * Decode the stable Mirador-facing UUID generated by IiifAnnotationJson.
     *
     * The viewer JSON uses 00000000-0000-4000-8000-{recID-as-12-hex-digits}
     * because Mirador handles UUID ids more reliably than canonical HTTP ids.
     * This reverse mapper accepts both plain UUID and urn:uuid:UUID forms.
     */
    private function recIDFromMiradorAnnotationUuid($annotationId): int
    {
        $uuid = strtolower(trim((string)$annotationId));
        if(strpos($uuid, 'urn:uuid:') === 0){
            $uuid = substr($uuid, 9);
        }

        if(!preg_match('/^00000000-0000-4000-8000-([0-9a-f]{12})$/', $uuid, $m)){
            return 0;
        }

        $recordId = hexdec($m[1]);
        return $recordId > 0 ? intval($recordId) : 0;
    }

    private function findRecIDbyIiifIdentifier($annotationId, $manifestRecID=0): int
    {
        if(!$annotationId || !$this->ensureDefinitionsReady(false)){
            return 0;
        }

        $uuidRecordId = $this->recIDFromMiradorAnnotationUuid($annotationId);
        if($uuidRecordId > 0){
            return ($this->isAnnotationRecord($uuidRecordId)
                && $this->annotationMatchesManifest($uuidRecordId, intval($manifestRecID)))
                    ? $uuidRecordId
                    : 0;
        }

        $extra = array();
        if($manifestRecID>0 && defined('DT_ANNOTATION_MANIFEST')){
            $extra['DT_ANNOTATION_MANIFEST'] = intval($manifestRecID);
        }

        $recordId = $this->findRecordByField('DT_IIIF_ID', $annotationId, $extra);
        if(!$recordId && defined('DT_ORIGINAL_IIIF_ID')){
            $recordId = $this->findRecordByField('DT_ORIGINAL_IIIF_ID', $annotationId, $extra);
        }
        if(!$recordId && $manifestRecID>0){
            return $this->findRecIDbyIiifIdentifier($annotationId, 0);
        }
        return intval($recordId);
    }

    private function findRecIDbyOriginalId($annotationId, $manifestRecID=0): int
    {
        if(!$annotationId || !$this->ensureDefinitionsReady(false) || !defined('DT_ORIGINAL_IIIF_ID')){
            return 0;
        }

        $extra = array();
        if($manifestRecID>0 && defined('DT_ANNOTATION_MANIFEST')){
            $extra['DT_ANNOTATION_MANIFEST'] = intval($manifestRecID);
        }

        $recordId = $this->findRecordByField('DT_ORIGINAL_IIIF_ID', $annotationId, $extra);
        if(!$recordId && $manifestRecID>0){
            return $this->findRecIDbyOriginalId($annotationId, 0);
        }
        return intval($recordId);
    }

    public function delete($disable_foreign_checks = false){
        if(@$this->data['recID']){
            if(!$this->_validatePermission()){
                return false;
            }
            $recordId = $this->findAnnotationRecordID($this->data['recID'], intval(@$this->data['manifestRecID']));
            if($recordId>0){
                return recordDelete($this->system, $recordId);
            }
            $this->system->addError(HEURIST_NOT_FOUND, 'Annotation record to be deleted not found');
        }else{
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid annotation identifier');
        }
        return false;
    }

    private function isProtectedFromReimport(array $details): bool
    {
        $state = intval($this->getFirstDetailValue($details, 'DT_ANNOTATION_STATE'));
        $termIds = $this->annotationTermIds();
        $protectedStates = $termIds['protected_states'] ?? array();

        return $state > 0 && in_array($state, $protectedStates, true);
    }

    /** Resolve and cache fixed annotation term IDs once per entity instance. */
    private function annotationTermIds(): array
    {
        if(is_array($this->annotationTermIds)){
            return $this->annotationTermIds;
        }

        $this->annotationTermIds = array(
            'state_imported' => $this->getTermId('TRM_ANNOTATION_STATE_IMPORTED'),
            'motivation_commenting' => $this->getTermId('TRM_ANNOTATION_MOTIVATION_COMMENTING'),
            'selector_fragment' => $this->getTermId('TRM_SELECTOR_FRAGMENT'),
            'selector_svg' => $this->getTermId('TRM_SELECTOR_SVG'),
            'protected_states' => array_values(array_filter(array(
                $this->getTermId('TRM_ANNOTATION_STATE_MIRADOR'),
                $this->getTermId('TRM_ANNOTATION_STATE_HEURIST'),
                $this->getTermId('TRM_ANNOTATION_STATE_MODIFIED'),
                $this->getTermId('TRM_ANNOTATION_STATE_OBSOLETE'),
                $this->getTermId('TRM_ANNOTATION_STATE_REMOVED')
            )))
        );

        return $this->annotationTermIds;
    }

    /**
     * Saves an imported or Mirador-created IIIF annotation.
     * Returns recordSave-like response with flags: is_new, is_retained, is_preserved_local.
     *
     * Normal single saves still use the import writer for persistence, but without a
     * long-lived import session. Bulk imports should use IiifAnnotationImportWriter
     * directly so statements are prepared once and reused.
     */
    public function save($createThumbnail=true, $ulf_ID=0){
        if(!$this->ensureImportReady()){ 
            return false;
        }

        $fields = @$this->data['fields'];
        $prepared = $this->prepareImportedAnnotation($fields, $createThumbnail, intval($ulf_ID), false);
        if($prepared===false){
            return false;
        }
        if(!empty($prepared['response'])){
            return $prepared['response'];
        }

        $writer = new IiifAnnotationImportWriter($this->system, $this);
        if(!$writer->begin(false)){
            return false;
        }
        $res = $writer->savePreparedAnnotation($prepared);
        $writer->end();

        // Mirador creates the annotation first, then we create and attach its
        // thumbnail. This keeps remote image work outside the direct writer and
        // provides one reusable record-based method for the later batch action.
        $recID = intval($res['data'] ?? 0);
        if($createThumbnail && is_array($res) && $recID>0){
            $thumbnailId = $this->createAnnotationThumbnail($recID, true);
            if($thumbnailId>0){
                $res['thumbnail_ulf_id'] = $thumbnailId;
            }
        }

        return $res;
    }

    /** Check permission and IIIF annotation definitions. Called once by bulk import writer. */
    public function ensureImportReady(): bool
    {
        return $this->_validatePermission() && $this->ensureDefinitionsReady($this->system->isAdmin());
    }

    /** Return the local RT_IIIF_ANNOTATION id for import writer. */
    public function annotationRecordTypeId(): int
    {
        return intval($this->recordTypeId());
    }

    /**
     * Parse an incoming IIIF/Web Annotation and map it to Heurist annotation details.
     * This method does no database writes. The import writer owns direct SQL persistence.
     *
     * @return array|false Either ['record_id'=>int, 'details'=>array] or ['response'=>array]
     */
    public function prepareImportedAnnotation($fields, bool $createThumbnail=false, int $ulf_ID=0, bool $skipCanvasLookup=false)
    {
        if(!is_array($fields)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Annotation fields are not defined');
            return false;
        }

        $parsed = $this->annotationJson()->parseIncomingAnnotation($fields);
        if(!$parsed){
            $this->system->addError(HEURIST_INVALID_REQUEST,
                $this->annotationJson()->getLastError() ?: 'Unsupported annotation format');
            return false;
        }

        $manifestRecID = intval($fields['manifestRecID'] ?? $fields['annotation']['sourceRecordId'] ?? 0);
        $manifestFileID = intval(@$fields['manifestFileID']);
        $canvasRecID = intval(@$fields['canvasRecID']);
        $canvasUrl = @$fields['canvasOriginalId'] ?: @$fields['canvas'] ?: @$parsed['canvas'];
        $manifestUrl = @$fields['manifestUrl'];

        if($canvasUrl){
            $parsed['canvas'] = $canvasUrl;

            // During managed import IiifManifestImporter resolves canvasOriginalId
            // through the canvas import map and passes fields['canvasRecID'].
            if(!$skipCanvasLookup && $manifestRecID>0 && $canvasRecID===0){
                $dbCanvas = new DbIiifCanvas($this->system);
                $canvasRecIDs = $dbCanvas->canvasRecordsForCanonicalUrl((string)$canvasUrl);
                if(count($canvasRecIDs)>0){
                    $canvasRecID = intval($canvasRecIDs[0]);
                }
            }
        }
        
        $recordId = $this->findRecIDbyIiifIdentifier($parsed['id'], $manifestRecID);
        $details = $recordId>0 ? $this->loadRecordDetails($recordId) : array();
        $isMiradorUpdate = ($recordId > 0 && strtolower((string)($fields['source'] ?? '')) === 'mirador');

        if($recordId>0 && !empty($fields['preserveLocal']) && $this->isProtectedFromReimport($details)){
            return array('response'=>array('status'=>HEURIST_OK, 'data'=>$recordId, 'is_preserved_local'=>true));
        }

        $oldJson = $this->getFirstDetailValue($details, 'DT_ANNOTATION_INFO');
        $changed = $this->fillDetailsFromParsedAnnotation($details, $parsed, $manifestRecID, $manifestFileID, $canvasRecID, $isMiradorUpdate);

        if($ulf_ID>0){
            $changed = $this->appendUniqueField($details, 'DT_FILE_RESOURCE', $ulf_ID) || $changed;
        }

        if(!$changed && $recordId>0 && $oldJson === $parsed['json']){
            return array('response'=>array('status'=>HEURIST_OK, 'data'=>$recordId, 'is_retained'=>true));
        }

        return array(
            'record_id' => intval($recordId),
            'details' => $details
        );
    }

    /**
     * Called after a normal Heurist record-editor save of an RT_IIIF_ANNOTATION record.
     * This deliberately updates only DT_ANNOTATION_STATE directly in recDetails, avoiding
     * a second recordSave() call and avoiding recursion through the generic save pipeline.
     */
    public function markSavedFromHeuristEditor(int $recID): bool
    {
        if($recID<1 || !$this->ensureDefinitionsReady(false)){
            return false;
        }

        $details = $this->loadRecordDetails($recID, array('DT_ANNOTATION_STATE'));
        $oldState = intval($this->getFirstDetailValue($details, 'DT_ANNOTATION_STATE'));

        $imported = $this->getTermId('TRM_ANNOTATION_STATE_IMPORTED');
        $mirador  = $this->getTermId('TRM_ANNOTATION_STATE_MIRADOR');
        $heurist  = $this->getTermId('TRM_ANNOTATION_STATE_HEURIST');
        $modified = $this->getTermId('TRM_ANNOTATION_STATE_MODIFIED');
        $obsolete = $this->getTermId('TRM_ANNOTATION_STATE_OBSOLETE');
        $removed  = $this->getTermId('TRM_ANNOTATION_STATE_REMOVED');

        if($oldState === $obsolete || $oldState === $removed){
            return true;
        }

        if($oldState === $imported || $oldState === $mirador || $oldState === $modified){
            return $this->updateAnnotationStateDirect($recID, intval($modified));
        }

        if($oldState < 1){
            return $this->updateAnnotationStateDirect($recID, intval($heurist), true);
        }

        return true;
    }

    /** Directly update/insert DT_ANNOTATION_STATE, and optionally assign DT_IIIF_ID for new Heurist-created annotations. */
    private function updateAnnotationStateDirect(int $recID, int $stateTermID, bool $assignId=false): bool
    {
        if($recID<1 || $stateTermID<1 || !$this->ensureDefinitionsReady(false)){
            return false;
        }

        if(!$this->updateSingleDetailDirect($recID, 'DT_ANNOTATION_STATE', intval($stateTermID))){
            return false;
        }

        if($assignId){
            return $this->updateSingleDetailDirect($recID, 'DT_IIIF_ID', $this->annotationApiUrl($recID));
        }

        return true;
    }

    private function annotationApiUrl(int $recID): string
    {
        $baseUrl = rtrim(defined('HEURIST_BASE_URL_PRO') ? HEURIST_BASE_URL_PRO : HEURIST_BASE_URL, '/');
        return $baseUrl
            .'/api/'.$this->system->dbname()
            .'/annotations/'.intval($recID);
    }

    /** Build the IIIF/Web Annotation JSON used in AnnotationPage output. */
    public function buildIiifAnnotationFromRecord(int $recID): ?array
    {
        if($recID<1 || !$this->ensureDefinitionsReady(false)){
            return null;
        }
        return $this->buildIiifAnnotationFromDetails($recID, $this->loadRecordDetails($recID));
    }

    private function buildIiifAnnotationFromDetails(int $recID, array $details): ?array
    {
        return $this->annotationJson()->buildFromAnnotationData(
            $this->annotationDataFromDetails($recID, $details)
        );
    }
    
    public function getCanvasUrl(int $recID, ?array $details=null): string{
        
        if(!defined('DT_IIIF_CANVAS')){
            $this->system->defineConstant('DT_IIIF_CANVAS');
        }
        
        if($details==null){
            $details = $this->loadRecordDetails($recID, array('DT_IIIF_CANVAS','DT_URL'));
        }

        $canvasRecID = intval($this->getFirstDetailValue($details, 'DT_IIIF_CANVAS'));
        $managedCanvasUrl = null;
        if($canvasRecID>0){
            $dbCanvas = new DbIiifCanvas($this->system);
            $managedCanvasUrl = $dbCanvas->canonicalCanvasUrlForCanvasRecord($canvasRecID);
        }
        
        return $managedCanvasUrl ?: $this->getFirstDetailValue($details, 'DT_URL');
    }

    private function annotationDataFromDetails(int $recID, array $details): array
    {
        $stateCode = $this->getTermCodeOrLabel($this->getFirstDetailValue($details, 'DT_ANNOTATION_STATE'));

        $canvasRecID = intval($this->getFirstDetailValue($details, 'DT_IIIF_CANVAS'));
        $managedCanvasUrl = null;
        if($canvasRecID>0){
            $dbCanvas = new DbIiifCanvas($this->system);
            $managedCanvasUrl = $dbCanvas->canonicalCanvasUrlForCanvasRecord($canvasRecID);
        }

        return array(
            'recID' => $recID,
            'id' => $this->getFirstDetailValue($details, 'DT_IIIF_ID'),
            'annotationApiUrl' => $this->annotationApiUrl($recID),
            'rawJson' => $this->getFirstDetailValue($details, 'DT_ANNOTATION_INFO'),
            'stateCode' => $stateCode ? strtolower($stateCode) : '',
            'bodyText' => $this->getFirstDetailValue($details, 'DT_SHORT_SUMMARY'),
            'motivation' => $this->getTermCodeOrLabel($this->getFirstDetailValue($details, 'DT_ANNOTATION_MOTIVATION')),
            'language2' => $this->getLanguageCode2FromDetails($details),
            // Overlay mode uses the original Canvas id in DT_URL. Managed mode uses the linked RT_IIIF_CANVAS API URL.
            'canvas' => $managedCanvasUrl ?: $this->getFirstDetailValue($details, 'DT_URL'),
            'originalCanvas' => $this->getFirstDetailValue($details, 'DT_URL'),
            'canvasRecordID' => $canvasRecID,
            'managedCanvasUrl' => $managedCanvasUrl,
            'selectorType' => $this->getTermCodeOrLabel($this->getFirstDetailValue($details, 'DT_ANNOTATION_SELECTOR_TYPE')),
            'selectorValue' => $this->getFirstDetailValue($details, 'DT_ANNOTATION_SELECTOR_VALUE')
        );
    }

    private function getLanguageCode2FromDetails(array $details): ?string
    {
        $langTermId = intval($this->getFirstDetailValue($details, 'DT_LANGUAGE'));
        if($langTermId<1){
            return null;
        }
        $lang3 = $this->getTermCodeOrLabel($langTermId);
        if(!$lang3){
            return null;
        }
        $lang2 = getLangCode2($lang3);
        return $lang2 ?: $lang3;
    }

    private function getTermCodeOrLabel($termId): ?string
    {
        $termId = intval($termId);
        if($termId<1){
            return null;
        }
        $mysqli = $this->system->getMysqli();
        $row = mysql__select_row($mysqli,
            'SELECT trm_Code, trm_Label FROM defTerms WHERE trm_ID='.$termId.' LIMIT 1');
        if(is_array($row)){
            return trim((string)($row[0] ?: $row[1]));
        }
        return null;
    }
    
    private function annotationSelectorTermId($selectorType, array $termIds): ?int
    {
        $selectorType = trim((string)$selectorType);
        if($selectorType===''){
            return null;
        }

        $key = strtolower($selectorType);
        if($key==='fragmentselector' || $key==='fragment'){
            return $termIds['selector_fragment'] ?? null;
        }
        if($key==='svgselector' || $key==='svg'){
            return $termIds['selector_svg'] ?? null;
        }

        return $this->getTermId($selectorType);
    }
    
    private function fillDetailsFromParsedAnnotation(&$details, $parsed, $manifestRecID=0, $manifestFileID=0, $canvasRecID=0, bool $preserveMissingSelector=false){
        
        $changed = false;
        $text = trim(strip_tags((string)@$parsed['body_text']));
        $title = $text ? substr($text, 0, 50) : substr((string)$parsed['id'], 0, 50);

        $termIds = $this->annotationTermIds();
        $motivationId = $this->getTermId(@$parsed['motivation']) ?: ($termIds['motivation_commenting'] ?? null);
        $stateId = $this->getTermId(@$parsed['state']) ?: ($termIds['state_imported'] ?? null);
        $selectorTypeId = $this->annotationSelectorTermId(@$parsed['selector_type'], $termIds);
        $selectorValue = @$parsed['selector_value'];

        // Current Mirador annotation editor may omit target.selector when only
        // annotation text was edited. Treat an omitted selector from Mirador as
        // "area unchanged", not as "delete annotation area".
        if($preserveMissingSelector && !$selectorTypeId && ($selectorValue===null || $selectorValue==='')){
            $oldSelectorTypeId = intval($this->getFirstDetailValue($details, 'DT_ANNOTATION_SELECTOR_TYPE'));
            $oldSelectorValue = $this->getFirstDetailValue($details, 'DT_ANNOTATION_SELECTOR_VALUE');
            if($oldSelectorTypeId>0 && $oldSelectorValue!==null && $oldSelectorValue!==''){
                $selectorTypeId = $oldSelectorTypeId;
                $selectorValue = $oldSelectorValue;
                $selectorType = $this->getTermCodeOrLabel($oldSelectorTypeId) ?: 'FragmentSelector';
                $parsed['json'] = $this->annotationJson()->forceTargetSelectorJson(
                    (string)@$parsed['json'],
                    $selectorType,
                    (string)$oldSelectorValue
                );
            }
        }

        $changed = $this->setField($details, 'DT_NAME', $title) || $changed;
        $changed = $this->setField($details, 'DT_SHORT_SUMMARY', @$parsed['body_text']) || $changed;
        $changed = $this->setField($details, 'DT_ORIGINAL_IIIF_ID', @$parsed['id']) || $changed;
        $changed = $this->setField($details, 'DT_ANNOTATION_INFO', @$parsed['json']) || $changed;
        $changed = $this->setField($details, 'DT_URL', @$parsed['canvas']) || $changed;

        $changed = $this->setField($details, 'DT_ANNOTATION_MOTIVATION', $motivationId) || $changed;
        $changed = $this->setField($details, 'DT_LANGUAGE', $this->getLanguageTermId(@$parsed['language'])) || $changed;
        $changed = $this->setField($details, 'DT_ANNOTATION_SELECTOR_TYPE', $selectorTypeId) || $changed;
        $changed = $this->setField($details, 'DT_ANNOTATION_SELECTOR_VALUE', $selectorValue) || $changed;
        $changed = $this->setField($details, 'DT_ANNOTATION_STATE', $stateId) || $changed;

        if($manifestRecID>0){
            $changed = $this->appendUniqueField($details, 'DT_ANNOTATION_MANIFEST', $manifestRecID) || $changed;
        }

        if($canvasRecID>0){
            $changed = $this->setField($details, 'DT_IIIF_CANVAS', intval($canvasRecID)) || $changed;
        }
        /*
        if($manifestFileID>0){
            $changed = $this->appendUniqueField($details, 'DT_FILE_RESOURCE', $manifestFileID) || $changed;
        }
        */

        return $changed;
    }

    private function removeUriSchema($val){
        if($val && strpos($val, 'http://')===0){
            $val = substr($val, 7);
        }
        return $val;
    }

    /**
    * 
    */
    private function extractImageUrlFromCanvas($canvas, $url) {
        if($canvas['@id']!=$url || !is_array(@$canvas['images'])){
            return null;
        }
        foreach($canvas['images'] as $image){
            $url2 = @$image['resource']['service']['@id'];
            if($url2!=null) {
                return $url2;
            }
        }
        return null;
    }

    /**
    * 
    */
    private function getImageUrlV2($iiif_manifest, $url){

        if(!is_array(@$iiif_manifest['sequences'])){
            return null;
        }

        foreach($iiif_manifest['sequences'] as $seq){
            if(is_array(@$seq['canvases'])){
                foreach($seq['canvases'] as $canvas){
                    $url2 = $this->extractImageUrlFromCanvas($canvas, $url);
                    if($url2!=null) {
                        return $url2;
                    }
                }
            }
        }
        //not found
        return null;
    }

    /**
    * 
    */
    private function extractImageUrlFromAnnotationPage($annot_page) {

        if(@$annot_page['type']=='AnnotationPage' && is_array(@$annot_page['items']))
        {
            foreach($annot_page['items'] as $annot){
                if(@$annot['type']=='Annotation'
                && @$annot['body']['type']=='Image')
                {
                    $url2 = @$annot['body']['service']['id'];
                    if($url2!=null) {
                        return $url2;
                    }
                }
            }
        }
        return null;
    }

    /**
    * 
    */
    private function getImageUrlV3($iiif_manifest, $url){

        if(!is_array(@$iiif_manifest['items'])){
            return $url;
        }

        foreach($iiif_manifest['items'] as $canvas){
            if(@$canvas['type']=='Canvas' && $canvas['id']==$url && is_array(@$canvas['items'])){
                foreach($canvas['items'] as $annot_page){
                    $url2 = $this->extractImageUrlFromAnnotationPage($annot_page);
                    if($url2!=null) {
                        return $url2;
                    }
                }
            }
        }

        //not found
        return $url;
    }

    /**
    * 
    */
    /**
     * Create and attach a thumbnail for one saved IIIF Annotation record.
     *
     * This record-based entry point is also suitable for the planned batch action.
     * At present FragmentSelector and SvgSelector are supported. SVG selections use
     * their rectangular bounding box because IIIF Image API regions are rectangular.
     *
     * @return int Registered thumbnail ulf_ID, or 0 when no thumbnail was created.
     */
    public function createAnnotationThumbnail(int $recID, bool $replaceExisting=false): int
    {
        if($recID<1 || !$this->ensureDefinitionsReady(false)){
            return 0;
        }

        $details = $this->loadRecordDetails($recID, array(
            'DT_THUMBNAIL',
            'DT_ANNOTATION_SELECTOR_TYPE',
            'DT_ANNOTATION_SELECTOR_VALUE',
            'DT_IIIF_CANVAS',
            'DT_URL'
        ));

        $existingThumbnail = intval($this->getFirstDetailValue($details, 'DT_THUMBNAIL'));
        if($existingThumbnail>0 && !$replaceExisting){
            return $existingThumbnail;
        }

        $selectorType = (string)$this->getTermCodeOrLabel(
            $this->getFirstDetailValue($details, 'DT_ANNOTATION_SELECTOR_TYPE')
        );
        $selectorValue = (string)$this->getFirstDetailValue($details, 'DT_ANNOTATION_SELECTOR_VALUE');
        $region = $this->annotationSelectorRegion($selectorType, $selectorValue);
        if($region===null){
            return 0;
        }

        $dbCanvas = new DbIiifCanvas($this->system);
        $canvasRecID = intval($this->getFirstDetailValue($details, 'DT_IIIF_CANVAS'));
        $canvasUrl = trim((string)$this->getFirstDetailValue($details, 'DT_URL'));
        $source = $canvasRecID>0
            ? $dbCanvas->thumbnailSourceForCanvasRecord($canvasRecID)
            : $dbCanvas->thumbnailSourceForCanvasUrl($canvasUrl);

        if(!is_array($source) || empty($source['type'])){
            USanitize::errorLog('Cannot create IIIF annotation thumbnail: Canvas image source is not available. Annotation record '.$recID);
            return 0;
        }

        $tmpFile = tempnam(HEURIST_SCRATCH_DIR, 'annotation_thumb_');
        if(!$tmpFile){
            return 0;
        }

        try{
            $created = false;
            if($source['type']==='iiif'){
                $serviceUrl = trim((string)($source['service_url'] ?? ''));
                $created = $serviceUrl!==''
                    && UImage::getIiifRegionThumbnail($serviceUrl, $region, $tmpFile, 200, 200)!==null;
            }elseif($source['type']==='local'){
                $created = UImage::createRegionThumbnail(
                    (string)($source['file_path'] ?? ''),
                    $region,
                    $tmpFile,
                    floatval($source['canvas_width'] ?? 0),
                    floatval($source['canvas_height'] ?? 0),
                    200,
                    200,
                    false
                );
            }elseif($source['type']==='remote'){
                $created = UImage::createRegionThumbnail(
                    (string)($source['image_url'] ?? ''),
                    $region,
                    $tmpFile,
                    floatval($source['canvas_width'] ?? 0),
                    floatval($source['canvas_height'] ?? 0),
                    200,
                    200,
                    true
                );
            }

            if(!$created || !file_exists($tmpFile) || filesize($tmpFile)<1){
                USanitize::errorLog('Cannot create annotation-region thumbnail. Annotation record '.$recID
                    .'; source_type='.(string)$source['type'].'; region='.$region);
                return 0;
            }

            $entity = new DbRecUploadedFiles($this->system);
            $thumbnailId = $entity->registerFile(
                $tmpFile,
                'annotation_'.$recID.'_thumbnail.jpg',
                true,
                false,
                array('ulf_Description'=>'Thumbnail for IIIF annotation record '.$recID)
            );
            if(is_array($thumbnailId)){
                $thumbnailId = reset($thumbnailId);
            }
            $thumbnailId = intval($thumbnailId);
            if($thumbnailId<1){
                $this->system->clearError();
                return 0;
            }

            if(!$this->updateAnnotationThumbnailDirect($recID, $thumbnailId)){
                return 0;
            }
            return $thumbnailId;
        }finally{
            if(file_exists($tmpFile)){
                @unlink($tmpFile);
            }
        }
    }

    /** Convert a stored selector to an IIIF Image API pixel region. */
    private function annotationSelectorRegion(string $selectorType, string $selectorValue): ?string
    {
        $selectorType = strtolower(trim($selectorType));
        $selectorValue = trim($selectorValue);
        if($selectorValue===''){
            return null;
        }

        if($selectorType==='fragmentselector' || $selectorType==='fragment'){
            if(preg_match('/(?:^|[&#])xywh=(?:pixel:)?([0-9.]+),([0-9.]+),([0-9.]+),([0-9.]+)/i', $selectorValue, $m)){
                return $this->normalisePixelRegion($m[1], $m[2], $m[3], $m[4]);
            }
            return null;
        }

        if($selectorType==='svgselector' || $selectorType==='svg'){
            return $this->svgSelectorBoundingRegion($selectorValue);
        }

        return null;
    }

    private function normalisePixelRegion($x, $y, $width, $height): ?string
    {
        $x = max(0, (int)floor((float)$x));
        $y = max(0, (int)floor((float)$y));
        $width = (int)ceil((float)$width);
        $height = (int)ceil((float)$height);
        return ($width>0 && $height>0) ? $x.','.$y.','.$width.','.$height : null;
    }

    /** Return the bounding rectangle of common MAE SVG selector shapes. */
    private function svgSelectorBoundingRegion(string $svg): ?string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if(!$loaded){
            return null;
        }

        $xs = array();
        $ys = array();
        $addPoint = static function($x, $y) use (&$xs, &$ys): void {
            if(is_numeric($x) && is_numeric($y)){
                $xs[] = (float)$x;
                $ys[] = (float)$y;
            }
        };

        foreach($dom->getElementsByTagName('*') as $node){
            $name = strtolower($node->localName ?: $node->nodeName);
            if($name==='rect'){
                $x = (float)$node->getAttribute('x');
                $y = (float)$node->getAttribute('y');
                $w = (float)$node->getAttribute('width');
                $h = (float)$node->getAttribute('height');
                $addPoint($x, $y); $addPoint($x+$w, $y+$h);
            }elseif($name==='circle'){
                $cx = (float)$node->getAttribute('cx');
                $cy = (float)$node->getAttribute('cy');
                $r = (float)$node->getAttribute('r');
                $addPoint($cx-$r, $cy-$r); $addPoint($cx+$r, $cy+$r);
            }elseif($name==='ellipse'){
                $cx = (float)$node->getAttribute('cx');
                $cy = (float)$node->getAttribute('cy');
                $rx = (float)$node->getAttribute('rx');
                $ry = (float)$node->getAttribute('ry');
                $addPoint($cx-$rx, $cy-$ry); $addPoint($cx+$rx, $cy+$ry);
            }elseif($name==='line'){
                $addPoint($node->getAttribute('x1'), $node->getAttribute('y1'));
                $addPoint($node->getAttribute('x2'), $node->getAttribute('y2'));
            }elseif($name==='polygon' || $name==='polyline'){
                preg_match_all('/-?(?:\d+\.?\d*|\.\d+)/', $node->getAttribute('points'), $nums);
                for($i=0; $i+1<count($nums[0]); $i+=2){
                    $addPoint($nums[0][$i], $nums[0][$i+1]);
                }
            }elseif($name==='path'){
                $path = trim($node->getAttribute('d'));
                preg_match_all('/[a-zA-Z]|[-+]?(?:\d*\.\d+|\d+\.?)(?:[eE][-+]?\d+)?/', $path, $matches);
                $tokens = $matches[0];
                $count = count($tokens);
                $index = 0;
                $command = '';
                $currentX = 0.0;
                $currentY = 0.0;
                $startX = 0.0;
                $startY = 0.0;

                while($index<$count){
                    if(ctype_alpha($tokens[$index])){
                        $command = $tokens[$index++];
                    }
                    if($command===''){
                        break;
                    }

                    $relative = ctype_lower($command);
                    $upper = strtoupper($command);
                    if($upper==='Z'){
                        $currentX = $startX;
                        $currentY = $startY;
                        $addPoint($currentX, $currentY);
                        $command = '';
                        continue;
                    }

                    $needed = array('M'=>2, 'L'=>2, 'H'=>1, 'V'=>1, 'A'=>7)[$upper] ?? 0;
                    if($needed===0 || $index+$needed>$count || ctype_alpha($tokens[$index])){
                        $command = '';
                        continue;
                    }

                    if($upper==='M' || $upper==='L'){
                        $x = (float)$tokens[$index++];
                        $y = (float)$tokens[$index++];
                        if($relative){
                            $x += $currentX;
                            $y += $currentY;
                        }
                        $currentX = $x;
                        $currentY = $y;
                        if($upper==='M'){
                            $startX = $x;
                            $startY = $y;
                            $command = $relative ? 'l' : 'L';
                        }
                        $addPoint($x, $y);
                    }elseif($upper==='H'){
                        $x = (float)$tokens[$index++];
                        if($relative){ $x += $currentX; }
                        $currentX = $x;
                        $addPoint($currentX, $currentY);
                    }elseif($upper==='V'){
                        $y = (float)$tokens[$index++];
                        if($relative){ $y += $currentY; }
                        $currentY = $y;
                        $addPoint($currentX, $currentY);
                    }elseif($upper==='A'){
                        $rx = abs((float)$tokens[$index++]);
                        $ry = abs((float)$tokens[$index++]);
                        $rotation = deg2rad(fmod((float)$tokens[$index++], 360.0));
                        $largeArc = ((int)$tokens[$index++])!==0;
                        $sweep = ((int)$tokens[$index++])!==0;
                        $endX = (float)$tokens[$index++];
                        $endY = (float)$tokens[$index++];
                        if($relative){
                            $endX += $currentX;
                            $endY += $currentY;
                        }

                        $startArcX = $currentX;
                        $startArcY = $currentY;
                        $addPoint($startArcX, $startArcY);
                        $addPoint($endX, $endY);

                        if($rx>0 && $ry>0 && (abs($endX-$startArcX)>0.000001 || abs($endY-$startArcY)>0.000001)){
                            $cosPhi = cos($rotation);
                            $sinPhi = sin($rotation);
                            $dx = ($startArcX-$endX)/2.0;
                            $dy = ($startArcY-$endY)/2.0;
                            $xp = $cosPhi*$dx + $sinPhi*$dy;
                            $yp = -$sinPhi*$dx + $cosPhi*$dy;
                            $lambda = ($xp*$xp)/($rx*$rx) + ($yp*$yp)/($ry*$ry);
                            if($lambda>1){
                                $factor = sqrt($lambda);
                                $rx *= $factor;
                                $ry *= $factor;
                            }

                            $denominator = ($rx*$rx*$yp*$yp) + ($ry*$ry*$xp*$xp);
                            $coefficient = 0.0;
                            if($denominator>0){
                                $numerator = max(0.0, ($rx*$rx*$ry*$ry) - ($rx*$rx*$yp*$yp) - ($ry*$ry*$xp*$xp));
                                $coefficient = sqrt($numerator/$denominator);
                                if($largeArc===$sweep){ $coefficient = -$coefficient; }
                            }
                            $cxp = $coefficient*($rx*$yp/$ry);
                            $cyp = $coefficient*(-$ry*$xp/$rx);
                            $cx = $cosPhi*$cxp - $sinPhi*$cyp + ($startArcX+$endX)/2.0;
                            $cy = $sinPhi*$cxp + $cosPhi*$cyp + ($startArcY+$endY)/2.0;

                            $vectorAngle = static function(float $ux, float $uy, float $vx, float $vy): float {
                                $dot = $ux*$vx + $uy*$vy;
                                $len = sqrt(($ux*$ux+$uy*$uy)*($vx*$vx+$vy*$vy));
                                if($len<=0){ return 0.0; }
                                $angle = acos(max(-1.0, min(1.0, $dot/$len)));
                                return ($ux*$vy-$uy*$vx)<0 ? -$angle : $angle;
                            };
                            $theta1 = $vectorAngle(1.0, 0.0, ($xp-$cxp)/$rx, ($yp-$cyp)/$ry);
                            $delta = $vectorAngle(
                                ($xp-$cxp)/$rx,
                                ($yp-$cyp)/$ry,
                                (-$xp-$cxp)/$rx,
                                (-$yp-$cyp)/$ry
                            );
                            if(!$sweep && $delta>0){ $delta -= 2*M_PI; }
                            if($sweep && $delta<0){ $delta += 2*M_PI; }

                            foreach(array(0.0, M_PI/2, M_PI, 3*M_PI/2) as $testAngle){
                                $offset = fmod($testAngle-$theta1 + 4*M_PI, 2*M_PI);
                                $inside = $delta>=0
                                    ? $offset <= $delta+0.000001
                                    : (2*M_PI-$offset) <= -$delta+0.000001;
                                if($inside){
                                    $addPoint(
                                        $cx + $rx*cos($testAngle)*$cosPhi - $ry*sin($testAngle)*$sinPhi,
                                        $cy + $rx*cos($testAngle)*$sinPhi + $ry*sin($testAngle)*$cosPhi
                                    );
                                }
                            }
                        }

                        $currentX = $endX;
                        $currentY = $endY;
                    }
                }
            }
        }

        if(empty($xs) || empty($ys)){
            return null;
        }
        return $this->normalisePixelRegion(min($xs), min($ys), max($xs)-min($xs), max($ys)-min($ys));
    }

    /** Replace DT_THUMBNAIL without invoking the generic record save pipeline. */
    private function updateAnnotationThumbnailDirect(int $recID, int $ulfID): bool
    {
        if($recID<1 || $ulfID<1 || !defined('DT_THUMBNAIL')){
            return false;
        }
        $mysqli = $this->system->getMysqli();
        $mysqli->begin_transaction();
        try{
            if(!$mysqli->query('DELETE FROM recDetails WHERE dtl_RecID='.intval($recID)
                .' AND dtl_DetailTypeID='.intval(DT_THUMBNAIL))){
                throw new \RuntimeException($mysqli->error);
            }
            $query = 'INSERT INTO recDetails '
                .'(dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID) VALUES ('
                .intval($recID).','.intval(DT_THUMBNAIL).',NULL,'.intval($ulfID).')';
            if(!$mysqli->query($query)){
                throw new \RuntimeException($mysqli->error);
            }
            $mysqli->commit();
            return true;
        }catch(\Throwable $e){
            $mysqli->rollback();
            USanitize::errorLog('Cannot attach IIIF annotation thumbnail: '.$e->getMessage());
            return false;
        }
    }

}
