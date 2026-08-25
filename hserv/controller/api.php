<?php
/**
* api.php - Entry point for api requests
* 
* Entry point for the Heurist application to retrieve entity data 
* (database definitions), Heurist record and iiif presentation via api requests
* in format   /api/my_database/entitys_name/identification|query
* https://example.net/api/mydbname/rst/12
*
* This script initializes and runs the particular entity class, which is responsible
* for handling incoming requests
* or routing to record_output.php controller for Records and iiif_presentation.php for iiif.
* 
* APACHE CONFIGURATION (CENTRALIZED ROUTING)
* ---
* All requests are routed through the root /index.php entrypoint.
* 
* Place these rules in the VirtualHost config (preferred) or .htaccess:
* 
* RewriteEngine On
* 
* # 1) Do not rewrite existing files or directories (serve directly)
* RewriteCond %{REQUEST_FILENAME} -f [OR]
* RewriteCond %{REQUEST_FILENAME} -d
* RewriteRule ^ - [L]
* 
* # 2) Route all other requests to the root router
* RewriteRule ^.*$ /index.php [L,QSA]
* 
* 
* ROUTING BEHAVIOUR
* ---
* All non-static requests are first handled by the root /index.php router.
* 
* The router (RequestRouter) then decides how to process the request:
* 
* - API requests (/api/...) are internally dispatched to:
*     /<version>/hserv/controller/api.php
* 
* - Record and file requests may be redirected to specific scripts
*   (e.g. record_output.php, fileDownload.php)
* 
* - UI requests are handled by including: /<root>/index.php
* 
* This replaces the old direct Apache rewrites to controller scripts.
* The original entrypoint (index.php) now resides in /movetoparent
* copy it to webserver <root>.
* 
* 
* JWT AUTHENTICATION
* ---
* To enable JWT-based API authentication, define the following in:
* 
* <root>/heuristConfigIni.php
* 
*     $jwt_Secret = 'your-long-random-secret'; // REQUIRED (min 8 chars)
*     $jwt_TTL    = 600; // token lifetime in seconds (default: 10 minutes)
* 
* When configured:
* 
* - Tokens are issued via auth.php
* - API requests can authenticate using:
*       Authorization: Bearer <token>
* - Token verification is performed in api.php before request dispatch
* 
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.6
*/
use hserv\utilities\USanitize;
use hserv\utilities\USystem;
use hserv\utilities\UJwt;
use hserv\controller\MapPresentationController;
use hserv\controller\MapDataController;
use hserv\controller\RecordQueryController;

require_once dirname(__FILE__).'/../../autoload.php';

if(@$_REQUEST['method']){
    $method = $_REQUEST['method'];
}else{
    //get method  - GET POST PUT DELETE
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {  //add
        if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
            $method = 'DELETE';
        } elseif($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT' || $_SERVER['HTTP_X_HTTP_METHOD'] == 'PATCH') {
            $method = 'PUT';//replace
        } else {
            exitWithError('Unexpected Header', 400);
        }
    }
    if($method == 'PATCH'){
        $method = 'PUT';
    }
}

//allowed entities for entityScrud
$entities = array(

'rtg'=>'DefRecTypeGroups',
'dtg'=>'DefDetailTypeGroups',
'vcg'=>'DefVocabularyGroups',
'rty'=>'DefRecTypes',
'dty'=>'DefDetailTypes',
'trm'=>'DefTerms',
'trl'=>'DefTermsLinks', 'termlinks'=>'DefTermsLinks',
'rst'=>'DefRecStructure',
'recstructure'=>'DefRecStructure',
'rem'=>'UsrReminders',
'swf'=>'SysWorkflowRules',
'tag'=>'UsrTags',

'fieldgroups'=>'DefDetailTypeGroups',
'rectypegroups'=>'DefRecTypeGroups',
'rectypes'=>'DefRecTypes',
'fields'=>'DefDetailTypes',
'terms'=>'DefTerms',
'reminders'=>'DbUsrReminders',

'dbs'=>'SysDatabases', 'databases'=>'SysDatabases',
'users'=>'SysUsers',
'groups'=>'SysGroups',
'records'=>'Records', //only search allowed
'login'=>'System',
'logout'=>'System',

'annotations'=>'Annotations', //for iiif annotation server
'iiif'=>'iiif', //for iiif presenatation v3 (only GET allowed)
);
//records
    //controlles:
    //record_batch - batch actions for records
    //record_search
    //record_edit

