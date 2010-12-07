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

$res = mysql_query('select dtl_RecID, dty_Name, dty_PtrConstraints, rec_ID, rec_Title, rty_Name
                      from defDetailTypes
                 left join recDetails on dty_ID = dtl_DetailTypeID
                 left join Records on rec_ID = dtl_Value
                 left join defRecTypes on rty_ID = rec_RecTypeID
                     where dty_Type = "resource"
                       and dty_PtrConstraints > 0
                       and rec_RecTypeID not in (dty_PtrConstraints)');
$bibs = array();
while ($row = mysql_fetch_assoc($res))
	$bibs[$row['dtl_RecID']] = $row;

?>
<div style="font-weight: bold;">
 Records with resource pointers to the wrong rec_RecTypeID
 &nbsp;&nbsp;
 <a target=_new href='../../search/search.html?w=all&q=ids:<?= join(',', array_keys($bibs)) ?>'>(show all in search)</a>
</div>
<table>
<?php
foreach ($bibs as $row) {
?>
 <tr>
  <td><a target=_new href='../../records/editrec/edit.html?bib_id=<?= $row['dtl_RecID'] ?>'><?= $row['dtl_RecID'] ?></a></td>
  <td><?= $row['dty_Name'] ?></td>
  <td>points to</td>
  <td><?= $row['rec_ID'] ?> (<?= $row['rty_Name'] ?>) - <?= substr($row['rec_Title'], 0, 50) ?></td>
 </tr>
<?php
}
?>
</table>

<hr>

<?php
$res = mysql_query('select dtl_RecID, dty_Name, a.rec_Title
                      from recDetails
                 left join defDetailTypes on dty_ID = dtl_DetailTypeID
                 left join Records a on a.rec_ID = dtl_RecID
                 left join Records b on b.rec_ID = dtl_Value
                     where dty_Type = "resource"
		               and a.rec_ID is not null
                       and b.rec_ID is null');
$bibs = array();
while ($row = mysql_fetch_assoc($res))
	$bibs[$row['dtl_RecID']] = $row;

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
  <td><input type=checkbox name=bib_cb value=<?= $row['dtl_RecID'] ?>></td>
  <td><a target=_new href='../../records/editrec/edit.html?bib_id=<?= $row['dtl_RecID'] ?>'><?= $row['dtl_RecID'] ?></a></td>
  <td><?= $row['rec_Title'] ?></td>
  <td><?= $row['dty_Name'] ?></td>
 </tr>
<?php
}
print "</table>\n";



?>
</body>
</html>

