<?php
/**
* MapPresentationController.php - Map document/layer API controller
*
* Handles public read-only MapDocument and MapLayer presentation requests.
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

use hserv\records\map\MapPresentationService;

/**
 * Thin HTTP boundary for MapPresentationService.
 */
class MapPresentationController
{
    /** @var \hserv\System */
    private $system;

    /** @var array */
    private $params;

    public function __construct($system, array $params = array())
    {
        $this->system = $system;
        $this->params = $params;
    }

    /**
     * Output a public MapDocument or MapLayer JSON representation.
     */
    public function handleRequest(string $resource, int $recordId): void
    {
        if(!in_array($resource, array('document','layer'), true)){
            $this->system->errorExitApi('Invalid map presentation resource', HEURIST_INVALID_REQUEST, true, 400);
        }
        if($recordId < 1){
            $this->system->errorExitApi('Map '.$resource.' id is missing or invalid', HEURIST_INVALID_REQUEST, true, 400);
        }

        $service = new MapPresentationService($this->system);
        $result = $resource === 'document'
            ? $service->getDocument($recordId)
            : $service->getLayer($recordId);

        if(!$result){
            $this->system->errorExitApi(
                ucfirst($resource).' record not found or is not publicly visible',
                HEURIST_NOT_FOUND,
                true,
                404
            );
        }

        header(HEADER_CORS_POLICY);
        $this->system->setResponseHeader();
        header('Content-Type: application/json; charset=utf-8');
        print json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
