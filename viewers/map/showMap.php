<?php

	/**
	* showMap.php
	*
	* search records and fills the structure to display on map
	*
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/
	define('ISSERVICE',1);


	require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
	require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
	require_once(dirname(__FILE__)."/../../viewers/map/showMapRequest.php");

	header("Content-type: text/javascript");

	$mapobjects = getMapObjects($_REQUEST);
	print json_format($mapobjects);
	exit();
?>
