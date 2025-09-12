<?php
require_once dirname(__FILE__).'/../../autoload.php';

use hserv\utilities\UJwt;


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  UJwt::json_out(405, ['error'=>'method_not_allowed']);
}

if(!isset($jwt_Secret) || strlen($jwt_Secret)<8){
  UJwt::json_out(405, ['error'=>'method_not_allowed']);
}

$contentType = $_SERVER['CONTENT_TYPE'];
$raw = file_get_contents('php://input');

echo $contentType.' ';

if(false && $contentType=='application/x-www-form-urlencoded'){
    $in = [];
    parse_str($raw, $in); 
}else{
    $in  = json_decode($raw, true);    
}


$username = $in['username'] ?? null;
$password = $in['password'] ?? null;
$database = $in['db'] ?? null;

$system = new hserv\System();
if( !isset($database) || !$system->init($database) ){
    //$system->errorExitApi();
    UJwt::json_out(400, ['error'=>'invalid_database_param'], ['WWW-Authenticate' => 'Basic realm="api", charset="UTF-8"']);
}

if (!$username || !$password || !$system->doLogin($username, $password, 'none')){
  UJwt::json_out(401, ['error'=>'invalid_credentials'], ['WWW-Authenticate' => 'Basic realm="api", charset="UTF-8"']);
}

$now = time();
$claims = [
  'sub' => $system->getUserId(),  //$username
  'iat' => $now,
  'nbf' => $now,
  'exp' => $now + $jwt_TTL??600,   //10 minutes
  'scope' => 'read:data' // example
];

$token = UJwt::jwt_create($claims, $jwt_Secret);

UJwt::json_out(200, [
  'access_token' => $token,
  'token_type'   => 'Bearer',
  'expires_in'   => $jwt_TTL??600
]);
