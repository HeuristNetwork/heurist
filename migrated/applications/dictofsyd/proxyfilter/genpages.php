
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('fileUtils.php');

$baseURL = "http://dictionaryofsydney.org/";
//$localFolderCache = "/var/www/html/maritime/cache/";
// THIS NEEDS SETTING TO AN APPROPRIATE PATH
$localFolderCache = "/var/www/html/maritime/anmm/cache/";
$localFolderOrig = "/var/www/html/maritime/orig/";

//
// Change these 3 parameters
//
$needProxy = false;  //set to false if prepared pages will be on http://dictionaryofsydney.org/

//$localURL = "http://heuristscholar.org/maritime/";    // url for root folder
//$localURLCache = "http://heuristscholar.org/maritime/cache/"; //url for pages folder

$localURL = "http://dictionaryofsydney.org/anmm/";    // url for root folder
$localURLCache = "http://dictionaryofsydney.org/anmm/cache/"; //url for pages folder

/**/
$list_file = "maritime_list_4400.txt"; //enabled links
$links_string = file_get_contents($list_file);
$links_list = explode("\n", $links_string);

//image/48208  - empty!!!

$links_list2 = array('entry/artists_camps');

echo "cnt pages ".count($links_list)."<br />";

$is_remote = (@$_REQUEST['remote']!=null);

$cnt = 1;
foreach ($links_list  as $link){

	echo $cnt." ".$link;

	if($link && $link!=""){
			doLink($link);
	}

$cnt++;
	echo "<br />";

if( ($cnt % 100) == 0){
ob_flush();
flush();
}
//if($cnt>10){break;}

}//for


exit;

//
//
//
function doLink($link){
		global $baseURL, $localFolderOrig, $localFolderCache, $is_remote;


		if(!$is_remote){
			$raw = loadPage($localFolderOrig, $link);
		}else {
			$raw = null;
		}

		if($raw==null ||  strlen($raw)<10 ){
			$raw = loadRemoteURLContent($baseURL.$link);
			savePage($localFolderOrig, $link, $raw);
		}

		$raw = parsePage($raw, (strpos($link, "contributor")===0) );

		savePage($localFolderCache, $link, $raw);
}

//
//
//
function savePage($path, $link, $raw)
{
		//create filename from link
		$parts = explode("/", trim($link));

		$folder = $path.trim($parts[0])."/";
		$filename = $folder.trim($parts[1]).".html";

		if(!file_exists($folder)){
			if (!mkdir($folder, 0777, true)) {
				echo ("<h3>Warning:</h3> Unable to create folder $folder<br>");
				return;
			}
		}

		if(file_exists($filename)){
			unlink($filename);
		}
		$fp = fopen($filename, "w");
		fwrite($fp, $raw);
		fclose($fp);
}

//
//
//
function loadPage($folder, $link)
{
	$filename = $folder.$link.".html";
	if(file_exists($filename)){
		return file_get_contents($filename);
	}else{
		return null;
	}
}

//
//
//
function addClass($node, $value){
	$class = $node->getAttribute("class");

	if($class!=null){
		$value = $class." ".$value;
	}

	$node->setAttribute("class", $value);
}
//
//
//
function delClass($node, $name){
	$class = $node->getAttribute("class");

	if($class!=null && strpos($class, $name)!==false){
		$class = str_replace($name, "", $class);
		$node->setAttribute("class", $class);
	}
}
//
//
//
function hasClass($node, $name){
	$class = $node->getAttribute("class");
	return ($class && strpos($class, $name)!==false);
}
//
//
//
function setCss($node, $name, $value){

	$style =  $node->getAttribute("style");

	if($style!=null){
		//@todo - add style to existing one
		$value = $style.";".$name.":".$value.";";
	}else{
		$value = $name.":".$value.";";
	}

	$node->setAttribute("style", $value);

}
//
//
//
function isempty($href){
	return ($href==null || $href=="#" || $href=="");
}
//
//
//
function checkLink($href){

	global $links_list;


	if (isempty($href)) //(href.indexOf('browse/')==0))
	{
		return true;
	}
	if (preg_match('/^https?\:/i', $href) )
	{
		//href.match(/^https?\:/i) && !href.match(document.domain))
		return false; //external link - disable
	}

	//remove hash
	$href = explode("#",$href);
	$href = $href[0];

	//check in list of allowed links
	foreach ($links_list  as $link){
	 	if( strpos($link,$href)!==false ){
 		 	return true;
		}
	}
	return false;
	//return in_array($href, $links_list);
}


function debug($msg){
 // echo $msg;
}

