<?php
/**
* indexController.php
* Interface/Controller for requests to Heurist_Reference_Index database
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
action

info
register
update
delete  
*/

require_once dirname(__FILE__).'/../System.php';
require_once dirname(__FILE__).'/../utilities/dbRegis.php';

    $isOutSideRequest = (strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===false);//this is NOT reference server

    $res = false;

    $action = @$_REQUEST['action'];
    
    $allow_action = true;

    $system = new System();//global system
    
    //if db parameter is defined this is initial request
    //1. checks permission - must be dbowner or sysadmin password provided
    //2. adds dbowner credentials to request
    if(@$_REQUEST['db'] && $action!='url'){
        
        $allow_action = false;
        if($system->init($_REQUEST['db'])){

            if($system->is_dbowner()){
                $allow_action = true;         
            }else{
                //sysadmin protection
                $allow_action = !$system->verifyActionPassword(@$_REQUEST['pwd'], $passwordForServerFunctions);
            }
            
            if($allow_action){                
                //get database owner credentials
                $dbowner = user_getByField($system->get_mysqli(), 'ugr_ID', 2);
                
                $_REQUEST['usrPassword'] = $dbowner['ugr_Password'];
                $_REQUEST['usrEmail']    = $dbowner['ugr_eMail'];
                
                if($action=='register'){
                    $_REQUEST['usrName']      = $dbowner['ugr_Name'];
                    $_REQUEST['usrFirstName'] = $dbowner['ugr_FirstName'];
                    $_REQUEST['usrLastName']  = $dbowner['ugr_LastName'];
                }
                
            }else{
                $system->addError(HEURIST_REQUEST_DENIED, 
                            'To perform this action you must be logged in as Database Owner');
            }
        }
    }
    
    if($allow_action){
        if($action=='info'){
            $res = DbRegis::registrationGet($_REQUEST);
        }else if($action=='register'){
            $res = DbRegis::registrationAdd($_REQUEST);//returns ID or false
        }else if($action=='update'){
            $res = DbRegis::registrationUpdate($_REQUEST);
        }else if($action=='delete'){
            $res = DbRegis::registrationDelete($_REQUEST);//returns ID or false
        }else{
            $system->addError(HEURIST_INVALID_REQUEST, 'Action parameter is missing or incorrect');
        }            
    }

if(is_bool($res) && $res==false){
    $response = $system->getError();
}else{
    $response = array("status"=>HEURIST_OK, "data"=> $res);
}

header('Content-type: text/javascript');
print json_encode($response);
?>                  