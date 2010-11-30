<?php

require_once(dirname(__FILE__).'/../../common/config/heurist-instances.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__)."/../../records/reminders/reminder.php");

foreach (get_all_instances() as $prefix => $instance) {

	mysql_connection_db_select($instance["db"]);

	$res = mysql_query("select * from reminders where rem_startdate <= curdate();");

	$reminders = array();

	//echo "checking reminders...\n";

	while ($row = mysql_fetch_assoc($res)) {
		$year = substr($row['rem_startdate'], 0, 4);
		$month = substr($row['rem_startdate'], 5, 2);
		$day = substr($row['rem_startdate'], 8, 2);
		$start_timestamp = mktime(0, 0, 0, $month, $day, $year);

	/*
		echo 'id:              ' . $row['rem_ID'] . "\n";
		echo 'startdate:       ' . $row['rem_startdate'] . "\n";
		echo 'freq:            ' . $row['rem_freq'] . "\n";
		echo 'start_timestamp: ' . $start_timestamp . "\n";
		echo 'weekday:         ' . date('w', $start_timestamp) . "\n";
	*/

		if (($row['rem_freq'] == 'once'  &&  $row['rem_startdate'] == date('Y-m-d'))  ||
			($row['rem_freq'] == 'daily')) {
			$reminders[] = $row;
		}
		else if ($row['rem_freq'] == 'weekly') {
			if (date('w') == date('w', $start_timestamp)) {
				$reminders[] = $row;
			}
		}
		else if ($row['rem_freq'] == 'monthly') {
			if (date('d') == date('d', $start_timestamp)) {
				$reminders[] = $row;
			}
			else if (!checkdate(date('m'), $day, date('Y'))  &&				// $day doesn't exist for this month
					 !checkdate(date('m'), date('d') + 1, date('Y'))) {		// and it's currently the last day of the month
				$reminders[] = $row;
			}
		}
		else if ($row['rem_freq'] == 'annually') {
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
