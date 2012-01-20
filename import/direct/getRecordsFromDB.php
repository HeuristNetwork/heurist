<?php

/* getRecordsFromDB.php - gets all records in a specified database (later, a selection) and write directly to current DB
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

	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
	print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
	return;
	}
?>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<title>Heurist - Direct import</title>
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
	print "This script reads records from a source database, maps the record type, field type and terms codes to new values, ";
    print "and writes the records into the current logged-in database. It also transfers uploaded file records. ";
    print "The current database can already contain data, new records are appended and IDs adjsuted for records and files.<br>";
    print "<br>";
     print "Make sure the target records and field types are compatible. <b>";
    print "If you get the codes wrong, you will get a complete dog's breakfast in your target database ...</b><p>\n";

    print "<p>This function is under development at 23/11/11 - please check back shortly ...</p>";

	$sourcedbname = NULL;

// ----FORM 1 - SELECT THE SOURCE DATABASE --------------------------------------------------------------------------------

	if(!array_key_exists('mode', $_REQUEST) || !array_key_exists('sourcedbname', $_REQUEST)){

		print "<form name='selectdb' action='getRecordsFromDB.php' method='get'>";
		//  We have to use 'get', 'post' fails to transfer target database to step 2
		print "<input name='mode' value='2' type='hidden'>"; // calls the form to select mappings, step 2
		print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
		// print "Enter source database name (prefix added automatically): <input type='text' name='sourcedbname' />";
		print "Choose source database: <select id='db' name='sourcedbname'>";
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
		print "<input type='submit' value='Continue' />";
		exit;
	}

// ----FORM 2 - MAPPINGS FORM ----------------------------------------------------------------------------------------

	$sourcedbname = $_REQUEST['sourcedbname'];

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
    	transfer();
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

		global $sourcedbname, $dbPrefix;

		$sourcedb = $dbPrefix.$sourcedbname;

	    print "<br>\n";
	    print "Source database: <b>$sourcedb</b> <br>\n";

	    $res=mysql_query("select * from $sourcedb.sysIdentification");
	    if (!$res) {
			die ("<p>Unable to open source database <b>$sourcedb</b>. Make sure you have included prefix");
	    }


		print "<form name='mappings' action='getRecordsFromDB.php' method='post'>";
		print "<input id='mode' name='mode' value='5' type='hidden'>"; // calls the transfer function
		print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
		print "<input name='sourcedbname' value='$sourcedbname' type='hidden'>";

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


	     $query1 = "SELECT DISTINCT rec_RecTypeID,rty_Name FROM $sourcedb.Records,$sourcedb.defRecTypes where rec_RecTypeID=rty_ID";
	     $res1 = mysql_query($query1);
	     if (mysql_num_rows($res1) == 0) {
			 die ("<p><b>Sorry, there are no data records in this database, or database is bad format</b>");
	     }
	     print "<h3>Record type mappings</h3>Code on left, in <b>$sourcedb</b>, maps to record type in <b>$dbPrefix" . HEURIST_DBNAME."</b><p>";// . "<p>";
	     print "<table>";
	     while ($row1 = mysql_fetch_assoc($res1)) {
     		$rt=$row1['rec_RecTypeID'];

     		 $selopts = $seloptions;
			 $selectedId = getPresetId($config,"cbr".$rt);
			 if(!$selectedId){	//find the closest name
     		 	$selectedId = findClosestName($row1['rty_Name'], $entnames);
			 }
     		 if($selectedId){
     		 	 $repl = "value='".$selectedId."'";
     		 	 $selopts = str_replace($repl, $repl." selected='selected' ", $selopts);
			 }

       		print "<tr><td>[ $rt ] $row1[rty_Name] </td>".
       				"<td><select id='cbr$rt' name='cbr$rt' class='rectypes'><option id='or0' value='0'></option>".$selopts."</select></td></tr>\n";
		 } // loop through record types
		 print "</table>";


 		 // --------------------------------------------------------------------------------------------------------------------
		 // Get the field type mapping, by default assume that the code is unchanged so select the equivalent detail type if available
		 //create the string for combobox
		 $entnames = getAllDetailTypeStructures(); //in current database
		 $entnames = $entnames['names'];
		 $seloptions = createOptions("od", $entnames);

		 print "<h3>Field type mappings</h3>Code on left, in <b>$sourcedb</b>, maps to field type in <b>$dbPrefix" . HEURIST_DBNAME."</b><p>";// . "<p>";
	     $query1 = "SELECT DISTINCT dtl_DetailTypeID,dty_Name,dty_Type FROM $sourcedb.recDetails,$sourcedb.defDetailTypes ".
	     		"where dtl_DetailTypeID=dty_ID";
	     $res1 = mysql_query($query1);
	     print "<table>";
	     while ($row1 = mysql_fetch_assoc($res1)) {
     		 $ft=$row1['dtl_DetailTypeID'];

     		 $selopts = $seloptions;
     		 //find the closest name
			 $selectedId = getPresetId($config,"cbd".$ft);
			 if(!$selectedId){	//find the closest name
     		 	$selectedId = findClosestName($row1['dty_Name'], $entnames);
			 }
     		 if($selectedId){
     		 	 $repl = "value='".$selectedId."'";
     		 	 $selopts = str_replace($repl, $repl." selected='selected' ", $selopts);
			 }

			 print "<tr><td>[ $ft  - $row1[dty_Type] ] $row1[dty_Name] </td>".
			 		"<td><select id='cbd$ft' name='cbd$ft' class='detailTypes'><option id='od0' value='0'></option>".
			 		$selopts."</select></td></tr>\n";
		 } // loop through field types
         print "</table>";

 		 // --------------------------------------------------------------------------------------------------------------------

		 $entnames = getTerms(); //in current database
		 $entnames = $entnames['termsByDomainLookup']['enum'];
		 foreach ($entnames as $id => $name) {
		 	 $entnames[$id] = $name[0];
		 }
		 $seloptions = createOptions("ot", $entnames);


		 print "<h3>Term mappings</h3>Code on left, in <b>$sourcedb</b>, maps to field type in <b>$dbPrefix" . HEURIST_DBNAME."</b><p>";// . "<p>";
	     // Get the term mapping, by default assume that the code is unchanged so select the equivalent term if available
	     $query1 = "SELECT DISTINCT dtl_Value,trm_ID,trm_Label FROM $sourcedb.recDetails,$sourcedb.defTerms ".
	     			"where (dtl_Value=trm_ID) AND (dtl_DetailTypeID in (select dty_ID from $sourcedb.defDetailTypes ".
	     			"where (dty_Type='enum') or (dty_type='relationtype')))";
	     $res1 = mysql_query($query1);
	     print "<table>";
	     while ($row1 = mysql_fetch_assoc($res1)) {
	     	$tt=$row1['trm_ID'];

     		 $selopts = $seloptions;
     		 //find the closest name
			 $selectedId = getPresetId($config,"cbt".$tt);
			 if(!$selectedId){	//find the closest name
     		 	$selectedId = findClosestName($row1['trm_Label'], $entnames);
			 }
     		 if($selectedId){
     		 	 $repl = "value='".$selectedId."'";
     		 	 $selopts = str_replace($repl, $repl." selected='selected' ", $selopts);
			 }

       		print "<tr><td>[ $tt ] $row1[trm_Label] </td>".
       				"<td><select id='cbt$tt' name='cbt$tt' class='terms'><option id='ot0' value='0'></option>".
       				$selopts."</select></td></tr>\n";
		 } // loop through terms
         print "</table>";
		 print "</form>";

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


	function transfer() {
		global $sourcedbname, $dbPrefix;

		$sourcedb = $dbPrefix.$sourcedbname;

	    echo "<p>Now copying data from <b>$sourcedb</b> to <b>". HEURIST_DBNAME. "</b><p>Processing: ";

	    // Loop through types for all records in the database (actual records, not defined types)
	    $query1 = "SELECT DISTINCT (`rec_RecTypeID`) FROM $sourcedb.Records";
	    $res1 = mysql_query($query1);
		if(!$res1) {
				print "<br>Bad query for record type loop $res1 <br>";
				print "$query1<br>";
				die ("<p>Sorry ...");
			}

	    // loop through the set of rectypes actually in the records in the database
	    while ($row1 = mysql_fetch_assoc($res1)) {
	        $rt=$row1['rec_RecTypeID'];
	        print "<br>Record type: $rt  &nbsp;&nbsp;&nbsp;"; // tell user somethign is happening
			ob_flush();flush(); // flush to screen
    		include 'recFields.inc'; // sets value of $flds2 to the fields we want from the records table
		    $query2 = "select $flds2 from $sourcedb.Records Where $sourcedb.Records.rec_RecTypeID=$rt";
	        $res2 = mysql_query($query2);
			if(!$res2) {
				print "<br>Bad query for records loop for source record type $rt";
				print "Query: $query2";
				die ("<p>Sorry ...");
			}

			// RECTYPE
			// loop through the records for this rectype
			print "record ids: ";
			 while ($row2 = mysql_fetch_assoc($res2)) {
				$rid=$row2['rec_ID'];
				print " $rid "; // print out record numbers
				ob_flush();flush();
				$rec_RecTypeID = $_REQUEST['cbr'.$row2['rec_RecTypeID']]; // sets the mapped rectype ID
				$rec_URL=mysql_real_escape_string($row2['rec_URL']);
				$rec_Title=mysql_real_escape_string($row2['rec_Title']);
				$rec_Hash=mysql_real_escape_string($row2['rec_Hash']);
				$rec_ScratchPad=mysql_real_escape_string($row2['rec_ScratchPad']); // not included in insert, generates ""
				$rec_Added = $row2['rec_Added'];
				$rec_URLExtensionForMimeType=$row2['rec_URLExtensionForMimeType'];
				$rec_Modified=$row2['rec_Modified'];
 				$rec_AddedByImport=1;
 				$rec_AddedByUGrpID=2; // TODO: SHOULD BE CURRENT USER ID
 				$rec_OwnerUGrpID=1; // TODO: SHOULD BE CURRENT USER ID OR SELECTABLE
 				$rec_NonOwnerVisibility='viewable'; // TODO: SHOULD BE A CHOICE

 				// RECORD
 				// write the new record into the target database
 				$query2target = "INSERT INTO Records (rec_RecTypeID,rec_URL,rec_Added,rec_Title,rec_AddedByUGrpID,rec_AddedByImport,".
 					"rec_OwnerUGrpID,rec_NonOwnerVisibility,rec_URLExtensionForMimeType,rec_Hash) " .
					"VALUES ('$rec_RecTypeID','$rec_URL','$rec_Added','$rec_Title','$rec_AddedByUGrpID','$rec_AddedByImport',".
					"'$rec_OwnerUGrpID','$rec_NonOwnerVisibility','$rec_URLExtensionForMimeType','$rec_Hash')";
		        $res2target = mysql_query($query2target);

				$ridNew =  mysql_insert_id(); // the ID of the new record inserted (if successful)

				if (mysql_error()) {
					print "<p>Inserting record type ID: &nbsp;&nbsp;&nbsp; Source =".$row2['rec_RecTypeID']."  &nbsp;&nbsp;&nbsp;&nbsp; Target =".$rec_RecTypeID;
 					print "<p>MySQL error on record insert: ".mysql_error();
					print "<p><i>$query2target</i>";
 					die ("<p>Sorry ...");
				}

 			    include 'dtlFields.inc'; // sets value of $flds3 to the fields we want from the details table
				$query3 = "select $flds3 from $sourcedb.recDetails Where $sourcedb.recDetails.dtl_RecID=$rid";
        		$res3 = mysql_query($query3);
        		// todo: check query was successful
				if(!$res3) {
					print "<br>Bad select of detail fields for record type $rt  record id = $rid  new record id = $ridNew";
					print "Query3: $query3";
					die ("<p>Sorry ...");
				}

        		// DETAIL
        		// loop through the record details for this record
        		while ($row3 = mysql_fetch_assoc($res3)) {
					$dtl_RecID=$row3['dtl_RecID']; // should be same as original $rid, but $ridNew now updated to target
					$dtl_DetailTypeID = $_REQUEST['cbd'.$row3['dtl_DetailTypeID']]; // sets the mapped detailtype ID
					$dtl_Value=mysql_real_escape_string($row3['dtl_Value']);
					//TODO: IF THE DETAIL TYPE IS AN ENUM, NEED TO MAP THE VALUE TO THE NEW VALUES
					$dtl_Geo=mysql_real_escape_string($row3['dtl_Geo']);
					$dtl_ValShortened=mysql_real_escape_string($row3['dtl_ValShortened']);
					$dtl_UploadedFileID=$row3['dtl_UploadedFileID'];

					// FILE
					// transfer uploaded file record, appending to table in target database and updating ID
					$newFileID = 'NULL'; // for details which aren't a file
					if ($dtl_UploadedFileID>0) {
						$queryfile = "INSERT INTO recUploadedFiles
							   (ulf_OrigFileName,ulf_UploaderUGrpID,ulf_Added,ulf_Modified,
							   ulf_ObfuscatedFileID,ulf_ExternalFileReference,ulf_PreferredSource,ulf_Thumbnail,ulf_Description,
							   ulf_MimeExt,ulf_AddedByImport,ulf_FileSizeKB)
						    select
							   ulf_OrigFileName,ulf_UploaderUGrpID,ulf_Added,ulf_Modified,
							   ulf_ObfuscatedFileID,ulf_ExternalFileReference,ulf_PreferredSource,ulf_Thumbnail,ulf_Description,
							   ulf_MimeExt,ulf_AddedByImport,ulf_FileSizeKB
							from $sourcedb.recUploadedFiles
							where $sourcedb.recUploadedFiles.ulf_ID=$dtl_UploadedFileID";
						$resfile = mysql_query($queryfile);
						if(!$resfile) {
							print "<br>Bad file record transfer for file ID = $dtl_UploadedFileID";
							print "<br>Query: $queryfile";
						} else {
							$newFileID = mysql_insert_id(); // the ID of the new file record inserted
							print "<br>Old file ID = $dtl_UploadedFileID &nbsp;&nbsp;&nbsp;&nbsp; New file ID = $newFileID";
						}
						//TODO; Need to copy the actual file over, changing its name to the new ID
					}


 				// write the new detail into the target database
 				/*
 				print ".";
 				$query3target = "INSERT INTO ".
 				"recDetails(dtl_RecID,dtl_DetailTypeID,dtl_Value,dtl_Geo,dtl_ValShortened,dtl_UploadedFileID) ".
				"VALUES ('$ridNew','$dtl_DetailTypeID','$dtl_Value','$dtl_Geo','$dtl_ValShortened',$newFileID) ";
		        $res3target = mysql_query($query3target);
				if(!$res3target) {
					ob_flush();flush();
					print "<p>Bad detail insert: record type $rt  source record id = $rid  new record id = $ridNew";
					print "<p>Query: $query3target";
					print "<p>MySQL error: <i>".mysql_error()."</i>";
					die ("<p>Sorry ...");
				}
				*/



				}; // end of loop for details

			}; // end of loop for records
		}; // end of loop for record types
		print "<p><h3>Transfer completed</h3>Please set an image directory and copy image files across to the new location";
		print "<br>Note: this will only work if there were no images in the target database. Otherwise, consult Heurist team";
	} // function transfer

	// --------------------------------------------------------------------------------------


?>


</body>
</html>
