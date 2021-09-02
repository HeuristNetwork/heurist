<?php

/**
*  Email Users of any Heurist database located on this server, requires a Heurist Database + System Administrator password
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__)."/../../hsapi/System.php");
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/conceptCode.php');

header("Access-Control-Allow-Origin: *");
header('Content-type: application/json;charset=UTF-8');

$system = new System();

$data = null;
$response = array();
$rtn = false;

$isSystemInited = $system->init(@$_REQUEST['db']);

if(!$isSystemInited) {

	$response = $system->getError();
	$rtn = json_encode($response);

	print $rtn;
	exit();
}

$mysqli = $system->get_mysqli();

if(isset($_REQUEST['get_email']) && isset($_REQUEST['recid'])) {	/* Get the Title and Short Summary field for the selected id, id is for Email record */

	$email_title = "";
	$email_body = "";
	$id = $_REQUEST['recid'];

	// Validate ID
	if(!is_numeric($id)){

    $response = array("status"=>HEURIST_ERROR, "message"=>"An invalid record id was provided.<br>The Heurist team has been notified.", "request"=>$id);
    $system->addError(HEURIST_ERROR, "Bulk Email Other: The record IDs for the Email selector are invalid or are not being retrieved correctly. ");
    $rtn = json_encode($response);

    print $rtn;
    exit();
	}

	// Get title/name and short summary detail type ids
	$title_detailtype_id = ConceptCode::getDetailTypeLocalID("2-1");
	$shortsum_detiltype_id = ConceptCode::getDetailTypeLocalID("2-3");
	if (empty($title_detailtype_id) || empty($shortsum_detiltype_id)) {
		$missing = "";

		if(empty($title_detailtype_id) || empty($shortsum_detiltype_id)){
			$missing = "for both title and short summary detail types.";
		}else{
			$missing = empty($title_detailtype_id) ? "for the title detail type." : "for the short summary detail type.";
		}

    $response = array("status"=>HEURIST_ERROR, "message"=>"Unable to retrieve the local id $missing <br>The Heurist team has been notified.");
    $system->addError(HEURIST_ERROR, "Bulk Email Other: Unable to retrieve the local id ". $missing);
    $rtn = json_encode($response);

    print $rtn;
    exit();
	}

  $query = "SELECT dtl_Value, dtl_DetailTypeID
            FROM recDetails
            WHERE dtl_RecID = $id AND dtl_DetailTypeID IN (".$shortsum_detiltype_id.", ".$title_detailtype_id.")";

  $detail_rtn = $mysqli->query($query);
  if(!$detail_rtn){

    $response = array("status"=>HEURIST_ERROR, "message"=>"Unable to retrieve the details of the Email record ID => $id.<br>", "error_msg"=>$mysqli->error, "request"=>$id);
    $rtn = json_encode($response);

    print $rtn;
    exit();
  }

  while($email_dtl = $detail_rtn->fetch_row()){
  	if($email_dtl[1] == $shortsum_detiltype_id){
  		$email_body = $email_dtl[0];
  	}else if($email_dtl[1] == $title_detailtype_id){
  		$email_title = $email_dtl[0];
  	}
  }

  $data = array($email_title, $email_body);

	$response = array("status"=>HEURIST_OK, "data"=>$data, "request"=>$id);
	$rtn = json_encode($response);

	print $rtn;

} else if(isset($_REQUEST['db_filtering'])) { /* Get a list of DBs based on the list of provided filters, first search gets all dbs */

	$db_request = $_REQUEST['db_filtering'];
	$dbs = array();
	$invalid_dbs = array();

	// Get all dbs that start with the Heurist prefix
	$query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE `SCHEMATA`.`SCHEMA_NAME` LIKE '".HEURIST_DB_PREFIX."%' ORDER BY `SCHEMATA`.`SCHEMA_NAME` COLLATE utf8_general_ci";

	$db_list = $mysqli->query($query);
	if (!$db_list) {

	    $response = array("status"=>HEURIST_ERROR, "message"=>"Unable to retrieve a list of Heurist databases.<br>", "error_msg"=>$mysqli->error, "request"=>$db_request);
	    $rtn = json_encode($response);

	    print $rtn;
	    exit();
	}

	while($db = $db_list->fetch_row()){

		// Ensure that the Heurist db has the required tables, ignore if they don't
    $query = "SHOW TABLES IN ".$db[0]." WHERE Tables_in_".$db[0]." = 'Records' OR Tables_in_".$db[0]." = 'recDetails' OR Tables_in_".$db[0]." = 'sysUGrps' OR Tables_in_".$db[0]." = 'sysUsrGrpLinks'";

    $table_listing = $mysqli->query($query);
    if (!$table_listing || mysqli_num_rows($table_listing) != 4) { // Skip, missing required tables

    	if($table_listing && $db_request == "all"){
    		$invalid_dbs[] = $db[0];
    	}

      continue;
    }

    $dbs[] = $db[0];
	}

	if($db_request == "all"){ // No additional filtering needed
		$data = $dbs;
	} else if(is_array($db_request) && count($db_request)==4){ // Do filtering, record count and last modified

		$count = $db_request['count'];

		$lastmod_logic = $db_request['lastmod_logic'];
		$lastmod_period = $db_request['lastmod_period'];
		$lastmod_unit = $db_request['lastmod_unit'];

		$lastmod_where = ($lastmod_unit!="ALL") ? "AND rec_Modified " . $lastmod_logic . " date_format(curdate(), '%Y-%m-%d') - INTERVAL " . $lastmod_period . " " . $lastmod_unit . " " : "";

		foreach ($dbs as $db) {
	
			$query = "SELECT count(*) 
								FROM (
									SELECT *
									FROM ".$db.".Records AS rec
									WHERE rec_Title IS NOT NULL
									AND rec_Title NOT LIKE 'Heurist System Email Receipt%'
									AND rec_FlagTemporary != 1
									AND rec_Title != '' " . $lastmod_where . "
								) AS a";

			$count_res = $mysqli->query($query);
			if(!$count_res){

				$response = array("status"=>HEURIST_ERROR, "message"=>"Unable to filter Heurist databases based on provided filter.<br>", "error_msg"=>$mysqli->error, "request"=>$db_request);
				$rtn = json_encode($response);

				$count_res->close();

				print $rtn;

				exit();

			}else{
				$row = $count_res->fetch_row();

				if($row[0] > $count){
					$data[] = $db;
				}
			}
		}
	}

	$response = array("status"=>HEURIST_OK, "data"=>$data, "request"=>$db_request);
	$rtn = json_encode($response);

	print $rtn;

} else if(isset($_REQUEST['user_count']) && isset($_REQUEST['db_list'])) { // Get a count of distinct users

	$user_request = $_REQUEST['user_count'];
	$dbs = $_REQUEST['db_list'];

	$data = 0;
	$email_list = array();

	foreach($dbs as $db){

		if($user_request == "owner"){ // Owners
			$where_clause = "WHERE ugr.ugr_ID = 2";
		}else if($user_request == "admin"){ // Admins for workgroup Database Managers

			$where_clause = "WHERE ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled = 'y' AND ugl.ugl_GroupID = 1";

			}else if($user_request == "admins"){ // Admins for ALL workgroups

				$where_clause = "WHERE ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled = 'y' AND ugl.ugl_GroupID IN 
		  		 (SELECT ugr_ID 
			   		  FROM " . $db . ".sysUGrps 
			   		  WHERE ugr_Type = 'workgroup' AND ugr_Enabled = 'y')";

		}else if($user_request == "user"){ // ALL users
			$where_clause = "WHERE ugr.ugr_Type = 'user' AND ugr.ugr_Enabled = 'y'";
		}else{

			$response = array("status"=>HEURIST_INVALID_REQUEST, "message"=>"Invalid user choice", "request"=>$user_request);
			$rtn = json_encode($response);

			print $rtn;
			exit();
		}

		$query = "SELECT DISTINCT ugr.ugr_FirstName, ugr.ugr_LastName, ugr.ugr_eMail 
						  FROM " . $db . ".sysUsrGrpLinks AS ugl  
						  INNER JOIN " . $db . ".sysUGrps AS ugr ON ugl.ugl_UserID = ugr.ugr_ID "
						. $where_clause;

		$res = $mysqli->query($query);
		if(!$res){

			$response = array("status"=>HEURIST_ERROR, "message"=>"Unable to retrieve user count for databases => $db<br>", "error_msg"=>$mysqli->error, "request"=>$user_request);
			$rtn = json_encode($response);

			print $rtn;
			exit();
		}

		while($row = $res->fetch_row()){
			
			if(!in_array($row[2], $email_list)){
				$data += 1;
				$email_list[] = $row[2];
			}
		}

	}

	$response = array("status"=>HEURIST_OK, "data"=>$data, "request"=>$user_request);
	$rtn = json_encode($response);

	print $rtn;
	
} else if(isset($_REQUEST['sysadmin_pwd'])) { // Verify Admin Password

	if(!$system->verifyActionPassword($_REQUEST['sysadmin_pwd'], $passwordForServerFunctions)){
		$data = true;
	} else {
		$data = false;
	}

	$response = array("status"=>HEURIST_OK, "data"=>$data);
	$rtn = json_encode($response);

	print $rtn;
} else { // Invalid Request

	$response = array("status"=>HEURIST_INVALID_REQUEST, "message"=>"invalid request sent", "request"=>$_REQUEST);
	$rtn = json_encode($response);

	print $rtn;
}

?>