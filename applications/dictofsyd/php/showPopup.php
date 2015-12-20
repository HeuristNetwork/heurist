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
* OLD WAY of page generation. WORKING, but NOT USED
*
* It loads XML from Heurist instance (PATH HARDCODED!!!) and applies XSLT
*
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/

require_once("utilsFile.php");

/**
* loads xml and applies xslt
*
* @param mixed $rec_id
* @param mixed $is_cache - 0 not use cache, 1 - use xml cache, 2 - use html cache
*/
function showPopup($rec_id, $is_cache=1){

	$out = null;
	$k=strpos($rec_id,"c");
	if($k>0){
		$rec_id = substr($rec_id,0,$k);
	}

	if(is_numeric($rec_id)){

		$filename_html = dirname(__FILE__)."/../popup/".$rec_id.".html";

		if($is_cache==2 && file_exists($filename_html)){

			header('Content-type: text/html');
			readfile($filename_html);

		}else{
			$filename = dirname(__FILE__)."/../popup/".$rec_id.".xml";
			$xml = new DOMDocument();

			if($is_cache>0 && file_exists($filename)){

					$xml->load($filename);

			}else{
				$url = "http://heuristscholar.org/h3-ij/export/xml/flathml.php?w=all&a=1&depth=1&fc=-1&q=ids:".$rec_id."&db=dos_1&rtfilters={\"1\":[\"24\"]}";  //contributor only
				$data = loadRemoteURLContentWithRange($url, null);
				if($data){
					$xml->loadXML($data);
					if($is_cache>0){
						saveAsFile($data, $filename);
					}
				}
			}

			if($xml){

				$xsl = new DOMDocument;
				$xsl->load(dirname(__FILE__)."/../xsl/popup.xsl");

				$proc = new XSLTProcessor();
				$proc->importStyleSheet($xsl);

				$out = $proc->transformToXML($xml);

				if($is_cache==2){
					saveAsFile($out, $filename_html);
				}

				echo $out;


				/*
				if(true){ //is entry
					$xml = $proc->transformToXML($xml);

					//$proc = new XSLTProcessor();
					//$xsl->load('xsl/tei_to_html_basic.xsl');
					//$proc->importStyleSheet($xsl);
					//echo $proc->transformToXML($xml);

					echo $xml;

				}else{
				}
				*/
			}
		}
	}

	return $out;
}
?>
