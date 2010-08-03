<?php

require_once(dirname(__FILE__).'/../common/connect/cred.php');
require_once(dirname(__FILE__).'/../common/connect/db.php');

if (! is_logged_in()) return;

mysql_connection_db_select(DATABASE);

?>
<html>
 <head>
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/heurist.css">

  <title>Set ratings</title>

  <script type="text/javascript">

function set_ratings() {
	var i_rating = document.getElementById('ratings_interest');
	var q_rating = document.getElementById('ratings_quality');
	var c_rating = document.getElementById('ratings_content');


	var action_fr = parent.document.getElementById('i_action').contentWindow;
	var bkmk_ids_elt = action_fr.document.getElementById('bkmk_ids');
	if (! action_fr  ||  ! bkmk_ids_elt) {
		alert('Problem contacting server - try again in a moment');
		return;
	}

	var action_elt = action_fr.document.getElementById('action');
	var i_r = action_fr.document.getElementById('i_rating');
	var q_r = action_fr.document.getElementById('q_rating');
	var c_r = action_fr.document.getElementById('c_rating');

	if (! action_elt  ||  ! i_r  ||  ! q_r  ||  ! c_r) {
		alert('Problem contacting server - try again in a moment');
		return;
	}

	i_r.value = i_rating.value;
	q_r.value = q_rating.value;
	c_r.value = c_rating.value;

	var bkmk_ids_list = top.HEURIST.search.get_bkmk_ids();
	bkmk_ids_elt.value = bkmk_ids_list.join(',');
	action_elt.value = 'set_ratings';

	action_elt.form.submit();

	setTimeout(function() { window.close(); }, 10);
}

  </script>
 </head>

 <body width=260 height=160 style="width: 250px; height: 140px; margin: 3px; background-color: transparent;">

   <table>
    <tr>
     <td style="font-weight: bold; margin-bottom: 5px;" colspan="3"><nobr>Set ratings</nobr></td>
    </tr>

    <tr>
     <td>&nbsp;&nbsp;</td>
     <td style="text-align: right;">Interest</td>
     <td><?php print_ratings_pulldown('ratings_interest', 'ri_id', 'ri_label'); ?></td>
    </tr>

    <tr>
     <td>&nbsp;&nbsp;</td>
     <td style="text-align: right;">Content</td>
     <td><?php print_ratings_pulldown('ratings_content', 'rc_id', 'rc_label'); ?></td>
    </tr>

    <tr>
     <td>&nbsp;&nbsp;</td>
     <td style="text-align: right;">Quality</td>
     <td><?php print_ratings_pulldown('ratings_quality', 'rq_id', 'rq_label'); ?></td>
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
<?php

function print_ratings_pulldown($table, $id_col, $label_col) {
	$res = mysql_query("select $id_col, $label_col from $table order by $id_col desc");
	print '<select name="'.$table.'" id="'.$table.'" style="width: 150px;">';
	while ($row=mysql_fetch_row($res)) {
		if ($row[0] == 2)
			print '<option value="'.$row[0].'" selected>'.htmlspecialchars($row[1]).'</option>';
		else
			print '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
	}
	print '</select>';
}

?>
