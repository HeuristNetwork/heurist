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
'rst'=>'DefRecStructure',
'rem'=>'UsrReminders',
'swf'=>'SysWorkflowRules',
'tag'=>'UsrTags',

'fieldgroups'=>'DefDetailTypeGroups',
'rectypegroups'=>'DefRecTypeGroups',
'rectypes'=>'DefRecTypes',
'fields'=>'DefDetailTypes',
'terms'=>'DefTerms',
'reminders'=>'DbUsrReminders',

'dbs'=>'SysDatabases',
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
        exitWithError('API Not Found', 400);
    }

}
elseif(@$req_params['db'] && @$requestUri[2]!=null){ //backward when database is parameter

    if(@$entities[$requestUri[2]]!=null){
        $requestUri = array(0, 'api', $req_params['db'], $requestUri[2], @$requestUri[3]);
    }else{
        exitWithError('API Not Found', 400);
    }

}elseif(@$requestUri[2]!=null){
    $req_params['db'] = $requestUri[2];
}

$allowed_methods = array('search','add','save','delete');

$method = getAction($method);
if($method == null || !in_array($method, $allowed_methods)){
    exitWithError('Method Not Allowed CCC', 405);
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

    if(@$req_params['limit']==null || $req_params['limit']>1000 || $req_params['limit']<1){
        $req_params['limit']=1000;
    }

}

// ----------------------------------------------------
// Resolve auth for API requests
// ----------------------------------------------------
$resource = @$requestUri[3];

// Routes where auth processing is not needed here
$skip_auth_processing =
    ($resource === 'login') ||
    ($resource === 'logout') ||
    ($resource === 'iiif');

// Routes that may be used anonymously, but should still use
// current session/JWT if provided
$allow_anonymous = false;
if($method === 'search'){
    $allow_anonymous = ($resource === 'records' || $resource === 'groups' || $resource === 'users');
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

if (@$requestUri[3]=='iiif') {
    
    // https://server/heurist/api/mydb/iiif/annotations/<ulf_obfuscated_id>
    
    // http://127.0.0.1/heurist/?db=osmak_annot&file=662a913ee25012ba445b5b96d6eea2f9537ca6a4
    // http://127.0.0.1/heurist/api/osmak_annot/iiif/annotations/662a913ee25012ba445b5b96d6eea2f9537ca6a4

    if($method=='search'){ //GET method
        $req_params['resource'] = @$requestUri[4];
        $req_params['id'] = @$requestUri[5];
        $req_params['restapi'] = 1; //set http response code

        include_once '../../hserv/controller/iiif_presentation.php';
    }else{
        exitWithError('Method Not Allowed DDD', 405);
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
    $req_params['entity'] = @$entities[@$requestUri[3]];
    $req_params['a'] = $method;
    $req_params['restapi'] = 1; //set http response code

    if(@$requestUri[4]!=null){
      $req_params['recID'] = $requestUri[4];
    }

    if($req_params['entity']=='Records'){
        if($method=='search'){
            include_once '../../hserv/controller/record_output.php';
        }else{
            exitWithError('Method Not Implemented', 405);
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
function exitWithError($message, $code, $headers = array()){

    header(HEADER_CORS_POLICY);
    header(CTYPE_JSON);

    foreach($headers as $k => $v){
        header("$k: $v");
    }

    http_response_code($code);
    print json_encode(array("status"=>'invalid', "message"=>$message));
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