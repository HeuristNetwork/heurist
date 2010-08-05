<?php

require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');

if (! is_admin()) {
	header("Location: ".HEURIST_URL_BASE."common/connect/login.php");
}

$groupID = intval(@$_REQUEST["g"]);
$userID = intval(@$_REQUEST["u"]);
$date = $_REQUEST["d"];

if (! $groupID  &&  ! $userID) {
	exit("No group or user specified");
}

function get_group_members($gid) {
	$query = "SELECT Id as id, firstname, lastname
	            FROM ".USERS_DATABASE.".".USER_GROUPS_TABLE."
	      INNER JOIN ".USERS_DATABASE.".".USERS_TABLE." ON Id = ug_user_id
	           WHERE ug_group_id = $gid
	        ORDER BY lastname, firstname";
	$res = mysql_query($query);
	$rv = array();
	while ($row = mysql_fetch_assoc($res)) {
		array_push($rv, $row);
	}
	return $rv;
}

function print_heading($date) {
	print "<h2>Entries since " . $date . ", generated on " . date("Y-m-d") . " at " . date("H:i:s") . "<h2>\n";
}

function get_blog_entries($uid, $date) {
	$query = "SELECT rec_id as id,
	                 rec_added as added,
	                 rec_title as title
	            FROM records
	           WHERE rec_added_by_usr_id = $uid
	             AND rec_type = 137
	" . ($date ? "AND rec_added >= '".addslashes($date)."'" : "") . "
	         ORDER BY rec_added DESC";
	error_log($query);
	$res = mysql_query($query);
	$rv = array();
	while ($row = mysql_fetch_assoc($res)) {
print "\n<!--\n";
print_r($row);
print "\n-->\n";
		array_push($rv, $row);
	}
	return $rv;
}

function get_blog_entry_content($id) {
	$query = "SELECT GROUP_CONCAT(chunk_text SEPARATOR '\n')
	            FROM woots
	       LEFT JOIN woot_chunks ON chunk_woot_id = woot_id
	                            AND chunk_is_latest
	                            AND ! chunk_deleted
	           WHERE woot_title = CONCAT('record:', $id)
	        GROUP BY woot_id
	        ORDER BY chunk_order";
	$res = mysql_query($query);
	if (mysql_num_rows($res) < 1) {
		return "";
	}
	$row = mysql_fetch_row($res);
	return $row[0];
}

function print_blog_entries($uid, $name, $date) {
	print "<hr>\n";
	print "<h3>$name</h3>\n";
	$entries = get_blog_entries($uid, $date);
	foreach ($entries as $entry) {
		print "<h2>" . $entry["title"] . " - " . $entry["added"] . "</h2>\n";
		$content = get_blog_entry_content($entry["id"]);
		print $content . "\n\n";
		print_comments($entry["id"]);
	}
}


function print_comments($rec_id) {
	$query = "SELECT Realname, cmt_date, cmt_text
	          FROM comments
			  LEFT JOIN ACLAdmin.Users ON ID = cmt_usr_id
	          WHERE cmt_rec_id = $rec_id
	          AND ! cmt_deleted
	          ORDER BY cmt_date";
	$res = mysql_query($query);
	if (mysql_num_rows($res) > 0) {
		print "<h3>Comments</h3>\n";
		while ($row = mysql_fetch_assoc($res)) {
			print "<p>" . $row["Realname"] . " - " . $row["cmt_date"] . "</p>\n";
			print "<p>" . $row["cmt_text"] . "</p>\n";
		}
	}
}

mysql_connection_select(DATABASE);

if ($groupID) {
	$gres = mysql_query("select grp_name from ACLAdmin.Groups where grp_id = $groupID");
	$row = mysql_fetch_assoc($gres);
	$grp_name = $row["grp_name"];
	print "<h1>Blog report for group $grp_name</h1>\n";
	print_heading($date);
	$members = get_group_members($groupID);
	foreach ($members as $member) {
		print_blog_entries($member["id"], $member["firstname"]." ".$member["lastname"], $date);
	}
}
else {
	$ures = mysql_query("select Realname from ACLAdmin.Users where Id = $userID");
	$row = mysql_fetch_assoc($ures);
	$usr_name = $row["Realname"];
	print "<h1>Blog report for user $usr_name</h1>\n";
	print_heading($date);
	print_blog_entries($userID, $row["Realname"], $date);
}

?>
