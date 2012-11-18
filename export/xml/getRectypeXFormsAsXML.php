<?php

	/* getDBStructureAsforms.php - returns database definitions (rectypes, details etc.)
	* as form data ready for use in mobile app - primarily intended for NeCTAR FAIMS project
	*
	* @Author Stephen White
	* @author Artem Osmakov
	* @copyright (C) 2012 AeResearch University of Sydney.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @param includeUgrps=1 will output user and group information in addition to definitions
	* @param approvedDefsOnly=1 will only output Reserved and Approved definitions
	* @todo
	*
	*
	-->*/

	require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
	require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
	require_once(dirname(__FILE__).'/../../admin/describe/rectypeXFormLibrary.php');

	// Deals with all the database connections stuff
	//define("DT_DRAWING","2-59");
	mysql_connection_db_select(DATABASE);
	if(mysql_error()) {
		die("Could not get database structure from given database source, MySQL error - unable to connect to database.");
	}
	if (! is_logged_in()) {
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
		return;
	}
	if (! is_admin()) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must be logged in as system administrator to export structure as forms</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}

	$rtyID = (@$_REQUEST['rtyID'] ? $_REQUEST['rtyID'] : null);
	if (! $rtyID) {
		print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You must supply a rectype ID</span><p></div></div></body></html>";
		return;
	}

	list($form,$rtName,$rtConceptID,$rtDescription,$report) = buildform($rtyID);
	if(!$form){
		echo $report;
	}else{
		echo $form;
	}
	return;
?>
