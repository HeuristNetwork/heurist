<?php

/* importRecordsFromDelimited.php - save, load mappings for csv import
 * Ian Johnson Artem Osmakov 18 Nov 2011
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
	* @param includeUgrps=1 will output user and group information in addition to definitions
	* @param approvedDefsOnly=1 will only output Reserved and Approved definitions
 * @todo
*/
require_once(dirname(__FILE__).'/../../common/config/initialise.php');

header("Content-type: text/javascript");

$folder = HEURIST_UPLOAD_DIR."settings/";

if(!file_exists($folder)){
	if (!mkdir($folder, 0777, true)) {
    	die('Failed to create folder for settings');
	}
}

if(@$_REQUEST['mode'] && $_REQUEST['mode']=='list'){

	getList();

}else if(@$_REQUEST['mode'] && @$_REQUEST['file'] && $_REQUEST['mode']=='save'){

	$filename = $folder."importcsv_".$_REQUEST['file'].".map";

	$content = $_REQUEST['content'];

	file_put_contents($filename, $content);

	print "Mapping has been saved";


}else if(@$_REQUEST['mode'] && @$_REQUEST['file'] && $_REQUEST['mode']=='load'){

	$filename = $folder."importcsv_".$_REQUEST['file'].".map";

	if(file_exists($filename)){
		//readcontent and send to client side
		print file_get_contents($filename);
	}else{
		print "Error: config file does not exist";
	}
}

exit();

/**
* Returns the list of available tempaltes as json array
*/
function getList(){

	global $folder;

	$files = scandir($folder);
	$results = array();

	foreach ($files as $filename){
		//$ext = strtolower(end(explode('.', $filename)));
		//$ext = preg_replace('/^.*\.([^.]+)$/D', '$1', $filename);

		$path_parts = pathinfo($filename);
		$ext = strtolower($path_parts['extension']);
/*****DEBUG****///error_log(">>>>".$path_parts['filename']."    ".$filename.indexOf("_")."<<<<");
		$ind = strpos($filename,"importcsv_");
        $isok = (is_numeric($ind) && $ind==0);

		if(file_exists($folder.$filename) && $ext=="map" && $isok)
		{
			array_push($results, substr($path_parts['filename'],10) );
		}
	}
	header("Content-type: text/javascript");
	//header('Content-type: text/html; charset=utf-8');
	/*****DEBUG****////*****DEBUG****/error_log(">>>>>>>>>>>>>".print_r($results, true));

	//comma separated list of filenames
	print implode("|", $results);
}


?>