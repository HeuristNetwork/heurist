<?php

require_once(dirname(__FILE__).'/../../common/config/heurist-instances.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');

foreach (get_all_instances() as $instance) {

	mysql_connection_db_overwrite($instance["db"]);

	// delete usrBookmarks that are tied to temporary record entries
	mysql_query('delete usrBookmarks, records from usrBookmarks, records where bkm_recID=rec_id and rec_temporary');

	// delete temporary record entries
	mysql_query('delete from records where rec_temporary');
}

?>
