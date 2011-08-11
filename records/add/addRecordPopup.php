<?php

	/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


	define('SAVE_URI', 'disabled');

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	if (! is_logged_in()) return;

	mysql_connection_db_select(DATABASE);

	$addRecDefaults = @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]["addRecordDefaults"];
	if ($addRecDefaults) {
		$defaults = explode(",",$addRecDefaults);
	}
?>
<html>
<head>
	<link rel=stylesheet href="<?=HEURIST_SITE_PATH?>common/css/global.css">
	<link rel=stylesheet href="<?=HEURIST_SITE_PATH?>common/css/edit.css">
	<title>Add new record</title>

  <script src="<?=HEURIST_SITE_PATH?>external/jquery/jquery.js"></script>

  <script>
//		rt, wg_id,vis, kwd, tags, restrict Access;
var defaults = [ <?= ($addRecDefaults ? $addRecDefaults :'') ?>];
var usrID = <?= get_user_id() ?> ;
var defAccess = '<?= HEURIST_NEWREC_ACCESS?HEURIST_NEWREC_ACCESS:"viewable"?>';
var defOwnerID = <?=in_array(HEURIST_NEWREC_OWNER_ID,get_group_ids())?HEURIST_NEWREC_OWNER_ID:0?>;
$(document).ready(function() {
	$("#show-adv-link").click(function() {
		$(this).hide();
		$('#advanced-section').show();
		return false;
	});
	// assign onchange handle to update_link for values used in link
	$("#rectype_elt, #restrict_elt, #rec_OwnerUGrpID, #tag, #rec_NonOwnerVisibility, #add-link-title, #add-link-tags").change(update_link);
	if(defaults && defaults.length > 0){
		if(defaults[0]){
			$("#rectype_elt").val(defaults[0]);
		}
		if(defaults[2]){
			$("#rec_NonOwnerVisibility").val(defaults[2]);
		}
		if(defaults[4]){
			$("#add-link-tags").val(defaults[4]);
		}
		if(defaults[5]){
			$("#restrict_elt").click();
		}
		if(defaults[1]){
			$("#rec_OwnerUGrpID").val(parseInt(defaults[1]));
		}
		buildworkgroupTagselect(defaults[1] ? parseInt(defaults[1]) : null, defaults[3] ? decodeURIComponent(defaults[3]) : null );
	}else{
	var matches = location.search.match(/wg_id=(\d+)/);
	buildworkgroupTagselect(matches ? matches[1] : null);
		$("#rec_NonOwnerVisibility").val(defAccess);
		$("#rec_OwnerUGrpID").val(parseInt(defOwnerID));
	}
	update_link();
});

function buildworkgroupTagselect(wgID,keyword) {
	var i, l, kwd, val;
	$("#tag").empty();
	$("<option value='' selected disabled>(select workgroup tag)</option>").appendTo("#tag");
	l = top.HEURIST.user.workgroupTagOrder.length;
	for (i = 0; i < l; ++i) {
		kwd = top.HEURIST.user.workgroupTags[top.HEURIST.user.workgroupTagOrder[i]];
		if (! wgID  ||  wgID == kwd[0]) {
			val = top.HEURIST.workgroups[kwd[0]].name + "\\" + kwd[1];
			$("<option value='" + val + "'"+ (keyword && val == keyword ? " selected":"") +">" + kwd[1] + "</option>").appendTo("#tag");
		}
	}
}


function update_link() {
	var base = "<?= HEURIST_URL_BASE?>records/add/addRecord.php?addref=1&db=<?=HEURIST_DBNAME?>";
	var link = base + compute_args();

	var tags = $("#add-link-tags").val();
	var title = $("#add-link-title").val();

	if (tags) {
		link += (link.match(/&tag=/))  ?  "," + tags  :  "&tag=" + tags;
	}

	// removed Ian 19/9/08 - title in form is confusing
    // if (title) {
	//	link += "&t=" + title;
	// }
    // added Ian 19/9/08 -  simple guidleine for user of URL - only on the link, not on the insert
    link += "&t=" + "enter title here ...";

	if (! parseInt($("#rectype_elt").val())) {
		link = "";
	}

	$("#add-link-input").val(link);

	$("#broken-kwd-link").hide();
	//setup link to search for records add to a workgroup with tag by a non-member
	if ($("#tag").val()) {
		$("#broken-kwd-link").show()[0].href =
			"../../search/search.html?w=all&q=tag:\"" + $("#tag").val().replace(/\\/, "") + "\"" +
			          " -tag:\"" + $("#tag").val() + "\"";
	}
}

