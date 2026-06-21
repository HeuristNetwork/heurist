<?php
/**
* DbIiifManifest.php - Record-type-backed entity for RT_IIIF_MANIFEST.
*/
namespace hserv\entity;

use hserv\structure\ConceptCode;
use hserv\iiif\IiifManifestJson;

require_once dirname(__FILE__).'/DbRecordTypeEntity.php';
require_once dirname(__FILE__).'/DbIiifCanvas.php';
require_once dirname(__FILE__).'/../iiif/IiifManifestJson.php';

/**
 * Manages IIIF Manifest records stored as user records of RT_IIIF_MANIFEST.
 */
class DbIiifManifest extends DbRecordTypeEntity
{
    protected function initRecordTypeEntity(): void
    {
        $this->recordTypeConst = 'RT_IIIF_MANIFEST';
        $this->recordTypeConceptCode = '2-110';

        $this->requiredConstants = array(
            'RT_IIIF_MANIFEST',
            'RT_IIIF_CANVAS',
            'DT_NAME',
            'DT_EXTENDED_DESCRIPTION',
            'DT_FILE_RESOURCE',
            'DT_COPYRIGHT',
            'DT_IIIF_ID',
            'DT_ORIGINAL_IIIF_ID',
            'DT_IIIF_IMPORT_MODE',
            'DT_IIIF_CANVAS'
        );

        // Fill these when the term concept codes are final.
        $this->requiredTermConstants = array(
            'TRM_IIIF_IMPORT_MODE_OVERLAY' => '2-10444',
            'TRM_IIIF_IMPORT_MODE_MANAGED' => '2-10446'
        );
    }

    /**
     * Create or update a minimal RT_IIIF_MANIFEST record for a registered Manifest file.
     * $manifestFile is the resolved file array from ImportAnnotations.
     *
     * DT_IIIF_ID is the canonical Heurist Manifest API URL.
     * DT_ORIGINAL_IIIF_ID stores the source Manifest id or source URL.
     */
    public function ensureFromManifestFile(array $manifestFile, array $manifest, string $importMode='overlay')
    {
        if(!$this->ensureDefinitionsReady($this->system->isAdmin())){
            return 0;
        }

        $sourceManifestId = $this->getJsonId($manifest) ?: (string)@$manifestFile['source_url'];
        $recordId = $this->findManifestRecord($manifestFile, $sourceManifestId);

        $details = array();
        $titleValues = $this->normaliseLangValues(@$manifest['label']);
        if(empty($titleValues)){
            $titleValues = array(basename((string)@$manifestFile['source_url']));
        }

        $this->setField($details, 'DT_NAME', $titleValues);
        $this->setField($details, 'DT_FILE_RESOURCE', intval(@$manifestFile['ulf_ID']));
        if($sourceManifestId){
            $this->setField($details, 'DT_ORIGINAL_IIIF_ID', $sourceManifestId);
        }
        if($recordId>0){
            $this->setField($details, 'DT_IIIF_ID', $this->manifestApiUrl($recordId));
        }

        $modeValue = $this->resolveImportModeTerm($importMode);
        if($modeValue){
            $this->setField($details, 'DT_IIIF_IMPORT_MODE', $modeValue);
        }

        $descValues = $this->normaliseLangValues(@$manifest['summary']);
        if(empty($descValues)){
            $descValues = $this->normaliseLangValues(@$manifest['description']);
        }
        $this->setField($details, 'DT_EXTENDED_DESCRIPTION', $descValues);

        $copyrightValues = $this->extractCopyrightValues($manifest);
        if(!empty($copyrightValues)){
            $this->setField($details, 'DT_COPYRIGHT', $copyrightValues);
        }

        $res = $this->saveRecordDetails($recordId, $details, 0);
        if(!is_array($res) || @$res['status']!=HEURIST_OK || intval(@$res['data'])<1){
            if(is_array($res) && @$res['message']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, $res['message']);
            }
            return 0;
        }