//auth
    //usr_info

// http://127.0.0.1/heurist/hserv/controller/entityScrud.php?db=osmak_9a&entity=rst&a=search&details=structure&rst_ID=12
// http://127.0.0.1/heurist/api/osmak_1/rst/12
// http://127.0.0.1/heurist/api/osmak_9a/rem/1
// http://127.0.0.1/heurist/api/osmak_9a/tag?rtl_RecID=9

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = explode('/', trim($path, '/'));

if(@$requestUri[0]=='api'){
   array_unshift($requestUri,'heurist');
}

//$requestUri[1] = "api"
//$requestUri[2] - database name
//$requestUri[3] - resource(entity )
//$requestUri[4] - selector - id or name



$raw_body = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

$req_params = USanitize::sanitizeInputArray();

$json = null;
$json_error = null;

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ||
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'PUT' ||
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'PATCH'
) {
    if (stripos($contentType, 'application/json') === 0) {
        $json = json_decode($raw_body, true);
        $json_error = json_last_error_msg();

        if (is_array($json)) {
            if (!isset($req_params['fields']) || !is_array($req_params['fields'])) {
                $req_params['fields'] = $json;
            }

            foreach ($json as $k => $v) {
                if (!array_key_exists($k, $req_params)) {
                    $req_params[$k] = $v;
                }
            }
        }
    }
}
/*
echo "<pre>";
echo "REQUEST_METHOD:\n";
var_dump($_SERVER['REQUEST_METHOD'] ?? null);

echo "\nCONTENT_TYPE:\n";
var_dump($contentType);

echo "\nRAW BODY LENGTH:\n";
var_dump(strlen($raw_body));

echo "\nRAW BODY:\n";
var_dump($raw_body);

echo "\nJSON DECODED:\n";
var_dump($json);

echo "\nJSON ERROR:\n";
var_dump($json_error);

echo "\nREQ_PARAMS:\n";
var_dump($req_params);
echo "</pre>";
exit;
*/

if(@$requestUri[1]!== 'api' || @$req_params['ent']!=null){
    //takes all parameters from $req_params

    //try to detect entity as parameter
    if(@$entities[$req_params['ent']] != null ){
        $requestUri = array(0, 'api', $req_params['db'], $req_params['ent'], @$req_params['id']);
    }else{
        exitWithError('API route not found', 404);
    }

}
elseif(@$req_params['db'] && @$requestUri[2]!=null){ //backward when database is parameter

    if(@$entities[$requestUri[2]]!=null){
        $requestUri = array(0, 'api', $req_params['db'], $requestUri[2], @$requestUri[3]);
    }else{
        exitWithError('API route not found', 404);
    }

}elseif(@$requestUri[2]==='dbs' || @$requestUri[2]==='databases'){
    $req_params['db'] = null;    
    if(@$requestUri[3]!=='dbs'){
        array_splice( $requestUri, 3, 0, 'dbs' );
    }
}elseif(@$requestUri[2]!=null){
    $req_params['db'] = $requestUri[2];
}

// Database listing is a server-level resource and never belongs to the
// database segment in the URL. Treat both /api/dbs and /api/{db}/dbs
// (and their /databases aliases) identically.
if(in_array(@$requestUri[3], array('dbs', 'databases'), true)){
    $req_params['db'] = null;
}

$allowed_methods = array('search','add','save','delete');

// Preserve the normalised HTTP verb for route-specific method checks.
$http_method = strtoupper((string)$method);
$method = getAction($method);
if($method == null || !in_array($method, $allowed_methods)){
    exitWithError('Method not allowed', 405, array('Allow' => 'GET, POST, PUT, PATCH, DELETE'));
}

if($method=='save' || $method=='add'){
    /*get request body
    if(!@$req_params['fields']){
        $data = json_decode(file_get_contents('php://input'), true);
        if($data){
            //request body
            $req_params['fields'] = $data;
        }else{
            $req_params['fields'] = $req_params;
        }
    }
    if(@$req_params['fields']['db']){ //may contain db
        $req_params['db'] = $req_params['fields']['db'];
        unset($req_params['fields']['db']);
    }*/
}else{

    $params['limit'] = isset($params['limit']) ? intval($params['limit']) : 1000;
    if($params['limit'] < 1 || $params['limit'] > 5000){
        $params['limit'] = 5000;
    }

}

// ----------------------------------------------------
// Resolve auth for API requests
// ----------------------------------------------------
$resource = @$requestUri[3];

