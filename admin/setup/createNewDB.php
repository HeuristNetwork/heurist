<?php

	/**
	* File: new_database.php Create a new database by copying db_template.sql, ian Johnson 11 Oct 2010
	* @copyright 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	require_once(dirname(__FILE__).'/../../common/connect/cred.php');

	/* You need to be logged in to a Heurist database in order to create anotehr one */

	THIS MAY CAUSE PROBLEMS FOR THE INITIALISER OF THE SYSTEM SINCE THEY WILL NOT BE LOGGED INTO A DATABASE
	WE MIGHT HAVE TO FUDGE IT BY GIVING THEM A NOTIONAL LOGIN TO hdb_HeuristSystem OR PASSING A MAGIC NUMBER FROM
	THE INITIAL INSTALLATION ROUTINE

	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
		return;
	}

?>

<!-- Step 1: Get a name for the new Heurist database -->

<html>
	<head>
		<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
		<title>New Heurist Database</title>
	</head>

	<body>
		<form method="post" name="NewDBName">
			<span style="font-weight: bold;">Creating new Heurist database</span><br>
			<br>
			Enter name for new database ($prefix will be prepended):
			<input maxlength="64" size="25" name="dbname">

			<input type="button" name="submit" value="Create database" style="font-weight: bold;" onclick="set_ratings();">

		</form>
	</body>
</html>

<?php

	/* Step 2: Create the new blank database */

	print "\n-- Creating blank database";print "\n";
	$query = "Create database $newname";
	$res = mysql_query($query);
	$query = "Use $newname";
	$res = mysql_query($query);


	/* Step 3: Populate the blank database from a template SQL file */

	NEED TO SET $pwd TO THE ROOT PASS WORD FOR THE DATABASE SYSTEM

	$cmdline='mysql -uroot -p$pwd populate_blank_heurist_db.sql';
	exec($cmdline,$output,$res);

	TEST $RES TO SEE IF SUCCESSFUL, WARN IF NOT AND TAKE ACTION

?>
