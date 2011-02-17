<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php
	require_once(dirname(__FILE__).'/common/config/initialise.php');
//error_log("request params = ". $_SERVER["QUERY_STRING"]);
	if (@$_SERVER["QUERY_STRING"]) {
		$q = $_SERVER["QUERY_STRING"];
	}else{
		$q = "db=".HEURIST_DBNAME;
	}
	header('Location: '.HEURIST_URL_BASE.'search/search.html?'.$q);
?>