// Map and timeline routes have method rules that differ from generic entities.
// Presentation definitions and file-backed datasource output are GET-only.
// POST is accepted only for ordinary map record/query searches and timeline searches.
$is_map_record_query = ($resource === 'map'
    && !in_array(@$requestUri[4], array('document', 'layer', 'data'), true));
$is_timeline_query = ($resource === 'time');

if($resource === 'map'){
    if(in_array(@$requestUri[4], array('document', 'layer', 'data'), true)){
        if($http_method !== 'GET'){
            exitWithError('Method not allowed', 405, array('Allow' => 'GET'));
        }
    }elseif(!in_array($http_method, array('GET', 'POST'), true)){
        exitWithError('Method not allowed', 405, array('Allow' => 'GET, POST'));
    }
}elseif($resource === 'time' && !in_array($http_method, array('GET', 'POST'), true)){
    exitWithError('Method not allowed', 405, array('Allow' => 'GET, POST'));
}

if(($is_map_record_query || $is_timeline_query) && $http_method === 'POST'){
    $method = 'search';
}

// The modern records collection search is read-only for both GET and POST.
$is_record_query = ($resource === 'records' && !isset($requestUri[4]));
if($is_record_query){
    if(!in_array($http_method, array('GET','POST'), true)){
        exitWithError('Method not allowed', 405, array('Allow' => 'GET, POST'));
    }
    if($http_method === 'POST'
        && stripos($contentType, 'application/json') === 0
        && !is_array($json)){
        exitWithError('Invalid JSON request body', 400);
    }
    if($http_method === 'POST' && is_array($json)
        && !isset($req_params['query']) && !isset($req_params['q']) && !isset($req_params['ids'])){
        $contractKeys = array('rules','limit','offset','fields','detail');
        $isList = empty($json) || array_keys($json) === range(0, count($json)-1);
        if($isList || empty(array_intersect(array_keys($json), $contractKeys))){
            $req_params['query'] = $json;
        }
    }
    if($http_method === 'POST' && is_array($json)){
        if(array_key_exists('fields', $json)){
            $req_params['fields'] = $json['fields'];
        }else{
            // api.php's generic JSON handler temporarily places the whole body
            // in fields; that is not the records search output-field option.
            unset($req_params['fields']);
        }
    }
    $method = 'search';
}

// POST /records/details is also a read-only operation.
if($resource === 'records'
    && @$requestUri[4] === 'details'
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'){
    $method = 'search';
}

// Routes where auth processing is not needed here
$is_public_annotation_read =
    ($resource === 'annotations'
        && $method === 'search'
        && (
            // Canvas AnnotationPage, used by Mirador:
            //   /api/{db}/annotations/pages?uri={canvasUri}
            in_array(@$requestUri[4], array('pages', 'page'), true)

            // Single annotation record:
            //   /api/{db}/annotations/{annotationId}
            || (isset($requestUri[4]) && is_numeric($requestUri[4]) && !isset($requestUri[5]))

            // Manifest-scoped Canvas AnnotationPage:
            //   /api/{db}/annotations/{manifestRecID}/pages?uri={canvasUri}
            || (isset($requestUri[4]) && is_numeric($requestUri[4])
                && in_array(@$requestUri[5], array('pages', 'page'), true))

            // Manifest-scoped single annotation:
            //   /api/{db}/annotations/{manifestRecID}/{annotationId}
            || (isset($requestUri[4]) && is_numeric($requestUri[4])
                && isset($requestUri[5]) && is_numeric($requestUri[5]))
        ));

$skip_auth_processing =
    ($resource === 'login') ||
    ($resource === 'logout') ||
    $is_public_annotation_read ||
    ($resource === 'iiif') ||
    in_array($resource, ['dbs','databases'], true);

// Routes that may be used anonymously, but should still use
// current session/JWT if provided
$allow_anonymous = false;
if($method === 'search'){
    $publicSearchResources = array(
        'records', 'groups', 'users', 'dbs', 'databases',
        'rty', 'rectypes',
        'dty', 'fields',
        'trm', 'terms', 'trl', 'termlinks',
        'rst', 'recstructure',
        'map', 'time'
    );
    $allow_anonymous = in_array($resource, $publicSearchResources, true);    
}

