<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

?>

<?php

define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

if (! is_logged_in()) return;

mysql_connection_db_select(DATABASE);

?>
<html>
 <head>
  <link rel=stylesheet href="<?=HEURIST_SITE_PATH?>common/css/global.css">
  <title>Add new record</title>

  <script src="<?=HEURIST_SITE_PATH?>external/jquery/jquery.js"></script>

  <script>


$(document).ready(function() {
	$("#show-adv-link").click(function() {
		$(this).hide();
		$('#advanced-section').show();
		return false;
	});

	$("#rectype_elt, #restrict_elt, #rec_OwnerUGrpID, #tag, #rec_NonOwnerVisibility, #add-link-title, #add-link-tags").change(update_link);

	var matches = location.search.match(/wg_id=(\d+)/);
	buildworkgroupTagselect(matches ? matches[1] : null);
});

function buildworkgroupTagselect(wgID) {
	var i, l, kwd, val;
	$("#tag").empty();
	$("<option value='' selected disabled>(select workgroup tag)</option>").appendTo("#tag");
	l = top.HEURIST.user.workgroupTagOrder.length;
	for (i = 0; i < l; ++i) {
		kwd = top.HEURIST.user.workgroupTags[top.HEURIST.user.workgroupTagOrder[i]];
		if (! wgID  ||  wgID == kwd[0]) {
			val = top.HEURIST.workgroups[kwd[0]].name + "\\" + kwd[1];
			$("<option value='" + val + "'>" + kwd[1] + "</option>").appendTo("#tag");
		}
	}
}


function update_link() {
	var base = "<?= HEURIST_URL_BASE?>records/add/addRecord.php?addref=1&instance=<?=HEURIST_INSTANCE?>";
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
	if ($("#tag").val()) {
		$("#broken-kwd-link").show()[0].href =
			"../?w=all&q=tag:\"" + $("#tag").val().replace(/\\/, "") + "\"" +
			          " -tag:\"" + $("#tag").val() + "\"";
	}
}

