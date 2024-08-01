<?php
/**
* recordFile.php
* 
* methods for recUploadedFiles
* @todo - 1) some methods to dbRecUploadedFiles 2) some to uImage
* 
* file - prefix for functions
* 
* 
* fileRegister - register file in recUploadedFiles
* fileGetByObfuscatedId - get id by obfuscated id (NOT USED)
* fileGetByFileName
* fileGetByOriginalFileName
* fileRenameToOriginal
* fileGetFullInfo  - local paths, external links, mimetypes and parameters (mediatype and source)
* fileGetThumbnailURL - URL to thumbnail for given record ID and specified bg color
* fileGetMetadata - return metadata for registered media, plus width,height for images
*
* getPrevailBackgroundColor2  - not used
* getPrevailBackgroundColor 
* 
* fileGetPlayerTag - produce appropriate html tag to view media content
* getPlayerURL  - get player url for youtube, vimeo, soundcloud
*
* getWebImageCache - get scaled down jpeg version of a image, to reduce load times
* 
* @todo move to uFile.php
* resolveFilePath  
* downloadFile
* 
* downloadViaProxy - Blocked because of possible Remote file disclosure
* 
* detect3D_byExt - returns 3d or 3dhop depends on extension parameter
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once dirname(__FILE__).'/../../System.php';
require_once dirname(__FILE__).'/../../structure/dbsUsersGroups.php';
require_once dirname(__FILE__).'/../../utilities/uFile.php';
require_once dirname(__FILE__).'/../../utilities/uImage.php';
require_once dirname(__FILE__).'/../../entity/dbRecUploadedFiles.php';

/**
* @todo - make it as method of DbRecUploadedFiles
* Register file in recUploadedFiles - used for import CSV, index directory
* 
* @param mixed $system
* @param mixed $fullname
*/
function fileRegister($system, $fullname, $description=null){
    
    
    $file_id = fileGetByFileName($system, $fullname);//check if it is already registered
    
    if(!($file_id>0)) {
                            
        $filesize = filesize($fullname);
        $fileinfo = pathinfo($fullname);
        
        $mimetypeExt = strtolower($fileinfo['extension']);
        $filename_base = $fileinfo['basename'];
        $dir = $fileinfo['dirname'];
        
        // get relative path to db root folder
        $relative_path = getRelativePath(HEURIST_FILESTORE_DIR, $dir);
        
        $fileinfo = array(
            'entity'=>'recUploadedFiles', 
            'fields'=>array(    
                'ulf_OrigFileName' => $filename_base,
                'ulf_MimeExt' => $mimetypeExt, //extension or mimetype allowed
                'ulf_FileSizeKB' => ($filesize<1024?1:intval($filesize/1024)),
                'ulf_FilePath' => $relative_path, //relative path to HEURIST_FILESTORE_DIR - db root
                'ulf_FileName' => $filename_base
            )
        );
        if($description!=null){
            $fileinfo['fields']['ulf_Description'] = $description;
        }
        
        $entity = new DbRecUploadedFiles($system, $fileinfo);
        $ret = $entity->save();
        if($ret!==false){
            $records = $entity->records();
            $file_id = $records[0]['ulf_ID'];
        }
    }
    
    
    return $file_id;
}

//NOT USED
/**
* Get file IDs by Obfuscated ID
*
* @param mixed $ulf_ObfuscatedFileID
*/
function fileGetByObfuscatedId($system, $ulf_ObfuscatedFileID){

    if(!$ulf_ObfuscatedFileID || strlen($ulf_ObfuscatedFileID)<1) {return null;}

    $res = mysql__select_value($system->get_mysqli(), 'select ulf_ID from recUploadedFiles where ulf_ObfuscatedFileID="'.
        $system->get_mysqli()->real_escape_string($ulf_ObfuscatedFileID).'"');

    return $res;
}

/**
* Get file ID by file $fullname
*
* @param mixed $fullname
*/
function fileGetByFileName($system, $fullname){
    
        $path_parts = pathinfo($fullname);
        if($path_parts['dirname']!='.'){
            $dirname = $path_parts['dirname'].'/';
            $relative_path = getRelativePath($system->getSysDir(), $dirname);
        }else{
            $relative_path = null;
        }
        $filename = $path_parts['basename'];
        
        // get relative path to db root folder
        
        $mysqli = $system->get_mysqli();
      
        $query = 'select ulf_ID from recUploadedFiles '
            .'where ulf_FileName = "'.$mysqli->real_escape_string($filename).'"';
        if($relative_path!=null){
            $query = $query 
                .' and (ulf_FilePath = "file_uploads/" or ulf_FilePath = "'
                .$mysqli->real_escape_string($relative_path).'")';
        }

        $file_id = mysql__select_value($mysqli, $query);
        return $file_id;
        
}


/**
* Get file ID by original file name
*
* @param mixed $orig_name
*/
function fileGetByOriginalFileName($system, $orig_name){
    
        $mysqli = $system->get_mysqli();
      
        $fileinfo = mysql__select_row_assoc($mysqli, 'select * from recUploadedFiles '
            .'where ulf_OrigFileName = "'.$mysqli->real_escape_string($orig_name).'"');

        return $fileinfo;

}

