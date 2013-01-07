<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


/* load some very basic HEURIST objects into top.HEURIST */

// session_cache_limiter("private");
define('ISSERVICE',1);
define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);
//ob_start('ob_gzhandler');


require_once(dirname(__FILE__)."/../connect/applyCredentials.php");
//require_once("dbMySqlWrappers.php");
require_once("getRecordInfoLibrary.php");

mysql_connection_db_select(DATABASE);

header("Content-type: text/javascript");

$rv = array();
if(@$_REQUEST['action']=='usageCount'){
	$rv = getRecTypeUsageCount();
}else{
	$rv['rectypes'] = getAllRectypeStructures(false);
	$rv['detailTypes'] = getAllDetailTypeStructures(false);
	$rv['terms'] = getTerms(false);
}
print json_format($rv, true);

ob_end_flush();
?>
