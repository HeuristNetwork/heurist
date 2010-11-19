<?php

/* update the records table, setting the rec_popularity column */

/* We use a different heuristic now for finding popularity: each user's bookmark contributes (bkm_Rating) */

require_once('../php/modules/heurist-instances.php');
require_once("../php/modules/db.php");

foreach (get_all_instances() as $instance) {

	mysql_connection_db_overwrite($instance["db"]);

	mysql_query("set @logged_in_user_id := 0");
	mysql_query("set @suppress_update_trigger := 1");

	mysql_query("create temporary table popularities as select bkm_recID as bibID, sum(bkm_Rating) as popularity from usrBookmarks group by bkm_recID");
	mysql_query("update records, popularities set rec_popularity = popularity where bibID = rec_id");
}

?>