//
// Finds registered file by original name, rename ulf_xxx_ to original name and update recUploadedFiles
//
function fileRenameToOriginal($system, $orig_name, $new_name=null){

    if($new_name==null) {$new_name = $orig_name;}
    $file_fullpath = HEURIST_FILESTORE_DIR.'file_uploads/'.$new_name;
    
    if(!file_exists($file_fullpath)){
        //find by original file name
        $fileinfo = fileGetByOriginalFileName($system, $orig_name);
        if($fileinfo){
            
            $reg_name = HEURIST_FILESTORE_DIR.$fileinfo['ulf_FilePath'].$fileinfo['ulf_FileName'];
            if(file_exists($reg_name)){
                //rename file to original (without prefix ulf_xxx_) and update in database 
                rename($reg_name, $file_fullpath);
                
                $mysqli = $system->get_mysqli();
                $new_name = $mysqli->real_escape_string($new_name);
                
                $qupdate = 'UPDATE recUploadedFiles set ulf_FilePath="file_uploads/", '
                .' ulf_FileName="'.$new_name.'", ulf_OrigFileName="'.$new_name.'" '
                .'WHERE ulf_ID='.$fileinfo['ulf_ID'];
                
                $mysqli->query($qupdate);
                
            }else{
                return null;  //registered file missed
            } 
        }else{
            return null;  //registered file not found by original name
        }
    }
        
    return $file_fullpath;
}

/**
* Get array of local paths, external links, mimetypes and parameters (mediatype and source)
* for list of file id (may be obsfucated)
*
* @param mixed $system
* @param mixed $file_ids
*/
function fileGetFullInfo($system, $file_ids, $all_fields=false){

    //@todo use prepareIds() and prepareStrIds
    if(is_string($file_ids)){
        $file_ids = explode(',', $file_ids);
    }else if(!is_array($file_ids)){
        $file_ids = array($file_ids);
    }
    
    if(is_array($file_ids) && count($file_ids)>0){
        
        $mysqli = $system->get_mysqli();

                    
        foreach ($file_ids as $idx=>$testcase) {
            if (is_string($testcase)){
                if (ctype_alnum($testcase)) {
                    $file_ids[$idx] = $mysqli->real_escape_string($testcase);
                }else{
                    $system->addError(HEURIST_INVALID_REQUEST, 
                        'Wrong file id parametrer provided to fileGetFullInfo.',
                        $mysqli->error);
                    return false;
                }
            }
        }
        
        if(is_string($file_ids[0]) && strlen($file_ids[0])>15){
            
            $query = 'ulf_ObfuscatedFileID';

            $filed_ids2 = array();
            foreach($file_ids as $idx=>$v){
                $filed_ids2[] = preg_replace('/[^a-z0-9]/', "", $v);//for snyk
            }
            
            if(count($filed_ids2)>1){
                //escapeValues($mysqli, $file_ids);
                $query = $query.' in ("'.implode('","', $filed_ids2).'")';
            }else{
                $query = $query.' = "'.$filed_ids2[0].'"';
            }
            
        }else if(is_numeric($file_ids[0]) && $file_ids[0]>0){
            $query = 'ulf_ID';
            
            if(count($file_ids)>1){
                $file_ids = prepareIds($file_ids);
                $query = $query.' in ('.implode(',', $file_ids).')';
            }else{
                $query = $query.' = '.intval($file_ids[0]);
            }
        }else{
            $system->addError(HEURIST_INVALID_REQUEST, 
                'Wrong file id parametrer provided to fileGetFullInfo.',
                $mysqli->error);
            return false;
        }

        $query = 'select ulf_ID, concat(ulf_FilePath,ulf_FileName) as fullPath, ulf_ExternalFileReference,'
        .'fxm_MimeType, ulf_PreferredSource, ulf_OrigFileName, ulf_FileSizeKB,'
        .' ulf_ObfuscatedFileID, ulf_Description, ulf_Added, ulf_MimeExt,'
        .' ulf_Caption, ulf_Copyright, ulf_Copyowner, ulf_Parameters'
        //.($all_fields?', ulf_Thumbnail':'') we don't store thumbnail in database anymore
        .' from recUploadedFiles '
        .' left join defFileExtToMimetype on fxm_Extension = ulf_MimeExt where '
        .$query;

        
        $res = $mysqli->query($query);

        if ($res){
            $result = array();

            while ($row = $res->fetch_assoc()){
                array_push($result, $row);

                /*
                $filename = $row[0];
                $extURL = $row[1];
                $mimeType = $row[2];

                if( $filename && file_exists($filename) ){
                array_push($result, $filename);
                }else if($extURL && $type!='local'){
                array_push($result, $extURL);
                }
                */

            }
            $res->close();
            return $result;
        }else{
            $system->addError(HEURIST_DB_ERROR, 
                'Cannot get file info in fileGetFullInfo. Count of files '
                        .count($file_ids).'. Ask thumb img: '.($all_fields?'YES':'NO'), 
                $mysqli->error);
            return false;
        }
    }else{
        return false;
    }

}

