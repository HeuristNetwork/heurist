<?php
/**
* RecordPresentationController.php - Specialised record presentation dispatcher
*
* Routes record-type-backed presentation requests to Dataset and Map services
* and emits their stable JSON response.
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
namespace Heurist\Controller;

use Heurist\Runtime\ApiResponse;
use Heurist\Records\Presentation\DatasetPresentationService;
use Heurist\Records\Presentation\MapPresentationService;
use Heurist\Records\Query\QueryValidationException;

/** Dispatches public specialised representations of Heurist records. */
class RecordPresentationController
{
    private DatasetPresentationService $datasets;
    private MapPresentationService $maps;
    private ApiResponse $response;

    /** Initialise the controller for the current database. */
    public function __construct(
        DatasetPresentationService $datasets,
        MapPresentationService $maps,
        ?ApiResponse $response = null
    )
    {
        $this->datasets = $datasets;
        $this->maps = $maps;
        $this->response = $response ?? new ApiResponse();
    }

    /**
     * Output one Dataset, Map Document, or Map Layer presentation.
     *
     * @param string $presentation Presentation name.
     * @param int $recordId Heurist record ID.
     * @return void
     */
    public function handleRequest(string $presentation, int $recordId): void
    {
        if($recordId < 1){
            $this->fail('Record presentation id is not defined', 400);
            return;
        }

        try{
            switch($presentation){
                case 'dataset':
                    $result = $this->datasets->getDataset($recordId);
                    break;
                case 'document':
                    $result = $this->maps->getDocument($recordId);
                    break;
                case 'layer':
                    $result = $this->maps->getLayer($recordId);
                    break;
                default:
                    $this->fail('Unknown record presentation: '.$presentation, 404);
                    return;
            }
        }catch(QueryValidationException $error){
            $this->fail($error->getMessage(), 400);
            return;
        }

        if($result === null){
            $this->fail(ucfirst($presentation).' record is not available', 404);
            return;
        }

        if(defined('HEADER_CORS_POLICY')){ header(HEADER_CORS_POLICY); }
        $this->response->send($result);
    }

    /** Emit a standard API error. */
    private function fail(string $message, int $status): void
    {
        $this->response->sendError($status, 'invalid_request', $message);
    }
}
