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
* service to use transforms with the flathml version of a query to generate and xml out stored
*
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Export/xml
*/


//header('Content-type: text/xml; charset=utf-8');

// called by applyCredentials require_once(dirname(__FILE__).'/../../common/config/initialise.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');

//error_log("made it to here");
mysql_connection_select(DATABASE);

$verbose = @$_REQUEST['v'] && $_REQUEST['v']==0 ? false : true;
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
if (@$_REQUEST['inputFilename']){// get a saved XML file
	//if URI then treat as remote
	if (preg_match("/http/",$_REQUEST['inputFilename'])) {
//		returnXMLErrorMsgPage("Remote inputs are not supported at this time '".$_REQUEST['inputFilename']."'");
		$inputFilename = $_REQUEST['inputFilename'];
	}else{//local filename so assume in HML publication directory
		$inputFilename = "".HEURIST_HML_PUBPATH.$_REQUEST['inputFilename'];
		if ( !file_exists($inputFilename)) {
			returnXMLErrorMsgPage("unable to find input file '$inputFilename'");
		}
	}
}else if (@$_REQUEST['q']) {//get input file from service call for query.
	$inputFilename = HEURIST_BASE_URL."export/xml/flathml.php?ver=1&f=1".
								(@$_REQUEST['depth'] ? "&depth=".$_REQUEST['depth']:"").
								(@$_REQUEST['hinclude'] ? "&hinclude=".$_REQUEST['hinclude']:"").
								(@$_REQUEST['layout'] ? "&layout=".$_REQUEST['layout']:"").
								(@$_REQUEST['prtfilters'] ? "&prtfilters=".$_REQUEST['prtfilters']:"").
								(@$_REQUEST['rtfilters'] ? "&rtfilters=".$_REQUEST['rtfilters']:"").
								(@$_REQUEST['relfilters'] ? "&relfilters=".$_REQUEST['relfilters']:"").
								(@$_REQUEST['selids'] ? "&selids=".$_REQUEST['selids']:"").
								"&w=all&pubonly=1&q=".$_REQUEST['q']."&db=".HEURIST_DBNAME.
								(@$_REQUEST['outputFilename'] ? "&filename=".$_REQUEST['outputFilename'] :"").
								(@$outFullName && $_REQUEST['debug']? "&pathfilename=".$outFullName :"");
}else if (@$_REQUEST['recID']){//recID so assume that the file has been prepublished to the HML Publish directory
	$inputFilename = "".HEURIST_HML_PUBPATH.HEURIST_DBID."-".$_REQUEST['recID'].".hml";
	if ( !file_exists($inputFilename)) {
		returnXMLErrorMsgPage("unable to find input file '$inputFilename'");
	}
}
//error_log("input file name = $inputFilename");

if (!$inputFilename ) {
	returnXMLErrorMsgPage("cannot determine input file. Please sepecify 'inputFilename' or 'recID' or query 'q='");
}

if (@$_REQUEST['outputFilename']){//filename supplied so use it
	if (preg_match("/http/",$_REQUEST['outputFilename'])) {
		returnXMLErrorMsgPage("Remote outputs are not supported at this time '".$_REQUEST['outputFilename']."'");
	}
	$outputFilename = "".HEURIST_HTML_PUBPATH.$_REQUEST['outputFilename'];
}else if (@$_REQUEST['recID']){//recID so use naming algorythm
	$outputFilename = "".HEURIST_HTML_PUBPATH.HEURIST_DBID.$style."-".HEURIST_DBID."-".$_REQUEST['recID'].".html";
}
//error_log("output file name = $outputFilename");

//caclulate URI to output.
$pos = strpos(HEURIST_HTML_PUBPATH,HEURIST_DOCUMENT_ROOT);
if ($pos !== false || file_exists(HEURIST_DOCUMENT_ROOT.HEURIST_HTML_PUBPATH)){
	$outputURI = 'http://'.HEURIST_SERVER_NAME.
						( $pos !== false ? substr(HEURIST_HTML_PUBPATH,$pos + strlen(HEURIST_DOCUMENT_ROOT)) : HEURIST_HTML_PUBPATH).
							(@$_REQUEST['outputFilename'] ? $_REQUEST['outputFilename'] :
								(@$_REQUEST['recID'] ? $style."-".HEURIST_DBID."-".$_REQUEST['recID'].".html" : "unknown.html"));
}
//error_log("output UIR = $outputURI");

saveTransformOutput($inputFilename,$styleFilename,@$outputFilename);

function loadRemoteFile($filename){

	$file_content = loadRemoteURLContent($filename,true);
	if(!$file_content){
		returnXMLErrorMsgPage("Error loading remote file '$filename'");
	}

	return $file_content;
}

function saveTransformOutput($recHMLFilename, $styleFilename, $outputFilename = null){
global $outputURI;
	$recHmlDoc = new DOMDocument();
	if (preg_match("/http/",$recHMLFilename)) {
//error_log("hml = ".loadRemoteFile($recHMLFilename));
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
		if (is_logged_in()) {
			$cntByte = $recHmlDoc->saveHTMLFile($outputFilename);
		}
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
	if($outputFilename && is_logged_in()){
		$cntByte = $xslProc->transformToURI($recHmlDoc,$outputFilename);
		if ($cntByte > 0) {
			returnXMLSuccessMsgPage("Successfully wrote $cntByte bytes of $recHMLFilename transformed by  $styleFilename to $outputFilename".
									($outputURI?" <a href=\"$outputURI\" target=\"_blank\">$outputURI</a>":
									"Unable to determine URI for $outputFilename because is does not match website path!"));
		}else{
			returnXMLErrorMsgPage("Unable to  transform and/or output file $recHMLFilename transformed by  $styleFilename to $outputFilename");
		}
	}else{
		$doc = $xslProc->transformToDoc($recHmlDoc);
//		echo $xslProc->transformToXML($recHmlDoc);
		echo $doc->saveHTML();
	}
}

function returnXMLSuccessMsgPage($msg) {
	global $verbose;
    if (@$verbose) {
	    die("<html><body><success>$msg</success></body></html>");
    }else{
      error_log("successful transform ".$msg);
    }
}
function returnXMLErrorMsgPage($msg) {
	global $verbose;
	if (@$verbose) {
        die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
    }
   error_log("errored transform ".$msg);
}
