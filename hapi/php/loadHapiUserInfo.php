<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

header("Content-Type: text/javascript");
define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

/* removed hapi keys  saw 17/1/11
require_once("validateKeyedAccess.php");
if (! @$_REQUEST["key"]) {
	print 'alert("No Heurist API key specified");';
	return;
}

if (! ($loc = get_location($_REQUEST["key"]))) {
	print 'alert("Unknown Heurist API key");';
	return;
}
define_constants($loc["hl_instance"]);
*/
mysql_connection_db_select(DATABASE);

if (! is_logged_in()) {
	print "var HAPI_userData = {};\n";
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

print "var HAPI_userData = ";
print json_encode($userData);
print ";\n";

?>
