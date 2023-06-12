<?php
/*
Add to httpd.conf

RewriteEngine On
#if URI starts with api/ redirect it to controller/api.php
RewriteRule ^/heurist/api/(.*)$ /heurist/hsapi/controller/api.php

*/
  
$requestUri = explode('/', trim($_SERVER['REQUEST_URI'],'/'));


if(@$_REQUEST['method']){
    $method = $_REQUEST['method'];
}else{
    //get method  - GET POST PUT DELETE
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {  //add
        if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
            $method = 'DELETE';
        } else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT' || $_SERVER['HTTP_X_HTTP_METHOD'] == 'PATCH') {
            $method = 'PUT';   //replace
        } else {
            exitWithError('Unexpected Header', 400);
        }
    }
    if($method == 'PATCH'){
        $method = 'PUT';
    } 
}

//error_log($method);

//$requestUri[1] = "api"
//$requestUri[2] - database name
//$requestUri[3] - resource(entity )
//$requestUri[4] - selector - id or name

//allowed entities for entityScrud
$entities = array(
'fieldgroups'=>'DefDetailTypeGroups',
'fields'=>'DefDetailTypes',
'rectypegroups'=>'DefRecTypeGroups',
'rectypes'=>'DefRecTypes',
'reminders'=>'DbUsrReminders',
'users'=>'SysUsers',
'groups'=>'SysGroups',
'records'=>'Records', //only search allowed
'login'=>'System',
'logout'=>'System',

'rem'=>'UsrReminders',
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

//echo print_r($requestUri,true);
//echo '<br>'.$method;
// hsapi/controller/api.php?ent=

if(count($requestUri)>0){
    $params = array();
    foreach($requestUri as $prm){
        $k = strpos($prm, '?');
        if($k>0){
            $params[] = substr($prm,0,$k);
            break;
        }
        $params[] = $prm;
    }
    $requestUri = $params;
}

if(@$requestUri[1]!== 'api' || @$_REQUEST['ent']!=null){ 
    //takes all parameters from $_REQUEST

    //try to detect entity as parameter
    if(@$entities[$_REQUEST['ent']] != null ){
        $requestUri = array(0, 'api', $_REQUEST['db'], $_REQUEST['ent'], @$_REQUEST['id']);
    }else{
        exitWithError('API Not Found', 400);    
    }
    
}else if(@$_REQUEST['db'] && @$requestUri[2]!=null){ //backward when database is parameter
    
    if(@$entities[$requestUri[2]]!=null){
        $requestUri = array(0, 'api', $_REQUEST['db'], $requestUri[2], @$requestUri[3]);
    }else{
        exitWithError('API Not Found', 400);    
    }
    
}else if(@$requestUri[2]!=null){
    $_REQUEST['db'] = $requestUri[2];    
}


$allowed_methods = array('search','add','save','delete');

$method = getAction($method);
if($method == null || !in_array($method, $allowed_methods)){
    exitWithError('Method Not Allowed', 405);
}

if($method=='save' || $method=='add'){
    //get request body
    if(!@$_REQUEST['fields']){
        $data = json_decode(file_get_contents('php://input'), true);
        if($data){
            //DEBUG error_log(print_r($data,true));    
            //request body
            $_REQUEST['fields'] = $data;
        }else{
            $_REQUEST['fields'] = $_REQUEST;
        }
    }
    if(@$_REQUEST['fields']['db']){ //may contain db
        $_REQUEST['db'] = $_REQUEST['fields']['db'];
        unset($_REQUEST['fields']['db']);
    }
}else{
    
    if(@$_REQUEST['limit']==null || $_REQUEST['limit']>100 || $_REQUEST['limit']<1){
        $_REQUEST['limit']=100;
    }
    
}

// throw new RuntimeException('Unauthorized - authentication failed', 401);
if (@$requestUri[3]=='iiif') {

    if($method=='search'){
        $_REQUEST['resource'] = @$requestUri[4];
        $_REQUEST['id'] = @$requestUri[5];
        $_REQUEST['restapi'] = 1; //set http response code
        
        include '../../hsapi/controller/iiif_presentation.php';
    }else{
        exitWithError('Method Not Allowed', 405);
    }
    
    
}else if ($entities[@$requestUri[3]]=='System') {
    
    include '../System.php';
    
    $system = new System();
    if( ! $system->init($_REQUEST['db']) ){
        //get error and response
        $system->error_exit_api(); //exit from script
    }
    
    if($requestUri[3]==='login'){
        
        if(!$system->doLogin(@$_REQUEST['fields']['login'], @$_REQUEST['fields']['password'], 'shared'))
        {
            $system->error_exit_api();
        }else{
                    $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');
                    $session_id = session_id();
                    $time = time() + 24*60*60;     //day
                    $cres = setcookie('heurist-sessionid', $session_id, $time, '/', '', $is_https );
        }
        
    }else if($requestUri[3]==='logout'){
        $system->doLogout();
    }
    
    $system->dbclose();
}
else
{
    //action
    $_REQUEST['entity'] = $entities[@$requestUri[3]];
    $_REQUEST['a'] = $method;
    $_REQUEST['restapi'] = 1; //set http response code

    if(@$requestUri[4]!=null){
      $_REQUEST['recID'] = $requestUri[4];  
    } 

    if($_REQUEST['entity']=='Records'){
        if($method=='search'){
            include '../../hsapi/controller/record_output.php';
        }else{
            exitWithError('Method Not Allowed', 405);
        }
    }else{
        include '../../hsapi/controller/entityScrud.php';
    }
}
exit();
//header("HTTP/1.1 " . $status . " " . $this->requestStatus($status));
//echo json_encode($data); 

function exitWithError($message, $code){
    
    header("Access-Control-Allow-Origin: *");
    header('Content-type: application/json;charset=UTF-8'); //'text/javascript');
    
    http_response_code($code);    
    print json_encode(array("status"=>'invalid', "message"=>$message));
    exit();
}

function getAction($method){
    if($method=='GET'){
        return 'search';
    }else if($method=='POST'){ //add new 
        return 'add';
    }else if($method=='PUT'){ //replace
        return 'save';
    }else if($method=='DELETE'){
        return 'delete';
    }else{
        return $method;
    }       
    return null;
}
?>
