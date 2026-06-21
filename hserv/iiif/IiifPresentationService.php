<?php
/**
* IiifPresentationService.php - IIIF Presentation API resource dispatcher.
*
* Builds IIIF Presentation resources for /api/{db}/iiif/{resource}/{id}
* without coupling the controller to ExportRecordsIIIF.
*/
namespace hserv\iiif;

use hserv\entity\DbIiifCanvas;
use hserv\entity\DbIiifManifest;

require_once dirname(__FILE__).'/../entity/DbIiifCanvas.php';
require_once dirname(__FILE__).'/../entity/DbIiifManifest.php';
require_once dirname(__FILE__).'/IiifManifestJson.php';
require_once dirname(__FILE__).'/IiifMediaHelper.php';

/**
 * Coordinates IIIF Presentation resource output for registered files and
 * managed/overlay Manifest records.
 */
class IiifPresentationService
{
    /** @var \hserv\System */
    private $system;

    /** @var DbIiifCanvas|null */
    private $dbCanvas = null;

    /** @var DbIiifManifest|null */
    private $dbManifest = null;

    public function __construct($system)
    {
        $this->system = $system;
    }

    /**
     * Return a IIIF Presentation resource as an array, or false on error.
     *
     * @param string $resource manifest, canvas, page, annotation, annotations
     * @param string $id Registered file obfuscated ID, or Manifest record ID for resource=manifest
     * @param array $options Supported: omit_annotation_pages
     * @return array|false
     */
    public function getResource(string $resource, string $id, array $options=array())
    {
        $resource = strtolower(trim($resource));
        if($resource===''){
            $resource = 'manifest';
        }

        switch($resource){
            case 'manifest':
                return $this->manifestResource($id, $options);

            case 'canvas':
                return $this->canvasResource($id, $options);

            case 'page':
                return $this->paintingPageResource($id, $options);

            case 'annotation':
                return $this->paintingAnnotationResource($id, $options);

            case 'annotations':
            case 'annotationpage':
                return $this->linkedAnnotationPageResource($id);

            default:
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Unsupported IIIF resource: '.htmlspecialchars($resource));
                return false;
        }
    }

    /** Return a IIIF resource encoded as pretty JSON, or false on error. */
    public function getResourceJson(string $resource, string $id, array $options=array())
    {
        $res = $this->getResource($resource, $id, $options);
        return is_array($res)
            ? json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : false;
    }

    private function manifestResource(string $id, array $options=array())
    {
        $omitAnnotationPages = !empty($options['omit_annotation_pages']);

        // Numeric IDs are RT_IIIF_MANIFEST records.
        if(preg_match('/^[1-9][0-9]*$/', $id)){
            $manifestRecID = intval($id);
            if($this->dbManifest()->isIiifManifestRecord($manifestRecID)){
                $manifest = $this->dbManifest()->getOverlayManifestJson($manifestRecID, $omitAnnotationPages);
                return is_array($manifest) ? $manifest : false;
            }
        }

        $fileinfo = $this->fileInfoForId($id);
        if(!$fileinfo){
            return false;
        }

        if($this->isIiifManifestFile($fileinfo)){
            return $this->registeredManifestJson($fileinfo);
        }

        $canvas = $this->canvasFromFileInfo($fileinfo, array(
            'omit_annotation_pages' => $omitAnnotationPages
        ));
        if(!$canvas){
            return false;
        }

        $label = $this->labelForFile($fileinfo, 'Heurist IIIF manifest');
        return IiifManifestJson::composeManifest(
            $this->iiifApiRoot().'manifest/'.$id,
            array('none'=>array($label)),
            array($canvas)
        );
    }

    private function canvasResource(string $id, array $options=array())
    {
        $fileinfo = $this->fileInfoForId($id);
        if(!$fileinfo){
            return false;
        }
        return $this->canvasFromFileInfo($fileinfo, $options);
    }

    private function paintingPageResource(string $id, array $options=array())
    {
        $canvas = $this->canvasResource($id, $options);
        if(!is_array($canvas) || empty($canvas['items'][0]) || !is_array($canvas['items'][0])){
            $this->system->addError(HEURIST_ERROR, 'Unable to build IIIF painting AnnotationPage');
            return false;
        }
        return $canvas['items'][0];
    }

    private function paintingAnnotationResource(string $id, array $options=array())
    {
        $page = $this->paintingPageResource($id, $options);
        if(!is_array($page) || empty($page['items'][0]) || !is_array($page['items'][0])){
            $this->system->addError(HEURIST_ERROR, 'Unable to build IIIF painting Annotation');
            return false;
        }
        return $page['items'][0];
    }

