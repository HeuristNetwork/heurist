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
require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');

if (!is_logged_in()) {
	return;
}
error_log("made it to here");
mysql_connection_db_select(DATABASE);

$transformID = @$_REQUEST['transID'] ? $_REQUEST['transID'] : null;
//if no style given then try default, if default doesn't exist we output raw xml

if ($transformID) {
	//read Record information to find transform and outputname
		returnXMLErrorMsgPage("Transform ids not working at this time id='$transformID'");
	// check record(transformID) is RT_TRANSFORM type
	// check for fileresources / template files load template(s) (local or remote)
	// and/or check for local transform then check transform type and dispatch to appropriate transform service

}else {
	//check transform style exist in standard directory
	$style = @$_REQUEST['style'] ? $_REQUEST['style'] : 'default';
	if ( @$_REQUEST['styleFilename']) {
		$styleFilename = $_REQUEST['styleFilename'];
	}else{
		$styleFilename = "".HEURIST_XSL_TEMPLATES_DIR.$style.".xsl";
		//set the style filename and check that it exist
		if (!file_exists($styleFilename)) {
			returnXMLErrorMsgPage("unable to find $style file at '$styleFilename'");
		}
	}
}
//error_log("made it to here 2");

//error_log("styleFilename - ".print_r($styleFilename,true));
if (@$_REQUEST['inputFilename']){
	if (preg_match("/http/",$_REQUEST['inputFilename'])) {
//		returnXMLErrorMsgPage("Remote inputs are not supported at this time '".$_REQUEST['inputFilename']."'");
		$inputFilename = $_REQUEST['inputFilename'];
	}else{
		$inputFilename = "".HEURIST_HML_PUBPATH.$_REQUEST['inputFilename'];
		if ( !file_exists($inputFilename)) {
			returnXMLErrorMsgPage("unable to find input file '$inputFilename'");
		}
	}
}else if (@$_REQUEST['q']) {
	$inputFilename = HEURIST_BASE_URL."export/xml/flathml.php?ver=1&f=1".
								(@$_REQUEST['depth'] ? "&depth=".$_REQUEST['depth']:"").
								(@$_REQUEST['hinclude'] ? "&hinclude=".$_REQUEST['hinclude']:"").
								(@$_REQUEST['selids'] ? "&selids=".$_REQUEST['selids']:"").
								"&w=all&q=".$_REQUEST['q']."&db=".HEURIST_DBNAME.
								(@$_REQUEST['outputFilename'] ? "&filename=".$_REQUEST['outputFilename'] :"").
								($outFullName && $_REQUEST['debug']? "&pathfilename=".$outFullName :"");
}else if (@$_REQUEST['recID']){
	$inputFilename = "".HEURIST_HML_PUBPATH.HEURIST_DBID."-".$_REQUEST['recID'].".hml";
	if ( !file_exists($inputFilename)) {
		returnXMLErrorMsgPage("unable to find input file '$inputFilename'");
	}
}
//error_log("input file name = $inputFilename");

if (!$inputFilename ) {
	returnXMLErrorMsgPage("cannot determine input file. Please sepecify 'inputFilename' or 'recID' or query 'q='");
}

if (@$_REQUEST['outputFilename']){
	if (preg_match("/http/",$_REQUEST['outputFilename'])) {
		returnXMLErrorMsgPage("Remote outputs are not supported at this time '".$_REQUEST['outputFilename']."'");
	}
	$outputFilename = "".HEURIST_HTML_PUBPATH.$_REQUEST['outputFilename'];
}else if (@$_REQUEST['recID']){
	$outputFilename = "".HEURIST_HTML_PUBPATH.HEURIST_DBID.$style."-".HEURIST_DBID."-".$_REQUEST['recID'].".html";
}
//error_log("output file name = $outputFilename");


