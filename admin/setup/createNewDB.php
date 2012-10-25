<?php
	/**
	* File: createNewDB.php Create a new database by applying blankDBStructure.sql and coreDefinitions.txt
	* Juan Adriaanse 10 Apr 2011
	* @copyright 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	*
	* Extensively modified 4/8/11 by Ian Johnson to reduce complexity and load new database in
	* a series of files with checks on each stage and cleanup code
	*
	* **/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

	if(!is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
?>
<html>
	<head>
		<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
		<title>Create New Heurist Database</title>
		<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
		<link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
	</head>
	<style>
		.detailType {width:180px !important}
	</style>
	<script>
		function hideProgress(){
			var ele = document.getElementById("loading");
			if(ele){
				ele.style.display = "none";
			}
		}
		function showProgress(){
			var ele = document.getElementById("loading");
			if(ele.style.display != "none"){
				ele = document.getElementById("divProgress");
				if(ele){
					ele.innerHTML = ele.innerHTML + ".";
					setTimeout(showProgress, 500);
				}
			}
		}

		function challengeForDB(){
		  var pwd_value = document.getElementById("pwd").value;
		  if(pwd_value==="<?=$passwordForDatabaseCreation?>"){
		  	  document.getElementById("challengeForDB").style.display = "none";
		  	  document.getElementById("createDBForm").style.display = "block";
		  }else{
			  alert("Password incorrect, please check with system administrator. Note: password is case sensitive");
		  }
		}

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

	<body class="popup" onload="hideProgress()">

		<div class="banner"><h2>Create new Heurist database</h2></div>
		<div id="page-inner" style="overflow:auto">

<?php
	$newDBName = "";
	$isNewDB = false; // Used by buildCrosswalks to detemine whether to get data from coreDefinitions.txt (for new database)
	// or by querying an existing Heurist database using getDBStructure (for crosswalk)

	global $errorCreatingTables; // Set to true by buildCrosswalks if error occurred
	global $done; // Prevents the makeDatabase() script from running twice
	$done = false; // redundant
    $isCreateNew = true;

	/*****DEBUG****///error_log(" post dbname: $_POST['dbname'] ");  //debug

	if(isset($_POST['dbname'])) {
		$isCreateNew = false;
		$isExtended = ($_POST['dbtype']=='1');

		/*verify that database name is unique
		$list = mysql__getdatabases();
		$dbname = $_POST['uname']."_".$_POST['dbname'];
		if(array_key_exists($dbname, $list)){
			echo "<h3>Database '".$dbname."' already exists. Choose different name</h3>";
		}else{*/
?>
		<div id="loading">
					<img src="../../common/images/mini-loading.gif" width="16" height="16" />
					<strong><span id="divProgress">&nbspCreating database, please wait</span></strong>
		</div>
		<script type="text/javascript">showProgress();</script>
<?php
			ob_flush();flush();
			//sleep(5);

			makeDatabase(); // this does all the work

	}

	if($isCreateNew){
?>
	   	<div id="challengeForDB" style="<?='display:'.(($passwordForDatabaseCreation=='')?'none':'block')?>;">
			<h3>Enter the password set by your system adminstrator for new database creation:</h3>
				<input type="password" maxlength="64" size="25" id="pwd">
				<input type="button" onclick="challengeForDB()" value="OK" style="font-weight: bold;" >
		</div>

		<div id="createDBForm" style="<?='display:'.($passwordForDatabaseCreation==''?'block':'none')?>;padding-top:20px;">
			<form action="createNewDB.php?db=<?= HEURIST_DBNAME ?>" method="POST" name="NewDBName">

				<input type="radio" name="dbtype" value="0" id="rb1" checked="true" /><label for="rb1" class="labelBold">Standard database</label>
				<div style="padding-left: 38px;padding-bottom:10px">Gives an uncluttered database with essential record and field types<br />Recommended for general use</div>
				<input type="radio" name="dbtype" value="1" id="rb2" /><label for="rb2" class="labelBold">Extended database</label>
				<div style="padding-left: 38px;">A database structure with extra record types and fields to support tool such as XSL transforsm<br />The additional structure elements can be imported later from the H3ToolSupport database</div>

				<p>New database creation takes 10 - 20 seconds. New databases are created on the current server.<br>
					You will become the owner and administrator of the new database.<br>
					The database will be created with the prefix "<?= HEURIST_DB_PREFIX ?>"
				(all databases created by this installation of the software will have the same prefix).</p>
				<h3>Enter a name for the new database:</h3>
                <div style="margin-left: 40px;">
                    <!-- user name used as prefix -->
                    <b><?= HEURIST_DB_PREFIX ?>
                    <input type="text" maxlength="20" size="6" name="uname"  onkeypress="{onKeyPress(event)}"
                    style="padding-left:3px; font-weight:bold;" value=<?=substr(get_user_username(),0,5)?> >
                    _  </b>
					<input type="text" maxlength="64" size="25" name="dbname"  onkeypress="{onKeyPress(event);}">
					<input type="submit" name="submit" value="Create database" style="font-weight: bold;"  >
					<p>The user name prefix is editable, and may be blank, but we suggest using a consistent prefix for personal databases<br> so that all your personal databases appear together in the list of databases<p></p>
                </div>
			</form>
		</div>
<?
	}


	function isInValid($str) {
		return preg_match('[\W]', $str);
	}

	function cleanupNewDB ($newname) { // called in case of failure to remove the partially created database
		global $newDBName, $isNewDB, $done;
		$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'drop database `$newname`'";
		$output2=exec($cmdline . ' 2>&1', $output, $res2);
		echo "<br>Database cleanup for $newname, completed<br>&nbsp;<br>";
		echo($output2);
		$done = true;
	} // cleanupNewDB



	function makeDatabase() { // Creates a new database and populates it with triggers, constraints and core definitions
		global $newDBName, $isNewDB, $done, $isCreateNew, $isExtended;
		$error = false;
		$warning=false;

		if (isset($_POST['dbname'])) {

			// Check that there is a current administrative user who can be made the owner of the new database
			if(ADMIN_DBUSERNAME == "") {
				if(ADMIN_DBUSERPSWD == "") {
					echo "DB Admin username and password have not been set in config.ini. Please do so before trying to create a new database.<br>";
					return;
				}
				echo "DB Admin username has not been set in config.ini. Please do so before trying to create a new database.<br>";
				return;
			}
			if(ADMIN_DBUSERPSWD == "") {
				echo "DB Admin password has not been set in config.ini. Please do so before trying to create a new database.<br>";
				return;
			} // checking for current administrative user

			// Create a new blank database
            $newDBName = trim($_POST['uname']).'_';
            if ($newDBName == '_') {$newDBName='';}; // don't double up underscore if no user prefix
            $newDBName = $newDBName . trim($_POST['dbname']);
			$newname = HEURIST_DB_PREFIX . $newDBName; // all databases have common prefix then user prefix

			// Avoid illegal chars in db name
			$hasInvalid = isInValid($newname);
			if ($hasInvalid) {
				echo ("Only letters, numbers and underscores (_) are allowed in the database name");
				return false;
			} // rejecting illegal characters in db name

			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e\"create database `$newname`\"";
				} else {
				$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'create database `$newname`'";
			}
			$output1 = exec($cmdline . ' 2>&1', $output, $res1);
			if ($res1 != 0 ) {
				echo ("Error code $res1 on MySQL exec: Unable to create database $newname<br>&nbsp;<br>");
				echo("\n\n");

				if(is_array($output)){
					$isExists = (strpos($output[0],"1007")>0);
				}else{
					$sqlErrorCode = split(" ", $output);
					$isExists = (count($sqlErrorCode) > 1 &&  $sqlErrorCode[1] == "1007");
				}
				if($isExists)
					echo "<strong>A database with that name already exists.</strong>";
					$isCreateNew = true;
				return false;
			}

			// At this point a database exists, so need cleanup if anythign goes wrong later

			// Create the Heurist structure for the newly created database, using the template SQL file
			// This file sets up teh table definitions and inserts a few critical values
			// it does not set referential integrity constraints or triggers
			$cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < blankDBStructure.sql";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			if ($res2 != 0 ) {
				echo ("Error $res2 on MySQL exec: Unable to load blankDBStructure.sql into database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed<br>&nbsp;<br>");
				echo($output2);
				cleanupNewDB($newname);
				return false;
			}

			// Add referential constraints
			$cmdline="mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addReferentialConstraints.sql";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			if ($res2 != 0 ) {
				echo ("Error $res2 on MySQL exec: Unable to load addReferentialConstraints.sql into database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed<br>&nbsp;<br>");
				echo($output2);
				cleanupNewDB($newname);
				return false;
			}

			// Add procedures and triggers
			$cmdline = "mysql -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addProceduresTriggers.sql";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			if ($res2 != 0 ) {
				echo ("Error $res2 on MySQL exec: Unable to load addProceduresTriggers.sql for database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed<br>&nbsp;<br>");
				echo($output2);
				cleanupNewDB($newname);
				return false;
			}

			// Run buildCrosswalks to import minimal definitions from coreDefinitions.txt into the new DB
			// yes, this is badly structured, but it works - if it ain't broke ...
			$isNewDB = true; // flag of context for buildCrosswalks, tells it to use coreDefinitions.txt

			require_once('../structure/buildCrosswalks.php');

			// errorCreatingTables is set to true by buildCrosswalks if an error occurred
			if($errorCreatingTables) {
				echo ("Error importing core definitions from ".($isExtended?"coreDefinitionsExtended.txt":"coreDefinitions.txt")." for database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed");
				cleanupNewDB($newname);
				return false;
			}

			// Get and clean information for the user creating the database
			mysql_connection_db_insert(DATABASE);
			$query = mysql_query("SELECT ugr_LongName, ugr_FirstName, ugr_LastName, ugr_eMail, ugr_Name, ugr_Password, ugr_Department, ugr_Organisation, ugr_City, ugr_State, ugr_Postcode, ugr_Interests FROM sysUGrps WHERE ugr_ID=".get_user_id());
			$details = mysql_fetch_row($query);
			$longName = mysql_escape_string($details[0]);
			$firstName = mysql_escape_string($details[1]);
			$lastName = mysql_escape_string($details[2]);
			$eMail = mysql_escape_string($details[3]);
			$name = mysql_escape_string($details[4]);
			$password = mysql_escape_string($details[5]);
			$department = mysql_escape_string($details[6]);
			$organisation = mysql_escape_string($details[7]);
			$city = mysql_escape_string($details[8]);
			$state = mysql_escape_string($details[9]);
			$postcode = mysql_escape_string($details[10]);
			$interests = mysql_escape_string($details[11]);

			//     todo: code location of upload directory into sysIdentification, remove from edit form (should not be changed)
			//     todo: might wish to control ownership rather than leaving it to the O/S, although this works well at present

			$warnings = 0;

			// Create a default upload directory for uploaded files eg multimedia, images etc.
			$uploadPath = HEURIST_UPLOAD_ROOT.$newDBName;
			$cmdline = "mkdir -p -m a=rwx ".$uploadPath;
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);
			if ($res2 != 0 ) { // TODO: need to properly trap the error and distiguish different versions.
				// Old uplaod directories hanging around could cause problems if upload file IDs are duplicated,
				// so should probably NOT allow their re-use
				echo ("<h3>Warning:</h3> Unable to create $uploadPath directory for database $newDBName<br>&nbsp;<br>");
				echo ("This may be because the directory already exists or the parent folder is not writable<br>");
				echo ("Please check/create directory by hand. Consult Heurist helpdesk if needed<br>");
				echo($output2);
				$warnings = 1;
			}

			// copy icon and thumbnail directories from default set in the program code (sync. with H3CoreDefinitions)
			$cmdline = "cp -R rectype-icons $uploadPath"; // creates directories and copies icons and thumbnails
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);
			if ($res2 != 0 ) {
				echo ("<h3>Warning:</h3> Unable to create/copy record type icons folder rectype-icons to $uploadPath<br>");
				echo ("If upload directory was created OK, this is probably due to incorrect file permissions on new folders<br>");
				echo($output2);
				$warnings = 1;
			}
			// copy smarty template directory from default set in the program code
			$cmdline = "cp -R smarty-templates $uploadPath";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);
			if ($res2 != 0 ) {
				echo ("<h3>Warning:</h3> Unable to create/copy smarty-templates folder to $uploadPath<br>");
				echo($output2);
				$warnings = 1;
			}
			// copy xsl template directories from default set in the program code
			$cmdline = "cp -R xsl-templates $uploadPath";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);
			if ($res2 != 0 ) {
				echo ("<h3>Warning:</h3> Unable to create/copy xsl-templates folder to $uploadPath<br>");
				echo($output2);
				$warnings = 1;
			}

			$warnings =+ createFolder("settings","used to store import mappings and the like");
			$warnings =+ createFolder("scratch","used to store temporary files");
			$warnings =+ createFolder("hml-output","used to write published records as hml files");
			$warnings =+ createFolder("html-output","used to write published records as generic html files");
			$warnings =+ createFolder("generated-reports","used to write generated reports");

			if ($warnings > 0) {
				echo "<h2>Please take note of warnings above</h2>";
				echo "You must create the folders indicated or uploads, icons and templates will not work<br>";
				echo "If upload folder is created but icons and template forlders are not, look at file permissions on new folder creation";
			}

            // Prepare to write to the newly created database
			mysql_connection_db_insert($newname);

            // Update file locations
			$query='update sysIdentification
                set sys_hmlOutputDirectory = "'.$uploadPath.'/hml-output",
                sys_htmlOutputDirectory = "'.$uploadPath.'/html-output"';
  			mysql_query($query);
			if (mysql_error()) {
				echo "<h3>Warning: </h3> Unable to update sysIdentification table - please go to DBAdmin > Databases > Properties &".
				" Advanced Properties, and check the path to the upload, hml and html directories. (".mysql_error().")";
			}

            // Make the current user the owner and admin of the new database
			mysql_query('UPDATE sysUGrps SET ugr_LongName="'.$longName.'", ugr_FirstName="'.$firstName.'",
			ugr_LastName="'.$lastName.'", ugr_eMail="'.$eMail.'", ugr_Name="'.$name.'",
			ugr_Password="'.$password.'", ugr_Department="'.$department.'", ugr_Organisation="'.$organisation.'",
			ugr_City="'.$city.'", ugr_State="'.$state.'", ugr_Postcode="'.$postcode.'",
			ugr_interests="'.$interests.'" WHERE ugr_ID=2');
			// TODO: error check, although this is unlikely to fail

			echo "<h2>New database '$newDBName' created successfully</h2>";

			echo "<p><strong>Admin username:</strong> ".$name."<br />";
			echo "<strong>Admin password:</strong> &#60;<i>same as account currently logged in to</i>&#62;</p>";

			echo "<p>The search page for this database is: <a href=\"".HEURIST_URL_BASE."?db=".$newDBName."\" title=\"\" target=\"_new\">".HEURIST_URL_BASE."?db=".$newDBName."</a>.</p>";
			echo "<p>Please click here: <a href='".HEURIST_URL_BASE."admin/adminMenu.php?db=".$newDBName."' title='' target=\"_new\"><strong>administration page</strong></a>, to configure your new database</p>";

			// TODO: automatically redirect to the new database, maybe, in a new window

			return false;
		} // isset

	} //makedatabase

	//
	//
	//
	function createFolder($name, $msg){
		global 	$newDBName;
		$uploadPath = HEURIST_UPLOAD_ROOT.$newDBName;
		$folder = $uploadPath."/".$name;

		if(file_exists($folder) && !is_dir($folder)){
			if(!unlink($folder)){
				echo ("<h3>Warning:</h3> Unable to remove file $folder. We have to create folder with such name ($msg)<br>");
				return 1;
			}
		}

		if(!file_exists($folder)){
			if (!mkdir($folder, 0777, true)) {
				echo ("<h3>Warning:</h3> Unable to create folder $folder ($msg)<br>");
				return 1;
			}
		}else if (!is_writable($folder)) {
			echo ("<h3>Warning:</h3> Folder $folder already exists and it is not writeable. Check permissions! ($msg)<br>");
			return 1;
		}

		return 0;
	}

?>
	</body>
</html>


