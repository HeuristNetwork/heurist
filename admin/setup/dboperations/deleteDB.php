<?php                                                
//@TODO json responce 

/**
* deleteDB.php: delete multiple databases. Called by dbStatistics.php (for system admin only)
*               note that deletion of current database is handled separately by deleteCurrentDB.php which calls dbUtils.php
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

//require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
//require_once(dirname(__FILE__).'/../../common/php/dbUtils.php');

require_once(dirname(__FILE__).'/../../../hserver/System.php');
require_once(dirname(__FILE__).'/../../../hserver/utilities/DbUtils.php');

$res = false;

$system = new System();


/** Password check */
if(isset($_POST['password'])) {
    $pw = $_POST['password'];

    // Password in configIni.php must be at least 6 characters
    if(strlen($passwordForDatabaseDeletion) > 6) {
        $comparison = strcmp($pw, $passwordForDatabaseDeletion);  // Check password
        if($comparison == 0) { // Correct password
        
            if(@$_REQUEST['database']){
                
                //if database to be deleted is not current - only system admin can do it
                $isSystemInited = $system->init(@$_REQUEST['db']); //need to verify credentials for current database

                /** Db check */
                if(!$isSystemInited){
                    $system->addError(HEURIST_DB_ERROR, 'Can not init database connection to '.@$_REQUEST['db']);
                }else{
                    if($system->is_system_admin() || 
                            ($_REQUEST['database']==$_REQUEST['db'] && $system->is_dbowner()) )
                    {
                        $res = DbUtils::databaseDrop(false, $_REQUEST['database']);    
                    }else{
                        $system->addError(HEURIST_REQUEST_DENIED, 
                            'You must be logged in as a system administrator or database owner to delete database',1);
                    }
                }
            }else{
                $res = true; //password correct
            }
        }else{
            // Invalid password
            $system->addError(HEURIST_REQUEST_DENIED, 'Invalid password');
        }    
    }else{
        $system->addError(HEURIST_SYSTEM_CONFIG, 'Password in configIni.php must be at least 6 characters');
    }
}else{
    //password not defined
    $system->addError(HEURIST_INVALID_REQUEST, 'Password not specified');
}

if(is_bool($res) && !$res){
    $response = $system->getError();
}else{
    $response = array("status"=>HEURIST_OK, "data"=> $res);
}
    
header('Content-type: text/javascript');
print json_encode($response);
?>
