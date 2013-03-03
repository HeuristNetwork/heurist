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
* brief description of file
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

?>

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
 * one off special preprocessing file for field data imports. Translations included for crossdatabase errors.
 *
 * @author		Stephen White	<stephen.white@sydney.edu.au>
 * @copyright 	(C) 2005-2013 University of Sydney
 * @link 		http://Sydney.edu.au/Heurist/about.html
 * @version		3.1.0
 * @license 	http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @package 	Heurist academic knowledge management system
 * @subpackage	XForms
 */
/**
*
* Function list:
* - processChildDir()
* - parseImportForm()
* - loadRemoteURLContent()
* - findRecorder()
* - findSurveyUnit()
* - returnXMLSuccessMsgPage()
* - returnXMLErrorMsgPage()
*
* No Classes in this File
*
*/

require_once (dirname(__FILE__) . '/../../common/connect/applyCredentials.php');
require_once (dirname(__FILE__) . '/../../common/php/dbMySqlWrappers.php');
require_once (dirname(__FILE__) . '/../../common/php/getRecordInfoLibrary.php');

if (!is_logged_in()) {
	return;
}

mysql_connection_select(DATABASE);
// set parameter defaults
$formImportDir = @$_REQUEST['formImportDir']; // outName returns the hml direct.
$resourceUriRoot = @$_REQUEST['resourceUriRoot']; // URI prefix for the resources upload with forms.
//if no style given then try default, if default doesn't exist we our put raw xml
$importData = array();
$info = new SplFileInfo($formImportDir);
$i = 0;
$xmlForms = array("form" => array(), "resource" => array());
if ($info->isDir()) {
	$iterator = new RecursiveDirectoryIterator($formImportDir);
	while ($iterator->valid()) {
		if ($iterator->isFile()) {
			$ext = pathinfo($iterator->getPathname(), PATHINFO_EXTENSION);
			if ($ext == "xml") {
				//				echo $prefix,$j,"&nbsp;import ",$dirIterator->getPathname(), "<br>\n";
				$xmlForms["form"][$iterator->getFilename() ] = $iterator->getPathname();
			} else {
				$filepath = preg_replace("/" . preg_replace("/\//", "\\\/", HEURIST_UPLOAD_DIR) . "/", "/", $iterator->getPathname());
				//				echo $prefix,$j,"&nbsp;resource ".$dirIterator->getFilename()." has path ".$filepath, "<br>\n";
				$xmlForms["resource"][$iterator->getFilename() ] = $filepath;
			}
		} else if ($iterator->isDir() && $iterator->hasChildren(false)) {
			//			echo $i,pathinfo($iterator->getPathname(),PATHINFO_FILENAME), "<br>\n";
			processChildDir($iterator->getChildren(), $i . "-");
		}
		$iterator->next();
		$i++;
	}
} else {
	echo "something is wrong";
}

foreach ($xmlForms["form"] as $filename => $path) {
	list($rtyName, $headers, $row) = parseImportForm($filename, $xmlForms["resource"]);
	$rtyName = "" . $rtyName;
	if (!$rtyName) continue;
	if (!array_key_exists($rtyName, $importData)) {
		$importData[$rtyName] = array();
	}
	if (!array_key_exists($headers, $importData[$rtyName])) {
		$importData[$rtyName][$headers] = array();
	}
	array_push($importData[$rtyName][$headers], $row);
	//break;

}
//echo json_format($xmlForms,true);
echo "<h2> Importing Forms </h2>";
foreach ($importData as $rtyName => $importForms) {
	echo "<br><b>" . $rtyName . "</b><br>";
	foreach ($importForms as $header => $forms) {
		echo "" . $header . "<br>";
		foreach ($forms as $form) {
			echo "" . $form . "<br>";
		}
	}
}
return;
//  END OF Service Code

