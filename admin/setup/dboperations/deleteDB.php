<?php                                                
/**
* deleteDB.php: delete multiple databases. Called by dbStatistics.php (for system admin only)
*               note that deletion of current database is handled separately by deleteCurrentDB.php which calls dbUtils.php
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2018 University of Sydney
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

require_once(dirname(__FILE__).'/../../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');

$res = false;

$system = new System();

/** Password check */
if(!$system->verifyActionPassword(@$_REQUEST['pwd'], $passwordForDatabaseDeletion, 14) )
{
    if(@$_REQUEST['database']){
        
        //if database to be deleted is not current - only system admin can do it
        $isSystemInited = $system->init(@$_REQUEST['db']); //need to verify credentials for current database

        /** Db check */
        if($isSystemInited){
            if($system->is_system_admin() || 
                    ($_REQUEST['database']==$_REQUEST['db'] && $system->is_dbowner()) )
            {
                $res = DbUtils::databaseDrop(false, $_REQUEST['database'], false);    
            }else{
                $system->addError(HEURIST_REQUEST_DENIED, 
                    'You must be logged in as a system administrator or database owner to delete database',1);
            }
        }
    }else{
        $res = true; //authentification passed
        //$system->addError(HEURIST_INVALID_REQUEST, 'Database parameter is not defined');
    }
}

if(is_bool($res) && !$res){
    $response = $system->getError();
}else{
    $response = array("status"=>HEURIST_OK, "data"=> $res);
}
    
header('Content-type: text/javascript');
print json_encode($response);
?>