        $manifestRecID = intval($res['data']);
        $this->updateSingleDetailDirect($manifestRecID, 'DT_IIIF_ID', $this->manifestApiUrl($manifestRecID));
        return $manifestRecID;
    }

    private function extractCopyrightValues(array $manifest): array
    {
        $required = $manifest['requiredStatement'] ?? null;
        if(is_array($required)){
            $values = $this->normaliseLangValues($required['value'] ?? null);
            if(!empty($values)){
                return $values;
            }
        }

        // IIIF v2 commonly uses attribution/license rather than requiredStatement.
        $values = $this->normaliseLangValues($manifest['attribution'] ?? null);
        if(!empty($values)){
            return $values;
        }

        $rights = trim((string)($manifest['rights'] ?? ''));
        if($rights===''){
            $rights = trim((string)($manifest['license'] ?? ''));
        }

        return $rights!=='' ? array($rights) : array();
    }

    private function resolveImportModeTerm(string $importMode): ?int
    {
        switch($importMode){
            case 'overlay':
                return $this->getTermId('TRM_IIIF_IMPORT_MODE_OVERLAY') ?: $this->getTermId('overlay');
            case 'managed':
                return $this->getTermId('TRM_IIIF_IMPORT_MODE_MANAGED') ?: $this->getTermId('managed');
            default:
                return $this->getTermId($importMode);
        }
    }

    private function findManifestRecord(array $manifestFile, ?string $sourceManifestId): int
    {
        $mysqli = $this->system->getMysqli();
        $rty = $this->recordTypeId();
        if(!$rty){
            return 0;
        }

        $conditions = array();

        if(defined('DT_FILE_RESOURCE') && intval(@$manifestFile['ulf_ID'])>0){
            $ulfID = intval($manifestFile['ulf_ID']);
            $conditions[] = '(d.dtl_DetailTypeID='.DT_FILE_RESOURCE.' AND (d.dtl_UploadedFileID='.$ulfID.' OR d.dtl_Value="'.$ulfID.'"))';
        }

        if(defined('DT_ORIGINAL_IIIF_ID') && $sourceManifestId){
            $conditions[] = '(d.dtl_DetailTypeID='.DT_ORIGINAL_IIIF_ID.' AND d.dtl_Value="'.addslashes($sourceManifestId).'")';
        }

        if(empty($conditions)){
            return 0;
        }

        $query = 'SELECT r.rec_ID FROM Records r, recDetails d WHERE r.rec_ID=d.dtl_RecID '
            .'AND r.rec_RecTypeID='.$rty.' AND ('.implode(' OR ', $conditions).') LIMIT 1';
        $recID = mysql__select_value($mysqli, $query);
        return $recID ? intval($recID) : 0;
    }



    /** Replace the ordered managed Canvas list for this Manifest. */
    public function setCanvasRefs(int $manifestRecID, array $canvasRecIDs): bool
    {
        if($manifestRecID<1 || !$this->ensureDefinitionsReady(false) || !$this->isManifestRecord($manifestRecID)){
            return false;
        }

        $canvasRecIDs = array_values(array_filter(array_map('intval', $canvasRecIDs), function($id){ return $id>0; }));
        $details = $this->loadRecordDetails($manifestRecID);

        if(!empty($canvasRecIDs)){
            $this->setField($details, 'DT_IIIF_CANVAS', $canvasRecIDs);
        }else{
            $this->removeField($details, 'DT_IIIF_CANVAS');
        }

        $res = $this->saveRecordDetails($manifestRecID, $details, 0);
        if(!is_array($res) || @$res['status']!=HEURIST_OK || intval(@$res['data'])<1){
            if(is_array($res) && @$res['message']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, $res['message']);
            }
            return false;
        }
        return true;
    }

    /**
     * Return a v3 overlay Manifest for Mirador.
     * Existing Canvas.annotations are replaced with Heurist AnnotationPage URLs
     * to avoid showing source annotations and imported DB annotations twice.
     */
    public function getOverlayManifestJson(int $manifestRecID, bool $omitAnnotationPages=false): ?array
    {
        if(!$this->ensureDefinitionsReady(false) || !$this->isManifestRecord($manifestRecID)){
            return null;
        }

        $manifestDetails = $this->loadRecordDetails($manifestRecID);

        // Full-management mode is generated from Heurist records. The source
        // Manifest is not reloaded here: Canvas ids, painting bodies, thumbnails
        // and AnnotationPage links are all derived from RT_IIIF_CANVAS records.
        if($this->isManagedManifestDetails($manifestDetails)){
            return $this->buildManagedManifestJson($manifestRecID, $manifestDetails, $omitAnnotationPages);
        }

        return $this->buildOverlayManifestJson($manifestRecID, $manifestDetails, $omitAnnotationPages);
    }

    private function buildOverlayManifestJson(int $manifestRecID, array $manifestDetails, bool $omitAnnotationPages=false): ?array
    {
        // A valid RT_IIIF_MANIFEST record may be created before any source Manifest
        // file or managed Canvas list is attached. Return a legal empty IIIF Manifest
        // instead of letting the API turn this into a technical notfound response.
        $sourceFileID = intval($this->getFirstDetailValue($manifestDetails, 'DT_FILE_RESOURCE'));
        if($sourceFileID < 1){
            return $this->buildManagedManifestJson($manifestRecID, $manifestDetails, $omitAnnotationPages);
        }

        $manifest = $this->loadSourceManifestForRecord($manifestRecID, $manifestDetails);
        if(!is_array($manifest)){
            // Missing, unreadable or malformed source manifests should not produce
            // an API error for Mirador. Return a valid empty v3 Manifest instead.
            $this->system->clearError();
            return $this->buildManagedManifestJson($manifestRecID, $manifestDetails, $omitAnnotationPages);
        }

        // Overlay output is only safe for IIIF Presentation API v3 manifests.
        // Presentation v2 uses sequences/canvases/otherContent, so do not apply
        // the v3 Canvas.annotations replacement logic to v2 source manifests.
        // v2 should be imported in managed mode, then generated from Heurist
        // RT_IIIF_CANVAS records. For legacy/bad overlay records, return an
        // empty v3 Manifest rather than a blocked API response.
        $overlay = IiifManifestJson::transformOverlayV3Manifest(
            $manifest,
            function(string $canvasId) use ($manifestRecID): string {
                return $this->annotationPageUrl($manifestRecID, $canvasId);
            },
            $omitAnnotationPages
        );

        if(!is_array($overlay)){
            $this->system->clearError();
            return $this->buildManagedManifestJson($manifestRecID, $manifestDetails, $omitAnnotationPages);
        }

        return $overlay;
    }

    private function buildManagedManifestJson(int $manifestRecID, array $manifestDetails, bool $omitAnnotationPages=false): array
    {
        $titleValues = $this->getDetailValues($manifestDetails, 'DT_NAME');
        $summaryValues = $this->getDetailValues($manifestDetails, 'DT_EXTENDED_DESCRIPTION');
        if(empty($summaryValues)){
            $summaryValues = $this->getDetailValues($manifestDetails, 'DT_SHORT_SUMMARY');
        }

        $copyrightValues = $this->getDetailValues($manifestDetails, 'DT_COPYRIGHT');
        $options = array(
            'summary' => !empty($summaryValues) ? $this->toIiifLanguageMap($summaryValues) : null
        );

        if(!empty($copyrightValues)){
            $options['copyright'] = $this->toIiifLanguageMap($copyrightValues);

            $copyright = trim((string)reset($copyrightValues));
            if(preg_match('/^https?:\/\//i', $copyright)){
                $options['rights'] = $copyright;
            }
        }

        return IiifManifestJson::composeManifest(
            $this->manifestApiUrl($manifestRecID),
            $this->toIiifLanguageMap($titleValues, 'Manifest '.$manifestRecID),
            $this->buildManagedCanvasItems($manifestDetails, $manifestRecID, $omitAnnotationPages),
            $options
        );
    }


    private function isManagedManifestDetails(array $manifestDetails): bool
    {
        $canvasRefs = $this->getDetailValues($manifestDetails, 'DT_IIIF_CANVAS');
        if(!empty($canvasRefs)){
            return true;
        }

        $mode = intval($this->getFirstDetailValue($manifestDetails, 'DT_IIIF_IMPORT_MODE'));
        $managed = $this->getTermId('TRM_IIIF_IMPORT_MODE_MANAGED') ?: $this->getTermId('managed');
        return $mode>0 && $managed>0 && $mode===$managed;
    }

    private function buildManagedCanvasItems(array $manifestDetails, int $manifestRecID, bool $omitAnnotationPages=false): array
    {
        $canvasRecIDs = $this->getDetailValues($manifestDetails, 'DT_IIIF_CANVAS');
        $items = array();

        foreach($canvasRecIDs as $canvasRecID){
            $canvas = $this->buildManagedCanvasItem(intval($canvasRecID), $manifestRecID, $omitAnnotationPages);
            if($canvas){
                $items[] = $canvas;
            }
        }

        return $items;
    }

    private function buildManagedCanvasItem(int $canvasRecID, int $manifestRecID, bool $omitAnnotationPages=false): ?array
    {
        return $this->dbCanvas()->canvasJsonForCanvasRecord($canvasRecID, array(
            'manifest_rec_id' => $manifestRecID,
            'omit_annotation_pages' => $omitAnnotationPages
        ));
    }

    private function manifestApiUrl(int $manifestRecID): string
    {
        return rtrim(HEURIST_BASE_URL, '/')
            .'/api/'.$this->system->dbname()
            .'/iiif/manifest/'.intval($manifestRecID);
    }
    
    private function dbCanvas(): DbIiifCanvas
    {
        static $dbCanvas = null;
        if($dbCanvas === null){
            $dbCanvas = new DbIiifCanvas($this->system);
        }
        return $dbCanvas;
    }

    public function isIiifManifestRecord(int $manifestRecID): bool
    {
        return $this->ensureDefinitionsReady(false) && $this->isManifestRecord($manifestRecID);
    }

    private function isManifestRecord(int $manifestRecID): bool
    {
        if($manifestRecID<1 || !$this->recordTypeId()){
            return false;
        }

        $recType = mysql__select_value($this->system->getMysqli(),
            'SELECT rec_RecTypeID FROM Records WHERE rec_ID='.intval($manifestRecID));
        return intval($recType)===$this->recordTypeId();
    }

    private function loadSourceManifestForRecord(int $manifestRecID, array $manifestDetails): ?array
    {
        $sourceUrl = null;
        $ulfID = intval($this->getFirstDetailValue($manifestDetails, 'DT_FILE_RESOURCE'));
        if($ulfID>0){
            $row = mysql__select_row($this->system->getMysqli(),
                'SELECT ulf_ObfuscatedFileID, ulf_ExternalFileReference FROM recUploadedFiles WHERE ulf_ID='.$ulfID.' LIMIT 1');
            if(is_array($row)){
                $sourceUrl = $row[1] ? $row[1]
                    : HEURIST_BASE_URL.'?db='.$this->system->dbname().'&file='.$row[0];
            }
        }

        if(!$sourceUrl){
            $this->system->addError(HEURIST_NOT_FOUND,
                'Cannot locate the registered source Manifest file for IIIF Manifest record '.$manifestRecID);
            return null;
        }

        $content = loadRemoteURLContent($sourceUrl);
        if(!$content){
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                'Source Manifest is not accessible: '.$sourceUrl);
            return null;
        }

        $json = json_decode($content, true);
        if(!is_array($json)){
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                'Source Manifest is not valid JSON. '.json_last_error_msg());
            return null;
        }

        return $json;
    }

    private function annotationPageUrl(int $manifestRecID, string $canvasId): string
    {
        return rtrim(HEURIST_BASE_URL, '/')
            .'/api/'.$this->system->dbname()
            .'/annotations'  //Temorarely '/'.intval($manifestRecID)
            .'/pages?uri='.rawurlencode($canvasId);
    }

}