/**
* Return full URL to thumbnail for given record ID
*
* @param mixed $system
* @param mixed $recIDs
*/
function fileGetThumbnailURL($system, $recID, $get_bgcolor, $check_linked_media = false){

    $thumb_url = null;
    $bg_color = null;
    $fileid = null;

    $query = "select recUploadedFiles.ulf_ObfuscatedFileID".
    " from recDetails".
    " left join recUploadedFiles on ulf_ID = dtl_UploadedFileID".
    " left join defFileExtToMimetype on fxm_Extension = ulf_MimeExt".
    " where dtl_RecID = $recID ";
    
    // at first - try to find image that are marked as thumbnail in dedicated field
    if($system->defineConstant('DT_THUMBNAIL') & DT_THUMBNAIL>0){
        $fileid = mysql__select_value($system->get_mysqli(), $query
                .' and dtl_DetailTypeID='.DT_THUMBNAIL.' limit 1');
    }
    // if special thumbnail not found - try to find image or resource with thumbail (youtube ot iiif)
    if($fileid == null){
        $query = $query
            .' and (dtl_UploadedFileID is not null)'    // no dty_ID of zero so undefined are ignored
            ." and (fxm_MimeType like 'image%' OR fxm_MimeType='video/youtube' OR fxm_MimeType='video/vimeo' OR fxm_MimeType='audio/soundcloud' "
            ." OR ulf_OrigFileName LIKE '_iiif%' OR ulf_PreferredSource LIKE 'iiif%')" // ORDER BY dtl_DetailTypeID, dtl_ID
            .' LIMIT 1';
        $fileid = mysql__select_value($system->get_mysqli(), $query);
    }

    // Check linked record types
    if(!$fileid && $check_linked_media && 
        $system->defineConstant('RT_MEDIA_RECORD') && RT_MEDIA_RECORD > 0){

        $query = "SELECT rec_ID FROM Records LEFT JOIN recLinks ON rl_TargetID = rec_ID WHERE rl_SourceID = $recID AND rec_RecTypeID = " . RT_MEDIA_RECORD;
        $linked_rec_ids = mysql__select_list2($system->get_mysqli(), $query);

        while(!empty($linked_rec_ids)){

            $linked_rec_id = array_shift($linked_rec_ids);
            $file_details = fileGetThumbnailURL($system, $linked_rec_id, $get_bgcolor, false);

            if(!empty($file_details) && !empty($file_details['url'])){
                return $file_details;
                //break;
            }
        }
    }
    
    if($fileid){

        $fileid = preg_replace('/[^a-z0-9]/', "", $fileid);//for snyk
        
        $thumbfile = 'ulf_'.$fileid.'.png';// ulf_[obfuscation].png

        if(defined('HEURIST_THUMB_URL') && file_exists(HEURIST_THUMB_DIR . $thumbfile)){
            $thumb_url = HEURIST_THUMB_URL.$thumbfile;
        }else{
            //it will be redirected to hserv/controller/fileDownload.php
            $thumb_url = HEURIST_BASE_URL."?db=".$system->dbname()."&thumb=".$fileid;
        }
        
        if($get_bgcolor){
            $background_file  = 'ulf_'.$fileid.'.bg';
            if(false && file_exists(HEURIST_THUMB_DIR . $background_file)){
                $bg_color = file_get_contents(HEURIST_THUMB_DIR.$background_file);
            }else if(file_exists(HEURIST_THUMB_DIR . $thumbfile)){
                $bg_color = UImage::getPrevailBackgroundColor( HEURIST_THUMB_DIR . $thumbfile );
            }else{
                $bg_color = 'rgb(223, 223, 223)';
            }
        }
        
    }

    return array('url'=>$thumb_url, 'bg_color'=>$bg_color);
}

/**
* @TODO there are places with the same code - 1) use this function everywhere 2) move to uFile.php
* 
* resolve path relatively db root or file_uploads
* 
* @param mixed $path
*/
function resolveFilePath($path, $db_name=null){

    if( $path ){
        
        if(!file_exists($path) ){
            
            if($db_name!=null){
                $dir_folder = USanitize::sanitizePath(HEURIST_FILESTORE_ROOT . $db_name . '/');
                $db_folder_files = $dir_folder . 'file_uploads/';
            }else{
                $dir_folder = HEURIST_FILESTORE_DIR;
                $db_folder_files = HEURIST_FILES_DIR;
            }
        
            chdir($dir_folder);// relatively db root
            $fpath = realpath($path);
            if($fpath!==false && file_exists($fpath)){
                return $fpath;
            }else{
                chdir($db_folder_files);// relatively file_uploads 
                $fpath = realpath($path);
                if($fpath!==false && file_exists($fpath)){
                    return $fpath;
                }else{
                    //special case to support absolute path on file server
                    if(strpos($path, '/srv/HEURIST_FILESTORE/')===0){
                        $fpath = str_replace('/srv/HEURIST_FILESTORE/', HEURIST_FILESTORE_ROOT, $path);
                        if(file_exists($fpath)){
                            return $fpath;
                        }
                    }else
                    if(strpos($path, '/misc/heur-filestore/')===0){
                        $fpath = str_replace('/misc/heur-filestore/', HEURIST_FILESTORE_ROOT, $path);
                        if(file_exists($fpath)){
                            return $fpath;
                        }
                    }else
                    if(strpos($path, '/data/HEURIST_FILESTORE/')===0){ //for huma-num
                        $fpath = str_replace('/data/HEURIST_FILESTORE/', HEURIST_FILESTORE_ROOT, $path);
                        if(file_exists($fpath)){
                            return $fpath;
                        }
                    }
                }
            }
        }else{
            //current dir already set
            $fpath = realpath($path);
            if($fpath!==false && file_exists($fpath)){
                return $fpath;
            }
        }

    }
    return $path;
}

/**
* @todo - to be removed 
* Remarked because of possible "Remote file disclosure"
* 
* Download remote url as file - heurist acts as proxy to download remote resource 
* 
* Usage: 
* 1. proxy http (unsecure) resources (registered in database)
* 2. proxy for http tiled map image server
* 3. adelaide web site styles (not used anymore)
* 4. annotated template (not used anymore)
* 
* @param mixed $filename
* @param mixed $mimeType
* @param mixed $url
* @param mixed $bypassProxy
*/
function downloadViaProxy($filename, $mimeType, $url, $bypassProxy = true, $originalFileName=null){
/*
    $rawdata = loadRemoteURLContent($url, $bypassProxy);//blocked
    
    if($rawdata!==false){

        fileSave($rawdata, $filename);

        if(file_exists($filename)){
            downloadFile($mimeType, $filename, $originalFileName);
        }
    
    }
*/    
}