if(!$skip_auth_processing){
    $system = new hserv\System();

    if(!$system->init(@$req_params['db'])){
        $system->errorExitApi(); // exits
    }

    // Preserve existing cookie-backed user if present.
    // Otherwise try Bearer token auth.
    if(!$system->getUserId()){
        authenticateApiRequestWithJwt($system);
    }

    // Only require authentication for protected routes.
    if(!$allow_anonymous && !$system->getUserId()){
        exitWithError('Unauthorized', 401, [
            'WWW-Authenticate' => 'Bearer realm=\"api\"'
        ]);
    }
}
// ----------------------------------------------------

if(in_array(@$requestUri[3], array('map', 'time'), true)) {

    $req_params['restapi'] = 1;

    if($requestUri[3]==='time'){
        $controller = new MapDataController($system, $req_params);
        $controller->outputRecordGeoJson(null, true);

    }elseif(in_array(@$requestUri[4], array('document', 'layer'), true)){

        $controller = new MapPresentationController($system, $req_params);
        $controller->handleRequest((string)$requestUri[4], intval(@$requestUri[5]));

    }elseif(@$requestUri[4] === 'data'){
        // File-backed datasource: KML/KMZ/CSV/TSV/GeoJSON/SHP.
        // GET /api/{db}/map/data/{recID}?format=geojson|rawfile|source
        $controller = new MapDataController($system, $req_params);
        $controller->outputDataSource(intval(@$requestUri[5]));

    }else{
        // Ordinary Heurist record/query GeoJSON.
        $controller = new MapDataController($system, $req_params);
        $recordId = isset($requestUri[4]) && is_numeric($requestUri[4])
            ? intval($requestUri[4])
            : null;
        $controller->outputRecordGeoJson($recordId, false);
    }

}elseif (@$requestUri[3]=='iiif') {
    
    // IIIF Presentation API routes, for example:
    //   /api/{db}/iiif/manifest/{manifestRecID}
    //   /api/{db}/iiif/manifest/{ulf_ObfuscatedFileID}
    //   /api/{db}/iiif/canvas/{ulf_ObfuscatedFileID}
    //   /api/{db}/iiif/canvas/{canvasRecID}       // alias; output id is canonical
    //   /api/{db}/iiif/annotations/{ulf_ObfuscatedFileID}
    //
    // Do not confuse /iiif/annotations/{fileObfuscatedId}, which is a linked
    // IIIF AnnotationPage for a Canvas/file, with /annotations/{annotationRecID},
    // which is the annotation-record REST API handled below by DbAnnotations.

    if($method=='search'){ //GET method
        $req_params['resource'] = @$requestUri[4];
        $req_params['id'] = @$requestUri[5];
        $req_params['restapi'] = 1; //set http response code

        include_once '../../hserv/controller/iiif_presentation.php';
    }else{
        exitWithError('Method not allowed', 405, array('Allow' => 'GET'));
    }

}elseif (@$entities[@$requestUri[3]]=='System') {
    //login and logout actions

    $system = new hserv\System();
    if( ! $system->init($req_params['db']) ){
        //get error and response
        $system->errorExitApi();//exit from script
    }

    if($requestUri[3]==='login'){
        if(!$system->doLogin(filter_var($req_params['fields']['login']??$req_params['login']??null, FILTER_SANITIZE_STRING),
                             $req_params['fields']['password']??$req_params['password']??null, 'shared'))
        {
            $system->errorExitApi();
        }else{
            $lifetime = time() + 24*60*60;     //day
            USystem::sessionUpdateCookies($lifetime);
        }

    }elseif($requestUri[3]==='logout'){
        $system->doLogout();
    }

    $system->dbclose();
}
else
{
    //action
    $requestedResource = @$requestUri[3];
    $req_params['entity'] = @$entities[$requestedResource];
    $req_params['a'] = $method;
    $req_params['restapi'] = 1; //set http response code

    // Modern public response format is initially limited to the four
    // definition resources. Explicit API response context tells the shared
    // entity search layer to retain its full internal result for formatting
    // at the entityScrud.php controller boundary.
    $definitionResources = array(
        'dbs' => 'databases',
        'databases' => 'databases',
        'rty' => 'rectypes',
        'rectypes' => 'rectypes',
        'dty' => 'fields',
        'fields' => 'fields',
        'trm' => 'terms',
        'terms' => 'terms',
        'trl' => 'termlinks',
        'termlinks' => 'termlinks',
        'rst' => 'recstructure',
        'recstructure' => 'recstructure'
    );

    if($method === 'search' && isset($definitionResources[$requestedResource])){
        $apiResponseContext = array(
            'mode' => 'collection',
            'entity' => $definitionResources[$requestedResource]
        );

        if($apiResponseContext['entity'] === 'recstructure'){
            // /recstructure/{recordTypeId} returns all fields for a record type.
            // /recstructure/{recordTypeId}/{detailTypeId} returns one field.
            if(isset($requestUri[4]) && $requestUri[4] !== ''){
                $req_params['rst_RecTypeID'] = $requestUri[4];
                $apiResponseContext['recordTypeId'] = intval($requestUri[4]);
            }
            if(isset($requestUri[5]) && $requestUri[5] !== ''){
                $req_params['rst_DetailTypeID'] = $requestUri[5];
                $apiResponseContext['detailTypeId'] = intval($requestUri[5]);
                $apiResponseContext['mode'] = 'item';
            }
            if(!isset($req_params['details'])){
                $req_params['details'] = 'structure';
            }
        }elseif(isset($requestUri[4]) && $requestUri[4] !== ''){
            $primaryFields = array(
                'rectypes' => 'rty_ID',
                'fields' => 'dty_ID',
                'terms' => 'trm_ID',
                'databases' => 'sys_Database'
            );
            $req_params[$primaryFields[$apiResponseContext['entity']]] = $requestUri[4];
            $apiResponseContext['mode'] = 'item';
        }

        $req_params['api_response_context'] = $apiResponseContext;
    }

    // Public term-link filters use concise API parameter names.
    if(in_array($requestedResource, array('trl', 'termlinks'), true)){
        if(isset($req_params['parentId'])){
            $req_params['trl_ParentID'] = $req_params['parentId'];
        }
        if(isset($req_params['termId'])){
            $req_params['trl_TermID'] = $req_params['termId'];
        }
    }

    if(@$requestUri[3]=='annotations'){
        // Supported annotation API paths:
        //   /api/{db}/annotations/pages?uri={canvasUri}
        //   /api/{db}/annotations/{annotationId}
        //   /api/{db}/annotations/{manifestRecID}/pages?uri={canvasUri}
        //   /api/{db}/annotations/{manifestRecID}/{annotationId}
        if(isset($requestUri[5])){
            if($method!=='search'){ //temporary remarked - to show all annotations per canvas
                $req_params['manifestRecID'] = intval($requestUri[4]);    
            }
            $req_params['recID'] = $requestUri[5];
        }elseif(isset($requestUri[4])){
            // A single path segment after /annotations is the annotation id,
            // not a Manifest id. Manifest-scoped routes always have two
            // segments: /annotations/{manifestRecID}/pages or
            // /annotations/{manifestRecID}/{annotationId}.
            $req_params['recID'] = $requestUri[4];
        }

        if(intval(@$req_params['manifestRecID'])>0){
            if(!isset($req_params['fields']) || !is_array($req_params['fields'])){
                $req_params['fields'] = array();
            }
            $req_params['fields']['manifestRecID'] = intval($req_params['manifestRecID']);
        }

        if($method=='add' || $method=='save'){
            if(!isset($req_params['fields']) || !is_array($req_params['fields'])){
                $req_params['fields'] = array();
            }
            // This API route is used by the Mirador annotation adapter.
            // DbAnnotations decides the actual state from this source marker.
            if(!isset($req_params['fields']['source'])){
                $req_params['fields']['source'] = 'mirador';
            }
        }
    }elseif(@$requestUri[4]!=null && !isset($definitionResources[$requestedResource])
        && !($requestedResource === 'records' && $requestUri[4] === 'details')){
      $req_params['recID'] = $requestUri[4];
    }

    if($req_params['entity']=='Records'){
        $isRecordDetailsRequest = (@$requestUri[4] === 'details');
        $isRecordQueryRequest = !isset($requestUri[4]);

        if($isRecordQueryRequest){
            // POST /records uses a read contract, not the generic entity-write
            // fields object. Restore its explicit query parameters from JSON.
            if(is_array($json)){
                foreach(array('query','q','ids','fields','detail','rules','limit','offset') as $key){
                    if(array_key_exists($key, $json)){ $req_params[$key] = $json[$key]; }
                }
            }
            $controller = new RecordQueryController($system);
            $controller->output($req_params);
            $system->dbclose();
            exit;
        }

        if($isRecordDetailsRequest && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
            exitWithError('Method not allowed', 405, array('Allow' => 'POST'));
        }

        if($method=='search'){
            // Public records API always returns the stable JSON representation.
            // Export-specific options remain available to direct/internal
            // record_output.php callers but are not part of this API contract.
            $req_params['format'] = 'json';
            unset($req_params['fmt'], $req_params['extended'], $req_params['depth'], $req_params['linkmode']);

            if($isRecordDetailsRequest){
                // The generic JSON-body handler uses "fields" for entity writes.
                // Restore the explicit records/details body fields here.
                if(is_array($json)){
                    if(array_key_exists('ids', $json)){
                        $req_params['ids'] = $json['ids'];
                    }
                    if(array_key_exists('fields', $json)){
                        $req_params['fields'] = $json['fields'];
                    }
                }
            }

            $apiResponseContext = array(
                'entity' => 'records',
                'mode' => $isRecordDetailsRequest
                    ? 'details'
                    : (isset($requestUri[4]) && $requestUri[4] !== '' ? 'item' : 'collection')
            );
            include_once '../../hserv/controller/record_output.php';
        }else{
            exitWithError('Method not allowed', 405, array('Allow' => $isRecordDetailsRequest ? 'POST' : 'GET'));
        }
    }else{
        include_once '../../hserv/controller/entityScrud.php';
    }
}
exit;

