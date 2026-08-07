<?php
/**
* map_presentation.php - Map and timeline API controller
*
* Handles public read-only MapDocument, MapLayer, GeoJSON, and timeline requests.
* It delegates record presentation to MapPresentationService and reuses the
* existing visibility-aware record search and GeoJSON export implementation.
*
* @project     Heurist academic knowledge management system
* @package     Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson@heuristnetwork.org>
* @since       7.0
*/
require_once dirname(__FILE__).'/../../autoload.php';

$params = isset($req_params) ? $req_params : $_REQUEST;
if(!isset($system) || !$system){
    $system = new hserv\System();
    if(!$system->init(@$params['db'])){ $system->errorExitApi(); }
}

$resource = (string)($params['resource'] ?? 'geojson');
$id = intval($params['id'] ?? 0);

if($resource === 'document' || $resource === 'layer'){
    $service = new hserv\map\MapPresentationService($system);
    $result = $resource === 'document' ? $service->getDocument($id) : $service->getLayer($id);
    if(!$result){ $system->errorExitApi(ucfirst($resource).' record not found or is not publicly visible'); }
    header(HEADER_CORS_POLICY);
    $system->setResponseHeader();
    header('Content-Type: application/json; charset=utf-8');
    print json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return;
}

// Reuse the mature search/visibility/GeoJSON implementation. The public
// controller normalises request parameters and wraps pagination metadata.
$params['format'] = 'geojson';
$params['restapi'] = 1;
$params['zip'] = 0;
$params['file'] = 0;
$params['simplify'] = !empty($params['simplify']) ? 1 : 0;
//if($resource === 'time'){ $params['leaflet'] = 1; }
$params['leaflet'] = 1;
if($id > 0){ $params['q'] = 'ids:'.$id; }
if(isset($params['query']) && is_array($params['query'])){ $params['q'] = $params['query']; }
$req_params = $params;

ob_start();
include dirname(__FILE__).'/record_output.php';
$raw = ob_get_clean();
$data = json_decode($raw, true);
if(!is_array($data)){
    print $raw;
    return;
}

$offset = max(0, intval($params['offset'] ?? 0));
$limit = max(1, intval($params['limit'] ?? 1000));
if($resource === 'time'){
    $items = array();
    foreach(($data['timeline'] ?? array()) as $entry){
        foreach(($entry['when'] ?? array()) as $span){
            $items[] = array(
                'recordId'=>intval($entry['rec_ID'] ?? 0),
                'title'=>(string)($entry['rec_Title'] ?? ''),
                'start'=>$span[0] ?? null,
                'end'=>$span[3] ?? ($span[0] ?? null),
                'group'=>null
            );
        }
    }
    $result = array('format'=>'heurist-timeline','version'=>1,'items'=>$items,
        'meta'=>array('total'=>count($items),'offset'=>$offset,'limit'=>$limit));
}else{
    $features = $data['geojson'];
    $result = array('type'=>'FeatureCollection','features'=>$features,
        'meta'=>array('database'=>(string)($params['db'] ?? ''),'recordId'=>$id ?: null,
            'total'=>count($features),'offset'=>$offset,'limit'=>$limit));
}
header(HEADER_CORS_POLICY);
$system->setResponseHeader();
header('Content-Type: application/json; charset=utf-8');
print json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
