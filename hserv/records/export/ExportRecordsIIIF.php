<?php
/**
* ExportRecordsIIIF.php - Class ExportRecordsIIIF.php 
*
* Extends `ExportRecords` for exporting records as IIIF manifest.
*
* @project     Heurist academic knowledge management system
* @package Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
namespace hserv\records\export;
use hserv\records\export\ExportRecords;
use hserv\entity\DbIiifCanvas;
use hserv\entity\DbIiifManifest;
use hserv\iiif\IiifMediaHelper;
use hserv\iiif\IiifPresentationService;

require_once dirname(__FILE__).'/../../entity/DbIiifCanvas.php';
require_once dirname(__FILE__).'/../../iiif/IiifMediaHelper.php';
require_once dirname(__FILE__).'/../../iiif/IiifPresentationService.php';

/**
* Class ExportRecordsIIIF
*
* Extends ExportRecords to provide functionality for exporting records
* in IIIF (International Image Interoperability Framework) Presentation API format.
* This class is typically controlled by the 'records_output' controller.
* It can generate manifests for IIIF Presentation API v2 and v3.
*
*/
class ExportRecordsIIIF extends ExportRecords {

    /**
     * @var int The IIIF Presentation API version to use (2 or 3). Default is 3.
     */
    private $iiif_version = 3;

    /**
     * @var string|null The obfuscated ID of a specific uploaded file to export.
     *                  If set, only this file will be processed into the manifest.
     */
    private $ulf_ObfuscatedFileID = null;

    /**
     * @var int Counter for the number of records (canvases) outputted.
     *          Used to limit the number of items per manifest.
     */
    private $cnt = 0;

    /** @var array Buffered IIIF v3 Canvas JSON strings for generated media manifests. */
    private $v3_canvas_items = array();

    /** @var array Buffered IIIF v3 Manifest reference JSON strings for registered manifests in the recordset. */
    private $v3_manifest_items = array();

    /** @var string|null Current output URI, reused by v3 Manifest/Collection wrappers. */
    private $manifest_uri = null;

    /** @var array Obfuscated file IDs for registered IIIF manifests found in v3 recordset export. */
    private $v3_manifest_fileids = array();

