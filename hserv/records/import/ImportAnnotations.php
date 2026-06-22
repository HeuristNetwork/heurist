<?php
/**
* ImportAnnotations.php - Import IIIF annotations from a selected Manifest.
*/
namespace hserv\records\import;

use hserv\entity\DbAnnotations;
use hserv\entity\DbIiifManifest;
use hserv\entity\DbIiifCanvas;
use hserv\entity\DbRecUploadedFiles;

require_once dirname(__FILE__).'/../edit/recordModify.php';

set_time_limit(0);

/**
 * Imports IIIF annotations for one selected manifest file.
 * Supports import_level=overlay and import_level=managed.
 */
class ImportAnnotations{

    private $system;
    private $manifestFileId;
    private $importLevel = 'managed';
    private $progressSessionId = 0;
    private $createThumbnail = false;
    private $dbAnno;

    public function __construct( $system, $params = null ) {
        $this->system = $system;

        // New explicit parameter. Keep old ids as backward-compatible fallback.
        $this->manifestFileId = @$params['manifest_file_id'] ?: @$params['manifest_ulf_id'] ?: @$params['ids'];
        if(is_array($this->manifestFileId)){
            $this->manifestFileId = reset($this->manifestFileId);
        }

        $this->importLevel = @$params['import_level'] ?: 'managed';
        $this->progressSessionId = @$params['session'];
        $this->createThumbnail = @$params['create_thumb']==1;
    }

    public function execute(){

        if(!$this->system->isAdmin()){
            $this->system->addError(HEURIST_REQUEST_DENIED,
                'To perform this action you must be logged in as Administrator of group \'Database Managers\'');
            return false;
        }

        if(!in_array($this->importLevel, array('overlay', 'managed'), true)){
            $this->system->addError(HEURIST_INVALID_REQUEST,
                'Unsupported IIIF import mode: '.$this->importLevel);
            return false;
        }

        $manifestFile = $this->resolveSelectedManifest();
        if(!$manifestFile){
            return false;
        }

        $manifest = $this->loadManifestJson($manifestFile);
        if(!$manifest){
            return false;
        }

        if(!$this->validateImportModeForManifest($manifest)){
            return false;
        }

        $manifestRecID = 0;
        $dbManifest = new DbIiifManifest($this->system);
        $existingManagedRecID = $dbManifest->findManifestRecordForFile($manifestFile);

        if($this->importLevel === 'overlay'){
            if($existingManagedRecID>0){
                $this->system->addError(HEURIST_ACTION_BLOCKED,
                    'This registered Manifest is already managed by Heurist Manifest record '
                    .$existingManagedRecID.'. Annotation overlay mode is not available. Use Full manifest management instead.');
                return false;
            }
        }else{
            $manifestRecID = $this->ensureManifestRecord($manifestFile, $manifest);
            if(!$manifestRecID){
                return false;
            }
        }

        $canvasImport = array('map'=>array(), 'ordered'=>array(), 'added'=>array(), 'updated'=>array(), 'retained'=>array(), 'preserved_local'=>array(), 'issues'=>array());
        if($this->importLevel === 'managed'){
            $canvasImport = $this->importCanvases($manifest, $manifestRecID);
            if($canvasImport===false){
                return false;
            }
        }

        $annotations = $this->extractAnnotations($manifest, $manifestFile['source_url']);

        $result = array(
            'manifest_rec_id' => $manifestRecID,
            'manifest_file_id' => intval($manifestFile['ulf_ID']),
            'import_level' => $this->importLevel,
            'total_canvases' => count($canvasImport['ordered']),
            'canvases_added' => $canvasImport['added'],
            'canvases_updated' => $canvasImport['updated'],
            'canvases_retained' => $canvasImport['retained'],
            'canvases_preserved_local' => $canvasImport['preserved_local'],
            'total_annotations' => count($annotations),
            'processed' => 0,
            'added' => array(),
            'updated' => array(),
            'retained' => array(),
            'preserved_local' => array(),
            'without_annotations' => empty($annotations),
            'issues' => $canvasImport['issues']
        );

        if(empty($annotations)){
            return $result;
        }

        if($this->progressSessionId){
            mysql__update_progress(null, $this->progressSessionId, true, '0,'.count($annotations));
        }

        $this->dbAnno = new DbAnnotations($this->system);

        foreach($annotations as $idx=>$ctx){
            $ctx['manifestRecID'] = $manifestRecID;
            $ctx['state'] = 'imported';
            $ctx['preserveLocal'] = 1;
            if($this->importLevel === 'managed' && @$ctx['canvasOriginalId'] && isset($canvasImport['map'][$ctx['canvasOriginalId']])){
                $ctx['canvasRecID'] = intval($canvasImport['map'][$ctx['canvasOriginalId']]);
            }

            $res = $this->dbAnno->saveImportedAnnotation($ctx, $this->createThumbnail);
            $result['processed']++;

            if($res===false){
                $err = $this->system->getError();
                $key = @$ctx['annotation_id'] ?: ('annotation #'.($idx+1));
                $result['issues'][$key] = @$err['message'] ?: 'Unknown annotation import error';
                $this->system->clearError();
            }elseif(is_array($res) && @$res['status']!=HEURIST_OK){
                $key = @$ctx['annotation_id'] ?: ('annotation #'.($idx+1));
                $result['issues'][$key] = @$res['message'] ?: 'Unknown annotation import error';
            }else{
                $rec_id = intval(@$res['data']);
                if(@$res['is_new']){
                    $result['added'][] = $rec_id;
                }elseif(@$res['is_preserved_local']){
                    $result['preserved_local'][] = $rec_id;
                }elseif(@$res['is_retained']){
                    $result['retained'][] = $rec_id;
                }else{
                    $result['updated'][] = $rec_id;
                }
            }

            if($this->progressSession($result)){
                return false;
            }
        }

        if($this->progressSessionId){
            mysql__update_progress(null, $this->progressSessionId, false, 'REMOVE');
        }

        return $result;
    }

