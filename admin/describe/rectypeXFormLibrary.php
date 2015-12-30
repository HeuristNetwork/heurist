<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* rectypeXFormLibrary contains the functions which translate a heurist rectype definition info an XForm xml document
* following the ODK Collect xForms guidelines.
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/**
 * Function list:
 * - getXFormTypeFromBaseType()
 * - buildform()
 * - createRecordLookup()
 * - createTermSelect()
 */
require_once (dirname(__FILE__) . '/../../common/connect/applyCredentials.php');
require_once (dirname(__FILE__) . '/../../common/php/dbMySqlWrappers.php');
require_once (dirname(__FILE__) . '/../../common/php/getRecordInfoLibrary.php');
/*
	http://opendatakit.org/help/form-design/xlsform/

	ODK Collect supports a number of simple question types:
	text						Text input.
	integer						Integer (ie, whole number) input.
	decimal						Decimal input.
	selection_one [options]		Multiple choice question; only one answer can be selected.
	select_multiple [options]	Multiple choice question; multiple answers can be selected.
	note						Display a note on the screen, takes no input.
	geopoint					Collect GPS coordinates. LAT LONG ALT ACCUR
	image						Take a photograph.
	barcode						Scan a barcode, requires the barcode scanner app is installed.
	date						Date input.
	datetime					Accepts a date and a time input.
	audio						Take an audio recording.
	video						Take a video recording.
	calculate 					Perform a calculation; see “calculates” below.

	Note: the 'appearance " attribute can be used to launch android intents that can be serviced by
	arbritrary android applications - it's assumed that the app returns the correct type
*/
/**
 * mapping function that maps Heurist base types into XForm ui types.
 * @param    string [$sDetailBasetype] base type of HERUIST detail
 * @return   string extened ODK and javaRosa XForm type mapped from Heurist detail base data type
 * @link     http://opendatakit.org/help/form-design/xlsform/
 */
