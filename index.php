<?php

/**
 * index.php, the main entry point to Heurist, redirects to the search page
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/
	define('ROOTINIT',"1");

	require_once(dirname(__FILE__).'/common/config/initialise.php');
	// correct parameters, connection, existence of database are all checked in initialise.php

	if (@$_SERVER["QUERY_STRING"]) {
		$q = $_SERVER["QUERY_STRING"];
		if (!preg_match("/db=/",$q)){
			$q .= "&db=".HEURIST_DBNAME;
		}
	}else{
		$q = "db=".HEURIST_DBNAME;
	}

		header('Location: '.HEURIST_URL_BASE.'search/search.html?'.$q);


?>