function compute_args() {
	var extra_parms = '';
	if (document.getElementById('restrict_elt').checked) {
		var wg_id = parseInt(document.getElementById('rec_OwnerUGrpID').value);
		if (wg_id) {
			extra_parms = '&bib_workgroup=' + wg_id;
			extra_parms += '&bib_visibility=' + document.getElementById('rec_NonOwnerVisibility').value;

			var kwdList = document.getElementById('tag');
			if (kwdList.selectedIndex > 0) extra_parms += "&tag=" + encodeURIComponent(kwdList.options[kwdList.selectedIndex].value);
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

	var extra_parms = '';
	if (document.getElementById('restrict_elt').checked) {
		var wg_id = parseInt(document.getElementById('rec_OwnerUGrpID').value);
		if (wg_id) {
			extra_parms = '&bib_workgroup=' + wg_id;
			extra_parms += '&bib_visibility=' + document.getElementById('rec_NonOwnerVisibility').value;

			var kwdList = document.getElementById('tag');
			if (kwdList.selectedIndex > 0) extra_parms += "&tag=" + encodeURIComponent(kwdList.options[kwdList.selectedIndex].value);
		}
		else {
			alert('Please select a group to which this record shall be restricted');
			document.getElementById('rec_OwnerUGrpID').focus();
			return;
		}
	}
	if (<?= @$_REQUEST['related'] ? '1' : '0' ?>) {
		extra_parms += '&related=<?= @$_REQUEST['related'] ?>';
		if (<?= @$_REQUEST['reltype'] ? '1' : '0' ?>) {
			extra_parms += '&reltype=<?= @$_REQUEST['reltype'] ?>';
		}
	}
	// added to pass on the title if the user got here from add.php? ... &t=  we just pass it back around
	extra_parms += '<?= @$_REQUEST['t'] ? '&t='.$_REQUEST['t'] : '' ?>';

	if (document.getElementById('note_elt').checked) {
		rt = "2";
	} else {
		rt = parseInt(document.getElementById('rectype_elt').value);
	}

    if (! rt) rt = "2";  //added ian 19/9/08 to re-enable notes as default

	top.location.href = '<?= HEURIST_URL_BASE?>records/add/addRecord.php?addref=1&instance=<?=HEURIST_INSTANCE?>&bib_rectype='+rt + extra_parms;

}
function note_type_click() {
	var ref_elt = document.getElementById('reference_elt');
	document.getElementById('rectype_elt').disabled = !ref_elt.checked;
}


  </script>

  <style type=text/css>
.hide_workgroup .workgroup { visibility: hidden; }
hr { margin: 20px 0; }
#add-link-input { width: 95%; }
#add-link-tags {width : 70%;}
  </style>

 </head>

 <body width=500 height=400 style="font-size: 11px;">


  <table border="0" id=maintable<?= @$_REQUEST['wg_id'] > 0 ? "" : " class=hide_workgroup" ?>>
   <tr><td colspan=3 style="color: red; margin-bottom:5px;">
<?php
	 print  ''. @$_REQUEST['error_msg'] ? $_REQUEST['error_msg'] . '' : '' ;

?>
   </td></tr>
   <tr>
    <td colspan=3> &nbsp;</td>
   </tr>
   <tr>
    <td><nobr><label><input type="radio" name ="a" id="note_elt" checked onclick="note_type_click();"> Note</label></nobr></td>
   </tr>
   <tr>
    <td><nobr><label><input type="radio" name="a" id="reference_elt" onclick="note_type_click();"> Record type:</label></nobr></td>
    <td colspan=2>
<?php
	$res = mysql_query("select distinct rty_ID,rty_Name,rty_Description, rty_OrderInGroup, rtg_Name, rtg_Order
						from defRecTypes left join defRecTypeGroups on rtg_ID = rty_RecTypeGroupID
						where 1 order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name");
?>
     <select name="ref_type"  title="New bibliographic record type" style="margin: 3px;" id="rectype_elt" onChange='document.getElementById("reference_elt").checked = true; document.getElementById("note_elt").checked = false;'>
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
  <option value="<?= $row["rty_ID"] ?>" title="<?= htmlspecialchars($row["rty_Description"]) ?>"><?= htmlspecialchars($row["rty_Name"]) ?></option>
<?php
	}
?>
      </optgroup>
     </select>
    </td>
   </tr>

   <tr>
    <td colspan=3></td>
   </tr>

   <tr>
    <td></td>
    <td style="vertical-align: top;">
     <nobr style="vertical-align: middle;">
      <input type="checkbox" name="bib_workgroup_restrict" id="restrict_elt" value="1" onclick="document.getElementById('maintable').className = this.checked? '' : 'hide_workgroup';" style="margin: 0; padding: 0;"<?= @$_REQUEST['wg_id'] > 0 ? " checked" : ""?>>
      <label for=restrict_elt>Restrict access</label>
     </nobr>
    </td>
    <td class=workgroup><nobr>
     <select name="rec_OwnerUGrpID" id="rec_OwnerUGrpID" style="width: 200px;" onchange="buildworkgroupTagselect(options[selectedIndex].value)">
      <option value="0" disabled selected>(select group)</option>
<?php
	$res = mysql_query('select '.GROUPS_ID_FIELD.', '.GROUPS_NAME_FIELD.' from '.USERS_DATABASE.'.'.USER_GROUPS_TABLE.' left join '.USERS_DATABASE.'.'.GROUPS_TABLE.' on '.GROUPS_ID_FIELD.'='.USER_GROUPS_GROUP_ID_FIELD.' where '.USER_GROUPS_USER_ID_FIELD.'='.get_user_id().' and '.GROUPS_TYPE_FIELD.'!="Usergroup" order by '.GROUPS_NAME_FIELD);
	$wgs = array();
	while ($row = mysql_fetch_row($res)) {
		print "      <option value=".$row[0].(@$_REQUEST['wg_id']==$row[0] ? " selected" : "").">".htmlspecialchars($row[1])." only</option>\n";
		array_push($wgs, $row[0]);
	}
?>
     </select>

     </nobr>
    </td>
   </tr>

   <tr class=workgroup>
    <td>
    </td>
    <td style="text-align: right;">
     Workgroup tag:
    </td>
    <td>
     <select name="tag" id="tag" style="width: 200px;">
     </select>
    </td>
   </tr>

   <tr class=workgroup>
    <td>
    </td>
    <td style="text-align: right;">
     Outside this group:
    </td>
    <td>
     <select name="rec_NonOwnerVisibility" id="rec_NonOwnerVisibility" style="width: 200px;">
      <option value="Visible">record is read-only</option>
      <option value="Hidden">record is hidden</option>
     </select>
    </td>
   </tr>

  </table>

  <br>

  <table style="width: 100%;">
   <tr>
    <td class=workgroup colspan =2>
      <nobr>
      <input type="button" style="font-weight: bold;" value="Add" onclick="add_note(event);">
      &nbsp;&nbsp;
      <input type="button" value="Cancel" onclick="window.close();" id="note_cancel">
     </nobr>
    </td>
   </tr>
  </table>

  <hr>
  <div id=advanced-section style="display: block;">
   <div>
   <h2>Advanced</h2>
   Add these personal tags: <input id=add-link-tags></div>
   <p>Hyperlink this URL in your web page or a desktop shortcut <br>to provide one-click addition of Heurist records with these characteristics:<br>
   <textarea id=add-link-input></textarea></p>
   <p><a id=broken-kwd-link target=_blank style="display: none;">search for records added by non workgroup members using the above link</a></p>
  </div>

 </body>
</html>
