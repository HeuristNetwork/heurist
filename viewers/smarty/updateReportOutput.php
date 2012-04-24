<?php

/**
 * updateReportOutput.php
 *
 * takes a report ID (rps_ID in usrReportSchedule) and writes out the report (html and js files)
 * to the location specified for that ID.
 * An ID of 0 should trigger sequential refreshing of all the reports
 *
 * parameters
 * 'output' - full file path to be saved
 * 'mode' - if publish>0: js or html (default)
 * 'publish' - 0 H3 UI (smarty tab),  1 - publish,  2 - no browser output (save into file only)
 * 				3 - redirect the existing report, if it does not exist publish=1
 *
 * If publish=2 then the script displays a web page with a report on the process
 * (success or errors as below). If not set, then the errors (file can't be written, can't find template,
 * can't find file path, empty query etc) are sent by email to the database owner.
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

 require_once(dirname(__FILE__).'/../../viewers/smarty/showReps.php');


$dir = HEURIST_SMARTY_TEMPLATES_DIR;

$rps_ID = (array_key_exists('id',$_REQUEST)) ? $_REQUEST['id'] :0;

mysql_connection_db_select(DATABASE);

if(array_key_exists('publish',$_REQUEST)){
	$publish = intval($_REQUEST['publish']);
}else{
	$publish = 1;
}
$format = (array_key_exists('mode',$_REQUEST) && $_REQUEST['mode']=="js") ?"js":"html";

if($rps_ID==0){
	//regenerate all reports
	$res = mysql_query('select * from usrReportSchedule');
	while ($row = mysql_fetch_assoc($res)) {
		doReport($row);
	}

}else{
	//load one
	$res = mysql_query("select * from usrReportSchedule where rps_ID=".$rps_ID);
	if(mysql_error()){
		error_log("ERROR=".mysql_error());
	}else{
		$row = mysql_fetch_assoc($res);
		if($row){
			doReport($row);
		}
	}
}

exit();

//
// generate report
//
function doReport($row){

	global $publish, $format;

	$dir = ($row['rps_FilePath']!=null)?$row['rps_FilePath']:HEURIST_UPLOAD_DIR."generated-report/";
	if(substr($dir,-1)!="/") $dir = $dir."/";

	$filename = ($row['rps_FileName']!=null)?$row['rps_FileName']:$row['rps_Template'];

	$outputfile = $dir.$filename;

	if($publish==3){

		$path_parts = pathinfo($outputfile);
		$ext = $path_parts['extension'];
error_log("EXT=".$ext);

		if ($ext == null) {

			$filename2 = $outputfile.".".$format;
			if(file_exists($filename2)){
				$outputfile = $filename2;
				$ext = $format;
			}else if ($format=="js"){
				$outputfile = $outputfile.".html";
				$ext = "html";
			}
		}
		if(file_exists($outputfile)){
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
		$publish = 1;
	}

	$hquery = $row['rps_HQuery'];
	if(strpos($hquery, "&q=")>0){
		parse_str($hquery, $params);
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

	executeSmartyTemplate($params); //in showReps
}
?>
