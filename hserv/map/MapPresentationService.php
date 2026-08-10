<?php
/**
* MapPresentationService.php - Public map response builder
*
* Converts Map Document, Map Layer, and linked data-source records into stable,
* engine-neutral JSON structures for the public Heurist map API.
*
* @project     Heurist academic knowledge management system
* @package     map
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson@heuristnetwork.org>
* @since       7.0
*/
namespace hserv\map;

use hserv\entity\DbMapDocument;
use hserv\entity\DbMapLayer;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../entity/DbMapDocument.php';
require_once dirname(__FILE__).'/../entity/DbMapLayer.php';
require_once dirname(__FILE__).'/../structure/dbsTerms.php';
require_once dirname(__FILE__).'/../structure/ConceptCode.php';

/**
 * Builds public MapDocument and MapLayer representations.
 *
 * This service coordinates the record-type adapters, term lookup, bookmark
 * parsing, source detection, and conversion of stored JSON definitions without
 * introducing dependencies on a particular client-side mapping engine.
 */
class MapPresentationService
{
    private $system;
    private $documents;
    private $layers;
    private $terms;

    /**
     * Initialise the map presentation service.
     *
     * @param \hserv\System $system Initialised Heurist system context.
     */
    public function __construct($system)
    {
        $this->system = $system;
        $this->documents = new DbMapDocument($system);
        $this->layers = new DbMapLayer($system);
        $this->terms = new \DbsTerms($system, dbs_GetTerms($system));
        ConceptCode::setSystem($system);
    }

    /**
     * Build the public MapDocument response for a record.
     *
     * Referenced layers retain the order in which DT_MAP_LAYER values are stored.
     * Bookmark text is preserved and parsed when its format is recognised.
     *
     * @param int $recordId RT_MAP_DOCUMENT record ID.
     * @return array|null Public MapDocument response, or null when unavailable.
     */
    public function getDocument(int $recordId): ?array
    {
        $record = $this->documents->getPublicRecord($recordId);
        if(!$record){ return null; }

        $bookmarkRaw = $this->scalar($this->documents->value($record, 'DT_MAP_BOOKMARK'));
        $layerIds = array_values(array_filter(array_map('intval',
            $this->documents->values($record, 'DT_MAP_LAYER'))));
        $layers = array();
        foreach($layerIds as $index=>$layerId){
            $layer = $this->layers->getPublicRecord($layerId);
            if(!$layer){ continue; }
            $layers[] = array(
                'id' => $layerId,
                'recordId' => $layerId,
                'title' => (string)($layer['rec_Title'] ?? ''),
                'order' => $index + 1,
                'visible' => $this->termBoolean($this->layers->value($layer, 'DT_IS_VISIBLE'), true)
            );
        }

        return array(
            'format' => 'heurist-map-document',
            'version' => 1,
            'id' => intval($record['rec_ID']),
            'title' => (string)($record['rec_Title'] ?? ''),
            'mapBookmark' => $this->parseBookmark($bookmarkRaw),
            'bounds' => $this->normaliseGeo($this->documents->value($record, 'DT_GEO_OBJECT')),
            'symbology' => $this->decodeJson($this->documents->value($record, 'DT_SYMBOLOGY')),
            'minZoom' => $this->numberOrNull($this->documents->value($record, 'DT_MINIMUM_ZOOM_LEVEL')),
            'maxZoom' => $this->numberOrNull($this->documents->value($record, 'DT_MAXIMUM_ZOOM_LEVEL')),
            'minimumZoomKm' => $this->numberOrNull($this->documents->value($record, 'DT_MAXIMUM_ZOOM')),
            'maximumZoomKm' => $this->numberOrNull($this->documents->value($record, 'DT_MINIMUM_ZOOM')),
            'zoomToPointInKM' => $this->numberOrNull($this->documents->value($record, 'DT_ZOOM_KM_POINT')),
            'worldBaseMap' => $this->termDescriptor($this->documents->value($record, 'DT_WORLD_BASEMAP')),
            'crs' => $this->termDescriptor($this->documents->value($record, 'DT_CRS')),
            'layers' => $layers
        );
    }

