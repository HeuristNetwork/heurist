<?php

	/**
	* File: new_database.php Create a new database by copying populateBlankDB.sql
    * Ian Johnson 11 Oct 2010
	* @copyright 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');


	/* TO DO: THIS MAY CAUSE PROBLEMS FOR THE INITIALISER OF THE SYSTEM SINCE THEY WILL NOT BE LOGGED INTO A DATABASE
	WE MIGHT HAVE TO FUDGE IT BY GIVING THEM A NOTIONAL LOGIN TO hdb_HeuristSystem or bypass this first time around */


	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
		return;
	}

    if (! is_admin()) {
        print "<html><body><p>You need to be a system administrator to create a new database</p><p>
        <a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
        return;
        }
?>


<?php	// ???? is there a reason that you made a separate php block? If not, please remove it.

function makeDatabase() {

// ???? lines like the one right above create bloat in the code, please setup your editor to avoid these
    /* Create the new blank database */

        $newname= HEURIST_DB_PREFIX . $newname; // all databases have common prefix

        $cmdline="mysql -u$dbAdminUsername -p$dbAdminPassword -e'create database $newname'";
        exec($cmdline,$output,$res);

        /* TO DO: TEST $RES TO SEE IF SUCCESSFUL, WARN IF NOT AND TAKE ACTION */

        /* Populate the blank database from the template SQL file */
        $cmdline="mysql -u$dbAdminUsername -p$dbAdminPassword -D$newname < populateBlankDB.sql";
        exec($cmdline,$output,$res);

        /* TO DO: TEST $RES TO SEE IF SUCCESSFUL, WARN IF NOT AND TAKE ACTION */

} // end makeDatabase

?>


<!-- Step 1: Get a name for the new Heurist database -->

<html>
	<head>
		<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
		<title>Create New Heurist Database</title>
	</head>

	<body>
		<form method="post" name="NewDBName">
			<h3>Create new Heurist database</h3>
			<br>
			<?php echo "Enter name for new database (" . HEURIST_DB_PREFIX . " will be prepended):"; // ??? see line below ?>
			Enter a name for the new database "<?= HEURIST_DB_PREFIX ?>" will be prepended before creating the database
			<input maxlength="64" size="25" name="dbname">

			<input type="button" name="submit" value="Create database"
                        style="font-weight: bold;" onclick=<?php "makeDatabase();"?> >

		</form>

    </body>

</html>