$pos = strpos(HEURIST_HTML_PUBPATH,HEURIST_DOCUMENT_ROOT);
if ($pos !== false || file_exists(HEURIST_DOCUMENT_ROOT.HEURIST_HTML_PUBPATH)){
	$outputURI = 'http://'.HEURIST_HOST_NAME.
						( $pos !== false ? substr(HEURIST_HTML_PUBPATH,$pos + strlen(HEURIST_DOCUMENT_ROOT)) : HEURIST_HTML_PUBPATH).
							(@$_REQUEST['outputFilename'] ? $_REQUEST['outputFilename'] :
								(@$_REQUEST['recID'] ? $style."-".HEURIST_DBID."-".$_REQUEST['recID'].".html" : "unknown.html"));
}
//error_log("output UIR = $outputURI");

saveTransformOutput($inputFilename,$styleFilename,$outputFilename);

function loadRemoteFile($filename){

	$file_content = loadRemoteURLContent($filename);
	if(!$file_content){
		returnXMLErrorMsgPage("Error loading remote file '$filename'");
	}

	return $file_content;
}

function saveTransformOutput($recHMLFilename, $styleFilename, $outputFilename){
global $outputURI;
	$recHmlDoc = new DOMDocument();
	if (preg_match("/http/",$recHMLFilename)) {
		$suc = $recHmlDoc->loadXML( loadRemoteFile($recHMLFilename));
	}else{
		$suc = $recHmlDoc->load($recHMLFilename);
	}
	if (!$suc){
			returnXMLErrorMsgPage("Unable to load file $recHMLFilename");
	}
	$recHmlDoc->xinclude();//todo write code here to squash xincludes down to some limit.
	if (!$styleFilename) {
		if(!$outputFilename){
			returnXMLErrorMsgPage("No transform filename or outputFilename provided for $recHMLFilename");
		}
		$cntByte = $recHmlDoc->saveHTMLFile($outputFilename);
		if ($cntByte>0) {
			returnXMLSuccessMsgPage("Successfully wrote $cntByte bytes of untransformed file $recHMLFilename to $outputFilename");
		}else{
			returnXMLErrorMsgPage("Unable to output untransformed file $recHMLFilename to $outputFilename");
		}
	}else{
		$xslDoc = new DOMDocument();
		if (preg_match("/http/",$styleFilename)) {
			$suc = $xslDoc->loadXML( loadRemoteFile($styleFilename));
		}else{
			$suc = $xslDoc->load($styleFilename);
		}
		if (!$suc){
				returnXMLErrorMsgPage("Unable to load XSLT transform file $styleFilename");
		}
	}
	$xslProc = new XSLTProcessor();
	$xslProc->importStylesheet($xslDoc);
// set up common parameters for stylesheets.
	$xslProc->setParameter('','hbaseURL',HEURIST_BASE_URL);
	$xslProc->setParameter('','dbName',HEURIST_DBNAME);
	$xslProc->setParameter('','dbID',HEURIST_DBID);
	$xslProc->setParameter('','transform',$styleFilename);
	$xslProc->setParameter('','standalone','1');
	if($outputFilename){
		$cntByte = $xslProc->transformToURI($recHmlDoc,$outputFilename);
		if ($cntByte > 0) {
			returnXMLSuccessMsgPage("Successfully wrote $cntByte bytes of $recHMLFilename transformed by  $styleFilename to $outputFilename".
									($outputURI?" <a href=\"$outputURI\" target=\"_blank\">$outputURI</a>":
									"Unable to determine URI for $outputFilename because is does not match website path!"));
		}else{
			returnXMLErrorMsgPage("Unable to  transform and/or output file $recHMLFilename transformed by  $styleFilename to $outputFilename");
		}
	}else{
//		$xmlString = $xslProc->transformToXML($recHmlDoc);
		echo $xslProc->transformToXML($recHmlDoc);
	}
}

function returnXMLSuccessMsgPage($msg) {
	die("<html><body><success>$msg</success></body></html>");
}
function returnXMLErrorMsgPage($msg) {
	die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}