    private function resolveSelectedManifest(){
        if(!$this->manifestFileId){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Manifest file is not selected');
            return false;
        }

        $id = trim((string)$this->manifestFileId);
        $mysqli = $this->system->getMysqli();

        if(is_numeric($id)){
            $where = 'ulf_ID='.intval($id);
        }else{
            $where = 'ulf_ObfuscatedFileID="'.addslashes($id).'"';
        }

        $query = 'SELECT ulf_ID, ulf_ObfuscatedFileID, ulf_OrigFileName, ulf_ExternalFileReference, '
            .'ulf_FilePath, ulf_FileName, ulf_PreferredSource FROM recUploadedFiles WHERE '.$where.' LIMIT 1';
        $row = mysql__select_row($mysqli, $query);

        if(!is_array($row)){
            $this->system->addError(HEURIST_NOT_FOUND, 'Selected manifest file was not found in registered files');
            return false;
        }

        $external = $row[3];
        $rawUrl = $external ? $external : HEURIST_BASE_URL.'?db='.$this->system->dbname().'&file='.$row[1];

        return array(
            'ulf_ID' => intval($row[0]),
            'ulf_ObfuscatedFileID' => $row[1],
            'ulf_OrigFileName' => $row[2],
            'ulf_ExternalFileReference' => $external,
            'ulf_FilePath' => $row[4],
            'ulf_FileName' => $row[5],
            'ulf_PreferredSource' => $row[6],
            'source_url' => $rawUrl
        );
    }

    private function loadManifestJson($manifestFile){
        $content = loadRemoteURLContent($manifestFile['source_url']);
        if(!$content){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Manifest file '.$manifestFile['source_url'].' is not accessible');
            return false;
        }

        $json = json_decode($content, true);
        if(!is_array($json)){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Manifest file is not valid JSON. '.json_last_error_msg());
            return false;
        }

        if(!($this->isManifest($json) || $this->isAnnotationContainer($json))){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Selected JSON is not a recognised IIIF Manifest or Annotation container');
            return false;
        }

        return $json;
    }

    private function ensureManifestRecord($manifestFile, $manifest){
        $dbManifest = new DbIiifManifest($this->system);
        return $dbManifest->ensureFromManifestFile($manifestFile, $manifest, $this->importLevel);
    }