/**
 * ADDED:
 * Authenticate current API request from Authorization: Bearer <jwt>
 * and set current user into System.
 *
 * @param hserv\System $system
 * @return void
 */
function authenticateApiRequestWithJwt($system){

    global $jwt_Secret;

    if(!isset($jwt_Secret) || strlen($jwt_Secret) < 8){
        // JWT auth is not configured; just return without authenticating.
        return;
    }

    $auth = UJwt::get_auth_header();
    if(!$auth){
        return;
    }

    if(!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)){
        exitWithError('Invalid Authorization header', 401, [
            'WWW-Authenticate' => 'Bearer error="invalid_request"'
        ]);
    }

    $payload = UJwt::jwt_verify($m[1], $jwt_Secret);
    if($payload === false){
        exitWithError('Invalid token', 401, [
            'WWW-Authenticate' => 'Bearer error="invalid_token"'
        ]);
    }

    $userID = @$payload['sub'];
    if(!$userID){
        exitWithError('Invalid token payload', 401, [
            'WWW-Authenticate' => 'Bearer error="invalid_token"'
        ]);
    }

    // Optional scope enforcement:
    // $scope = $payload['scope'] ?? null;
    // if($scope !== 'read:data'){
    //     exitWithError('Insufficient scope', 403, [
    //         'WWW-Authenticate' => 'Bearer error="insufficient_scope"'
    //     ]);
    // }

    $system->setCurrentUser([
        'ugr_ID' => $userID,
        'ugr_Groups' => user_getWorkgroups($system->getMysqli(), $userID)
    ]);
}

