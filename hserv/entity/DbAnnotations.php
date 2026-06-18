<?php
/**
* DbAnnotations.php - Class DbAnnotations
*
* Manages IIIF annotation records.
*/
namespace hserv\entity;

use hserv\entity\DbRecordTypeEntity;
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/DbRecordTypeEntity.php';

/**
* Class DbAnnotations
*
* Manages IIIF Web Annotation records used by Mirador and the IIIF annotation import workflow.
*/
class DbAnnotations extends DbRecordTypeEntity
{
    protected function initRecordTypeEntity(): void
    {
        $this->recordTypeConst = 'RT_IIIF_ANNOTATION';
        $this->recordTypeConceptCode = '2-101';

        $this->requiredConstants = array(
            'RT_IIIF_ANNOTATION',
            'RT_IIIF_MANIFEST',
            'DT_NAME',
            'DT_SHORT_SUMMARY',
            'DT_EXTENDED_DESCRIPTION',
            'DT_URL',
            'DT_ORIGINAL_RECORD_ID',
            'DT_ANNOTATION_INFO',
            'DT_ANNOTATION_MANIFEST',
            'DT_ANNOTATION_TARGET',
            'DT_ANNOTATION_STATE',
            'DT_ANNOTATION_MOTIVATION',
            'DT_ANNOTATION_SELECTOR_TYPE',
            'DT_ANNOTATION_SELECTOR_VALUE',
            'DT_LANGUAGE',
            'DT_THUMBNAIL'
        );

        // Entity-local term constants. Fill the empty placeholders when the concept codes are final.
        $this->requiredTermConstants = [
            'TRM_ANNOTATION_STATE_IMPORTED' => '2-10430',
            'TRM_ANNOTATION_STATE_MIRADOR'  => '2-10431',
            'TRM_ANNOTATION_STATE_HEURIST'  => '2-10432',
            'TRM_ANNOTATION_STATE_MODIFIED' => '2-10433',
            'TRM_ANNOTATION_STATE_OBSOLETE' => '2-10434',
            'TRM_ANNOTATION_STATE_REMOVED'  => '2-10435',

            'TRM_ANNOTATION_MOTIVATION_COMMENTING' => '2-10419',
            'TRM_SELECTOR_FRAGMENT' => '2-10433',
            'TRM_SELECTOR_SVG' => '2-10434',
            
            'TRM_VOCAB_LANGUAGE' => '2-496'
        ];        
        
    }
    
    

