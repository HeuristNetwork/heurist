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



/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/



require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

function jsonError($message) {
	print "{\"error\":\"" . addslashes($message) . "\"}";
	exit(0);
}

if (! is_logged_in()) {
	jsonError("no logged-in user");
}

$label = @$_REQUEST["label"];
$wg = intval(@$_REQUEST["wg"]);
$ssid = intval(@$_REQUEST["ssid"]);

if($label && $ssid){
	jsonError("missing argument (id or label) for saved search deletion");
}

mysql_connection_overwrite(DATABASE);

if($ssid>0){
	mysql_query("delete from usrSavedSearches where svs_ID=$ssid");
}else if ($wg > 0) { //OLD WAY
	mysql_query("delete from usrSavedSearches where svs_Name='$label' and svs_UGrpID=$wg");
} else {
	mysql_query("delete from usrSavedSearches where svs_Name='$label' and svs_UGrpID=".get_user_id());
}

print "{\"deleted\":" . (mysql_affected_rows() > 0 ? "true" : "false") . "}";

?>
