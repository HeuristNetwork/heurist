<?php

    /*
    * Copyright (C) 2005-2016 University of Sydney
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
    set of functions
    *     upload_file - copies temp file to HEURIST_FILES_DIR and register in recUploadedFiles
    *     register_file - registger the existing file on the server in recUploadedFiles (used in import)
    *     get_uploaded_file_info  - returns values from recUploadedFiles for given file ID
    *     getThumbnailURL - find the appropriate detail type for given record ID and returns thumbnail URL
    *     is_image - detect if resource is image
    *
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Stephen White
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2016 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  Records/Util
    * @todo        rename functions to match global convention
    */


    require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
    require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
    require_once("fileUtils.php");

    /**
    * Invoked from HAPI.saveFile.php
    *
    * Copies temp file to HEURIST_FILES_DIR and registger in recUploadedFiles
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
            return "File size recognized as 0. Either file size more than php upload_max_filesize or file is corrupted. Error: $error";
        }
        
        /* clean up the provided file name -- these characters shouldn't make it through anyway */
        $name = str_replace("\0", '', $name);
        $name = str_replace('\\', '/', $name);
        $name = preg_replace('!.*/!', '', $name);

        if($needConnect){
            mysql_connection_overwrite(DATABASE);
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
        
        $is_image = (strpos($mimeType,'image')===0); 
        
        //limit the size upload file to 50Mb and 120Mpix for images       
        if($size>50*1048576){

            $uploadFileError = 'The file you are attempting to upload exceeds the limit set for this system (50 Mbytes - this maximum can be reset by the system administrator). File size is restricted both to avoid excessive server space usage'
            .($is_image?' and because very large images are not generally useful in a web context. Please rescale images to an appropriate size before upload (we suggest a good quality JPG)':'').'. (File size='.round($size/1048576).'Mb)';
            return $uploadFileError;
            
        }else if($is_image && file_exists($tmp_name)) {
                $imageInfo = getimagesize($tmp_name); 
                $memoryNeeded = 0;
                
                if(is_array($imageInfo)){
                    if(array_key_exists('channels', $imageInfo) && array_key_exists('bits', $imageInfo)){
                        $memoryNeeded = round(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $imageInfo['channels'] / 8 + Pow(2,16)) * 1.65); 
                    }else{ //width x height
                        $memoryNeeded = round($imageInfo[0] * $imageInfo[1]*3);  
                    } 
                }
                if($memoryNeeded>120*1048576){  
                    $uploadFileError = 'The file you are attempting to upload exceeds the limit set for this system (120 MPix - this maximum can be reset by the system administrator). File size is restricted both to avoid excessive server space usage and because very large images are not generally useful in a web context. Please rescale images to an appropriate size before upload (we suggest a good quality JPG). (Image resolution='.round($memoryNeeded/1048576).'M)';
                    return $uploadFileError;
                }
        }

        $res = mysql__insert('recUploadedFiles', array(	'ulf_OrigFileName' => $name,
            'ulf_UploaderUGrpID' => get_user_id(),
            'ulf_Added' => date('Y-m-d H:i:s'),
            'ulf_MimeExt ' => $mimetypeExt,
            'ulf_FileSizeKB' => $file_size,
            'ulf_Description' => $description? $description : NULL,
            'ulf_FilePath' => 'file_uploads/', //relative path to HEURIST_FILESTORE_DIR - db root
            'ulf_Parameters' => "mediatype=".getMediaType($mimeType, $mimetypeExt))
        );
                                             
        if (! $res) {
            
            $uploadFileError = "Error inserting file metadata or unable to recognise uploaded file format. '.
                'This generally means that the mime type for this file has not been defined for this database (common mime types are defined by default). '.
                'Please add mime type from Database > Administration > Structure > Define mime types. '.
                'Otherwise please contact your system administrator or the Heurist developers.";
            return $uploadFileError;
        }

        $file_id = mysql_insert_id();
        $filename = "ulf_".$file_id."_".$name;
        mysql_query('update recUploadedFiles set ulf_FileName = "'.$filename.
            '", ulf_ObfuscatedFileID = "' . addslashes(sha1($file_id.'.'.rand())) . '" where ulf_ID = ' . $file_id);
        /* nonce is a random value used to download the file */

        if(!file_exists($tmp_name)){
error_log("NOT FOUND ".$tmp_name);
        }

        $pos = strpos($tmp_name, HEURIST_FILES_DIR);
        if( copy($tmp_name, HEURIST_FILES_DIR .  $filename) ) 
        {
            //remove temp file
            unlink($tmp_name);
            return $file_id;

        }
        /*else if ($tmp_name==null || move_uploaded_file($tmp_name, HEURIST_FILES_DIR . $filename)) {  //move file into upload folder

            return $file_id;
        }*/
        /*

        $isError = move_uploaded_file($tmp_name, HEURIST_FILES_DIR . $filename);
error_log("MOVE ".$tmp_name.">>>".HEURIST_FILES_DIR . $filename.">>>>error=".$isError);
        if($isError==true){
            return $file_id;
        }else*/

        if(true){

            /* something messed up ... make a note of it and move on */
            $uploadFileError = "upload file: $name couldn't be saved to upload path definied for db = "
            . HEURIST_DBNAME." (".HEURIST_FILES_DIR."). Please ask your system administrator to correct the path and/or permissions for this directory";
            error_log($uploadFileError);
            mysql_query('delete from recUploadedFiles where ulf_ID = ' . $file_id);
            return $uploadFileError;
        }
    }

    /**
    * check if file is already registered
    */
    function check_if_register_file($fullname, $needConnect) {
        
        if($needConnect){
            mysql_connection_overwrite(DATABASE);
        }
        $path_parts = pathinfo($fullname);
        $dirname = $path_parts['dirname'].'/';
        $filename = $path_parts['basename'];
        
        // get relative path to db root folder
        $relative_path = getRelativePath(HEURIST_FILESTORE_DIR, $dirname);
      
        $res = mysql_query('select ulf_ID from recUploadedFiles '
            .'where ulf_FileName = "'.mysql_real_escape_string($filename).'" and '
            .' (ulf_FilePath = "file_uploads/" or ulf_FilePath = "'.mysql_real_escape_string($relative_path).'")');

        if (mysql_num_rows($res) == 1) {
            $row = mysql_fetch_assoc($res);
            $file_id = $row['ulf_ID'];
            return $file_id;
        }else{
            return null;
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
            mysql_connection_overwrite(DATABASE);
        }

        //check if such file is already registered
        $file_id = check_if_register_file($fullname, false);
        if($file_id>0){
            return $file_id;
        }
        
        //get folder, extension and filename
        $path_parts = pathinfo($fullname);
        $dirname = $path_parts['dirname'].'/';
        $mimetypeExt = strtolower($path_parts['extension']);
        //$filename = $path_parts['filename'];
        $filename = $path_parts['basename'];

        if($mimetypeExt){ //check extension
            $mimeType = findMimeType($mimetypeExt);
            if(!$mimeType){
                return "Error: mimtype for extension $mimetypeExt is is not defined in database";
            }
        }
        

        if ($size && $size < 1024) {
            $file_size = 1;
        }else{
            $file_size = round($size / 1024);
        }

        // get relative path to db root folder
        $relative_path = getRelativePath(HEURIST_FILESTORE_DIR, $dirname);

        $toins = array(	'ulf_OrigFileName' => $filename,
            'ulf_UploaderUGrpID' => get_user_id(),
            'ulf_Added' => date('Y-m-d H:i:s'),
            'ulf_MimeExt ' => $mimetypeExt,
            'ulf_FileSizeKB' => $file_size,
            'ulf_Description' => $description?$description : NULL,
            'ulf_FilePath' => $relative_path,
            'ulf_FileName' => $filename,
            'ulf_Parameters' => "mediatype=".getMediaType($mimeType, $mimetypeExt));



        $res = mysql__insert('recUploadedFiles', $toins);

        if (!$res) {
            return "Error registration file $fullname into database";
        }

        $file_id = mysql_insert_id();

        mysql_query('update recUploadedFiles set ulf_ObfuscatedFileID = "' . addslashes(sha1($file_id.'.'.rand())) . '" where ulf_ID = ' . $file_id);

        return $file_id;

    }

    /**
    * Unregister file: delete record from table and remove file
    * (not used)
    */
    function unregister_for_recid($recid, $needConnect=false){

        if($needConnect){
            mysql_connection_overwrite(DATABASE);
        }

        unregister_for_recid2($recid, true);
    }

    /**
    * Unregister file: delete record from table and remove file
    */
    function unregister_for_recid2($recid, $needDelete){

        $ulf_ToDelete = array();
        $query = "select dtl_UploadedFileID from recDetails where dtl_RecID=".$recid;
        $res = mysql_query($query);
        while ($row = mysql_fetch_array($res)) {
            $ulf_ID = $row[0];
            if($ulf_ID>0){
            //verify that other records does not refer to this file
            $query = 'select count(*) from recDetails where dtl_UploadedFileID='.$ulf_ID;
            $res2 = mysql_query($query);
            if($res2){
                $cnt = mysql_fetch_array($res2);
                if($cnt[0]==1){
                    array_push($ulf_ToDelete, $ulf_ID);
                }
            }
            }
        }

        if(count($ulf_ToDelete)>0){
        
            if($needDelete){
                foreach ($ulf_ToDelete as $ulf_ID) {
                    deleteUploadedFiles($ulf_ID);
                }
            }
            
            //remove from database
            mysql_query('SET foreign_key_checks = 0');
            mysql_query('delete from recUploadedFiles where ulf_ID in ('.implode(',',$ulf_ToDelete).')');
            mysql_query('SET foreign_key_checks = 1');

            if (mysql_error()) {
                return mysql_error();
            }
        
        }
        return null;
        
        /* OLD WAY
        if($needDelete){
            // find all files associated with this record
            $query = "select dtl_UploadedFileID from recDetails where dtl_RecID=".$recid;
            $res = mysql_query($query);
            while ($row = mysql_fetch_array($res)) {
                deleteUploadedFiles($row[0]);
            }
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
        */
    }


    /**
    *
    */
    function deleteUploadedFiles($fileid){

        /*if($needConnect){
        mysql_connection_overwrite(DATABASE);
        }*/

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
                    $filename = HEURIST_FILESTORE_DIR ."/". $filedata['id']; // pre 18/11/11 - bare numbers as names, just use file ID
                }

                //$filename = str_replace('/../', '/', $filename);  // not sure why this is being taken out, pre 18/11/11, unlikely to be needed any more
                $filename = str_replace('//', '/', $filename);
                $filename = str_replace('\\', '/', $filename);

                //do not delete files that are not in root folder, they may be indexed/imported by "filez in situ" utility
                $path_parts = pathinfo($filename);
                $dirname = $path_parts['dirname']."/";

                //HEURIST_FILESTORE root or file_uploads subfolder  
                if( $dirname == HEURIST_FILESTORE_DIR || $dirname == HEURIST_FILES_DIR ){
                    //remove physically from file_uploads or db root only
                    if(file_exists($filename)){
                        unlink($filename);
                    }
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
        if(strpos($url, $REQUEST_PROTOCOL . "://" . HEURIST_SERVER_NAME)===0 && strpos($url, 'records/files/downloadFile.php') >=0){
            $source = 'heurist';
        }else if(strpos($url,'http://www.flickr.com')===0){
            $source = 'flickr';
            $type = 'image';
        }else if(strpos($url, 'http://www.panoramio.com/')===0){
            $source = 'panoramio';
            $type = 'image';
        }else if(preg_match('http://(www.)?locr\.de|locr\.com', $url)){
            $source = 'locr';
            $type = 'image';
            //}else if(link.indexOf('http://www.youtube.com/')==0 || link.indexOf('http://youtu.be/')==0){
        }else if(preg_match('http://(www.)?youtube|youtu\.be', $url)){
            $source = 'youtube';
            $type = 'video';
        }else if( strpos($url, 'https://docs.google.com/file')===0) {
            $source = 'gdrive';
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

        //update it in initViewer.js
        if($extension=="jpg" || $extension=="jpeg" || $extension=="png" || $extension=="gif"){
            $type = 'image';

        }else if($extension=="mp4" || $extension=="mov" || $extension=="avi" || $extension=="webm" || $extension=="flv" || $extension=="qt" || $extension=="swf" || $extension=="wmv"){
            $type = 'video';
        }else if($extension=="mp3" || $extension=="wav" || $extension=="ogg" || $extension=="wma"){
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
        if($filejson){
            $filejson = str_replace('\\"','"',$filejson);
        }
        $filedata = json_decode($filejson, true);


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
            mysql_connection_overwrite(DATABASE);
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
            mysql_connection_overwrite(DATABASE);
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
    *
    *
    *
    * @param mixed $path
    */
    function resolveFilePath($path){

            if( $path && !file_exists($path) ){
                chdir(HEURIST_FILESTORE_DIR);  // relatively db root
                $fpath = realpath($path);
                if(file_exists($fpath)){
                    return $fpath;
                }else{
                    chdir(HEURIST_FILES_DIR);          // relatively file_uploads 
                    $fpath = realpath($path);
                    if(file_exists($fpath)){
                        return $fpath;
                    }else{
                        //realpath gives real path on remote file server
                        if(strpos($path, '/srv/HEURIST_FILESTORE/')===0){
                            $fpath = str_replace('/srv/HEURIST_FILESTORE/', HEURIST_UPLOAD_ROOT, $path);
                            if(file_exists($fpath)){
                                return $fpath;
                            }
                        }else
                        if(strpos($path, '/misc/heur-filestore/')===0){
                            $fpath = str_replace('/misc/heur-filestore/', HEURIST_UPLOAD_ROOT, $path);
                            if(file_exists($fpath)){
                                return $fpath;
                            }
                        }
                        
                    }
                }
            }

            return $path;
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
            mysql_connection_overwrite(DATABASE);
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
            ulf_FilePath, ulf_FileName,
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
                $res["thumbURL"] = HEURIST_THUMB_URL.$thumbnail_file;
            }else{
                $res["thumbURL"] =
                HEURIST_BASE_URL."common/php/resizeImage.php?".
                (defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=".$res["nonce"];
            }

            

            $downloadURL = HEURIST_BASE_URL."records/files/downloadFile.php/".$origName."?".
            (defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=".$res["nonce"];

            if($res["remoteURL"]!=null || $res["prefsource"]=="external") {
                $res["URL"] = $res["remoteURL"];
            }else{
                $res["URL"] = $downloadURL;
            }

            if(@$res['ulf_FilePath'] || @$res['ulf_FileName']){
                $res['fullpath'] = @$res['ulf_FilePath'].@$res['ulf_FileName'];
            }
            //add database media storage folder for relative paths
            $res['fullpath'] = resolveFilePath(@$res['fullpath']);

            $params = parseParameters($res["parameters"]);
            $res["mediaType"] =	(array_key_exists('mediatype', $params))?$params['mediatype']:null;
            $res["remoteSource"] = (array_key_exists('source', $params))?$params['source']:null;

            $type_source = $res['remoteSource'];
            if ($type_source==null || $type_source!='heurist'){ //verify that this is actually remote resource
                if( @$res['fullpath'] && file_exists($res['fullpath']) ){
                    $res['remoteSource'] = 'heurist';
                }
            }
            
            //
            //@todo - add special parameters for specific sources and media types
            // QUESTION - store it in database? Or create on-fly??
            //
            if($res["remoteSource"]=="youtube" || $res["remoteSource"]=="gdrive"
                || $res["mediaType"]=="image" || $res["ext"] == "pdf" ||
                $res["mediaType"]=="video" || $res["mediaType"]=="audio"
            ){
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
                if(strpos($pair,'=')>0){
                    list($k, $v) = explode("=", $pair); //array_map("urldecode", explode("=", $pair));
                    $op[$k] = $v;
                }
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
        $squery = "select rec_RecTypeID".
        " from Records".
        " where rec_ID = $recordId";
        $res = mysql_query($squery);
        $row = mysql_fetch_assoc($res);
        $rtyID = $row["rec_RecTypeID"];
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
            " and (fxm_MimeType like 'image%' or ulf_Parameters like '%mediatype=image%')".
            " order by".
            ($thumbDT?		" dtl_DetailTypeID = $thumbDT desc,"	:"").
            ($logoDT?		" dtl_DetailTypeID = $logoDT desc,"		:"").
            ($imgDT?		" dtl_DetailTypeID = $imgDT desc,"		:"").
            " dtl_DetailTypeID".	// no preference on associated or other files just select the first
            " limit 1";
            $res = mysql_query($squery);

            if ($res && mysql_num_rows($res) == 1) {
                $file = mysql_fetch_assoc($res);

                $thumbnail_file = "ulf_".$file['ulf_ObfuscatedFileID'].".png";
                if(file_exists(HEURIST_THUMB_DIR.$thumbnail_file)){
                    $thumb_url = HEURIST_THUMB_URL.$thumbnail_file;
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

    /*function getRelativePath2( $targetPath ){
        if (HEURIST_FILESTORE_DIR === $targetPath) {
            return '';
        }
        if(strpos($targetPath, HEURIST_FILESTORE_DIR)===0){ //target is subfolder
            return substr($targetPath, strlen(HEURIST_FILESTORE_DIR));
        }else{ //return absolute path
            return $targetPath;
        }
    }*/


    /**
     * Returns the target path as relative reference from the base path.
     *
     * Only the URIs path component (no schema, host etc.) is relevant and must be given, starting with a slash.
     * Both paths must be absolute and not contain relative parts.
     * Relative URLs from one resource to another are useful when generating self-contained downloadable document archives.
     * Furthermore, they can be used to reduce the link size in documents.
     *
     * Example target paths, given a base path of "/a/b/c/d":
     * - "/a/b/c/d"     -> ""
     * - "/a/b/c/"      -> "./"
     * - "/a/b/"        -> "../"
     * - "/a/b/c/other" -> "other"
     * - "/a/x/y"       -> "../../x/y"
     *
     * @param string $basePath   The base path
     * @param string $targetPath The target path
     *
     * @return string The relative target path
     */
    function getRelativePath($basePath, $targetPath)
    {
        
        $targetPath = str_replace("\0", '', $targetPath);
        $targetPath = str_replace('\\', '/', $targetPath);
        
        if( substr($targetPath, -1, 1) != '/' )  $targetPath = $targetPath.'/';
        
        if ($basePath === $targetPath) {
            return '';
        }
        //else  if(strpos($basePath, $targetPath)===0){
        //    $relative_path = $dirname;


        $sourceDirs = explode('/', isset($basePath[0]) && '/' === $basePath[0] ? substr($basePath, 1) : $basePath);
        $targetDirs = explode('/', isset($targetPath[0]) && '/' === $targetPath[0] ? substr($targetPath, 1) : $targetPath);
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);

        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', count($sourceDirs)).implode('/', $targetDirs);

        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see http://tools.ietf.org/html/rfc3986#section-4.2).
        return '' === $path || '/' === $path[0]
            || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? './'.$path : $path;
    }
    
    //
    // 
    //
    function getPlayerTag($fileid, $mimeType, $url, $size){
            
            $is_video = (strpos($mimeType,"video/")===0);
            $is_audio = (strpos($mimeType,"audio/")===0);
            $is_image = (strpos($mimeType,"image/")===0);
            
            $res = '';
                
            if($url){
                $filepath = $url;  //external 
            }else{
                //to itself
                $filepath = HEURIST_BASE_URL."?db=".HEURIST_DBNAME."&file=".$fileid;
            }
            $thumb_url = HEURIST_BASE_URL."?db=".HEURIST_DBNAME."&thumb=".$fileid;
                
            if ( $is_video ) {
                    
                    if($size==null || $size==''){
                        $size = 'width="640" height="360"';
                    }
                    
                    if ($mimeType=='video/youtube' || $mimeType=='video/vimeo'
                            || strpos($url, 'vimeo.com')>0
                            || strpos($url, 'youtu.be')>0
                            || strpos($url, 'youtube.com')>0)
                    {
                    
                        $playerURL = getPlayerURL($mimeType, $url);
                        
                        $res = '<iframe '.$size.' src="'.$playerURL.'" frameborder="0" '
                            . ' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';                        
                            
                        
                    }else{
                        //preload="none"
                        $res = "<video  type='$size'  controls='controls''>"
                                ."<source type='$mimeType' src='$filepath'/></video>";
                         /*
                        $player = HEURIST_BASE_URL."ext/mediaelement/flashmediaelement.swf";
                         //note: we may remove flash fallback since it is blocked in most modern browsers
                                <!-- Flash fallback for non-HTML5 browsers -->
                                <object width="640" height="360" type="application/x-shockwave-flash" data="<?php echo $player;?>">
                                    <param name="movie" value="<?php echo $player;?>" />
                                    <param name="flashvars" value="controls=true&file=<?php echo $filepath;?>" />
                                    <img src="<?php echo $thumb_url;?>" width="320" height="240" title="No video playback capabilities" />
                                </object>
                         */    
                    }
                    
                }
                else if ( $is_audio ) 
                {
                    
                    if ($mimeType=='audio/soundcloud'
                            || strpos($url, 'soundcloud.com')>0)
                    {
                        
                        if($size==null || $size==''){
                            $size = 'width="640" height="166"';
                        }

                        $playerURL = getPlayerURL($mimeType, $url);
                        
                        $res = '<iframe '.$size.' src="'.$playerURL.'" frameborder="0"></iframe>';                        
                    
                    }else{
                        $res = '<audio controls="controls"><source src="'.$filepath
                            .'" type="'.$mimeType.'"/>Your browser does not support the audio element.</audio>';                        
                    }

                }else 
                if($is_image){
                    
                    // || strpos($url,".jpg")>0 || strpos($url,".jpeg")>0  || strpos($url,".png")>0 || strpos($url,".gif")>0
                
                        if($size==null || $size==''){
                            $size = 'width="300"';
                        }
                        $res = '<img '.$size.' src="'.$filepath.'"/>';
                    
                }else{
                    //not media - show thumb with download link
                    $res = '<a href="'.$filepath.'" target="_blank"><img src="'.$thumb_url.'"/></a>';
                    /*                
                        if($size==null || $size==''){
                            $size = 'width="420" height="345"';
                        }
                        print '<iframe '.$size.' src="'.$filepath.'" frameborder="0"></iframe>';                        
                    */    
                }
                
            return $res;
    }
    
    

//
// get player url for youtube, vimeo, soundcloud
//
function getPlayerURL($mimeType, $url){
    
    if( $mimeType == 'video/youtube' 
            || strpos($url, 'youtu.be')>0
            || strpos($url, 'youtube.com')>0){ //match('http://(www.)?youtube|youtu\.be')
            
        $url = 'https://www.youtube.com/embed/'.youtube_id_from_url($url);
        
    }else if( $mimeType == 'video/vimeo' || strpos($url, 'viemo.com')>0){
        
        $hash = json_decode(loadRemoteURLContent("http://vimeo.com/api/oembed.json?url=".rawurlencode($url), false), true);
        $video_id = @$hash['video_id'];
        if($video_id>0){
           $url =  'https://player.vimeo.com/video/'.$video_id;
        }
    }else if( $mimeType == 'audio/soundcloud' || strpos($url, 'soundcloud.com')>0){
    
        return 'https://w.soundcloud.com/player/?url='.$url
                .'&amp;auto_play=false&amp;hide_related=false&amp;show_comments=false&amp;show_user=false&amp;'
                .'show_reposts=false&amp;show_teaser=false&amp;visual=true';
    } 
    
    return $url;
}

function youtube_id_from_url($url) {
/*    
    $pattern = 
        '%^# Match any youtube URL
        (?:https?://)?  # Optional scheme. Either http or https
        (?:www\.)?      # Optional www subdomain
        (?:             # Group host alternatives
          youtu\.be/    # Either youtu.be,
        | youtube\.com  # or youtube.com
          (?:           # Group path alternatives
            /embed/     # Either /embed/
          | /v/         # or /v/
          | /watch\?v=  # or /watch\?v=
          )             # End path alternatives.
        )               # End host alternatives.
        ([\w-]{10,12})  # Allow 10-12 for 11 char youtube id.
        $%x'
        ;
        
    //$url = urldecode(rawurldecode($_GET["q"]));
    $result = preg_match($pattern, $url, $matches);
    if ($result) {
        return $matches[1];
    }
    return false;
*/    
    # https://www.youtube.com/watch?v=nn5hCEMyE-E
    preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);
    return $matches[1];    
}
    
?>