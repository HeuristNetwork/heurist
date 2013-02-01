<?php

	/*<!--
	* filename, brief description, date of creation, by whom
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	-->*/


	/* load the user's search preferences into JS -- saved searches are all that spring to mind at the moment */

	define("SAVE_URI", "disabled");

	// using ob_gzhandler makes this stuff up on IE6-

	require_once(dirname(__FILE__).'/../connect/applyCredentials.php');

	header('Content-type: text/javascript');


	mysql_connection_select(DATABASE);

	if (is_logged_in()) {
	?>

	top.HEURIST.user = {};

	top.HEURIST.user.savedSearches = [<?php
		$res = mysql_query('select svs_Name, svs_Query, svs_Query not like "%w=bookmark%" as w_all, svs_ID from usrSavedSearches where svs_UGrpID='.get_user_id().' order by w_all, svs_Name');
		$first = true;
		while ($row = mysql_fetch_assoc($res)) {
			if (! $first) print ",";  print "\n"; $first = false;
			//this is for searches from  obsolete published-searches table. they start with "q";
			if (preg_match('/^q/', $row['svs_Query'])) {
				$row['svs_Query'] = "?".$row['svs_Query'];
			}
			print "        [ \"" . addslashes($row['svs_Name']) . "\", \"" . addslashes($row['svs_Query']) . "\", ". $row['svs_ID'] .", 0, " . intval($row['w_all']) ." ]";
		}
	?>
	];

	top.HEURIST.user.tags = [<?php
		$res = mysql_query('select distinct tag_Text from usrTags where tag_UGrpID='.get_user_id().' order by tag_Text');
		$first = true;
		while ($row = mysql_fetch_row($res)) {
			if (! $first) print ",";  print "\n"; $first = false;
			print "        \"" . slash($row[0]) . "\"";
		}
	?>
	];
	<?php

		$res = mysql_query("select tag_ID, tag_UGrpID, tag_Text from usrTags, ".USERS_DATABASE.".sysUsrGrpLinks, ".USERS_DATABASE.".sysUGrps grp where ugl_GroupID=tag_UGrpID and ugl_GroupID=grp.ugr_ID and ugl_UserID=".get_user_id()." and grp.ugr_Type!='user' order by grp.ugr_Name, tag_Text");
		$rows = array();
		$ids = array();
		while ($row = mysql_fetch_row($res)) {
			$kwd_id = array_shift($row);
			$rows[$kwd_id] = $row;
			array_push($ids, $kwd_id);
		}
	?>
	top.HEURIST.user.workgroupTags = <?= json_format($rows); ?>;
	top.HEURIST.user.workgroupTagOrder = <?= json_format($ids); ?>;

	top.HEURIST.user.topTags = [<?php
		/* find the top five tags for this user */
		$res = mysql_query("select tag_Text, count(rtl_ID) as c from usrTags left join usrRecTagLinks on rtl_TagID=tag_ID
			where tag_UGrpID=".get_user_id()." group by tag_Text order by c desc limit 5");
		$first = true;
		while ($row = mysql_fetch_row($res)) {
			if (! $first) print ",";  print " "; $first = false;
			print "\"" . addslashes($row[0]) . "\"";
		}
	?> ];

	top.HEURIST.user.recentTags = [<?php
		/* find the ten most recently used tags for this user */
		$res = mysql_query("select distinct(tag_Text) from usrTags left join usrRecTagLinks on rtl_TagID=tag_ID
			where tag_UGrpID=".get_user_id()." group by rtl_TagID order by max(rtl_ID) desc limit 10");
		$first = true;
		while ($row = mysql_fetch_row($res)) {
			if (! $first) print ",";  print " "; $first = false;
			print "\"" . addslashes($row[0]) . "\"";
		}
	?> ];

	top.HEURIST.user.workgroups = [<?php
		if (is_array(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'])) {
			$query = "grp.ugr_ID in (".join(",", array_keys($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'])).") and grp.ugr_Type !='user' order by grp.ugr_Name";

/*****DEBUG****///error_log(">>>>>>>>>>>> PREFIX=".HEURIST_SESSION_DB_PREFIX."   ".$query);
/*****DEBUG****///error_log("session data".print_r($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'],true));
			$workgroups = mysql__select_array(USERS_DATABASE.".sysUGrps grp", "grp.ugr_ID", $query);
			print join(", ", $workgroups);
		}
	?> ];

	top.HEURIST.user.workgroupSavedSearches = <?php
		$ws = array();
		if (@$workgroups) {
			$res = mysql_query("select svs_UGrpID, svs_ID, svs_Name, svs_Query from usrSavedSearches left join ".USERS_DATABASE.".sysUGrps grp on grp.ugr_ID = svs_UGrpID where svs_UGrpID in (".join(",", $workgroups).") order by grp.ugr_Name, svs_Name");
			while ($row = mysql_fetch_assoc($res)) {
				$wg = $row['svs_UGrpID'];
				if (! @$ws[$wg])
					$ws[$wg] = array();
				//this is for searches from  obsolete published-searches table. they start with "q";
				if (preg_match('/^q/', $row['svs_Query'])) {
					$row['svs_Query'] = "?".$row['svs_Query'];
				}
				array_push($ws[$wg], array($row['svs_Name'], $row['svs_Query'], $row['svs_ID']));
			}
		}
		print json_format($ws, true);
	?>;


	top.HEURIST.user.isInWorkgroup = function(wgID) {
		var usrID = top.HEURIST.get_user_id();
		if (wgID == 0 || usrID == wgID) return true;
		if (! top.HEURIST.user.workgroups) return false;
		for (var i in top.HEURIST.user.workgroups) {
			if (wgID == top.HEURIST.user.workgroups[i]) return true;
		}
		return false;
	};

	<?php
	}


	$res = mysql_query("select usr.ugr_ID, usr.ugr_Name, concat(usr.ugr_FirstName, ' ', usr.ugr_LastName) as fullname
		from ".USERS_DATABASE.".sysUGrps usr
		where usr.ugr_Enabled='y' and usr.ugr_FirstName is not null and usr.ugr_LastName is not null and !usr.ugr_IsModelUser
	order by fullname");
	print "    top.HEURIST.allUsers = {\n";
	$first = true;
	while ($row = mysql_fetch_row($res)) {
		if (! $first) print ",";  print "\n"; $first = false;
		print "\t\"" . $row[0] . "\":\t[ \"".slash($row[1])."\", \"".slash($row[2])."\" ]";
	}
	print "    };\n";
?>

top.HEURIST.is_registration_allowed = <?=((defined('HEURIST_ALLOW_REGISTRATION') && HEURIST_ALLOW_REGISTRATION) ?"true" :"false")?>;
top.HEURIST.is_logged_in = function() { return <?= intval(is_logged_in()) ?> > 0; };
top.HEURIST.get_user_id = function() { return <?= intval(get_user_id()) ?>; };
top.HEURIST.get_user_name = function() { return "<?= addslashes(get_user_name()) ?>"; };
top.HEURIST.get_user_username = function() { return "<?= addslashes(get_user_username()) ?>"; };
top.HEURIST.is_admin = function() { return <?= intval(is_admin()) ?>; };
top.HEURIST.is_wgAdmin = function(wgID) {
var usrID = top.HEURIST.get_user_id(), j, i;
if (wgID == 0 || usrID == wgID) return true;
if (!top.HEURIST.workgroups || (wgID && !top.HEURIST.workgroups[wgID])) return false;
var wgroups = wgID?[wgID]:top.HEURIST.user.workgroups;
for (j=0; j < wgroups.length; i++) {
var wgAdmins = top.HEURIST.workgroups[wgroups[j]].admins;
for (i=0; i < wgAdmins.length; i++) {
if ( wgAdmins[i].id == usrID) return true;
}
return false;
}};

<?php if (! is_admin()) { ?>
	top.document.body.className += " is-not-admin";
	<?php } ?>

<?php if (! is_logged_in()) { ?>
	top.document.body.className += " is-not-logged-in";
	<?php } ?>
top.HEURIST.fireEvent(window, "heurist-obj-user-loaded");