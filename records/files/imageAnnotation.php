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

?>

<?php

	/**
	* imageAnnotation.php
	*
	* function to search and define image annotations
	*
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	define('ISSERVICE',1);
	define("SAVE_URI", "disabled");

	require_once(dirname(__FILE__).'/../../search/getSearchResults.php');
	require_once(dirname(__FILE__).'/../../records/edit/deleteRecordInfo.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
	//require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

	//mysql_connection_select(DATABASE);
	mysql_connection_overwrite(DATABASE);

	$res = array();

	/*****DEBUG****///error_log(">>>>>".print_r($_REQUEST, true));

	if (array_key_exists('url',$_REQUEST)) {
		$res = getAnnotationsByUrl($_REQUEST['url']);
	}else if (array_key_exists('recid', $_REQUEST)) {
		if (array_key_exists('delete', $_REQUEST)) {
			$res = deleteAnnotationById($_REQUEST['recid']);
		}else{
			$res = getAnnotationsById($_REQUEST['recid']);
		}
	}else if (array_key_exists('listrt', $_REQUEST)) {
		$res = getAnnotationsRectype();
	}

	header("Content-type: text/javascript");
	print json_format($res);


	/**
	* get list of all annotation records: image area + pointer
	*
	*
	*/
	function getAnnotationsRectype(){

		$result = array();
		if (defined('DT_ANNOTATION_RANGE') && defined('DT_ANNOTATION_RESOURCE')){

			//find record types that suits to annotation: image area + pointer
			$query = "select distinct d1.rst_RecTypeID as id, rt.rty_Name as name from defRecTypes rt, defRecStructure d1, defRecStructure d2 where ".
			"rt.rty_ID=d1.rst_RecTypeID and d1.rst_RecTypeID = d2.rst_RecTypeID ".
			" and d1.rst_DetailTypeID=".DT_ANNOTATION_RANGE.
			" and d2.rst_DetailTypeID=".DT_ANNOTATION_RESOURCE;
			///"d2.rst_DetailTypeID in (select dty_ID from defDetailTypes where dty_Type=\"resource\")";

			$fres = mysql_query($query);


			while ($row = mysql_fetch_assoc($fres)) {
				array_push($result, $row);
			}

		}
		return $result;
	}


	/**
	* Search annotations by record id
	* returns JSON array
	*/
	function getAnnotationsById($recid){

		$result = array();
		if (defined('DT_ANNOTATION_RANGE') && defined('DT_ANNOTATION_RESOURCE')){

			$params = array("q"=>"f:".DT_ANNOTATION_RESOURCE."=".$recid, "w"=>BOTH);

			$result = loadSearch($params); //from search/getSearchResults.php - loads array of records based og GET request

			if(!array_key_exists('records',$result) ||  $result['resultCount']==0 ){
				/*****DEBUG****///error_log("EMPTY");
			}
		}
		return $result;

	}

	/**
	* Search annotations by media url
	* returns JSON array
	*/
	function getAnnotationsByUrl($url){

		//find media in recUploadedFiles

		//find record by file id

		//find annotations by record id

	}

	//
	//
	//
	function deleteAnnotationById($rec_id){

		$res = deleteRecord($rec_id);
		/*****DEBUG****///error_log(">>>>".print_r($res, true));
		if( array_key_exists("error", $res) ){
			mysql_query("rollback");
		}else{
			updateRecTypeUsageCount();
			mysql_query("commit");
		}

		return $res;
	}
?>