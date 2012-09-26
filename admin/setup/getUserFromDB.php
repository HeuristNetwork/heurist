<?php

	/* getUserFromDB.php - gets a user definition from an existing database and adds to current database
	* Ian Johnson Artem Osmakov 25 - 28 Oct 2011
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @param includeUgrps=1 will output user and group information in addition to definitions
	* @param approvedDefsOnly=1 will only output Reserved and Approved definitions
	* @todo
	*/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	//require_once(dirname(__FILE__).'/../../admin/ugrps/saveUsergrpsFs.php');

	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	// User must be system administrator or admin of the owners group for this database
	if (!is_admin()) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to import user</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}

?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Heurist - Import user from another database</title>
    	<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
	</head>
	<body class="popup">

		<div class="banner"><h2>Import user from another database</h2></div>
		<div id="page-inner" style="overflow:auto;padding-left: 20px;">

		<?php

			mysql_connection_db_overwrite(DATABASE);
			if(mysql_error()) {
				die("Sorry, could not connect to the database (mysql_connection_db_overwrite error)");
			}

			print "<h2>Import user from another database</h2>";
			print "Imports a user record from another Heurist database on the system and adds the user to the current database. You will need to allocate imported users to groups.";

			$sourcedbname = NULL;

			// ----FORM 1 - SELECT THE SOURCE DATABASE --------------------------------------------------------------------------------

			if(!array_key_exists('mode', $_REQUEST)) {

				print "<form name='selectdb' action='getUserFromDB.php' method='get'>";
				print "<input name='mode' value='2' type='hidden'>"; // calls the form to select mappings, step 2
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				print "<p>Choose source database: <select id='db' name='sourcedbname'>";
				$query = "show databases";
				$res = mysql_query($query);
				while ($row = mysql_fetch_array($res)) {
					$test=strpos($row[0],$dbPrefix);
					if (is_numeric($test) && ($test==0) ) {
						$name = substr($row[0],strlen($dbPrefix));  // delete the prefix
						print "<option value='$name'>$name</option>";
					}
				}
				print "</select>";
				print "<input type='submit' value='Select database' />";
				exit;
			}

			// ----FORM 2 - MAPPINGS FORM ----------------------------------------------------------------------------------------

			$sourcedbname = $_REQUEST['sourcedbname'];

			if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){

				$sourcedb = $dbPrefix.$sourcedbname;
				print "<form name='selectuser' action='getUserFromDB.php' method='get'>";
				print "<input name='mode' value='3' type='hidden'>";
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				print "<input name='sourcedbname' value='".$sourcedbname."' type='hidden'>";

				print "<input name='sourcedbname' value='".$sourcedbname."' type='hidden'>";
				print "<p>Source database: <b>$sourcedb</b> <br>";
				$res=mysql_query("select * from $sourcedb.sysIdentification");
				if (!$res) {
					die ("<p>Unable to open source database <b>$sourcedb</b>. Make sure you have included prefix");
				}

				$query1 = "SELECT * FROM $sourcedb.sysUGrps where ugr_Type='user'";
				$res1 = mysql_query($query1);
				if (mysql_num_rows($res1) == 0) {
					die ("<p><b>Sorry, unable to read users from this database</b></p>");
				}

				print "<p>Choose user: <select id='usr' name='usr'>";
				while ($row1 = mysql_fetch_assoc($res1)) {
					print "<option value=".$row1['ugr_ID'].">".$row1['ugr_Name']."</option>";
				} // loop through users
				print "</select>";
				print "<input type='submit' value='Insert user' />";
			}

			// ---- Find and add user -----------------------------------------------------------------

			$sourcedbname = $_REQUEST['sourcedbname'];

			if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='3'){

				$sourcedb = $dbPrefix.$sourcedbname;

				if (array_key_exists('usr', $_REQUEST)) {
					$userID = $_REQUEST['usr'];
				} else {
					die ("<p>Sorry, selected user $userID does not exist");
				}

				$err = transferUser($sourcedb, $userID, DATABASE, false);

				if ($err) {
					print "<p>MySQL returns: ".$err;
					print "<p><b>Sorry, Problem writing user # $userID from the source database $sourcedb into the current database</b>".
						"<p><a href=".HEURIST_URL_BASE."admin/setup/getUserFromDB.php?db=".HEURIST_DBNAME."&sourcedbname=$sourcedbname&mode=2>Add another</a>";
				} else {
/* IJ: 19-Sep-12 Don't make imported users members of the database owners group - too risky.
					$newUserID =  mysql_insert_id();
					$query1="INSERT INTO sysUsrGrpLinks (ugl_UserID,ugl_GroupID) VALUES ($newUserID,'1')"; // adds to 1 = 'database owners' as 'member'
					// todo: should really offer choice of existing user groups to add the user to, as well as their role
					$res1 = mysql_query($query1);
					$err=mysql_error();
					if (!$res1) {
						print "<p>MySQL returns: ".$err;
						print "<p><b>Sorry, Unable to allocate the new user to a group - please set maually</b>".
							"<p><a href=".HEURIST_URL_BASE."admin/setup/getUserFromDB.php?db=".HEURIST_DBNAME."&sourcedbname=$sourcedbname&mode=2>Add another</a>";
					} else {
					print "<p><b>New user allocated as a member of the 'database owners' group (# 1) - edit group allocation as required".
						"<p><a href=".HEURIST_URL_BASE."admin/setup/getUserFromDB.php?db=".HEURIST_DBNAME."&sourcedbname=$sourcedbname&mode=2>Add another</a>";

					}
*/
					print "<p><b>New user allocated as not a member of any group. Edit group allocation as required".
						"<p><a href=".HEURIST_URL_BASE."admin/setup/getUserFromDB.php?db=".HEURIST_DBNAME."&sourcedbname=$sourcedbname&mode=2>Add another</a>";

				}
			}

	/**
	*  Transfer user from one database to another
	*/
	function transferUser($sourcedb, $sourceuserid, $destdb, $isowner){

			$fields = "ugr_Type,ugr_Name,ugr_LongName,ugr_Description,ugr_Password,ugr_eMail,".
						"ugr_FirstName,ugr_LastName,ugr_Department,ugr_Organisation,ugr_City,".
						"ugr_State,ugr_Postcode,ugr_Interests,ugr_Enabled,ugr_LastLoginTime,".
						"ugr_MinHyperlinkWords,ugr_LoginCount,ugr_IsModelUser,".
						"ugr_IncomingEmailAddresses,ugr_TargetEmailAddresses,ugr_URLs,ugr_FlagJT";

			$query1 = "insert into $destdb.sysUGrps ($fields) ".
						"SELECT $fields ".
						"FROM $sourcedb.sysUGrps where ugr_ID=$sourceuserid";

			$res1 = mysql_query($query1);
			$err = mysql_error();
			/*
			if (!$err && $isowner) {
					$newUserID =  mysql_insert_id();

					$query1 = "delete from $destdb.sysUGrps where ugr_ID=2";
					mysql_query($query1);
					$err = mysql_error();
					if(!$err){
						$query1 = "update $destdb.sysUGrps set ugr_ID=2 where ugr_ID=".$newUserID;
						mysql_query($query1);
						$err = mysql_error();
					}
			}*/
			return $err;
	}
		?>
		</div>
	</body>
</html>