    /**
     * Returns an IIIF AnnotationPage for Mirador.
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

        $sjson = array('id'=>"https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]", 'type' => 'AnnotationPage', 'items' => array());

        if(@$this->data['recID']!='pages'){
            $item = $this->findItembyUUID(@$this->data['recID']);
            if($item!=null){
                $anno = json_decode($item, true);
                if($anno){
                    $sjson['items'] = array($anno);
                }
            }
            return $sjson;
        }

        if(!@$this->data['uri']){
            $params = USanitize::sanitizeInputArray();
            $this->data['uri'] = @$params['uri'];
        }

        $items = $this->findItemsByCanvas(@$this->data['uri']);
        if(isEmptyArray($items)){
            return $sjson;
        }

        foreach($items as $item){
            $anno = json_decode($item, true);
            if($anno && @$anno['type']=='Annotation'){
                $sjson['items'][] = $anno;
            }
        }

        return $sjson;
    }

    private function findItemsByCanvas($canvasUri){
        if($this->ensureDefinitionsReady(false)){
            $mysqli = $this->system->getMysqli();
            $query = 'SELECT d2.dtl_Value FROM recDetails d1, recDetails d2, Records r WHERE '
                .'r.rec_ID=d1.dtl_RecID AND r.rec_ID=d2.dtl_RecID AND r.rec_RecTypeID='.intval($this->recordTypeId())
                .' AND d1.dtl_DetailTypeID='.DT_URL .' AND d1.dtl_Value="'.addslashes($canvasUri).'"'
                .' AND d2.dtl_DetailTypeID='.DT_ANNOTATION_INFO;
            return mysql__select_list2($mysqli, $query);
        }
        return array();
    }

    private function findItembyUUID($uuid){
        if($this->ensureDefinitionsReady(false)){
            $mysqli = $this->system->getMysqli();
            $query = 'SELECT d2.dtl_Value FROM recDetails d1, recDetails d2, Records r WHERE '
                .'r.rec_ID=d1.dtl_RecID AND r.rec_ID=d2.dtl_RecID AND r.rec_RecTypeID='.intval($this->recordTypeId())
                .' AND d1.dtl_DetailTypeID='.DT_ORIGINAL_RECORD_ID .' AND d1.dtl_Value="'.addslashes($uuid).'"'
                .' AND d2.dtl_DetailTypeID='.DT_ANNOTATION_INFO.' LIMIT 1';
            return mysql__select_value($mysqli, $query);
        }
        return null;
    }

    private function findRecIDbyUUID($uuid){
        return $this->findRecIDbyOriginalId($uuid, 0);
    }

    private function findRecIDbyOriginalId($annotationId, $manifestRecID=0){
        if(!$annotationId || !$this->ensureDefinitionsReady(false)){
            return 0;
        }

        $extra = array();
        if($manifestRecID>0 && defined('DT_ANNOTATION_MANIFEST')){
            $extra['DT_ANNOTATION_MANIFEST'] = intval($manifestRecID);
        }

        $recordId = $this->findRecordByField('DT_ORIGINAL_RECORD_ID', $annotationId, $extra);
        if(!$recordId && $manifestRecID>0){
            // fallback for annotations imported before the manifest link existed
            return $this->findRecIDbyOriginalId($annotationId, 0);
        }
        return $recordId;
    }

    public function delete($disable_foreign_checks = false){
        if(@$this->data['recID']){
            if(!$this->_validatePermission()){
                return false;
            }
            $recordId = $this->findRecIDbyUUID($this->data['recID']);
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

        $parsed = $this->parseIncomingAnnotation($fields);
        if(!$parsed){
            return false;
        }

        $manifestRecID = intval(@$fields['manifestRecID']);
        $manifestFileID = intval(@$fields['manifestFileID']);
        $canvasUrl = @$fields['canvasOriginalId'] ?: @$fields['canvas'] ?: @$parsed['canvas'];
        $manifestUrl = @$fields['manifestUrl'];

        if($canvasUrl){
            $parsed['canvas'] = $canvasUrl;
        }

        $recordId = $this->findRecIDbyOriginalId($parsed['id'], $manifestRecID);
        $details = $this->loadRecordDetails($recordId);

        if($recordId>0 && $this->isProtectedFromReimport($details) && @$fields['preserveLocal']!==0){
            return array('status'=>HEURIST_OK, 'data'=>$recordId, 'is_preserved_local'=>true);
        }

        $oldJson = $this->getFirstDetailValue($details, 'DT_ANNOTATION_INFO');
        $changed = $this->fillDetailsFromParsedAnnotation($details, $parsed, $manifestRecID, $manifestFileID);

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
            'details' => $details
        );

        $out = recordSave($this->system, $record, false, true, 0);
        if(is_array($out) && @$out['data']>0){
            $out['is_new'] = ($recordId == 0);
        }
        return $out;
    }

    public function saveImportedAnnotation($annotationContext, $createThumbnail=false){
        $this->setData(array('fields'=>$annotationContext));
        return $this->save($createThumbnail, 0);
    }
    
    private function fillDetailsFromParsedAnnotation(&$details, $parsed, $manifestRecID=0, $manifestFileID=0){
        
        $changed = false;
        $text = trim(strip_tags((string)@$parsed['body_text']));
        $title = $text ? substr($text, 0, 50) : substr((string)$parsed['id'], 0, 50);

        $changed = $this->setField($details, 'DT_NAME', $title) || $changed;
        $changed = $this->setField($details, 'DT_SHORT_SUMMARY', @$parsed['body_text']) || $changed;
        $changed = $this->setField($details, 'DT_ORIGINAL_RECORD_ID', @$parsed['id']) || $changed;
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
        /*
        if($manifestFileID>0){
            $changed = $this->appendUniqueField($details, 'DT_FILE_RESOURCE', $manifestFileID) || $changed;
        }
        */

