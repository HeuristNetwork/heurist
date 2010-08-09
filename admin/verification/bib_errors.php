<html>

<head>
 <script type=text/javascript>
  function open_selected() {
	var cbs = document.getElementsByName('bib_cb');
	if (!cbs  ||  ! cbs instanceof Array)
		return false;
	var ids = '';
	for (var i = 0; i < cbs.length; i++) {
		if (cbs[i].checked)
			ids = ids + cbs[i].value + ',';
	}
	var link = document.getElementById('selected_link');
	if (!link)
		return false;
	link.href = '../../search/search.html?w=all&q=ids:' + ids;
	return true;
  }
 </script>
</head>

<body>
<?php

require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');

mysql_connection_db_select(DATABASE);

$res = mysql_query('select rd_rec_id, rdt_name, rdt_constrain_rec_type, rec_id, rec_title, rt_name
                      from rec_detail_types
                 left join rec_details on rdt_id = rd_type
                 left join records on rec_id = rd_val
                 left join rec_types on rt_id = rec_type
                     where rdt_type = "resource"
                       and rdt_constrain_rec_type > 0
                       and rec_type != rdt_constrain_rec_type');
$bibs = array();
while ($row = mysql_fetch_assoc($res))
	$bibs[$row['rd_rec_id']] = $row;

?>
<div style="font-weight: bold;">
 Records with resource pointers to the wrong rec_type
 &nbsp;&nbsp;
 <a target=_new href='../../search/search.html?w=all&q=ids:<?= join(',', array_keys($bibs)) ?>'>(show all in search)</a>
</div>
<table>
<?php
foreach ($bibs as $row) {
?>
 <tr>
  <td><a target=_new href='../../records/editrec/heurist-edit.html?bib_id=<?= $row['rd_rec_id'] ?>'><?= $row['rd_rec_id'] ?></a></td>
  <td><?= $row['rdt_name'] ?></td>
  <td>points to</td>
  <td><?= $row['rec_id'] ?> (<?= $row['rt_name'] ?>) - <?= substr($row['rec_title'], 0, 50) ?></td>
 </tr>
<?php
}
?>
</table>

<hr>

<?php
$res = mysql_query('select rd_rec_id, rdt_name, a.rec_title
                      from rec_details
                 left join rec_detail_types on rdt_id = rd_type
                 left join records a on a.rec_id = rd_rec_id
                 left join records b on b.rec_id = rd_val
                     where rdt_type = "resource"
		               and a.rec_id is not null
                       and b.rec_id is null');
$bibs = array();
while ($row = mysql_fetch_assoc($res))
	$bibs[$row['rd_rec_id']] = $row;

?>
<div style="font-weight: bold;">
 Records with resource pointers to non-existent records
 &nbsp;&nbsp;
 <a target=_new href='../../search/search.html?w=all&q=ids:<?= join(',', array_keys($bibs)) ?>'>(show all in search)</a>
 &nbsp;&nbsp;
 <a target=_new href='#' id=selected_link onclick="return open_selected();">(show selected in search)</a>
</div>
<table>
<?php
foreach ($bibs as $row) {
?>
 <tr>
  <td><input type=checkbox name=bib_cb value=<?= $row['rd_rec_id'] ?>></td>
  <td><a target=_new href='../../records/editrec/heurist-edit.html?bib_id=<?= $row['rd_rec_id'] ?>'><?= $row['rd_rec_id'] ?></a></td>
  <td><?= $row['rec_title'] ?></td>
  <td><?= $row['rdt_name'] ?></td>
 </tr>
<?php
}
print "</table>\n";



?>
</body>
</html>

