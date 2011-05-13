<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


/* load some very basic HEURIST objects into top.HEURIST */

// session_cache_limiter("private");

define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);
//ob_start('ob_gzhandler');


require_once(dirname(__FILE__)."/../connect/applyCredentials.php");
require_once("dbMySqlWrappers.php");
require_once("getRecordInfoLibrary.php");

mysql_connection_db_select(DATABASE);

header("Content-type: text/javascript");

// for all tables used in common obj find the lastest update date and if it's not great than the last request
// signal requester
$res = mysql_query("select max(tlu_DateStamp) from sysTableLastUpdated where tlu_CommonObj = 1");
$lastModified = mysql_fetch_row($res);
$lastModified = strtotime($lastModified[0]);
//error_log("lastmod = $lastModified with current time = ".@$_SERVER["REQUEST_TIME"]);
// not changed since last requested so return
if (strtotime(@$_SERVER["HTTP_IF_MODIFIED_SINCE"]) > $lastModified) {
  header('HTTP/1.1 304 Not Modified');
  exit();
}


// This is the best place I can think of to stick this stuff --kj, 2008-07-21
print "if (!top.HEURIST) top.HEURIST = {};\n";
print "top.HEURIST.database = {};\n";
print "top.HEURIST.database.name = " . json_format(HEURIST_DBNAME) . ";\n";
print "top.HEURIST.database.sessionPrefix = " . json_format(HEURIST_SESSION_DB_PREFIX) . ";\n";
print "top.HEURIST.database.exploreURL = " . json_format(EXPLORE_URL) . ";\n";
print "if (!top.HEURIST.basePath) top.HEURIST.basePath = ".json_format(HEURIST_SITE_PATH) . ";\n";
print "if (!top.HEURIST.baseURL) top.HEURIST.baseURL = ".json_format(HEURIST_URL_BASE) . ";\n";

/* rectypes are an array of names sorted alphabetically, and lists of
   primary (bibliographic) and other rectypes, also sorted alphbetically */
print "top.HEURIST.rectypes = ".json_format(getAllRectypeStructures(true)).";\n";

/* detailTypes */

print "top.HEURIST.detailTypes = " . json_format(getAllDetailTypeStructures(true)) . ";\n\n";

print "\ntop.HEURIST.terms = \n". json_format(getTerms(true),true) . ";\n";

/*print "\ntop.HEURIST.terms.termsByDomainLookup = \n" . json_format(getTerms(),true) . ";\n";

print "\ntop.HEURIST.terms.treesByDomain = { 'relation' : " . json_format(getTermTree("reltype","prefix"),true).",\n
												'enum' : " . json_format(getTermTree("enum","prefix"),true)." };\n";
*/
?>
top.HEURIST.ratings = {"0": "not rated",
						"1": "*",
						"2": "**",
						"3": "***",
						"4": "****",
						"5": "*****"
};

<?php
	$workgroups = array();
	$workgroupIDs = array();
	$workgroupsLength = 0;

	$res = mysql_query("select grp.ugr_ID as grpID, grp.ugr_Name as grpName, grp.ugr_Description as description, grp.ugr_URLs as URL, count(ugl_UserID) as members
						  from ".USERS_DATABASE.".sysUGrps grp
					 left join ".USERS_DATABASE.".sysUsrGrpLinks on ugl_GroupID = grp.ugr_ID
					 left join ".USERS_DATABASE.".sysUGrps b on b.ugr_ID = ugl_UserID
						 where grp.ugr_Type != 'user'
						   and b.ugr_Enabled  = 'y'
					  group by grp.ugr_ID order by grp.ugr_Name");
	while ($row = mysql_fetch_assoc($res)) {
		$workgroups[$row["grpID"]] = array(
			"name" => $row["grpName"],
			"description" => $row["description"],
			"url" => $row["URL"],
			"memberCount" => $row["members"]
		);
		$workgroupIDs[$row["grpName"]] = $row["grpID"];

		$workgroupsLength = max($workgroupsLength, intval($row["grpID"])+1);
	}
	$workgroups["length"] = $workgroupsLength;

	$res = mysql_query("select ugl_GroupID, concat(b.ugr_FirstName,' ',b.ugr_LastName) as name, b.ugr_eMail, b.ugr_ID
						  from ".USERS_DATABASE.".sysUGrps grp
					 left join ".USERS_DATABASE.".sysUsrGrpLinks on ugl_GroupID = grp.ugr_ID
					 left join ".USERS_DATABASE.".sysUGrps b on b.ugr_ID = ugl_UserID
						 where grp.ugr_Type != 'user'
						   and ugl_Role = 'admin'
						   and b.ugr_Enabled  = 'y'
					  order by ugl_GroupID, b.ugr_LastName, b.ugr_FirstName");
	$grp_id = 0;
	while ($row = mysql_fetch_assoc($res)) {
		if ($grp_id == 0   ||  $grp_id != $row["ugl_GroupID"]) {
			if ($workgroups[$row["ugl_GroupID"]])
				$workgroups[$row["ugl_GroupID"]]["admins"] = array();
		}
		$grp_id = $row["ugl_GroupID"];

		if ($workgroups[$grp_id])
			array_push($workgroups[$grp_id]["admins"],
				array("name" => $row["name"], "email" => $row["ugr_eMail"], "id" => $row["ugr_ID"]));
	}

	print "top.HEURIST.workgroups = " . json_format($workgroups) . ";\n";
	print "top.HEURIST.workgroupIDs = " . json_format($workgroupIDs) . ";\n";
	print "\n";
//error_log("made to line ".__LINE__." in file ".__FILE__);

?>

if (typeof top.HEURIST.fireEvent == "function") top.HEURIST.fireEvent(window, "heurist-obj-common-loaded");

<?php
ob_end_flush();
?>
