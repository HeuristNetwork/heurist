<?php

/* load the user's search preferences into JS -- saved searches are all that spring to mind at the moment */

define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-

require_once(dirname(__FILE__).'/../connect/cred.php');
require_once(dirname(__FILE__).'/../connect/db.php');

header('Content-type: text/javascript');


mysql_connection_db_select(DATABASE);

if (is_logged_in()) {
?>

	top.HEURIST.user = {};

    top.HEURIST.user.savedSearches = [<?php
$res = mysql_query('select ss_name, ss_query, ss_query not like "%w=bookmark%" as w_all, ss_id from saved_searches where ss_usr_id='.get_user_id().' and ! ss_wg_id order by w_all, ss_name');
$first = true;
while ($row = mysql_fetch_assoc($res)) {
    if (! $first) print ",";  print "\n"; $first = false;
	//this is for searches from  obsolete published-searches table. they start with "q";
	if (preg_match('/^q/', $row['ss_query'])) {
		$row['ss_query'] = "?".$row['ss_query'];
    }
    print "        [ \"" . addslashes($row['ss_name']) . "\", \"" . addslashes($row['ss_query']) . "\", ". $row['ss_id'] .", 0, " . intval($row['w_all']) ." ]";
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

$res = mysql_query("select tag_ID, tag_UGrpID, tag_Text from usrTags, ".USERS_DATABASE.".UserGroups, ".USERS_DATABASE.".Groups where ug_group_id=tag_UGrpID and ug_group_id=grp_id and ug_user_id=".get_user_id()." and grp_type!='Usergroup' order by grp_name, tag_Text");
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
if (is_array(@$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_access'])) {
	$workgroups = mysql__select_array(USERS_DATABASE.".Groups", "grp_id", "grp_id in (".join(",", array_keys($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['user_access'])).") and grp_type!='Usergroup' order by grp_name");
	print join(", ", $workgroups);
}
?> ];

    top.HEURIST.user.workgroupSavedSearches = <?php
$ws = array();
if (@$workgroups) {
	$res = mysql_query("select ss_wg_id, ss_id, ss_name, ss_query from saved_searches left join ".USERS_DATABASE.".Groups on grp_id = ss_wg_id where ss_wg_id in (".join(",", $workgroups).") order by grp_name, ss_name");
	while ($row = mysql_fetch_assoc($res)) {
		$wg = $row['ss_wg_id'];
		if (! @$ws[$wg])
			$ws[$wg] = array();
		//this is for searches from  obsolete published-searches table. they start with "q";
		if (preg_match('/^q/', $row['ss_query'])) {
			$row['ss_query'] = "?".$row['ss_query'];
    	}
		array_push($ws[$wg], array($row['ss_name'], $row['ss_query'], $row['ss_id']));
	}
}
print json_format($ws, true);
?>;


<?php
$bdrs = array();
$colNames = array("rdr_rec_type", "rdr_rdt_id", "rdr_name", "rdr_prompt", "rdr_default", "rdr_required", "rdr_repeatable", "rdr_size", "rdr_match", "rdr_wg_id");
if (@$workgroups) {
	$res = mysql_query("select " . join(", ", $colNames) . " from rec_detail_requirements_overrides where rdr_wg_id in (" . join(',', $workgroups) . ") order by rdr_rec_type, rdr_order is null, rdr_order");
	array_shift($colNames);	// don't print rec_types on every row
	array_shift($colNames);	// don't print rdt_id on every row

	while ($row = mysql_fetch_row($res)) {
		$rt_id = array_shift($row);
		$rdt_id = array_shift($row);
		if (! @$bdrs[$rt_id]) $bdrs[$rt_id] = array();
		if (! @$bdrs[$rt_id][$rdt_id]) $bdrs[$rt_id][$rdt_id] = array();
		array_push($bdrs[$rt_id][$rdt_id], $row);
	}
}
$bdr_details = array("colNames" => $colNames, "valuesByReftypeID" => $bdrs);
?>
   top.HEURIST.user.bibDetailRequirements = <?= json_format($bdr_details) ?>;

    top.HEURIST.user.colleagueGroups = {<?php
	$res = mysql_query("select cgr_id, cgr_name from coll_groups where cgr_owner_id=".get_user_id()." order by cgr_name");
	$first = true;
	while ($row = mysql_fetch_row($res)) {
		if (! $first) print ",";  print "\n"; $first = false;
		print "\t\"".$row[0]."\":\t\"".slash($row[1])."\"";
	}
?>

    };

    top.HEURIST.user.isInWorkgroup = function(wg_id) {
		if (! top.HEURIST.user.workgroups) return false;
		for (var i in top.HEURIST.user.workgroups) {
			if (wg_id == top.HEURIST.user.workgroups[i]) return true;
		}
		return false;
	};

<?php
}

$res = mysql_query("select Id, Username, concat(firstname, ' ', lastname) as fullname
					  from ".USERS_DATABASE.".Users
					 where Active='Y' and firstname is not null and lastname is not null and !IsModelUser
				  order by fullname");
print "    top.HEURIST.allUsers = {\n";
$first = true;
while ($row = mysql_fetch_row($res)) {
	if (! $first) print ",";  print "\n"; $first = false;
	print "\t\"" . $row[0] . "\":\t[ \"".slash($row[1])."\", \"".slash($row[2])."\" ]";
}
print "    };\n";
?>


    top.HEURIST.is_logged_in = function() { return <?= intval(is_logged_in()) ?> > 0; };
    top.HEURIST.get_user_id = function() { return <?= intval(get_user_id()) ?>; };
    top.HEURIST.get_user_name = function() { return "<?= addslashes(get_user_name()) ?>"; };
    top.HEURIST.get_user_username = function() { return "<?= addslashes(get_user_username()) ?>"; };
    top.HEURIST.is_admin = function() { return <?= intval(is_admin()) ?>; };

<?php if (! is_admin()) { ?>
    top.document.body.className += " is-not-admin";
<?php } ?>

<?php if (! is_logged_in()) { ?>
    top.document.body.className += " is-not-logged-in";
<?php } ?>

    top.HEURIST.fireEvent(window, "heurist-obj-user-loaded");
