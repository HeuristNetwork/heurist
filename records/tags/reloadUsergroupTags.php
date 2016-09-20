<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* reloadUsergroupTags.php
* returns json array to client side with updated list of workgroup tags to use in
* top.HEURIST.user.workgroupTags  and top.HEURIST.user.workgroupTagOrder
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


define('ISSERVICE',1);
define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
if (! is_logged_in()) return;

mysql_connection_overwrite(DATABASE);

header("Content-type: text/javascript");


$res = mysql_query("select tag_ID, tag_UGrpID, tag_Text from usrTags, ".USERS_DATABASE.".sysUsrGrpLinks, ".USERS_DATABASE.".sysUGrps grp where ugl_GroupID=tag_UGrpID and ugl_GroupID=grp.ugr_ID and ugl_UserID=".get_user_id()." and grp.ugr_Type!='user' order by grp.ugr_Name, tag_Text");
$rows = array();
$ids = array();
while ($row = mysql_fetch_row($res)) {
			$kwd_id = array_shift($row);
			$rows[$kwd_id] = $row;
			array_push($ids, $kwd_id);
}

$userData = array(
	"workgroupTags" => $rows,
	"workgroupTagOrder" => $ids
);

print json_format($userData, true);
?>
