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

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

if (! is_logged_in()) return;

mysql_connection_db_select(DATABASE);

?>
<html>
<head>
<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
<title>Set ratings</title>

<script type="text/javascript">

function set_ratings() {

	var elements = document.getElementsByName('r');
	for (var i=0, iLen=elements.length; i<iLen; i++) {
  		if (elements[i].checked) {
    		value = elements[i].value;
    		break;
  		}
	}

	var action_fr = parent.document.getElementById('i_action').contentWindow;
	var bkmk_ids_elt = action_fr.document.getElementById('bkmk_ids');
	if (! action_fr  ||  ! bkmk_ids_elt) {
		alert('Problem contacting server - try again in a moment');
		return;
	}

	var action_elt = action_fr.document.getElementById('action');
	var updateRatingElt = action_fr.document.getElementById('rating');

	if (! action_elt  ||  ! updateRatingElt  ) {
		alert('Problem contacting server - try again in a moment');
		return;
	}

	updateRatingElt.value = value;

	var bkmk_ids_list = top.HEURIST.search.getSelectedBkmIDs().get();
	bkmk_ids_elt.value = bkmk_ids_list.join(',');
	action_elt.value = 'set_ratings';

	action_elt.form.submit();

	setTimeout(function() { window.close(); }, 10);
}
</script>
<style>
.lblr{
background-image: url(../../common/images/star-yellow.png);
display:block;
height:14px;
}
</style>
</head>

<body class="popup" width="200" height="160">

		<table>
				<tr><td><input type="radio" value="0" name="r" id="r0"></td><td><label for="r0">No Rating</label></td></tr>
				<tr><td><input type="radio" value="1" name="r" id="r1"></td><td><label for="r1" class="lblr" style="width:14px;"></label></td></tr>
				<tr><td><input type="radio" value="2" name="r" id="r2"></td><td><label for="r2" class="lblr" style="width:24px;"></label></td></tr>
				<tr><td><input type="radio" value="3" name="r" id="r3"></td><td><label for="r3" class="lblr" style="width:38px;"></label></td></tr>
				<tr><td><input type="radio" value="4" name="r" id="r4"></td><td><label for="r4" class="lblr" style="width:50px;"></label></td></tr>
				<tr><td><input type="radio" value="5" name="r" id="r5"></td><td><label for="r5" class="lblr" style="width:64px;"></label></td></tr>
		</table>
		<div style="text-align: right; padding-top: 10px;">
			<input type="button" value="Set ratings" style="font-weight: bold;" onclick="set_ratings();">
		</div>

</body>
</html>
