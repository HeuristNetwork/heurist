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

require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../common/php/dbMySqlWrappers.php');

if (! is_logged_in()) return;

mysql_connection_db_select(DATABASE);

?>
<html>
 <head>
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/import.css">

  <title>Set ratings</title>

  <script type="text/javascript">

function set_ratings() {
	var ratingSelect = document.getElementById('overall-rating');


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

	updateRatingElt.value = ratingSelect.value;

	var bkmk_ids_list = top.HEURIST.search.get_bkmk_ids();
	bkmk_ids_elt.value = bkmk_ids_list.join(',');
	action_elt.value = 'set_ratings';

	action_elt.form.submit();

	setTimeout(function() { window.close(); }, 10);
}

  </script>
 </head>

 <body width=350 height=250>

   <table>
    <tr>
     <td style="font-weight: bold; margin-bottom: 5px;" colspan="3"><nobr>Set ratings</nobr></td>
    </tr>

    <tr>
     <td>&nbsp;&nbsp;</td>
     <td style="text-align: right;">Rating</td>
     <td><select name="overall-rating" id="overall-rating" style="width: 150px;">
			<option value="0">Not Rated</option>
			<option value="1">*</option>
			<option value="2">**</option>
			<option value="3">***</option>
			<option value="4">****</option>
			<option value="5">*****</option>
         </select>
     </td>
    </tr>

    <tr>
     <td colspan="2">&nbsp;</td>
     <td style="padding-top: 10px;">
      <input type="button" value="Set ratings" style="font-weight: bold;" onclick="set_ratings();">
     </td>
    </tr>

   </table>
  </form>
 </body>
</html>