/**
* simple description
* detailed desription
* @global       type description of global variable usage in a function
* @param        type [$varname] description
* @return       type description
* @link         URL
* @see          name of another element (function or object) used in this function
* @throws       list of exceptions thrown in this code
*/
function processChildDir($dirIterator, $prefix) {
	global $xmlForms;
	$j = 0;
	while ($dirIterator->valid()) {
		if ($dirIterator->isFile()) {
			$ext = pathinfo($dirIterator->getPathname(), PATHINFO_EXTENSION);
			$filepath = preg_replace("/" . preg_replace("/\//", "\\\/", HEURIST_UPLOAD_DIR) . "/", "/", $dirIterator->getPathname());
			if ($ext == "xml") {
				//				echo $prefix,$j,"&nbsp;import ",$dirIterator->getPathname(), "<br>\n";
				//				array_push($xmlForms["form"],$dirIterator->getPathname());
				$xmlForms["form"][$dirIterator->getFilename() ] = $dirIterator->getPathname();
			} else {
				//				echo $prefix,$j,"&nbsp;resource ".$dirIterator->getFilename()." has path ".$filepath, "<br>\n";
				$xmlForms["resource"][$dirIterator->getFilename() ] = $filepath;
			}
		} else if ($dirIterator->isDir() && $dirIterator->hasChildren(false)) {
			//			echo $prefix,$j,$iterator->getPathname(), "<br>\n";
			processChildDir($dirIterator->getChildren(), $prefix . $j . "-");
		}
		$dirIterator->next();
		$j++;
	}
}
/**
* simple description
* detailed desription
* @global       type description of global variable usage in a function
* @param        type [$varname] description
* @return       type description
* @link         URL
* @see          name of another element (function or object) used in this function
* @throws       list of exceptions thrown in this code
*/
function parseImportForm($fhmlFilename, $resources) {
	global $xmlForms, $formImportDir, $resourceUriRoot;
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
	$dtyIDTrans = array("15" => "327", //	Recorder
	"28" => "119", //	Start point
	"332" => "520", //	End point
	"336" => "510", //	Photo
	"337" => "511", //	Sketch plan
	"322" => "508", 15 => 327, 28 => 119, 332 => 520, 336 => 510, 337 => 511, 322 => 508); //Survey Unit
	$fhmlDoc = simplexml_load_file($xmlForms["form"][$fhmlFilename]);
	$header = array();
	$dataRow = array();
	$recType = $fhmlDoc->xpath("/fhml/records/record/type/label");
	echo "processing rectype: \"" . $recType[0] . "\" from: " . $fhmlFilename . "<br>";
	// echo "rectyp: ".$recType[0]."<br>";
	array_push($header, "rectype");
	array_push($dataRow, $recType[0]);
	$formID = $fhmlDoc->xpath("/fhml/@id");
	//	echo "nonce: ".$nonce[0]."<br>";
	array_push($header, "formID");
	array_push($dataRow, $formID[0]);
	$formVer = $fhmlDoc->xpath("/fhml/@version");
	//	echo "nonce: ".$nonce[0]."<br>";
	array_push($header, "formVer");
	array_push($dataRow, $formVer[0]);
	$nonce = $fhmlDoc->xpath("/fhml/records/record/nonce");
	//	echo "nonce: ".$nonce[0]."<br>";
	array_push($header, "nonce");
	array_push($dataRow, $nonce[0]);
	//	error_log("nonce = ".print_r($nonce,true));
	$database = $fhmlDoc->xpath("/fhml/database");
	$database = $database[0];
	$dbID = $database->attributes();
	$dbID = $dbID["id"];
	$dateStamp = @$fhmlDoc->xpath("/fhml/dateStamp");
	$createTime = @$fhmlDoc->xpath("/fhml/createTime");
	if (!@$dateStamp[0] && @$createTime[0]) {
		$dateStamp = $createTime;
	}
	//	echo "dataStamp: ".$dateStamp[0]."<br>";
	array_push($header, "dateStamp");
	array_push($dataRow, $dateStamp[0]);
	$deviceID = $fhmlDoc->xpath("/fhml/deviceID");
	array_push($header, "deviceID");
	array_push($dataRow, $deviceID[0]);
	$details = $fhmlDoc->xpath("/fhml/records/record/details");
	foreach ($details[0]->children() as $detail) {
		//		if ("".$detail == "") continue;
		$attr = $detail->attributes();
		$dtyID = $detail->getName();
		//		error_log("dtyID ".print_r($dtyID,true));
		$dtyID = preg_replace("/dt/", "", $dtyID);
		if (($dtyID == "15" || $dtyID == "345")) {
			$detail = findRecorder($detail);
		}
		if (($dtyID == "322" || $dtyID == "344")) {
			$detail = findSurveyUnit($detail);
		}
		//error_log("form dtyID ".print_r($dtyID,true));
		$dtyID = @$dtyIDTrans[$dtyID] ? $dtyIDTrans[$dtyID] : $dtyID; // SPECIAL FOR ZAGORA IMPORT  REMOVE!!!!!!!!!!!!!!!!
		//error_log("detailtype $dtyID  ".print_r($dettypes[intval($dtyID)]['commonFields'],true));
		$dtBaseType = $dettypes[intval($dtyID) ]['commonFields'][$di['dty_Type']];
		$detailName = ($attr["name"] ? $attr["name"] : $detail->getName());
		if ($dtBaseType == "freetext" || $dtBaseType == "blocktext") { //text so enclose in quotes
			if (preg_match("/\"/", $detail)) {
				$detail = preg_replace("/\"/", "'", $detail); //strip any quotes for non string values

			}
			$detail = "\"" . $detail . "\"";
		} else {
			$detail = preg_replace("/\"/", "", $detail); //strip any quotes for non string values

		}
		$dbConceptPrefix = $dbID . "-";
		error_log("basetype = " . print_r($dtBaseType, true));
		if ($dtBaseType == "enum" || $dtBaseType == 'relation') {
			$detail = preg_replace("/$dbConceptPrefix/", "", $detail);
		} else if ($dtBaseType == 'geo') {
			preg_match_all("/\d+\.\d+/", $detail, $match);
			//error_log("match".print_r($match,true));
			$detail = "POINT(" . $match[0][1] . " " . $match[0][0] . ")";
		}
		//	error_log("attr = ".$attr["name"]);
		//		echo "".($attr["name"] ? $attr["name"] : $detail->getName()).": ". $detail."<br>";
		array_push($header, $detailName);
		if (("" . $detail != "") && $resourceUriRoot && ($dtyID == DT_IMAGES || $dtyID == DT_DRAWING)) {
			//			echo "has resource $inputFormResourceDir/$detail<br>";
			//error_log("Photo magic= ".DT_IMAGES."  dtyID= $dtyID");
			//error_log("Sketch magic= ".DT_DRAWING."  dtyID= $dtyID");
			if (array_key_exists($detail, $resources)) {
				array_push($dataRow, $resourceUriRoot . $resources[$detail]);
			} else {
				array_push($dataRow, "resource file $detail not found");
			}
		} else {
			array_push($dataRow, $detail);
		}
	}
	//	echo  "scratchPad: ".$fhmlDoc->asXML()."<br><br>";
	array_push($header, "scratchPad");
	$fhmlDoc->addChild('filename', $fhmlFilename);
	$fhml = $fhmlDoc->asXML();
	$fhml = preg_replace("/^\<\?xml[^\?]+\?\>/", "", $fhml);
	$fhml = "\"" . preg_replace("/\"/", "'", $fhml) . "\"";
	$fhml = "\"" . preg_replace("/\</", "&lt;", $fhml) . "\"";
	$fhml = "\"" . preg_replace("/\>/", "&gt;", $fhml) . "\"";
	array_push($dataRow, $fhml);
	array_push($header, "importFilename");
	array_push($dataRow, $fhmlFilename);
	//	echo join(",", $header);
	//	echo "<br>";
	//	echo join(",", $dataRow);
	//	echo "<br>";
	//	echo "<br>";
	return array($recType[0], join(",", $header), join(",", $dataRow));
}
/**
* simple description
* detailed desription
* @global       type description of global variable usage in a function
* @param        type [$varname] description
* @return       type description
* @link         URL
* @see          name of another element (function or object) used in this function
* @throws       list of exceptions thrown in this code
*/
function loadRemoteURLContent($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the output as a string from curl_exec
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 0);
	curl_setopt($ch, CURLOPT_HEADER, 0); //don't include header in output
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // follow server header redirects
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // don't verify peer cert
	curl_setopt($ch, CURLOPT_TIMEOUT, 10); // timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // no more than 5 redirections
	if (defined("HEURIST_HTTP_PROXY")) {
		curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	$data = curl_exec($ch);
	$error = curl_error($ch);
	if ($error) {
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		echo "$error ($code)" . " url = " . $url;
		return false;
	} else {
		return $data;
	}
}
/**
* simple description
* detailed desription
* @global       type description of global variable usage in a function
* @param        type [$varname] description
* @return       type description
* @link         URL
* @see          name of another element (function or object) used in this function
* @throws       list of exceptions thrown in this code
*/
function findRecorder($val) {
	$recorderMap = array("278" => "13862", "279" => "13867", "280" => "13873", "276" => "13875", "277" => "13876", "14" => "13871",
						"5" => "13877", "10" => "13868", "7" => "13879", "11" => "13869", "2" => "13870", "1" => "13863", "12" => "13865",
						"3" => "13872", "9" => "13866", "4" => "13861", "41" => "13864", "6" => "13874", "13" => "13878", "10-3504" => "13871",
						"10-3495" => "13877", "10-3500" => "13868", "10-3497" => "13879", "10-3501" => "13869", "10-3492" => "13870",
						"10-3491" => "13863", "10-3502" => "13865", "10-3493" => "13872", "10-3499" => "13866", "10-3494" => "13861",
						"10-3505" => "13864", "10-3496" => "13874", "10-3503" => "13878");
	if (preg_match("/\"/", $val)) {
		$val = preg_replace("/\"/", "", $val); //strip any quotes for non string values

	}
	return (array_key_exists("" . $val, $recorderMap) ? $recorderMap["" . $val] : "unknown recorder - '" . $val . "'");
}
/**
* simple description
* detailed desription
* @global       type description of global variable usage in a function
* @param        type [$varname] description
* @return       type description
* @link         URL
* @see          name of another element (function or object) used in this function
* @throws       list of exceptions thrown in this code
*/
function findSurveyUnit($val) {
	$suMap = array("246" => "13881", "247" => "13882", "249" => "13883", "250" => "13884", "251" => "13885", "252" => "13886",
					"253" => "13887", "254" => "13888", "255" => "13889", "256" => "13890", "257" => "13891", "258" => "13892",
					"259" => "13893", "260" => "13894", "261" => "13895", "262" => "13896", "263" => "13897", "264" => "13898",
					"265" => "13899", "266" => "13900", "267" => "13901", "268" => "13902", "269" => "13903", "270" => "13904",
					"271" => "13905", "272" => "13906", "273" => "13907", "274" => "13908", "275" => "13909", "281" => "13910",
					"282" => "13911", "283" => "13912", "284" => "13913", "285" => "13914", "286" => "13915", "287" => "13916",
					"288" => "13917", "289" => "13918", "290" => "13919", "291" => "13920", "292" => "13921", "293" => "13922",
					"294" => "13923", "295" => "13924", "296" => "13925", "297" => "13926", "298" => "13927", "299" => "13928",
					"300" => "13929", "301" => "13930", "302" => "13931", "303" => "13932", "304" => "13933", "305" => "13934",
					"306" => "13935", "307" => "13936", "308" => "13937", "309" => "13938", "310" => "13939", "311" => "13940",
					"312" => "13941", "313" => "13942", "314" => "13943", "315" => "13944", "316" => "13945", "317" => "13946",
					"318" => "13947", "319" => "13948", "320" => "13949", "321" => "13950", "322" => "13951", "323" => "13952",
					"324" => "13953", "325" => "13954", "326" => "13955", "327" => "13956", "328" => "13957", "329" => "13958",
					"330" => "13959", "331" => "13960", "332" => "13961", "333" => "13962", "334" => "13963", "335" => "13964",
					"336" => "13965", "337" => "13966", "338" => "13967", "339" => "13968", "340" => "13969", "341" => "13970",
					"342" => "13971", "343" => "13972", "344" => "13973", "345" => "13974", "346" => "13975", "347" => "13976",
					"348" => "13977", "349" => "13978", "350" => "13979", "125" => "13980", "126" => "13981", "127" => "13982",
					"128" => "13983", "129" => "13984", "115" => "13985", "130" => "13986", "116" => "13987", "131" => "13988",
					"117" => "13989", "132" => "13990", "118" => "13991", "133" => "13992", "119" => "13993", "134" => "13994",
					"120" => "13995", "235" => "13996", "230" => "13997", "240" => "13998", "135" => "13999", "121" => "14000",
					"236" => "14001", "231" => "14002", "241" => "14003", "136" => "14004", "122" => "14005", "237" => "14006",
					"232" => "14007", "242" => "14008", "137" => "14009", "123" => "14010", "238" => "14011", "233" => "14012",
					"243" => "14013", "138" => "14014", "124" => "14015", "239" => "14016", "234" => "14017", "244" => "14018",
					"62" => "14019", "63" => "14020", "57" => "14021", "52" => "14022", "47" => "14023", "42" => "14024", "64" => "14025",
					"58" => "14026", "53" => "14027", "48" => "14028", "43" => "14029", "65" => "14030", "59" => "14031", "54" => "14032",
					"49" => "14033", "44" => "14034", "66" => "14035", "60" => "14036", "55" => "14037", "50" => "14038", "45" => "14039",
					"67" => "14040", "61" => "14041", "56" => "14042", "51" => "14043", "46" => "14044", "175" => "14045", "166" => "14046",
					"157" => "14047", "148" => "14048", "139" => "14049", "176" => "14050", "167" => "14051", "158" => "14052", "149" => "14053",
					"140" => "14054", "177" => "14055", "168" => "14056", "159" => "14057", "150" => "14058", "141" => "14059", "178" => "14060",
					"169" => "14061", "160" => "14062", "151" => "14063", "142" => "14064", "179" => "14065", "170" => "14066", "161" => "14067",
					"152" => "14068", "143" => "14069", "180" => "14070", "171" => "14071", "162" => "14072", "153" => "14073", "144" => "14074",
					"181" => "14075", "172" => "14076", "163" => "14077", "154" => "14078", "145" => "14079", "182" => "14080", "173" => "14081",
					"164" => "14082", "155" => "14083", "146" => "14084", "183" => "14085", "174" => "14086", "165" => "14087", "156" => "14088",
					"147" => "14089", "114" => "14090", "113" => "14091", "112" => "14092", "92" => "14093", "86" => "14094", "80" => "14095",
					"74" => "14096", "68" => "14097", "93" => "14098", "87" => "14099", "81" => "14100", "75" => "14101", "69" => "14102",
					"94" => "14103", "88" => "14104", "82" => "14105", "76" => "14106", "70" => "14107", "95" => "14108", "89" => "14109",
					"83" => "14110", "77" => "14111", "71" => "14112", "96" => "14113", "90" => "14114", "84" => "14115", "78" => "14116",
					"72" => "14117", "97" => "14118", "91" => "14119", "85" => "14120", "79" => "14121", "73" => "14122", "208" => "14123",
					"202" => "14124", "196" => "14125", "190" => "14126", "184" => "14127", "209" => "14128", "203" => "14129", "197" => "14130",
					"191" => "14131", "185" => "14132", "210" => "14133", "204" => "14134", "198" => "14135", "192" => "14136", "186" => "14137",
					"211" => "14138", "205" => "14139", "199" => "14140", "193" => "14141", "187" => "14142", "212" => "14143", "206" => "14144",
					"200" => "14145", "194" => "14146", "188" => "14147", "213" => "14148", "207" => "14149", "201" => "14150", "195" => "14151",
					"189" => "14152", "103" => "14153", "98" => "14154", "15" => "14155", "108" => "14156", "104" => "14157", "99" => "14158",
					"30" => "14159", "24" => "14160", "16" => "14161", "109" => "14162", "105" => "14163", "100" => "14164", "31" => "14165",
					"25" => "14166", "17" => "14167", "110" => "14168", "106" => "14169", "101" => "14170", "32" => "14171", "26" => "14172",
					"18" => "14173", "111" => "14174", "107" => "14175", "102" => "14176", "33" => "14177", "27" => "14178", "19" => "14179",
					"225" => "14180", "220" => "14181", "214" => "14182", "34" => "14183", "28" => "14184", "20" => "14185", "226" => "14186",
					"221" => "14187", "215" => "14188", "29" => "14189", "21" => "14190", "227" => "14191", "222" => "14192", "216" => "14193",
					"22" => "14194", "228" => "14195", "223" => "14196", "217" => "14197", "23" => "14198", "229" => "14199", "224" => "14200",
					"218" => "14201", "219" => "14202", "39" => "14203", "35" => "14204", "40" => "14205", "36" => "14206", "37" => "14207",
					"38" => "14208", "10-3511" => "14153", "10-3506" => "14154", "10-3464" => "14155", "10-3516" => "14156", "10-3512" => "14157",
					"10-3507" => "14158", "10-3479" => "14159", "10-3473" => "14160", "10-3465" => "14161", "10-3517" => "14162",
					"10-3513" => "14163", "10-3508" => "14164", "10-3480" => "14165", "10-3474" => "14166", "10-3466" => "14167",
					"10-3518" => "14168", "10-3514" => "14169", "10-3509" => "14170", "10-3481" => "14171", "10-3475" => "14172",
					"10-3467" => "14173", "10-3519" => "14174", "10-3515" => "14175", "10-3510" => "14176", "10-3482" => "14177",
					"10-3476" => "14178", "10-3468" => "14179", "10-3483" => "14183", "10-3477" => "14184", "10-3469" => "14185",
					"10-3478" => "14189", "10-3470" => "14190", "10-3471" => "14194", "10-3472" => "14199", "10-3488" => "14203",
					"10-3484" => "14204", "10-3489" => "14205", "10-3485" => "14206", "10-3486" => "14207", "10-3487" => "14208");
	if (preg_match("/\"/", $val)) {
		$val = preg_replace("/\"/", "", $val); //strip any quotes for non string values

	}
	return (array_key_exists("" . $val, $suMap) ? $suMap["" . $val] : "unknown survey Unit - '" . $val . "'");
}
/**
* simple description
* detailed desription
* @global       type description of global variable usage in a function
* @param        type [$varname] description
* @return       type description
* @link         URL
* @see          name of another element (function or object) used in this function
* @throws       list of exceptions thrown in this code
*/
function returnXMLSuccessMsgPage($msg) {
	die("<html><body><success>$msg</success></body></html>");
}
/**
* simple description
* detailed desription
* @global       type description of global variable usage in a function
* @param        type [$varname] description
* @return       type description
* @link         URL
* @see          name of another element (function or object) used in this function
* @throws       list of exceptions thrown in this code
*/
function returnXMLErrorMsgPage($msg) {
	die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}
