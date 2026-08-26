<?php
/**
* MapDataController.php - Spatial map data API controller
*
* Handles GeoJSON for Heurist records/queries and file-backed datasource
* records. File conversion is delegated to MapDataSourceService.
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
namespace hserv\controller;

use hserv\records\map\MapDataSourceService;
use hserv\records\map\MapFeatureService;
use hserv\records\map\GeoJsonStreamWriter;
use hserv\records\search\QueryValidationException;

/**
 * Single HTTP boundary for map feature data.
 */
class MapDataController
{
    /** @var \hserv\System */
    private $system;

    /** @var array */
    private $params;

    /** @var MapFeatureService|object|null */
    private $featureService;

    /** @var GeoJsonStreamWriter|object|null */
    private $streamWriter;

    public function __construct(
        $system,
        array $params = array(),
        $featureService = null,
        $streamWriter = null
    )
    {
        $this->system = $system;
        $this->params = $params;
        $this->featureService = $featureService;
        $this->streamWriter = $streamWriter;
    }

    /**
     * Output a file-backed datasource record (shp, kml, ccv, geojson files)
     *
     * Route: /api/{db}/map/data/{recID}
     */
    public function outputDataSource(int $recordId): void
    {
        $format = strtolower(trim((string)($this->params['format'] ?? 'geojson')));
        if($format === ''){
            $format = 'source';
        }

        try{
            $service = new MapDataSourceService($this->system);
            $simplify = !array_key_exists('simplify', $this->params)
                || !in_array(strtolower((string)$this->params['simplify']), array('0','no','false'), true);

            $result = $service->getData($recordId, $format, array(
                'metadata' => !empty($this->params['metadata']),
                // Preserve legacy datasource behaviour: simplify by default,
                // but allow ?simplify=0 to disable it explicitly.
                'simplify' => $simplify
            ));
            $this->outputResult($result);
        }catch(\InvalidArgumentException $e){
            $this->system->errorExitApi($e->getMessage(), HEURIST_INVALID_REQUEST, true, 400);
        }catch(\RuntimeException $e){
            $this->system->errorExitApi($e->getMessage(), HEURIST_ERROR, true, 500);
        }catch(\Throwable $e){
            $this->system->errorExitApi($e->getMessage(), HEURIST_ERROR, true, 500);
        }
    }

    /**
     * Output GeoJSON for ordinary Heurist records or a Heurist query.
     *
     * Ordinary map output uses the modern record-search, expansion and record-
     * data services. The timeline route remains on its legacy exporter until
     * the separate timeline migration phase.
     */
    public function outputRecordGeoJson(?int $recordId = null, bool $timeline = false): void
    {
        if($timeline){
            $this->outputLegacyTimeline($recordId);
            return;
        }
        $req_params = $this->params;
        if($recordId !== null && $recordId > 0){
            unset($req_params['q'], $req_params['query']);
            $req_params['ids'] = array($recordId);
        }elseif(isset($req_params['query']) && is_array($req_params['query'])){
            $req_params['q'] = $req_params['query'];
        }

        try{
            require_once dirname(__FILE__).'/../records/map/MapFeatureService.php';
            require_once dirname(__FILE__).'/../records/map/GeoJsonStreamWriter.php';
            $service = $this->featureService ?? new MapFeatureService($this->system);
            $stream = $service->createStream($req_params);

            header(HEADER_CORS_POLICY);
            $this->system->setResponseHeader();
            header('Content-Type: application/geo+json; charset=utf-8');
            http_response_code(200);
            $writer = $this->streamWriter ?? new GeoJsonStreamWriter();
            $writer->write($stream);
        }catch(QueryValidationException $e){
            $this->system->errorExitApi($e->getMessage(), HEURIST_INVALID_REQUEST, true, 400);
        }catch(\InvalidArgumentException $e){
            $this->system->errorExitApi($e->getMessage(), HEURIST_INVALID_REQUEST, true, 400);
        }catch(\Throwable $e){
            $this->system->errorExitApi('Unable to produce map data', HEURIST_ERROR, true, 500);
        }
    }

    /** Keep /time behaviour unchanged while map output is migrated independently. */
    private function outputLegacyTimeline(?int $recordId): void
    {
        $req_params = $this->params;
        $req_params['format'] = 'geojson';
        $req_params['restapi'] = 1;
        $req_params['zip'] = 0;
        $req_params['file'] = 0;
        $req_params['simplify'] = !empty($req_params['simplify']) ? 1 : 0;
        $req_params['leaflet'] = \hserv\records\export\ExportRecordsGEOJSON::LEAFLET_FEATURE_COLLECTION;
        $req_params['resource'] = 'time';
        if($recordId !== null && $recordId > 0){
            $req_params['q'] = 'ids:'.$recordId;
        }elseif(isset($req_params['query']) && is_array($req_params['query'])){
            $req_params['q'] = $req_params['query'];
        }

        // record_output.php expects these variables in include scope.
        $system = $this->system;
        include dirname(__FILE__).'/record_output.php';
    }

    /**
     * Write a MapDataSourceService result to the HTTP response.
     */
    private function outputResult(array $result): void
    {
        header(HEADER_CORS_POLICY);
        $this->system->setResponseHeader();

        $contentType = trim((string)($result['contentType'] ?? 'application/octet-stream'));
        header('Content-Type: '.$contentType);

        $filename = trim((string)($result['filename'] ?? ''));
        if($filename !== ''){
            $safe = rawurlencode($filename);
            header(
                'Content-Disposition: attachment; filename="'.$safe.'"; '.
                "filename*=utf-8''".$safe
            );
        }

        if(($result['type'] ?? null) === 'file'){
            $path = $result['path'] ?? null;
            if(!$path || !is_file($path)){
                $this->system->errorExitApi('Map data output file is not available', HEURIST_ERROR, true, 500);
            }

            if(($result['contentEncoding'] ?? null) === 'gzip'){
                $content = gzencode(file_get_contents($path), 6);
                header('Content-Encoding: gzip');
                header(CONTENT_LENGTH . strlen($content));
                echo $content;
            }else{
                header(CONTENT_LENGTH . filesize($path));
                readfile($path);
            }

            if(!empty($result['deleteAfterOutput'])){
                @unlink($path);
            }
            return;
        }

        $content = (string)($result['content'] ?? '');
        header(CONTENT_LENGTH . strlen($content));
        echo $content;
    }
}
