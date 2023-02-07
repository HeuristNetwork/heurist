<?php                                                
/**
* resetDB.php removes and rectreats the certain demo database. For faily cron job
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
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

define('DEMO_DB', 'hdb_demo');
define('DEMO_DB_TEMPLATE', 'hdb_demo_template');

require_once(dirname(__FILE__).'/../../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../../hsapi/utilities/dbUtils.php');

set_time_limit(0);

$system = new System();

$res = false;

$isSystemInited = $system->init(DEMO_DB);

if($isSystemInited){
        
    $mysqli = $system->get_mysqli();
    $user_record = user_getById($mysqli, 2);
        
    $res = DbUtils::databaseDrop(false, DEMO_DB, false);    
                            
    if($res) { 
        
        //clone
        if(true){
            $res = false;
            if(DbUtils::databaseCreate(DEMO_DB, 1)){
                if( DbUtils::databaseClone(DEMO_DB_TEMPLATE, DEMO_DB, false, false, false) ){
                    if(DbUtils::databaseCreateConstraintsAndTriggers(DEMO_DB)){
                        
                        $source_db = substr(DEMO_DB_TEMPLATE, 4);
                        $target_db = substr(DEMO_DB, 4);
                        folderRecurseCopy( HEURIST_FILESTORE_ROOT.$source_db, HEURIST_FILESTORE_ROOT.$target_db );
                        $query1 = "update recUploadedFiles set ulf_FilePath='".HEURIST_FILESTORE_ROOT.$target_db.
                        "/' where ulf_FilePath='".HEURIST_FILESTORE_ROOT.$source_db."/' and ulf_ID>0";
                        $res1 = $mysqli->query($query1);
                        
                        //change user#2 to guest
                        mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', array('ugr_ID'=>2, 'ugr_Name'=>'guest',
                                    'ugr_Password' => hash_it( 'guest' ) ) );
                        
                        $res = true;
                    }
                }
            }
        }else{
            //new empty
            $res = DbUtils::databaseCreateFull(DEMO_DB, $user_record);
        }
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
