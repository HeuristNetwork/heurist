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
* getRegisteredDBs.php
* Returns all databases and their URLs that are registered in the master Heurist index server,
* SIDE ONLY. ONLY ALLOW IN HEURISTSCHOLAR.ORG index database
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


	if (!@$_REQUEST['db']){// be sure to override default this should only be called on the Master Index server so point db master index dbname
		$_REQUEST['db'] = "Heurist_Master_Index";
	}
	require_once(dirname(__FILE__)."/../../../common/config/initialise.php");
	require_once(dirname(__FILE__).'/../../../common/php/dbMySqlWrappers.php');
	mysql_connection_select("hdb_Heurist_Master_Index");

	// Return all registered databases as a json string
	$res = mysql_query("select rec_ID, rec_URL, rec_Title, rec_Popularity, dtl_value as version from Records left join recDetails on rec_ID=dtl_RecID and dtl_DetailTypeID=335 where `rec_RecTypeID`=22");
	$registeredDBs = Array();
	while($registeredDB = mysql_fetch_array($res, MYSQL_ASSOC)) {
		array_push($registeredDBs, $registeredDB);
	}
	$jsonRegisteredDBs = json_encode($registeredDBs);

	echo $jsonRegisteredDBs;

	//BEWARE: If there si some sort of error, nothjign gets returned and this should be trapped at ht otehr end (selectDBForImport.php)

?>