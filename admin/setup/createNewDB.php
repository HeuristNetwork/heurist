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
    
    if (isset($_POST['dbname'])) {
		makeDatabase();
	}

function makeDatabase() {
	if(ADMIN_DBUSERNAME == "") {
		if(ADMIN_DBUSERPSWD == "") {
			echo "Admin username and password have not been set. Please do so before trying to create a new database.";
			return;
		}
		echo "Admin username has not been set. Please do so before trying to create a new database.";
		return;
	}
	if(ADMIN_DBUSERPSWD == "") {
		echo "Admin password has not been set. Please do so before trying to create a new database.";
		return;
	}
    /* Create the new blank database */
	$dbname=$_POST['dbname'];
    $newname= HEURIST_DB_PREFIX . $dbname; // all databases have common prefix

    $cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'create database $newname'";
    $output1 = exec($cmdline . ' 2>&1', $output, $res1);

    /* TO DO: TEST $RES TO SEE IF SUCCESSFUL, WARN IF NOT AND TAKE ACTION */

    
    /* Test if creation was succesful */
    if($res1 == 0) {
    	/* Populate the blank database from the template SQL file */
        $cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < populateBlankDB.sql";
        $output2 = exec($cmdline . ' 2>&1', $output, $res2);
        
        if($res2 == 0) {
	       	echo "Database successfully created with name: " . $newname . "<br />";
	    }
	    else {
	    	$errorOrWarning = split(" ", $output2);
	    	if($errorOrWarning[0] == "Warning") {
	    		echo "An warning was given trying to populate the blank database from the template SQL file:<br />";
	    	}
	    	else {
		    	echo "An error accurred trying to populate the blank database from the template SQL file:<br />";
	    	}
	    	echo $output2 . "<br />";
	    }
    }
    else {
    	$sqlErrorCode = split(" ", $output1);
    	if($sqlErrorCode[1] == "1007") {
    		echo "A database with that name already exists.";
    	}
    	else {
			$errorOrWarning2 = split(" ", $output1);
	    	if($errorOrWarning2[0] == "Warning") {
	    		echo "An warning was given trying to create the database:<br />";
	    		
	    	}
	    	else {
		    	echo "An error accurred trying to create the database:<br />";
	    	}
	    	echo $output1 . "<br />";
    	}
    	
    }
        
} // end makeDatabase

?>

<!-- Step 1: Get a name for the new Heurist database -->
<html>
	<head>
		<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
		<title>Create New Heurist Database</title>
	</head>

	<body>
		<form action="createNewDB.php" method="POST" name="NewDBName">
			<h3>Create new Heurist database</h3>
			<br>
			Enter a name for the new database. The prefix "<?= HEURIST_DB_PREFIX ?>" will be prepended before creating the database.<br /><br />
			<input type="text" maxlength="64" size="25" name="dbname">

			<input type="submit" name="submit" value="Create database" style="font-weight: bold;" onClick="<?php makeDatabase(); ?>" >

		</form>
    </body>
</html>
