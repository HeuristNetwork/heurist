<?php

    /* getDBStructureAsXForms.php - returns database definitions (rectypes, details etc.)
    * as XForm data ready for use in mobile app - primarily intended for NeCTAR FAIMS project
    *
    * Ian Johnson 16/9/12 based on getDBStructure.php
    * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
    * @link: http://HeuristScholar.org
    * @license http://www.gnu.org/licenses/gpl-3.0.txt
    * @package Heurist academic knowledge management system
    * @param includeUgrps=1 will output user and group information in addition to definitions
    * @param approvedDefsOnly=1 will only output Reserved and Approved definitions
    * @todo
    *
    * IJ Updated to database format 3.1.0 2/8/11
    *
    -->*/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

    // Deals with all the database connections stuff

    mysql_connection_db_select(DATABASE);
    if(mysql_error()) {
        die("Could not get database structure from given database source, MySQL error - unable to connect to database.");
    }
	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (!is_admin()) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to export structure as XForms</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}
?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Export record type structures to XForms</title>
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
    	print '<font color="red">Failed to create folder for xforms!</font>';
    	return;
	}
}

 $a_rectypes = explode(",",$rectypes);

 foreach ($a_rectypes as $rt_id) {
 	 if($rt_id){
	 	print "<div>".createXForm($rt_id)."</div> ";
	 }
 }
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
geopoint	Collect GPS coordinates.
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
  				return "text";
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
case "enum":
				return "select1";
case "file":
				return "binary";
default:
  return null;
}

}

//
// Creates XForm and save it into FILESTORE/xforms folder
//
function createXForm($rt_id){

	global $folder;


	$dettypes = getAllDetailTypeStructures();
	$dettypes = $dettypes['typedefs'];
	$di = $dettypes['fieldNamesToIndex'];

	$rectypes = getAllRectypeStructures();
	$terms = getTerms();
	$ti = $terms['fieldNamesToIndex'];

	if(!array_key_exists($rt_id, $rectypes['typedefs'])){
		return "Rectype# $rt_id not found";
	}

	$report = "";

	$rectype = $rectypes['typedefs'][$rt_id];
	$ri = $rectypes['typedefs']['commonNamesToIndex'];
	$rid = $rectypes['typedefs']['dtFieldNamesToIndex'];

	$title = $rectypes['names'][$rt_id];

	try{

	$concept_id = $rectype['commonFields'] [$ri['rty_ConceptID']];
	if(!$concept_id){
		$concept_id = "0-".$rt_id;
	}

	$instance = '
		<instance>
			<nm id="rt'.$concept_id.'">';
	$bind = '';
	$body = '<h:body>';

	//@todo - sort by rst_DisplayOrder

	foreach($rectype['dtFields'] as $dt_id => $rt_dt){

		if($rt_dt[$rid['rst_NonOwnerVisibility']]=='hidden'){
			continue;
		}

		$dettype = $dettypes[$dt_id]['commonFields']; //get detail type description

		$fieldtype = getFieldType($dettype[$di['dty_Type']]);

		if(!$fieldtype) {
			$report = $report." Field ".$dettype[$di['dty_Name']]." ignored since type ".$dettype[$di['dty_Type']]." not supported<br/>";
			continue; // not supported
		}

		$dt_conceptid = $dettype[$di['dty_ConceptID']];
		if(!$dt_conceptid){
			$dt_conceptid = "0-".$dt_id;
		}

		$instance = $instance.'
				<dt'.$dt_conceptid.'>'.htmlentities($rt_dt[$rid['rst_DefaultValue']]).'</dt'.$dt_conceptid.'>';


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

		$label = htmlentities($rt_dt[$rid['rst_DisplayName']]);
		$hint = htmlentities($rt_dt[$rid['rst_DisplayHelpText']]);
		$inputhdr = '
			<label>'.$label.'</label>
			<hint>'.$hint.'</hint>';


		$bind = $bind.'
		<bind nodeset="/nm/dt'.$dt_conceptid.'" type="'.$fieldtype.'" '.$isrequired.' '.$constraint.'/>';

		if($fieldtype=="select1"){

			$termIDTree =  $dettype[$di['dty_JsonTermIDTree']];
			$disabledTermIDsList = $dettype[$di['dty_TermIDTreeNonSelectableIDs']];
			$termLookup = $terms['termsByDomainLookup']['enum'];

//
			$body = $body.'
		<select1 ref="select1" appearance="minimal" ref="dt'.$dt_conceptid.'">'.$inputhdr.
		createTermSelect($termIDTree, $disabledTermIDsList, $termLookup, false, $ti).'
		</select1>';

/* @todo
      <item>
        <label>option 1</label>
        <value>1</value>
      </item>
*/

		}else if($fieldtype=="binary"){

			$body = $body.'
		<upload ref="dt'.$dt_conceptid.'" mediatype="image/*">'.$inputhdr.'
		</upload>';

		}else{  //all others and  $fieldtype=="geopoint"  as well

			$body = $body.'
		<input ref="dt'.$dt_conceptid.'">'.$inputhdr.'
		</input>';
		}

	}

	$instance = $instance.'
			</nm>
		</instance>';
	$body = $body.'
	</h:body>';

	$out = '<?xml version="1.0"?>
<h:html xmlns="http://www.w3.org/2002/xforms" xmlns:h="http://www.w3.org/1999/xhtml" xmlns:ev="http://www.w3.org/2001/xml-events" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:jr="http://openrosa.org/javarosa">
	<h:head>
		<h:title>'.$title.'</h:title>
		<model>
			'.$instance.$bind.'
		</model>
	</h:head>
	'.$body.'
</h:html>';

	$filename = preg_replace('/[^a-zA-Z0-9-_\.]/','', $title);
	$filename = $folder."/".$filename.".xml";

	file_put_contents($filename, $out);

	$report = $report.". Saved ok";

	} catch (Exception $e) {
    		$report = $report.'Exception '.($e->getMessage());
	}

	return $title.". ".$report;
}

/*
* @param termIDTree an array tree of term ids
* @param disabledTermIDsList a comma separated list of term ids to be markered as headers, can be empty
* @param termLookup a lookup array of term names
* @param defaultTermID id of term to show as selected, can be null
*/
function createTermSelect($termIDTree, $disabledTermIDsList, $termLookup, $isAddFirstEmpty, $ti) {

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
				 		$termCode = "0-".$localLookup[$termID][$ti['trm_Code']];
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

					$res2 = $res2."
      <item>
        <label>".$termName."</label>
        <value>".$termCode."</value>
      </item>";

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
