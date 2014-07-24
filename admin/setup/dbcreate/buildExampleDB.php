<?php
/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Recreate example database
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage DataStore
* @todo       funnel all calls to functions in this file. Not all system code uses there abstractions. Especially services.
*/
define('RECREATE_EXAMPLE_DB', 1);
require_once(dirname(__FILE__).'/../../../common/config/initialise.php');

function buildExampleDB($dbFullName){
    $commands = file_get_contents("buildExampleDB.sql");
    $commands = str_replace("hdb_H3Sandpit", $dbFullName, $commands);
    
    //$tempfile = tmpfile();  
    $tmpfname = tempnam("/tmp", "buildExampleDB");
    file_put_contents($tmpfname, $commands);
      
    $cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." < ".$tmpfname;
    
//error_log(">>> ".$cmdline);
    
    $output2 = exec($cmdline . ' 2>&1', $output, $res2);
    
//error_log(">>> ".$res2);
//error_log(">>> ".$output2);

    return $res2;
}
?>
