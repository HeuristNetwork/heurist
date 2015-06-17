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
* work in progress to output HTML pages using HML published record sets.
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
if (!is_logged_in()) {
	return;
}

mysql_connection_select(DATABASE);

// set parameter defaults
//input must be relative to HML publish directory
$inputFilename = (@$_REQUEST['inputFilename'] ? "".HEURIST_HML_DIR.$_REQUEST['inputFilename'] :
					(@$_REQUEST['recID'] ? "".HEURIST_HML_DIR.HEURIST_DBID."-".$recID.".hml":
						null));	// outName returns the hml direct.
//if no style given then try default, if default doesn't exist we our put raw xml
$style = @$_REQUEST['style'] ? $_REQUEST['style'] : 'default';
$outputFilename = (@$_REQUEST['outputFilename'] ? "".HEURIST_HTML_DIR.$_REQUEST['outputFilename'] :
					(@$_REQUEST['recID'] ? "".HEURIST_HTML_DIR.$style."-".HEURIST_DBID."-".$recID.".html":
						null));	// outName returns the hml direct.

$outputURI = HEURIST_HTML_URL . (@$_REQUEST['outputFilename'] ? $_REQUEST['outputFilename'] :
                (@$_REQUEST['recID'] ? $style."-".HEURIST_DBID."-".$_REQUEST['recID'].".html" : "unknown.html"));

if (!$inputFilename || !file_exists($inputFilename)) {
	returnXMLErrorMsgPage("unable to find input file '$inputFilename'");
}
//set the style filename and check that it exist
$styleFilename = ($style ? "".HEURIST_XSL_TEMPLATES_DIR.$style.".xsl":null);
if (!$styleFilename || !file_exists($styleFilename)) {
	returnXMLErrorMsgPage("unable to find style file '$styleFilename'");
}

loadRecordHTML($inputFilename,$styleFilename);

returnXMLSuccessMsgPage("Successfully wrote output file".
							($outputURI?" <a href=\"$outputURI\" target=\"_blank\">$outputURI</a>":"Unable to determine URI. Not in website path! $outputFilename"));


function loadRecordHTML($recHMLFilename, $styleFilename){
global $recID, $outputFilename;
	$recHmlDoc = new DOMDocument();
	$recHmlDoc ->load($recHMLFilename);
	$recHmlDoc->xinclude();
	if (!$styleFilename) {
		return $recHmlDoc->saveHTMLFile($outputFilename);
	}
	$xslDoc = DOMDocument::load($styleFilename);
	$xslProc = new XSLTProcessor();
	$xslProc->importStylesheet($xslDoc);
// set up common parameters for stylesheets.
//	$xslProc->setParameter('','hbaseURL',HEURIST_BASE_URL);
//	$xslProc->setParameter('','dbName',HEURIST_DBNAME);
//	$xslProc->setParameter('','dbID',HEURIST_DBID);
	$xslProc->setParameter('','standalone','1');
	$xslProc->transformToURI($recHmlDoc,$outputFilename);
}

function returnXMLSuccessMsgPage($msg) {
	die("<html><body><success>$msg</success></body></html>");
}
function returnXMLErrorMsgPage($msg) {
	die("<?xml version='1.0' encoding='UTF-8'?>\n<error>$msg</error>");
}
