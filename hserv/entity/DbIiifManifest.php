<?php
/**
* DbIiifManifest.php - Record-type-backed entity for RT_IIIF_MANIFEST.
*/
namespace hserv\entity;

use hserv\structure\ConceptCode;
use hserv\iiif\IiifManifestJson;

require_once dirname(__FILE__).'/DbRecordTypeEntity.php';
require_once dirname(__FILE__).'/DbIiifCanvas.php';

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
            'DT_IIIF_CANVAS'
        );

        $this->requiredTermConstants = array();
    }

    /**
     * Create or update a managed RT_IIIF_MANIFEST record for a registered Manifest file.
     * $manifestFile is the resolved file array from ImportAnnotations.
     *
     * DT_IIIF_ID is the canonical Heurist Manifest API URL.
     * DT_ORIGINAL_IIIF_ID stores the source Manifest id or source URL.
     */
    public function ensureFromManifestFile(array $manifestFile, array $manifest, string $importMode='managed')
    {
        if(!$this->ensureDefinitionsReady($this->system->isAdmin())){
            return 0;
        }

        $sourceManifestId = $this->getJsonId($manifest) ?: (string)@$manifestFile['source_url'];
        $recordId = $this->findManifestRecordForFile($manifestFile);

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

    /** Return the managed RT_IIIF_MANIFEST record that references this registered Manifest file, if any. */
    public function findManifestRecordForFile(array $manifestFile): int
    {
        if(!$this->ensureDefinitionsReady(false) || !defined('DT_FILE_RESOURCE')){
            return 0;
        }

        $ulfID = intval(@$manifestFile['ulf_ID']);
        if($ulfID<1){
            return 0;
        }

        $mysqli = $this->system->getMysqli();
        $rty = $this->recordTypeId();
        if(!$rty){
            return 0;
        }

        $query = 'SELECT r.rec_ID FROM Records r, recDetails d WHERE r.rec_ID=d.dtl_RecID '
            .'AND r.rec_RecTypeID='.$rty
            .' AND d.dtl_DetailTypeID='.DT_FILE_RESOURCE
            .' AND (d.dtl_UploadedFileID='.$ulfID.' OR d.dtl_Value="'.$ulfID.'") LIMIT 1';
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

    /** Return a managed v3 Manifest generated from an RT_IIIF_MANIFEST record. */
    public function getManifestRecordJson(int $manifestRecID, bool $omitAnnotationPages=false): ?array
    {
        if(!$this->ensureDefinitionsReady(false) || !$this->isManifestRecord($manifestRecID)){
            return null;
        }

        return $this->buildManagedManifestJson(
            $manifestRecID,
            $this->loadRecordDetails($manifestRecID),
            $omitAnnotationPages
        );
    }

    /**
     * Return Manifest JSON for a registered Manifest file.
     *
     * If an RT_IIIF_MANIFEST record references this file, the Manifest is managed
     * and generated from Heurist records. Otherwise the registered source Manifest
     * is returned directly for v2, or transformed as a v3 annotation overlay.
     */
    public function getManifestFileJson(array $fileinfo, bool $omitAnnotationPages=false): ?array
    {
        if(!$this->isIiifManifestFile($fileinfo)){
            $this->system->addError(HEURIST_NOT_FOUND, 'Registered file is not an IIIF Manifest');
            return null;
        }

        $managedRecID = $this->findManifestRecordForFile($fileinfo);
        if($managedRecID>0){
            return $this->getManifestRecordJson($managedRecID, $omitAnnotationPages);
        }

        $manifest = $this->loadSourceManifestForFile($fileinfo);
        if(!is_array($manifest)){
            return null;
        }

        if(IiifManifestJson::isV2Manifest($manifest)){
            return $manifest;
        }

        if(IiifManifestJson::isV3Manifest($manifest)){
            $overlay = IiifManifestJson::transformOverlayV3Manifest(
                $manifest,
                function(string $canvasId): string {
                    return $this->annotationPageUrlForCanvas($canvasId);
                },
                $omitAnnotationPages
            );
            return is_array($overlay) ? $overlay : null;
        }

        $this->system->addError(HEURIST_INVALID_REQUEST, 'Registered IIIF resource is not a Manifest');
        return null;
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


    private function buildManagedCanvasItems(array $manifestDetails, int $manifestRecID, bool $omitAnnotationPages=false): array
    {
        $canvasRecIDs = $this->getDetailValues($manifestDetails, 'DT_IIIF_CANVAS');
        $items = array();

        foreach($canvasRecIDs as $canvasRecID){
            $canvas = $this->dbCanvas()->canvasJsonForCanvasRecord($canvasRecID, array(
                'manifest_rec_id' => $manifestRecID,
                'omit_annotation_pages' => $omitAnnotationPages
            ));
            if($canvas){
                $items[] = $canvas;
            }
        }

        return $items;
    }

    private function manifestApiUrl(int $manifestRecID): string
    {
        $baseUrl = rtrim(defined('HEURIST_BASE_URL_PRO') ? HEURIST_BASE_URL_PRO : HEURIST_BASE_URL, '/');
        return $baseUrl
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


    /** Return true if a recUploadedFiles row represents a registered IIIF Manifest JSON file. */
    public function isIiifManifestFile(array $fileinfo): bool
    {
        return ($fileinfo['ulf_PreferredSource'] ?? '') === 'iiif'
            || (defined('ULF_IIIF') && strpos((string)($fileinfo['ulf_OrigFileName'] ?? ''), ULF_IIIF) === 0);
    }

    /** Return the public Heurist IIIF Manifest API URL for a registered Manifest file. */
    public function manifestApiUrlForFile(string $fileObfuscatedID): string
    {
        return rtrim(HEURIST_BASE_URL_PRO, '/')
            .'/api/'.$this->system->dbname()
            .'/iiif/manifest/'.rawurlencode($fileObfuscatedID);
    }

    /** Return a v3 Manifest reference for a registered Manifest file, suitable for Collection.items. */
    public function manifestReferenceForFile(array $fileinfo, ?array $record=null): ?array
    {
        if(!$this->isIiifManifestFile($fileinfo) || empty($fileinfo['ulf_ObfuscatedFileID'])){
            return null;
        }

        $label = trim(strip_tags((string)($fileinfo['ulf_Description'] ?? '')));
        if($label==='' && $record!==null){
            $label = trim(strip_tags((string)($record['rec_Title'] ?? '')));
        }
        if($label===''){
            $label = trim(strip_tags((string)($fileinfo['ulf_OrigFileName'] ?? '')));
        }
        if($label===''){
            $label = 'IIIF Manifest';
        }

        return array(
            'id' => $this->manifestApiUrlForFile((string)$fileinfo['ulf_ObfuscatedFileID']),
            'type' => 'Manifest',
            'label' => $this->toIiifLanguageMap(array($label), 'IIIF Manifest')
        );
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

    private function loadSourceManifestForFile(array $fileinfo): ?array
    {
        $content = '';
        $sourceUrl = trim((string)($fileinfo['ulf_ExternalFileReference'] ?? ''));

        if($sourceUrl !== ''){
            $content = loadRemoteURLContent($sourceUrl);
        }

        if($content === ''){
            $path = '';
            if(!empty($fileinfo['ulf_FilePath']) && !empty($fileinfo['ulf_FileName'])){
                $path = $fileinfo['ulf_FilePath'].$fileinfo['ulf_FileName'];
                if(function_exists('resolveFilePath')){
                    $path = resolveFilePath($path);
                }
            }

            if($path && file_exists($path)){
                $content = file_get_contents($path);
            }
        }

        if($content === '' && !empty($fileinfo['ulf_ObfuscatedFileID'])){
            $sourceUrl = HEURIST_BASE_URL_PRO.'?db='.$this->system->dbname().'&file='.$fileinfo['ulf_ObfuscatedFileID'];
            $content = loadRemoteURLContent($sourceUrl);
        }

        if($content === ''){
            $this->system->addError(HEURIST_NOT_FOUND, 'Registered IIIF Manifest content could not be loaded');
            return null;
        }

        $json = json_decode($content, true);
        if(!is_array($json)){
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                'Registered IIIF Manifest is not valid JSON. '.json_last_error_msg());
            return null;
        }

        if(!IiifManifestJson::isManifest($json)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Registered IIIF resource is not a Manifest');
            return null;
        }

        return $json;
    }

    private function annotationPageUrlForCanvas(string $canvasId): string
    {
        $baseUrl = rtrim(defined('HEURIST_BASE_URL_PRO') ? HEURIST_BASE_URL_PRO : HEURIST_BASE_URL, '/');
        return $baseUrl
            .'/api/'.$this->system->dbname()
            .'/annotations/pages?uri='.rawurlencode($canvasId);
    }

}
