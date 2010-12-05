<?php

/* Copies the Heurist "master" tables:
 *
 * defRecTypes
 * red_detail_types
 * defTerms
 * defRecStructure
 *
 * from the "heurist-common" database to each of the Heurist instance databases
 *
 * Kim Jackson, April 2008
 */

require_once(dirname(__FILE__).'/../../common/config/heurist-instances.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');

foreach (get_all_instances() as $instance_name => $instance) {

	if ($instance_name == 'reference') continue; //FIXME change this so that we can synch to a give databases definitions

	mysql_connection_db_overwrite($instance["db"]);

	mysql_query("START TRANSACTION");
	mysql_query("DELETE FROM defRecTypes");
	mysql_query("INSERT INTO defRecTypes SELECT * FROM `heurist-common`.defRecTypes");
	mysql_query("DELETE FROM defVocabularies");
	mysql_query("INSERT INTO defVocabularies SELECT * FROM `heurist-common`.defVocabularies");
	mysql_query("DELETE FROM rel_constraints");
	mysql_query("INSERT INTO rel_constraints SELECT * FROM `heurist-common`.defRelationshipConstraints");
	mysql_query("DELETE FROM defDetailTypes");
	mysql_query("INSERT INTO defDetailTypes SELECT * FROM `heurist-common`.defDetailTypes");
	mysql_query("DELETE FROM defTerms");
	mysql_query("INSERT INTO defTerms SELECT * FROM `heurist-common`.defTerms");
	mysql_query("DELETE FROM defRecStructure");
	mysql_query("INSERT INTO defRecStructure SELECT * FROM `heurist-common`.defRecStructure");
	mysql_query("COMMIT");

	if (@$_SERVER["HTTP_HOST"]) {
		print "<p>Master record type info copied to instance: $instance_name</p>\n";
	}
}

if (@$_SERVER["HTTP_HOST"]) {
	print "<p>Synchronised all instances OK</p>\n";
}

