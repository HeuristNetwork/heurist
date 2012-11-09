<?php

	/**
	* getRegisteredDBs.php - Returns all databases and their URLs that are registered in the master Heurist index server,
	* Juan Adriaanse 27 May 2011. SERVER SIDE ONLY. ONLY ALLOW IN HEURISTSCHOLAR.ORG index database
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	if (!@$_REQUEST['db']){// be sure to override default this should only be called on the Master Index server so point db master index dbname
		$_REQUEST['db'] = "H3MasterIndex";
	}
	require_once(dirname(__FILE__)."/../../common/config/initialise.php");
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	mysql_connection_db_select("hdb_H3MasterIndex");

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