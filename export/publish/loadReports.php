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
* brief description of file
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
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/* load the particular report or list of reports */

define("SAVE_URI", "disabled");

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
//require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

if (! is_logged_in()) {
		header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
}

	header('Content-type: text/javascript');

	$sys_usrReportSchedule_ColumnNames = array(
	"rps_ID"=>"i",
	"rps_Type"=>"s",
	"rps_Title"=>"s",
	"rps_FilePath"=>"s",
	"rps_URL"=>"s",
	"rps_FileName"=>"s",
	"rps_HQuery"=>"s",
	"rps_Template"=>"s",
	"rps_IntervalMinutes"=>"i"
	);

	$metod = @$_REQUEST['method'];

	if($metod=="searchreports"){

		mysql_connection_select(DATABASE);

		//search the list of users by specified parameters
		$f_id 	= @$_REQUEST['recID'];
		$f_name = urldecode(@$_REQUEST['name']);
		$f_userid 	= @$_REQUEST['usrID']; //@todo

		$records = array();
		$recordsCount = 0;

		//loads list of all records

		$query = "select rps_ID, rps_Type, rps_Title, rps_FilePath, rps_URL, rps_FileName, rps_HQuery, rps_Template, rps_IntervalMinutes, 0 as selection, 0 as status from usrReportSchedule";

		if($f_name && $f_name!=""){
			$query = $query." where rps_Title like '%".$f_name."%'";
		}

		$res = mysql_query($query);

		while ($row = mysql_fetch_assoc($res)) {

			$row['status'] = getStatus($row);

			array_push($records, $row);
		}

		print json_format($records);

	}else if($metod=="getreport"){ //-----------------

		mysql_connection_select(DATABASE);

		$groupID = @$_REQUEST['recID'];
		if ($groupID==null) {
			die("invalid call to loadReports, recID is required");
		}

		$colNames = array("rps_ID", "rps_Type", "rps_Title", "rps_FilePath", "rps_URL", "rps_FileName", "rps_HQuery", "rps_Template", "rps_IntervalMinutes");

		$records = array();
		$records['fieldNames'] = $colNames;
		$records['records'] = array();

		$query = "select ".join(",", $colNames)." from ".USERS_DATABASE.".usrReportSchedule ";

		if($groupID!="0"){
			$query = $query." where rps_ID=".$groupID;
		}else{
			$query = null;
		}

/*****DEBUG****///error_log(">>>>>>>>>>>>>>> QUERY =".$query);
		if($query){
			$res = mysql_query($query);
			while ($row = mysql_fetch_row($res)) {
				$records['records'][$row[0]] = $row;
			}
		}

		print "top.HEURIST.reports = " . json_format($records) . ";\n";
		print "\n";

	}else if($metod=="savereport"){ //-----------------

		$db = mysqli_connection_overwrite(DATABASE);

		$data  = json_decode(urldecode(@$_REQUEST['data']), true);
		$recID  = @$_REQUEST['recID'];

		if (!array_key_exists('report',$data) ||
		!array_key_exists('colNames',$data['report']) ||
		!array_key_exists('defs',$data['report'])) {
			die("invalid data structure sent with savereport method call to loadReports.php");
		}

		$colNames = $data['report']['colNames'];

		$rv = array();
		$rv['result'] = array(); //result

		foreach ($data['report']['defs'] as $recID => $rt) {
			array_push($rv['result'], updateReportSchedule($colNames, $recID, $rt));
		}
		print json_format($rv);

	}else if($metod=="deletereport"){



		$recID  = @$_REQUEST['recID'];
		$rv = array();
		if (!$recID) {
			$rv['error'] = "invalid or not ID sent with deletereport method call to loadReports.php";
		}else{
			$rv = deleteReportSchedule($recID);
			if (!array_key_exists('error',$rv)) {
				//$rv['rectypes'] = getAllRectypeStructures();
			}
		}
		print json_format($rv);

	}

exit();

	/**
	* @return 0 - ok,  1 - template file missed, 2 - output folder does not exist, 2 - file does not exist
	*/
	function getStatus($row){

		if(!file_exists(HEURIST_SMARTY_TEMPLATES_DIR.$row['rps_Template'])){
			return 1;
		}

		if($row['rps_FilePath']!=null){
			$dir = $row['rps_FilePath'];
			if(substr($dir,-1)!="/") $dir = $dir."/";
		}else{
			$dir = HEURIST_UPLOAD_DIR."generated-reports/";
		}

		if(!file_exists($dir)){
			return 2;
		}

		$filename = ($row['rps_FileName']!=null)?$row['rps_FileName']:$row['rps_Template'];

		$outputfile = $dir.$filename;

		$path_parts = pathinfo($outputfile);
		$ext = array_key_exists('extension',$path_parts)?$path_parts['extension']:null;
		if ($ext == null) {
			$outputfile = $outputfile.".html";
		}

		if(!file_exists($outputfile)){
			return 3;
		}else{
			return 0;
		}
	}

	/**
	* deleteReportSchedule - delete record from
	* @author Artem Osmakov
	* @param $recID record ID to delete
	* @return $ret record id that was deleted or error message
	**/
	function deleteReportSchedule($recID) {

		$db = mysqli_connection_overwrite(DATABASE);

		$ret = array();

		//delete references from user-group link table
		$query = "delete from usrReportSchedule where rps_ID=$recID";
		$rows = execSQL($db, $query, null, true);
		if (is_string($rows) ) {
			$ret['error'] = "db error deleting record from report schedules - ".$rows;
		}else{
			$ret['result'] = $recID;
		}

		$db->close();

		return $ret;
	}

	/**
	*
	*
	* @param mixed $colNames
	* @param mixed $recID
	* @param mixed $rt
	*/
	function updateReportSchedule($colNames, $recID, $values){

		global $db, $sys_usrReportSchedule_ColumnNames;

		$ret = null;

		if (count($colNames) && count($values)){

			$db = mysqli_connection_overwrite(DATABASE);

			$isInsert = ($recID<0);

			$query = "";
			$fieldNames = "";
			$parameters = array("");
			$fieldNames = join(",",$colNames);

			foreach ($colNames as $colName) {

				$val = array_shift($values);

				if (array_key_exists($colName, $sys_usrReportSchedule_ColumnNames))
				{

					if($query!="") $query = $query.",";

					if($isInsert){
							$query = $query."?";
					}else{
							$query = $query."$colName = ?";
					}

					$parameters[0] = $parameters[0].$sys_usrReportSchedule_ColumnNames[$colName]; //take datatype from array
					array_push($parameters, $val);

				}
			}//for columns

			if($query!=""){
				if($isInsert){
					$query = "insert into usrReportSchedule (".$fieldNames.") values (".$query.")";
				}else{
					$query = "update usrReportSchedule set ".$query." where rps_ID = $recID";
				}

				$rows = execSQL($db, $query, $parameters, true);

				if ($rows==0 || is_string($rows) ) {
					$oper = (($isInsert)?"inserting":"updating");
					$ret = "error $oper in updateReportSchedule - ".$rows; //$msqli->error;
				} else {
					if($isInsert){
						$recID = $db->insert_id;
						$ret = -$recID;

					}//if $isInsert
					else{
						$ret = $recID;
					}
				}
			}



			$db->close();
		}//if column names


		if ($ret==null){
			$ret = "no data supplied for updating report - $recID";
		}

		return $ret;
	}
?>