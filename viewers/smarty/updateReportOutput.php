<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* updateReportOutput.php
*
* It takes a report ID (rps_ID in usrReportSchedule) and writes out the report (html and js files)
* to the location specified for that ID.
* If ID is 0 it should trigger sequential refreshing of all the reports
*
* parameters
* 'id' - key field value in usrReportSchedule
* 'mode' - if publish>0: js or html (default)
* 'publish' - 0 vsn 3 UI (smarty tab),  1 - publish,  2 - no browser output (save into file only)
*                 3 - redirect the existing report (use already publshed output), if it does not exist publish=1
*
* If publish=1 then the script displays a web page with a report on the process
* (success or errors as below). If not set, then the errors (file can't be written, can't find template,
* can't find file path, empty query etc) are sent by email to the database owner.
*
* Use this script in cronjob for auto regenerate all reports
* viewers/smarty/updateReportOutput.php?db=xxx&publish=3
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson.heurist@gmail.com>
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
use hserv\utilities\USanitize;
use hserv\report\ReportExecute;

if(isset($_REQUEST) && !empty($_REQUEST)){ //if set it is included in dailyCronJobs

    //system is defined in showReps
    if(!$system->is_inited()){
        echo 'System is not inited';
        exit;
    }

    $rps_ID = (array_key_exists('id',$_REQUEST)) ? $_REQUEST['id'] :0;

    //mode of publication  3 - redirect to existing  html or js wrapper
    if(array_key_exists('publish',$_REQUEST)){
	    $update_mode = intval($_REQUEST['publish']);
    }else{
	    $update_mode = 1;
    }

    $format = null;
    if(array_key_exists('mode',$_REQUEST)){
        $mode = htmlspecialchars($_REQUEST['mode']);
        $allowed_exts = array('html','js','txt','csv','xml','json','css');
        $idx = array_search($mode, $allowed_exts);
        $format = ($idx>=0)?$allowed_exts[intval($idx)]:'html';
        //preg_match('/html|js|txt|csv|xml|json|css/', $_REQUEST['mode'])
    }

    $mysqli = $system->get_mysqli();

    if($update_mode==3){
        header(CTYPE_HTML);
    }


    if($rps_ID==0){
	    //regenerate all reports
	    $res = $mysqli->query('select * from usrReportSchedule');
        if($res){
            while ($row = $res->fetch_assoc()) {
                doReport($system, $update_mode, $format, $row);
            }
            $res->close();
        }

    }elseif(is_numeric($rps_ID)){
	    //load one

	    $row = mysql__select_row_assoc($mysqli, "select * from usrReportSchedule where rps_ID=".$rps_ID);
        if($row){
			    doReport($system, $update_mode, $format, $row);
	    }

    }else{
        echo "Wrong report ID parameter: ".$rps_ID;
    }

    exit;
}
//
// Generates report
//
// $update_mode
// 1 saves into file and produces (into browser) the report only (with urls)
// 2 executes report and download it under given output name (no file save, no browser output)
// 3 redirects to the existing report (use already publshed output), if it does not exist, recreate it (publish=1)
// 4 supress output
//
// returns:
// 1 - report is created
// 2 - report is updated
// 3 - report is intakted (not updated)
//
function doReport($system, $update_mode, $format, $row){

    $res = 1;

    /* not allows due to security reasons
	if($row['rps_FilePath']!=null){
		$dir = $row['rps_FilePath'];
        $dir = USanitize::sanitizePath($dir);
	}else{
    */
	$dir = $system->getSysDir('generated-reports');
    if(!folderCreate($dir, true)){
        die('Failed to create folder for generated reports');
    }


    if($format==null){
        if(strpos(@$row['rps_URL'],'&mode=')!==false){
            $params = array();
            parse_str($row['rps_URL'], $params);
            $format = preg_replace('/[^a-zA-Z]/', "", @$params['mode']);//for snyk
        }else{
            $format = @$row['rps_URL'];
        }
    }
    if($format==null || !preg_match('/html|js|txt|csv|xml|json|css/',$format)){
        $format = 'html';//default
    }

    $filename = basename($row['rps_FileName'] ?? $row['rps_Template']);
    $filename = USanitize::sanitizeFileName($filename);
    
    $path_parts = pathinfo($filename);
    $file_name = $path_parts['filename'] . '.' . $format;
    
	$outputfile = $dir.$file_name;
    

	if($update_mode==3 || $update_mode==4){  //if published file already exists take it

		/*$path_parts = pathinfo($outputfile);
		$ext = array_key_exists('extension',$path_parts)?$path_parts['extension']:null;
		if ($ext == null) {
            //add extension
            $format = basename($format);
            
			$filename2 = $outputfile.'.'.$format;
			if(file_exists($filename2)){
				$outputfile = $filename2;

			}else{ // if ($format=="js")
				$outputfile = $outputfile.'.'.$format;
			}
            $ext = $format;
            $filename = $filename.'.'.$ext;
		}*/

		if(file_exists($outputfile)){

            if($row['rps_IntervalMinutes']>0){
                $dt1 = new DateTime("now");
                $dt2 = new DateTime();
                $dt2->setTimestamp(filemtime($outputfile));//get file time
                $interval = $dt1->diff( $dt2 );

                $tot_minutes = ($interval->days*1440 + $interval->h*60 + $interval->i);
                if($tot_minutes > $row['rps_IntervalMinutes']){
                    $publish = 3; //saves into file and produces info page (was 3 - and browser output)
                    $res = 2; //to update
                }
            }
            if($res == 1){ //request for current files (without smarty execution)
                if($update_mode==3){
                    //file exists and up to date

                    if($format=='js'){
                        header(CTYPE_JS);
                    }else{

                        if($format=='txt'){
                            $mimetype = 'plain/text';
                        }elseif($format=='json'){
                            $mimetype = 'application/json';
                        }else{
                            $mimetype = "text/$format";
                        }

                        if($format!='html'){
                            header("Content-type: $mimetype;charset=UTF-8");
                        }

                        if($format!='html'){
                            header('Pragma: public');
                            header('Content-Disposition: attachment; filename="'.$file_name.'"');
                        }
                    }

			        $content = file_get_contents($outputfile);
			        if($format=="js"){
				        $content = str_replace("\n","",$content);
				        $content = str_replace("\r","",$content);
				        $content = str_replace("'","&#039;",$content);
    			        echo "document.write('". $content."');";
			        }else{
				        echo $content;
			        }
                }
			    return 3; //intakted - existing taken
            }
		}
		$publish = 3; //file does not exist - regenerates and output into browser
	}//publish==3
    else{
        $publish = $update_mode; //1 - regenerates and output info page OR 2 - download
    }

	$hquery = $row['rps_HQuery'];
	if(strpos($hquery, "&q=")>0){
		parse_str($hquery, $params);//parse query and put to parameters
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
	$params["rps_id"] 	= $row['rps_ID'];    //report schedule iD
    $params["void"]     = ($update_mode==4); //no browser output

	$report = ReportExecute($system, $params);
    $success = $report->execute();

    if(!$success) {$res = 0;}

    if($update_mode==4){
        echo htmlspecialchars($outputfile.'  '.($res==0?'error':($res==1?'created':'updated')))."\n";
    }

    return $res;
}
?>
