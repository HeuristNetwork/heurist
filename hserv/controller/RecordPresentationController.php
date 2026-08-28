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
namespace hserv\controller;

use hserv\records\data\DatasetPresentationService;
use hserv\records\map\MapPresentationService;
use hserv\records\search\QueryValidationException;

/** Dispatches public specialised representations of Heurist records. */
class RecordPresentationController
{
    private $system;

    /** Initialise the controller for the current database. */
    public function __construct($system, array $params = array())
    {
        $this->system = $system;
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
        }

        try{
            switch($presentation){
                case 'dataset':
                    $result = (new DatasetPresentationService($this->system))
                        ->getDataset($recordId);
                    break;
                case 'document':
                    $result = (new MapPresentationService($this->system))
                        ->getDocument($recordId);
                    break;
                case 'layer':
                    $result = (new MapPresentationService($this->system))
                        ->getLayer($recordId);
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
        }

        header(HEADER_CORS_POLICY);
        header(CTYPE_JSON);
        $this->system->setResponseHeader();
        print json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->system->dbclose();
    }

    /** Emit a standard API error. */
    private function fail(string $message, int $status): void
    {
        if(function_exists('exitWithError')){
            \exitWithError($message, $status);
        }
        http_response_code($status);
        $this->system->errorExitApi($message);
    }
}