/** 
* Direct file download - (@todo move to uFile?)
* 
* Usage in 2 cases only
* 1) Download database backup (exportMyDataPopup)
* 2) Download registered file from Heurist storage folder (fileDownload)
*
* @param mixed $mimeType
* @param mixed $filename
*/
function downloadFile($mimeType, $filename, $originalFileName=null){

    if (file_exists($filename)) {
    //if(isPathInHeuristUploadFolder($filename, true)){
        
        $range = @$_SERVER['HTTP_RANGE'];
        $range_max = 0;
        if($range!=null){
            //get bytes range  bytes=0-88
            list($dim, $range) = explode('=', $range);
            list($range_min,$range_max) = explode('-', $range);
            $range_min = intval($range_min);
            $range_max = intval($range_max);
        }        

        header('Content-Description: File Transfer');
        $is_zip = false;
        if(!$mimeType || $mimeType == 'application/octet-stream' || $mimeType == 'application/json'){
            $is_zip = true;
            header('Content-Encoding: gzip');
        }
        if ($mimeType) {
            header('Content-type: ' .$mimeType);
        }else{
            header('Content-type: binary/download');
        }
        if($mimeType!="video/mp4"){
            header('access-control-allow-origin: *');
            header('access-control-allow-credentials: true');
        }
        
        //header('Content-Type: application/octet-stream');
        
        //force download  - important for embed element DO NOT include this atttibute!
        if($originalFileName!=null){
            $contentDispositionField = 'Content-Disposition: attachment; '
                . sprintf('filename="%s";', rawurlencode($originalFileName))
                . sprintf("filename*=utf-8''%s", rawurlencode($originalFileName));
            
        }else{
            $contentDispositionField = 'Content-Disposition: inline';
        }
        header($contentDispositionField);
        
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . ($range_max>0?($range_max-$range_min+1):filesize($filename)));
        @ob_clean();
        ob_end_flush();//flush();
        
        if($is_zip){
            ob_start();
            readfile($filename);
            $output = gzencode(ob_get_contents(),6);
            ob_end_clean();
            echo $output; 
            unset($output);
        }else{
            if(false && filesize($filename)<10*1024*1024){
                readfile($filename);//if less than 10MB download at once  
            }else{
                $handle = fopen($filename, "rb");
                if($handle!==false){
                    if($range_max>0){
                        if($range_min>0) fseek($handle,$range_min);
                        $chunk = fread($handle, $range_max-$range_min+1);
                        //echo unpack("c2/n",$chunk);
                        echo $chunk; 
                        //fread($handle, $range_max-$range_min+1);//by chunks
                    }else{
                        while (!feof($handle)) {
                            echo fread($handle, 1000);//by chunks
                        }            
                    }
                }else{
                    //error_log('file not found: '.htmlspecialchars($filename));
                }
            }
        }
    }
}

//
// $fileinfo - data obtained by fileGetFullInfo
// $rec_ID -  metadata for record_id
//
function downloadFileWithMetadata($system, $fileinfo, $rec_ID){
  
    $filepath = $fileinfo['fullPath'];//concat(ulf_FilePath,ulf_FileName as fullPath
    $external_url = $fileinfo['ulf_ExternalFileReference'];//ulf_ExternalFileReference
    $mimeType = $fileinfo['fxm_MimeType'];// fxm_MimeType
    $source_type = $fileinfo['ulf_PreferredSource'];
    $originalFileName = $fileinfo['ulf_OrigFileName'];
    $fileSize = $fileinfo['ulf_FileSizeKB'];
    $fileExt = $fileinfo['ulf_MimeExt'];
    
    $filepath = resolveFilePath($filepath);
    $is_local = file_exists($filepath);

    //name for zip archive 
    $downloadFileName = null;
    $record = array("rec_ID"=>$rec_ID);
    if($system->defineConstant('DT_NAME')){
        recordSearchDetails($system, $record, array(DT_NAME));
        if(is_array($record['details'][DT_NAME])){
                $downloadFileName = USanitize::sanitizeFileName(array_values($record['details'][DT_NAME])[0]);
        }
    }
    if(!$downloadFileName) {$downloadFileName = 'Dataset_'.$rec_ID;}
    
    
    $finfo = pathinfo($originalFileName);
    $ext = @$finfo['extension'];
    if($ext==null || $ext==''){
        if($is_local){
            $finfo = pathinfo($filepath);//take from path
        }else{
            $finfo = array();
        }
        if(@$finfo['extension']){
            $originalFileName = $originalFileName.'.'.@$finfo['extension'];
        }else if($fileExt){
            $originalFileName = $originalFileName.'.'.$fileExt;   
        }
    }    
    
    
    $_tmpfile = null;
    
    if($is_local){
        
    }else if($external_url && strpos($originalFileName,'_tiled')!==0 && $source_type!='tiled'){
        
        $_tmpfile = tempnam(HEURIST_SCRATCH_DIR, '_remote_');
        $filepath = $_tmpfile;
        saveURLasFile($external_url, $_tmpfile);//save to temp in scratch folder
    }
    
    $file_zip = $downloadFileName.'.zip';
    $file_zip_full = tempnam(HEURIST_SCRATCHSPACE_DIR, "arc");

    $zip = new ZipArchive();
    if (!$zip->open($file_zip_full, ZIPARCHIVE::CREATE)) {
        $system->error_exit_api("Cannot create zip $file_zip_full");
    }else if(strpos($originalFileName,'_tiled')!==0 && $source_type!='tiled' ) {
        $zip->addFile($filepath, $originalFileName);
    }

    $zip->addFromString($downloadFileName.'.txt', 
                    recordLinksFileContent($system, $record));
    
    $zip->close();
    
    //remove temp file
    if($_tmpfile!=null) {
        unlink($_tmpfile);
    }
    
    //donwload
    $contentDispositionField = 'Content-Disposition: attachment; '
        . sprintf('filename="%s";', rawurlencode($file_zip))
        . sprintf("filename*=utf-8''%s", rawurlencode($file_zip));
    
    header('Content-Type: application/zip');
    header($contentDispositionField);
    header('Content-Length: ' . filesize($file_zip_full));
    readfile($file_zip_full);
    
}

