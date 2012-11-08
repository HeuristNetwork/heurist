<?php

	/**
	* uploadFile.php
	*
	* set of functions
	* 		upload_file - copies temp file to HEURIST_UPLOAD_DIR and register in recUploadedFiles
	*
	* 		register_file - registger the existing file on the server in recUploadedFiles (used in import)
	*
	* 		get_uploaded_file_info  - returns values from recUploadedFiles for given file ID
	*
	* 		getThumbnailURL - find the appropriate detail type for given record ID and returns thumbnail URL
	*
	* 		is_image - detect if resource is image
	*
	*
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	**/

	require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
	require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

	/**
	* Invoked from HAPI.saveFile.php
	*
	* Copies temp file to HEURIST_UPLOAD_DIR and registger in recUploadedFiles
	*
	* Check that the uploaded file has a sane name / size / no errors etc,
	* enter an appropriate record in the recUploadedFiles table,
	* save it to disk,
	* and return the ulf_ID for that record.
	* This will be error message if anything went pear-shaped along the way.
	*
	* @param mixed $name - original file name
	* @param mixed $mimetypeExt - ??
	* @param mixed $tmp_name - temporary name from FILE array
	* @param mixed $error
	* @param mixed $size
	*
	* @return file ID or error message
	*/
	function upload_file($name, $mimetypeExt, $tmp_name, $error, $size, $description, $needConnect) {

		if (! is_logged_in()) return "Not logged in";

		if ($size <= 0  ||  $error) {
			error_log("size is $size, error is $error");
			return $error;
		}

		/* clean up the provided file name -- these characters shouldn't make it through anyway */
		$name = str_replace("\0", '', $name);
		$name = str_replace('\\', '/', $name);
		$name = preg_replace('!.*/!', '', $name);

		if($needConnect){
			mysql_connection_db_overwrite(DATABASE);
		}

		$mimeType = null;

		if($mimetypeExt){ //check extension
			$mimetypeExt = strtolower($mimetypeExt);
			$mimeType = findMimeType($mimetypeExt);
		}

		if (!$mimetypeExt && preg_match('/\\.([^.]+)$/', $name, $matches))
		{	//find the extention
			$extension = strtolower($matches[1]);
			$mimeType = findMimeType($extension);
			/*
			//unfortunately mimeType is not defined for some extensions
			if(!$mimeType){
			return "Error: unsupported extension ".$extension;
			}
			*/
			$mimetypeExt = $extension;
		}

		if ($size && $size < 1024) {
			$file_size = 1;
		}else{
			$file_size = round($size / 1024);
		}

		$res = mysql__insert('recUploadedFiles', array(	'ulf_OrigFileName' => $name,
				'ulf_UploaderUGrpID' => get_user_id(),
				'ulf_Added' => date('Y-m-d H:i:s'),
				'ulf_MimeExt ' => $mimetypeExt,
				'ulf_FileSizeKB' => $file_size,
				'ulf_Description' => $description? $description : NULL,
				'ulf_FilePath' => HEURIST_UPLOAD_DIR,
				'ulf_Parameters' => "mediatype=".getMediaType($mimeType, $mimetypeExt))
		);

		if (! $res) {
			error_log("error inserting file upload info: " . mysql_error());
			$uploadFileError = "Error inserting file upload info into database";
			return $uploadFileError;
		}

		$file_id = mysql_insert_id();
		$filename = "ulf_".$file_id."_".$name;
		mysql_query('update recUploadedFiles set ulf_FileName = "'.$filename.
			'", ulf_ObfuscatedFileID = "' . addslashes(sha1($file_id.'.'.rand())) . '" where ulf_ID = ' . $file_id);
		/* nonce is a random value used to download the file */
		/*****DEBUG****///error_log(">>>>".$tmp_name."  >>>> ".$filename);
		$pos = strpos($tmp_name, HEURIST_UPLOAD_DIR);
		if( is_numeric($pos) && $pos==0 && copy($tmp_name, HEURIST_UPLOAD_DIR . "/" . $filename) )
		{
			unlink($tmp_name);
			return $file_id;

		} else if ($tmp_name==null || move_uploaded_file($tmp_name, HEURIST_UPLOAD_DIR . "/" . $filename)) {

			return $file_id;
		} else {
			/* something messed up ... make a note of it and move on */
			$uploadFileError = "upload file: $name couldn't be saved to upload path definied for db = ". HEURIST_DBNAME;
			error_log($uploadFileError);
			mysql_query('delete from recUploadedFiles where ulf_ID = ' . $file_id);
			return $uploadFileError;
		}
	}

	/**
	* Registger the file on the server in recUploadedFiles
	*
	* It used in import (from db and folder) to register the existing files
	*
	* @param type $fullname - absolute path to file on this server
	* @param type $description
	* @param type $needConnect
	* @return string  new file id
	*/
	function register_file($fullname, $description, $needConnect) {

		if (! is_logged_in()) return "Not logged in";

		/* clean up the provided file name -- these characters shouldn't make it through anyway */
		$fullname = str_replace("\0", '', $fullname);
		$fullname = str_replace('\\', '/', $fullname);
		//$fullname = preg_replace('!.*/!', '', $fullname);

		if (!file_exists($fullname)) {
			return "Error: $fullname file does not exist";
		}
		$size = filesize($fullname);
		if ( (!is_numeric($size)) || ($size <= 0) ) {
			return "Error: size is ".$size;
		}

		if($needConnect){
			mysql_connection_db_overwrite(DATABASE);
		}

		//get folder, extension and filename
		$path_parts = pathinfo($fullname);
		$dirname = $path_parts['dirname']."/";
		$mimetypeExt = strtolower($path_parts['extension']);
		//$filename = $path_parts['filename'];
		$filename = $path_parts['basename'];

		if($mimetypeExt){ //check extension
			$mimeType = findMimeType($mimetypeExt);
			if(!$mimeType){
				return "Error: mimtype for extension $mimetypeExtis is not defined in database";
			}
		}

		if ($size && $size < 1024) {
			$file_size = 1;
		}else{
			$file_size = round($size / 1024);
		}

		//check if such file is already registered
		$res = mysql_query('select ulf_ID from recUploadedFiles '.
			'where ulf_FilePath = "'.addslashes($dirname).
			'" and ulf_FileName = "'.addslashes($filename).'"');

		if (mysql_num_rows($res) == 1) {
			$row = mysql_fetch_assoc($res);
			$file_id = $row['ulf_ID'];
			return $file_id;
		}else{

			$toins = array(	'ulf_OrigFileName' => $filename,
				'ulf_UploaderUGrpID' => get_user_id(),
				'ulf_Added' => date('Y-m-d H:i:s'),
				'ulf_MimeExt ' => $mimetypeExt,
				'ulf_FileSizeKB' => $file_size,
				'ulf_Description' => $description?$description : NULL,
				'ulf_FilePath' => $dirname,
				'ulf_FileName' => $filename,
				'ulf_Parameters' => "mediatype=".getMediaType($mimeType, $mimetypeExt));


			/*****DEBUG****///error_log(">>>>>".print_r($toins,true));

			$res = mysql__insert('recUploadedFiles', $toins);

			if (!$res) {
				return "Error registration file $fullname into database";
			}

			$file_id = mysql_insert_id();

			mysql_query('update recUploadedFiles set ulf_ObfuscatedFileID = "' . addslashes(sha1($file_id.'.'.rand())) . '" where ulf_ID = ' . $file_id);

			return $file_id;

		}
	}

	/**
	* Unregister file: delete record from table and remove file
	*/
	function unregister_for_recid($recid, $needConnect=false){

		if($needConnect){
			mysql_connection_db_overwrite(DATABASE);
		}

		// find all files associated with this record
		$res = mysql_query("select dtl_UploadedFileID from recDetails where dtl_RecID=".$recid);
		while ($row = mysql_fetch_array($res)) {
			deleteUploadedFiles($row[0], false);
		}

		//remove from database
		mysql_query('SET foreign_key_checks = 0');
		mysql_query('delete from recUploadedFiles where ulf_ID in (select dtl_UploadedFileID from recDetails where dtl_RecID="'.$recid.'")');
		mysql_query('SET foreign_key_checks = 1');

		if (mysql_error()) {
			return mysql_error();
		}else{
			return null;
		}
	}


	/**
	*
	*/
	function deleteUploadedFiles($fileid, $needConnect){

		if($needConnect){
			mysql_connection_db_overwrite(DATABASE);
		}

		$filedata = get_uploaded_file_info_internal($fileid, false);
		if($filedata!=null){

			$type_source = $filedata['remoteSource'];
			if ($type_source==null || $type_source=='heurist')  //Local/Uploaded resources
			{
				// set the actual filename. Up to 18/11/11 this is jsut a bare nubmer corresponding with ulf_ID
				// from 18/11/11, it is a disambiguated concatenation of 'ulf_' plus ulf_id plus ulfFileName
				if ($filedata['fullpath']) {
					$filename = $filedata['fullpath']; // post 18/11/11 proper file path and name
				} else {
					$filename = HEURIST_UPLOAD_DIR ."/". $filedata['id']; // pre 18/11/11 - bare numbers as names, just use file ID
				}

				$filename = str_replace('/../', '/', $filename);  // not sure why this is being taken out, pre 18/11/11, unlikely to be needed any more
				$filename = str_replace('//', '/', $filename);

				if(file_exists($filename)){
					unlink($filename);
				}

				//remove thumbnail
				$thumbnail_file = HEURIST_THUMB_DIR."ulf_".$filedata["nonce"].".png";
				if(file_exists($thumbnail_file)){
					unlink($thumbnail_file);
				}
			}
		}
	}


	/**
	* try to detect the source (service) and type of file/media content
	*
	* returns object with 2 properties
	*
	* !!! the same function in smarty/showReps.php
	*/
	function detectSourceAndType($url){

		$source = 'generic';
		$type = 'unknown';

		//1. detect source
		if(strpos($url, 'http://'.HEURIST_HOST_NAME) ==0 && strpos($url, 'records/files/downloadFile.php') >=0){
			$source = 'heurist';
		}else if(strpos($url,'http://www.flickr.com')==0){
			$source = 'flickr';
			$type = 'image';
		}else if(strpos($url, 'http://www.panoramio.com/')==0){
			$source = 'panoramio';
			$type = 'image';
		}else if(preg_match('http://(www.)?locr\.de|locr\.com', $url)){
			$source = 'locr';
			$type = 'image';
			//}else if(link.indexOf('http://www.youtube.com/')==0 || link.indexOf('http://youtu.be/')==0){
		}else if(preg_match('http://(www.)?youtube|youtu\.be', $url)){
			$source = 'youtube';
			$type = 'video';
		}


		if($type=='xml'){

			$extension = 'xml';

		}else if($type=='unknown'){ //try to detect type by extension and protocol

			//get extension from url - unreliable
			$extension = null;
			$ap = parse_url($url);
			if( array_key_exists('path', $ap) ){
				$path = $ap['path'];
				if($path){
					$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
				}
			}
			$mimeType = findMimeType($extension);

			//from query
			if($mimeType==''){

				if( array_key_exists('query', $ap) ){
					$path = $ap['query'];
					if($path){
						$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
					}
				}
				$mimeType = findMimeType($extension);
			}
			//from
			if($mimeType==''){
				$extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
				$mimeType = findMimeType($extension);
			}

			$type = getMediaType($mimeType, $extension);
		}

		return array($source, $type, $extension);
	}

	/**
	* detect media type
	*
	* @todo - use mimetype to detect proper mediaType
	*
	* @param mixed $extension
	*/
	function getMediaType($mimeType, $extension){

		if($extension=="jpg" || $extension=="jpeg" || $extension=="png" || $extension=="gif"){
			$type = 'image';
		}else if($extension=="mp4" || $extension=="mov" || $extension=="avi" || $extension=="flv"){
			$type = 'video';
		}else if($extension=="mp3" || $extension=="wav"){
			$type = 'audio';
		}else if($extension=="html" || $extension=="htm" || $extension=="txt"){
			$type = 'text/html';
		}else if($extension=="pdf" || $extension=="doc" || $extension=="xls"){
			$type = 'document';
		}else if($extension=="swf"){
			$type = 'flash';
		}else if($extension=="xml"){
			$type = 'xml';
		}else{
			$type = null;
		}
		return $type;
	}

	/**
	* verifies the extension and returns mimetype
	*
	* @param mixed $mimetypeExt
	* @return null if extension is not found and mimeType if it is found
	*/
	function findMimeType($mimetypeExt)
	{

		$mimeType = '';
		if($mimetypeExt){
			$mimetypeExt = strtolower($mimetypeExt);

			$fres = mysql_query('select fxm_Extension, fxm_Mimetype from defFileExtToMimetype where fxm_Extension = "'.addslashes($mimetypeExt).'"');
			if (mysql_num_rows($fres) == 1) {
				$res = mysql_fetch_assoc($fres);
				$mimeType = $res['fxm_Mimetype'];
				if($mimeType==null){
					$mimeType=='';
				}
			}

		}

		return $mimeType;
	}


	/**
	* register external URL (see saveRecordDetails.php)
	* $filejson - either url or json string with file data array
	*
	* returns ulf_ID
	*/
	function register_external($filejson)
	{
		$filedata = json_decode($filejson, true);

		//DEBUG
		/*****DEBUG****///error_log("1.>>>>>".$filedata);
		/*****DEBUG****///error_log("2.>>>>>".print_r($filedata, true));


		if(!is_array($filedata)){ //can't parse - assume this is URL - old way

			$filedata = array();
			$url = $filejson;
			//1. get url, source and type
			$acfg = explode('|', $url);
			$filedata['remoteURL'] = $acfg[0];
			$filedata['ext'] = NULL;

			if(count($acfg)<3){
				$oType = detectSourceAndType($url);
				$filedata['remoteSource'] = $oType[0];
				$filedata['mediaType']  = $oType[1];
				$filedata['ext'] = $oType[2];
			}else{
				$filedata['remoteSource'] = $acfg[1];
				$filedata['mediaType'] = $acfg[2];
				if(count($acfg)==4){
					$filedata['ext'] = $acfg[3];
				}
			}
		}

		if(@$filedata['ext']==null && $filedata['mediaType']=="xml"){
			$filedata['ext'] = "xml";
		}
//*****DEBUG****/// error_log("reg remote file data ".print_r($filedata,true));
		$fileparameters = @$filedata['params'] ? $filedata['params'] : "mediatype=".$filedata['mediaType'];
		if(@$filedata['remoteSource'] && $filedata['remoteSource']!='heurist'){ // && $filedata['remoteSource']!='generic'){
			$fileparameters	= $fileparameters."|source=".$filedata['remoteSource'];
		}

		//if id is defined
		if(array_key_exists('id', $filedata) &&  intval($filedata['id'])>0){
			//update
			$file_id = $filedata['id'];
			//ignore registration for already uploaded file
			if(array_key_exists('remoteSource', $filedata) && $filedata['remoteSource']!='heurist'){

				mysql__update('recUploadedFiles','ulf_ID='.$file_id,
					array(
						'ulf_Modified' => date('Y-m-d H:i:s'),
						'ulf_MimeExt ' => $filedata['ext'],
						//'ulf_FileSizeKB' => $file['fileSize'],
						//'ulf_Description' => $file['fileSize'],
						'ulf_ExternalFileReference' => $filedata['remoteURL'],
						'ulf_Parameters' => $fileparameters)
				);

			}
		}else{

			if(!array_key_exists('remoteURL', $filedata) || $filedata['remoteURL']==null || $filedata['remoteURL']==""){
				return null;
			}

			//2. find duplication (the same url)
			if(array_key_exists('remoteSource', $filedata) && $filedata['remoteSource']!='heurist'){
				$res = mysql_query('select ulf_ID from recUploadedFiles '.
					'where ulf_ExternalFileReference = "'.addslashes($filedata['remoteURL']).'"');

				if (mysql_num_rows($res) == 1) {
					$row = mysql_fetch_assoc($res);
					$file_id = $row['ulf_ID'];
					mysql__update('recUploadedFiles','ulf_ID='.$file_id,
						array(
							'ulf_Modified' => date('Y-m-d H:i:s'),
							'ulf_MimeExt ' => $filedata['ext'],
							//'ulf_FileSizeKB' => $file['fileSize'],
							//'ulf_Description' => $file['fileSize'],
							'ulf_Parameters' => $fileparameters)
					);
					return $file_id;
				}
			}

			//3. save into  recUploadedFiles
			$res = mysql__insert('recUploadedFiles', array(
					'ulf_OrigFileName' => '_remote',
					'ulf_UploaderUGrpID' => get_user_id(),
					'ulf_Added' => date('Y-m-d H:i:s'),
					'ulf_MimeExt ' => array_key_exists('ext', $filedata)?$filedata['ext']:NULL,
					'ulf_FileSizeKB' => 0,
					'ulf_Description' => NULL,
					'ulf_ExternalFileReference' => array_key_exists('remoteURL', $filedata)?$filedata['remoteURL']:NULL,
					'ulf_Parameters' => $fileparameters)
			);

			if (!$res) {
/*****DEBUG****///error_log("ERROR Insert record: ".mysql_error());
				return null; //"Error registration remote source  $url into database";
			}

			$file_id = mysql_insert_id();

			mysql_query('update recUploadedFiles set ulf_ObfuscatedFileID = "' . addslashes(sha1($file_id.'.'.rand())) . '" where ulf_ID = ' . $file_id);

		}

		//4. returns ulf_ID
		return $file_id;
	}

	/**
	* Returns values from recUploadedFiles for given file ID
	*
	* used in saveURLasFile and getSearchResults
	*
	* @todo to use in renderRecordData
	*
	* @param mixed $fileID
	* @param mixed $needConnect
	*/
	function get_uploaded_file_info($fileID, $needConnect)
	{
		$res = get_uploaded_file_info_internal($fileID, $needConnect);
		if($res){
			unset($res["parameters"]);
			//unset($res["remoteURL"]);
			unset($res["fullpath"]);
			unset($res["prefsource"]);

			$res = array("file" => $res);
		}
		return $res;
	}

	/**
	* find record id by file id
	*
	* @param mixed $fileID
	* @param mixed $needConnect
	*/
	function get_uploaded_file_recordid($fileID, $needConnect)
	{
		if($needConnect){
			mysql_connection_db_overwrite(DATABASE);
		}

		$recID = null;

		$res = mysql_query("select dtl_RecID from recDetails where dtl_UploadedFileID=".$fileID);
		while ($row = mysql_fetch_array($res)) {
			$recID = $row[0];
			break;
		}

		return $recID;
	}

	/**
	* get file id by record id
	*
	* @param mixed $recID
	* @param mixed $needConnect
	*/
	function get_uploaded_fileid_by_recid($recID, $needConnect)
	{
		if($needConnect){
			mysql_connection_db_overwrite(DATABASE);
		}

		$ulf_id = null;

		$res = mysql_query("select dtl_UploadedFileID from recDetails where dtl_RecID=".$recID." and dtl_UploadedFileID is not null");
		while ($row = mysql_fetch_array($res)) {
			$ulf_id = $row[0];
			break;
		}

		return $ulf_id;
	}

	/**
	* put your comment there...
	*
	* @param mixed $fileID
	* @param mixed $needConnect
	* @return bool
	*/
	function get_uploaded_file_info_internal($fileID, $needConnect)
	{

		if($needConnect){
			mysql_connection_db_overwrite(DATABASE);
		}

		$res = null;

		$fres = mysql_query(//saw NOTE! these field names match thoses used in HAPI to init an HFile object.
			'select ulf_ID as id,
			ulf_ObfuscatedFileID as nonce,
			ulf_OrigFileName as origName,
			ulf_FileSizeKB as fileSize,
			fxm_MimeType as mimeType,
			ulf_Added as date,
			ulf_Description as description,
			ulf_MimeExt as ext,
			ulf_ExternalFileReference as remoteURL,
			ulf_Parameters as parameters,
			concat(ulf_FilePath,ulf_FileName) as fullpath,
			ulf_PreferredSource as prefsource

			from recUploadedFiles left join defFileExtToMimetype on ulf_MimeExt = fxm_Extension
			where '.(is_numeric($fileID)
				?'ulf_ID = '.intval($fileID)
				:'ulf_ObfuscatedFileID = "'.addslashes($fileID).'"') );

		if (mysql_num_rows($fres) == 1) {

			$res = mysql_fetch_assoc($fres);

			$origName = urlencode($res["origName"]);

			$thumbnail_file = "ulf_".$res["nonce"].".png";
			if(file_exists(HEURIST_THUMB_DIR.$thumbnail_file)){
				$res["thumbURL"] = HEURIST_THUMB_URL_BASE.$thumbnail_file;
			}else{
				$res["thumbURL"] =
				HEURIST_URL_BASE."common/php/resizeImage.php?".
				(defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=".$res["nonce"];
			}


			$downloadURL = HEURIST_URL_BASE."records/files/downloadFile.php/".$origName."?".
			(defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=".$res["nonce"];

			if($res["remoteURL"]!=null || $res["prefsource"]=="external") {
				$res["URL"] = $res["remoteURL"];
			}else{
				$res["URL"] = $downloadURL;
			}

			$params = parseParameters($res["parameters"]);
			$res["mediaType"] =	(array_key_exists('mediatype', $params))?$params['mediatype']:null;
			$res["remoteSource"] = (array_key_exists('source', $params))?$params['source']:null;


			$type_source = $res['remoteSource'];
			if (!($type_source==null || $type_source=='heurist')){ //verify that this is actually remote resource
				if( $res['fullpath'] && file_exists($res['fullpath']) ){
					$res['remoteSource'] = 'heurist';
				}
			}

			//
			//@todo - add special parameters for specific sources and media types
			// QUESTION - store it in database? Or create on-fly??
			//
			if($res["remoteSource"]=="youtube" || $res["mediaType"]=="image" || $res["mediaType"]=="video" || $res["mediaType"]=="audio"){
				$res["playerURL"] =	$downloadURL."&player=yes";
			}

			//$res = array("file" => $res);
		}

		return $res;

	}

	/**
	* put your comment there...
	*
	* @param mixed $params
	*/
	function parseParameters($params){
		$op = array();
		if($params){
			$pairs = explode('|', $params);
			foreach ($pairs as $pair) {
				list($k, $v) = explode("=", $pair); //array_map("urldecode", explode("=", $pair));
				$op[$k] = $v;
			}
		}
		return $op;
	}

	/**
	* Find the appropriate detail type for given record ID
	* and
	* returns thumbnail URL or empty string
	*
	* used in getResultsPageAsync.php and showMap.php
	*
	* @param mixed $recordId
	*/
	function getThumbnailURL($recordId){

		$assocDT = (defined('DT_FILE_RESOURCE')?DT_FILE_RESOURCE:0);
		$logoDT = (defined('DT_LOGO_IMAGE')?DT_LOGO_IMAGE:0);
		$thumbDT = (defined('DT_THUMBNAIL')?DT_THUMBNAIL:0);
		$imgDT = (defined('DT_IMAGES')?DT_IMAGES:0); //deprecated
		$otherDT = (defined('DT_OTHER_FILE')?DT_OTHER_FILE:0);
		//url details
		$thumbUrlDT = (defined('DT_THUMB_IMAGE_URL')?DT_THUMB_IMAGE_URL:0); //deprecated
		$fullUrlDT = (defined('DT_FULL_IMAG_URL')?DT_FULL_IMAG_URL:0); //deprecated
		$webIconDT = (defined('DT_WEBSITE_ICON')?DT_WEBSITE_ICON:0);

		$thumb_url = "";
		// 223  Thumbnail
		// 222  Logo image
		// 224  Images
		//check file type details for a something to represent this record as an icon
		if ( $thumbDT || $logoDT || $imgDT || $assocDT || $otherDT) {
			$squery = "select recUploadedFiles.*".
			" from recDetails".
			" left join recUploadedFiles on ulf_ID = dtl_UploadedFileID".
			" left join defFileExtToMimetype on fxm_Extension = ulf_MimeExt".
			" where dtl_RecID = $recordId" .
			" and dtl_DetailTypeID in ($thumbDT,$logoDT,$imgDT,$assocDT,$otherDT)".	// no dty_ID of zero so undefined are ignored
			" and fxm_MimeType like 'image%'".
			" order by".
			($thumbDT?		" dtl_DetailTypeID = $thumbDT desc,"	:"").
			($logoDT?		" dtl_DetailTypeID = $logoDT desc,"		:"").
			($imgDT?		" dtl_DetailTypeID = $imgDT desc,"		:"").
			" dtl_DetailTypeID".	// no preference on associated or other files just select the first
			" limit 1";
			/*****DEBUG****///error_log(">>>>>>>>>>>>>>>>>>>>>>>".$squery);
			$res = mysql_query($squery);

			if ($res && mysql_num_rows($res) == 1) {
				$file = mysql_fetch_assoc($res);

				$thumbnail_file = "ulf_".$file['ulf_ObfuscatedFileID'].".png";
				if(file_exists(HEURIST_THUMB_DIR.$thumbnail_file)){
					$thumb_url = HEURIST_THUMB_URL_BASE.$thumbnail_file;
				}else{
					$thumb_url = HEURIST_BASE_URL."common/php/resizeImage.php?db=".HEURIST_DBNAME."&ulf_ID=".$file['ulf_ObfuscatedFileID'];
				}
			}
		}
		//check freetext (url) type details for a something to represent this record as an icon
		if( $thumb_url == "" && ($thumbUrlDT || $fullUrlDT || $webIconDT)) {
			$squery = "select dtl_Value".
			" from recDetails".
			" where dtl_RecID = $recordId" .
			" and dtl_DetailTypeID in ($thumbUrlDT,$fullUrlDT,$webIconDT)".	// no dty_ID of zero so undefined are ignored
			" order by".
			($thumbUrlDT?	" dtl_DetailTypeID = $thumbUrlDT desc,"		:"").
			($fullUrlDT?	" dtl_DetailTypeID = $fullUrlDT desc,"		:"").
			" dtl_DetailTypeID".	// anythingelse is last
			" limit 1";

			/*****DEBUG****///error_log("2.>>>>>>>>>>>>>>>>>>>>>>>".$squery);
			$res = mysql_query($squery);

			if ($res && mysql_num_rows($res) == 1) {
				$dRow = mysql_fetch_assoc($res);
				if ( $fullUrlDT &&  $dRow['dtl_DetailTypeID'] == $fullUrlDT) {
					$thumb_url = HEURIST_BASE_URL."common/php/resizeImage.php?db=".HEURIST_DBNAME."&file_url=".htmlspecialchars($dRow['dtl_Value']);
				}else{
					$thumb_url = "".htmlspecialchars(addslashes($row['dtl_Value']));
				}
			}
		}

		return $thumb_url;
	}

	/**
	*  detect if resource is image
	*
	* @param mixed $filedata - see get_uploaded_file_info
	*/
	function is_image($filedata){

		return ($filedata['mediaType'] == 'image' ||
			$filedata['mimeType'] == 'image/jpeg'  ||  $filedata['mimeType'] == 'image/gif'  ||  $filedata['mimeType'] == 'image/png');

	}

?>