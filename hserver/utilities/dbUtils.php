<?php
/**
* dbUtils.php : Functions to create, delelet, clean the entire HEURIST database
*               and other functions to do with database file structure
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
* @subpackage  DataStore
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* @todo     Funnel all calls to functions in this file.
*           Not all system code uses these abstractions. Especially services.
* 
* @todo for H4 - implement as class fot given database connection
*
* Function list:
* - add_index_html()
* - server_connect()
* - db_create()
* - db_drop()
* - db_dump()
* - db_clean()
* - db_script()  - see utils_db_load_script.php
* 
* - db_register - set register ID to sysIdentification and rectype, detail and term defintions
*/

require_once(dirname(__FILE__).'/../../hserver/utilities/utils_db_load_script.php');

class DbUtils {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}    
    private static $mysqli = null;

    public static function initialize($mysqli=null)
    {
        if (self::$initialized)
            return;

        global $system;
        
        if($mysqli){
            self::$mysqli = $mysqli;
        }else{
            self::$mysqli = $system->get_mysqli();    
        }

        self::$initialized = true;
    }

    //
    // set Origin ID for rectype, detail and term defintions
    //
    public static function register($dbID){

        self::initialize();
        
        $res = true;

        if($dbID>0){

                    //@todo why 3 actions for every table????? 
                    $result = 0;
                    $res = $mysqli->query("update defRecTypes set rty_OriginatingDBID='$dbID' ".
                        "where (rty_OriginatingDBID = '0') OR (rty_OriginatingDBID IS NULL) ");
                    if (!$res) {$result = 1; }
                    $res = $mysqli->query("update defRecTypes set rty_NameInOriginatingDB=rty_Name ".
                        "where (rty_NameInOriginatingDB = '') OR (rty_NameInOriginatingDB IS NULL)");
                    if (!$res) {$result = 1; }
                    $res = $mysqli->query("update defRecTypes set rty_IDInOriginatingDB=rty_ID ".
                        "where (rty_IDInOriginatingDB = '0') OR (rty_IDInOriginatingDB IS NULL) ");
                    if (!$res) {$result = 1; }
                    // Fields
                    $res = $mysqli->query("update defDetailTypes set dty_OriginatingDBID='$dbID' ".
                        "where (dty_OriginatingDBID = '0') OR (dty_OriginatingDBID IS NULL) ");
                    if (!$res) {$result = 1; }
                    $res = $mysqli->query("update defDetailTypes set dty_NameInOriginatingDB=dty_Name ".
                        "where (dty_NameInOriginatingDB = '') OR (dty_NameInOriginatingDB IS NULL)");
                    if (!$res) {$result = 1; }
                    $res = $mysqli->query("update defDetailTypes set dty_IDInOriginatingDB=dty_ID ".
                        "where (dty_IDInOriginatingDB = '0') OR (dty_IDInOriginatingDB IS NULL) ");
                    if (!$res) {$result = 1; }
                    // Terms
                    $res = $mysqli->query("update defTerms set trm_OriginatingDBID='$dbID' ".
                        "where (trm_OriginatingDBID = '0') OR (trm_OriginatingDBID IS NULL) ");
                    if (!$res) {$result = 1; }
                    $res = $mysqli->query("update defTerms set trm_NameInOriginatingDB=trm_Label ".
                        "where (trm_NameInOriginatingDB = '') OR (trm_NameInOriginatingDB IS NULL)");
                    if (!$res) {$result = 1; }
                    $res = $mysqli->query("update defTerms set trm_IDInOriginatingDB=trm_ID ".
                        "where (trm_IDInOriginatingDB = '0') OR (trm_IDInOriginatingDB IS NULL) ");
                    if (!$res) {$result = 1; }

                    
                    if (!$res){
                        error_log('Error on database registration '.$db_name.'  '.$mysqli->error);
                    }
                    
                    $res = ($result==0);
        }
        return $res;
    }    
    
}


?>