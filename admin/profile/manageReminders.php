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

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

if (@$_REQUEST["action"] == "delete"  &&  @$_REQUEST["rem_ID"]) {
	mysql_connection_db_overwrite(DATABASE);
	mysql_query("delete from usrReminders where rem_ID = " . intval($_REQUEST["rem_ID"]) . " and rem_OwnerUGrpID = " . get_user_id());
}

$future = (! @$_REQUEST["show"]  ||  $_REQUEST["show"] === "future");

?>
<html>
 <head>
  <title>Heurist reminders</title>
  <link rel="icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
  <style>
   div#page { padding: 10px; }
   div#page .headline { margin-bottom: 10px; }
   div#page img { border: none; }
  </style>
  <script>
	function del(rem_id) {
		document.getElementById("rem_id_input").value = rem_id;
		document.getElementById("action_input").value = "delete";
		document.getElementById("action_input").form.submit();
	}
  </script>
 </head>
 <body class="popup" width=600 height=300>
  <div>
   <div class=headline>Reminders</div>

   <form>
    <input type=hidden name="db" value="<?= HEURIST_DBNAME?>">
    <label for=show-future>
     <input type=radio name=show value=future id=show-future <?= $future ? "checked" : "" ?> onchange="form.submit();">
     Show future reminders only
    </label>
    <br>
    <label for=show-all>
     <input type=radio name=show value=all id=show-all <?= $future ? "" : "checked" ?> onchange="form.submit();">
     Show all reminders
    </label>
   </form>

   <form method=post>
    <input type=hidden name="db" value="<?= HEURIST_DBNAME?>">
    <input type=hidden name=rem_ID id=rem_id_input>
    <input type=hidden name=action id=action_input>
   </form>

   <table class=reminder width="100%">
    <tr>
     <th width="20"></th>
     <th width="200">Record</th>
     <th width="100">Recipient</th>
     <th width="100">Notification frequency</th>
     <th>Message</th>
    </tr>
<?php

mysql_connection_db_select(DATABASE);

$future_clause = $future ? "and rem_Freq != 'once' or rem_StartDate > now()" : "";

$res = mysql_query("select usrReminders.*, rec_Title, grp.".GROUPS_NAME_FIELD.",  concat(usr.".USERS_FIRSTNAME_FIELD.",' ',usr.".USERS_LASTNAME_FIELD.") as username
					  from usrReminders
				 left join Records on rec_ID = rem_RecID
				 left join ".USERS_DATABASE.".".GROUPS_TABLE." grp on grp.".GROUPS_ID_FIELD." = rem_ToWorkgroupID
				 left join ".USERS_DATABASE.".".USERS_TABLE." usr on usr.".USERS_ID_FIELD." = rem_ToUserID
					 where rem_OwnerUGrpID = " . get_user_id() . "
					 $future_clause
				  order by rem_RecID, rem_StartDate");


if (mysql_num_rows($res) == 0) {
	print "<tr><td></td><td colspan=4>No reminders</td></tr>";
}

while ($row = mysql_fetch_assoc($res)) {
	$recipient = $row[GROUPS_NAME_FIELD] ? $row[GROUPS_NAME_FIELD] :
					($row["username"] ? $row["username"] : $row["rem_ToEmail"]);
?>
    <tr>
     <td><a title=delete href=# onclick="del(<?= $row["rem_ID"] ?>); return false;"><img src="<?=HEURIST_SITE_PATH?>common/images/cross.png"></a></td>
     <td><a href="<?=HEURIST_SITE_PATH?>records/edit/editRecord.html?recID=<?= $row["rem_RecID"] ?>&db=<?= HEURIST_DBNAME?>#personal"><b><?= $row["rec_Title"] ?></b></a></td>
     <td><b><?= $recipient ?></b></td>
     <td><b><?= $row["rem_Freq"] ?></b> from <b><?= $row["rem_StartDate"] ?></b></td>
     <td><?= $row["rem_Message"] ?></td>
    </tr>
<?php
}

?>
  </div>
 </body>
</html>