    /**
     * Build the public MapLayer response for a record.
     *
     * The returned structure separates source configuration, styling, timeline
     * configuration, and display behaviour, and contains no native map-engine
     * objects or runtime layer identifiers.
     *
     * @param int $recordId RT_MAP_LAYER record ID.
     * @return array|null Public MapLayer response, or null when unavailable.
     */
    public function getLayer(int $recordId): ?array
    {
        $layer = $this->layers->getPublicRecord($recordId);
        if(!$layer){ return null; }
        $sourceRecord = $this->layers->getDataSource($layer);
        if(!$sourceRecord){ return null; }

        $styleRaw = $this->layers->value($layer, 'DT_SYMBOLOGY');
        $thematicRaw = $this->layers->value($layer, 'DT_MAP_THEMATIC');
        $timelineFields = $this->layers->values($layer, 'DT_TIMELINE_FIELDS');

        // Native zoom levels may be defined on the MapLayer itself or inherited
        // from its linked source record (notably tiled-image sources). Explicit
        // MapLayer values take precedence over source values.
        $layerMinZoom = $this->numberOrNull($this->layers->value($layer, 'DT_MINIMUM_ZOOM_LEVEL'));
        $layerMaxZoom = $this->numberOrNull($this->layers->value($layer, 'DT_MAXIMUM_ZOOM_LEVEL'));
        $sourceMinZoom = $this->numberOrNull($this->layers->value($sourceRecord, 'DT_MINIMUM_ZOOM_LEVEL'));
        $sourceMaxZoom = $this->numberOrNull($this->layers->value($sourceRecord, 'DT_MAXIMUM_ZOOM_LEVEL'));
        $dynamicRequests = $this->termBoolean($this->layers->value($sourceRecord, 'DT_IS_LOADED_BY_EXTENT'), false);

        $effectiveMinZoom = $layerMinZoom !== null ? $layerMinZoom : $sourceMinZoom;
        $effectiveMaxZoom = $layerMaxZoom !== null ? $layerMaxZoom : $sourceMaxZoom;

        return array(
            'format' => 'heurist-map-layer',
            'version' => 1,
            'id' => intval($layer['rec_ID']),
            'title' => (string)($layer['rec_Title'] ?? ''),
            'description' => '',
            'visible' => $this->termBoolean($this->layers->value($layer, 'DT_IS_VISIBLE'), true),
            'selectable' => true,
            'source' => $this->buildSource($sourceRecord),
            'style' => array(
                'type' => $thematicRaw ? 'thematic' : 'simple',
                'symbol' => $this->decodeJson($styleRaw),
                'thematic' => $this->decodeJson($thematicRaw)
            ),
            'timeline' => array(
                'enabled' => !empty($timelineFields),
                'fields' => array_values($timelineFields)
            ),
            'options' => array(
                'markerClustering' => false,
                'zoomToExtent' => false,
                'dynamicRequests' => $dynamicRequests,
                'minZoom' => $effectiveMinZoom,
                'maxZoom' => $effectiveMaxZoom,
                'minimumZoomKm' => $this->numberOrNull($this->layers->value($layer, 'DT_MAXIMUM_ZOOM')),
                'maximumZoomKm' => $this->numberOrNull($this->layers->value($layer, 'DT_MINIMUM_ZOOM')),
                'popupTemplate' => $this->scalar($this->layers->value($layer, 'DT_SMARTY_TEMPLATE'))
            )
        );
    }

