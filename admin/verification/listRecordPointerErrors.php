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

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
?>

<html>

	<head>
		<script type=text/javascript>
			function open_selected() {
				var cbs = document.getElementsByName('recCB');
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
				link.href = '../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:' + ids;
				return true;
			}
		</script>

		<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
		<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
		<style type="text/css">
			h3, h3 span {
				display: inline-block;
				padding:0 0 10px 0;
			}
			Table tr td {
				line-height:2em;
			}
		</style>
	</head>

	<body class="popup">
		<?php

			mysql_connection_select(DATABASE);

			$res = mysql_query('select dtl_RecID, dty_Name, dty_PtrTargetRectypeIDs, rec_ID, rec_Title, rty_Name
			from defDetailTypes
			left join recDetails on dty_ID = dtl_DetailTypeID
			left join Records on rec_ID = dtl_Value
			left join defRecTypes on rty_ID = rec_RecTypeID
			where dty_Type = "resource"
			and dty_PtrTargetRectypeIDs > 0
			and rec_RecTypeID not in (dty_PtrTargetRectypeIDs)');
			$bibs = array();
			while ($row = mysql_fetch_assoc($res))
			$bibs[$row['dtl_RecID']] = $row;

		?>
		<div class="banner">
			<h2>Invalid pointer check</h2>
		</div>
		<div id="page-inner">

			These checks look for invalid record pointers within the Heurist database. These should arise rarely.
			<p> Click the hyperlinked number at the start of each row to open an edit form on that record. Look for pointer fields which do not display data or dispaly a warning.

			<hr>

			<div>
				<h3>Records with record pointers to the wrong rec_RecTypeID</h3>
				<span><a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:<?= join(',', array_keys($bibs)) ?>'>(show results as search)</a></span>
			</div>
			<table>
				<?php
					foreach ($bibs as $row) {
					?>
					<tr>
						<td><a target=_new href='../../records/edit/editRecord.html?db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'><?= $row['dtl_RecID'] ?></a></td>
						<td><?= $row['dty_Name'] ?></td>
						<td>points to</td>
						<td><?= $row['rec_ID'] ?> (<?= $row['rty_Name'] ?>) - <?= substr($row['rec_Title'], 0, 50) ?></td>
					</tr>
					<?php
					}
				?>
			</table>
			[end of list]
			<p>

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
			<div>
				<h3>Records with record pointers to non-existent records</h3>
				<span>
					<a target=_new href='../../search/search.html?db=<?= HEURIST_DBNAME?>&w=all&q=ids:<?= join(',', array_keys($bibs)) ?>'>(show results as search)</a>
					<a target=_new href='#' id=selected_link onClick="return open_selected();">(show selected as search)</a>
				</span>
			</div>
			<table>
			<?php
				foreach ($bibs as $row) {
				?>
				<tr>
					<td><input type=checkbox name="recCB" value=<?= $row['dtl_RecID'] ?>></td>
					<td><a target=_new href='../../records/edit/editRecord.html?db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'><?= $row['dtl_RecID'] ?></a></td>
					<td><?= $row['rec_Title'] ?></td>
					<td><?= $row['dty_Name'] ?></td>
				</tr>
				<?php
				}
				print "</table>\n";
			?>
			[end of list]
			<p><hr>

		</div>
	</body>
</html>

