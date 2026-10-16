<?php
/**
* indexController.php - Controller for requests to Heurist_Reference_Index database
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       6.0
* 
* @todo - convert to class, use FronController to init
*/
use hserv\utilities\DbRegis;
use hserv\utilities\USanitize;
use hserv\System;

require_once dirname(__FILE__).'/../../autoload.php';

    $isOutSideRequest = (strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===false);//this is NOT reference server

    $sysadmin_pwd = USanitize::getAdminPwd('pwd');
    $req_params = USanitize::sanitizeInputArray();

    $res = false;
    
    $error = null;

    $action = @$req_params['action'];

    $allow_action = true;
    $protected_actions = array('register', 'update', 'delete');

    $system = new System();//global system
    
    if(@$req_params['db'] && strpos($req_params['db'],'-')>0){
        
        $system->addError(HEURIST_ACTION_BLOCKED,
            'The registration database on remote host is disabled');
        $allow_action = false;
        
    }elseif(@$req_params['db'] && in_array($action, $protected_actions, true)){
    
        //if db parameter is defined this is initial request
        //1. checks permission - must be dbowner or sysadmin password provided
        //2. adds dbowner credentials to request
    

        $allow_action = false;
        if($system->init($req_params['db'])){

            if($system->isDbOwner()){
                $allow_action = true;
            }else{
                //sysadmin protection
                $allow_action = !$system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions);
            }

            if($allow_action){
                //get database owner credentials
                $dbowner = user_getByField($system->getMysqli(), 'ugr_ID', 2);

                $req_params['usrPassword'] = $dbowner['ugr_Password'];
                $req_params['usrEmail']    = $dbowner['ugr_eMail'];

                if($action=='register'){
                    $req_params['usrName']      = $dbowner['ugr_Name'];
                    $req_params['usrFirstName'] = $dbowner['ugr_FirstName'];
                    $req_params['usrLastName']  = $dbowner['ugr_LastName'];
                }

            }else{
                $system->addError(HEURIST_REQUEST_DENIED,
                            'To perform this action you must be logged in as Database Owner');
            }
        }
    }else{
        $system->initPathConstants();
    }

    if($allow_action){
        
        try {
            if($action=='resolve'){

                // Full registered database URL resolution.
                //
                // Used by RecordResolver. Given a registered database ID, this performs
                // the full DbRegis lookup chain:
                // 1) local URL cache
                // 2) local _INDEX_OF_REGISTERED_DATABASES
                // 3) central Reference Index server lookup
                // 4) destination server local ID lookup
                //
                // Returns the current database URL, for example:
                // https://example.org/heurist/?db=my_database
                //$res = DbRegis::registrationGet($req_params);
                
                $res = DbRegis::registrationGet($req_params);
                
            }elseif($action=='resolve_local'){
                // Destination-server local lookup only.
                //
                // Used internally by DbRegis::registrationGet() after the central
                // Reference Index identifies the likely server for a registered DB ID.
                // This does not query the central index. It checks this server's
                // _INDEX_OF_REGISTERED_DATABASES and returns the current database URL.
                $res = DbRegis::getDatabaseUrlLocal($req_params);

            }elseif($action=='central_index_lookup'){ 
                
                // Central Reference Index lookup only.
                //
                // Given a registered DB ID, return the registered server/base URL from
                // Heurist_Reference_Index. This value identifies the server to ask next;
                // it should not be treated as the final database URL.        
                $res = DbRegis::registrationGetFromCentralIndexDb($req_params);
                
            }elseif($action=='register'){
                $res = DbRegis::registrationAdd($req_params);//returns ID or false
            }elseif($action=='update'){
                $res = DbRegis::registrationUpdate($req_params);
            }elseif($action=='delete'){
                $res = DbRegis::registrationDelete($req_params);//returns ID or false
            }else{
                $error = [
                    'status' => HEURIST_INVALID_REQUEST,
                    'message' => 'Action parameter is missing or incorrect'
                ];
            }
            
            if(!$res && $error===null){
                $error = DbRegis::getLastError();
            }
            
        } catch (Throwable $e) {
            $error = [
                'status' => HEURIST_SYSTEM_FATAL,
                'message' => $e->getMessage()
            ];
        }            
    }

if(is_bool($res) && $res===false){
    if($error){
        $response = $error;
    }else{
        $response = $system->getError();    
    }
    if(!is_array($response)){
        $response = array('status'=>HEURIST_SYSTEM_FATAL, 'message'=>'Unknown registration error');
    }
    if(!array_key_exists('status', $response) || $response['status']==HEURIST_OK){
        $response['status'] = HEURIST_SYSTEM_FATAL;
    }
    if(!array_key_exists('message', $response) && array_key_exists(1, $response)){
        $response['message'] = $response[1];
    }
    if(!array_key_exists('code', $response)){
        $response['code'] = 'REGISTRATION_ERROR';
    }
}else{
    $response = array("status"=>HEURIST_OK, "data"=> $res);
}

header(CTYPE_JSON);
print json_encode($response);
?>