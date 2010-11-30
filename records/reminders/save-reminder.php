<?php

/* Save a reminder */

define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

if (! is_logged_in()) return;

mysql_connection_db_overwrite(DATABASE);


$rec_id = intval($_POST["bib_id"]);
$rem_id = intval($_POST["rem_ID"]);
if ($rec_id  &&  $_POST["save-mode"] == "add") {
	if ($_POST["reminder-user"]) {
		$res = mysql_query("select usr.ugr_ID from ".USERS_DATABASE.".sysUGrps usr where concat(usr.ugr_FirstName, ' ', usr.ugr_LastName) = '" . addslashes($_POST["reminder-user"]) . "'");
		$user = mysql_fetch_row($res);
		if ($user) {
			$_POST["reminder-user"] = intval($user[0]);
		}
		else {
			print "({ error: \"User '" . addslashes($_POST["reminder-user"]) . "' not found\" })";
			return;
		}
	}

	$rem = array(
		"rem_RecID" => $rec_id,
		"rem_OwnerUGrpID" => get_user_id(),
		"rem_ToUserID" => ($_POST["reminder-user"] > 0 ? $_POST["reminder-user"] : null),
		"rem_wg_id" => ($_POST["reminder-group"] > 0 ? $_POST["reminder-group"] : null),
		"rem_email" => $_POST["reminder-email"],
		"rem_startdate" => $_POST["reminder-when"],
		"rem_freq" => $_POST["reminder-frequency"],
		"rem_message" => $_POST["reminder-message"],
		"rem_nonce" => dechex(rand())
	);

	if ($_POST["mail-now"]) {
		/* user clicked "notify immediately" */
		require_once("reminder.php");
		print sendReminderEmail($rem);
	} else {
		mysql__insert("reminders", $rem);
		if (mysql_error()) {
			print "({ error: \"Internal database error - " . mysql_error() . "\" })";
			return;
		}

		$rem_id = mysql_insert_id();
		$res = mysql_query("select * from reminders where rem_ID = $rem_id");
		$rem = mysql_fetch_assoc($res);
?>
({ reminder: {
     id: <?= $rem["rem_ID"] ?>,
     user: <?= intval($rem["rem_ToUserID"]) ?>,
     group: <?= intval($rem["rem_wg_id"]) ?>,
     email: "<?= slash($rem["rem_email"]) ?>",
     message: "<?= slash($rem["rem_message"]) ?>",
     when: "<?= slash($rem["rem_startdate"]) ?>",
     frequency: "<?= slash($rem["rem_freq"]) ?>"
  }
})
<?php
	}

} else if ($rec_id  &&  $rem_id  &&  $_POST["save-mode"] == "delete") {
	$res = mysql_query("delete from reminders where rem_ID=$rem_id and rem_RecID=$rec_id and rem_OwnerUGrpID=".get_user_id());
	if (! mysql_error()) {
		print "1";
	} else {
		print "({ error: \"Internal database error - " . mysql_error() . "\" })";
	}
}

?>
