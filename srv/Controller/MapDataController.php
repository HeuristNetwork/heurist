<?php
/**
* MapDataController.php - Read-only record GeoJSON controller
*
* Streams GeoJSON produced from Heurist record queries. File-backed source
* conversion, shapefiles and legacy timeline output remain in `/hserv`.
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

declare(strict_types=1);

namespace Heurist\Controller;

use Heurist\Records\Map\GeoJsonStreamWriter;
use Heurist\Records\Map\MapFeatureService;
use Heurist\Runtime\ApiResponse;
use Heurist\Runtime\ErrorReporter;
use Heurist\Runtime\RuntimeContext;
use Throwable;

/** HTTP boundary for the migrated query-to-GeoJSON pipeline. */
final class MapDataController
{
    private MapFeatureService $features;
    private GeoJsonStreamWriter $writer;
    private ApiResponse $response;
    private ErrorReporter $errors;
    private RuntimeContext $runtime;

    /** Initialise the controller with the complete map-feature pipeline. */
    public function __construct(
        MapFeatureService $features,
        RuntimeContext $runtime,
        ?GeoJsonStreamWriter $writer = null,
        ?ApiResponse $response = null,
        ?ErrorReporter $errors = null
    ) {
        $this->features = $features;
        $this->runtime = $runtime;
        $this->writer = $writer ?? new GeoJsonStreamWriter();
        $this->response = $response ?? new ApiResponse();
        $this->errors = $errors ?? new ErrorReporter();
    }

    /**
     * Stream GeoJSON for one record or a query.
     *
     * @param array $parameters Sanitized API parameters.
     * @param int|null $recordId Optional single-record selector.
     */
    public function outputRecordGeoJson(array $parameters, ?int $recordId = null): void
    {
        if($recordId !== null && $recordId > 0){
            unset($parameters['q'], $parameters['query']);
            $parameters['ids'] = array($recordId);
        }elseif(isset($parameters['query']) && is_array($parameters['query'])){
            $parameters['q'] = $parameters['query'];
        }

        try{
            $stream = $this->features->createStream($parameters);
            if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
            header('Content-Type: application/geo+json; charset=utf-8');
            http_response_code(200);
            $this->writer->write($stream);
        }catch(\InvalidArgumentException $exception){
            $this->response->sendError(400, 'invalid_request', $exception->getMessage());
        }catch(Throwable $exception){
            $this->errors->report($exception, $this->runtime);
            $this->response->sendError(500, 'server_error', 'Unable to produce map data');
        }
    }
}