//
//  MAIN
//
function parsePage($raw, $is_contributor){

global $localURL, $localURLCache, $needProxy;


//replace some text directly

	//repalce KEY for google map
	if($needProxy){
		$raw = str_replace("ABQIAAAA5wNKmbSIriGRr4NY0snaURTtHC9RsOn6g1vDRMmqV_X8ivHa_xSNBstkFn6GHErY6WRDLHcEp1TxkQ",
						"ABQIAAAAGZugEZOePOFa_Kc5QZ0UQRQUeYPJPN0iHdI_mpOIQDTyJGt-ARSOyMjfz0UjulQTRjpuNpjk72vQ3w", $raw);

		//to avoid cross domain issue - load kml via proxy
		$raw = str_replace("../kml/full/", $localURL."urlproxy.php?kml=", $raw);
	}

	//to avoid issue with relative path - use global variable baseURL
	$raw = str_replace("../js/popups.js", $localURL."popups.js", $raw);
	$raw = str_replace("../js/tooltip.js", $localURL."tooltip.js", $raw);
	$raw = str_replace("../js/highlight.js", $localURL."highlight.js", $raw);
	//$raw = str_replace("../js/browse.js", $localURL."browse.js", $raw);

	//repalce logo
	//$raw = str_replace('src="../images/img-logo.jpg"','src="'.$localURL.'img-logo.png"', $raw);
	//$raw = str_replace('alt="Dictionary of Sydney"','alt="ANMM"',$raw);

	//add base path  $('base').attr('href')
	$raw = str_replace("<head>", "<head><base href=\"http://dictionaryofsydney.org/\">", $raw);

	//inject main style and javascript
	$raw = str_replace('<script type="text/javascript" src="../jquery/jquery.js"></script>',
	'<script type="text/javascript" src="../jquery/jquery.js"></script><link type="text/css" href="'.$localURL.'maritime2.css" rel="stylesheet"><script type="text/javascript" src="'.$localURL.'maritime2.js"></script>',
	 $raw);

	//remove
	$raw = str_replace("<div id=\"browser\"></div>","",$raw);
	$raw = str_replace('<div id="browse-link">','<div id="browse-link"><a href="../">Return to map</a></div><div style="display:none;">',$raw);

	//remove not supported tags
	/*$raw = preg_replace("</?(?i:figure|graphic)(.|\n)*?>", "", $raw);*/

	if(strpos($raw, "<figure>")!==false){
		$pos = strpos($raw, "<figure>");

		$raw2 = "".$raw;
		$raw = substr($raw2, 0, $pos).substr($raw2,strpos($raw,"</figure>",$pos)+9);
	}


$dom = new DOMDocument();
$dom->loadHTML($raw);

//special for contributor -----
if($is_contributor){
	$node = $dom->getElementById('subject-list');
	if($node){
		$nodes = $node->getElementsByTagName('p');
		if($nodes && count($nodes)>0){
			foreach ($nodes as $node){
				if($node){
					setCss($node, "display", "none");
				}
			}
		}
	}
}
//----------------------------------------------------------------------- ANNOTATIONS - DYNAMIC LINKS
$elements = $dom->getElementsByTagName('script');
$toremove = array();
foreach($elements as $node){
	$txt = $node->textContent;
	if(strpos($txt, "refs.push(")!==false){
			$pos = strpos($txt, "href :");
			if($pos!==false){
					$href = substr($txt,$pos+11, strpos($txt,"\",",$pos)-$pos-11);
					if(!checkLink($href)){
						array_push($toremove, $node);
					}else{

						if(strpos($href,"#")>0){
							$hash = substr($href,strpos($href,"#"));
							$href = substr($href,0,strpos($href,"#"));
						}else{
							$hash = "";
						}

  						$href = $localURLCache.$href.".html".$hash;

						$txt = substr($txt,0,$pos+8).$href.substr($txt, strpos($txt,"\",",$pos));

						$node->removeChild($node->firstChild);
						$node->appendChild(new DOMText($txt));

//echo $node->textContent."<br>";
					}
			}
	}
}
foreach($toremove as $node){
	$node->parentNode->removeChild($node);
}
$toremove = null;

//----------------------------------------------------------------------- ALL OTHER STATIC LINKS
$elements = $dom->getElementsByTagName('a');

debug(count($elements)." ");

foreach($elements as $node){

		$href =  $node->getAttribute("href");

debug($href." => ");

		if($href && strpos($href, "#")===0){ //anchor
			addClass($node, "allowed");
debug(" allowed <br />");
			continue;
		}
		else if( $href && strpos($href, "../")===0)
		{
  			$href = substr($href, 3);

  			if($href==""){

  				//http://www.anmm.gov.au
  				$node->setAttribute("href", $localURL);  //change reference instead of dictionaryofsydney home to martime museum home
				addClass($node, "allowed");
debug($node->getAttribute("href")."<br />");
				continue;
			}else if(strpos($href, "citation/")===0){
				addClass($node, 'disabled');
				setCss($node, "display", "none");
debug(" disabled <br />");
				continue;
			}
		}


		if ( (!(isempty($href) && hasClass($node, "annotation"))) //for annotation pointer to images
			 && checkLink($href))
		{
			addClass($node, "allowed");

  			if(!isempty($href)){

  				$is_ok = true;
/*
  				if( hasClass($node, "popup") ){

  					$is_ok = false;
						//cache popup as well

echo "0 popup ".$href;
						$matches = preg_match('/\d+$/', $href);
echo "1 popup ".print_r($matches, true);
						if ($matches) {
								$is_ok = false;
								$link = "popup/".$matches[0];
echo "2 popup ".$link;
								doLink($link);
						}
				}
*/

				if($is_ok){

						if(strpos($href,"#")>0){
							$hash = substr($href,strpos($href,"#"));
							$href = substr($href,0,strpos($href,"#"));
						}else{
							$hash = "";
						}

  						$node->setAttribute("href", $localURLCache.$href.".html".$hash);
				}

debug($node->getAttribute("href")."<br />");
  				//echo "<br />";

  				// show allowed links in right-hand index
  				if( strtolower($node->parentNode->nodeName) == "li"){

  					addClass($node->parentNode, 'index-allowed');

  					$sdiv = $node->parentNode->parentNode->parentNode;
  					do{
  						$sdiv = $sdiv->previousSibling;
					}while ($sdiv && strtolower($sdiv->nodeName)!='div');

					if($sdiv && !  hasClass($sdiv, 'allowed')){
  						addClass($sdiv, 'allowed');
					}
				}

			}

		}else{
			addClass($node, "disabled");
debug(" disabled <br />");

			$txt = $node->textContent; //html()
			if(($txt=="more »") || ($txt=="full record »") || ($txt=="more &raquo;")  || ($txt=="full record &raquo;")){
				setCss($node, "display", "none");
			}

		}


}//for

return $dom->saveHTML();

}


?>