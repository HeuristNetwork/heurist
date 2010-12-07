<?php

require_once(dirname(__FILE__).'/../../common/config/heurist-instances.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');

foreach (get_all_instances() as $instance) {

	mysql_connection_db_overwrite($instance["db"]);

	// delete usrBookmarks that are tied to temporary record entries
	mysql_query('delete usrBookmarks, Records from usrBookmarks, Records where bkm_recID=rec_ID and rec_FlagTemporary');

	// delete temporary record entries
	mysql_query('delete from Records where rec_FlagTemporary');
}

?>
