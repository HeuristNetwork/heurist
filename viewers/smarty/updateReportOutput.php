<?php

/*
* Copyright (C) 2005-2020 University of Sydney
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
* updateReportOutput.php
*
* takes a report ID (rps_ID in usrReportSchedule) and writes out the report (html and js files)
* to the location specified for that ID.
* An ID of 0 should trigger sequential refreshing of all the reports
*
* parameters
* 'id' - key field value in usrReportSchedule
* 'mode' - if publish>0: js or html (default)
* 'publish' - 0 vsn 3 UI (smarty tab),  1 - publish,  2 - no browser output (save into file only)
*                 3 - redirect the existing report, if it does not exist publish=1
*
* If publish=1 then the script displays a web page with a report on the process
* (success or errors as below). If not set, then the errors (file can't be written, can't find template,
* can't find file path, empty query etc) are sent by email to the database owner.
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
require_once(dirname(__FILE__).'/../../viewers/smarty/showReps.php');

//system is defined in showReps
if(!$system->is_inited()){
    echo 'System is not inited';
    exit();
}

$rps_ID = (array_key_exists('id',$_REQUEST)) ? $_REQUEST['id'] :0;

//mode of publication  3 - redirect to existing  html or js wrapper
if(array_key_exists('publish',$_REQUEST)){
	$publish = intval($_REQUEST['publish']);
}else{
	$publish = 1;
}
$format = (array_key_exists('mode',$_REQUEST) && $_REQUEST['mode']=="js") ?"js":"html";

$mysqli = $system->get_mysqli();

if($publish==3){
    header("Content-type: text/html;charset=UTF-8");
}


if($rps_ID==0){
	//regenerate all reports
	$res = $mysqli->query('select * from usrReportSchedule');
    if($res){
        while ($row = $res->fetch_assoc()) {
            doReport($row);
        }
        $res->close();        
    }

}else if(is_numeric($rps_ID)){
	//load one
    
	$row = mysql__select_row_assoc($mysqli, "select * from usrReportSchedule where rps_ID=".$rps_ID);
    if($row){
			doReport($row);
	}

}else{
    echo "Wrong report ID parameter: ".$rps_ID;
}

exit();

//
// generate report
//
function doReport($row){

	global $system, $publish, $format;

	if($row['rps_FilePath']!=null){
		$dir = $row['rps_FilePath'];
		if(substr($dir,-1)!="/") $dir = $dir."/";
	}else{
		$dir = HEURIST_FILESTORE_DIR."generated-reports/";
        if(!folderCreate($dir, true)){
            die('Failed to create folder for generated reports');
        }   
	}

	$filename = ($row['rps_FileName']!=null)?$row['rps_FileName']:$row['rps_Template'];

	$outputfile = $dir.$filename;

	if($publish==3){  //if published file already exists take it

		$path_parts = pathinfo($outputfile);
		$ext = array_key_exists('extension',$path_parts)?$path_parts['extension']:null;

		if ($ext == null) {

			$filename2 = $outputfile.".".$format;
			if(file_exists($filename2)){
				$outputfile = $filename2;
				$ext = $format;
			}else{ // if ($format=="js")
				$outputfile = $outputfile.".html";
				$ext = "html";
			}
		}
		if(file_exists($outputfile)){
            
            if($row['rps_IntervalMinutes']>0){
                $dt1 = new DateTime("now");
                $dt2 = new DateTime();
                $dt2->setTimestamp(filemtime($outputfile));
                $interval = $dt1->diff( $dt2 );
                if($interval->i > $row['rps_IntervalMinutes']){
                    $publish = 2;
                }
            }
            if($publish == 3){ //request for current files
            
			    $content = file_get_contents($outputfile);
			    if($format=="js" && $ext != $format){
				    $content = str_replace("\n","",$content);
				    $content = str_replace("\r","",$content);
				    $content = str_replace("'","&#039;",$content);
    			    echo "document.write('". $content."');";
			    }else{
				    echo $content;
			    }
			    return;
            }
		}
		$publish = 1; //file does not exists - regenerate
	}//publish==3

	$hquery = $row['rps_HQuery'];
	if(strpos($hquery, "&q=")>0){
		parse_str($hquery, $params); //parse query and put to parameters
	}else{
		$params = array("q"=>$hquery);
	}

	if(!array_key_exists("ver", $params)){
		$params["ver"] = "1";
	}
	if(!array_key_exists("w", $params)){
		$params["w"] = "all";
	}

	$params["template"] = $row['rps_Template'];
	$params["output"]	= $outputfile;
	$params["mode"] 	= $format;
	$params["publish"] 	= $publish;
	$params["rps_id"] 	= $row['rps_ID'];
    
	executeSmartyTemplate($system, $params); //in showReps
}
?>
