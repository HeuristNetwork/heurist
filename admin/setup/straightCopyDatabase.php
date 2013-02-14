<?php

	/** File: strraightCopyDB.php Copies an entire databsae verbatim, Ian Johnson 31 Oct 2011
	* @copyright 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

	if (! is_logged_in()) {
		header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	// User must be system administrator or admin of the owners group for this database
	if (!is_admin()) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to clone a database</span><p><a href=".HEURIST_BASE_URL."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}

	mysql_connection_overwrite(DATABASE);
	if(mysql_error()) {
		die("<h2>Error</h2>Sorry, could not connect to the database (mysql_connection_overwrite error)");
	}
?>

<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Heurist - Straight copy of a database to an identical copy</title>

		<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
		<link rel="stylesheet" type="text/css" href="../../common/css/edit.css">
		<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">

		<style>
			ul {color:#CCC;}
			li {line-height: 20px; list-style:outside square; font-size:9px;}
			li ul {color:#CFE8EF; font-size:9px}
			li span {font-size:11px; color:#000;}
		</style>
	</head>
	<body class="popup">

		<script type="text/javascript">
		function onKeyPress(event){

			event = event || window.event;
			var charCode = typeof event.which == "number" ? event.which : event.keyCode;
			if (charCode && charCode > 31)
			{
					var keyChar = String.fromCharCode(charCode);
					if(!/^[a-zA-Z0-9$_]+$/.test(keyChar)){

						event.cancelBubble = true;
						event.returnValue = false;
						event.preventDefault();
						if (event.stopPropagation) event.stopPropagation();

						/* does not work
						var ele = event.target;
						var evt = document.createEvent("KeyboardEvent");
						evt.initKeyEvent("keypress",true, true, window, false, false,false, false, 0, 'A'.charCodeAt(0));
						ele.dispatchEvent(evt);*/

						return false;
					}
			}
			return true;
		}
		</script>


		<div class="banner"><h2>Heurist Direct Copy</h2></div>

		<script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
		<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
		<script src="../../common/php/loadCommonInfo.php"></script>
		<div id="page-inner" style="overflow:auto">

			<p>This script simply copies the current database <b> <?=HEURIST_DBNAME?> </b> to a new one with no changes. The new database is identical to the old in all respects including access.</p>

			<p>The script will take a long time to execute for large databases (more than 5 - 10,000 records) and may fail on the reload of the dumped data.
			<p>In this case we recommend the following steps from the command line interface:
			<ul>
				<li><span>Dump the existing database with mysqldump:  mysqldump -u... -p... hdb_xxxxx > filename</span></li>
				<li><span>Create database, switch to database: mysqldump -u... -p... -e 'create database hdb_yyyyy'</span></li>
				<li><span>Load the dumped database: mysqldump -u... -p... hdb_yyyyyy < filename </span></li>
				<li><span>Change to <?HEURIST_UPLOAD_DIR?> and copy the following directories and contents:</span>
					<ul>
						<li><span>Upload file directory '<?=HEURIST_DBNAME?>' to directory with name of new database (excluding prefix)</span></li>
						<li><span>Icons directory '<?=HEURIST_DBNAME?>' to directory with name of new database (excluding prefix)</span></li>
					</ul>
				</li>
			</ul>


			<?php


				// ---- SPECIFY THE TARGET DATABASE (first pass) -------------------------------------------------------------------

				if(!array_key_exists('mode', $_REQUEST) || !array_key_exists('targetdbname', $_REQUEST)){
				?>
				<div class="separator_row" style="margin:20px 0;"></div>
				<form name='selectdb' action='straightCopyDatabase.php' method='get'>
					<input name='mode' value='2' type='hidden'> <!-- calls the form to select mappings, step 2 -->
					<input name='db' value='<?=HEURIST_DBNAME?>' type='hidden'>
					<p>The database will be created with the prefix "hdb_" (all databases created by this installation of the software will have the same prefix).</p>
					<h3>Enter a name for the new database:</h3>
					<div style="margin-left: 40px;">
						<input type='text' name='targetdbname' onkeypress="{onKeyPress(event)}"/>
						<input type='submit' value='Clone <?=HEURIST_DBNAME?>'/>
					</div>

				</form>
			</div>
		</body>
	</html>
	<?php
		exit;
	}

	// ---- PROCESS THE COPY FUNCTION (second pass) --------------------------------------------------------------------


	function isInValid($str) {
		return preg_match('[\W]', $str);
	}

	if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){
		$targetdbname = $_REQUEST['targetdbname'];
		/*****DEBUG****///error_log("Target database is $dbPrefix$targetdbname");

		// Avoid illegal chars in db name
		$hasInvalid = isInValid($targetdbname);
		if ($hasInvalid) {
			echo ("<p><hr><p>&nbsp;<p>Requested database copy name: <b>$targetdbname</b>".
				"<p>Sorry, only letters, numbers and underscores (_) are allowed in the database name");
			return false;
		} // rejecting illegal characters in db name

		$list = mysql__getdatabases();
		if(in_array($targetdbname, $list)){
			echo "<h3>Error: database '".$targetdbname."' already exists. Choose different name</h3>";
			return false;
		}

		straightCopyDatabase($targetdbname);
	}


	// ---- COPY FUNCTION -----------------------------------------------------------------


	function straightCopyDatabase($targetdbname) {

		// Use the file upload directory for this database because we know it should exist and be writable

		$newname = HEURIST_DB_PREFIX.$targetdbname;

		$dump_command = "mysqldump --routines -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".HEURIST_DB_PREFIX.HEURIST_DBNAME." > ".HEURIST_UPLOAD_DIR."temporary_db_dump.sql";
		$create_command = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e 'create database $newname'";
		$upload_command = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." $newname < '".HEURIST_UPLOAD_DIR."/temporary_db_dump.sql'";
		$cleanup_command = "rm ".HEURIST_UPLOAD_DIR."/temporary_db_dump.sql"; // cleanup

		echo ("Execution log:<p>");

		$msg=explode(ADMIN_DBUSERPSWD,$dump_command); // $msg[1] strips out the password info ...
		print " processing: <i>mysqldump -u... -p... $msg[1]</i><br>";
		exec("$dump_command". ' 2>&1', $output, $res1);
		if ($res1 != 0 ) {
			die ("<h2>Error</h2>Unable to process database dump: <i>mysqldump -u... -p... $msg[1]</i>".
				"<p>The most likely reason is that the target directory is not writable by 'nobody', or the SQL output file already exists".
				"Please check the target directory listed above, or ask your sysadmin to make it writable/remove existing SQL file");
		}

		$msg=explode(ADMIN_DBUSERPSWD,$create_command); // $msg[1] strips out the password info ...
		print " processing: <i>mysql -u... -p... $msg[1]</i><br>";
		exec("$create_command". ' 2>&1', $output, $res1);
		if ($res1 != 0 ) {
			die ("<h2>Error</h2>Unable to process database create command: <i>mysql -u... -p... $msg[1]</i>".
				"<p>The database may already exist - please check on your MySQL server or ask your sysadmin for help".
				"<p><a href='../structure/getListOfDatabases.php' target=_blank>List of Heurist databases</a>");
		}

		$msg=explode(ADMIN_DBUSERPSWD,$upload_command); // $msg[1] strips out the password info ...
		print " processing: <i>mysql -u... -p... $msg[1]</i><br>";
		exec("$upload_command". ' 2>&1', $output, $res1);
		if ($res1 != 0 ) {
			die ("<h2>Error</h2>Unable to process database upload command: <i>mysql -u... -p... $msg[1]</i>".
				"<p>The SQL file might not have been written correctly. Please ask your sysadmin for help and report the problem to the Heurist development team");
		}

		// RESET register db ID
		$query1 = "update $newname.sysIdentification set sys_dbRegisteredID=0 where sys_ID=1";
		$res1 = mysql_query($query1);
		if (mysql_error())  { //(mysql_num_rows($res1) == 0)
			print "<p><h4>Warning</h4><b>Unable to reset sys_dbRegisteredID in sysIdentification table. (".mysql_error().")<br> Please reset the registration ID manually</b></p>";
		}

		/* Actually not a bad idea to leave the file in the directory
		print " processing: $cleanup_command<br><p>";
		exec("$cleanup_command". ' 2>&1', $output, $res1);
		if ($res1 != 0 ) {
		die ("Unable to process cleanup command ($cleanup_command)");
		}
		*/

		// Copy the images and the icons directories

		$copy_file_directory = "cp -R " . HEURIST_UPLOAD_ROOT.HEURIST_DBNAME . " " . HEURIST_UPLOAD_ROOT."$targetdbname"; // no prefix
		print "<br>Copying upload files: $copy_file_directory";
		exec("$copy_file_directory" . ' 2>&1', $output, $res1);
		if ($res1 != 0 ) {
			die ("<h3>Error</h3>Unable to copy uploaded files using: <i>$copy_file_directory</i>".
				"<p>Please copy the directory manually or ask you sysadmin to help you</p>");
		}

		echo "<h2>New database '$targetdbname' created successfully</h2>";

		print "<p>New upload directory ".HEURIST_UPLOAD_ROOT."$targetdbname</p>";
		print "<p>The search page for this database is: <a href=\"".HEURIST_BASE_URL."?db=".$targetdbname."\" title=\"\" target=\"_new\">".HEURIST_BASE_URL."?db=".$targetdbname."</a>.</p>";
		print "<p>Please click here: <a href='".HEURIST_BASE_URL."admin/adminMenu.php?db=".$targetdbname."' title='' target=\"_new\"><strong>administration page</strong></a>, to configure your new database</p>";

		print "</body></html>";
		exit;


	} // straightCopyNewDatabase


?>
