<?php

/*<!--
 saveUsergrps.php
* This file accepts request to update sysUGrps and sys sysUsrGrpLinks tables
*
* @version 2011.0510
* @autor: Artem Osmakov
*
* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
* @link: http://HeuristScholar.org
* @license http://www.gnu.org/licenses/gpl-3.0.txt
* @package Heurist academic knowledge management system
* @todo
 -->*/


	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	//require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');


	if (! is_logged_in()) {
	//ARTEM
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}

	header('Content-type: text/javascript');

	$legalMethods = array(
	"saveUser",
	"saveGroup",
	"deleteUser",
	"deleteGroup",
	"changeRole");

	$sysUGrps_ColumnNames = array(
	"ugr_ID"=>"i",
	"ugr_Type"=>"s",
	"ugr_Name"=>"s",
	"ugr_LongName"=>"s",
	"ugr_Description"=>"s",
	"ugr_Password"=>"s",
	"ugr_eMail"=>"s",
	"ugr_FirstName"=>"s",
	"ugr_LastName"=>"s",
	"ugr_Department"=>"s",
	"ugr_Organisation"=>"s",
	"ugr_City"=>"s",
	"ugr_State"=>"s",
	"ugr_Postcode"=>"s",
	"ugr_Interests"=>"s",
	"ugr_Enabled"=>"s",
	"ugr_URLs"=>"s"
	);

	$sysUsrGrpLinks_ColumnNames = array(
	"ugl_ID"=>"i",
	"ugl_UserID"=>"i",
	"ugl_GroupID"=>"i",
	"ugl_Role"=>"s"
	);

	if (!@$_REQUEST['method']) {
		die("invalid call to saveUsergrps, method parameter is required");
	}else if(!in_array($_REQUEST['method'], $legalMethods)) {
		die("unsupported method call to saveUsergrps");
	}

	$db = mysqli_connection_overwrite(DATABASE); //artem's

	//decode and unpack data
	$data  = json_decode(urldecode(@$_REQUEST['data']), true);
	$recID  = @$_REQUEST['recID'];

	switch (@$_REQUEST['method']) {

	case 'saveUser':
		if (!array_key_exists('user',$data) ||
		!array_key_exists('colNames',$data['user']) ||
		!array_key_exists('defs',$data['user'])) {
			die("invalid data structure sent with saveUser method call to saveUsergrps.php");
		}
		$colNames = $data['user']['colNames'];

		$rv = array();
		$rv['result'] = array(); //result

		foreach ($data['user']['defs'] as $recID => $rt) {
			array_push($rv['result'], updateUserGroup('user', $colNames, $recID, $rt));
		}

		break;

	case 'deleteUser':

		if (!$recID) {
			$rv = array();
			$rv['error'] = "invalid or not ID sent with deleteUser method call to saveUsergrps.php";
		}else{
			$rv = deleteUser($recID);
			if (!array_key_exists('error',$rv)) {
				//$rv['rectypes'] = getAllRectypeStructures();
			}
		}
		break;

	case 'saveGroup':
		if (!array_key_exists('group',$data) ||
		!array_key_exists('colNames',$data['group']) ||
		!array_key_exists('defs',$data['group'])) {
			die("invalid data structure sent with saveGroup method call to saveUsergrps.php");
		}
		$colNames = $data['group']['colNames'];

		$rv = array();
		$rv['result'] = array(); //result

		foreach ($data['group']['defs'] as $recID => $rt) {
			array_push($rv['result'], updateUserGroup('group', $colNames, $recID, $rt));
		}

		break;

	case 'deleteGroup':

		if (!$recID) {
			$rv = array();
			$rv['error'] = "invalid or not ID sent with deleteGroup method call to saveUsergrps.php";
		}else{
			$rv = deleteGroup($recID);
		}
		break;

	case 'changeRole':

		$recIds	= @$_REQUEST['recIDs'];
		$newRole = @$_REQUEST['role'];
		$oldRole = @$_REQUEST['oldrole'];

		if (!$recID || !$recIds || !$newRole) {
			$rv = array();
			$rv['error'] = "invalid or not IDs sent with changeRole method call to saveUsergrps.php";
		}else{
			$rv = changeRole($recID, $recIds, $newRole, $oldRole, true);
			if (!array_key_exists('error',$rv)) {
				//$rv['rectypes'] = getAllRectypeStructures();
			}
		}
		break;

	}//end of switch
	$db->close();

	print json_format($rv);
	/*
	if (@$rv) {
	print json_format($rv);
	}*/

	exit();

	/**
	* put your comment there...
	*
	* @param mixed $type - group or user
	* @param mixed $recID
	*/
	function checkPermission( $type, $recID ) {

		global $db;

		$ret = null;

			if(	!is_admin() ){
				if($type=='user'){
					if ( $recID != get_user_id() ){
						$ret = "You are not admin and can't edit another user";
					}
				}else if($type=='group'){
					//find admin
					$query = "select ugl_UserID from sysUsrGrpLinks where ugl_Role = 'admin' and ugl_GroupID=$recID
							and ugl_UserID=".get_user_id();
					$rows = execSQL($db, $query, null, true);

					if (!is_numeric($rows)) {
						$ret = "Error checking rights Group $recID in updateUserGroup - ".$rows;
					}else if ($rows==0){
						$ret = "You are not admin for Group# $recID. Edit is not allowed";
					}
				}
			}

			return $ret;
	}

	/**
	* put your comment there...
	*
	*
	* @param $type - "user" or "group"
	* @param mixed $commonNames
	* @param mixed $rt
	*/
	function updateUserGroup( $type, $colNames, $recID, $values ) {

		global $db, $sysUGrps_ColumnNames;

		$ret = null;

		if (count($colNames) && count($values))
		{
			$isInsert = ($recID<0);

			//check rights for update
			if(!$isInsert){
				$ret = checkPermission($type, $recID);
				if($ret!=null) return $ret;
			}

			$query = "";
			$fieldNames = "";
			$parameters = array("");
			$fieldNames = join(",",$colNames);

			foreach ($colNames as $colName) {

				$val = array_shift($values);

				if (array_key_exists($colName, $sysUGrps_ColumnNames))
				{


					if($colName == "ugr_Password"){
						$s = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./';
						$salt = $s[rand(0, strlen($s)-1)] . $s[rand(0, strlen($s)-1)];
						$val = crypt($val, $salt);
					}else if($colName == "ugr_Name"){
						$ugr_Name = $val;
					}else if($colName == "ugr_FirstName"){
						$ugr_FirstName = $val;
					}else if($colName == "ugr_LastName"){
						$ugr_LastName = $val;
					}else if($colName == "ugr_eMail"){
						$ugr_eMail = $val;
					}


					//array_push($ret['error'], "$colName is not a valid column name for defDetailTypes val= $val was not used");

						if($query!="") $query = $query.",";

						if($isInsert){
							$query = $query."?";
							//if($fieldNames!="") $fieldNames=$fieldNames.",";
							//$fieldNames = $fieldNames.$colName;
						}else{
							$query = $query."$colName = ?";
						}

					$parameters[0] = $parameters[0].$sysUGrps_ColumnNames[$colName]; //take datatype from array
					array_push($parameters, $val);

				}
			}//for columns

			if($query!=""){
				if($isInsert){
					$query = "insert into sysUGrps (".$fieldNames.") values (".$query.")";
				}else{
					$query = "update sysUGrps set ".$query." where ugr_ID = $recID";
				}

				$rows = execSQL($db, $query, $parameters, true);

				if (!is_numeric($rows) || $rows==0) {
					$oper = (($isInsert)?"inserting":"updating");
					$ret = "error $oper $type# $recID in updateUserGroup - ".$rows; //$msqli->error;
				} else {
					if($isInsert){
						$recID = $db->insert_id;

						if($type=='user'){
							$email_text =
"Your Heurist account registration has been approved.

Login at:

".HEURIST_URL_BASE."

with the username: " . $ugr_Name . ".

We recommend visiting the 'Take the Tour' section and
also visiting the Help function, which provides comprehensive
overviews and step-by-step instructions for using Heurist.

";
							$rv = mail($ugr_eMail, 'Heurist User Registration: '.$ugr_FirstName.' '.
								$ugr_LastName.' ['.$ugr_eMail.']', $email_text,
								"From: info@acl.arts.usyd.edu.au\r\nCc: info@acl.arts.usyd.edu.au");
							if (! $rv) {
								error_log("mail send failed: " . $ugr_eMail);
							}

							}else{//add current user as admin for new group

								changeRole($recID, get_user_id(), "admin", null, false);
							}
							$ret = -$recID;
					}//if $isInsert
					else{
						$ret = $recID;
					}
				}
			}
		} //if column names

		if ($ret==null){
			$ret = "no data supplied for updating $type - $recID";
		}

		return $ret;
	}

	/**
	* deleteUser - Helper function that delete a user and its group relations. It is not possible to delete user if it has record entries
	* @author Artem Osmakov
	* @param $recID user ID to delete
	* @return $ret user id that was deleted or error message
	**/
	function deleteUser($recID) {
		global $db;

		$ret = array();

		$ret2 = checkPermission('user', $recID);
		if($ret2!=null) {
			$ret['error'] = $ret2;
			return $ret;
		}

		$query = "select rec_ID from Records where rec_OwnerUGrpID=$recID limit 1";

		$rows = execSQL($db, $query, null, true);


		if (!is_numeric($rows)) {
			$ret['error'] = "error finding Records for User $recID in deleteUser - ".$rows;
		}else if ($rows>0){
			$ret['error'] = "Error. Deleting User ($recID) with existing Records not allowed";
		} else { // no Records belong this User -  ok to delete this User.

			$checkLastAdmin = checkLastAdmin($recID, null);
			if($checkLastAdmin!=null){
				$ret['error'] = $checkLastAdmin;
				return;
			}

			//delete references from user-group link table
			$query = "delete from sysUsrGrpLinks where ugl_UserID=$recID";
			$rows = execSQL($db, $query, null, true);
			if (!is_numeric($rows) || $rows==0) {
					$ret['error'] = "db error deleting relations for User $recID from sysUsrGrpLinks - ".$rows;
			}else{

				$query = "delete from sysUGrps where ugr_ID=$recID";
				$rows = execSQL($db, $query, null, true);

				if (!is_numeric($rows) || $rows==0) {
					$ret['error'] = "db error deleting of User $recID from sysUGrps - ".$rows;
				} else {
					$ret['result'] = $recID;
				}
			}
		}

		return $ret;
	}

	/**
	* put your comment there...
	*
	* @param mixed $recID - group id to be deleted
	*/
	function deleteGroup($recID) {
		global $db;

		$ret = array();

		$ret2 = checkPermission('group', $recID);
		if($ret2!=null) {
			$ret['error'] = $ret2;
			return $ret;
		}

		$query = "select rec_ID from Records where rec_OwnerUGrpID=$recID limit 1";
		$rows = execSQL($db, $query, null, true);


		if (!is_numeric($rows)) {
			$ret['error'] = "error finding Records for User $recID in deleteGroup - ".$rows;
		}else if ($rows>0){
			$ret['error'] = "Error. Deleting Group ($recID) with existing Records not allowed";
		} else { // no Records belong this User -  ok to delete this User.

			/*
			$query = "select ugl_UserID from sysUsrGrpLinks where ugl_GroupID=$recID limit 1";
			$rows = execSQL($db, $query, null, true);
			if (!is_numeric($rows)) {
				$ret['error'] = "error finding Users for Group $recID in deleteGroup - ".$rows;
			}else if ($rows>0){
				$ret['error'] = "Error. Deleting Group ($recID) with existing Users not allowed";
			}else{
			}*/

			$query = "delete from sysUsrGrpLinks where ugl_GroupID=$recID";
			$rows = execSQL($db, $query, null, true);
			if (!is_numeric($rows) || $rows==0) {
					$ret['error'] = "db error deleting relations for Group $recID from sysUsrGrpLinks - ".$rows;
			}else{
				$query = "delete from sysUGrps where ugr_ID=$recID";
				$rows = execSQL($db, $query, null, true);

				if (!is_numeric($rows) || $rows==0) {
					$ret['error'] = "db error deleting of Group $recID from sysUGrps - ".$rows;
				} else {
					$ret['result'] = $recID;
				}
			}
		}

		return $ret;
	}

	/**
	* put your comment there...
	*
	* @param mixed $recID - user ID
	*/
	function checkLastAdmin($recID, $groupID){
			global $db;
			$query =
"select g1.ugl_GroupID,
(select count(*) from sysUsrGrpLinks as g2 where g1.ugl_GroupID=g2.ugl_GroupID and g2.ugl_Role='admin') as adm
from sysUsrGrpLinks as g1 where g1.ugl_UserID=$recID and g1.ugl_Role='admin'";

//, (select count(*) from sysUsrGrpLinks as g3 where g1.ugl_GroupID=g3.ugl_GroupID and g3.ugl_Role='member') as mem

			if($groupID){
				$query = $query." and g1.ugl_GroupID=$groupID";
			}

error_log(">>>>>>>>>>>>>>>>>>>>	".$query);

			$rows = execSQL($db, $query, null, false);

			if (is_string($rows)){
				$ret['error'] = "db error finding number of possible orphan groups for User $recID from sysUsrGrpLinks - ".$rows;
				return $ret;
			}
			foreach ($rows as $row) {

error_log("ROWS   >>>".$row[1]."   <<<<<<<<<<<<"); //."  ".$row[2].

				if($row[1]<2){ // && $row[2]>0){
					return "error deleting User #$recID since it is the only admin for Group #$row[0]";
				}
			}
			return null;
	}

	/**
	* put your comment there...
	*
	* @param mixed $grpID - group ID
	* @param mixed $recIds - comma separated list of affected user IDs
	* @param mixed $newRole - new role
	*/
	function changeRole($grpID, $recIds, $newRole, $oldRole, $needCheck){
		global $db;

		$ret = array();

		if($needCheck){
			$ret2 = checkPermission('group', $grpID);
			if($ret2!=null) {
				$ret['error'] = $ret2;
				return $ret;
			}
		}

error_log("QQQQQQQQQQQQQQ>>>>>>>>>>>>>>>>>>>>".$recIds."   ".is_numeric($recIds)."<<<<<<");

		if(is_numeric($recIds)){
			$arr = array();
			$arr[0] = $recIds;
		}else{
			$arr = split(",", $recIds);
		}

error_log("QQQQQQQQQQQQQQ>>>>>>>>>>>>>>>>>>>>".$arr[0]);

		//remove from group
		if($newRole=="delete"){

			$ret['results'] = array();
			$ret['errors'] = array();

			foreach ($arr as $userID) {
				$error = checkLastAdmin($userID, $grpID);
				if($error==null){
					$query = "delete from sysUsrGrpLinks where ugl_UserID=$userID and ugl_GroupID=$grpID";
					$rows = execSQL($db, $query, null, true);
					if (!is_numeric($rows) || $rows==0) {
						// error delete reference for this user
						//$ret['error'] = "db error deleting relations for User $recID from sysUsrGrpLinks - ".$rows;
						array_push($ret['errors'], array($userID=>"db error deleting relations"));
					}else{
						array_push($ret['results'], $userID);
					}
				}else{
						array_push($ret['errors'], array($userID=>"last admin in group"));
				}
			}

		}else{

			//insert new roles for non-existing entries
			$query = "INSERT INTO sysUsrGrpLinks (ugl_GroupID, ugl_UserID, ugl_Role) VALUES ";
			$nofirst = false;

			$ret['errors'] = array();

			foreach ($arr as $userID) {

				if($oldRole==="admin" && $newRole==="member"){
					$error = checkLastAdmin($userID, $grpID);
					array_push($ret['errors'], array($userID=>"last admin in group"));
				}else{

					if($nofirst) {
						$query	= $query.", ";
					}
					$query	= $query."($grpID, $userID, '$newRole')";
					$nofirst = true;
				}
			}

			if($nofirst){

				$query	= $query." ON DUPLICATE KEY UPDATE ugl_Role='$newRole'";

				$rows = execSQL($db, $query, null, true);

				if (!is_numeric($rows) || $rows==0) {
					$ret['error'] = "db error changing roles in sysUsrGrpLinks - ".$rows;
				} else {
					$ret['result'] = $recIds;
				}
			}

		}

		return $ret;
	}

	//ARTEM uses mysqli
	/*
	$sql = Statement to execute;
	$parameters = array of type and values of the parameters (if any)
	$close = true to close $stmt (in inserts) false to return an array with the values;
	*/
	function execSQL($mysqli, $sql, $params, $close){

		$result = "unknown error";

		if($params==null || count($params)<1){

			if($result = $mysqli->query($sql)){
				if($close){
					$result = $mysqli->affected_rows;
				}
			}else{
				$result = $mysqli->error;
			}

		}else{ //prepared query

			$stmt = $mysqli->stmt_init();
			$stmt = $mysqli->prepare($sql) or die ("Failed to prepared the statement!");

			call_user_func_array(array($stmt, 'bind_param'), refValues($params));

			$stmt->execute();

			if($close){
				$result = $mysqli->error;
				if($result == ""){
					$result = $mysqli->affected_rows;
				}
			} else {

				$meta = $stmt->result_metadata();

				while ( $field = $meta->fetch_field() ) {
					$parameters[] = &$row[$field->name];
				}

				call_user_func_array(array($stmt, 'bind_result'), refValues($parameters));

				while ( $stmt->fetch() ) {
					$x = array();
					foreach( $row as $key => $val ) {
							$x[$key] = $val;
						}
					$results[] = $x;
				}

				$result = $results;
			}

			$stmt->close();
		}

		return  $result;
	}

	function refValues($arr){
		if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
		{
			$refs = array();
			foreach($arr as $key => $value)
					$refs[$key] = &$arr[$key];
			return $refs;
        }
		return $arr;
	}
?>