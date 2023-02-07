<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* removeDatabaseLocks.php, Removes all locks on the database. Ian Johnson 20/9/12
* We can get away with no checkiong b/c locks are administrative and collisons are almost inconceivable
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

define('MANAGER_REQUIRED',1);   
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');

$mysqli = $system->get_mysqli();


$query="delete from sysLocks";
$res = $mysqli->query($query);
if (!$res) {
    $message = 'Invalid query, please '.CONTACT_HEURIST_TEAM.': '.$query.'  Error: '.$mysqli->error();
    include dirname(__FILE__).'/../../hclient/framecontent/infoPage.php';
    exit();
}

print '<html><head><link rel="stylesheet" type="text/css" href="'.PDIR.'h4styles.css" /></head><body class="popup">';

if ($mysqli->affected_rows==0) {
    print '<h2>There were no database locks to remove</h2>';
}
else {
    print '<h2>Database locks have been removed</h2>';
}

print "</body>";
print "</html>";
?>