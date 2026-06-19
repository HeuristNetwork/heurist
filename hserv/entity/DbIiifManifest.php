<?php
/**
* DbIiifManifest.php - Record-type-backed entity for RT_IIIF_MANIFEST.
*/
namespace hserv\entity;

use hserv\structure\ConceptCode;

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
            'RT_IIIF_CANVAS',
            'DT_NAME',
            'DT_EXTENDED_DESCRIPTION',
            'DT_FILE_RESOURCE',
            'DT_COPYRIGHT',
            'DT_IIIF_ID',
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

        $copyright = $this->extractCopyrightText($manifest);
        if($copyright){
            $this->setField($details, 'DT_COPYRIGHT', $copyright);
        }

        $res = $this->saveRecordDetails($recordId, $details, 0);
        if(!is_array($res) || @$res['status']!=HEURIST_OK || intval(@$res['data'])<1){
            if(is_array($res) && @$res['message']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, $res['message']);
            }
            return 0;
        }
        return intval($res['data']);
    }

    private function extractCopyrightText(array $manifest): string
    {
        $rights = trim((string)($manifest['rights'] ?? ''));
        $required = $manifest['requiredStatement'] ?? null;

        if(is_array($required)){
            $value = $this->normaliseLangValue($required['value'] ?? null);
            if($value){
                return $value;
            }
        }

        return $rights;
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
        $manifest = $this->loadSourceManifestForRecord($manifestRecID, $manifestDetails);
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

    private function buildManagedManifestJson(int $manifestRecID, array $manifestDetails, bool $omitAnnotationPages=false): array
    {
        $title = $this->getFirstDetailValue($manifestDetails, 'DT_NAME') ?: ('Manifest '.$manifestRecID);

        $manifest = array(
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $this->manifestApiUrl($manifestRecID),
            'type' => 'Manifest',
            'label' => $this->toLanguageMap($title),
            'items' => $this->buildManagedCanvasItems($manifestDetails, $manifestRecID, $omitAnnotationPages)
        );

        $summary = $this->getFirstDetailValue($manifestDetails, 'DT_EXTENDED_DESCRIPTION');
        if(!$summary){
            $summary = $this->getFirstDetailValue($manifestDetails, 'DT_SHORT_SUMMARY');
        }
        if($summary){
            $manifest['summary'] = $this->toLanguageMap($summary);
        }

        $copyright = $this->getFirstDetailValue($manifestDetails, 'DT_COPYRIGHT');
        if($copyright){
            $manifest['requiredStatement'] = array(
                'label' => $this->toLanguageMap('Copyright'),
                'value' => $this->toLanguageMap($copyright)
            );

            if(preg_match('/^https?:\/\//i', trim((string)$copyright))){
                $manifest['rights'] = trim((string)$copyright);
            }
        }

        return $manifest;
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
        if($canvasRecID<1){
            return null;
        }

        $details = $this->loadRecordDetails($canvasRecID);
        if(empty($details)){
            return null;
        }

        $canvasUrl = $this->canvasApiUrl($canvasRecID);
        $canvas = array(
            'id' => $canvasUrl,
            'type' => 'Canvas',
            'label' => $this->toLanguageMap($this->getFirstDetailValue($details, 'DT_NAME') ?: ('Canvas '.$canvasRecID))
        );

        $summary = $this->getFirstDetailValue($details, 'DT_SHORT_SUMMARY');
        if($summary){
            $canvas['summary'] = $this->toLanguageMap($summary);
        }

        $width = $this->getNumericDetailValueByConstOrCode($details, 'DT_WIDTH', '3-1040');
        $height = $this->getNumericDetailValueByConstOrCode($details, 'DT_HEIGHT', '3-1041');
        $duration = $this->getNumericDetailValueByConstOrCode($details, 'DT_DURATION', '2-66');

        // In managed mode Canvas dimensions are authoritative database values.
        // They must be present on both the Canvas item and the painting body so
        // Mirador/OpenSeadragon can size the Canvas and the painted image consistently.
        if($height !== null && $height > 0){ $canvas['height'] = intval($height); }
        if($width !== null && $width > 0){ $canvas['width'] = intval($width); }
        if($duration !== null && $duration > 0){ $canvas['duration'] = floatval($duration); }

        $thumbID = intval($this->getFirstDetailValue($details, 'DT_THUMBNAIL'));
        $thumb = $this->fileBodyFromUlfID($thumbID);
        if($thumb){
            $canvas['thumbnail'] = array($thumb);
        }

        $mediaID = intval($this->getFirstDetailValue($details, 'DT_FILE_RESOURCE'));
        $body = $this->fileBodyFromUlfID($mediaID, $width, $height, $duration);
        if($body){
            $canvas['items'] = array(
                array(
                    'id' => $canvasUrl.'/painting-page',
                    'type' => 'AnnotationPage',
                    'items' => array(
                        array(
                            'id' => $canvasUrl.'/painting',
                            'type' => 'Annotation',
                            'motivation' => 'painting',
                            'body' => $body,
                            'target' => $canvasUrl
                        )
                    )
                )
            );
        }else{
            $canvas['items'] = array();
        }

        if(!$omitAnnotationPages){
            $canvas['annotations'] = array(
                array(
                    'id' => $this->annotationPageUrl($manifestRecID, $canvasUrl),
                    'type' => 'AnnotationPage'
                )
            );
        }

        return $canvas;
    }

    private function fileBodyFromUlfID(int $ulfID, $width=null, $height=null, $duration=null): ?array
    {
        if($ulfID<1){
            return null;
        }

        $mysqli = $this->system->getMysqli();
        $query = 'SELECT ulf_ObfuscatedFileID, ulf_ExternalFileReference, ulf_MimeExt, fxm_MimeType '
            .'FROM recUploadedFiles LEFT JOIN defFileExtToMimetype ON fxm_Extension=ulf_MimeExt '
            .'WHERE ulf_ID='.intval($ulfID).' LIMIT 1';
        $row = mysql__select_row($mysqli, $query);
        if(!is_array($row)){
            return null;
        }

        $url = $row[1] ? $row[1] : HEURIST_BASE_URL.'?db='.$this->system->dbname().'&file='.$row[0];
        $mimeType = $row[3] ?: $this->mimeTypeFromExtension((string)$row[2]);
        $type = $this->iiifBodyTypeFromMime($mimeType);

        $body = array(
            'id' => $url,
            'type' => $type,
        );
        if($mimeType){
            $body['format'] = $mimeType;
        }

        // IIIF painting bodies should repeat the media dimensions. For images this
        // is width/height; for AV resources duration may also be applicable.
        if($height !== null && $height > 0 && in_array($type, array('Image', 'Video'), true)){
            $body['height'] = intval($height);
        }
        if($width !== null && $width > 0 && in_array($type, array('Image', 'Video'), true)){
            $body['width'] = intval($width);
        }
        if($duration !== null && $duration > 0 && in_array($type, array('Video', 'Sound'), true)){
            $body['duration'] = floatval($duration);
        }

        return $body;
    }

    private function mimeTypeFromExtension(string $ext): ?string
    {
        $ext = strtolower(trim($ext));
        if($ext===''){
            return null;
        }
        if(strpos($ext, '/')!==false){
            return $ext;
        }
        switch($ext){
            case 'jpg':
            case 'jpeg': return 'image/jpeg';
            case 'png': return 'image/png';
            case 'gif': return 'image/gif';
            case 'webp': return 'image/webp';
            case 'tif':
            case 'tiff': return 'image/tiff';
            case 'mp4': return 'video/mp4';
            case 'mp3': return 'audio/mpeg';
            case 'wav': return 'audio/wav';
            default: return null;
        }
    }

    private function iiifBodyTypeFromMime(?string $mimeType): string
    {
        $mimeType = strtolower((string)$mimeType);
        if(strpos($mimeType, 'video/')===0){
            return 'Video';
        }
        if(strpos($mimeType, 'audio/')===0){
            return 'Sound';
        }
        return 'Image';
    }

    private function getFirstDetailValueByConstOrCode(array $details, string $constName, string $conceptCode)
    {
        $id = $this->constId($constName);
        if(!$id){
            $id = ConceptCode::getDetailTypeLocalID($conceptCode);
        }
        return $id ? ($details[intval($id)][0] ?? null) : null;
    }

    private function getNumericDetailValueByConstOrCode(array $details, string $constName, string $conceptCode): ?float
    {
        $value = $this->getFirstDetailValueByConstOrCode($details, $constName, $conceptCode);
        if($value === null || $value === ''){
            return null;
        }
        if(is_string($value)){
            $value = trim($value);
        }
        return is_numeric($value) ? floatval($value) : null;
    }

    private function toLanguageMap($value): array
    {
        $value = trim((string)$value);
        if($value===''){
            $value = 'Untitled';
        }

        if(preg_match('/^([A-Z]{3}):(.*)$/', $value, $m)){
            $lang = strtolower($m[1]);
            $map = array('eng'=>'en', 'fre'=>'fr', 'fra'=>'fr', 'deu'=>'de', 'ger'=>'de', 'spa'=>'es', 'ita'=>'it');
            $lang = $map[$lang] ?? substr($lang, 0, 2);
            $text = trim($m[2]);
            return array($lang => array($text!=='' ? $text : $value));
        }

        return array('none' => array($value));
    }

    private function manifestApiUrl(int $manifestRecID): string
    {
        return rtrim(HEURIST_BASE_URL, '/')
            .'/api/'.$this->system->dbname()
            .'/iiif/manifest/'.intval($manifestRecID);
    }

    private function canvasApiUrl(int $canvasRecID): string
    {
        return rtrim(HEURIST_BASE_URL, '/')
            .'/api/'.$this->system->dbname()
            .'/iiif/canvas/'.intval($canvasRecID);
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
            .'/annotations/'.intval($manifestRecID)
            .'/pages?uri='.rawurlencode($canvasId);
    }

}
