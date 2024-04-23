<?php                                                
/**
* restoreDB.php: re-create heurist database from selected archive
*  
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

exit; //disabled

require_once dirname(__FILE__).'/../../../hserv/System.php';
require_once dirname(__FILE__).'/../../../hserv/utilities/dbUtils.php';
require_once dirname(__FILE__).'/../../../hserv/utilities/uFile.php';
//http://127.0.0.1/h6-alpha/admin/setup/dboperations/restoreDB.php
set_time_limit(0);

header('Content-type: text/javascript');

$_DEBUG_NOT_CREATE = false; //set to true to avoid db creation
$_DEBUG_NOT_EMAIL = false; //set to true to avoid db creation

$res = false;

$system = new System();

/** Password check */
if( false &&
    isset($passwordForDatabaseCreation) && $passwordForDatabaseCreation!='' &&
    $passwordForDatabaseCreation!=@$_REQUEST['create_pwd'] )
{
    //password not defined
    $system->addError(HEURIST_ERROR, 'Invalid password');
    
}else{

    $system->init(null, false);
    
    $db_archive = 'waterloo_cross_dressing_archive_2024-03-28_10_27_45.zip';
    $db_name = 'waterloo_cross_dressing';
    
    $res = false;
    
    //$res = DbUtils::databaseCreateFromArchive($db_name, $db_archive);
    
    if($res){            
        //print json_encode(array('status'=>HEURIST_SYSTEM_CONFIG, 'message'=>'Sorry, we were not able to create all file directories required by the database.<br>Please contact the system administrator (email: ' . HEURIST_MAIL_TO_ADMIN . ') for assistance.', 'sysmsg'=>'This error has been emailed to the Heurist team (for servers maintained by the project - may not be enabled on personal servers). We apologise for any inconvenience', 'error_title'=>null));
        //        exit;
        if(!$_DEBUG_NOT_EMAIL){
                // sendEmail_NewDatabase($user_record, $database_name, null);
        }
    }
}

if(is_bool($res) && !$res){
    $response = $system->getError();
}else{
    $response = array('status'=>HEURIST_OK, 
                'newdbname'=>$database_name, 
                'newdblink'=> HEURIST_BASE_URL.'?db='.$database_name.'&welcome=1'
                //'newusername'=>$user_record['ugr_Name'],
                //'warnings'=>$warnings
                );
}
    
print json_encode($response);
?>
