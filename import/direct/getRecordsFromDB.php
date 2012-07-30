<?php

	/* getRecordsFromDB.php - gets all records in a specified database (later, a selection) and write directly to current DB
	* Reads from either H2 or H3 format databases
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
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
	require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");
	require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
		print "<html><body><p>You must be an administrator to import records from a source database</p><p><a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
		return;
	}
?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Heurist - Direct database transfer</title>
	</head>
	<body>
		<!-- script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
		<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
		<script src="../../common/php/loadCommonInfo.php"></script -->

		<?php

			mysql_connection_db_overwrite(DATABASE);
			if(mysql_error()) {
				die("Sorry, could not connect to the database (mysql_connection_db_overwrite error)");
			}

			print "<h2>Heurist Direct Data Transfer</h2>";
			print "<h2>FOR  ADVANCED USERS ONLY</h2>";
			print "This script reads records from a source database of H2 or H3 format, maps the record type, field type and term codes to new values, ";
			print "and writes the records into the current logged-in database. It also transfers uploaded file records. It does not (at present) transfer tags and othe user data";
			print "The current database can already contain data, new records are appended and IDs adjsuted for records and files.<br>";
			print "<br>";
			print "Make sure the target records and field types are compatible. <b>";
			print "If you get the codes wrong, you will get a complete dog's breakfast in your target database ...</b><p>\n";

			//print ">>>".(defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);

			$dt_SourceRecordID = (defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);
			if($dt_SourceRecordID==0){  //getDetailTypeLocalID
				//add missed detail type
				mysql_query("INSERT INTO `defDetailTypes` ( dty_Name,  dty_Documentation,  dty_Type,  dty_HelpText,  dty_EntryMask,  dty_Status,
					dty_OriginatingDBID,  dty_NameInOriginatingDB,  dty_IDInOriginatingDB,  dty_DetailTypeGroupID,  dty_OrderInGroup,  dty_JsonTermIDTree,  dty_TermIDTreeNonSelectableIDs,
				dty_PtrTargetRectypeIDs,  dty_FieldSetRectypeID,  dty_ShowInLists,  dty_NonOwnerVisibility,  dty_Modified,  dty_LocallyModified) VALUES ('Original ID',' ','freetext','The original ID of the record in a source database from which these data were imported','','reserved',2,'Original ID',36,99,0,'','','',0,1,'viewable','2011-10-12 09:05:19',0)");

				if (mysql_error()) {
					error_log(mysql_error());
				}else{
					$dt_SourceRecordID = mysql_insert_id();
					define('DT_ORIGINAL_RECORD_ID', $dt_SourceRecordID);
				}

			}
			if($dt_SourceRecordID==0){
				print "<hr><b>Original record IDs</b> ".
				"<br/>This data transfer function saves the original (source) record IDs in the <i>Original ID</i> field (origin code 2-589) for each record".
				"<br/>This field does not exist in the database - please import it from the Heurist Core definitions database (db#2)".
				"<br/>You do not need to add the <i>Original ID</i> field to each record type, it is recorded automatically as additional data.".
				"<p><a href=../../admin/structure/selectDBForImport.php?db=" . HEURIST_DBNAME . " title='Import database structure elements' target=_blank><b>Import structure elements</b></a> (loads in new tab)".
				"<br/>(choose H3 Core definitions database (db#2), import the <i>Original ID container record</i>, then delete it - the required field remains)".
				"<br/>Reload this page after importing the field<hr><p>";
			}else{
				print "<hr><b>Original record IDs</b> ".
				"<br/>This data transfer function saves the original (source) record IDs in the <i>Original ID</i> field (origin code 2-36) for each record".
				"<br/>You do not need to add the <i>Original ID</i> field to each record type, it is recorded automatically as additional data.";
			}


			$sourcedbname = NULL;
			$password = NULL;
			$username = NULL;

			$is_h2 = array_key_exists('h2', $_REQUEST) && ($_REQUEST['h2']==1);

			$db_prefix = $is_h2?"heuristdb-" :$dbPrefix;

			// ----FORM 1 - SELECT THE SOURCE DATABASE --------------------------------------------------------------------------------

			if(!array_key_exists('mode', $_REQUEST) || !array_key_exists('sourcedbname', $_REQUEST)){


				print "<form name='selectdbtype' action='getRecordsFromDB.php' method='get'>";
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				if(!$is_h2){
					print "<input name='h2' value='1' type='hidden'>";
				}
				print "<input type='submit' value='Switch to H".($is_h2?"3":"2")." databases' /><br/>";
				print "</form>";

				print "<form name='selectdb' action='getRecordsFromDB.php' method='post'>";
				//  We have to use 'get', 'post' fails to transfer target database to step 2
				print "<input name='mode' value='2' type='hidden'>"; // calls the form to select mappings, step 2
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				print "<input name='h2' value='".($is_h2?1:0)."' type='hidden'>";
				// print "Enter source database name (prefix added automatically): <input type='text' name='sourcedbname' />";
				print "Choose source database: <select id='db' name='sourcedbname'>";
				$query = "show databases";
				$res = mysql_query($query);
				while ($row = mysql_fetch_array($res)) {
					$test=strpos($row[0],$db_prefix);
					if (is_numeric($test) && ($test==0) ) {
						$name = substr($row[0],strlen($db_prefix));  // delete the prefix
						print "<option value='$name'>$name</option>";
					}
				}
				print "</select>";
				if(!$is_h2){
				print "<div style=\"padding:5px;\">";
				print "Username:&nbsp;<input type='text' name='username' id='username' size='20' class='in'>&nbsp;&nbsp;";
				print "Password:&nbsp;<input type='password' name='password' size='20' class='in'>&nbsp;&nbsp;";
				print "Use the same as current:&nbsp;<input type='checkbox' checked='checked' name='samelogin' value='1'/>";

				if(array_key_exists('loginerror', $_REQUEST) && $_REQUEST['loginerror']=='1'){
					print '<br/><font color="#ff0000">Incorrect Username / Password for source database</font>';
				}

				print "</div>";
				}
				print "<input type='submit' value='Continue'/>";
				print "</form>";
				exit;
			}

			// ----FORM 2 - MAPPINGS FORM ----------------------------------------------------------------------------------------

			$sourcedbname = $_REQUEST['sourcedbname'];

			if(!$is_h2){
				//verify user+password for source database
				$usecurrentlogin = array_key_exists('samelogin', $_REQUEST) && $_REQUEST['samelogin']=='1';
				if($usecurrentlogin || (!(@$_REQUEST['username']  and  @$_REQUEST['password'])) ){
					$username = get_user_username();
					//take from database
					$res = mysql_query('select * from '.USERS_TABLE.' where '.USERS_USERNAME_FIELD.' = "'.addslashes($username).'"');
					$user = mysql_fetch_assoc($res);
					if ($user){
						$password = $user[USERS_PASSWORD_FIELD];
					}else{
						$password = "";
					}
					$needcrypt = false;

				} else {
					$username = $_REQUEST['username'];
					$password = $_REQUEST['password'];
					$needcrypt = true;
				}

				mysql_connection_db_select($db_prefix.$sourcedbname);

				$res = mysql_query('select * from '.USERS_TABLE.' where '.USERS_USERNAME_FIELD.' = "'.addslashes($username).'"');
    			if ( ($user = mysql_fetch_assoc($res))  &&
		 			$user[USERS_ACTIVE_FIELD] == 'y'  &&
		 			(($needcrypt && crypt($password, $user[USERS_PASSWORD_FIELD]) == $user[USERS_PASSWORD_FIELD]) ||
		 			 (!$needcrypt && $password == $user[USERS_PASSWORD_FIELD]))
		 			)
			    {

				}else{
					header('Location: ' . HEURIST_URL_BASE . 'import/direct/getRecordsFromDB.php?loginerror=1&db='.HEURIST_DBNAME);
					exit;
				}
				mysql_connection_db_overwrite(DATABASE);
			}


			if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){

				createMappingForm(null);

			} else

				// ---- visit #3 - SAVE SETTINGS -----------------------------------------------------------------

				if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='3'){
					saveSettings();
				} else

					// ---- visit #4 - LOAD SETTINGS -----------------------------------------------------------------

					if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='4'){
						loadSettings();
					} else

						// ---- visit #5 - PROCESS THE TRANSFER -----------------------------------------------------------------

						if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='5'){
							doTransfer();
							//transfer();
						}

						// ---- Create mapping form -----------------------------------------------------------------

						function getPresetId($config, $id){
				if($config && array_key_exists($id, $config)){
					return $config[$id];
				}else{
					return null;
				}
			}

			function createMappingForm($config){

				global $sourcedbname, $db_prefix, $dbPrefix, $is_h2, $password, $username;

				$sourcedb = $db_prefix.$sourcedbname;

				print "<br>\n";
				print "Source database: <b>$sourcedb</b> <br>\n";

				if($is_h2){
					$res=mysql_query("select * from `$sourcedb`.Users");
				}else{
					$res=mysql_query("select * from $sourcedb.sysIdentification");
				}
				if (!$res) {
					die ("<p>Unable to open source database <b>$sourcedb</b>. Make sure you have included prefix");
				}


				print "<form name='mappings' action='getRecordsFromDB.php' method='post'>";
				print "<input id='mode' name='mode' value='5' type='hidden'>"; // calls the transfer function
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				print "<input name='h2' value='".($is_h2?1:0)."' type='hidden'>";
				print "<input name='sourcedbname' value='$sourcedbname' type='hidden'>";
				if(!$is_h2){
					print "<input name='username' value='$username' type='hidden'>";
					print "<input name='password' value='$password' type='hidden'>";
				}
				print "<input name='reportlevel' value='1' type='checkbox' checked='checked'>Report level: show errors only<br>";
				print "Check the code mappings below, then click  <input type='button' value='Import data' onclick='{document.getElementById(\"mode\").value=5; document.forms[\"mappings\"].submit();}'>\n";
				// alert(document.getElementById(\"mode\").value);

				print "<input type='button' value='Save settings' onclick='{document.getElementById(\"mode\").value=3; document.forms[\"mappings\"].submit();}'>";

				$filename = HEURIST_UPLOAD_DIR."settings/importfrom_".$sourcedbname.".cfg";

				if(file_exists($filename)){
					print "<input type='submit' value='Load settings' onclick='{document.getElementById(\"mode\").value=4; document.forms[\"mappings\"].submit();}'>\n";
				}

				print "<p><hr>\n";

				// --------------------------------------------------------------------------------------------------------------------
				// Get the record type mapping, by default assume that the code is unchanged so select the equivalent record type if available
				$entnames = getAllRectypeStructures(); //in current database
				$entnames = $entnames['names'];
				$seloptions = createOptions("or", $entnames);

				if($is_h2){
					$query1 = "SELECT DISTINCT `rec_type`,`rt_name` FROM `$sourcedb`.`records`,`$sourcedb`.`rec_types` where `rec_type`=`rt_id`";
				}else{
					$query1 = "SELECT rty_ID, rty_Name, count(rec_ID) as cnt ".
					"from `$sourcedb`.`Records` ".
					"left join `$sourcedb`.`defRecTypes` on rec_RecTypeID=rty_ID ".
					"group by rty_ID";
				}
				$res1 = mysql_query($query1);
				if (mysql_num_rows($res1) == 0) {
					die ("<p><b>Sorry, there are no data records in this database, or database is bad format</b>");
				}
				print "<h3>Record type mappings</h3>[RT code] <b>$sourcedb</b> (use count) ==> <b>$dbPrefix" . HEURIST_DBNAME."</b><p>";// . "<p>";
				print "<table>";
				while ($row1 = mysql_fetch_array($res1)) {
					$rt=$row1[0]; //0=rec_RecTypeID
					$cnt=$row1[2];
					$selopts = $seloptions;
					$selectedId = getPresetId($config,"cbr".$rt);
					if(!$selectedId){	//find the closest name
						$selectedId = findClosestName($row1[1], $entnames);  //1=rty_Name
					}
					if($selectedId){
						$repl = "value='".$selectedId."'";
						$selopts = str_replace($repl, $repl." selected='selected' ", $selopts);
					}

					print "<tr><td>[ $rt ] ".$row1[1]."($cnt) </td>".
					"<td>==> <select id='cbr$rt' name='cbr$rt' class='rectypes'><option id='or0' value='0'></option>".$selopts."</select></td></tr>\n";
				} // loop through record types
				print "</table>";


				// --------------------------------------------------------------------------------------------------------------------
				// Get the field type mapping, by default assume that the code is unchanged so select the equivalent detail type if available
				//create the string for combobox
				$entnames = getAllDetailTypeStructures(); //in current database
				$entnames = $entnames['names'];
				$seloptions = createOptions("od", $entnames);

				print "<h3>Field type mappings</h3>[FT code] <b>$sourcedb</b> ==> <b>$dbPrefix" . HEURIST_DBNAME."</b><p>";// . "<p>";
				if($is_h2){
					$query1 = "SELECT DISTINCT `rd_type`,`rdt_name`,`rdt_type` FROM `$sourcedb`.`rec_details`,`$sourcedb`.`rec_detail_types` ".
					"where `rd_type`=`rdt_id`";
				}else{
					$query1 = "SELECT DISTINCT `dtl_DetailTypeID`,`dty_Name`,`dty_Type` FROM `$sourcedb`.`recDetails`,`$sourcedb`.`defDetailTypes` ".
					"where `dtl_DetailTypeID`=`dty_ID`";
				}
				$res1 = mysql_query($query1);
				print "<table>";
				while ($row1 = mysql_fetch_array($res1)) {
					$ft=$row1[0]; //0=dtl_DetailTypeID

					$selopts = $seloptions;
					//find the closest name
					$selectedId = getPresetId($config,"cbd".$ft);
					if(!$selectedId){	//find the closest name
						$selectedId = findClosestName($row1[1], $entnames); //dty_Name
					}
					if($selectedId){
						$repl = "value='".$selectedId."'";
						$selopts = str_replace($repl, $repl." selected='selected' ", $selopts);
					}

					print "<tr><td>[ $ft ] - ".$row1[2]." ".$row1[1]." </td>".  //2=dty_Type
					"<td>==> <select id='cbd$ft' name='cbd$ft' class='detailTypes'><option id='od0' value='0'></option>".
					$selopts."</select></td></tr>\n";
				} // loop through field types
				print "</table>";

				// --------------------------------------------------------------------------------------------------------------------

				createTermsOptions($config, 'enum');
				createTermsOptions($config, 'relation');
				print "</form>";

			}

			function createTermsOptions($config, $type){

				global $sourcedbname, $db_prefix, $dbPrefix, $is_h2;

				$sourcedb = $db_prefix.$sourcedbname;

				$entnames = getTerms(); //in current database
				$entnames = $entnames['termsByDomainLookup'][$type];
				foreach ($entnames as $id => $name) {
					$entnames[$id] = $name[0];
				}
				$seloptions = createOptions("ot", $entnames);


				print "<h3>Term mappings ($type"."s)"."</h3>[Term code] <b>$sourcedb</b> ==> <b>$dbPrefix" . HEURIST_DBNAME."</b><p>";// . "<p>";
				// Get the term mapping, by default assume that the code is unchanged so select the equivalent term if available
				if($is_h2){
					$query1 = "SELECT DISTINCT `rdl_id`,`rdl_id`,`rd_val` FROM `$sourcedb`.`rec_details`,`$sourcedb`.`rec_detail_lookups` ".
					"where (`rd_type`=`rdl_rdt_id`) AND (`rdl_value`=`rd_val`) AND (`rdl_related_rdl_id` is ".
					(($type!='enum')?"not":"")." null)";
				}else{
					if($type!='enum'){
						$type = 'relationtype';
					}

					$query1 = "SELECT DISTINCT `dtl_Value`,`trm_ID`,`trm_Label` FROM `$sourcedb`.`recDetails`,`$sourcedb`.`defTerms` ".
					"where (`dtl_Value`=`trm_ID`) AND (`dtl_DetailTypeID` in (select `dty_ID` from `$sourcedb`.`defDetailTypes` ".
					"where (`dty_Type`='$type') ))";
				}
				$res1 = mysql_query($query1);
				print "<table>";
				while ($row1 = mysql_fetch_array($res1)) {
					$tt=$row1[0]; //0=trm_ID

					$selopts = $seloptions;
					//find the closest name
					$selectedId = getPresetId($config,"cbt".$tt);
					if(!$selectedId){	//find the closest name
						$selectedId = findClosestName($row1[2], $entnames); //trm_Label
					}
					if($selectedId){
						$repl = "value='".$selectedId."'";
						$selopts = str_replace($repl, $repl." selected='selected' ", $selopts);
					}

					print "<tr><td>[ $tt ] ".$row1[2]." </td>".
					"<td>==> <select id='cbt$tt' name='cbt$tt' class='terms'><option id='ot0' value='0'></option>".
					$selopts."</select></td></tr>\n";
				} // loop through terms
				print "</table>";
			}

			// ---- Create options for HTML select -----------------------------------------------------------------

			function createOptions($pref, $names){

				$res = "";

				foreach ($names as $id => $name) {
					$res = $res."<option id='".$pref.$id."' name='".$pref.$id."' value='".$id."'>".$name." [$id]</option>";
				}

				return $res;
			}

			//
			// returns the id in array with closest similar name
			// return null if no similar names are found
			//
			function findClosestName($tocompare, $names){

				$tocompare = strtolower($tocompare);
				$minp = 55;
				$keepid = null;

				foreach ($names as $id => $name) {

					$name = strtolower($name);
					if($tocompare == $name){
						return $id;
					}else{
						similar_text($tocompare, $name, $p);
						if($p > $minp){
							$keepid = $id;
							$minp = $p;
						}
					}

				}

				return $keepid;
			}

			// ---- SAVE CURRENT SETTINGS INTO FILE --------------------------------------------------------------

			function loadSettings(){
				global $sourcedbname;

				$filename = HEURIST_UPLOAD_DIR."settings/importfrom_".$sourcedbname.".cfg";

				$str = file_get_contents($filename);

				$arr = explode(",",$str);
				$config = array();
				foreach ($arr as $item2) {
					$item = explode("=",$item2);
					if(count($item)<2){
					}else{
						//print $item2."  ";
						$config["".$item[0]] = $item[1];
					}
				}

				print "SETTINGS ARE LOADED!!!!";
				createMappingForm($config);
			}

			function saveSettings(){
				global $sourcedbname;

				$config = array();
				$str = "";
				foreach ($_REQUEST as $name => $value) {
					$pos = strpos($name,"cb");
					if(is_numeric($pos) && $pos==0){
						$str  = $str.$name."=".$value.",";
						$config[$name] = $value;
					}
				}

				$folder = HEURIST_UPLOAD_DIR."settings/";

				if(!file_exists($folder)){
					if (!mkdir($folder, 0777, true)) {
						die('Failed to create folder for settings');
					}
				}

				$filename = $folder."importfrom_".$sourcedbname.".cfg";

				file_put_contents($filename, $str);

				print "SETTINGS ARE SAVED!!!!";
				createMappingForm($config);
			}

			// ---- TRANSFER AND OTHER FUNCTIONS -----------------------------------------------------------------

			function doTransfer()
			{
				global $sourcedbname, $dbPrefix, $db_prefix, $is_h2;

				$sourcedb = $db_prefix.$sourcedbname;

				$rep_errors_only = (array_key_exists('reportlevel', $_REQUEST) && $_REQUEST['reportlevel']=="1");


				echo "<p>Now copying data from <b>$sourcedb</b> to <b>". $dbPrefix.HEURIST_DBNAME. "</b><p>Processing: ";

				$terms_h2 = array();

				// Loop through types for all records in the database (actual records, not defined types)
				if($is_h2){
					//load all terms
					$query1 = "SELECT `rdl_id`,`rdl_value` FROM `$sourcedb`.`rec_detail_lookups`";
					$res1 = mysql_query($query1);
					while ($row1 = mysql_fetch_array($res1)) {
						$terms_h2[$row1[1]] = $row1[0];
					}

					$query1 = "SELECT DISTINCT (`rec_type`) FROM `$sourcedb`.`records`";
				}else{
					$query1 = "SELECT DISTINCT (`rec_RecTypeID`) FROM $sourcedb.Records";
				}
				$res1 = mysql_query($query1);
				if(!$res1) {
					print "<br>Bad query for record type loop $res1 <br>";
					print "$query1<br>";
					die ("<p>Sorry ...</p>");
				}


				$added_records = array();
				$unresolved_pointers = array();
				$missed_terms = array();
				$missed_terms2 = array();

				/*$detailTypes = getAllDetailTypeStructures(); //in current database
				$detailTypes = $detailTypes['typedefs'];
				$fld_ind = $detailTypes['fieldNamesToIndex']['dty_Type']
				$detailTypes[$dttype][$fld_ind];*/

				print "<br>************************************************<br>Import records";
				print "<br>The following section adds records and allocates them new IDs.";
				if(!$rep_errors_only){
					print "<br>It reports this in the form Old ID => New ID";
				}




				// loop through the set of rectypes actually in the records in the database
				while ($row1 = mysql_fetch_array($res1)) {
					$rt = $row1[0];

					if(!array_key_exists('cbr'.$rt, $_REQUEST)) continue;

					$recordType = $_REQUEST['cbr'.$rt];
					if(intval($recordType)<1) {
						print "<br>Record type $rt is not mapped";
						ob_flush();flush(); // flush to screen
						continue;
					}

					//@todo - add record type name
					$rt_counter = 0;
					print "<br>Record type: $rt"; // tell user somethign is happening
					ob_flush();flush(); // flush to screen
					if($is_h2){
						$query2 = "select `rec_id`,`rec_url` from `$sourcedb`.`records` Where `$sourcedb`.`records`.`rec_type`=$rt";
					}else{
						$query2 = "select `rec_ID`,`rec_URL` from $sourcedb.Records Where $sourcedb.Records.rec_RecTypeID=$rt";
					}
					$res2 = mysql_query($query2);
					if(!$res2) {
						print "<div  style='color:red;'>Bad query for records loop for source record type $rt</div>";
						print "<br>Query: $query2";
						ob_flush();flush(); // flush to screen
						continue;
						//die ("<p>Sorry ...</p>");
					}

					//special detailtype to keep original record id
					$dt_SourceRecordID = (defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);


					while ($row2 = mysql_fetch_array($res2)) {

						//select details and create details array
						$rid = $row2[0]; //record id

						//				print "<br>".$rid."&nbsp;&nbsp;&nbsp;";

						if($is_h2){
							$query3 = "SELECT `rd_type`, `rdt_type`, `rd_val`, `rd_file_id`, astext(`rd_geo`)
							FROM `$sourcedb`.`rec_details` rd, `$sourcedb`.`rec_detail_types` dt where rd.`rd_type`=dt.`rdt_id` and rd.`rd_rec_id`=$rid order by `rd_type`";
						}else{
							$query3 = "SELECT `dtl_DetailTypeID`, `dty_Type`, `dtl_Value`, `dtl_UploadedFileID`, astext(`dtl_Geo`)
							FROM $sourcedb.`recDetails` rd, $sourcedb.`defDetailTypes` dt where rd.`dtl_DetailTypeID`=dt.`dty_ID` and rd.`dtl_RecID`=$rid order by `dtl_DetailTypeID`";
						}
						$res3 = mysql_query($query3);
						// todo: check query was successful
						if(!$res3) {
							print "<br>record ".$rid."&nbsp;&nbsp;&nbsp;<div  style='color:red;'>bad select of detail fields</div>";
							print "<br>query: $query3";
							ob_flush();flush(); // flush to screen
							continue;
							//die ("<p>Sorry ...</p>");
						}

						$unresolved_records = array();
						$details = array();
						$dtid = 0;
						$key = 0;
						$ind = 0;
						$values = array();

						//add special detail type 2-589 - reference to original record id
						if($dt_SourceRecordID>0){
							$details["t:".$dt_SourceRecordID] = array('0'=>$rid);
						}

						while ($row3 = mysql_fetch_array($res3)) {

							if($dtid != $row3[0]){
								if($key>0) {
									$details["t:".$key] = $values;
								}
								$dtid = $row3[0];
								$values = array();
								$ind;
							}

							if(!array_key_exists('cbd'.$row3[0], $_REQUEST)) continue;
							$key = $_REQUEST['cbd'.$row3[0]];
							if(intval($key)<1) {
								if($rt==52){//debug
									print "mapping not defined for detail #".$dtid;
								}
								//mapping for this detail type is not specified
								continue;
							}

							$value = $row3[2];

							//determine the type of filedtype
							if($row3[1]=='enum' || $row3[1]=='relationtype'){

								if($is_h2){
									if(array_key_exists($value, $terms_h2)){
										$value = $terms_h2[$value];
									}else{
										if(array_search($value, $missed_terms)==false){
											array_push($missed_terms, $value);
										}
									}
								}
								$term = getDestinationTerm($value);

								if($term==null){

									$ind = array_search(intval($value), $missed_terms2);
									if ( count($missed_terms2)==0 || ($ind==0 && $missed_terms2[$ind]!=intval($value)) ) {
										array_push($missed_terms2, intval($value));
									}
									$value = null;
								}else{
									$value = $term;
								}

							}else if($row3[1]=='file'){

								if($is_h2){
									$value = copyRemoteFileH2($row3[3]); //returns new file id
								}else{
									$value = copyRemoteFile($row3[3]); //returns new file id
								}

							}else if($row3[1]=='geo'){

								$value = $value." ".$row3[4]; // string   geotype+space+wkt

							}else if($row3[1]=='relmarker'){

							}else if($row3[1]=='resource'){
								//find the id of record in destionation database among pairs of added records
								if(array_key_exists($value, $added_records)){
									$value = $added_records[$value];
								}else{
									array_push($unresolved_records, $key."|".$value);
									//print "<div  style='color:#ffaaaa;'>resource record#".$value." not found</div>";
									$value = null; //ingnore
								}
							}

							if($value!=null){
								$values[$ind] = $value;
								$ind++;
							}

						}//while by details

						//for last one
						if($key>0 && count($values)>0){
							$details["t:".$key] = $values;
						}

						/*****DEBUG****///error_log("DETAILS:>>>>".print_r($details,true));
						$ref = null;

						//add-update Heurist record
						$out = saveRecord(null, //record ID
							$recordType,
							$row2[1], // URL
							null, //Notes
							null, //???get_group_ids(), //group
							null, //viewable    TODO: SHOULD BE A CHOICE
							null, //bookmark
							null, //pnotes
							null, //rating
							null, //tags
							null, //wgtags
							$details,
							null, //-notify
							null, //+notify
							null, //-comment
							null, //comment
							null, //+comment
							$ref,
							$ref,
							2	// import without check of record type structure
						);

						if (@$out['error']) {
							print "<br>Source record# ".$rid."&nbsp;&nbsp;&nbsp;";
							print "=><div style='color:red'> Error: ".implode("; ",$out["error"])."</div>";
						}else{

							$new_recid = $out["bibID"];
							$added_records[$rid] = $new_recid;

							$rt_counter++;

							if(count($unresolved_records)>0){
								$unresolved_pointers[$new_recid] = $unresolved_records;
							}

							if(!$rep_errors_only){
								print "<br>".$rid."&nbsp;=>&nbsp;".$out["bibID"];

								if (@$out['warning']) {
									print "<br>Warning: ".implode("; ",$out["warning"]);
								}
							}

						}
					}//while by record of particular record type

					if($rt_counter>0){
						print "&nbsp;&nbsp;=> added $rt_counter records";
					}

					ob_flush();flush(); // flush to screen

				} // end of loop for record types

				/*****DEBUG****///error_log("DEBUG: UNRESOLVED POINTERS>>>>>".print_r($unresolved_pointers, true));

				if(count($missed_terms)>0){
					print "<br><br>*********************************************************";
					print "<br>These terms IDs are not found in $sourcedb<br>";
					print implode('<br>',$missed_terms);
				}

				if(count($missed_terms2)>0){
					print "<br><br>*********************************************************";
					print "<br>Mapping for these terms IDs is not specified<br>";
					print implode('<br>',$missed_terms2);
				}

				if(count($unresolved_pointers)>0){

					$notfound_rec = array();

					print "<br><br>*********************************************************";
					print "<br>Finding and setting unresolved record pointers<br>";
					if(!$rep_errors_only){
						print "<br>It reports in form: source RecID => now target pointer RecID => in Rec Id<br>";
					}

					//resolve record pointers
					$inserts = array();
					foreach ($unresolved_pointers as $recid => $unresolved_records) {

						foreach ($unresolved_records as $code) {

							//print "<br>".$code;
							$aa = explode("|", $code);
							$dt_id = $aa[0];
							$src_recid = $aa[1];

							//print "    ".$dt_id."=".$src_recid;

							if(array_key_exists($src_recid, $added_records)){

								if(!$rep_errors_only){
									print "<br>".$src_recid."=>".$added_records[$src_recid]."=>".$recid;
								}

								array_push($inserts, "($recid, $dt_id, ".$added_records[$src_recid].", 1)");
							}else{
								if(array_search($src_recid, $notfound_rec)==false){
									array_push($notfound_rec, $src_recid." for ".$recid);
								}
							}
						}
					}

					if(count($notfound_rec)>0){
						print "<br>These records are specified as pointers in source database but they were not added into target database:<br>";
						print implode('<br>',$notfound_rec);
					}


					if (count($inserts)>0) {//insert all new details
						$query1 = "insert into $dbPrefix".HEURIST_DBNAME.".recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) values " . join(",", $inserts);
						/*****DEBUG****///error_log(">>>>>>>>>>>>>>>".$query1);
						mysql_query($query1);
						print "<br><br>Total count of resolved pointers:".count($inserts);
					}
				}

				print "<br><br><br><h3>Transfer completed - <a href=../../index.php?db=" . HEURIST_DBNAME .
				" title='Return to the main search page of the current database'><b>return to main page</b></a></h3>";

			} // function doTransfer

			/**
			* callback from saveRecord
			*
			* @param mixed $message
			*/
			function jsonError($message) {

				//mysql_query("rollback");
				error_log("ERROR :".$message);

				//$rep_issues = $rep_issues."<br/>Error save record for file:".$currfile.". ".$message;
			}


			/**
			* copy file from another h3 instance and register it
			*
			* @param mixed $src_fileid - file id in source db
			* @return int - file id in destionation db, null - if copy and registration are failed
			*/
			function copyRemoteFile($src_fileid){

				global $sourcedbname, $dbPrefix, $db_prefix;
				$sourcedb = $db_prefix.$sourcedbname;

				$_src_HEURIST_UPLOAD_DIR =  HEURIST_UPLOAD_ROOT.$sourcedbname.'/';


				$res = mysql_query("select * from $sourcedb.`recUploadedFiles` where ulf_ID=".$src_fileid);
				if (mysql_num_rows($res) != 1) {
					print "<div  style='color:red;'>no entry for file id#".$src_fileid."</div>";
					return null; // nothing returned if parameter does not match one and only one row
				}

				$file = mysql_fetch_assoc($res);

				$need_copy = false;
				$externalFile = false;

				if ($file['ulf_FileName']) {
					$filename = $file['ulf_FilePath'].$file['ulf_FileName']; // post 18/11/11 proper file path and name
					$need_copy = ($file['ulf_FilePath'] == $_src_HEURIST_UPLOAD_DIR);
				} else if ($file['ulf_ExternalFileReference']) {
					$filename = $file['ulf_ExternalFileReference']; // post 18/11/11 proper file path and name
					$need_copy = false;
					$externalFile = true;
				} else {
					$filename = $_src_HEURIST_UPLOAD_DIR . $file['ulf_ID']; // pre 18/11/11 - bare numbers as names, just use file ID
					$need_copy = true;
				}

				if(!$externalFile && !file_exists($filename)){
					//check if this file is remote
					print "<div  style='color:red;'>File $filename not found. Can't register file</div>";
					ob_flush();flush(); // flush to screen
					return null;
				}

				if(false && $externalFile){
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	//return the output as a string from curl_exec
					curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
					curl_setopt($ch, CURLOPT_NOBODY, 0);
					curl_setopt($ch, CURLOPT_HEADER, 0);	//don't include header in output
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
					curl_setopt($ch, CURLOPT_TIMEOUT, 10);	// timeout after ten seconds
					curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections
					if (defined("HEURIST_HTTP_PROXY")) {
						curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
					}

					curl_setopt($ch, CURLOPT_URL, $filename);
					$data = curl_exec($ch);

					$error = curl_error($ch);
					if ($error) {
						$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
						error_log("$error ($code)" . " url = ". $remote_url);
						print "<div  style='color:red;'>Error $filename - $error. Can't register URL</div>";
						ob_flush();flush(); // flush to screen
						return null;
					}
				}

				if($need_copy){
					$newfilename = HEURIST_UPLOAD_DIR.$file['ulf_OrigFileName'];
					//if file in source upload dirtectiry copy it to destionation upload directory
					if(!copy($filename, $newfilename)){
						print "<div  style='color:red;'>Can't copy file $fielname to ".HEURIST_UPLOAD_DIR."</div>";
						ob_flush();flush(); // flush to screen
						return null;
					}
					$filename = $newfilename;
				}

				//returns new file id in dest database
				if ($externalFile) {
					$jsonData = json_encode( array('remoteURL' => $filename,
													'ext' => $file['ulf_MimeExt'],
													'params' => $file['ulf_Parameters'] ? $file['ulf_Parameters']:null
												));
					$ret = register_external($jsonData, null, false);
				}else {
					$ret = register_file($filename, null, false);
				}
				if(intval($ret)>0){
					return $ret;
				}else{
					print "<div  style='color:red;'>Can't register file ".$filename."</div>";
					ob_flush();flush(); // flush to screen
					return null;
				}
			}

			/**
			* copy file from another H2 instance and register it
			*
			* @param mixed $src_fileid - file id in source db
			* @return int - file id in destionation db, null - if copy and registration are failed
			*/
			function copyRemoteFileH2($src_fileid){

				global $sourcedbname, $dbPrefix, $db_prefix;
				$sourcedb = $db_prefix.$sourcedbname;

				$HEURIST_UPLOAD_ROOT_OLD =	HEURIST_DOCUMENT_ROOT."/uploaded-heurist-files/";
				$_src_HEURIST_UPLOAD_DIR =  $HEURIST_UPLOAD_ROOT_OLD.$sourcedbname.'/';


				$res = mysql_query("select * from `$sourcedb`.`files` where `file_id`=".$src_fileid);
				if (mysql_num_rows($res) != 1) {
					print "<div  style='color:red;'>no entry for file id#".$src_fileid."</div>";
					return null; // nothing returned if parameter does not match one and only one row
				}

				$file = mysql_fetch_assoc($res);

				$filename = $_src_HEURIST_UPLOAD_DIR ."/". $file['file_id'];

				if(!file_exists($filename)){
					//check if this file is remote
					print "<div  style='color:red;'>File $fielname not found. Can't register it</div>";
					ob_flush();flush(); // flush to screen
					return null;
				}

				$newfilename = HEURIST_UPLOAD_DIR.$file['file_orig_name'];
				//if file in source upload dirtectiry copy it to destionation upload directory
				if(!copy($filename, $newfilename)){
					print "<div  style='color:red;'>Can't copy file $fielname to ".HEURIST_UPLOAD_DIR."</div>";
					ob_flush();flush(); // flush to screen
					return null;
				}
				$filename = $newfilename;

				//returns new file id in dest database
				$ret = register_file($filename, null, false);
				if(intval($ret)>0){
					return $ret;
				}else{
					print "<div  style='color:red;'>Can't register file ".$filename."</div>";
					ob_flush();flush(); // flush to screen
					return null;
				}
			}

			/**
			* put your comment there...
			*
			* @param mixed $src_termid
			*/
			function getDestinationTerm($src_termid){

				if(!array_key_exists('cbt'.$src_termid, $_REQUEST)) {
					return null;
				}
				$key = $_REQUEST['cbt'.$src_termid];
				if(intval($key)<1) {
					//mapping for this term is not specified
					return null;
				}

				return $key;
			}
		?>
	</body>
</html>