//
// output the appropriate html tag to view media content
// $params - array of special parameters for audio/video playback AND for IIIF (from smarty)
//
function fileGetPlayerTag($system, $fileid, $mimeType, $params, $external_url, $size=null, $style=null){ 

    $result = '';
    $is_iiif = false;
    
    $is_video = (strpos($mimeType,"video/")===0);// || @$params['video']
    $is_audio = (strpos($mimeType,"audio/")===0);// || @$params['audio']
    $is_image = (strpos($mimeType,"image/")===0);
    if($params && is_array($params)){
        $is_iiif = (strpos(@$params['var'][0]['ulf_OrigFileName'],'_iiif')===0 ||
                    strpos(@$params['var'][0]['ulf_PreferredSource'],'iiif')===0);
    }
    
    if($style==null) {$style='';}

    if($external_url && strpos($external_url,'http://')!==0){ //download non secure external resource via heurist
        $filepath = $external_url;  //external 
    }else{
        //to itself
        $filepath = HEURIST_BASE_URL_PRO."?db=".$system->dbname()."&file=".$fileid;
        
        //to avoid download via proxy 
        $filepath = $filepath.'&fancybox=1';
    }
    $thumb_url = HEURIST_BASE_URL_PRO."?db=".$system->dbname()."&thumb=".$fileid;

    $mode_3d_viewer = detect3D_byExt(@$params['var'][0]['ulf_MimeExt']);
    
    if($mode_3d_viewer!=null && $mode_3d_viewer!=''){

        $playerURL = HEURIST_BASE_URL.'hclient/widgets/viewers/'.$mode_3d_viewer.'Viewer.php?db='.$system->dbname()
                    .'&file='.$fileid;

        $result = '<a href="'.$playerURL.'" target="_blank"><img src="'.$thumb_url.'" '.$style.'/></a>';

        /* IN IFRAME
        if(($size==null || $size=='') && $style==''){
            $size = ' height="640" width="800" ';
        }
        $result = '<iframe '.$size.$style.' src="'.$playerURL.'" frameborder="0" '
            . ' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
        */
    }else
    if ( $is_video ) {

        if(($size==null || $size=='') && $style==''){
            //$size = '';
            $size = 'width="640px" height="480px"';
            //$style = 'style="width:640px !important; height:480px !important"';
        }

        if ($mimeType=='video/youtube' || $mimeType=='video/vimeo'
        || strpos($external_url, 'vimeo.com')>0
        || strpos($external_url, 'youtu.be')>0
        || strpos($external_url, 'youtube.com')>0)
        {

            $playerURL = getPlayerURL($mimeType, $external_url);

            $result = '<iframe '.$size.$style.' src="'.$playerURL.'" frameborder="0" '
            . ' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';

        }else{
            
            $autoplay = '';
            if(is_array($params) && @$params['auto_play']){
                $autoplay = ' autoplay="autoplay"  loop="" muted="" ';
            }
            
            //preload="none"
            $result = '<video '.$autoplay.$size.$style.' controls="controls">'
            .'<source type="'.$mimeType.'" src="'.$filepath.'"'
                .($external_url?'':' data-id="'.$fileid.'"')
                .'/>'
            .'<img src="'.$thumb_url.'" width="320" height="240" title="No video playback capabilities" />'
            .'</video>';

        }

    }
    else if ( $is_audio ) 
    {

        if ($mimeType=='audio/soundcloud'
        || strpos($external_url, 'soundcloud.com')>0)
        {

            if(($size==null || $size=='') && $style==''){
                $size = '';
                //$style = 'style="width:80% !important; height:166px !important"';
            }

            $playerURL = getPlayerURL($mimeType, $external_url, $params);

            $result = '<iframe '.$size.$style.' src="'.$playerURL.'" frameborder="0"></iframe>';

        }else{
            
            $autoplay = '';
            if(is_array($params) && @$params['auto_play']){
                $autoplay = ' autoplay="autoplay"';
            }
            
            $result = '<audio controls="controls"'.$autoplay.'>'
                .'<source src="'.$filepath.'" type="'.$mimeType.'"'
                .($external_url?'':' data-id="'.$fileid.'"')
                .'/>Your browser does not support the audio element.</audio>';
        }

    }else 
    if( $is_iiif ){

        if(($size==null || $size=='') && $style==''){
            $size = ' height="640" width="800" ';
        }
        
        $iiif_type = $params['var'][0]['ulf_OrigFileName'];//image or manifest
        
        $miradorViewer = HEURIST_BASE_URL.'hclient/widgets/viewers/miradorViewer.php?db='
                    .$system->dbname();
        if(($iiif_type=='_iiif_image' || $params['var'][0]['ulf_PreferredSource']=='iiif_image')
            && @$params['var'][0]['rec_ID']>0){
            $miradorViewer = $miradorViewer.'&q=ids:'.intval($params['var'][0]['rec_ID']);
        }else{
            $miradorViewer = $miradorViewer.'&'.substr($iiif_type,1).'='.$fileid;
        }
        
        $result = '<iframe '.$size.$style.' src="'.$miradorViewer.'" frameborder="0"></iframe>';
        
    }else
    if($is_image){

            if(($size==null || $size=='') && $style==''){
                $size = 'width="300"';
            }
            $fancybox = '';
            if(is_array($params) && @$params['fancybox']){
                $fancybox =' class="fancybox-thumb" data-id="'.$fileid.'" '; 
            }else if(!$external_url){
                $fancybox =' data-id="'.$fileid.'" '; 
            }
            $result = '<img '.$size.$style.' src="'.$filepath.'"'
                .$fancybox
                .'/>';
                
                

    }else if($mimeType=='application/pdf'){
        if(($size==null || $size=='') && $style==''){
            $size = '';
            $style = 'style="width:80% !important; height:90% !important"';
        }
        
        $result = '<embed width="100%" height="100%" name="plugin" src="'
        .$filepath.'&embedplayer=1'
        .'"'
        .($external_url?'':' data-id="'.$fileid.'"')
        .' type="application/pdf" internalinstanceid="9">';
        
    }else{
        //not media - show thumb with download link
        $result = '<a href="'.$filepath.'" target="_blank"><img src="'.$thumb_url.'" '.$style.'/></a>';

        /*                
        if($size==null || $size==''){
        $size = 'width="420" height="345"';
        }
        print '<iframe '.$size.' src="'.$filepath.'" frameborder="0"></iframe>';
        */    
    }
    
    if(is_array($params) && @$params['fancybox']){
        $result = '<div style="width:80%;height:90%">'.$result.'</div>';
    }
    
    
    return $result;
}           