function getXFormTypeFromBaseType($sDetailBasetype) {
	switch ($sDetailBasetype) {
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
		case "resource": //temporary in form lookup -- should be external call to lookup/form launch tool  lookups are per rt-dt-resource (constrained by query and rt)

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
/**
 * main form generation code build the model to approximate HML, the form UI and the binding between UI and model
 * @staticvar   array [$dettypes] array detail type definitions for this database
 * @staticvar   object [$di] field name to index mapping for detail type definition
 * @staticvar   array [$rectypes] array record type structure definitions for this database
 * @staticvar   object [$ri] field name to index mapping for record field structure definition
 * @staticvar   object [$ti] field name to index mapping for term definition
 * @staticvar   array [$termLookup] array term structure definitions for the enumerations in this database
 * @staticvar   array [$relnLookup] array term structure definitions for the relationships in this database
 * @param       integer [$rt_id] the rectype locally unique identifier
 * @return      object an array of strings representing form, rtName, rtConceptID, rtDescription, and report on success
 * @uses        getXFormTypeFromBaseType()
 * @uses        createRecordLookup()
 * @uses        createTermSelect()
 * @uses        getTerms()
 * @uses        getAllDetailTypeStructures()
 * @uses        getAllRectypeStructures()
 * @uses        HEURIST_FILESTORE_DIR
 * @uses        HEURIST_BASE_URL
 * @uses        HEURIST_DBNAME
 */
function buildform($rt_id) {
	// mappings and lookups - static so we only retrieve once per service call
	static $dettypes, $di, $rectypes, $ri, $rid, $terms, $ti, $termLookup, $relnLookup;
	if (!$dettypes || !$di) {
		$dettypes = getAllDetailTypeStructures();
		$dettypes = $dettypes['typedefs'];
		$di = $dettypes['fieldNamesToIndex'];
	}
	if (!$rectypes || !$ri || !$rid) {
		$rectypes = getAllRectypeStructures();
		$ri = $rectypes['typedefs']['commonNamesToIndex'];
		$rid = $rectypes['typedefs']['dtFieldNamesToIndex'];
	}
	if (!$terms || !$ti || !$termLookup || !$relnLookup) {
		$terms = getTerms();
		$ti = $terms['fieldNamesToIndex'];
		$termLookup = $terms['termsByDomainLookup']['enum'];
		$relnLookup = $terms['termsByDomainLookup']['relation'];
	}
	if (!array_key_exists($rt_id, $rectypes['typedefs'])) {
		return array(null, null, null, null, "Rectype# $rt_id not found");
	}

	$report = "";
	$rectype = $rectypes['typedefs'][$rt_id];

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
	$rtConceptID = $rectype['commonFields'][$ri['rty_ConceptID']];
	if (!$rtConceptID) {
		$rtConceptID = "0-" . $rt_id;
	}
	$rtDescription = $rectype['commonFields'][$ri['rty_Description']];

	// output structure variables
	$model = "<instance>\n" . "<fhml id=\"heuristscholar.org:$rtConceptID\" version=\"" . date("Ymd") . "\">\n" . "<database id=\"" . HEURIST_DBID . "\" urlBase=\"" . HEURIST_BASE_URL . "\">" . HEURIST_DBNAME . "</database>\n" . "<query depth=\"0\" db=\"" . HEURIST_DBNAME . "\" q=\"t:$rt_id\" />\n" . "<generatedBy userID=\"" . get_user_id() . "\">" . get_user_name() . "</generatedBy>\n" . "<createdBy/>\n" . "<deviceID/>\n" . "<createTime/>\n" . "<uuid/>\n" . "<records count=\"1\">\n" . "<record depth=\"0\">\n" . "<type>\n" . "<conceptID>$rtConceptID</conceptID>\n" . "<label>$rtName</label>\n" . "</type>\n" . "<nonce/>\n" . "<details>\n";
	$bind = "<bind nodeset=\"createdBy\" type=\"string\" jr:preload=\"property\" jr:preloadParams=\"username\"/>\n" . "<bind nodeset=\"createTime\" type=\"dateTime\" jr:preload=\"timestamp\" jr:preloadParams=\"start\"/>\n" . "<bind nodeset=\"deviceID\" type=\"string\" jr:preload=\"property\" jr:preloadParams=\"deviceid\"/>\n" . "<bind nodeset=\"uuid\" type=\"string\" readonly=\"true()\" calculate=\"uuid()\"/>\n" . "<bind nodeset=\"records/record/nonce\" type=\"string\" readonly=\"true()\" calculate=\"concat(/fhml/deviceID,'|',/fhml/createTime,'|',/fhml/uuid)\"/>\n";
	$body = "<h:body>\n" . "<group appearance=\"field-list\">\n";
	$groupSeparator = "</group>\n" . "<group appearance=\"field-list\">\n";
	//@todo - sort by rst_DisplayOrder
	$fieldsLeft = count($rectype['dtFields']);
	$atGroupStart = true; //init separator detection for repatables

	foreach ($rectype['dtFields'] as $dt_id => $rt_dt) {
		if ($rt_dt[$rid['rst_NonOwnerVisibility']] == 'hidden') {
			continue;
		}
		--$fieldsLeft; // count down fields so we know when we hit the last one
		$dettype = $dettypes[$dt_id]['commonFields']; //get detail type description
		$baseType = $dettype[$fieldBaseTypeIndex];
		$fieldTypeName = $dettype[$fieldTypeNameIndex];
		$fieldName = $rt_dt[$fieldNameIndex];
		$fieldtype = getXFormTypeFromBaseType($baseType);
		$fieldMaxCount = $rt_dt[$fieldMaxRepeatIndex];
		$isRepeatable = ($fieldMaxCount > 1 || $fieldMaxCount == NULL);
		//skip any unsupport field types
		if (!$fieldtype) {
			$report = $report . " $rtName." . $dettype[$fieldTypeNameIndex] . " ignored since type " . $baseType . " not supported<br/>";
			continue; // not supported
		}
		if ($fieldtype == "groupbreak" && $atGroupStart) { //skip double separator, note that this includes separators before non supported types
			continue;
		}
		if ($baseType == "resource") {
			$rtIDs = $dettype[$di['dty_PtrTargetRectypeIDs']];
			if (!$rtIDs || $rtIDs == "") { //unconstrained pointers not supported
				$report = $report . " $rtName." . $dettype[$fieldTypeNameIndex] . " ignored since unconstrained resource pointers are not supported<br/>";
				continue;
			}
		}
		$dt_conceptid = $dettype[$fieldTypeConceptIDIndex];
		if (!$dt_conceptid) {
			$dt_conceptid = "0-" . $dt_id;
		}

		$defaultValue = $rt_dt[$fieldDefaultValIndex]; // load default value
		//for controlled vocabs convert any local term ID to it's concept ID
		if ($baseType == "enum" && array_key_exists("$defaultValue", $termLookup)) {
			$termID = $termLookup[$defaultValue][$ti['trm_ConceptID']];
			if ($termID) {
				$defaultValue = $termID;
			} else {
				$defaultValue = HEURIST_DBID . "-" . $defaultValue;
			}
		} else if ($baseType == "relation" && array_key_exists("$defaultValue", $relnLookup)) {
			$termID = $relnLookup[$defaultValue][$ti['trm_ConceptID']];
			if ($termID) {
				$defaultValue = $termID;
			} else {
				$defaultValue = HEURIST_DBID . "-" . $defaultValue;
			}
		}
		if ($fieldtype != "groupbreak") {
			$model = $model . "<dt$dt_id conceptID=\"$dt_conceptid\" type=\"$fieldTypeName\" name=\"$fieldName\">" . ($defaultValue ? htmlentities($defaultValue) : "") . "</dt" . $dt_id . ">\n";
		}
		if ($rt_dt[$rid['rst_RequirementType']] == 'required') {
			$isrequired = 'required="true()"';
		} else if ($rt_dt[$rid['rst_RequirementType']] == 'forbidden') {
			$isrequired = 'readonly="true()"';
		} else {
			$isrequired = '';
		}
		$constraint = '';
		/* @todo
			if($rt_dt[$rid['rst_MinValues']]=='required'){
			//constraint=". &gt; 10.51 and . &lt; 18.39" jr:constraintMsg="number must be between 10.51 and 18.39"
			}
		*/
		// if repeatable vocab make it multi select. TODO: we should extend Heurist to include multi-select which is different than repeatable
		if ($fieldtype == "select1" && $isRepeatable) {
			$fieldtype = "select";
			$isRepeatable = false;
		}
		$label = htmlentities($rt_dt[$fieldNameIndex]);
		$hint = htmlentities($rt_dt[$fieldHelpTextIndex]);
		$inputDefBody = "<label>$label</label>\n" . "<hint>$hint</hint>\n";
		$xpathPrefix = "/fhml/records/record/details/";
		$groupRepeatHdr = ($atGroupStart ? "" : $groupSeparator) . // if first element of group is repeatable skip groupSeparator
		"<label>$label</label>\n" . "<repeat nodeset=\"/fhml/records/record/details/dt$dt_id\">\n";
		$groupRepeatFtr = "</repeat>\n" . ($fieldsLeft ? $groupSeparator : "");
		$atGroupStart = false; // past detection code so
		if ($fieldtype != "groupbreak") {
			$bind = $bind . "<bind nodeset=\"records/record/details/dt$dt_id\" type=\"$fieldtype\" $isrequired $constraint/>\n";
		}
		if ($isRepeatable) {
			$body.= $groupRepeatHdr;
		}
		if ($fieldtype == "select1" || $fieldtype == "select") {
			if ($baseType == "resource") {
				$body = $body . "<$fieldtype appearance=\"minimal\" ref=\"" . $xpathPrefix . "dt" . $dt_id . "\">\n" . $inputDefBody . createRecordLookup($rtIDs) . "</$fieldtype>\n";
			} else {
				$termIDTree = $dettype[$di['dty_JsonTermIDTree']];
				$disabledTermIDsList = $dettype[$di['dty_TermIDTreeNonSelectableIDs']];
				$fieldLookup = ($baseType == "relation" ? $relnLookup : $termLookup);
				$body = $body . "<$fieldtype appearance=\"minimal\" ref=\"" . $xpathPrefix . "dt" . $dt_id . "\">\n" . $inputDefBody . createTermSelect($termIDTree, $disabledTermIDsList, $fieldLookup, false, $ti) . "</$fieldtype>\n";
			}
		} else if ($fieldtype == "binary") {
			//todo check for sketch type
			$isDrawing = false;
			$appearance = $dt_id == DT_DRAWING ? "draw" : "annotate";
			$body = $body . "<upload ref=\"" . $xpathPrefix . "dt$dt_id\" appearance=\"$appearance\"  mediatype=\"image/*\">\n" . $inputDefBody . "</upload>\n";
		} else if ($fieldtype == "groupbreak") { // if we get to here we have a legitament sepearator so break
			$body.= $groupSeparator;
			$atGroupStart = true;
		} else if ($dt_id == DT_COUNTER) { //we have a counter field so let's launch the Inventory Counter
			$body = $body . "<input appearance=\"ex:faims.android.INVENTORYCOUNT\" ref=\"" . $xpathPrefix . "dt$dt_id\">\n" . $inputDefBody . "</input>\n";
		} else { //all others and  $fieldtype=="geopoint"  as well
			$body = $body . "<input ref=\"" . $xpathPrefix . "dt$dt_id\">\n" . $inputDefBody . "</input>\n";
		}
		if ($isRepeatable) {
			$body.= $groupRepeatFtr;
			if ($fieldsLeft > 0) {
				$atGroupStart = true;
			}
		}
	}
	$model = $model . "</details>\n" . "</record>\n" . "</records>\n" . "</fhml>\n" . "</instance>\n";
	$body = $body . "</group>\n" . "</h:body>\n";
	$form = "<?xml version=\"1.0\"?>\n" . "<h:html xmlns=\"http://www.w3.org/2002/forms\" xmlns:h=\"http://www.w3.org/1999/xhtml\" " . "xmlns:ev=\"http://www.w3.org/2001/xml-events\" " . "xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" " . "xmlns:jr=\"http://openrosa.org/javarosa\">\n" . "<h:head>\n" . "<h:title>$rtName</h:title>\n" . "<model>\n" . $model . $bind . "</model>\n" . "</h:head>\n" . $body . "</h:html>";
	return array($form, $rtName, $rtConceptID, $rtDescription, $report);
}
/**
 * creates an xForms select lookup list using the rectitles and heurist record ids
 *
 * given a list of recType IDs this function create a select list of Record Titles (alphabetical order)
 * with HEURIST record ids as the lookup value.
 * @param        array [$rtIDs] array of record Type identifiers for which a resource pointer is constrained to.
 * @return       string formatted as a XForm select lookup item list
 * @todo         need to accept a filter for reducing the recordset, currently you get all records of every type in the input list
 */
function createRecordLookup($rtIDs) {
	$emptyLookup = "<item>\n" . "<label>\"no records found for record types '$rtIDs'\"</label>\n" . "<value>0</value>\n" . "</item>\n";
	$recs = mysql__select_assoc("Records", "rec_ID", "rec_Title", "rec_RecTypeID in ($rtIDs) order by rec_Title");
	if (!count($recs)) {
		return $emptyLookup;
	}
	$ret = "";
	foreach ($recs as $recID => $recTitle) {
		if ($recTitle && $recTitle != "") {
			$ret = $ret . "<item>\n" . "<label>\"$recTitle\"</label>\n" . "<value>$recID</value>\n" . "</item>\n";
		}
	}
	return $ret;
}
/**
 * creates an xForm item list for the set of terms passed in.
 *
 * @param        string [$termIDTree] json string representing the tree of term ids for this term field
 * @param        string [$disabledTermIDsList] a comma separated list of term ids to be markered as headers, can be empty
 * @param        array [$termLocalLookup] a lookup array of term structures
 * @param        object [$ti] term structure field name to index mapping for term definition
 * @return       string representing an xForm select item list
 * @see          getTermOffspringList
 * @todo         expand this function to xForm cascaded selects auto completion select
 */
function createTermSelect($termIDTree, $disabledTermIDsList, $termLocalLookup, $ti) {
	$res = "";
	$termIDTree = preg_replace("/[\}\{\:\"]/", "", $termIDTree); //remove unused structure characters
	$termIDTree = explode(",", $termIDTree);
	if (count($termIDTree) == 1) { //term set parent term, so expand to direct children
		$childTerms = getTermOffspringList($termIDTree[0], false);
		if (count($childTerms) > 0) {
			$termIDTree = $childTerms;
		}
	}
	// sort($termIDTree);
	$disabledTerms = explode(",", $disabledTermIDsList);
	foreach ($termIDTree as $index => $idTerm) {
		if (array_key_exists($idTerm, $disabledTerms)) {
			continue;
		}
		if (array_key_exists($idTerm, $termLocalLookup)) {
			$termName = $termLocalLookup[$idTerm][$ti['trm_Label']];
			$termCode = $termLocalLookup[$idTerm][$ti['trm_ConceptID']];
			if (!$termCode) {
				$termCode = (HEURIST_DBID ? HEURIST_DBID : HEURIST_DBNAME) . "-" . $idTerm;
			}
		} else {
			continue;
		}
		$res = $res . "<item>\n" . "<label>\"$termName\"</label>\n" . "<value>$termCode</value>\n" . "</item>\n";
	}
	return $res;
}
?>
