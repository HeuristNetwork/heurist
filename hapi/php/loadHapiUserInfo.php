<?php

/*
* Copyright (C) 2005-2018 University of Sydney
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
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2018 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristNetwork.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

header("Content-Type: text/javascript");
define('ISSERVICE',1);
define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_select(DATABASE);

$is_raw = (array_key_exists('raw', $_REQUEST));

if (! is_logged_in()) {
	if($is_raw){
		print json_format(array(), true);
	}else{
		print "var HAPI_userData = {};\n";
	}
	return;
}

$tags = mysql__select_array("usrTags", "distinct tag_Text", "tag_UGrpID=" . get_user_id());

$workgroups = mysql__select_array(USERS_DATABASE.".sysUsrGrpLinks", "distinct ugl_GroupID", "ugl_UserID=" . get_user_id());

$res = mysql_query("select tag_ID, tag_Text, tag_UGrpID from usrTags, ".USERS_DATABASE.".sysUsrGrpLinks where ugl_GroupID=tag_UGrpID and ugl_UserID=" . get_user_id());
$workgroupTags = array();
while ($row = mysql_fetch_row($res)) { array_push($workgroupTags, $row); }

$currentUser = array(get_user_id(), is_admin(), $workgroups, @$_SESSION[HEURIST_SESSION_DB_PREFIX."heurist"]["display-preferences"]);

$userData = array(
	"tags" => $tags,
	"workgroupTags" => $workgroupTags,
	"currentUser" => $currentUser
);
if($is_raw){
	print json_format($userData, true);
}else{
	print "var HAPI_userData = ";
	print json_encode($userData);
	print ";\n";
}
?>