//
// get player url for youtube, vimeo, soundcloud
// $params - parameters for playback    show_artwork,auto_play
//
function getPlayerURL($mimeType, $url, $params=null){
    
    if( $mimeType == 'video/youtube' 
            || strpos($url, 'youtu.be')>0
            || strpos($url, 'youtube.com')>0){ //match('https://(www.)?youtube|youtu\.be')
            
        $url = 'https://www.youtube.com/embed/'.youtube_id_from_url($url);
        
    }else if( $mimeType == 'video/vimeo' || strpos($url, 'viemo.com')>0){
        
        $hash = json_decode(loadRemoteURLContent("https://vimeo.com/api/oembed.json?url=".rawurlencode($url), false), true);//get vimeo video id
        $video_id = @$hash['video_id'];
        if($video_id>0){
           $url =  'https://player.vimeo.com/video/'.$video_id;
        }
    }else if( $mimeType == 'audio/soundcloud' || strpos($url, 'soundcloud.com')>0){
        
        $autoplay = '&amp;auto_play=';
        $show_artwork = '&amp;show_artwork=';
        
        if($params && @$params['auto_play']){
            $autoplay .= 'true';
        }else{
            $autoplay .= 'false';
        }
        if($params && @$params['show_artwork']==0){
            $show_artwork .= 'false';
        }else{
            $show_artwork .= 'true';
        }
    
        return 'https://w.soundcloud.com/player/?url='.$url
                .$autoplay.'&amp;hide_related=false&amp;show_comments=false&amp;show_user=false&amp;'
                .'show_reposts=false&amp;show_teaser=false&amp;visual=true'.$show_artwork;
    } 
    
    return $url;
}

/**
 * Create a png version of an image for website usage
 *  Only performs this if the file is greater than 500 KB
 *  Also, scales the image down to at most 1000x1000 pixels
 * 
 * @param System $system - initialised Heurist system object
 * @param $fileinfo - data obtained by fileGetFullInfo
 * @param bool $return_url - return url to file instead of file path
 * 
 * @return bool | string - false on error, or path or url for cached image
 */