/**
 * Outputs a JSON error message and exits the script.
 *
 * Sets the HTTP response code and content type, then prints a JSON
 * encoded error message before terminating the script.
 *
 * @param string $message The error message.
 * @param int $code The HTTP status code.
 * @param array $headers Optional response headers.
 * @return void
 */
function exitWithError($message, $code, $headers = array(), $error_code = null){

    if($error_code === null){
        if($code === 401 || $code === 403){
            $error_code = HEURIST_REQUEST_DENIED;
        }elseif($code === 404){
            $error_code = HEURIST_NOT_FOUND;
        }elseif($code === 409){
            $error_code = HEURIST_ACTION_BLOCKED;
        }elseif($code >= 500){
            $error_code = HEURIST_SYSTEM_FATAL;
        }else{
            $error_code = HEURIST_INVALID_REQUEST;
        }
    }

    header(HEADER_CORS_POLICY);
    header(CTYPE_JSON);

    foreach($headers as $k => $v){
        header("$k: $v");
    }

    http_response_code($code);
    print json_encode(array(
        'status' => $code,
        'error' => $error_code,
        'message' => $message
    ));
    exit;
}

/**
 * Converts an HTTP method to a corresponding action string.
 *
 * Maps HTTP methods (GET, POST, PUT, DELETE) to internal action
 * identifiers ('search', 'add', 'save', 'delete').
 *
 * @param string $method The HTTP method string.
 * @return string|null The corresponding action string, or null if the method is not recognized.
 */
function getAction($method){
    if($method=='GET'){
        return 'search';
    }elseif($method=='POST'){ // add new
        return 'add';
    }elseif($method=='PUT'){ // replace
        return 'save';
    }elseif($method=='DELETE'){
        return 'delete';
    }else{
        return null;
    }
}
?>
