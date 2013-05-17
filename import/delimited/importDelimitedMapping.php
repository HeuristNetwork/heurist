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
* importRecordsFromDelimited.php
* save, load mappings for csv import
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
    * @param includeUgrps=1 will output user and group information in addition to definitions
    * @param approvedDefsOnly=1 will only output Reserved and Approved definitions
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


define('ISSERVICE',1);

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
	/*****DEBUG****///error_log(">>>>>>>>>>>>>".print_r($results, true));

	//comma separated list of filenames
	print implode("|", $results);
}


?>