        return $changed;
    }

    private function parseIncomingAnnotation($fields){
        $anno = @$fields['annotation'];
        if(!$anno){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Annotation data is not defined');
            return false;
        }

        $state = @$fields['state'] ?: (@$fields['source']=='mirador' ? 'mirador' : 'imported');

        if(is_array($anno) && @$anno['data']){
            $decoded = json_decode($anno['data'], true);
            if(!is_array($decoded)){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Annotation JSON is invalid: '.json_last_error_msg());
                return false;
            }
            $id = @$anno['uuid'] ?: @$decoded['id'] ?: @$decoded['@id'];
            $parsed = $this->parseWebAnnotationArray($decoded, $id, @$anno['canvas']);
            $parsed['json'] = json_encode($decoded, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $parsed['state'] = $state;
            return $parsed;
        }

        if(!is_array($anno)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Annotation data is not an array');
            return false;
        }

        if($this->isOpenAnnotation($anno)){
            $webAnno = $this->convertOpenAnnotationToWebAnnotation($anno, @$fields['canvasOriginalId']);
            $parsed = $this->parseWebAnnotationArray($webAnno, @$webAnno['id'], @$fields['canvasOriginalId']);
            $parsed['json'] = json_encode($webAnno, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $parsed['state'] = $state;
            return $parsed;
        }

        if(@$anno['type']=='Annotation'){
            $parsed = $this->parseWebAnnotationArray($anno, @$anno['id'], @$fields['canvasOriginalId']);
            $parsed['json'] = json_encode($anno, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $parsed['state'] = $state;
            return $parsed;
        }

        $this->system->addError(HEURIST_INVALID_REQUEST, 'Unsupported annotation format');
        return false;
    }
    
    private function parseLanguageFromBody($body): ?string
    {
        if(!is_array($body)){
            return null;
        }

        if(isset($body['lang'])){
            return is_array($body['lang']) ? reset($body['lang']) : $body['lang'];
        }
        
        if(isset($body['language'])){
            return is_array($body['language']) ? reset($body['language']) : $body['language'];
        }

        if(isset($body['languages'])){
            return is_array($body['languages']) ? reset($body['languages']) : $body['languages'];
        }

        return null;
    }
    
    private function parseWebAnnotationArray($anno, $fallbackId=null, $fallbackCanvas=null){
        $bodyText = '';
        $language = null;
        $body = @$anno['body'];
        $bodies = is_array($body) && array_keys($body)===range(0, count($body)-1) ? $body : array($body);
        foreach($bodies as $b){
            if(is_array($b)){
                if((@$b['type']=='TextualBody' || @$b['value']) && @$b['value']!==null){
                    $bodyText = $b['value'];
                    //$language = @$b['language'] ?: (@$b['lang'] ?: (@$b['languages'] ?: $language));
                    $lang = $this->parseLanguageFromBody($b);
                    if($lang){
                        $language = $lang;
                    }                    
                    
                    break;
                }
            }elseif(is_string($b) && $b!==''){
                $bodyText = $b;
                break;
            }
        }

        $motivation = @$anno['motivation'];
        if(is_array($motivation)){
            $motivation = reset($motivation);
        }
        $motivation = $this->stripPrefix((string)$motivation);

        $target = @$anno['target'];
        $canvas = $fallbackCanvas;
        $selectorType = null;
        $selectorValue = null;

        if(is_string($target)){
            $parts = explode('#', $target, 2);
            $canvas = $parts[0];
            if(@$parts[1]){
                $selectorType = 'FragmentSelector';
                $selectorValue = $parts[1];
            }
        }elseif(is_array($target)){
            if(array_keys($target)===range(0, count($target)-1)){
                $target = reset($target);
            }
            $canvas = @$target['source'] ?: (@$target['id'] ?: $canvas);
            $selector = @$target['selector'];
            if(is_array($selector) && array_keys($selector)===range(0, count($selector)-1)){
                $selector = reset($selector);
            }
            if(is_array($selector)){
                $selectorType = $this->stripPrefix((string)(@$selector['type'] ?: @$selector['@type']));
                $selectorValue = @$selector['value'];
            }
        }

        $id = @$anno['id'] ?: (@$anno['@id'] ?: $fallbackId);
        if(!$id){
            $id = 'heurist-import-'.md5(($canvas ?: '').json_encode($anno));
        }

        return array(
            'id' => $id,
            'body_text' => $bodyText,
            'motivation' => $motivation ?: 'commenting',
            'language' => $language,
            'canvas' => $canvas,
            'selector_type' => $selectorType,
            'selector_value' => $selectorValue,
            'json' => json_encode($anno, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
        );
    }

    private function stripPrefix($value){
        $pos = strrpos($value, ':');
        return $pos!==false ? substr($value, $pos+1) : $value;
    }

    private function isOpenAnnotation($anno){
       return @$anno['@type']=='oa:Annotation' || @$anno['type']=='oa:Annotation';
    }

    private function convertOpenAnnotationToWebAnnotation($anno, $fallbackCanvas=null){
        $id = @$anno['@id'] ?: @$anno['id'];
        $motivation = @$anno['motivation'];
        if(is_array($motivation)){
            $motivation = reset($motivation);
        }
        $motivation = $this->stripPrefix((string)$motivation) ?: 'commenting';

        $bodyText = '';
        if(is_array(@$anno['resource'])){
            $res = reset($anno['resource']);
            $bodyText = @$res['chars'] ?: (@$res['full_text'] ?: '');
        }

        $canvas = $fallbackCanvas;
        $selectors = array();
        if(is_array(@$anno['on'])){
            $target = reset($anno['on']);
            $canvas = @$target['full'] ?: $canvas;
            $sel = @$target['selector'];
            if(is_array($sel)){
                if(@$sel['default']['value']){
                    $selectors[] = array('type'=>'FragmentSelector', 'value'=>$sel['default']['value']);
                }
                if(@$sel['item']['value']){
                    $selectors[] = array('type'=>'SvgSelector', 'value'=>$sel['item']['value']);
                }
            }
        }

        return array(
            'id' => $id ?: 'heurist-import-'.md5(($canvas ?: '').json_encode($anno)),
            'type' => 'Annotation',
            'motivation' => $motivation,
            'body' => array('type'=>'TextualBody', 'value'=>$bodyText, 'format'=>'text/html'),
            'target' => array('source'=>$canvas, 'selector'=>$selectors)
        );
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
