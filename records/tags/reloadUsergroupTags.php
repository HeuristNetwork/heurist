<?php

/**
 * reloadUsergroupTags.php
 *
 * returns json array to client side with updated list of workgroup tags to use in
 * top.HEURIST.user.workgroupTags  and top.HEURIST.user.workgroupTagOrder
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

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
