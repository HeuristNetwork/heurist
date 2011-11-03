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
	require_once(dirname(__FILE__).'/../../common/config/initialise.php');

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
	print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
	return;
	}

	mysql_connection_db_overwrite(DATABASE);
	if(mysql_error()) {
		die("Sorry, could not connect to the database (mysql_connection_db_overwrite error)");
	}
?>

<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<title>Heurist - Straight copy of a database to an identical copy</title>
</head>
<body>
	<script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
	<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
	<script src="../../common/php/loadCommonInfo.php"></script>
	<h2>Heurist Direct Copy</h2>
	This script simply copies the current database <b> <?=HEURIST_DBNAME?> </b> to a new one with no changes. New database is identical to the old in all respects including access.<br><p>
	<p>It will take a long time to execute for large databases (more than 5 - 10,000 records) and may fail on the reload of the dumped data.
	<br>In this case we recommend the following steps from the command line interface:
	<ul>
		<li>Dump the existing database with mysqldump:  mysqldump -uxxxxx -pxxxxx hdb_zzzzzzz > filename</li>
		<li>Create database, switch to database: mysqldump -uxxxxx -pxxxxx -e 'create database hdb_yyyyyy'</li>
		<li>Load the dumped database: mysqldump -uxxxxx -pxxxxx hdb_yyyyyy < filename </li>
		<li>Change to <?HEURIST_UPLOAD_DIR?> and copy the following directories and contents:</li>
			<ul>
			<li>Upload file directory '<?=HEURIST_DBNAME?>' to directory with name of new database (excluding prefix)</li>
			<li>Icons directory '<?=HEURIST_DBNAME?>' to directory with name of new database (excluding prefix)</li>
			</ul>
	</ul>

<?php


// ---- SPECIFY THE TARGET DATABASE (first pass) -------------------------------------------------------------------

 	if(!array_key_exists('mode', $_REQUEST) || !array_key_exists('targetdbname', $_REQUEST)){
?>

<form name='selectdb' action='straightCopyDatabase.php' method='get'>
<input name='mode' value='2' type='hidden'> <!-- calls the form to select mappings, step 2 -->
<input name='db' value='<?=HEURIST_DBNAME?>' type='hidden'>
<br>Enter name for new database to be created (prefix added automatically): <input type='text' name='targetdbname' />
<input type='submit' value='Go'/>
</form>
</body>
</html>
<?php
		exit;
	}

// ---- PROCESS THE COPY FUNCTION (second pass) --------------------------------------------------------------------

    if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){
		$targetdbname = $_REQUEST['targetdbname'];
		error_log("Target database is $dbPrefix$targetdbname");
	 	straightCopyDatabase($targetdbname);
	}


// ---- COPY FUNCTION -----------------------------------------------------------------


function straightCopyDatabase($targetdbname) {

	// Use the file upload directory for this database because we know it should exist and be writable

	$newname = HEURIST_DB_PREFIX.$targetdbname;

	$dump_command = "mysqldump -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." ".HEURIST_DB_PREFIX.HEURIST_DBNAME." > ".HEURIST_UPLOAD_DIR."/temporary_db_dump.sql";
	$create_command = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e 'create database $newname'";
	$upload_command = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." $newname < '".HEURIST_UPLOAD_DIR."/temporary_db_dump.sql'";
	$cleanup_command = "rm ".HEURIST_UPLOAD_DIR."/temporary_db_dump.sql"; // cleanup

	echo ("Execution log:<p>");

	// exec("$dump_command;$create_command;$upload_command;$cleanup_command". ' 2>&1', $output, $res1);
	print " processing: $dump_command<br>";
	exec("$dump_command". ' 2>&1', $output, $res1);
	if ($res1 != 0 ) {
		die ("Unable to process database dump ($dump_command) - check directory/file is writable (delete file if it exists)");
	}
	print " processing: $create_command<br>";
	exec("$create_command". ' 2>&1', $output, $res1);
	if ($res1 != 0 ) {
		die ("Unable to process database create ($create_command) - database may already exist");
	}
	print " processing: $upload_command<br>";
	exec("$upload_command". ' 2>&1', $output, $res1);
	if ($res1 != 0 ) {
		die ("Unable to process upload command ($upload_command)");
	}
	/* Actually not a bad idea to leave this in the directory
	print " processing: $cleanup_command<br><p>";
	exec("$cleanup_command". ' 2>&1', $output, $res1);
	if ($res1 != 0 ) {
		die ("Unable to process cleanup command ($cleanup_command)");
	}
	*/

 // Copy the images and the icons directories

	    // TODO: THE ICONS SHOUDL BE IN A DIRECTORY WITHIN THE UOPLOADED FILES DIRECTORY, NOT IN THE CODEBASE
	    // ESSENTIAL CHANGE TO AVOID PROBLEMS WITH SYMLINKS AND/OR DELETING ICONS AS PART OF SOFTWARE UPDATES
	    // SMARTY TEMPLATES SHOULD ALSO BE IN THE UPLOADED FILES DIRECTORY, XSLT TEMPLATES TOO (MAYBE)

		$copy_file_directory = "cp -R " . HEURIST_UPLOAD_ROOT.HEURIST_DBNAME . " " . HEURIST_UPLOAD_ROOT."$targetdbname"; // no prefix
        print "<br>Copying upload files: $copy_file_directory";
        exec("$copy_file_directory" . ' 2>&1', $output, $res1);
		if ($res1 != 0 ) {
			die ("<p>Unable to copy uploaded files ($copy_file_directory) - please copy directory manually");
		}

        $copy_icons_directory = "cp -R ../../common/images/".HEURIST_DBNAME."/rectype-icons  ../../common/images/$targetdbname/rectype-icons"; // no prefix
        print "<br>Copying icons: $copy_icons_directory";
        exec("$copy_icons_directory" . ' 2>&1', $output, $res1);
		if ($res1 != 0 ) {
			die ("<p>Unable to copy icon files ($copy_icons_directory) - please copy directory manually");
		}

        print "<br><p>Done. New database <b>$newname</b> created<br>";
		print "<p>New upload directory ".HEURIST_UPLOAD_ROOT."$targetdbname";
	print "</body></html>";
	exit;


} // straightCopyNewDatabase


?>
