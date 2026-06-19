<?php
/**
* DbIiifManifest.php - Record-type-backed entity for RT_IIIF_MANIFEST.
*/
namespace hserv\entity;

require_once dirname(__FILE__).'/DbRecordTypeEntity.php';

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
            'DT_NAME',
            'DT_EXTENDED_DESCRIPTION',
            'DT_FILE_RESOURCE',
            'DT_IIIF_ID',
            'DT_IIIF_IMPORT_MODE'
        );

        // Fill these when the term concept codes are final.
        $this->requiredTermConstants = array(
            'TRM_IIIF_IMPORT_MODE_OVERLAY' => '2-10444',
            'TRM_IIIF_IMPORT_MODE_PRESERVE_CANVASES' => '2-10446',
            'TRM_IIIF_IMPORT_MODE_MANAGED' => '2-10445'
        );
    }

    /**
     * Create or update a minimal RT_IIIF_MANIFEST record for a registered Manifest file.
     * $manifestFile is the resolved file array from ImportAnnotations.
     */
    public function ensureFromManifestFile(array $manifestFile, array $manifest, string $importMode='overlay')
    {
        if(!$this->ensureDefinitionsReady($this->system->isAdmin())){
            return 0;
        }

        $manifestId = $this->getJsonId($manifest);
        $recordId = $this->findManifestRecord($manifestFile, $manifestId);

        $details = array();
        $title = $this->normaliseLangValue(@$manifest['label']);
        if(!$title){
            $title = basename((string)@$manifestFile['source_url']);
        }

        $this->setField($details, 'DT_NAME', $title);
        $this->setField($details, 'DT_FILE_RESOURCE', intval(@$manifestFile['ulf_ID']));

        $this->setField($details, 'DT_IIIF_ID', $manifestId);

        $modeValue = $this->resolveImportModeTerm($importMode);
        if($modeValue){
            $this->setField($details, 'DT_IIIF_IMPORT_MODE', $modeValue);
        }

        $desc = $this->normaliseLangValue(@$manifest['summary']);
        if(!$desc){
            $desc = $this->normaliseLangValue(@$manifest['description']);
        }
        $this->setField($details, 'DT_EXTENDED_DESCRIPTION', $desc);

        $res = $this->saveRecordDetails($recordId, $details, 0);
        if(!is_array($res) || @$res['status']!=HEURIST_OK || intval(@$res['data'])<1){
            if(is_array($res) && @$res['message']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, $res['message']);
            }
            return 0;
        }
        return intval($res['data']);
    }

    private function resolveImportModeTerm(string $importMode): ?int
    {
        switch($importMode){
            case 'overlay':
                return $this->getTermId('TRM_IIIF_IMPORT_MODE_OVERLAY') ?: $this->getTermId('overlay');
            case 'preserve_canvases':
                return $this->getTermId('TRM_IIIF_IMPORT_MODE_PRESERVE_CANVASES') ?: $this->getTermId('preserve_canvases');
            case 'managed':
                return $this->getTermId('TRM_IIIF_IMPORT_MODE_MANAGED') ?: $this->getTermId('managed');
            default:
                return $this->getTermId($importMode);
        }
    }

    private function findManifestRecord(array $manifestFile, ?string $manifestId): int
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

        if(defined('DT_IIIF_ID') && $manifestId){
            $conditions[] = '(d.dtl_DetailTypeID='.DT_IIIF_ID.' AND d.dtl_Value="'.addslashes($manifestId).'")';
        }

        if(empty($conditions)){
            return 0;
        }

        $query = 'SELECT r.rec_ID FROM Records r, recDetails d WHERE r.rec_ID=d.dtl_RecID '
            .'AND r.rec_RecTypeID='.$rty.' AND ('.implode(' OR ', $conditions).') LIMIT 1';
        $recID = mysql__select_value($mysqli, $query);
        return $recID ? intval($recID) : 0;
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

        $manifest = $this->loadSourceManifestForRecord($manifestRecID);
        if(!is_array($manifest)){
            return null;
        }

        // Phase 2 supports v3 overlay output only.
        if(@$manifest['type']!='Manifest' || !is_array(@$manifest['items'])){
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                'Only IIIF Presentation API v3 Manifest overlay output is supported');
            return null;
        }

        foreach($manifest['items'] as $idx=>$canvas){
            if(!is_array($canvas) || @$canvas['type']!='Canvas'){
                continue;
            }

            $canvasId = @$canvas['id'];
            if(!$canvasId){
                continue;
            }

            // Always remove source annotation pages to avoid duplicate imported/source annotations.
            unset($manifest['items'][$idx]['annotations']);

            // External IIIF consumers need standard Canvas.annotations[] links.
            // The internal Heurist Mirador viewer uses window.endpointURL instead,
            // so it requests omit_annotation_pages=1 to avoid loading the same DB
            // annotations twice.
            if(!$omitAnnotationPages){
                $manifest['items'][$idx]['annotations'] = array(
                    array(
                        'id' => $this->annotationPageUrl($manifestRecID, $canvasId),
                        'type' => 'AnnotationPage'
                    )
                );
            }
        }

        return $manifest;
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

    private function loadSourceManifestForRecord(int $manifestRecID): ?array
    {
        $details = $this->loadRecordDetails($manifestRecID);

        $sourceUrl = null;
        $ulfID = intval($this->getFirstDetailValue($details, 'DT_FILE_RESOURCE'));
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
            .'/annotations/'.intval($manifestRecID)
            .'/pages?uri='.rawurlencode($canvasId);
    }

}