    private function importCanvases(array $manifest, int $manifestRecID)
    {
        $canvasList = $this->extractCanvases($manifest);
        $out = array('map'=>array(), 'ordered'=>array(), 'added'=>array(), 'updated'=>array(), 'retained'=>array(), 'preserved_local'=>array(), 'issues'=>array());

        if(empty($canvasList)){
            return $out;
        }

        $dbCanvas = new DbIiifCanvas($this->system);
        foreach($canvasList as $idx=>$canvas){
            $canvasId = $this->getJsonId($canvas);
            $key = $canvasId ?: ('canvas #'.($idx+1));
            $res = $dbCanvas->ensureFromCanvas($canvas, true);
            if($res===false){
                $err = $this->system->getError();
                $out['issues'][$key] = @$err['message'] ?: 'Unknown Canvas import error';
                $this->system->clearError();
                continue;
            }

            $recID = intval(@$res['recID']);
            if($recID<1){
                continue;
            }
            if($canvasId){
                $out['map'][$canvasId] = $recID;
            }
            $out['ordered'][] = $recID;

            if(@$res['is_new']){
                $out['added'][] = $recID;
            }elseif(@$res['is_preserved_local']){
                $out['preserved_local'][] = $recID;
            }elseif(@$res['is_retained']){
                $out['retained'][] = $recID;
            }else{
                $out['updated'][] = $recID;
            }
        }

        $dbManifest = new DbIiifManifest($this->system);
        if(!$dbManifest->setCanvasRefs($manifestRecID, $out['ordered'])){
            $err = $this->system->getError();
            $out['issues']['manifest_canvases'] = @$err['message'] ?: 'Unable to update Manifest Canvas list';
            $this->system->clearError();
        }

        return $out;
    }

    private function extractCanvases(array $json): array
    {
        if(@$json['type']=='Manifest'){
            return array_values(array_filter((array)@$json['items'], function($item){
                return is_array($item) && @$item['type']=='Canvas';
            }));
        }

        if(@$json['@type']=='sc:Manifest'){
            $out = array();
            foreach((array)@$json['sequences'] as $seq){
                foreach((array)@$seq['canvases'] as $canvas){
                    if(is_array($canvas) && @$canvas['@type']=='sc:Canvas'){
                        $out[] = $canvas;
                    }
                }
            }
            return $out;
        }

        return array();
    }

    private function isManifest($json){
        return @$json['type']=='Manifest' || @$json['@type']=='sc:Manifest';
    }

    private function isPresentationV2Manifest($json): bool
    {
        return is_array($json) && @$json['@type']=='sc:Manifest';
    }

