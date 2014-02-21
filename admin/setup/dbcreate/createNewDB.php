<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* File: createNewDB.php Create a new database by applying blankDBStructure.sql and coreDefinitions.txt 
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

      /**
    * Extensively modified 4/8/11 by Ian Johnson to reduce complexity and load new database in
    * a series of files with checks on each stage and cleanup code
    * **/

	define('NO_DB_ALLOWED',1);
	require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');

	if (!is_logged_in() && HEURIST_DBNAME!="") {
		header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME.'&last_uri='.urlencode(HEURIST_CURRENT_URL) );
		return;
	}

	//
	function prepareDbName(){
		$db_name = substr(get_user_username(),0,5);
		$db_name = preg_replace("/[^A-Za-z0-9_\$]/", "", $db_name);
		return $db_name;
	}

?>
<html>
	<head>
		<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
		<title>Create New Database</title>
		<link rel="stylesheet" type="text/css" href="../../../common/css/global.css">
		<link rel="stylesheet" type="text/css" href="../../../common/css/admin.css">
		<link rel="stylesheet" type="text/css" href="../../../common/css/edit.css">
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

		function setUserPartOfName(){
			var ele = document.getElementById("uname");
			if(ele.value==""){
				ele.value = document.getElementById("ugr_Name").value.substr(0,5);
			}
		}

		function onBeforeSubmit(){
<?php if(!is_logged_in()) { ?>

			function __checkMandatory(field, label)
			{

				if(document.getElementById(field).value==="") {
					alert(label+" is mandatory field");
					document.getElementById(field).focus();
					return true;
				}else{
					return false;
				}
			}

			// check mandatory fields
			if(
				__checkMandatory("ugr_FirstName","First name") ||
				__checkMandatory("ugr_LastName","Last name") ||
				__checkMandatory("ugr_eMail","Email") ||
				//__checkMandatory("ugr_Organisation","Institution/company") ||
				//__checkMandatory("ugr_Interests","Research Interests") ||
				__checkMandatory("ugr_Name","Login") ||
				__checkMandatory("ugr_Password","Password")
			){
					return false;
			}

			if(document.getElementById("ugr_Password").value!==document.getElementById("ugr_Password2").value){
					alert("Passwords are different");
					document.getElementById("ugr_Password2").focus();
					return false;
			}
<?php } ?>
			return true;
		}
	</script>

	<body class="popup" onload="hideProgress()">

		<div class="banner"><h2>Create New Database</h2></div>
		<div id="page-inner" style="overflow:auto">

