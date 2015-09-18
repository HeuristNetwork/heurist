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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/


define('ISSERVICE',1);

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

if (! is_logged_in()) return;

$_REQUEST = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

/* Takes a single parameter -- uploadID
 * and returns data about the upload in progress
 */

if (! $_REQUEST["uploadIDs"]  &&  $_REQUEST["uploadID"])
	$uploadIDs = array($_REQUEST["uploadID"]);
else if ($_REQUEST["uploadIDs"])
	$uploadIDs = $_REQUEST["uploadIDs"];

if (! $uploadIDs) {
	print '{\"error\": "Internal upload error"}';	// ??!
	return;
}

header("Content-type: text/javascript");

$infos = array();
foreach ($uploadIDs as $uploadID) {
    
    if(function_exists('uploadprogress_get_info')){
	    $info = uploadprogress_get_info($uploadID);		// provided by uploadprogress php module
    }else{
        $info = null;
    }

	if ($info === null) {
		$infos["$uploadID"] = array("done" => true);
	}
	else {
		$infos["$uploadID"] = array(
			"bytesTotal" => intval($info["bytes_total"]),
			"bytesUploaded" => intval($info["bytes_uploaded"]),
			"estimatedTimeRemaining" => intval($info["est_sec"]),
			"averageSpeed" => intval($info["speed_average"]),
			"filesUploaded" => intval($info["files_uploaded"])
		);
	}
}

print json_format($infos);
?>
