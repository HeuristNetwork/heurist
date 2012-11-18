<?php

/*<!--publishRecordHML.php
 * configIni.php - Configuration information for Heurist Initialization - USER EDITABLE
 * @version $Id$
 * @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 *

	Copyright 2005 - 2010 University of Sydney Digital Innovation Unit
	This file is part of the Heurist academic knowledge management system (http://HeuristScholar.org)
	mailto:info@heuristscholar.org

	Concept and direction: Ian Johnson.
	Developers: Tom Murtagh, Kim Jackson, Steve White, Steven Hayes,
				Maria Shvedova, Artem Osmakov, Maxim Nikitin.
	Design and advice: Andrew Wilson, Ireneusz Golka, Martin King.

	Heurist is free software; you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation; either version 3
	of the License, or (at your option) any later version.

	Heurist is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
	even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.
	If not, see <http://www.gnu.org/licenses/>
	or write to the Free Software Foundation,Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

	-->*/



//header('Content-type: text/xml; charset=utf-8');

// called by applyCredentials require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
if (!is_logged_in()) {
	return;
}

mysql_connection_db_select(DATABASE);

// set parameter defaults
$inputFormInstanceDir = @$_REQUEST['inputFilepath'];	// outName returns the hml direct.
$inputFormResourceDir = @$_REQUEST['resourceFilepath'];	// outName returns the hml direct.
//if no style given then try default, if default doesn't exist we our put raw xml
$info = new SplFileInfo($inputFormInstanceDir);
if ($info->isDir()) {
	$iterator = new DirectoryIterator($inputFormInstanceDir);
	foreach ($iterator as $fileinfo) {
		if($fileinfo->isFile()){
			echo parseImportForm($fileinfo->getFilename());
		}
//		break;
	}
}else{
	echo "something is wrong";
}
return;



function parseImportForm($fhmlFilename){
	global $inputFormResourceDir, $inputFormInstanceDir;
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


	$fhmlDoc = simplexml_load_file($inputFormInstanceDir."/".$fhmlFilename);
	$header = array();
	$dataRow = array();
	echo "<b>".$fhmlFilename."</b>"."<br>";
	$recType = $fhmlDoc->xpath("/fhml/records/record/type/label");
	// echo "rectyp: ".$recType[0]."<br>";
	array_push($header,"rectype");
	array_push($dataRow,$recType[0]);
	$nonce = $fhmlDoc->xpath("/fhml/records/record/nonce");
//	echo "nonce: ".$nonce[0]."<br>";
	array_push($header,"nonce");
	array_push($dataRow,$nonce[0]);
//	error_log("nonce = ".print_r($nonce,true));
	$database = $fhmlDoc->xpath("/fhml/database");
	$database = $database[0];
	$dbID = $database->attributes();
	$dbID = $dbID["id"];
	$dateStamp = $fhmlDoc->xpath("/fhml/dateStamp");
//	echo "dataStamp: ".$dateStamp[0]."<br>";
	array_push($header,"dateStamp");
	array_push($dataRow,$dateStamp[0]);
	$details = $fhmlDoc->xpath("/fhml/records/record/details");
	foreach ($details[0]->children() as $detail){
//		if ("".$detail == "") continue;
		$attr = $detail->attributes();
		$dtyID = $detail->getName();
//		error_log("dtyID ".print_r($dtyID,true));
		$dtyID = preg_replace("/dt/","",$dtyID);
//		error_log("dtyID ".print_r($dtyID,true));
		$detailName = ($attr["name"] ? $attr["name"] : $detail->getName());
		$detail = preg_replace("/\"/","",$detail);
		$dbConceptPrefix = $dbID."-";
//error_log("detailtype ".print_r($dettypes[intval($dtyID)],true));
		$dtBaseType = $dettypes[intval($dtyID)]['commonFields'][$di['dty_Type']];
//		error_log("basetype = ".print_r($dtBaseType,true));
		if ($dtBaseType == "enum" || $dtBaseType == 'relation') {
			$detail = preg_replace("/$dbConceptPrefix/","",$detail);
		}else if ($dtBaseType == 'geo'){
			preg_match_all("/\d+\.\d+/",$detail,$match);
//error_log("match".print_r($match,true));
			$detail = "POINT(".$match[0][1]." ".$match[0][0].")";
		}
//	error_log("attr = ".$attr["name"]);
//		echo "".($attr["name"] ? $attr["name"] : $detail->getName()).": ". $detail."<br>";
		array_push($header,$detailName);
		if (("".$detail != "") && $inputFormResourceDir && ($detailName == "Photo" || $detailName == "Sketch")){
//			echo "has resource $inputFormResourceDir/$detail<br>";
			array_push($dataRow,$inputFormResourceDir."/".$detail);
		}else{
			array_push($dataRow,$detail);
		}
	}
//	echo  "scratchPad: ".$fhmlDoc->asXML()."<br><br>";
	array_push($header,"scratchPad");
	$fhmlDoc->addChild('filename', $fhmlFilename);
	array_push($dataRow,$fhmlDoc->asXML());
	echo join(",", $header);
	echo "<br>";
	echo join(",", $dataRow);
	echo "<br>";
	echo "<br>";
}


function loadRemoteURLContent($url) {
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

	curl_setopt($ch, CURLOPT_URL, $url);
	$data = curl_exec($ch);

	$error = curl_error($ch);
	if ($error) {
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		echo "$error ($code)" . " url = ". $url;
		return false;
	} else {
		return $data;
	}
}

function returnXMLSuccessMsgPage($msg) {
	die("<html><body><success>$msg</success></body></html>");
}
function returnXMLErrorMsgPage($msg) {
	die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}