<?php
	$newDBName = "";
	$isNewDB = false; // Used by buildCrosswalks to detemine whether to get data from coreDefinitions.txt (for new database)
	// or by querying an existing Heurist database using getDBStructureAsSQL (for crosswalk)

	global $errorCreatingTables; // Set to true by buildCrosswalks if error occurred
	global $done; // Prevents the makeDatabase() script from running twice
	$done = false; // redundant
    $isCreateNew = true;

	/*****DEBUG****///error_log(" post dbname: $_POST['dbname'] ");  //debug

	if(isset($_POST['dbname'])) {
		$isCreateNew = false;
        $isHuNI = ($_POST['dbtype']=='1');
        $isFAIMS = ($_POST['dbtype']=='2');

		/*verify that database name is unique
		$list = mysql__getdatabases();
		$dbname = $_POST['uname']."_".$_POST['dbname'];
		if(array_key_exists($dbname, $list)){
			echo "<h3>Database '".$dbname."' already exists. Choose different name</h3>";
		}else{*/
?>
		<div id="loading">
					<img src="../../../common/images/mini-loading.gif" width="16" height="16" />
					<strong><span id="divProgress">&nbsp;Creating database, please wait</span></strong>
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
			<form action="createNewDB.php?db=<?= HEURIST_DBNAME ?>" method="POST" name="NewDBName" onsubmit="return onBeforeSubmit()">
<?php if(!is_logged_in()) { ?>
<div id="detailTypeValues" style="border-bottom: 1px solid #7f9db9;padding-bottom:10px;">

	<div style="padding-left:160px">
		Define master user for new database
	</div>

	<div class="input-row required">
		<div class="input-header-cell" for="ugr_FirstName">Given name</div>
		<div class="input-cell"><input id="ugr_FirstName" name="ugr_FirstName" style="width:50;" maxlength="40" /></div>
	</div>

	<div class="input-row required">
		<div class="input-header-cell" for="ugr_LastName">Family name</div>
		<div class="input-cell"><input id="ugr_LastName" name="ugr_LastName" style="width:50;" maxlength="63" /></div>
	</div>
	<div class="input-row required">
		<div class="input-header-cell" for="ugr_eMail">Email</div>
		<div class="input-cell"><input id="ugr_eMail" name="ugr_eMail" style="width:200;" maxlength="100" onKeyUp="document.getElementById('ugr_Name').value=this.value;"/></div>
	</div>
	<div class="input-row required">
		<div class="input-header-cell" for="ugr_Name">Login name</div>
		<div class="input-cell"><input id="ugr_Name" name="ugr_Name" style="width:200;" maxlength="63" onkeypress="{onKeyPress(event)}" onblur="{setUserPartOfName()}"/></div>
	</div>
	<div class="input-row required">
		<div class="input-header-cell" for="ugr_Password">Password</div>
		<div class="input-cell"><input id="ugr_Password" name="ugr_Password" type="password" style="width:50;" maxlength="40" />
			<div class="help prompt help1" >
				<div style="color:red;">Warning: non-encrypted, please don't use an important password such as institutional login</div>
			</div>
		</div>
	</div>
	<div class="input-row required">
		<div class="input-header-cell" for="ugr_Password2">Repeat password</div>
		<div class="input-cell"><input id="ugr_Password2" type="password" style="width:50;" maxlength="40" />
		</div>
	</div>
</div>
<?php } ?>
			<div style="border-bottom: 1px solid #7f9db9;padding-bottom:10px; padding-top: 10px;">
				<input type="radio" name="dbtype" value="0" id="rb1" checked="true" /><label for="rb1" class="labelBold">Standard database</label>
				<div style="padding-left: 38px;padding-bottom:10px">Gives an uncluttered database with essential record and field types. Recommended for general use</div>
				<input type="radio" name="dbtype" value="1" id="rb2" /><label for="rb2" class="labelBold">HuNI Core schema</label>
                <div style="padding-left: 38px;">The <a href="http://huni.net.au" target=_blank>
                    Humanities Networked Infrastructure (HuNI)</a> core entities and field definitions, allowing easy harvesting into the HuNI aggregate</div>
                <input type="radio" name="dbtype" value="2" id="rb3" /><label for="rb3" class="labelBold">FAIMS Core schema</label>
                <div style="padding-left: 38px;">The <a href="http://fedarch.org" target=_blank>
                    Federated Archaeological Information Management System (FAIMS)</a> core entities and field definitions, providing a minimalist framework for archaeological fieldwork databases</div>

				<p>Further structure can be downloaded, after database creation, from the FAIMS Community Server (#10) or HuNI Community Server (#11), 
                    or from other databases registered with Heurist, using Import Structure in the Essentials menu on the left</p>
                <p><ul><li>New database creation takes 10 - 20 seconds. New databases are created on the current server</li>
					<li>You will become the owner and administrator of the new database</li>
					<li>The database will be created with the prefix "<?= HEURIST_DB_PREFIX ?>"
				(all databases created by this installation of the software will have the same prefix)</li>
                </ul></p>
			</div>

			<h3>Enter a name for the new database:</h3>
			<div style="margin-left: 40px;">
					<!-- user name used as prefix -->
					<b><?= HEURIST_DB_PREFIX ?>
					<input type="text" maxlength="20" size="6" name="uname" id="uname" onkeypress="{onKeyPress(event)}"
				    style="padding-left:3px; font-weight:bold;" value=<?=(is_logged_in()?prepareDbName():'')?> >
				    _  </b>
					<input type="text" maxlength="64" size="25" name="dbname"  onkeypress="{onKeyPress(event);}">
					<input type="submit" name="submit" value="Create database" style="font-weight: bold;"  >
					<p>The user name prefix is editable, and may be blank, but we suggest using a consistent prefix for personal databases<br> so that all your personal databases appear together in the list of databases<p></p>
			</div>
			</form>
		</div> <!-- createDBForm -->
<?
	}


	function isInValid($str) {
		return preg_match('[\W]', $str);
	}

	function cleanupNewDB ($newname) { // called in case of failure to remove the partially created database
		global $newDBName, $isNewDB, $done;
		$cmdline = "mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'drop database `$newname`'";
		$output2=exec($cmdline . ' 2>&1', $output, $res2);
		echo "<br>Database cleanup for $newname, completed<br>&nbsp;<br>";
		echo($output2);
		$done = true;
	} // cleanupNewDB



	function makeDatabase() { // Creates a new database and populates it with triggers, constraints and core definitions

		global $newDBName, $isNewDB, $done, $isCreateNew, $isHuNI, $isFAIMS, $errorCreatingTables;

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
				$cmdline = "mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e\"create database `$newname`\"";
				} else {
				$cmdline = "mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -e'create database `$newname`'";
			}
			$output1 = exec($cmdline . ' 2>&1', $output, $res1);
			if ($res1 != 0 ) {
				echo ("<p class='error'>Error code $res1 on MySQL exec: Unable to create database $newname<br>&nbsp;<br>");
				echo("\n\n");

				if(is_array($output)){
					$isExists = (strpos($output[0],"1007")>0);
				}else{
					$sqlErrorCode = split(" ", $output);
					$isExists = (count($sqlErrorCode) > 1 &&  $sqlErrorCode[1] == "1007");
				}
				if($isExists){
					echo "<strong>A database with that name already exists.</strong>";
				}
				echo "</p>";
					$isCreateNew = true;
				return false;
			}

			// At this point a database exists, so need cleanup if anythign goes wrong later

			// Create the Heurist structure for the newly created database, using the template SQL file
			// This file sets up teh table definitions and inserts a few critical values
			// it does not set referential integrity constraints or triggers
			$cmdline="mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < blankDBStructure.sql";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			if ($res2 != 0 ) {
				echo ("<p class='error'>Error $res2 on MySQL exec: Unable to load blankDBStructure.sql into database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed<br>&nbsp;<br></p>");
				echo($output2);
				cleanupNewDB($newname);
				return false;
			}

			// Add referential constraints
			$cmdline="mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addReferentialConstraints.sql";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			if ($res2 != 0 ) {
				echo ("<p class='error'>Error $res2 on MySQL exec: Unable to load addReferentialConstraints.sql into database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed<br>&nbsp;<br></p>");
				echo($output2);
				cleanupNewDB($newname);
				return false;
			}

			// Add procedures and triggers
			$cmdline = "mysql -h".HEURIST_DBSERVER_NAME." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD." -D$newname < addProceduresTriggers.sql";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);

			if ($res2 != 0 ) {
				echo ("<p class='error'>Error $res2 on MySQL exec: Unable to load addProceduresTriggers.sql for database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed<br>&nbsp;<br></p>");
				echo($output2);
				cleanupNewDB($newname);
				return false;
			}

			// Run buildCrosswalks to import minimal definitions from coreDefinitions.txt into the new DB
			// yes, this is badly structured, but it works - if it ain't broke ...
			$isNewDB = true; // flag of context for buildCrosswalks, tells it to use coreDefinitions.txt

			require_once('../../structure/import/buildCrosswalks.php');

			// errorCreatingTables is set to true by buildCrosswalks if an error occurred
			if($errorCreatingTables) {
				echo ("<p class='error'>Error importing core definitions from ".($isHuNI?"coreDefinitionsHuNI.txt":(($isFaims)?"coreDefinitionsFAIMS.txt":"coreDefinitions.txt"))." for database $newname<br>");
				echo ("Please check whether this file is valid; consult Heurist helpdesk if needed</p>");
				cleanupNewDB($newname);
				return false;
			}

			// Get and clean information for the user creating the database
			if(!is_logged_in()) {
				$longName = "";
				$firstName = $_REQUEST['ugr_FirstName'];
				$lastName = $_REQUEST['ugr_LastName'];
				$eMail = $_REQUEST['ugr_eMail'];
				$name = $_REQUEST['ugr_Name'];
				$password = $_REQUEST['ugr_Password'];
				$department = '';
				$organisation = '';
				$city = '';
				$state = '';
				$postcode = '';
				$interests = '';

				$s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
				$salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
				$password = crypt($password, $salt);

			}else{
				mysql_connection_insert(DATABASE);
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
			}

			//	 todo: code location of upload directory into sysIdentification, remove from edit form (should not be changed)
			//	 todo: might wish to control ownership rather than leaving it to the O/S, although this works well at present

			$warnings = 0;

			// Create a default upload directory for uploaded files eg multimedia, images etc.
			$uploadPath = HEURIST_UPLOAD_ROOT.$newDBName;//TODO: This locks us into upload path. This is teh place for DB override.
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
			$cmdline = "cp -R ../rectype-icons $uploadPath"; // creates directories and copies icons and thumbnails
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);
			if ($res2 != 0 ) {
				echo ("<h3>Warning:</h3> Unable to create/copy record type icons folder rectype-icons to $uploadPath<br>");
				echo ("If upload directory was created OK, this is probably due to incorrect file permissions on new folders<br>");
				echo($output2);
				$warnings = 1;
			}
			// copy smarty template directory from default set in the program code
			$cmdline = "cp -R ../smarty-templates $uploadPath";
			$output2 = exec($cmdline . ' 2>&1', $output, $res2);
			if ($res2 != 0 ) {
				echo ("<h3>Warning:</h3> Unable to create/copy smarty-templates folder to $uploadPath<br>");
				echo($output2);
				$warnings = 1;
			}

			// copy xsl template directories from default set in the program code
			$cmdline = "cp -R ../xsl-templates $uploadPath";
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
            $warnings =+ createFolder("backup","used to write files for user data dump");
            $warnings =+ createFolder("term-images","used for images illustrating terms");

			if ($warnings > 0) {
				echo "<h2>Please take note of warnings above</h2>";
				echo "You must create the folders indicated or uploads, icons, templates, publishing, term images etc. will not work<br>";
				echo "If upload folder is created but icons and template folders are not, look at file permissions on new folder creation";
			}

			// Prepare to write to the newly created database
			mysql_connection_insert($newname);

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

			echo "<p>You may wish to bookmark the database home page (search page): <a href=\"".HEURIST_BASE_URL."?db=".$newDBName."\" title=\"\" target=\"_new\">".HEURIST_BASE_URL."?db=".$newDBName."</a>.</p>";
			echo "<p><a href='".HEURIST_BASE_URL."admin/adminMenu.php?db=".$newDBName."' title='' target=\"_new\" style='font-size:1.2em;font-weight:bold'>Go to Administration page</a>, to configure your new database</p>";

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


