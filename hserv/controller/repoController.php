<?php
/**
* Interface/Controller for external repository configuration and file manipulations
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
action
  
  list -  get list of available external repositories for given user - user_getRepositoryList 
    (need select available repositories on file upload )
    
  get - returns credentials for given service and current user - user_getRepositoryCredentials
    (1. returns values to edit on client side 2. returns parameters to create url or login to repository)
    
  update - save/delete credentials in ugr_Preferences - user_saveRepositoryCredentials 
  
  upload - upload and register file to external repository
  
*/
require_once dirname(__FILE__).'/../System.php';
require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';

$need_compress = false;

$system = new System();

if(!$system->init(@$_REQUEST['db'])){
    //get error and response
    $response = $system->getError();
}else{
     
   if(!$system->get_user_id()>0){
        $response = $system->addError(HEURIST_REQUEST_DENIED, 'You must be logged in');
        // 'Administrator permissions are required');
   }else{
       
        //for kml step2,step3,set_primary_rectype,step3
        $action = @$_REQUEST["a"];
        $res = false;        
        $ugr_ID = $system->get_user_id(); //intval(@$_REQUEST["ugr_ID"]);
        
        if($action=='list'){   
            //get list of available repositories for given user (including for database and groups)
            
            //array(ugr_ID, serviceName)
            $res = user_getRepositoryList($system, $ugr_ID, true);
            
        }else if($action=='get'){   
            //get credentials (to edit on client side) for given user

            $res = user_getRepositoryCredentials($system, true, $ugr_ID);
            
        }else if($action=='update'){   
            //save credentials
            $to_delete = @$_REQUEST["delete"];
            $to_edit = @$_REQUEST["edit"];
            
            $res = user_saveRepositoryCredentials($system, $to_edit, $to_delete);

        }else if($action=='upload'){   
            //upload and register file to external repository

            
        }else{
            $system->addError(HEURIST_INVALID_REQUEST, "Action parameter is missing or incorrect");                
            $res = false;
        }
        
        if(is_bool($res) && $res==false){
                $response = $system->getError();
        }else{
                $response = array("status"=>HEURIST_OK, "data"=> $res);
        }
   }
}
header('Content-type: text/javascript');
print json_encode($response);
?>