    private function canvasFromFileInfo(array $fileinfo, array $options=array())
    {
        if(!$this->isSupportedMediaFile($fileinfo)){
            $this->system->addError(HEURIST_NOT_FOUND, 'Resource with given id is not a supported IIIF media resource');
            return false;
        }

        $includeAnnotationPages = false;
        if(empty($options['omit_annotation_pages'])){
            $includeAnnotationPages = $this->hasLinkedIiifAnnotations(intval($fileinfo['ulf_ID'] ?? 0));
        }

        return $this->dbCanvas()->canvasJsonForFileInfo($fileinfo, array(
            'include_annotation_pages' => $includeAnnotationPages,
            'omit_annotation_pages' => !empty($options['omit_annotation_pages']),
            'body_fullres' => true,
            'base_url' => HEURIST_BASE_URL_PRO
        ));
    }

    private function linkedAnnotationPageResource(string $id)
    {
        $fileinfo = $this->fileInfoForId($id);
        if(!$fileinfo){
            return false;
        }

        $fileid = $fileinfo['ulf_ObfuscatedFileID'] ?? $id;
        $ulfID = intval($fileinfo['ulf_ID'] ?? 0);
        $pageUri = $this->iiifApiRoot().'annotations/'.$fileid;
        $fallbackCanvasUri = $this->iiifApiRoot().'canvas/'.$fileid;

        $items = array();
        $rtyIDs = $this->annotationRecordTypeIds();
        if(!empty($rtyIDs) && $ulfID>0){
            $mysqli = $this->system->getMysqli();

            $targetJoin = '';
            $targetSelect = 'NULL AS target_uri';
            if($this->system->defineConstant('DT_URL')){
                $targetJoin = ' LEFT JOIN recDetails target ON target.dtl_RecID=anno.rec_ID AND target.dtl_DetailTypeID='.DT_URL;
                $targetSelect = 'target.dtl_Value AS target_uri';
            }

            $infoJoin = '';
            $infoSelect = 'NULL AS annotation_info';
            if($this->system->defineConstant('DT_ANNOTATION_INFO')){
                $infoJoin = ' LEFT JOIN recDetails ainfo ON ainfo.dtl_RecID=anno.rec_ID AND ainfo.dtl_DetailTypeID='.DT_ANNOTATION_INFO;
                $infoSelect = 'ainfo.dtl_Value AS annotation_info';
            }

            $bodyJoin = '';
            $bodySelect = 'NULL AS body_value';
            $bodyDtyIDs = $this->annotationBodyDetailTypeIds();
            if(!empty($bodyDtyIDs)){
                $bodyJoin = ' LEFT JOIN recDetails body ON body.dtl_RecID=anno.rec_ID AND body.dtl_DetailTypeID IN ('.implode(',', $bodyDtyIDs).')';
                $bodySelect = 'MIN(body.dtl_Value) AS body_value';
            }

            $query = 'SELECT anno.rec_ID, anno.rec_Title, '.$targetSelect.', '.$infoSelect.', '.$bodySelect
                .' FROM recDetails media '
                .' JOIN recLinks ON rl_TargetID=media.dtl_RecID '
                .' JOIN Records anno ON anno.rec_ID=rl_SourceID '
                .$targetJoin
                .$infoJoin
                .$bodyJoin
                .' WHERE media.dtl_UploadedFileID='.intval($ulfID)
                .' AND anno.rec_RecTypeID IN ('.implode(',', $rtyIDs).') '
                .' GROUP BY anno.rec_ID, anno.rec_Title, target_uri, annotation_info '
                .' ORDER BY anno.rec_ID';

            $res = $mysqli->query($query);
            if($res){
                while($row = $res->fetch_assoc()){
                    $items[] = $this->annotationFromLinkedRow($row, $pageUri, $fallbackCanvasUri);
                }
            }
        }

        return array(
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $pageUri,
            'type' => 'AnnotationPage',
            'items' => $items
        );
    }

    private function annotationFromLinkedRow(array $row, string $pageUri, string $fallbackCanvasUri): array
    {
        $annotationInfo = trim((string)($row['annotation_info'] ?? ''));
        if($annotationInfo!==''){
            $annotation = json_decode($annotationInfo, true);
            if(json_last_error()===JSON_ERROR_NONE && is_array($annotation)){
                if(empty($annotation['id'])){
                    $annotation['id'] = $pageUri.'/annotation/'.$row['rec_ID'];
                }
                if(empty($annotation['type'])){
                    $annotation['type'] = 'Annotation';
                }
                if(empty($annotation['target'])){
                    $annotation['target'] = $fallbackCanvasUri;
                }
                return $annotation;
            }
        }

        $target = trim((string)($row['target_uri'] ?? ''));
        if($target===''){
            $target = $fallbackCanvasUri;
        }

        $body = trim(strip_tags((string)($row['body_value'] ?? '')));
        if($body===''){
            $body = trim(strip_tags((string)($row['rec_Title'] ?? '')));
        }
        if($body===''){
            $body = 'Annotation '.$row['rec_ID'];
        }

        return array(
            'id' => $pageUri.'/annotation/'.$row['rec_ID'],
            'type' => 'Annotation',
            'motivation' => 'commenting',
            'body' => array(
                'type' => 'TextualBody',
                'value' => $body,
                'format' => 'text/plain'
            ),
            'target' => $target
        );
    }

