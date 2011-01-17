<?php

	/**
	* File: configure_new_installation.php Set up required data and databases for a new Heurist installation, Ian Johnson 12 Jan 2011
	* @copyright 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	/* This file should be called if Heurist attempts to access a database through index.php
	and no hdb_HeuristSystem database is found, indicating that the installation has not yet been configured */

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
		<title>Heurist New Installation configuration</title>
	</head>

	<body>

	</body>
</html>
<?php

	/* Step 1: Create the hdb_HeuristSystem database
	Note: fixed database name, later we could construct the name
	and set up a query to create it before executing the SQL file to populate it */

	NEED TO SET $pwd TO THE ROOT PASS WORD FOR THE DATABASE SYSTEM

	exec("mysql -uroot -p$PWD create_heurist_system_db.sql",$output,$res);

	TEST $RES TO SEE IF SUCCESSFUL, WARN IF NOT AND TAKE ACTION

	/* Step 2: Edit the sysIdentification table */

	CALL edit_sysIdentification.sql

	/* Step 3: Create a new database (without a database there is nothing you can do)
	Best just to create a sample database so minimal interaction */

	CALL create_new_heurist_db.php?db='sample';


?>
