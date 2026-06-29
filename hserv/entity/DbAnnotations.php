<?php
/**
* DbAnnotations.php - Class DbAnnotations
*
* Manages IIIF annotation records.
*/
namespace hserv\entity;

use hserv\entity\DbRecordTypeEntity;
use hserv\entity\DbIiifCanvas;
use hserv\utilities\USanitize;
use hserv\iiif\IiifAnnotationJson;

/**
* Class DbAnnotations
*
* Manages IIIF Web Annotation records used by Mirador and the IIIF annotation import workflow.
*/
class DbAnnotations extends DbRecordTypeEntity
{
    /** @var IiifAnnotationJson|null */
    private $annotationJson = null;

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

        if(@$this->data['recID']=='edit'){
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

    private function findRecIDbyIiifIdentifier($annotationId, $manifestRecID=0): int
    {
        if(!$annotationId || !$this->ensureDefinitionsReady(false)){
            return 0;
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

        $protectedStates = array_filter([
            $this->getTermId('TRM_ANNOTATION_STATE_MIRADOR'),
            $this->getTermId('TRM_ANNOTATION_STATE_HEURIST'),
            $this->getTermId('TRM_ANNOTATION_STATE_MODIFIED'),
            $this->getTermId('TRM_ANNOTATION_STATE_OBSOLETE'),
            $this->getTermId('TRM_ANNOTATION_STATE_REMOVED')
        ]);

        return $state > 0 && in_array($state, $protectedStates, true);
    }

    /**
     * Saves an imported or Mirador-created IIIF annotation.
     * Returns recordSave-like response with flags: is_new, is_retained, is_preserved_local.
     */
    public function save($createThumbnail=true, $ulf_ID=0){
        if(!$this->_validatePermission() || !$this->ensureDefinitionsReady($this->system->isAdmin())){
            return false;
        }

        $fields = @$this->data['fields'];
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
            
            if($manifestRecID>0 && $canvasRecID===0){
                $dbCanvas = new DbIiifCanvas($this->system);
                $canvasRecIDs = $dbCanvas->canvasRecordsForCanonicalUrl((string)$canvasUrl);
                if(count($canvasRecIDs)>0){
                    $canvasRecID = $canvasRecIDs[0];
                }
            }
            
        }

        $recordId = $this->findRecIDbyIiifIdentifier($parsed['id'], $manifestRecID);
        $details = $this->loadRecordDetails($recordId);

        if($recordId>0 && !empty($fields['preserveLocal']) && $this->isProtectedFromReimport($details)){
            return array('status'=>HEURIST_OK, 'data'=>$recordId, 'is_preserved_local'=>true);
        }

        $oldJson = $this->getFirstDetailValue($details, 'DT_ANNOTATION_INFO');
        $changed = $this->fillDetailsFromParsedAnnotation($details, $parsed, $manifestRecID, $manifestFileID, $canvasRecID);

        if($ulf_ID>0){
            $changed = $this->appendUniqueField($details, 'DT_FILE_RESOURCE', $ulf_ID) || $changed;
        }

        if($createThumbnail && defined('DT_THUMBNAIL') && @$parsed['selector_value'] && @$parsed['canvas']){
            $thumb_id = $this->getAnnotationImage($manifestUrl, $parsed['id'], $parsed['selector_value'], $parsed['canvas']);
            if($thumb_id>0){
                $changed = $this->setField($details, 'DT_THUMBNAIL', $thumb_id) || $changed;
            }
        }

        if(!$changed && $recordId>0 && $oldJson === $parsed['json']){
            return array('status'=>HEURIST_OK, 'data'=>$recordId, 'is_retained'=>true);
        }

        $record = array(
            'ID' => $recordId,
            'RecTypeID' => $this->recordTypeId(),
            'no_validation' => 'ignore_all',
            // DbAnnotations handles state itself for import/Mirador saves.
            // Prevent the generic recordSave() post-hook from marking this as a Heurist-editor edit.
            'skip_iiif_annotation_state_update' => true,
            'details' => $details
        );

        $out = recordSave($this->system, $record, false, true, 0);
        if(is_array($out) && @$out['data']>0){
            $savedId = intval($out['data']);
            $this->updateSingleDetailDirect($savedId, 'DT_IIIF_ID', $this->annotationApiUrl($savedId));
            $out['is_new'] = ($recordId == 0);
        }
        return $out;
    }

    public function saveImportedAnnotation($annotationContext, $createThumbnail=false){
        $this->setData(array('fields'=>$annotationContext));
        return $this->save($createThumbnail, 0);
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
    
    private function fillDetailsFromParsedAnnotation(&$details, $parsed, $manifestRecID=0, $manifestFileID=0, $canvasRecID=0){
        
        $changed = false;
        $text = trim(strip_tags((string)@$parsed['body_text']));
        $title = $text ? substr($text, 0, 50) : substr((string)$parsed['id'], 0, 50);

        $changed = $this->setField($details, 'DT_NAME', $title) || $changed;
        $changed = $this->setField($details, 'DT_SHORT_SUMMARY', @$parsed['body_text']) || $changed;
        $changed = $this->setField($details, 'DT_ORIGINAL_IIIF_ID', @$parsed['id']) || $changed;
        $changed = $this->setField($details, 'DT_ANNOTATION_INFO', @$parsed['json']) || $changed;
        $changed = $this->setField($details, 'DT_URL', @$parsed['canvas']) || $changed;
        $changed = $this->setField($details, 'DT_ANNOTATION_MOTIVATION', $this->getTermId(@$parsed['motivation'], 'TRM_ANNOTATION_MOTIVATION_COMMENTING') ) || $changed;
        $changed = $this->setField($details, 'DT_LANGUAGE', $this->getLanguageTermId(@$parsed['language'])) || $changed;
        $changed = $this->setField($details, 'DT_ANNOTATION_SELECTOR_TYPE', $this->getTermId(@$parsed['selector_type'])) || $changed;
        $changed = $this->setField($details, 'DT_ANNOTATION_SELECTOR_VALUE', @$parsed['selector_value']) || $changed;
        $changed = $this->setField($details, 'DT_ANNOTATION_STATE', $this->getTermId(@$parsed['state'], 'TRM_ANNOTATION_STATE_IMPORTED')) || $changed;

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
    private function getAnnotationImage($manifestUrl, $anno_uid, $region, $canvas_url){

        if(!$region){
            return 0;
        }
            $region = substr($region, 5);

            // https://fragmentarium.ms/metadata/iiif/F-hsd6/canvas/F-hsd6/fol_2r.jp2.json
            // https://gallica.bnf.fr/iiif/ark:/12148/bpt6k9604118j/canvas/f11/
            $url = $canvas_url;

            if($manifestUrl){ //target manifest url
                //find image service uri by canvas in manifest
                $iiif_manifest_url = filter_var($manifestUrl, FILTER_SANITIZE_URL);
                $iiif_manifest = loadRemoteURLContent($iiif_manifest_url);//retrieve iiif manifest into manifest
                $iiif_manifest = json_decode($iiif_manifest, true);
                if($iiif_manifest!==false && is_array($iiif_manifest)){

                    //"@context": "http://iiif.io/api/presentation/2/context.json"
                    //sequences->canvases->images->resource->service->@id
                    $context_url = 'http'.'://iiif.io/api/presentation/2/context.json';

                    if(@$iiif_manifest['@context']==$context_url){

                        $url = $this->getImageUrlV2($iiif_manifest, $url);

                    }else{ //version 3
                        //"@context": "http://iiif.io/api/presentation/3/context.json"
                        //items(type:Canvas)->items[AnnotationPage]->items[Annotation]->body->service[0]->id

                        $url = $this->getImageUrlV3($iiif_manifest, $url);
                    }

                }
            }

            if(strpos($url, '/canvas/')>0){
                //remove /canvas to get image url
                $url = str_replace('/canvas/','/',$url);
            }
            // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
            $url = $url.'/'.$region.'/!200,200/0/default.jpg';

            $tmp_file = HEURIST_SCRATCH_DIR.'/'.basename($anno_uid.'.jpg');//basename for snyk
            //tempnam(HEURIST_SCRATCH_DIR,'iiif_thumb');
            //tempnam()
            $res = saveURLasFile($url, $tmp_file);

            if($res>0){
                $entity = new DbRecUploadedFiles($this->system);

                $dtl_UploadedFileID = $entity->registerFile($tmp_file, null);//it returns ulf_ID

                if($dtl_UploadedFileID===false){
                    $err_msg = $this->system->getError();
                    $err_msg = $err_msg['message'];
                    $this->system->clearError();
                }else{
                    if(is_array($dtl_UploadedFileID)&&!empty($dtl_UploadedFileID)){$dtl_UploadedFileID = $dtl_UploadedFileID[0];}
                    return $dtl_UploadedFileID;
                }
            }
    }
}
