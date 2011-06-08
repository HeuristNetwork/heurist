<?php
	/**
	* getRegisteredDBs.php - Returns all databases and there URLs that are registered to the master Heurist index server,
	* Juan Adriaanse 27 May 2011. ONLY ALLOW IN HEURISTSCHOLAR.ORG index database
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	require_once(dirname(__FILE__)."/../../common/config/initialise.php");
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    mysql_connection_db_insert("HeuristSystem_Index");

    // Return all registered databases as a json string
    $res = mysql_query("select rec_ID, rec_URL, rec_Title from Records where `rec_RecTypeID`='209'");
    $registeredDBs = Array();
    while($registeredDB = mysql_fetch_array($res, MYSQL_ASSOC)) {
		array_push($registeredDBs, $registeredDB);
    }
    $jsonRegisteredDBs = json_encode($registeredDBs);
    echo $jsonRegisteredDBs;
?>