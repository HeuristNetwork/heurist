<?php

	/* compareStructure.php - gets all structure definitions (terms, fields, rectypes) in a specified database and compare to current DB
	*
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
		header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
		print "<html>";
		//print "<header><style type='text/css'>.rtheader {width:300px; background-color: #d8da3d;}</style></header>";
		print "<body><p>You must be an administrator to import records from a source database</p><p><a href=".HEURIST_BASE_URL.">Return to Heurist</a></p></body></html>";
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

			print "<h2>Heurist Structure comparison</h2>";
			print "<h2>FOR  ADVANCED USERS ONLY</h2>";
			print "This script compares record structures from a source database of H3 format to structures in current database ";

			$sourcedbname = NULL;
			$useOriginalID = false;

			$is_h2 = array_key_exists('h2', $_REQUEST) && ($_REQUEST['h2']==1);

			$db_prefix = $is_h2?"heuristdb-" :$dbPrefix;

			if(array_key_exists('sourcedbname', $_REQUEST)){
				$sourcedbname = $_REQUEST['sourcedbname'];
			}
			if(array_key_exists('useoid', $_REQUEST)){
				$useOriginalID = ($_REQUEST['useoid']=='1');
			}

			// ----FORM 1 - SELECT THE SOURCE DATABASE --------------------------------------------------------------------------------

/* SWITCH TO H2 database form

				print "<form name='selectdbtype' action='compareStructure.php' method='get'>";
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				if(!$is_h2){
					print "<input name='h2' value='1' type='hidden'>";
				}
				print "<input type='submit' value='Switch to H".($is_h2?"3":"2")." databases' /><br/>";
				print "</form>";
*/
				print "<form name='selectdb' action='compareStructure.php' method='get'>";
				//  We have to use 'get', 'post' fails to transfer target database to step 2
				print "<input name='mode' value='2' type='hidden'>"; // calls the form to select mappings, step 2
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				print "<input name='h2' value='".($is_h2?1:0)."' type='hidden'>";
				// print "Enter source database name (prefix added automatically): <input type='text' name='sourcedbname' />";
				print "Choose source database: <select id='db' name='sourcedbname'>";
				$list = mysql__getdatabases();
				foreach ($list as $name) {
						print "<option value='$name' ".($sourcedbname==$name?"selected='selected'":"").">$name</option>";
				}
				print "</select>";
				print "<input type='checkbox' name='useoid' value='1' ".($useOriginalID?"checked='checked'":"")."/>Use Concept ID&nbsp;&nbsp;";
				print "<input type='submit' value='Continue' />";
				print "</form>";

			if($sourcedbname==null){
				exit;
			}

			// ----FORM 2 - MAPPINGS FORM ----------------------------------------------------------------------------------------

			$sourcedbname = $_REQUEST['sourcedbname'];
			createMappingForm(null);

			// ---- Create mapping form -----------------------------------------------------------------

			function getPresetId($config, $id){
				if($config && array_key_exists($id, $config)){
					return $config[$id];
				}else{
					return null;
				}
			}

			/**
			* put your comment there...
			*
			*
			* @param mixed $config
			*/
			function createMappingForm($config){

				global $sourcedbname, $db_prefix, $dbPrefix, $is_h2, $useOriginalID;

				$sourcedb = $db_prefix.$sourcedbname;

				print "<br>\n";
				//print "Source database: <b>$sourcedb</b> <br>\n";

				if($is_h2){
					$res=mysql_query("select * from `$sourcedb`.Users");
				}else{
					$res=mysql_query("select * from $sourcedb.sysIdentification");
				}
				if (!$res) {
					die ("<p>Unable to open source database <b>$sourcedb</b>. Make sure you have included prefix");
				}


				print "<form name='mappings' action='compareStructure.php' method='post'>";
				print "<input id='mode' name='mode' value='5' type='hidden'>"; // calls the transfer function
				print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
				print "<input name='h2' value='".($is_h2?1:0)."' type='hidden'>";
				//print "<input name='sourcedbname' value='$sourcedbname' type='hidden'>";
				//print "<input name='reportlevel' value='1' type='checkbox' checked='checked'>Report level: show errors only<br>";
				//print "Check the code mappings below, then click  <input type='button' value='Import data' onclick='{document.getElementById(\"mode\").value=5; document.forms[\"mappings\"].submit();}'>\n";
				// alert(document.getElementById(\"mode\").value);

/*
				print "<input type='button' value='Save settings' onclick='{document.getElementById(\"mode\").value=3; document.forms[\"mappings\"].submit();}'>";

				$filename = HEURIST_UPLOAD_DIR."settings/importfrom_".$sourcedbname.".cfg";

				if(file_exists($filename)){
					print "<input type='submit' value='Load settings' onclick='{document.getElementById(\"mode\").value=4; document.forms[\"mappings\"].submit();}'>\n";
				}
*/
				print "<p><hr>\n";

				// --------------------------------------------------------------------------------------------------------------------
				// Get the record type mapping, by default assume that the code is unchanged so select the equivalent record type if available
				$d_rectypes = getAllRectypeStructures(); //in current database
				$d_dettypes = getAllDetailTypeStructures();
				$d_rtnames = $d_rectypes['names'];


				mysql_connection_db_overwrite($sourcedb);
				$s_rectypes = getAllRectypeStructures(false);
				$s_dettypes = getAllDetailTypeStructures(false);
				$s_rtnames = $s_rectypes['names'];

				print "<table border='1' width='900'><tr><td width='300'>".$sourcedbname."</td><td colspan='2'>".HEURIST_DBNAME."</td></tr>";

				$fi_type = $s_dettypes['typedefs']['fieldNamesToIndex']['dty_Type'];
				$fi_name = $s_dettypes['typedefs']['fieldNamesToIndex']['dty_Name'];

				$fi_rt_concept = $s_rectypes['typedefs']['commonNamesToIndex']['rty_ConceptID'];
				$fi_dt_concept = $s_dettypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];

				foreach ($s_rtnames as $s_id => $s_name) {

					$s_conceptid = $s_rectypes['typedefs'][$s_id]['commonFields'][$fi_rt_concept];

					$dest_id = null;
					$dest_name = null;
					//find record type in destination
					foreach ($d_rtnames as $d_id => $d_name) {


						if( ($useOriginalID && $d_rectypes['typedefs'][$d_id]['commonFields'][$fi_rt_concept] == $s_conceptid) ||
							(!$useOriginalID && $d_name==$s_name)
							)
						{
							//print "[".$d_id."]  ".$d_name."<br/>";
							//print structure in the same order as source rectype
							$dest_id = $d_id;
							$dest_name = $d_name;
							break;
						}
					}


					//Header  <tr><td colspan='2'><table border='1' width='100%'>
					print "<tr style='background-color:#AAAAAA;'><td width='250'><b>[".$s_id."]  ".$s_name."</b>(".$s_conceptid.")</td><td width='250'>";
					if($dest_id==null){
						print "...not found";
					}else{
						print "<b>[".$dest_id."]  ".$dest_name."</b>";
					}
					 print "</td><td width='400'>&nbsp;</td></tr>";



					//list of field types
					$s_flds = $s_rectypes['typedefs'][$s_id]['dtFields'];
					foreach ($s_flds as $sft_id => $sft_desc) {

						$fld_in_dest_rectype = "&nbsp;";
						$fld_in_dest_all = "&nbsp;";

						$s_conceptid = $s_dettypes['typedefs'][$sft_id]['commonFields'][$fi_dt_concept];
						$s_fitype = $s_dettypes['typedefs'][$sft_id]['commonFields'][$fi_type];

						//find in destination record type
						if($dest_id!=null){
							$d_flds = $d_rectypes['typedefs'][$dest_id]['dtFields'];
							foreach ($d_flds as $dft_id => $dft_desc) {
								//compare by original field name and by type
								if( ($useOriginalID && $d_dettypes['typedefs'][$dft_id]['commonFields'][$fi_dt_concept] == $s_conceptid) ||
									(!$useOriginalID &&
										$s_dettypes['names'][$sft_id]==$d_dettypes['names'][$dft_id] &&
										$s_fitype == $d_dettypes['typedefs'][$dft_id]['commonFields'][$fi_type])
									)
								{
										$fld_in_dest_rectype = "[".$dft_id."] ".$dft_desc[0];
										break;
								}
							}
						}

						//if not found try to find in entire list of field types
						if($fld_in_dest_rectype=="&nbsp;"){
							$d_flds = $d_dettypes['typedefs'];
							foreach ($d_flds as $dft_id => $dft_def) {

								//compare by original field name and by type
								if( ($useOriginalID && $dft_def['commonFields'][$fi_dt_concept] == $s_conceptid) ||
									(!$useOriginalID &&
											$s_dettypes['names'][$sft_id]==$dft_def['commonFields'][$fi_name] &&
											$s_fitype == $dft_def['commonFields'][$fi_type])
									)
								{
										$fld_in_dest_all = "[".$dft_id."] ".$dft_def['commonFields'][$fi_name]."  (".$s_fitype.")";
										break;
								}
							}
						}



						print "<tr><td>[".$sft_id."] ".$sft_desc[0]."&nbsp;(".$s_conceptid.")</td><td>".$fld_in_dest_rectype."</td><td>".$fld_in_dest_all."</td></tr>";
					}

					///print "</table></td><tr>";
				}


				print "</table>";

				return;


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

			// ---- TRANSFER AND OTHER FUNCTIONS -----------------------------------------------------------------

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