function compute_args() {
	var extra_parms = '';
	if (document.getElementById('restrict_elt').checked) {
		var wg_id = parseInt(document.getElementById('rec_OwnerUGrpID').value);
		if (wg_id) {
			if ( wg_id != usrID) {
			extra_parms = '&bib_workgroup=' + wg_id;
			}
			extra_parms += '&bib_visibility=' + document.getElementById('rec_NonOwnerVisibility').value;

			var kwdList = document.getElementById('tag');
			var tags = $("#add-link-tags").val();
			if (wg_id != usrID && kwdList.selectedIndex > 0) {
							extra_parms += "&tag=" + encodeURIComponent(kwdList.options[kwdList.selectedIndex].value);
						}
		}
	}

	rt = parseInt(document.getElementById('rectype_elt').value);

	if (rt) {
		return '&bib_rectype='+rt + extra_parms;
	}

	return '';
}

function add_note(e) {
	if (! e) e = window.event;

	var extra_parms = '',
		rt;
		var wg_id = parseInt(document.getElementById('rec_OwnerUGrpID').value);
	var vis = document.getElementById('rec_NonOwnerVisibility').value;
	var kwdList = document.getElementById('tag');
	var tags = $("#add-link-tags").val();
	if (document.getElementById('restrict_elt').checked) {
		if (wg_id) {
			if ( wg_id != usrID) {
			extra_parms = '&bib_workgroup=' + wg_id;
			}
			extra_parms += '&bib_visibility=' + vis;

			if (wg_id != usrID && kwdList.selectedIndex > 0) {
				extra_parms += "&tag=" + encodeURIComponent(kwdList.options[kwdList.selectedIndex].value);
		}
		}else {
			alert('Please select a group to which this record shall be restricted');
			document.getElementById('rec_OwnerUGrpID').focus();
			return;
		}
	}
	if (tags) {
		extra_parms += (extra_parms.match(/&tag=/))  ?  "," + tags  :  "&tag=" + tags; // warning! code assumes that &tag= is at the end of string
	}
	if ( <?= @$_REQUEST['related'] ? '1' : '0' ?> ) {
		extra_parms += '&related=<?= @$_REQUEST['related'] ?>';
		if (<?= @$_REQUEST['reltype'] ? '1' : '0' ?>) {
			extra_parms += '&reltype=<?= @$_REQUEST['reltype'] ?>';
		}
	}
	// added to pass on the title if the user got here from add.php? ... &t=  we just pass it back around
	extra_parms += '<?= @$_REQUEST['t'] ? '&t='.$_REQUEST['t'] : '' ?>';

		rt = parseInt(document.getElementById('rectype_elt').value);
		if (! rt) rt = "2";  //added ian 19/9/08 to re-enable notes as default

	if (document.getElementById('defaults_elt').checked) {
		defaults = [ rt, wg_id,"\"" + vis +"\"", "\"" + encodeURIComponent(kwdList.options[kwdList.selectedIndex].value) +"\"",
						"\"" + tags + "\"", document.getElementById('restrict_elt').checked?1:0];
		top.HEURIST.util.setDisplayPreference('addRecordDefaults', defaults.join(","));
	}else{
		top.HEURIST.util.setDisplayPreference('addRecordDefaults', "");
	}


	window.open('<?= HEURIST_URL_BASE?>records/add/addRecord.php?addref=1&db=<?=HEURIST_DBNAME?>&bib_rectype='+rt + extra_parms);

}

function cancelAdd(e) {
	if (! e) e = window.event;
	if (document.getElementById('defaults_elt').checked) {//save settings
		var rt = parseInt(document.getElementById('rectype_elt').value);
		var wg_id = parseInt(document.getElementById('rec_OwnerUGrpID').value);
		var vis = document.getElementById('rec_NonOwnerVisibility').value;
		var kwdList = document.getElementById('tag');
		var tags = $("#add-link-tags").val();
		defaults = [ rt, wg_id,"\"" + vis +"\"", "\"" + encodeURIComponent(kwdList.options[kwdList.selectedIndex].value) +"\"",
						"\"" + tags + "\"", document.getElementById('restrict_elt').checked?1:0];
		top.HEURIST.util.setDisplayPreference('addRecordDefaults', defaults.join(","));
	}else{ //reset saved setting
		top.HEURIST.util.setDisplayPreference('addRecordDefaults', "");
	}
	window.close();

}
  </script>

  <style type=text/css>
			.hide_workgroup .workgroup { display: none; }
			hr { margin: 20px 0; }
			#add-link-input { width: 100%; }
			#add-link-tags {width : 70%;float:right}
			p {line-height: 14px;}
  </style>

 </head>

