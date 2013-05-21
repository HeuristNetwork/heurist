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
* brief description of file
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
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/* Load HAPI with the correct database key.  Since this file has the privelege
 * of being on the server with the hapi key database it can look at that directly
 * and save us hard-coding a map of database => key (yuck).
 */
define('ISSERVICE',1);

header("Content-type: text/javascript");
/* hapi key's removed saw 17/1/11
if (@$_REQUEST["db"]) {
	define("HEURIST_DBNAME", $_REQUEST["db"]);
	define("HOST", $_SERVER["HTTP_HOST"]);
}
*/
require_once(dirname(__FILE__)."/../config/initialise.php");
require_once("dbMySqlWrappers.php");

?>

document.write("<" + "scr" +"ipt src=\"<?=HEURIST_BASE_URL?>hapi/hapiLoader.php?db=<?= HEURIST_DBNAME ?><?=(@$_REQUEST["inclGeo"]? "&inclGeo=1":"")?>\"><" + "/script>\n");

