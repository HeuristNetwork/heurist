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
* OLDWAY. NOT USED
*
* Used in entry.xsl for wml to html transfer
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

header("Content-type: text/xml");

//error_reporting(E_ALL);
//display_errors(1);

require_once("utilsFile.php");

$xml = new DOMDocument();

if(@$_REQUEST['id']){
	$rec_id = $_REQUEST['id'];

	$filename = "../cache/wml_".$rec_id.".xml";

	if(file_exists($filename)){

		$xml->load($filename);

	}else{
		$url = "http://heuristscholar.org/h3-ao/records/files/downloadFile.php?db=dos_1&ulf_ID=".$rec_id;
		$data = loadRemoteURLContentWithRange($url, null);
		if($data){
			$xml->loadXML($data);
			saveAsFile($data, $filename);
		}
	}

	if($xml){

		$xsl = new DOMDocument;
		$xsl->load(dirname(__FILE__)."/../xsl/wordml2TEI.xsl");

		$proc = new XSLTProcessor();
		$proc->importStyleSheet($xsl);

		$xml->loadXML($proc->transformToXML($xml));
		$xsl->load(dirname(__FILE__)."/../xsl/tei_to_html_basic2.xsl");
		$proc->importStyleSheet($xsl);

		echo $proc->transformToXML($xml);
	}
}
?>