    private function registeredManifestJson(array $fileinfo)
    {
        $manifest = '';
        if(!empty($fileinfo['ulf_ExternalFileReference'])){
            $manifest = loadRemoteURLContent($fileinfo['ulf_ExternalFileReference']);
        }else{
            $path = '';
            if(!empty($fileinfo['ulf_FilePath']) && !empty($fileinfo['ulf_FileName'])){
                $path = $fileinfo['ulf_FilePath'].$fileinfo['ulf_FileName'];
                if(function_exists('resolveFilePath')){
                    $path = resolveFilePath($path);
                }
            }
            if($path && file_exists($path)){
                $manifest = file_get_contents($path);
            }
            if($manifest===''){
                $manifest = loadRemoteURLContent(HEURIST_BASE_URL_PRO.'?db='.$this->system->dbname().'&file='.$fileinfo['ulf_ObfuscatedFileID']);
            }
        }

        if(!$manifest){
            $this->system->addError(HEURIST_NOT_FOUND, 'Registered IIIF manifest content could not be loaded');
            return false;
        }

        $json = json_decode($manifest, true);
        if(json_last_error()!==JSON_ERROR_NONE || !is_array($json)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Registered IIIF manifest is not valid JSON');
            return false;
        }

        if(!IiifManifestJson::isManifest($json)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Registered IIIF resource is not a Manifest');
            return false;
        }

        return $json;
    }

    private function fileInfoForId(string $id)
    {
        $info = fileGetFullInfo($this->system, $id);
        if(empty($info) || !is_array($info[0])){
            $this->system->addError(HEURIST_NOT_FOUND, 'Resource with given id not found');
            return false;
        }
        return $info[0];
    }

    private function isIiifManifestFile(array $fileinfo): bool
    {
        return ($fileinfo['ulf_PreferredSource'] ?? '') === 'iiif'
            || (defined('ULF_IIIF') && strpos((string)($fileinfo['ulf_OrigFileName'] ?? ''), ULF_IIIF) === 0);
    }

    private function isSupportedMediaFile(array $fileinfo): bool
    {
        $mimeType = strtolower((string)($fileinfo['fxm_MimeType'] ?? ''));
        if(strpos($mimeType, 'video/')===0){
            return strpos($mimeType, 'youtube')===false && strpos($mimeType, 'vimeo')===false;
        }
        if(strpos($mimeType, 'audio/')===0){
            return strpos($mimeType, 'soundcloud')===false;
        }
        return strpos($mimeType, 'image/')===0 || IiifMediaHelper::isIiifImageInfoFile($fileinfo);
    }

    private function hasLinkedIiifAnnotations(int $ulfID): bool
    {
        $rtyIDs = $this->annotationRecordTypeIds();
        if(empty($rtyIDs) || $ulfID<1){
            return false;
        }

        $mysqli = $this->system->getMysqli();
        $query = 'SELECT 1 '
            .' FROM recDetails media, recLinks, Records anno '
            .' WHERE media.dtl_UploadedFileID='.intval($ulfID)
            .' AND rl_TargetID=media.dtl_RecID '
            .' AND anno.rec_ID=rl_SourceID '
            .' AND anno.rec_RecTypeID IN ('.implode(',', $rtyIDs).') '
            .' LIMIT 1';

        return mysql__select_value($mysqli, $query) ? true : false;
    }

    private function annotationRecordTypeIds(): array
    {
        $ids = array();
        if($this->system->defineConstant('RT_IIIF_ANNOTATION')){
            $ids[] = RT_IIIF_ANNOTATION;
        }
        if($this->system->defineConstant('RT_ANNOTATION') && !in_array(RT_ANNOTATION, $ids)){
            $ids[] = RT_ANNOTATION;
        }
        return $ids;
    }

    private function annotationBodyDetailTypeIds(): array
    {
        $ids = array();
        foreach(array('DT_SHORT_SUMMARY', 'DT_EXTENDED_DESCRIPTION', 'DT_DESCRIPTION', 'DT_NOTES') as $constantName){
            if($this->system->defineConstant($constantName)){
                $value = constant($constantName);
                if($value>0 && !in_array($value, $ids)){
                    $ids[] = $value;
                }
            }
        }
        return $ids;
    }

    private function labelForFile(array $fileinfo, string $fallback): string
    {
        $label = trim(strip_tags((string)($fileinfo['ulf_Description'] ?? '')));
        if($label===''){
            $label = trim(strip_tags((string)($fileinfo['ulf_OrigFileName'] ?? '')));
        }
        return $label!=='' ? $label : $fallback;
    }

    private function iiifApiRoot(): string
    {
        return HEURIST_BASE_URL_PRO.'api/'.$this->system->dbname().'/iiif/';
    }

    private function dbCanvas(): DbIiifCanvas
    {
        if($this->dbCanvas===null){
            $this->dbCanvas = new DbIiifCanvas($this->system);
        }
        return $this->dbCanvas;
    }

    private function dbManifest(): DbIiifManifest
    {
        if($this->dbManifest===null){
            $this->dbManifest = new DbIiifManifest($this->system);
        }
        return $this->dbManifest;
    }
}
?>
