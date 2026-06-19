<?php
/**
* DbIiifCanvas.php - Record-type-backed entity for RT_IIIF_CANVAS.
*/
namespace hserv\entity;

use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/DbRecordTypeEntity.php';
require_once dirname(__FILE__).'/DbRecUploadedFiles.php';

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

    /**
     * Create or update one RT_IIIF_CANVAS record from a v2/v3 Canvas JSON object.
     *
     * Returns an array compatible with the annotation import reporting flags:
     *   recID, is_new, is_retained, is_preserved_local
     */
    public function ensureFromCanvas(array $canvas, bool $preserveLocal=true)
    {
        if(!$this->ensureDefinitionsReady($this->system->isAdmin())){
            return false;
        }

        $canvasId = $this->getJsonId($canvas);
        if(!$canvasId){
            $canvasId = 'heurist-import-canvas-'.md5(json_encode($canvas));
        }

        $recordId = $this->findCanvasRecord($canvasId);
        $details = $recordId>0 ? $this->loadRecordDetails($recordId) : array();

        if($recordId>0 && $preserveLocal && $this->isProtectedFromReimport($details)){
            return array('recID'=>$recordId, 'is_preserved_local'=>true);
        }

        $oldDetails = $details;
        $this->fillDetailsFromCanvas($details, $canvas, $canvasId);

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

    private function findCanvasRecord(string $canvasId): int
    {
        if($canvasId==='' || !$this->ensureDefinitionsReady(false)){
            return 0;
        }
        return $this->findRecordByField('DT_IIIF_ID', $canvasId);
    }

    private function fillDetailsFromCanvas(array &$details, array $canvas, string $canvasId): void
    {
        $label = $this->normaliseLangValue(@$canvas['label']);
        if(!$label){
            $label = basename(parse_url($canvasId, PHP_URL_PATH) ?: $canvasId);
        }

        $summary = $this->normaliseLangValue(@$canvas['summary']);
        if(!$summary){
            $summary = $this->normaliseLangValue(@$canvas['description']);
        }

        $this->setField($details, 'DT_NAME', $label);
        $this->setField($details, 'DT_SHORT_SUMMARY', $summary);
        $this->setField($details, 'DT_IIIF_ID', $canvasId);
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

        $mediaUrl = $this->extractPrimaryPaintingBodyUrl($canvas);
        if($mediaUrl){
            $ulfID = $this->registerExternalUrl($mediaUrl);
            if($ulfID>0){
                $this->setField($details, 'DT_FILE_RESOURCE', intval($ulfID));
            }
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
     * Updates only DT_ANNOTATION_STATE directly in recDetails to avoid a second recordSave().
     * Until DT_ANNOTATION_STATE is renamed, Canvas records reuse the IIIF state vocabulary.
     */
    public function markSavedFromHeuristEditor(int $recID): bool
    {
        if($recID<1 || !$this->ensureDefinitionsReady(false)){
            return false;
        }

        $details = $this->loadRecordDetails($recID);
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
            return $this->updateCanvasStateDirect($recID, intval($modified));
        }

        if($oldState < 1){
            return $this->updateCanvasStateDirect($recID, intval($heurist), true);
        }

        return true;
    }

    /** Directly update/insert DT_ANNOTATION_STATE, and optionally assign DT_IIIF_ID for new Heurist-created Canvas records. */
    public function updateCanvasStateDirect(int $recID, int $stateTermID, bool $assignId=false): bool
    {
        if($recID<1 || $stateTermID<1 || !$this->ensureDefinitionsReady(false)){
            return false;
        }

        $mysqli = $this->system->getMysqli();
        $recID = intval($recID);
        $stateTermID = intval($stateTermID);
        $stateDtID = intval(DT_ANNOTATION_STATE);

        $dtlID = mysql__select_value($mysqli,
            'SELECT dtl_ID FROM recDetails WHERE dtl_RecID='.$recID
            .' AND dtl_DetailTypeID='.$stateDtID.' LIMIT 1');

        if($dtlID>0){
            $query = 'UPDATE recDetails SET dtl_Value='.$stateTermID.' WHERE dtl_ID='.intval($dtlID);
        }else{
            $query = 'INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) VALUES ('
                .$recID.','.$stateDtID.','.$stateTermID.')';
        }

        if($mysqli->query($query) === false){
            return false;
        }

        if($assignId){
            $existingId = $this->getFirstDetailValue($this->loadRecordDetails($recID), 'DT_IIIF_ID');
            if(!$existingId){
                $iiifId = $mysqli->real_escape_string(
                    HEURIST_BASE_URL.'api/'.$this->system->dbname().'/iiif/canvas/'.$recID
                );
                $query = 'INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) VALUES ('
                    .$recID.','.intval(DT_IIIF_ID).',"'.$iiifId.'")';
                return $mysqli->query($query) !== false;
            }
        }

        return true;
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
