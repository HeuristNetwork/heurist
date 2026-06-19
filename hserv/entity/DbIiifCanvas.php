<?php
/**
* DbIiifCanvas.php - Record-type-backed entity for RT_IIIF_CANVAS.
*/
namespace hserv\entity;

use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/DbRecordTypeEntity.php';

/**
 * Manages IIIF Canvas records stored as user records of RT_IIIF_CANVAS.
 *
 * The current managed-import phase imports Canvas identity/order/metadata and
 * links annotations to these Canvas records. Painting media registration is left
 * to the next phase.
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

        $res = $this->saveRecordDetails($recordId, $details, 0);
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
            $this->setField($details, $this->detailId('DT_WIDTH', '2-1013'), intval($canvas['width']));
        }
        if(isset($canvas['height'])){
            $this->setField($details, $this->detailId('DT_HEIGHT', '2-1014'), intval($canvas['height']));
        }
        if(isset($canvas['duration'])){
            $this->setField($details, $this->detailId('DT_DURATION', '2-1133'), floatval($canvas['duration']));
        }

        $thumb = $this->extractThumbnailFileId($canvas);
        if($thumb>0){
            $this->setField($details, 'DT_THUMBNAIL', $thumb);
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

    /**
     * Placeholder for future registered-file thumbnail reuse.
     * Current imported manifests usually contain external thumbnail URLs, not local ulf_IDs.
     */
    private function extractThumbnailFileId(array $canvas): int
    {
        return 0;
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