<body class="popup" width=500 height=480 style="font-size: 11px;">


	<div border="0" id=maintable<?= @$_REQUEST['wg_id'] > 0 ? "" : " class=hide_workgroup" ?>>
	<div><?php
	 print  ''. @$_REQUEST['error_msg'] ? $_REQUEST['error_msg'] . '' : '' ;
				?>
	</div>
	<div>
		<label> Record type:</label>
					<?php
						$res = mysql_query("select distinct rty_ID,rty_Name,rty_Description, rtg_Name
						from defRecTypes left join defRecTypeGroups on rtg_ID = rty_RecTypeGroupID
						where rty_ShowInLists = 1 order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name");
					?>
		<select name="ref_type"  title="New record type" style="margin: 3px;" id="rectype_elt">
			<option selected disabled value="0">(select record type)</option>
				<?php
					$section = "";
					while ($row = mysql_fetch_assoc($res)) {
						if ($row["rtg_Name"] != $section) {
							if ($section) print "</optgroup>\n";
							$section = $row["rtg_Name"];
							print '<optgroup label="' . htmlspecialchars($section) . ' types">';
						}
				?>
			<option value="<?= $row["rty_ID"] ?>" title="<?= htmlspecialchars($row["rty_Description"]) ?>" <?= $row["rty_Name"]=="Notes" ? 'selected':'' ?> ><?= htmlspecialchars($row["rty_Name"]) ?></option>
				<?php
					}
				?>
		</optgroup>
		</select><br/>
	</div>

	<div>
		<div>
			<input type="checkbox" name="bib_workgroup_restrict" id="restrict_elt" value="1" style="vertical-align: middle" onclick="document.getElementById('maintable').className = this.checked? '' : 'hide_workgroup';" style="margin: 0; padding: 0;"<?= @$_REQUEST['wg_id'] > 0 ? " checked" : ""?>>
			<label for=restrict_elt>Restrict access</label>
		</div>
		<div class="resource workgroup" style="margin:10px 0">
			<div class="input-row workgroup">
				<div class="input-header-cell">Select Work Group</div>
				<div class="input-cell">
					<select name="rec_OwnerUGrpID" id="rec_OwnerUGrpID" style="width: 200px;" onchange="buildworkgroupTagselect(options[selectedIndex].value)">
						<option value="0" disabled selected>(select group)</option>
											<?php
						print "      <option value=".get_user_id().(@$_REQUEST['wg_id']==get_user_id() ? " selected" : "").">".htmlspecialchars(get_user_name())." only</option>\n";
						$res = mysql_query('select '.GROUPS_ID_FIELD.', '.GROUPS_NAME_FIELD.' from '.USERS_DATABASE.'.'.USER_GROUPS_TABLE.' left join '.USERS_DATABASE.'.'.GROUPS_TABLE.' on '.GROUPS_ID_FIELD.'='.USER_GROUPS_GROUP_ID_FIELD.' where '.USER_GROUPS_USER_ID_FIELD.'='.get_user_id().' and '.GROUPS_TYPE_FIELD.'!="Usergroup" order by '.GROUPS_NAME_FIELD);
						$wgs = array();
						while ($row = mysql_fetch_row($res)) {
							print "      <option value=".$row[0].(@$_REQUEST['wg_id']==$row[0] ? " selected" : "").">".htmlspecialchars($row[1])." only</option>\n";
							array_push($wgs, $row[0]);
						}
											?>
					</select>
				</div>
			</div>

			<div class="input-row workgroup">
				<div class="input-header-cell">Workgroup tag:</div>
				<div class="input-cell"><select name="tag" id="tag" style="width: 200px;"></select></div>
			</div>

			<div class="input-row workgroup">
				<div class="input-header-cell">Outside this group:</div>
				<div class="input-cell">
					<select name="rec_NonOwnerVisibility" id="rec_NonOwnerVisibility" style="width: 200px;">
						<option value="public">record is public</option>
						<option value="viewable">record is viewable</option>
						<option value="hidden">record is hidden</option>
					</select>
				</div>
			</div>
		</div>
	</div>

	</div>

	<div class="separator_row" style="margin:20px 0"></div>
	<a id="show-adv-link" href="#">more...</a>
	<div id=advanced-section style="display: none;">
		<h2>Advanced</h2>
		<div>Add these personal tags: <input id=add-link-tags></div>
		<div style="clear:both;margin-top:20px">Hyperlink this URL in your web page or a desktop shortcut to provide one-click addition of Heurist records with these characteristics:
		<textarea id=add-link-input></textarea>
		<p><a id=broken-kwd-link target=_blank style="display: none;">search for records added by non workgroup members using the above link</a></p>
		</div>
	</div>

	<div class="separator_row" style="margin:20px 0"></div>

	<div>
		<input type="checkbox" name="use_as_defaults" id="defaults_elt" value="1" style="margin: 0; padding: 0; vertical-align: middle;"<?= $addRecDefaults ? " checked" : ""?>>
		<label for=restrict_elt title="Default to these values for future additions (until changed)">Set as defaults</label>
	</div>
	<div>
		<input type="button" style="font-weight: bold;" value="Add" onclick="add_note(event);">
		&nbsp;&nbsp;
		<input type="button" value="Cancel" onclick="cancelAdd(event);" id="note_cancel">
	</div

</body>
</html>