function getWebImageCache($system, $fileinfo, $return_url=true){

    $skip_file = strpos(@$fileinfo['ulf_OrigFileName'], '_remote') === 0 || // skip if not local file
                 strpos(@$fileinfo['ulf_OrigFileName'], '_iiif') === 0 || 
                 strpos(@$fileinfo['ulf_OrigFileName'], '_tiled') === 0;

    if($skip_file || @$fileinfo['ulf_FileSizeKB'] < 500){ // skip
        return false;
    }
    
    $file_path = resolveFilePath( @$fileinfo['fullPath'] );
    if(!file_exists($file_path)){
        return false;
    }
    
    $web_cache_dir = HEURIST_FILESTORE_DIR . 'webimagecache';
    
    $swarn = folderCreate2($web_cache_dir,'(for cached web images)', true);

    if($swarn!=''){
        $system->addError(HEURIST_ERROR, $swarn);
        return false;
    }

    $files = array();
    $error_reported = false;

    //direct url to filestore folder
    $file_url = HEURIST_FILESTORE_URL.$fileinfo['fullPath'];

    $file_path_info = pathinfo($file_path);
    
    //return basename with extension
    $file_name_cached = $file_path_info['filename'].'.jpg';

    $file_url_cached = HEURIST_FILESTORE_URL.'webimagecache/'.$file_name_cached; 
    $file_path_cached =  $web_cache_dir.'/'.$file_name_cached;  
      //fileWithGivenExt( $web_cache_dir , $file_path_info['basename'] );
    
    $res  = true;
    if(!file_exists($file_path_cached)){ // already exists
        $res = UImage::createScaledImageFile($file_path, $file_path_cached, 1000, 1000, false, 'jpg');
    }
    if($res===true){
        return $return_url?$file_url_cached:$file_path_cached;   
    }else{
        return false;            
    }
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


//
// Returns registered media(file) metadata as json
// Additionally it obtains width and height for images
//
function fileGetMetadata($fileinfo){

    
    $filepath = $fileinfo['fullPath'];//concat(ulf_FilePath,ulf_FileName as fullPath
    $external_url = $fileinfo['ulf_ExternalFileReference'];//ulf_ExternalFileReference
    $mimeType = $fileinfo['fxm_MimeType'];//fxm_MimeType
    $originalFileName = $fileinfo['ulf_OrigFileName'];
    $sourceType = $fileinfo['ulf_PreferredSource'];
    
    $type_media = null;
    $ext = null;
    if($mimeType && strpos($mimeType, '/')!=false){
        list($type_media, $ext) = explode('/', $mimeType);
    }

    $image = null;
    $alt_image = null;
    
    if(strpos($originalFileName,'_tiled')!==0 && $sourceType!='tiled' && $type_media=='image'){
    
        if(file_exists($filepath)){
            
            $image = UImage::getImageFromFile($filepath);
            $alt_image = @getimagesize($filepath);

        }else if($external_url){

            $image = UImage::getRemoteImage( $external_url );
        }

        if($image){

            try{    
                $imgw = imagesx($image);
                $imgh = imagesy($image);

                $res = array('width'=>$imgw, 'height'=>$imgh);
                
            }catch(Exception  $e){
                
                $res = 'Cannot get image dimensions';
            }
        }else if(file_exists($filepath) && is_array($alt_image) && !empty($alt_image)){ // [0] => width, [1] => height
            $res = array('width' => $alt_image[0], 'height' => $alt_image[1]);
        }else{
            $res = array('error'=>'Image is not loaded');//Cannot load image file to get dimensions
        }
        
    }else{
        $res = array();
    }
    
    $res['mimetype'] = $mimeType;
    $res['original_name'] = $originalFileName;
    $res['size_KB'] = $fileinfo['ulf_FileSizeKB'];
    $res['description'] = $fileinfo['ulf_Description'];

    header('Content-type: application/json;charset=UTF-8');
    $response = array('status'=>HEURIST_OK, 'data'=>$res);
    print json_encode($response);
}

/**
* Recreate thumbnail for record uploaded file
* 
* @param mixed $system
* @param mixed $fileid - ulf_ID or obfuscation IF
* @param mixed $is_download - output thumbnail
*/
function fileCreateThumbnail( $system, $fileid, $is_download ){
    
    $img = null; //image to be resized
    $file = fileGetFullInfo($system, $fileid, true);
    $placeholder = '../../hclient/assets/100x100.gif';
    $thumbnail_file = null;
    $orientation = 0;
    
    if($file!==false){
        $file = $file[0];
    
        $thumbnail_file = HEURIST_THUMB_DIR."ulf_".$file['ulf_ObfuscatedFileID'].".png";
        
        if(@$file['ulf_ExternalFileReference']==null || @$file['ulf_ExternalFileReference']==''){  //local
            
            if (@$file['fullPath']){
                $filename = $file['fullPath'];
            }else if (@$file['ulf_FileName']) {
                $filename = $file['ulf_FilePath'].$file['ulf_FileName'];// post 18/11/11 proper file path and name
            } else {
                $filename = HEURIST_FILESTORE_DIR . $file['ulf_ID'];// pre 18/11/11 - bare numbers as names, just use file ID
            }
            $filename = str_replace('/../', '/', $filename);
            
            //add database media storage folder for relative paths
            $filename = resolveFilePath($filename);
        }
        
        
        if (isset($filename) && file_exists($filename)){ //original file exists

            $mimeExt = '';
            if ($file['ulf_MimeExt']) {
                $mimeExt = $file['ulf_MimeExt'];
            } else {
                preg_match('/\\.([^.]+)$/', $file["ulf_OrigFileName"], $matches);//find the extention
                $mimeExt = $matches[1];
            }

            //special case for pdf        
            if($mimeExt=='application/pdf' || $mimeExt=='pdf'){
                UImage::getPdfThumbnail($filename, $thumbnail_file);
            }else{
                
                //get real image type from exif
                $mimeExt = UImage::getImageType($filename);
                //get orientation based on exif
                $orientation = UImage::getImageOrientation($filename);
                
                $errorMsg = UImage::checkMemoryForImage($filename, $mimeExt);
                    
                if($errorMsg){
                    //database, record ID and name of bad image
                    sendEmail(HEURIST_MAIL_TO_ADMIN, 'Cant create thumbnail image. DB:'.HEURIST_DBNAME, 
                            'File ID#'.$file['ulf_ID'].'  '.$filename.'. '.$errorMsg);
                 
                    $img = UImage::createFromString('Thumbnail not created. '.$errorMsg);
                    
                }else{
                    
                    //load content
                    $img = UImage::safeLoadImage($filename, $mimeExt);
                    
                    if($img===false){
                        //this is not an image
                        $desc = '***' . strtoupper(preg_replace('/.*[.]/', '', $file['ulf_OrigFileName'])) . ' file';
                        $img = UImage::createFromString($desc);//from string
                    }
                    
                }                    
                
            }
            
        }
        else if(@$file['ulf_ExternalFileReference']){  //remote 
        
            if(@$file['ulf_OrigFileName'] && 
                (strpos($file['ulf_OrigFileName'],'_tiled')===0 || @$file['ulf_PreferredSource']=='tiled') )  {
                
                $img = UImage::createFromString('tiled images stack');//from string
        
            }else if(@$file['fxm_MimeType'] && strpos($file['fxm_MimeType'], 'image/')===0){
                //@todo for image services (flikr...) take thumbnails directly
                $img = UImage::getRemoteImage($file['ulf_ExternalFileReference'], $orientation);
                
            }else    
            if( @$file['fxm_MimeType'] == 'video/youtube' 
                || strpos($file['ulf_ExternalFileReference'], 'youtu.be')>0
                || strpos($file['ulf_ExternalFileReference'], 'youtube.com')>0){ //match('https://(www.)?youtube|youtu\.be')
                
                //@todo - youtube changed the way of retrieving thumbs !!!!
                $url = $file['ulf_ExternalFileReference'];
                
                preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);
                
                $youtubeid = $matches[1];
                
                $img = UImage::getRemoteImage('https://img.youtube.com/vi/'.$youtubeid.'/default.jpg');
                
                //$youtubeid = preg_replace('/^[^v]+v.(.{11}).*/' , '$1', $url);
                //$img = get_remote_image("http://img.youtube.com/vi/".$youtubeid."/0.jpg");//get thumbnail
            }else if($file['fxm_MimeType'] == 'video/vimeo'){

                $url = $file['ulf_ExternalFileReference'];
                
                $hash = json_decode(loadRemoteURLContent("https://vimeo.com/api/oembed.json?url=".rawurlencode($url), false), true);//get vimeo thumbnail
                $thumb_url = @$hash['thumbnail_url'];
                if($thumb_url){
                    $img = UImage::getRemoteImage($thumb_url);
                }
                
                //it works also - except regex is wrong for some vimeo urls
                /*
                if(preg_match('(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([??0-9]{6,11})[?]?.*', $url, $matches)>0){
                    $vimeo_id = $matches[5];
                    $hash = unserialize(file_get_contents("http://vimeo.com/api/v2/video/$vimeo_id.php"));
                    $thumb_url = $hash[0]['thumbnail_medium'];
                    $img = UImage::getRemoteImage($thumb_url);
                }
                */
            }else if($file['fxm_MimeType'] == 'audio/soundcloud'){

                $url = $file['ulf_ExternalFileReference'];

                $hash = json_decode(loadRemoteURLContent('https://soundcloud.com/oembed?format=json&url='   //get soundcloud thumbnail
                                .rawurlencode($url), false), true);
                $thumb_url = @$hash['thumbnail_url'];
                if($thumb_url){
                    $img = UImage::getRemoteImage($thumb_url);
                }else{
                    $img = '../../hclient/assets/branding/logo_soundcloud.png';
                }

                
            }else{
                // image placeholder
                $placeholder = '../../hclient/assets/200x200-warn.gif';
            }
            
        
        }
    }
    
    if(!$img){
        if($is_download){
            header('Location: '.$placeholder);
        }
    }else{
        UImage::resizeImage($img, $thumbnail_file, 200, 200, $orientation);//$img will be destroyed inside this function
        if($is_download){
            if(file_exists($thumbnail_file)){
                header('Content-type: image/png');
                echo file_get_contents($thumbnail_file);
            }else{
                header('Location: '.$placeholder);
            }
        }
    }
}

