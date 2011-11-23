<?php

/*<!--
 * downloadFile.php, returns an attached file requested using the obfuscated file identifier
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


// NOTE: THis file updated 18/11/11 to use proper fiel paths and names for uploaded files rather than bare numbers

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_db_select(DATABASE);


// May be best to avoid the possibility of somebody harvesting ulf_ID=1, 2, 3, ...
// so the files are indexed by the SHA-1 hash of the concatenation of the ulf_ID and a random integer.

if (! @$_REQUEST['ulf_ID']) return; // nothing returned if no ulf_ID parameter

$res = mysql_query('select * from recUploadedFiles where ulf_ObfuscatedFileID = "' . addslashes($_REQUEST['ulf_ID']) . '"');
if (mysql_num_rows($res) != 1) return; // nothign returned if parameter does not match one and only one row

$file = mysql_fetch_assoc($res);

// try and work out mime type, first stored in table and failign that look at extension of original file name
$mimeExt = '';
if ($file['ulf_MimeExt']) {
	$mimeExt = $file['ulf_MimeExt'];
} else {
	preg_match('/\\.([^.]+)$/', $file["ulf_OrigFileName"], $matches);	//find the extention
	$mimeExt = $matches[1];
}
if ($mimeExt) {
	$mres = mysql_query("select * from defFileExtToMimetype where fxm_Extension = '$mimeExt'");
}

// set the mime type, set to binary if mime type unknown
$mimeType = mysql_fetch_assoc($mres);
if (@$mimeType['fxm_MimeType']) {
	header('Content-type: ' .$mimeType['fxm_MimeType']);
}else{
	header('Content-type: binary/download');
}

// set the actual filename. Up to 18/11/11 this is jsut a bare nubmer corresponding with ulf_ID
// from 18/11/11, it is a disambiguated concatenation of 'ulf_' plus ulf_id plus ulfFileName
if ($file['ulf_FileName']) {
	$filename = $file['ulf_FilePath'].$file['ulf_FileName']; // post 18/11/11 proper file path and name
} else {
	$filename = HEURIST_UPLOAD_DIR ."/". $file['ulf_ID']; // pre 18/11/11 - bare numbers as names, just use file ID
}


//error_log("filename = $filename and mime = ".$mimeType['fxm_MimeType']. " mimeext=".$mimeExt." mysqlerr = ".mysql_error());
//error_log("filename = ".$filename);
$filename = str_replace('/../', '/', $filename);  // not sure why this is being taken out, pre 18/11/11, unlikely to be needed any more
$filename = str_replace('//', '/', $filename);
//error_log("filename = ".$filename);
if(false)
{
/*
	todo: waht is all this and when was it removed? Could it be useful for the furture. ? check with Artem, may be work related to kmls mid Nov 2011
	artem: THIS IS FOR showMap - to support kmz in timeline, it extracts kmz file and sends as kml to client side

	($mimeExt=="kmz"){
	$zip=zip_open($filename);
	if(!$zip) {return("Unable to proccess file '{$filename}'");}
	$e='';
    while($zip_entry=zip_read($zip)) {
       $zdir=dirname(zip_entry_name($zip_entry));
       $zname=zip_entry_name($zip_entry);

       if(!zip_entry_open($zip,$zip_entry,"r")) {$e.="Unable to proccess file '{$zname}'";continue;}
       //if(!is_dir($zdir)) mkdirr($zdir,0777);

       #print "{$zdir} | {$zname} \n";

       $zip_fs=zip_entry_filesize($zip_entry);
       if(empty($zip_fs)) continue;

       $zz=zip_entry_read($zip_entry, $zip_fs);

       $zname = HEURIST_UPLOAD_DIR."/".$zname;
error_log(">>>>>>>>>>".$zname);
       $z=fopen($zname,"w");
       fwrite($z,$zz);
       fclose($z);
       zip_entry_close($zip_entry);

    }
    zip_close($zip);

	readfile($zname);
    unlink($z);
*/
}else{
	readfile($filename);
}
?>