    private function validateImportModeForManifest($json): bool
    {
        // Annotation overlay mode stores annotations against the original v3
        // Canvas URIs and leaves the registered Manifest file unmanaged. IIIF
        // Presentation API v2 Manifests use sequences/canvases/otherContent, so
        // import them only in managed mode, where canvases are converted to
        // RT_IIIF_CANVAS records and the output is generated by Heurist.
        if($this->importLevel === 'overlay' && $this->isPresentationV2Manifest($json)){
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                'Annotation overlay mode is not available for IIIF Presentation API v2 manifests. Please import this Manifest in full management mode.');
            return false;
        }
        return true;
    }

    private function isAnnotationContainer($json){
        return @$json['type']=='AnnotationPage'
            || @$json['type']=='AnnotationCollection'
            || @$json['@type']=='sc:AnnotationList';
    }

    private function getJsonId($json){
        return @$json['id'] ?: @$json['@id'];
    }

    private function extractAnnotations($json, $baseUrl=null){
        if(@$json['type']=='AnnotationPage'){
            return $this->annotationsFromPage($json, null, $baseUrl);
        }
        if(@$json['type']=='AnnotationCollection'){
            $out = array();
            foreach((array)@$json['items'] as $page){
                $out = array_merge($out, $this->annotationsFromPageOrRef($page, null, $baseUrl));
            }
            return $out;
        }
        if(@$json['@type']=='sc:AnnotationList'){
            return $this->annotationsFromV2List($json, null, $baseUrl);
        }
        if(@$json['type']=='Manifest'){
            return $this->extractAnnotationsV3($json, $baseUrl);
        }
        if(@$json['@type']=='sc:Manifest'){
            return $this->extractAnnotationsV2($json, $baseUrl);
        }
        return array();
    }

    private function extractAnnotationsV3($manifest, $baseUrl){
        $out = array();
        foreach((array)@$manifest['items'] as $canvas){
            if(@$canvas['type']!='Canvas'){
                continue;
            }
            $canvasId = @$canvas['id'];
            foreach((array)@$canvas['annotations'] as $page){
                $out = array_merge($out, $this->annotationsFromPageOrRef($page, $canvasId, $baseUrl));
            }
        }
        return $out;
    }

    private function extractAnnotationsV2($manifest, $baseUrl){
        $out = array();
        foreach((array)@$manifest['sequences'] as $seq){
            foreach((array)@$seq['canvases'] as $canvas){
                $canvasId = @$canvas['@id'];
                foreach((array)@$canvas['otherContent'] as $list){
                    $out = array_merge($out, $this->annotationsFromPageOrRef($list, $canvasId, $baseUrl));
                }
            }
        }
        return $out;
    }

    private function annotationsFromPageOrRef($page, $canvasId, $baseUrl){
        if(is_array($page) && (@$page['items'] || @$page['resources'])){
            if(@$page['type']=='AnnotationPage'){
                return $this->annotationsFromPage($page, $canvasId, $baseUrl);
            }
            if(@$page['@type']=='sc:AnnotationList'){
                return $this->annotationsFromV2List($page, $canvasId, $baseUrl);
            }
        }

        $url = null;
        if(is_string($page)){
            $url = $page;
        }elseif(is_array($page)){
            $url = @$page['id'] ?: @$page['@id'];
        }

        if(!$url){
            return array();
        }

        $loaded = $this->loadJsonUrl($url, $baseUrl);
        if(!$loaded){
            return array();
        }

        if(@$loaded['type']=='AnnotationPage'){
            return $this->annotationsFromPage($loaded, $canvasId, $url);
        }
        if(@$loaded['@type']=='sc:AnnotationList'){
            return $this->annotationsFromV2List($loaded, $canvasId, $url);
        }
        return array();
    }

    private function loadJsonUrl($url, $baseUrl=null){
        $content = loadRemoteURLContent($url);
        if(!$content){
            return null;
        }
        $json = json_decode($content, true);
        return is_array($json) ? $json : null;
    }

    private function annotationsFromPage($page, $canvasId, $sourcePageUrl){
        $out = array();
        foreach((array)@$page['items'] as $anno){
            if(@$anno['type']!='Annotation'){
                continue;
            }
            if($this->isPaintingAnnotation($anno)){
                continue;
            }
            $ctxCanvas = $canvasId ?: $this->extractTargetCanvas($anno);
            $out[] = array(
                'annotation' => $anno,
                'annotation_id' => $this->getJsonId($anno),
                'canvasOriginalId' => $ctxCanvas,
                'sourceAnnotationPage' => $this->getJsonId($page) ?: $sourcePageUrl
            );
        }
        return $out;
    }

    private function annotationsFromV2List($list, $canvasId, $sourcePageUrl){
        $out = array();
        foreach((array)@$list['resources'] as $anno){
            if(!(@$anno['@type']=='oa:Annotation')){
                continue;
            }
            $ctxCanvas = $canvasId ?: $this->extractV2TargetCanvas($anno);
            $out[] = array(
                'annotation' => $anno,
                'annotation_id' => @$anno['@id'],
                'canvasOriginalId' => $ctxCanvas,
                'sourceAnnotationPage' => @$list['@id'] ?: $sourcePageUrl
            );
        }
        return $out;
    }

    private function isPaintingAnnotation($anno){
        $motivation = @$anno['motivation'];
        if(is_array($motivation)){
            $motivation = reset($motivation);
        }
        if($motivation === 'painting'){
            return true;
        }
        return @$anno['body']['type']=='Image' || @$anno['body']['type']=='Video' || @$anno['body']['type']=='Sound';
    }

    private function extractTargetCanvas($anno){
        $target = @$anno['target'];
        if(is_string($target)){
            return explode('#', $target, 2)[0];
        }
        if(is_array($target)){
            if(array_keys($target)===range(0, count($target)-1)){
                $target = reset($target);
            }
            return @$target['source'] ?: @$target['id'];
        }
        return null;
    }

    private function extractV2TargetCanvas($anno){
        if(is_array(@$anno['on'])){
            $target = reset($anno['on']);
            return @$target['full'];
        }
        return null;
    }

    private function progressSession($result){
        if($this->progressSessionId && @$result['processed'] % 5 == 0){
            $current_val = mysql__update_progress(null, $this->progressSessionId, true, $result['processed'].','.$result['total_annotations']);
            if($current_val && $current_val=='terminate'){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Operation is terminated by user');
                return true;
            }
        }
        return false;
    }
}
