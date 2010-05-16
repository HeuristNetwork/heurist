<?php

require_once('../php/modules/heurist-instances.php');
require_once('../php/modules/db.php');

foreach (get_all_instances() as $instance) {

	mysql_connection_db_overwrite($instance["db"]);

	// delete personals that are tied to temporary record entries
	mysql_query('delete personals, records from personals, records where pers_rec_id=rec_id and rec_temporary');

	// delete temporary record entries
	mysql_query('delete from records where rec_temporary');
}

?>