    private function buildSource(array $record): array
    {
        $rty = intval($record['rec_RecTypeID'] ?? 0);
        $type = 'record';
        $map = array(
            'RT_QUERY_SOURCE' => 'heurist-query',
            'RT_MAP_LAYER' => 'heurist-query',
            'RT_TLCMAP_DATASET' => 'heurist-query',
            'RT_TILED_IMAGE_SOURCE' => 'tile',
            //'RT_GEOTIFF_SOURCE' => 'geotiff',
            'RT_IMAGE_SOURCE' => 'image',
            'RT_KML_SOURCE' => 'remote-geojson',
            'RT_FILE_SOURCE' => 'remote-geojson', //csv, kml, geojson
            'RT_SHP_SOURCE' => 'remote-geojson'
        );
        foreach($map as $constant=>$candidate){
            
            $this->system->defineConstant($constant);
            
            if(defined($constant) && intval(constant($constant)) === $rty){ $type = $candidate; break; }
        }

        $source = array(
            'type' => $type,
            'recordId' => intval($record['rec_ID'] ?? 0),
            'title' => (string)($record['rec_Title'] ?? '')
        );
        
        $file = $this->layers->value($record, 'DT_FILE_RESOURCE');        

        if($type=='remote-geojson'){
            //HEURIST_BASE_URL.
            
            $script_name = 'record_map_source'; //for kml,csv,geojson
            if(defined('RT_SHP_SOURCE') && $rty === intval(RT_SHP_SOURCE)){
                $script_name = 'record_shp'; //for shp
            }
            
            $source['url'] = HEURIST_BASE_URL."hserv/controller/$script_name.php?db="
                                .$this->system->dbname().'&format=geojson&recID='.$record['rec_ID'];
        }elseif($type=='tile'){
            
            $fileId = $this->layers->value($record, 'DT_SERVICE_URL');
            $fileinfo = fileGetFullInfo($this->system, $fileId);
            if(!isEmptyArray($fileinfo)){
                $fileinfo = $fileinfo[0];//
                $url = $fileinfo['ulf_ExternalFileReference'];    
                
                if(!(strpos($url,'{q}')>0 || strpos($url,'{z}/{x}/{y}')>0)){
                    $url = rtrim($url,'/') . '/{z}/{x}/{y}.'.$fileinfo['ulf_MimeExt'];
                    /*
                    $mimeType = intval($this->layers->value($record, 'DT_MIME_TYPE'));
                    if($mimeType>0){
                        $mimeType = ConceptCode::getTermConceptID($mimeType);
                        $extension = ($mimeType == '2-540')? 'png' :($mimeType == '2-537'?'jpg':'gif');
                        $url = $url.'.'.$extension;
                    }
                    */
                }
                
                $source['url'] = $url;
            }
            
            $source['bounds'] = $this->normaliseGeo($this->layers->value($record, 'DT_GEO_OBJECT'));
            // 
            $minZoom = $this->layers->value($record, 'DT_MINIMUM_ZOOM_LEVEL');
            $maxZoom = $this->layers->value($record, 'DT_MAXIMUM_ZOOM_LEVEL');
            
            if($minZoom>0) { $source['minZoom'] = $minZoom; }
            $source['maxZoom'] = ($maxZoom>0)?$maxZoom :19; //mandatory

            $schema = $this->layers->value($record, 'DT_MAP_IMAGE_LAYER_SCHEMA');
            //if($schema !== null){ $source['tileSchema'] = $schema; }
            //TMS naming schema
            $tmsSchema = ConceptCode::getTermLocalID('2-548');
            if(intval($schema) == $tmsSchema){
                $source['tms'] = true;    
            }
            
        }elseif($type=='image'){

            $fileinfo = fileGetFullInfo($this->system, $file);
            if(!isEmptyArray($fileinfo)){
                $fileinfo = $fileinfo[0];
                $url = @$fileinfo['ulf_ExternalFileReference'];    
                if(!$url){
                    $fileIdObfuscated = @$fileinfo['ulf_ObfuscatedFileID'];
                    $url = HEURIST_BASE_URL.'?fullres=1&db='.$this->system->dbname().'&file='.$fileIdObfuscated;
                }
            }
            $source['bounds'] = $this->normaliseGeo($this->layers->value($record, 'DT_GEO_OBJECT'));
            $source['url'] = $url;
            
        }else{
            $query = $this->layers->value($record, 'DT_QUERY_STRING');
            if($query !== null){ $source['query'] = $this->parseQuery($query); } 
            $url = $this->layers->value($record, 'DT_SERVICE_URL');
            if($url !== null){
                $source['url'] = $this->scalar($url);
                if(stripos((string)$source['url'], '/info.json') !== false){ $source['type'] = 'iiif'; }
            }
        }
        if($file !== null){ $source['fileId'] = intval($file); }
        
        //$world = $this->layers->value($record, 'DT_MAP_IMAGE_WORLDFILE');
        //if($world !== null){ $source['worldFile'] = $this->scalar($world); }
        $crs = $this->layers->value($record, 'DT_CRS');
        if($crs !== null){ $source['crs'] = $this->termDescriptor($crs); }
        return $source;
    }

