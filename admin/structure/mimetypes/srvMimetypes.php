<?php
//NOT USED IN H4
/*
* Copyright (C) 2005-2016 University of Sydney
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
* srvMimetypes.php
* server methods to manipulate mime types
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

	require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');

	$legalMethods = array(
		"get",
		"search",
		"save",
		"delete");

	if (!@$_REQUEST['method']) {
		die("invalid call to srvMimetypes, method parameter is required");
	}else if(!in_array($_REQUEST['method'], $legalMethods)) {
		die("unsupported method call to srvMimetypes");
	}

	if (!is_logged_in()) {
		header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}

	$defFileExtToMimetype = array(
		  "fxm_Extension"=>"s",
		  "fxm_MimeType"=>"s",
		  "fxm_OpenNewWindow"=>"i",
		  "fxm_FiletypeName" => "s",
		  "fxm_IconFileName"=>"s",
		  "fxm_ImagePlaceholder"=>"s",
		  "fxm_Modified"=>"s");

	header('Content-type: text/javascript');

	$metod = @$_REQUEST['method'];

	if($metod=="search"){

		mysql_connection_select(DATABASE);

		$records = array();
		//$records['list'] = array();

		//loads list of all records
		$query = "select * from defFileExtToMimetype";
		$res = mysql_query($query);

		while ($row = mysql_fetch_assoc($res)) {
			array_push($records, $row);
		}


		print json_format($records);

	}else if($metod=="get"){ //-----------------

		mysql_connection_select(DATABASE);

		$recID = @$_REQUEST['recID'];
		if ($recID==null) {
			die("invalid call to srvMimetypes, recID is required");
		}

		$records = array();
		$records['fieldNames'] = array_keys($defFileExtToMimetype);
		$records['records'] = array();

		$query = "select ".join(",", $records['fieldNames'])." from ".USERS_DATABASE.".defFileExtToMimetype ";

		if($recID!="0"){
			$query = $query." where fxm_Extension='$recID'";
		}else{
			$query = null;
		}

		if($query){
			$res = mysql_query($query);
			while ($row = mysql_fetch_row($res)) {
				$records['records'][$row[0]] = $row;
			}
		}

		print "top.HEURIST.editMimetype = " . json_format($records) . ";\n";
		print "\n";

	}else if($metod=="save"){ //-----------------

		$data  = json_decode(urldecode(@$_REQUEST['data']), true);
		//$recID  = @$_REQUEST['recID'];

		if (!array_key_exists('entity',$data) ||
		!array_key_exists('colNames',$data['entity']) ||
		!array_key_exists('defs',$data['entity'])) {
			die("invalid data structure sent with save method call to srvMimetypes");
		}

		$colNames = $data['entity']['colNames'];

		$rv = array();
		$rv['result'] = array(); //result

		foreach ($data['entity']['defs'] as $recID => $rt) {
			array_push($rv['result'], updateMimetypes($colNames, $recID, $rt));
		}
		print json_format($rv);

	}else if($metod=="delete"){

		$recID  = @$_REQUEST['recID'];
		$rv = array();
		if (!$recID) {
			$rv['error'] = "invalid or not ID sent with delete method call to srvMimetypes";
		}else{
			$rv = deleteMimetypes($recID);
			if (!array_key_exists('error',$rv)) {

			}
		}
		print json_format($rv);

	}

exit();

	/**
	* deleteMimetypes - delete record from
	* @author Artem Osmakov
	* @param $recID record ID to delete
	* @return $ret record id that was deleted or error message
	**/
	function deleteMimetypes($recID) {

		$db = mysqli_connection_overwrite(DATABASE);

		$ret = array();

		$query = "delete from defFileExtToMimetype where fxm_Extension='$recID'";
		$rows = execSQL($db, $query, null, true);
		if (is_string($rows) ) {
			$ret['error'] = "db error deleting record from mime types table - ".$rows;
		}else{
			$ret['result'] = $recID;
		}

		$db->close();

		return $ret;
	}

	/**
	* updateMimetypes
	*
	* @param mixed $colNames
	* @param mixed $recID
	* @param mixed $rt
	*/
	function updateMimetypes($colNames, $recID, $values){

		global $defFileExtToMimetype;

		$ret = null;

		if (count($colNames) && count($values)){

			$db = mysqli_connection_overwrite(DATABASE);

			$isInsert = ($recID<0);

			$query = "";
			$fieldNames = "";
			$parameters = array("");
			$parameters2 = array("");
			$fieldNames = join(",",$colNames);

			foreach ($colNames as $colName) {

				$val = array_shift($values);

				if (array_key_exists($colName, $defFileExtToMimetype))
				{
					if($query!="") $query = $query.",";

					if($isInsert){
						if($colName == "fxm_Extension"){
							$recID = $val;
							$parameters2[0] = $defFileExtToMimetype[$colName]; //take datatype from array
							array_push($parameters2, $val);
						}
						$query = $query."?";
					}else{
						$query = $query."$colName = ?";
					}

					$parameters[0] = $parameters[0].$defFileExtToMimetype[$colName]; //take datatype from array
					array_push($parameters, $val);
				}
			}//for columns

					//check for duplication
			/*if($isInsert){
					$querydup = "select fxm_Extension from defFileExtToMimetype where fxm_Extension=?";
					$rows = execSQL($db, $querydup, $parameters2, false);
					if(is_array(@rows)){
						$ret = "error insert duplicate extension";
						$query = "";
					}
			}*/

			if($query!=""){
				if($isInsert){

					$query = "insert into defFileExtToMimetype (".$fieldNames.") values (".$query.")";
				}else{
					$query = "update defFileExtToMimetype set ".$query." where fxm_Extension = '$recID'";
				}

				$rows = execSQL($db, $query, $parameters, true);

				if ($rows==0 || is_string($rows) ) {
					$oper = (($isInsert)?"inserting":"updating");
					$ret = "Error $oper for Mime types - ".$rows; //$msqli->error;
				} else {
					if($isInsert){
						//$recID = $db->insert_id;
						$ret = "-1";

					}//if $isInsert
					else{
						$ret = 1;
					}
				}
			}

			$db->close();
		}//if column names


		if ($ret==null){
			$ret = "no data supplied for updating Mime types - $recID";
		}

		return $ret;
	}
?>