/**
* returns 3d or 3dhop depends on extension parameter
* 
* @param mixed $fileExt - file extension
*/
function detect3D_byExt($fileExt){
    
    $mode_3d_viewer = '';

    if($fileExt=='nxz' || $fileExt=='nxs'){
        $mode_3d_viewer = '3dhop';
    }else {
        $allowed_exts = array('obj', '3ds', 'stl', 'ply', 'gltf', 'glb', 'off', '3dm', 'fbx', 'dae', 'wrl', '3mf', 'ifc', 'brep', 'step', 'iges', 'fcstd', 'bim');
        if(in_array($fileExt, $allowed_exts)){
            $mode_3d_viewer = '3d';
        }
    }
    
    return $mode_3d_viewer;
}


/**
* Calculates disk usage for file_uploads and uploaded_tilestacks folders
* 
* @param mixed $system
*/
function filestoreGetUsageByScan($system){
    
    //HEURIST_FILESTORE_ROOT.$db_name.'/';
    //$dir_root = HEURIST_FILESTORE_DIR; 
    //$dir_files = $dir_root.'file_uploads/';
    //$dir_tiles = $dir_root.'uploaded_tilestacks/';
    
    $sz1 = folderSize2(HEURIST_FILES_DIR);
    $sz2 = folderSize2(HEURIST_TILESTACKS_DIR);
    
    return $sz1+$sz2;
}

function filestoreGetUsageByFolders($system){

    $mediaFolders = $system->get_system('sys_MediaFolders');
    if($mediaFolders==null || $mediaFolders == ''){ //not defined
        $mediaFolders = 'uploaded_files';
    }
    $mediaFolders = explode(';', $mediaFolders);// get an array of folders
    $dirs = array();
    
    foreach ($mediaFolders as $dir){
        if( $dir && $dir!="*") {
            $dir2 = $dir;
            $dir = USanitize::sanitizePath(HEURIST_FILESTORE_DIR.$dir);
            if($dir){
                $dirs[$dir2] = folderSize2($dir);
            }
        }
    }

    $dirs['file_uploads'] = folderSize2(HEURIST_FILES_DIR);
    $dirs['uploaded_tilestacks'] = folderSize2(HEURIST_TILESTACKS_DIR);
    
    return $dirs;
}

/**
* Calculates disk usage for file_uploads and uploaded_tilestacks folders
* by sum in recUploadedFiles
* 
* @param mixed $system
*/
function filestoreGetUsageByDb($system){
    
    $mysqli = $system->get_mysqli();
    $res =  mysql__select_value($mysqli, 'SELECT SUM(ulf_FileSizeKB) FROM recUploadedFiles');
    
    return $res;
}
?>
