<?php
/**
* recordQuery.php - Internal modern record-query entry point
*
* Provides the non-OpenAPI HTTP entry point for the new record search engine.
* It accepts the same request contract and returns the same response as
* /api/{db}/records. Legacy record_search.php remains untouched.
*
* @project     Heurist academic knowledge management system
* @package     Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

use hserv\controller\RecordQueryController;
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';

$httpMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if(!in_array($httpMethod, array('GET','POST'), true)){
    header('Allow: GET, POST');
    http_response_code(405);
    header(CTYPE_JSON);
    print json_encode(array(
        'status'=>405,
        'error'=>HEURIST_INVALID_REQUEST,
        'message'=>'Method not allowed'
    ));
    exit;
}

$req_params = USanitize::sanitizeInputArray();
if($httpMethod === 'POST' && stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === 0){
    $body = json_decode(file_get_contents('php://input'), true);
    if(json_last_error() !== JSON_ERROR_NONE){
        http_response_code(400);
        header(CTYPE_JSON);
        print json_encode(array(
            'status'=>400,
            'error'=>HEURIST_INVALID_REQUEST,
            'message'=>'Invalid JSON request body'
        ));
        exit;
    }
    if(is_array($body)){
        if(array_keys($body) === range(0, count($body)-1)){
            $req_params['query'] = $body;
        }else{
            foreach($body as $key=>$value){ $req_params[$key] = $value; }
        }
    }
}

$system = new hserv\System();
if(!$system->init($req_params['db'] ?? null)){
    $system->errorExitApi();
}

$controller = new RecordQueryController($system);
$controller->output($req_params);
$system->dbclose();
