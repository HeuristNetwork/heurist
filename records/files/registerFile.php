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



/*<!--
 * registerFile.php, register file that is already on server - returns JSON fileinfo
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


// NOTE: THis file updated 18/11/11 to use proper fiel paths and names for uploaded files rather than bare numbers
//http://heuristscholar.org/h3-ao/records/files/registerFile.php?db=artem2

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");
/*
print 'select rec_ID from Records where rec_ID in (';
for ($i = 152; $i <= 6152; $i++) {
print $i.",";
}
*/
/*
mysql_connection_overwrite(DATABASE);

$x = -170;
$y = 80;

for ($i = 1; $i <= 6000; $i++) {

$title = 'Record # '.$i;

$q = "insert into Records (rec_RecTypeID, rec_Title, rec_AddedByUGrpID, rec_OwnerUGrpID) VALUES (21, '$title',  2, 1)";
mysql_query($q);

$err = mysql_error();
if($err){
	print $q."<br>";
	print "1.>>>".mysql_error()."<br>";
}

$recid = mysql_insert_id();

$q = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_Geo, dtl_ValShortened) values ($recid, 312, 'p', GeomFromText('POINT($x $y)'), 'p')";
mysql_query($q);
$err = mysql_error();
if($err){
	print $q."<br>";
	print "2.>>>".mysql_error()."<br>";
}

$q = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_Geo, dtl_ValShortened) values ($recid, 1, '$title', null, '$title')";
mysql_query($q);
$err = mysql_error();
if($err){
	print $q."<br>";
	print "3.>>>".mysql_error()."<br>";
}

$x = $x + 4.4155844;
if($x>170){
	$x = -170;
	$y = $y - 2.077922;
}
}
*/
/*
if (! @$_REQUEST['path']) return; // nothing returned if no ulf_ID parameter

$filename = $_REQUEST['path'];

if(!file_exists($filename) || is_dir($filename)){
	$results = array("error"=>"File $filename not found on server");
}else{
	mysql_connection_overwrite(DATABASE);

	$ulf_ID = register_file($filename, '', false);

	if(!is_numeric($ulf_ID)){
		$results = array("error"=>$ulf_ID); //error message
	}else{
		$results = get_uploaded_file_info($ulf_ID);
	}
}

header("Content-type: text/javascript");
print json_format( $results, true );
*/
?>