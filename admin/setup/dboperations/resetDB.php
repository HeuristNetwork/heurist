<?php                                                
/**
* resetDB.php removes and rectreats the certain demo database. For faily cron job
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

define('DEMO_DB', 'hdb_demo');

require_once(dirname(__FILE__).'/../../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');

set_time_limit(0);

$system = new System();

$res = false;

$isSystemInited = $system->init(DEMO_DB);

if($isSystemInited){
                
    $user_record = user_getById($system->get_mysqli(), 2);
        
    $res = DbUtils::databaseDrop(false, DEMO_DB, false);    
                            
    if($res) { 
        
        $res = DbUtils::databaseCreateFull(DEMO_DB, $user_record);
    }
}

if(is_bool($res) && !$res){
    $response = $system->getError();
    $response = $response['message'];
}else if(is_array($res) && count($res) > 0){
    $response = 'not able to create all file directories '.implode(', ',$res);
}else{
    $response = 'Database '.DEMO_DB.' has been reset';
}
    
print $response;
?>