    /**
     * Prepares for the export operation.
     *
     * Sets the IIIF API version based on input parameters and calls the parent's
     * prepare method.
     *
     * @param array $data The data to be exported (typically records).
     * @param array $params Parameters for the export, may include 'version' or 'v' to specify IIIF API version.
     * @return bool True if preparation was successful, false otherwise.
     */
protected function _outputPrepare($data, $params){

    $params['depth'] = 0;

    $res = parent::_outputPrepare($data, $params);
    if($res){
        $this->iiif_version = (@$params['version']==2 || @$params['v']==2)?2:3;
    }
    
    return $res;
}

//
//
//
    /**
     * Prepares the fields required for the IIIF export.
     *
     * Specifies that 'file' details are needed and sets standard header fields.
     * It also initializes the specific IIIF image to be used from parameters, if provided.
     *
     * @param array $params Parameters for the export, may include 'iiif_image' for a specific file.
     */
protected function _outputPrepareFields($params){

    $this->retrieve_detail_fields = array('file');
    $this->retrieve_header_fields = 'rec_ID,rec_RecTypeID,rec_Title';

    $this->ulf_ObfuscatedFileID = @$params['iiif_image'];
}


//
//
//
    /**
     * Outputs the header of the IIIF manifest.
     *
     * Writes the initial JSON structure for the manifest, which differs
     * depending on whether IIIF Presentation API v2 or v3 is being used.
     * Initializes a counter for the number of canvases.
     */
protected function _outputHeader(){

    if($this->iiif_version==2){

        $manifest_uri = self::genUUID();
        $sequence_uri = self::genUUID();

    $iiif_header = <<<IIIF
{
    "@context": "http://iiif.io/api/presentation/2/context.json",
    "@id": "http://$manifest_uri",
    "@type": "sc:Manifest",
    "label": "Heurist IIIF manifest",
    "metadata": [],
    "description": [
        {
            "@value": "[Click to edit description]",
            "@language": "en"
        }
    ],
    "license": "https://creativecommons.org/licenses/by/3.0/",
    "attribution": "[Click to edit attribution]",
    "sequences": [
        {
            "@id": "http://$sequence_uri",
            "@type": "sc:Sequence",
            "label": [
                {
                    "@value": "Normal Sequence",
                    "@language": "en"
                }
            ],
            "canvases": [
IIIF;
    }else{
        // VERSION 3 is buffered until footer so we can decide whether the top-level
        // resource must be a Manifest or a Collection when registered manifests are present.
        $this->manifest_uri = HEURIST_SERVER_URL.$_SERVER["REQUEST_URI"];
        $iiif_header = '';
    }

    if($iiif_header !== ''){
        fwrite($this->fd, $iiif_header);
    }

    $this->cnt = 0;
    $this->v3_canvas_items = array();
    $this->v3_manifest_items = array();
    $this->v3_manifest_fileids = array();
}

//
//
//
    /**
     * Outputs a single record as an IIIF canvas.
     *
     * Converts the given Heurist record into an IIIF canvas object using `getIiifResource`.
     * Writes the canvas JSON to the output stream.
     * Limits the total number of canvases to 1000 per manifest, unless a specific
     * file ID was provided (in which case, only that file is processed).
     *
     * @param array $record The Heurist record to process.
     * @return bool True to continue processing, false to stop (e.g., if limit is reached).
     */
protected function _outputRecord($record){

    if($this->iiif_version==3){
        $manifestRefs = self::getIiifManifestReferences($this->system, $record, $this->ulf_ObfuscatedFileID);
        foreach($manifestRefs as $manifestRef){
            $this->v3_manifest_items[] = $manifestRef;
            $this->cnt++;
        }
        $this->v3_manifest_fileids = array_merge(
            $this->v3_manifest_fileids,
            self::getIiifManifestFileIds($this->system, $record, $this->ulf_ObfuscatedFileID)
        );

        $canvas = self::getIiifResource($this->system, $record, $this->iiif_version, $this->ulf_ObfuscatedFileID);
        if($canvas && $canvas!=''){
            $this->v3_canvas_items[] = $canvas;
            $this->cnt++;
        }
    }else{
        $canvas = self::getIiifResource($this->system, $record, $this->iiif_version, $this->ulf_ObfuscatedFileID);
        if($canvas && $canvas!=''){
            fwrite($this->fd, $this->comma.$canvas);
            $this->comma = ",
";
            $this->cnt++;
        }
    }
    //not more than 1000 records per manifest
    //or the only image if it is specified
    $ret = (!($this->cnt>1000 || $this->ulf_ObfuscatedFileID!=null));
    return $ret;

}

//
//
//
    /**
     * Outputs the footer of the IIIF manifest.
     *
     * Writes the closing JSON structure for the manifest, which differs
     * depending on whether IIIF Presentation API v2 or v3 is being used.
     */
protected function _outputFooter(){

    if($this->iiif_version==2){
        fwrite($this->fd, ']}],"structures": []}');
    }else{
        $label = array('en' => array('Heurist IIIF manifest'));

        if(count($this->v3_manifest_items) === 1 && empty($this->v3_canvas_items) && count($this->v3_manifest_fileids) === 1){
            $manifest = self::getIiifManifestForFile($this->system, $this->v3_manifest_fileids[0]);
            if($manifest){
                fwrite($this->fd, $manifest);
                return;
            }
        }

        if(!empty($this->v3_manifest_items)){
            $items = $this->v3_manifest_items;

            if(!empty($this->v3_canvas_items)){
                $generatedManifest = array(
                    'id' => $this->manifest_uri.'#generated-media-manifest',
                    'type' => 'Manifest',
                    'label' => $label,
                    'items' => array_map(function($json){ return json_decode($json, true); }, $this->v3_canvas_items)
                );
                $items[] = json_encode($generatedManifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            $collection = array(
                '@context' => 'http://iiif.io/api/presentation/3/context.json',
                'id' => $this->manifest_uri,
                'type' => 'Collection',
                'label' => array('en' => array('Heurist IIIF collection')),
                'items' => array_map(function($json){ return json_decode($json, true); }, $items)
            );
            fwrite($this->fd, json_encode($collection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }else{
            $manifest = array(
                '@context' => 'http://iiif.io/api/presentation/3/context.json',
                'id' => $this->manifest_uri,
                'type' => 'Manifest',
                'label' => $label,
                'items' => array_map(function($json){ return json_decode($json, true); }, $this->v3_canvas_items)
            );
            fwrite($this->fd, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }
}

//
// Converts heurist record to iiif canvas json
// It allows to see any media in mirador viewer
//
// return null if not media content found
//
    /**
     * Converts a Heurist record or a specific file into an IIIF resource representation.
     *
     * This method generates the JSON structure for an IIIF resource, which can be
     * a Canvas, AnnotationPage, or Annotation, based on the `$type_resource` parameter.
     * It handles different media types (image, video, audio) and can process
     * files linked to a record or a single file specified by its obfuscated ID.
     * It also supports images served via an IIIF Image API.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array|null $record The Heurist record array. If null, `$ulf_ObfuscatedFileID` must be provided.
     * @param int $iiif_version The IIIF Presentation API version (2 or 3).
     * @param string|null $ulf_ObfuscatedFileID The obfuscated ID of a specific uploaded file.
     *                                          Used if `$record` is null or to pinpoint a specific file within a record.
     * @param string $type_resource The type of IIIF resource to generate ('Canvas', 'AnnotationPage', 'Annotation').
     *                              Default is 'Canvas'.
     * @return string|false The JSON string for the IIIF resource, or false on error or if no suitable media content is found.
     */

private static function isIiifManifestFile(array $fileinfo): bool
{
    return ($fileinfo['ulf_PreferredSource'] ?? '') === 'iiif'
        || (defined('ULF_IIIF') && strpos((string)($fileinfo['ulf_OrigFileName'] ?? ''), ULF_IIIF) === 0);
}

private static function getIiifManifestReferences($system, $record, $ulf_ObfuscatedFileID=null): array
{
    $refs = array();
    if($record==null){
        $info = fileGetFullInfo($system, $ulf_ObfuscatedFileID);
    }else{
        $info = array();
        foreach ($record['details'] as $dty_ID=>$field_details) {
            foreach($field_details as $dtl_ID=>$file){
                if(!@$file['file']){ continue; }
                if($ulf_ObfuscatedFileID && $file['file']['ulf_ObfuscatedFileID']!=$ulf_ObfuscatedFileID){
                    continue;
                }
                $info[] = $file['file'];
            }
        }
    }

    foreach($info as $fileinfo){
        if(!self::isIiifManifestFile($fileinfo)){
            continue;
        }
        $fileid = $fileinfo['ulf_ObfuscatedFileID'];
        $id = HEURIST_BASE_URL_PRO.'api/'.$system->dbname().'/iiif/manifest/'.$fileid;
        $label = trim(strip_tags($fileinfo['ulf_Description'] ?? ''));
        if($label=='' && $record!=null){
            $label = trim(strip_tags($record['rec_Title'] ?? ''));
        }
        if($label==''){
            $label = $fileinfo['ulf_OrigFileName'] ?? 'IIIF manifest';
        }
        $refs[] = json_encode(array(
            'id' => $id,
            'type' => 'Manifest',
            'label' => array('en' => array($label))
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    return $refs;
}



private static function getIiifApiRoot($system): string
{
    return HEURIST_BASE_URL_PRO.'api/'.$system->dbname().'/iiif/';
}

private static function getAnnotationRectypeIds($system): array
{
    $rty_ids = array();

    if($system->defineConstant('RT_IIIF_ANNOTATION')){
        $rty_ids[] = RT_IIIF_ANNOTATION;
    }
    if($system->defineConstant('RT_ANNOTATION') && !in_array(RT_ANNOTATION, $rty_ids)){
        $rty_ids[] = RT_ANNOTATION;
    }

    return $rty_ids;
}

private static function getAnnotationBodyDetailTypeIds($system): array
{
    $dty_ids = array();

    foreach(array('DT_SHORT_SUMMARY', 'DT_EXTENDED_DESCRIPTION', 'DT_DESCRIPTION', 'DT_NOTES') as $constant_name){
        if($system->defineConstant($constant_name)){
            $constant_value = constant($constant_name);
            if($constant_value > 0 && !in_array($constant_value, $dty_ids)){
                $dty_ids[] = $constant_value;
            }
        }
    }

    return $dty_ids;
}

private static function hasLinkedIiifAnnotations($system, int $ulf_ID): bool
{
    $rty_ids = self::getAnnotationRectypeIds($system);
    if(empty($rty_ids) || $ulf_ID < 1){
        return false;
    }

    $mysqli = $system->getMysqli();
    $query = 'SELECT 1 '
        .' FROM recDetails media, recLinks, Records anno '
        .' WHERE media.dtl_UploadedFileID='.intval($ulf_ID)
        .' AND rl_TargetID=media.dtl_RecID '
        .' AND anno.rec_ID=rl_SourceID '
        .' AND anno.rec_RecTypeID IN ('.implode(',', $rty_ids).') '
        .' LIMIT 1';

    return mysql__select_value($mysqli, $query) ? true : false;
}

private static function getLinkedIiifAnnotationPage($system, string $ulf_ObfuscatedFileID)
{
    $info = fileGetFullInfo($system, $ulf_ObfuscatedFileID);
    if(empty($info)){
        $system->addError(HEURIST_NOT_FOUND, 'Resource with given id not found');
        return false;
    }

    $fileinfo = $info[0];
    $ulf_ID = intval($fileinfo['ulf_ID'] ?? 0);
    $fileid = $fileinfo['ulf_ObfuscatedFileID'] ?? $ulf_ObfuscatedFileID;
    $root_uri = self::getIiifApiRoot($system);
    $page_uri = $root_uri.'annotations/'.$fileid;
    $fallback_canvas_uri = $root_uri.'canvas/'.$fileid;

    $items = array();
    $rty_ids = self::getAnnotationRectypeIds($system);

    if(!empty($rty_ids) && $ulf_ID > 0){
        $mysqli = $system->getMysqli();

        $target_join = '';
        $target_select = 'NULL AS target_uri';
        if($system->defineConstant('DT_URL')){
            $target_join = ' LEFT JOIN recDetails target ON target.dtl_RecID=anno.rec_ID AND target.dtl_DetailTypeID='.DT_URL;
            $target_select = 'target.dtl_Value AS target_uri';
        }

        $info_join = '';
        $info_select = 'NULL AS annotation_info';
        if($system->defineConstant('DT_ANNOTATION_INFO')){
            $info_join = ' LEFT JOIN recDetails ainfo ON ainfo.dtl_RecID=anno.rec_ID AND ainfo.dtl_DetailTypeID='.DT_ANNOTATION_INFO;
            $info_select = 'ainfo.dtl_Value AS annotation_info';
        }

        $body_join = '';
        $body_select = 'NULL AS body_value';
        $body_dty_ids = self::getAnnotationBodyDetailTypeIds($system);
        if(!empty($body_dty_ids)){
            $body_join = ' LEFT JOIN recDetails body ON body.dtl_RecID=anno.rec_ID AND body.dtl_DetailTypeID IN ('.implode(',', $body_dty_ids).')';
            $body_select = 'MIN(body.dtl_Value) AS body_value';
        }

        $query = 'SELECT anno.rec_ID, anno.rec_Title, '.$target_select.', '.$info_select.', '.$body_select
            .' FROM recDetails media '
            .' JOIN recLinks ON rl_TargetID=media.dtl_RecID '
            .' JOIN Records anno ON anno.rec_ID=rl_SourceID '
            .$target_join
            .$info_join
            .$body_join
            .' WHERE media.dtl_UploadedFileID='.intval($ulf_ID)
            .' AND anno.rec_RecTypeID IN ('.implode(',', $rty_ids).') '
            .' GROUP BY anno.rec_ID, anno.rec_Title, target_uri, annotation_info '
            .' ORDER BY anno.rec_ID';

        $res = $mysqli->query($query);
        if($res){
            while($row = $res->fetch_assoc()){
                $annotation = null;
                $annotation_info = trim((string)($row['annotation_info'] ?? ''));
                if($annotation_info !== ''){
                    $annotation = json_decode($annotation_info, true);
                    if(json_last_error() !== JSON_ERROR_NONE || !is_array($annotation)){
                        $annotation = null;
                    }
                }

                if(is_array($annotation)){
                    if(empty($annotation['id'])){
                        $annotation['id'] = $page_uri.'/annotation/'.$row['rec_ID'];
                    }
                    if(empty($annotation['type'])){
                        $annotation['type'] = 'Annotation';
                    }
                    if(empty($annotation['target'])){
                        $annotation['target'] = $fallback_canvas_uri;
                    }
                    $items[] = $annotation;
                    continue;
                }

                $target = trim((string)($row['target_uri'] ?? ''));
                if($target === ''){
                    $target = $fallback_canvas_uri;
                }

                $body = trim(strip_tags((string)($row['body_value'] ?? '')));
                if($body === ''){
                    $body = trim(strip_tags((string)($row['rec_Title'] ?? '')));
                }
                if($body === ''){
                    $body = 'Annotation '.$row['rec_ID'];
                }

                $items[] = array(
                    'id' => $page_uri.'/annotation/'.$row['rec_ID'],
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
        }
    }

    return json_encode(array(
        '@context' => 'http://iiif.io/api/presentation/3/context.json',
        'id' => $page_uri,
        'type' => 'AnnotationPage',
        'items' => $items
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

private static function getIiifManifestFileIds($system, $record, $ulf_ObfuscatedFileID=null): array
{
    $ids = array();
    if($record==null){
        $info = fileGetFullInfo($system, $ulf_ObfuscatedFileID);
    }else{
        $info = array();
        foreach ($record['details'] as $dty_ID=>$field_details) {
            foreach($field_details as $dtl_ID=>$file){
                if(!@$file['file']){ continue; }
                if($ulf_ObfuscatedFileID && $file['file']['ulf_ObfuscatedFileID']!=$ulf_ObfuscatedFileID){
                    continue;
                }
                $info[] = $file['file'];
            }
        }
    }

    foreach($info as $fileinfo){
        if(self::isIiifManifestFile($fileinfo) && !empty($fileinfo['ulf_ObfuscatedFileID'])){
            $ids[] = $fileinfo['ulf_ObfuscatedFileID'];
        }
    }
    return array_values(array_unique($ids));
}

/**
 * Compatibility wrapper for /api/{db}/iiif/{resource}/{id}.
 *
 * The implementation now lives in IiifPresentationService so this exporter can
 * be simplified to dynamic recordset export only in the following patches.
 *
 * @param \hserv\System $system Initialised Heurist system object
 * @param string $resource Resource name: manifest, canvas, page, annotation, annotations
 * @param string $ulf_ObfuscatedFileID Registered file obfuscated ID, or Manifest record ID for resource=manifest
 * @return string|false JSON string or false on error
 */
public static function getIiifApiResource($system, string $resource, string $ulf_ObfuscatedFileID, bool $omitAnnotationPages=false)
{
    $service = new IiifPresentationService($system);
    return $service->getResourceJson($resource, $ulf_ObfuscatedFileID, array(
        'omit_annotation_pages' => $omitAnnotationPages
    ));
}

/**
 * Returns an actual IIIF Manifest for a registered Heurist file.
 * Existing IIIF manifests are returned as-is; ordinary media and IIIF image info
 * files are wrapped into a single-canvas IIIF Presentation 3 Manifest.
 *
 * @param \hserv\System $system Initialised Heurist system object
 * @param string $ulf_ObfuscatedFileID Registered file obfuscated ID
 * @return string|false JSON string or false on error
 */
public static function getIiifManifestForFile($system, string $ulf_ObfuscatedFileID)
{
    $info = fileGetFullInfo($system, $ulf_ObfuscatedFileID);
    if(empty($info)){
        $system->addError(HEURIST_NOT_FOUND, 'Resource with given id not found');
        return false;
    }

    $fileinfo = $info[0];
    if(self::isIiifManifestFile($fileinfo)){
        return self::getRegisteredManifestJson($system, $fileinfo);
    }

    $canvas = self::getIiifResource($system, null, 3, $ulf_ObfuscatedFileID, 'canvas');
    if(!$canvas){
        return false;
    }

    $canvasObj = json_decode($canvas, true);
    if(json_last_error() !== JSON_ERROR_NONE || !is_array($canvasObj)){
        $system->addError(HEURIST_ERROR, 'Unable to build IIIF canvas for registered file');
        return false;
    }

    $label = trim(strip_tags($fileinfo['ulf_Description'] ?? ''));
    if($label === ''){
        $label = trim(strip_tags($fileinfo['ulf_OrigFileName'] ?? ''));
    }
    if($label === ''){
        $label = 'Heurist IIIF manifest';
    }

    $manifest = array(
        '@context' => 'http://iiif.io/api/presentation/3/context.json',
        'id' => HEURIST_BASE_URL_PRO.'api/'.$system->dbname().'/iiif/manifest/'.$ulf_ObfuscatedFileID,
        'type' => 'Manifest',
        'label' => array('en' => array($label)),
        'items' => array($canvasObj)
    );

    return json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

private static function getRegisteredManifestJson($system, array $fileinfo)
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
        if($manifest === ''){
            $manifest = loadRemoteURLContent(HEURIST_BASE_URL_PRO.'?db='.$system->dbname().'&file='.$fileinfo['ulf_ObfuscatedFileID']);
        }
    }

    if(!$manifest){
        $system->addError(HEURIST_NOT_FOUND, 'Registered IIIF manifest content could not be loaded');
        return false;
    }

    $json = json_decode($manifest, true);
    if(json_last_error() !== JSON_ERROR_NONE || !is_array($json)){
        $system->addError(HEURIST_INVALID_REQUEST, 'Registered IIIF manifest is not valid JSON');
        return false;
    }

    $type = $json['type'] ?? $json['@type'] ?? '';
    if($type !== 'Manifest' && $type !== 'sc:Manifest'){
        $system->addError(HEURIST_INVALID_REQUEST, 'Registered IIIF resource is not a Manifest');
        return false;
    }

    return json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}


private static function iiifCanvasHelper($system): DbIiifCanvas
{
    static $helpers = array();
    $key = method_exists($system, 'dbname') ? $system->dbname() : spl_object_hash($system);
    if(!isset($helpers[$key])){
        $helpers[$key] = new DbIiifCanvas($system);
    }
    return $helpers[$key];
}

private static function getCanvasMetadataForFile($system, array $fileinfo): ?array
{
    $ulfID = intval($fileinfo['ulf_ID'] ?? 0);
    if($ulfID<1){
        return null;
    }
    return self::iiifCanvasHelper($system)->canvasMetadataForFileID($ulfID);
}

private static function languageMapJson(string $value): string
{
    $value = trim(strip_tags($value));
    if($value===''){
        $value = 'Untitled';
    }
    return json_encode(array('none'=>array($value)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

public static function getIiifResource($system, $record, $iiif_version, $ulf_ObfuscatedFileID, $type_resource='Canvas'){

    $type_resource = strtolower((string)$type_resource);
    if($record==null && ($type_resource=='annotations' || $type_resource=='annotationpage')){
        return self::getLinkedIiifAnnotationPage($system, (string)$ulf_ObfuscatedFileID);
    }

    $mysqli = $system->getMysqli();

    $canvas = '';
    $comma = '';
    $info = array();

    if($record==null){
        //find file info by obfuscation id
        $info = fileGetFullInfo($system, $ulf_ObfuscatedFileID);

        if(!empty($info)){
            $label = trim(htmlspecialchars(strip_tags($info[0]['ulf_Description'])));

            if($label==''){
                //find name from linked record
                $query = 'SELECT rec_RecTypeID, rec_Title FROM Records, recDetails '
                .'WHERE rec_ID=dtl_RecID and dtl_UploadedFileID='.$info[0]['ulf_ID']
                .' LIMIT 1';

                $record = mysql__select_row($mysqli, $query);
                $label = htmlspecialchars(strip_tags($record[1]));//rec_Title
                $rectypeID = $record[0];//rec_RecTypeID
            }else{
                $rectypeID = 5;
            }

        }else{
            $system->addError(HEURIST_NOT_FOUND, 'Resource with given id not found');
            return false;
        }

    }else{

        $label = htmlspecialchars(strip_tags($record['rec_Title']));
        $rectypeID = $record['rec_RecTypeID'];
        //1. get "file" from field values
        foreach ($record['details'] as $dty_ID=>$field_details) {
            foreach($field_details as $dtl_ID=>$file){

                if($ulf_ObfuscatedFileID){
                    if($file['file']['ulf_ObfuscatedFileID']==$ulf_ObfuscatedFileID){
                        array_push($info, $file['file']);
                        break 2;
                    }
                }else{
                    array_push($info, $file['file']);
                }
            }
        }

    }

    $label = preg_replace('/\r|\n/','\n',trim($label));

    //2. get file info
    if(!empty($info)){
        //$info = fileGetFullInfo($system, $file_ids);
        
        $fcnt = 0;

        foreach($info as $fileinfo){

        if(self::isIiifManifestFile($fileinfo)){
            continue;
        }
            
        $mimeType = $fileinfo['fxm_MimeType'];

        $resource_type = null;

        if(strpos($mimeType,"video/")===0){
            if(strpos($mimeType,"youtube")>0 || strpos($mimeType,"vimeo")>0) {continue;}

            $resource_type = 'Video';
        }elseif(strpos($mimeType,"audio/")===0){

            if(strpos($mimeType,"soundcloud")>0) {continue;}

            $resource_type = 'Sound';
        }elseif(strpos($mimeType, DIR_IMAGE)===0 || IiifMediaHelper::isIiifImageInfoFile($fileinfo)){
            $resource_type = 'Image';
        }

        if (($iiif_version==2 && $resource_type!='Image') || ($resource_type==null)){
            continue;
        }

        $canvasMeta = self::getCanvasMetadataForFile($system, $fileinfo);
        $canvas_label = trim((string)($canvasMeta['label'] ?? ''));
        $canvas_label = $canvas_label!=='' ? htmlspecialchars(strip_tags($canvas_label)) : $label;
        $canvas_label = preg_replace('/\r|\n/','\n',trim($canvas_label));
        $canvas_summary = trim(strip_tags((string)($canvasMeta['summary'] ?? '')));
        $summary_json = $canvas_summary!==''
            ? ",\n      \"summary\": ".self::languageMapJson($canvas_summary)
            : '';

        $fileid = $fileinfo['ulf_ObfuscatedFileID'];
        $resource_url = IiifMediaHelper::resourceUrlFromFileInfo($system, $fileinfo, true, HEURIST_BASE_URL_PRO);

        $dimensions = IiifMediaHelper::resolveDimensionsForFileInfo(
            $fileinfo,
            is_array($canvasMeta) ? ($canvasMeta['dimensions'] ?? array()) : array(),
            $resource_url,
            array('width'=>1000, 'height'=>800)
        );
        $width = $dimensions['width'];
        $height = $dimensions['height'];
        $duration = $dimensions['duration'];

        $thumbnail_format = 'image/png';
        $thumbnailInfo = is_array($canvasMeta) && is_array($canvasMeta['thumbnail_fileinfo'] ?? null)
            ? $canvasMeta['thumbnail_fileinfo']
            : null;
        if($thumbnailInfo){
            $tumbnail_url = IiifMediaHelper::resourceUrlFromFileInfo($system, $thumbnailInfo, false, HEURIST_BASE_URL_PRO);
            $thumbnail_format = $thumbnailInfo['fxm_MimeType'] ?: $thumbnail_format;
        }else{
            $tumbnail_url = null;
        }
        if(!$tumbnail_url){
            $thumbfile = HEURIST_THUMB_DIR.'ulf_'.$fileid.'.png';
            if(file_exists($thumbfile)){
                $tumbnail_url = HEURIST_BASE_URL_PRO.'?db='.$system->dbname().'&thumb='.$fileid;
            }else{
                //if thumb not exists - rectype thumb (HEURIST_RTY_ICON)
                $tumbnail_url = HEURIST_BASE_URL_PRO.'?db='.$system->dbname().'&version=thumb&icon='.$rectypeID;
            }
        }

        $service = '';
        $resource_id = '';

        //get iiif image parameters
        if(IiifMediaHelper::isIiifImageInfoFile($fileinfo)){ //this is image info - it gets all required info from json

                $iiif_manifest = loadRemoteURLContent($fileinfo['ulf_ExternalFileReference']);//retrieve iiif image.info to be included into manifest
                $iiif_manifest = json_decode($iiif_manifest, true);
                if($iiif_manifest!==false && is_array($iiif_manifest)){

                    $context = @$iiif_manifest['@context'];
                    $service_id = $iiif_manifest['@id'];
                    if(@$iiif_manifest['width']>0) {$width = $iiif_manifest['width'];}
                    if(@$iiif_manifest['height']>0) {$height = $iiif_manifest['height'];}

                    $profile = @$iiif_manifest['profile'];

                    $mimeType = null;
                    if(is_array($profile)){
                        $mimeType = @$profile[1]['formats'][0];
                        if($mimeType) {$mimeType = DIR_IMAGE.$mimeType;}
                        $profile = @$profile[0];
                    }elseif($profile==null){
                        $profile = 'level1';
                    }
                    if(!$mimeType) {$mimeType= 'image/jpeg';}

                    if(strpos($profile, 'library.stanford.edu/iiif/image-api/1.1')>0){
                        $quality = 'native';
                    }else{
                        $quality = 'default';
                    }
                    $resource_url = $iiif_manifest['@id'].'/full/full/0/'.$quality.'.jpg';
                    $resource_id = $iiif_manifest['@id'];

                    if($iiif_version==2){
$service = <<<SERVICE2
                "height": $height,
                "width": $width,
                "service" : {
                            "profile" : "$profile",
                            "@context" : "$context",
                            "@id" : "$service_id"
                          }
                ],
SERVICE2;
                    }else{
$service = <<<SERVICE3
                "height": $height,
                "width": $width,
                "service": [
                  {
                    "id": "$service_id",
                    "profile": "$profile"
                  }
                ],
SERVICE3;
//                    "type": "ImageService3"
                    }
                }
        }

        $canvasDimensions = is_array($canvasMeta) && is_array($canvasMeta['dimensions'] ?? null) ? $canvasMeta['dimensions'] : array();
        if(!empty($canvasDimensions['width'])){ $width = $canvasDimensions['width']; }
        if(!empty($canvasDimensions['height'])){ $height = $canvasDimensions['height']; }
        if(!empty($canvasDimensions['duration'])){ $duration = $canvasDimensions['duration']; }

        $canvas_uri = self::genUUID();

        $tumbnail_height = 200;
        $tumbnail_width = 200;

        if($iiif_version==2){ //not used - outdated for mirador v2

$item = <<<CANVAS2
{
        "@id": "http://$canvas_uri",
        "@type": "sc:Canvas",
        "label": "$canvas_label",
        "height": $height,
        "width": $width,
        "thumbnail" : {
                "@id" : "$tumbnail_url",
                "height": $tumbnail_height,
                "width": $tumbnail_width
         },
        "images": [
            {
                "@type": "oa:Annotation",
                "motivation": "sc:painting",
                "resource": {
                    $service
                    "@id": "$resource_url",
                    "@type": "dctypes:$resource_type",
                    "format": "$mimeType"
                },
                "on": "http://$canvas_uri"
            }
        ]
  }
CANVAS2;

//                    "height": $height,
//                    "width": $width
      }else{

//$annotation_uri = self::genUUID();
//  "duration": 5,
//        "height": $height,
//        "width": $width

// Returns json
if($resource_id){ //this is iiif image

    //last section
    $parts = explode('/',$resource_id);
    $cnt = count($parts)-1;
    array_splice( $parts, $cnt, 0, 'canvas');
    $canvas_uri = implode('/',$parts);
    $parts[$cnt] = 'page';
    $annopage_uri = implode('/',$parts);
    $parts[$cnt] = 'annotation';
    $annotation_uri = implode('/',$parts);
    $image_uri = $resource_id.'/info.json';


}else{
    $root_uri = HEURIST_BASE_URL_PRO.'api/'.$system->dbname().'/iiif/';
    $canvas_uri = $root_uri.'canvas/'.$fileid;
    $annopage_uri = $root_uri.'page/'.$fileid;
    $annotation_uri = $root_uri.'annotation/'.$fileid;
    $image_uri = $root_uri.DIR_IMAGE.$fileid.'/info.json';
}

$external_annotations = '';
$external_annopage_uri = self::getIiifApiRoot($system).'annotations/'.$fileid;
if(self::hasLinkedIiifAnnotations($system, intval($fileinfo['ulf_ID'] ?? 0))){
    $external_annotations = ',
      "annotations": [
        {
          "id": "'.$external_annopage_uri.'",
          "type": "AnnotationPage"
        }
      ]';
}

$body_dimensions = '';
if(in_array($resource_type, array('Image', 'Video'), true)){
    if($height>0){ $body_dimensions .= ','."\n                \"height\": ".intval($height); }
    if($width>0){ $body_dimensions .= ','."\n                \"width\": ".intval($width); }
}
if(in_array($resource_type, array('Video', 'Sound'), true) && $duration>0){
    $body_dimensions .= ','."\n                \"duration\": ".floatval($duration);
}

$annotation = <<<ANNOTATION3
            {
              "id": "$annotation_uri",
              "type": "Annotation",
              "motivation": "painting",
              "body": {
                $service
                "id": "$resource_url",
                "type": "$resource_type",
                "format": "$mimeType"$body_dimensions
              },
              "target": "$canvas_uri"
            }
ANNOTATION3;

if($type_resource=='annotation'){
    return $annotation;
}

$annotation_page = <<<PAGE3
        {
          "id": "$annopage_uri",
          "type": "AnnotationPage",
          "items": [
                $annotation
          ]
        }
PAGE3;

if($type_resource=='page'){
    return $annotation_page;
}

$item = <<<CANVAS3
{
      "id": "$canvas_uri",
      "type": "Canvas",
      "label": "$canvas_label"$summary_json,
                "height": $height,
                "width": $width,
      "items": [
           $annotation_page
      ],
      "thumbnail": [
        {
          "id": "$tumbnail_url",
          "type": "Image",
          "format": "$thumbnail_format",
          "width": $tumbnail_width,
          "height": $tumbnail_height
        }
      ]$external_annotations

 }
CANVAS3;

/*
                "height": $height,
                "width": $width,
                "duration": 5,
*/
        }


        $canvas = $canvas.$comma.$item;
        $comma =  ",\n";
        
        $fcnt++;
        }//for info in fileinfo

    }//!empty($file_ids)


    return $canvas;
}

//
// not used
//
private static function genUUID2() {
    return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4) );
}

//
//
//
    /**
     * Generates a Version 4 UUID (Universally Unique Identifier).
     *
     * @return string The generated UUID.
     */
private static function genUUID() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        random_int( 0, 0xffff ), random_int( 0, 0xffff ),

        // 16 bits for "time_mid"
        random_int( 0, 0xffff ),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        random_int( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        random_int( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xffff )
    );
}


} //end class
?>