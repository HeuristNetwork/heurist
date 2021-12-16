<?php

/**
* Controller for operations with record type title mask
* See titleMask.php
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Jan Jaap de Groot  <jjedegroot@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

if(count($_REQUEST)>900){
    error_log('TOO MANY _REQUEST PARAMS '.count($_REQUEST).' record_titlemask');
    error_log(print_r(array_slice($_REQUEST, 0, 100),true));
}    


require_once (dirname(__FILE__).'/../System.php');        
require_once (dirname(__FILE__).'/../utilities/titleMask.php');

/*
parameters

rty_id - record type id to check
mask - title mask, if not defined we get current mask if check=0
rec_id - execute mask for this record 

check 0 - execute for given record id
      1 - validate mask
      2 - get coded mask
      3-  get human readable

*/

// Initialize a System object that uses the requested database
$system = new System();
if( $system->init(@$_REQUEST['db']) ){
            
            $rectypeID = @$_REQUEST['rty_id'];
            $mask = @$_REQUEST['mask'];
            $check_mode = @$_REQUEST["check"];
            
            $invalid_mask = null;
            $response = null;

            if($check_mode==2){ //get coded mask
                
                $res = TitleMask::execute($mask, $rectypeID, 1, null, _ERR_REP_MSG);
                if (is_array($res)) {
                    $invalid_mask =$res[0];
                }else{
                    $response = $res;
                }
                
            }else 
            if($check_mode==3){ //to human readable

                $res = TitleMask::execute($mask, $rectypeID, 2, null, _ERR_REP_MSG);

                if (is_array($res)) {
                    $invalid_mask =$res[0];
                }else{
                    $response = $res;
                }
                
            }else
            if($check_mode==1){ //verify text title mask
            
                $check = TitleMask::check($mask, $rectypeID, true);

                if (!empty($check)) { //empty means titlemask is valid
                    $invalid_mask =$check;
                }else{
                    $response = null;
                }
                
            }else{
                
                $recID = @$_REQUEST['rec_id'];
                $new_title = TitleMask::execute($mask, $rectypeID, 3, $recID, _ERR_REP_WARN); //convert to coded and fill values
                $response =  $new_title;
                
            }
    
    $system->dbclose();
    
    $response = array("status"=>HEURIST_OK, 'data'=>$response, 'message'=>$invalid_mask );            
            
}else{   
    $response = $system->getError();
}

// Returning result as JSON
header('Content-type: application/json;charset=UTF-8');
print json_encode($response);
?>