<?php

	/* getDBStructureAsforms.php - returns database definitions (rectypes, details etc.)
	* as form data ready for use in mobile app - primarily intended for NeCTAR FAIMS project
	*
	* @Author Stephen White
	* @author Artem Osmakov
	* @copyright (C) 2012 AeResearch University of Sydney.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @param includeUgrps=1 will output user and group information in addition to definitions
	* @param approvedDefsOnly=1 will only output Reserved and Approved definitions
	* @todo
	*
	*
	-->*/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

	// Deals with all the database connections stuff
	//define("DT_DRAWING","2-59");
	mysql_connection_db_select(DATABASE);
	if(mysql_error()) {
		die("Could not get database structure from given database source, MySQL error - unable to connect to database.");
	}
	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to export structure as forms</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}
?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Export record type structures to forms</title>
		<link rel="stylesheet" type="text/css" href="../../common/css/global.css">
	</head>
	<?php
		if(array_key_exists("rectypes", $_REQUEST)){
			$rectypes = $_REQUEST['rectypes'];
		}else{
			$rectypes = "";
		}

		if(!array_key_exists("mode", $_REQUEST) || $_REQUEST['mode']!="export"){
		?>
		<body onload="{_recreateRecTypesList('<?=$rectypes?>', true)}" style="padding: 10px;">
			<script src="../../common/php/loadCommonInfo.php"></script>
			<script type="text/javascript" src="../../common/js/utilsUI.js"></script>
			<script type="text/javascript">

				var Hul = top.HEURIST.util;

				function _recreateRecTypesList(value, isFirst) {

					var txt = "";

					if(value) {
						var arr = value.split(","),
						ind, dtName;
						for (ind in arr) {
							var ind2 = Number(arr[Number(ind)]);
							if(!isNaN(ind2)){
								dtName = top.HEURIST.rectypes.names[ind2];
								if(!txt) {
									txt = dtName;
								}else{
									txt += ", " + dtName;
								}
							}
						} //for
					}

					document.getElementById("form1").style.display = Hul.isempty(txt)?"none":"block";

					if(isFirst && Hul.isempty(txt)){
						_onSelectRectype();
						document.getElementById("rectypesList").innerHTML = "";
					}else{
						document.getElementById("rectypesList").innerHTML = "<h3>Record types to export:</h3>&nbsp;"+txt;
					}

				}

				function _onSelectRectype()
				{
					var URL = top.HEURIST.basePath + "admin/structure/selectRectype.html?type=resource&ids="+document.getElementById("rectypes").value+"&db=<?=HEURIST_DBNAME?>";

					Hul.popupURL(top, URL, {
							"close-on-blur": false,
							"no-resize": true,
							height: 480,
							width: 440,
							callback: function(recordTypesSelected) {
								if(!Hul.isnull(recordTypesSelected)) {
									document.getElementById("rectypes").value = recordTypesSelected;

									_recreateRecTypesList(recordTypesSelected, false);
								}
							}
					});
				}
			</script>

			<div style="" id="rectypesList"></div>
			<input type="button" value="Select rectypes" onclick="_onSelectRectype()">
			<br/>
			<form id="form1" action="getDBStructureAsXForms.php" method="post">
				<input name="db" value="<?=HEURIST_DBNAME?>" type="hidden">
				<input id="rectypes" name="rectypes" value="<?=$rectypes?>" type="hidden">
				<input name="mode" value="export" type="hidden">
				<input id="btnStart" type="submit" value="Start export">
			</form>

		</body>
	</html>
	<?php
	}else{
	?>
	<body style="padding: 10px;">
		Export in progress....

		<?php

			$folder = HEURIST_UPLOAD_DIR."xforms/";

			if(!file_exists($folder)){
				if (!mkdir($folder, 0777, true)) {
					print '<font color="red">Failed to create folder for forms!</font>';
					return;
				}
			}

			$a_rectypes = explode(",",$rectypes);
			$formsList = "<?xml version=\"1.0\"?>\n".
						"<forms>\n";
			$xformsList = "<?xml version=\"1.0\"?>\n".
						"<xforms>\n";

			foreach ($a_rectypes as $rt_id) {
				if($rt_id){
					print "<div>".createform($rt_id)."</div> ";
				}
			}

			$formsList .= "</forms>\n";
			$xformsList .= "</xforms>\n";
			file_put_contents($folder."formList", $formsList);
			file_put_contents($folder."xformList", $xformsList);
//			chgrp($folder."formList","acl");
			print "<div>Wrote $folder"."xformList </div>\n";
		?>
		<br/><br/>
		<form id="form2" action="getDBStructureAsXForms.php" method="get">
			<input name="db" value="<?=HEURIST_DBNAME?>" type="hidden">
			<input id="rectypes" name="rectypes" value="<?=$rectypes?>" type="hidden">
			<input type="submit" value="Start over">
		</form>
	</body>
	</html>
	<?php
	}

	return;

	/*
	http://opendatakit.org/help/form-design/xlsform/

	ODK Collect supports a number of simple question types:
	text	Text input.
	integer	Integer (ie, whole number) input.
	decimal	Decimal input.
	selection_one [options]	Multiple choice question; only one answer can be selected.
	select_multiple [options]	Multiple choice question; multiple answers can be selected.
	note	Display a note on the screen, takes no input.
	geopoint	Collect GPS coordinates. LAT LONG ALT ACCUR
	image	Take a photograph.
	barcode	Scan a barcode, requires the barcode scanner app is installed.
	date	Date input.
	datetime	Accepts a date and a time input.
	audio	Take an audio recording.
	video	Take a video recording.
	calculate Perform a calculation; see “calculates” below.
	*/
	function getFieldType($ourtype){

		switch ($ourtype)
		{
			case "blocktext":
				return "string";
			case "date":
			case "year":
				return "date";
			case "float":
				return "decimal";
			case "integer":
				return "int";
			case "freetext":
				return "string";
			case "geo":
				return "geopoint";
			case "resource"://temporary in form lookup -- should be external call to lookup/form launch tool  lookups are per rt-dt-resource (constrained by query and rt)
			case "relationtype":
			case "enum":
				return "select1";
			case "file":
				return "binary";
			case "separator":
				return "groupbreak";
			default:
				return null;
		}

	}

	//
	// Creates form, save it into FILESTORE/forms folder and add it to the manifest lists
	//
	function createform($rt_id){

		global $folder, $formsList, $xformsList;


		try{

			list($form,$rtName,$rtConceptID,$rtDescription,$report) = buildform($rt_id);

			if ($form){
				$filename = preg_replace('/[^a-zA-Z0-9-_\.]/','', $rtName);//todo this is not international, need to strip only illegal filesys characters and perhaps trim spaces to single space
				$fullfilename = $folder.$filename.".xml";

				file_put_contents($fullfilename, $form);
	//			chgrp($fullfilename,"acl");
				//TODO: update formlist for this form.
				$report = $report."$rtName Form Saved ok ($fullfilename)<br/>";
				$xformEntry = "<xform>\n".
								"<downloadUrl>http://heuristscholar.org/hayes/xforms/$filename.xml</downloadUrl>\n".
								"<formID>$rtConceptID</formID>\n".
								"<name>$rtName Record Form</name>\n".
								"<descriptionText>$rtName Record as defined in the \"".HEURIST_DBNAME."\" database described as \"$rtDescription\" </descriptionText>\n".
								"<majorMinorVersion>".date("Ymd")."</majorMinorVersion>\n".
								"<version>".date("Ymd")."</version>\n".
								"<hash>md5:".md5_file($fullfilename)."</hash>\n".
							"</xform>\n";
				$formEntry = "<form url=\"http://heuristscholar.org/hayes/xforms/$filename.xml\">$rtName</form>\n";
			}
		} catch (Exception $e) {
			$report = $report.'Exception '.($e->getMessage());
			$formEntry = $xformEntry = "";
		}
		$formsList .= ($formEntry?$formEntry:"");
		$xformsList .= ($xformEntry?$xformEntry:"");
		return "<h2>$rtName</h2>".$report."<br/>";
	}

	function buildform($rt_id){
		// mappings and lookups - static so we only retrieve once per service call
		static $dettypes, $di, $rectypes, $ri, $rid, $terms, $ti, $termLookup, $relnLookup;
		if (!$dettypes || !$di){
			$dettypes = getAllDetailTypeStructures();
			$dettypes = $dettypes['typedefs'];
			$di = $dettypes['fieldNamesToIndex'];
		}
		if (!$rectypes || !$ri || !$rid){
			$rectypes = getAllRectypeStructures();
			$ri = $rectypes['typedefs']['commonNamesToIndex'];
			$rid = $rectypes['typedefs']['dtFieldNamesToIndex'];
		}
		if (!$terms || !$ti || !$termLookup || !$relnLookup){
			$terms = getTerms();
			$ti = $terms['fieldNamesToIndex'];
			$termLookup = $terms['termsByDomainLookup']['enum'];
			$relnLookup = $terms['termsByDomainLookup']['relation'];
		}

		if(!array_key_exists($rt_id, $rectypes['typedefs'])){
			return array(null,null,null,null,"Rectype# $rt_id not found");
		}

		$report = "";

		$rectype = $rectypes['typedefs'][$rt_id];
//		error_log("rectype is ".print_r($rectype,true));
		//record type info
		$rtName = $rectypes['names'][$rt_id];
		//detail or field type info
		$fieldTypeConceptIDIndex = $di['dty_ConceptID'];
		$fieldTypeNameIndex = $di['dty_Name'];
		$fieldBaseTypeIndex = $di['dty_Type'];
		//record field info
		$fieldNameIndex = $rid['rst_DisplayName'];
		$fieldDefaultValIndex = $rid['rst_DefaultValue'];
		$fieldHelpTextIndex = $rid['rst_DisplayHelpText'];
		$fieldTermsListIndex = $rid['rst_FilteredJsonTermIDTree'];
		$fieldTermHeaderListIndex = $rid['rst_TermIDTreeNonSelectableIDs'];
		$fieldPtrRectypeIDsListIndex = $rid['rst_PtrFilteredIDs'];
		$fieldMaxRepeatIndex = $rid['rst_MaxValues'];

		$rtConceptID = $rectype['commonFields'] [$ri['rty_ConceptID']];
		if(!$rtConceptID){
			$rtConceptID = "0-".$rt_id;
		}
		$rtDescription = $rectype['commonFields'] [$ri['rty_Description']];

		// output structure variables
		$model = "<instance>\n".
		"<fhml id=\"heuristscholar.org:$rtConceptID\" version=\"".date("Ymd")."\">\n".
		"<database id=\"".HEURIST_DBID."\" urlBase=\"".HEURIST_URL_BASE."\">".HEURIST_DBNAME."</database>\n".
		"<query depth=\"0\" db=\"".HEURIST_DBNAME."\" q=\"t:$rt_id\" />\n".
		"<generatedBy userID=\"".get_user_id()."\">".get_user_name()."</generatedBy>\n".
		"<createdBy/>\n".
		"<deviceID/>\n".
		"<createTime/>\n".
		"<uuid/>\n".
		"<records count=\"1\">\n".
		"<record depth=\"0\">\n".
		"<type>\n".
		"<conceptID>$rtConceptID</conceptID>\n".
		"<label>$rtName</label>\n".
		"</type>\n".
		"<nonce/>\n".
		"<details>\n";
		$bind ="<bind nodeset=\"createdBy\" type=\"string\" jr:preload=\"property\" jr:preloadParams=\"username\"/>\n".
		"<bind nodeset=\"createTime\" type=\"dateTime\" jr:preload=\"timestamp\" jr:preloadParams=\"start\"/>\n".
		"<bind nodeset=\"deviceID\" type=\"string\" jr:preload=\"property\" jr:preloadParams=\"deviceid\"/>\n".
		"<bind nodeset=\"uuid\" type=\"string\" readonly=\"true()\" calculate=\"uuid()\"/>\n".
		"<bind nodeset=\"records/record/nonce\" type=\"string\" readonly=\"true()\" calculate=\"concat(/fhml/deviceID,'|',/fhml/createTime,'|',/fhml/uuid)\"/>\n";

		$body = "<h:body>\n".
				"<group appearance=\"field-list\">\n";
		$groupSeparator = "</group>\n".
						"<group appearance=\"field-list\">\n";
		//@todo - sort by rst_DisplayOrder
		$fieldsLeft = count($rectype['dtFields']);
		$atGroupStart = true; //init separator detection for repatables
		foreach($rectype['dtFields'] as $dt_id => $rt_dt){

			if($rt_dt[$rid['rst_NonOwnerVisibility']]=='hidden'){
				continue;
			}

			--$fieldsLeft; // count down fields so we know when we hit the last one
			$dettype = $dettypes[$dt_id]['commonFields']; //get detail type description
			$baseType = $dettype[$fieldBaseTypeIndex];
			$fieldTypeName = $dettype[$fieldTypeNameIndex];
			$fieldName = $rt_dt[$fieldNameIndex];
			$fieldtype = getFieldType($baseType);
			$fieldMaxCount = $rt_dt[$fieldMaxRepeatIndex];
			$isRepeatable = ($fieldMaxCount > 1 || $fieldMaxCount == NULL);

			//skip any unsupport field types
			if(!$fieldtype) {
				$report = $report." $rtName.".$dettype[$fieldTypeNameIndex]." ignored since type ".$baseType." not supported<br/>";
				continue; // not supported
			}

			if($fieldtype == "groupbreak" && $atGroupStart){//skip double separator, note that this includes separators before non supported types
				continue;
			}

			if ($baseType == "resource"){
				$rtIDs =  $dettype[$di['dty_PtrTargetRectypeIDs']];
				if (!$rtIDs || $rtIDs == ""){//unconstrained pointers not supported
					$report = $report." $rtName.".$dettype[$fieldTypeNameIndex]." ignored since unconstrained resource pointers are not supported<br/>";
					continue;
				}
			}

			$dt_conceptid = $dettype[$fieldTypeConceptIDIndex];
			if(!$dt_conceptid){
				$dt_conceptid = "0-".$dt_id;
			}

			$defaultValue = $rt_dt[$fieldDefaultValIndex];// load default value
			//for controlled vocabs convert any local term ID to it's concept ID
			if ( $baseType == "enum" && array_key_exists("$defaultValue",$termLookup)){
				$termID = $termLookup[$defaultValue][$ti['trm_ConceptID']];
				if($termID){
					$defaultValue = $termID;
				}else{
					$defaultValue = HEURIST_DBID."-".$defaultValue;
				}
			} else if ( $baseType == "relation" && array_key_exists("$defaultValue",$relnLookup)){
				$termID = $relnLookup[$defaultValue][$ti['trm_ConceptID']];
				if($termID){
					$defaultValue = $termID;
				}else{
					$defaultValue = HEURIST_DBID."-".$defaultValue;
				}
			}

			if ($fieldtype != "groupbreak") {
				$model = $model."<dt$dt_id conceptID=\"$dt_conceptid\" type=\"$fieldTypeName\" name=\"$fieldName\">".($defaultValue? htmlentities($defaultValue):"")."</dt".$dt_id.">\n";
			}
			if($rt_dt[$rid['rst_RequirementType']]=='required'){
				$isrequired = 'required="true()"';
			}else if($rt_dt[$rid['rst_RequirementType']]=='forbidden') {
				$isrequired = 'readonly="true()"';
			}else{
				$isrequired = '';
			}

			$constraint = '';
			/* @todo
			if($rt_dt[$rid['rst_MinValues']]=='required'){
			//constraint=". &gt; 10.51 and . &lt; 18.39" jr:constraintMsg="number must be between 10.51 and 18.39"
			}
			*/

			// if repeatable vocab make it multi select. TODO: we should extend Heurist to include multi-select which is different than repeatable
			if($fieldtype=="select1" && $isRepeatable){
				$fieldtype = "select";
				$isRepeatable = false;
			}

			$label = htmlentities($rt_dt[$fieldNameIndex]);
			$hint = htmlentities($rt_dt[$fieldHelpTextIndex]);
			$inputDefBody = "<label>$label</label>\n".
			"<hint>$hint</hint>\n";
			$xpathPrefix = "/fhml/records/record/details/";
			$groupRepeatHdr =($atGroupStart ? "" : $groupSeparator).// if first element of group is repeatable skip groupSeparator
			"<label>$label</label>\n".
			"<repeat nodeset=\"/fhml/records/record/details/dt$dt_id\">\n";
			$groupRepeatFtr ="</repeat>\n".
						($fieldsLeft? $groupSeparator : "");
			$atGroupStart = false;// past detection code so

			if ($fieldtype != "groupbreak") {
				$bind = $bind."<bind nodeset=\"records/record/details/dt$dt_id\" type=\"$fieldtype\" $isrequired $constraint/>\n";
			}

			if ($isRepeatable){
				$body .= $groupRepeatHdr;
			}

			if($fieldtype=="select1" || $fieldtype=="select"){
				if ($baseType == "resource"){
					$body = $body."<$fieldtype appearance=\"minimal\" ref=\"".$xpathPrefix."dt".$dt_id."\">\n".
									$inputDefBody.
									createRecordLookup($rtIDs).
									"</$fieldtype>\n";
				}else{
					$termIDTree =  $dettype[$di['dty_JsonTermIDTree']];
					$disabledTermIDsList = $dettype[$di['dty_TermIDTreeNonSelectableIDs']];
					$fieldLookup = ($baseType == "relation" ? $relnLookup : $termLookup);
					$body = $body."<$fieldtype appearance=\"minimal\" ref=\"".$xpathPrefix."dt".$dt_id."\">\n".
									$inputDefBody.
									createTermSelect($termIDTree, $disabledTermIDsList, $fieldLookup, false, $ti).
									"</$fieldtype>\n";
				}
			}else if($fieldtype=="binary"){
				//todo check for sketch type
				$isDrawing = false;
				$appearance = $dt_id == DT_DRAWING ? "draw":"annotate";
				$body = $body."<upload ref=\"".$xpathPrefix."dt$dt_id\" appearance=\"$appearance\"  mediatype=\"image/*\">\n".
				$inputDefBody.
				"</upload>\n";

			}else if($fieldtype=="groupbreak"){// if we get to here we have a legitament sepearator so break
				$body .= $groupSeparator;
				$atGroupStart = true;
			}else if ($dt_id == DT_COUNTER){//we have a counter field so let's launch the Inventory Counter
				$body = $body."<input appearance=\"ex:faims.android.INVENTORYCOUNT\" ref=\"".$xpathPrefix."dt$dt_id\">\n".
				$inputDefBody.
				"</input>\n";
			}else{  //all others and  $fieldtype=="geopoint"  as well
				$body = $body."<input ref=\"".$xpathPrefix."dt$dt_id\">\n".
				$inputDefBody.
				"</input>\n";
			}

			if ($isRepeatable){
				$body .= $groupRepeatFtr;
				if ($fieldsLeft > 0) {
					$atGroupStart = true;
				}
			}

		}

		$model = $model."</details>\n".
		"</record>\n".
		"</records>\n".
		"</fhml>\n".
		"</instance>\n";
		$body = $body."</group>\n".
						"</h:body>\n";

		$form = "<?xml version=\"1.0\"?>\n".
				"<h:html xmlns=\"http://www.w3.org/2002/forms\" xmlns:h=\"http://www.w3.org/1999/xhtml\" ".
						"xmlns:ev=\"http://www.w3.org/2001/xml-events\" ".
						"xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" ".
						"xmlns:jr=\"http://openrosa.org/javarosa\">\n".
				"<h:head>\n".
				"<h:title>$rtName</h:title>\n".
				"<model>\n".
				$model.$bind.
				"</model>\n".
				"</h:head>\n".
				$body.
				"</h:html>";

		return array($form,$rtName,$rtConceptID,$rtDescription);
	}

	function createRecordLookup($rtIDs) {
		$emptyLookup = "<item>\n".
		"<label>\"no records found for rectypes '$rtIDs'\"</label>\n".
		"<value>0</value>\n".
		"</item>\n";
		$recs = mysql__select_assoc("Records","rec_ID","rec_Title","rec_RecTypeID in ($rtIDs) order by rec_Title");
		if (!count($recs)){
			return $emptyLookup;
		}
		$ret = "";
		foreach( $recs as $recID => $recTitle){
			if ($recTitle && $recTitle != ""){
				$ret = $ret."<item>\n".
				"<label>\"$recTitle\"</label>\n".
				"<value>$recID</value>\n".
				"</item>\n";
			}
		}
		return $ret;
	}

	/*
	* @param termIDTree an array tree of term ids
	* @param disabledTermIDsList a comma separated list of term ids to be markered as headers, can be empty
	* @param termLookup a lookup array of term names
	* @param defaultTermID id of term to show as selected, can be null
	*/
	function createTermSelect($termIDTree, $disabledTermIDsList, $termLocalLookup, $isAddFirstEmpty, $ti) {

		//global $ti; //terms field index

		$res = "";

		$termIDTree = preg_replace("/[\}\{\:\"]/","",$termIDTree);
		$termIDTree = explode(",", $termIDTree);
		$disabledTerms =  explode(",", $disabledTermIDsList);

		foreach($termIDTree as $index => $idTerm ) {
			if(array_key_exists($idTerm, $disabledTerms)){
				continue;
			}

			if(array_key_exists($idTerm, $termLocalLookup)) {
				$termName = $termLocalLookup[$idTerm][$ti['trm_Label']];
				$termCode = $termLocalLookup[$idTerm][$ti['trm_ConceptID']];
				if(!$termCode){
					$termCode = (HEURIST_DBID?HEURIST_DBID:HEURIST_DBNAME)."-".$idTerm;
				}
			}else{
				continue;
			}

			$res = $res."<item>\n".
			"<label>\"$termName\"</label>\n".
			"<value>$termCode</value>\n".
			"</item>\n";
		}
		return $res;
	}

	function createTermSelectList($termIDTree, $disabledTermIDsList, $termLookup, $isAddFirstEmpty, $ti) {

		//global $ti; //terms field index

		$res = "";

		$termIDTree = json_decode($termIDTree);
		$disabledTerms =  explode(",", $disabledTermIDsList);
		if($isAddFirstEmpty){
			$res =
			"<item>
			<label>&nbsp</label>
			<value></value>
			</item>";
		}

		$res = $res.createSubTreeOptions(null, 0, $termIDTree, $termLookup, $ti);
		return $res;
	}

	function createSubTreeOptions($optgroup, $depth, $termSubTree, $localLookup, $ti) {

		$res2 = "";

		foreach ($termSubTree as $termID => $subTermTree) {

			if(array_key_exists($termID, $localLookup)) {
				$termName = $localLookup[$termID][$ti['trm_Label']];
				$termCode = $localLookup[$termID][$ti['trm_ConceptID']];
				if(!$termCode){
					$termCode = HEURIST_DBID."-".$termID;
				}
			}else{
				continue;
			}

			/* @todo
			if(isNotFirefox && depth>1){
			//for non mozilla add manual indent
			var a = new Array(depth*2);
			termName = a.join('. ') + termName;
			}
			*/

			$isDisabled = (!$disabledTerms || array_key_exists($termID, $disabledTerms));
			$hasChildren = count((array)$subTermTree)>0;
			$isHeader = $isDisabled && $hasChildren;

			if($isHeader) { // header term behaves like an option group

				/* CHOICE is not supported in JavaRosa
				var new_optgroup = document.createElement("optgroup");
				new_optgroup.label = termName;

				if(optgroup==null){
				selObj.appendChild(new_optgroup);
				}else{
				optgroup.appendChild(new_optgroup);
				}*/

				//A dept of 8 (depth starts at 0) is maximum, to keep it organised
				$res2 = $res2.createSubTreeOptions(null, (($depth<7)?$depth+1:$depth), $subTermTree, $localLookup, $ti);
			}else{

				if(!$isDisabled){

					$res2 = $res2."<item>\n".
					"<label>\".$termName.\"</label>\n".
					"<value>\".$termCode.\"</value>\n".
					"</item>\n";

				}

				//second and more levels terms
				if($hasChildren) {
					// A depth of 8 (depth starts at 0) is the max indentation, to keep it organised
					$res2 = $res2.createSubTreeOptions($optgroup, (($depth<7)?$depth+1:$depth), $subTermTree, $localLookup, $ti);
				}
			}
		}
		return $res2;
	}//end function

?>
