<?php

/*<!--
 * registerFile.php, register file that is already on server - returns JSON fileinfo
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


// NOTE: THis file updated 18/11/11 to use proper fiel paths and names for uploaded files rather than bare numbers

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

if (! @$_REQUEST['path']) return; // nothing returned if no ulf_ID parameter

$filename = $_REQUEST['path'];

if(!file_exists($filename) || is_dir($filename)){
	$results = array("error"=>"File $filename not found on server");
}else{
	mysql_connection_db_overwrite(DATABASE);

	$ulf_ID = register_file($filename, '', false);

	if(!is_numeric($ulf_ID)){
		$results = array("error"=>$ulf_ID); //error message
	}else{
		$results = get_uploaded_file_info($ulf_ID);
	}
}

header("Content-type: text/javascript");
print json_format( $results, true );

?>