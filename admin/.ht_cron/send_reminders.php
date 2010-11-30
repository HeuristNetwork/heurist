<?php

require_once(dirname(__FILE__).'/../../common/config/heurist-instances.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__)."/../../records/reminders/reminder.php");

foreach (get_all_instances() as $prefix => $instance) {

	mysql_connection_db_select($instance["db"]);

	$res = mysql_query("select * from usrReminders where rem_StartDate <= curdate();");

	$reminders = array();

	//echo "checking reminders...\n";

	while ($row = mysql_fetch_assoc($res)) {
		$year = substr($row['rem_StartDate'], 0, 4);
		$month = substr($row['rem_StartDate'], 5, 2);
		$day = substr($row['rem_StartDate'], 8, 2);
		$start_timestamp = mktime(0, 0, 0, $month, $day, $year);

	/*
		echo 'id:              ' . $row['rem_ID'] . "\n";
		echo 'startdate:       ' . $row['rem_StartDate'] . "\n";
		echo 'freq:            ' . $row['rem_Freq'] . "\n";
		echo 'start_timestamp: ' . $start_timestamp . "\n";
		echo 'weekday:         ' . date('w', $start_timestamp) . "\n";
	*/

		if (($row['rem_Freq'] == 'once'  &&  $row['rem_StartDate'] == date('Y-m-d'))  ||
			($row['rem_Freq'] == 'daily')) {
			$reminders[] = $row;
		}
		else if ($row['rem_Freq'] == 'weekly') {
			if (date('w') == date('w', $start_timestamp)) {
				$reminders[] = $row;
			}
		}
		else if ($row['rem_Freq'] == 'monthly') {
			if (date('d') == date('d', $start_timestamp)) {
				$reminders[] = $row;
			}
			else if (!checkdate(date('m'), $day, date('Y'))  &&				// $day doesn't exist for this month
					 !checkdate(date('m'), date('d') + 1, date('Y'))) {		// and it's currently the last day of the month
				$reminders[] = $row;
			}
		}
		else if ($row['rem_Freq'] == 'annually') {
			if (date('m-d') == date('m-d', $start_timestamp)) {
				$reminders[] = $row;
			}
		}
	}

	//echo "\nvalid reminders:\n";
	foreach ($reminders as $reminder) {
		sendReminderEmail($reminder, $instance["userdb"], ($prefix? $prefix.".": "").HOST_BASE);
	}
}

?>