    private function parseBookmark(?string $raw): ?array
    {
        if($raw === null || trim($raw) === ''){ return null; }
        $parts = array_map('trim', explode(',', $raw));
        $result = array('raw'=>$raw, 'type'=>strtolower((string)$parts[0]));
        if(strcasecmp((string)$parts[0], 'Extent') === 0 && count($parts) >= 5){
            $result['bounds'] = array(
                'west'=>(float)$parts[2], 'south'=>(float)$parts[1],
                'east'=>(float)$parts[4], 'north'=>(float)$parts[3]
            );
            if(isset($parts[5])){ $result['minimumZoom'] = $this->numberOrNull($parts[5]); }
            if(isset($parts[6])){ $result['maximumZoom'] = $this->numberOrNull($parts[6]); }
        }elseif(strcasecmp((string)$parts[0], 'Point') === 0 && count($parts) >= 3){
            $result['point'] = array('latitude'=>(float)$parts[1], 'longitude'=>(float)$parts[2]);
            if(isset($parts[3])){ $result['zoom'] = $this->numberOrNull($parts[3]); }
        }
        return $result;
    }

    private function termDescriptor($value): ?array
    {
        $id = intval($value);
        if($id < 1){ return null; }
        return array('id'=>$id, 'code'=>$this->terms->getTermCode($id), 'label'=>$this->terms->getTermLabel($id));
    }

    private function termBoolean($value, bool $default): bool
    {
        if($value === null || $value === ''){ return $default; }
        $code = strtolower((string)$this->terms->getTermCode(intval($value)));
        $label = strtolower((string)$this->terms->getTermLabel(intval($value)));
        return !in_array($code, array('no','false','0'), true) && !in_array($label, array('no','false'), true);
    }

    private function parseQuery($value)
    {
        if(is_array($value)){ return $value; }
        $text = trim((string)$value);
        $json = json_decode($text, true);
        if(is_array($json)){ return $json; }
        return ltrim($text, '?');
        //parse_str(ltrim($text, '?'), $parsed);
        //return !empty($parsed) ? $parsed : array('q'=>$text);
    }

    private function decodeJson($value)
    {
        if($value === null || $value === ''){ return null; }
        if(is_array($value)){ return $value; }
        $decoded = json_decode((string)$value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : array('raw'=>(string)$value);
    }

    private function normaliseGeo($value)
    {
        if($value === null || $value === ''){ return null; }
        
        $bbox = null;
        $geom = \geoPHP::load($value, 'wkt');
        if($geom!==null && !$geom->isEmpty()){
            $bbox = $geom->getBBox();
        }
        return $bbox;
        
        //return array('raw'=>(string)$value);
    }

    private function scalar($value): ?string
    {
        if($value === null){ return null; }
        return is_scalar($value) ? (string)$value : json_encode($value);
    }

    private function numberOrNull($value)
    {
        return is_numeric($value) ? 0 + $value : null;
    }
}
