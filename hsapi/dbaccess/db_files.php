<?php

/**
* methods for recUploadedFiles
* @todo - 1) some methods to dbRecUploadedFiles 2) some to utils_image
* 
* file - prefix for functions
* 
* 
* fileRegister - register file in recUploadedFiles
* fileGetByObfuscatedId - get id by obfuscated id (NOT USED)
* fileGetByFileName
* fileGetFullInfo  - local paths, external links, mimetypes and parameters (mediatype and source)
* fileGetThumbnailURL - URL to thumbnail for given record ID and specified bg color
* getImageFromFile - return image object for given file
* getPrevailBackgroundColor2  - not used
* getPrevailBackgroundColor 
* 
* fileGetPlayerTag - produce appropriate html tag to view media content
* getPlayerURL  - get player url for youtube, vimeo, soundcloud
* 
* @todo move to utils_file.php
* resolveFilePath  
* downloadFile
* 
* downloadViaProxy - Blocked because of possible Remote file disclosure
* 
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/db_users.php');
require_once (dirname(__FILE__).'/../utilities/utils_file.php');
require_once (dirname(__FILE__).'/../entity/dbRecUploadedFiles.php');

/**
* register file in recUploadedFiles
* 
* @param mixed $system
* @param mixed $fullname
*/
function fileRegister($system, $fullname){
    
    
    $file_id = fileGetByFileName($system, $fullname); //apparently it is already registered
    
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

    if(!$ulf_ObfuscatedFileID || strlen($ulf_ObfuscatedFileID)<1) return null;

    $res = mysql__select_value($system->get_mysqli(), 'select ulf_ID from recUploadedFiles where ulf_ObfuscatedFileID="'.
        $system->get_mysqli()->real_escape_string(ulf_ObfuscatedFileID).'"');

    return $res;
}

/**
* Get file ID by file $fullname
*
* @param mixed $fullname
*/
function fileGetByFileName($system, $fullname){
    
        $path_parts = pathinfo($fullname);
        $dirname = $path_parts['dirname'].'/';
        $filename = $path_parts['basename'];
        
        // get relative path to db root folder
        $relative_path = getRelativePath(HEURIST_FILESTORE_DIR, $dirname);
        
        $mysqli = $system->get_mysqli();
      
        $file_id = mysql__select_value($mysqli, 'select ulf_ID from recUploadedFiles '
            .'where ulf_FileName = "'.$mysqli->real_escape_string($filename).'" and '
            .' (ulf_FilePath = "file_uploads/" or ulf_FilePath = "'.$mysqli->real_escape_string($relative_path).'")');

        return $file_id;
        
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
    
    if(count($file_ids)>0){
        
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
            if(count($file_ids)>1){
                $query = $query.' in ("'.implode('","', $file_ids).'")';
            }else{
                $query = $query.' = "'.$file_ids[0].'"';
            }
            
        }else if(is_numeric($file_ids[0]) && $file_ids[0]>0){
            $query = 'ulf_ID';
            
            if(count($file_ids)>1){
                $query = $query.' in ('.implode(',', $file_ids).')';
            }else{
                $query = $query.' = '.$file_ids[0];
            }
        }else{
            $system->addError(HEURIST_INVALID_REQUEST, 
                'Wrong file id parametrer provided to fileGetFullInfo.',
                $mysqli->error);
            return false;
        }

        $query = 'select ulf_ID, concat(ulf_FilePath,ulf_FileName) as fullPath, ulf_ExternalFileReference,'
        .'fxm_MimeType, ulf_Parameters, ulf_OrigFileName, ulf_FileSizeKB,'
        .' ulf_ObfuscatedFileID, ulf_Description, ulf_Added, ulf_MimeExt'
        //.($all_fields?', ulf_Thumbnail':'') we don't store thumbnail in databae anymore
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
function fileGetThumbnailURL($system, $recID, $get_bgcolor){

    $thumb_url = null;
    $bg_color = null;
    $fileid = null;

    $query = "select recUploadedFiles.ulf_ObfuscatedFileID".
    " from recDetails".
    " left join recUploadedFiles on ulf_ID = dtl_UploadedFileID".
    " left join defFileExtToMimetype on fxm_Extension = ulf_MimeExt".
    " where dtl_RecID = $recID ";
    
    if($system->defineConstant('DT_THUMBNAIL') & DT_THUMBNAIL>0){
        $fileid = mysql__select_value($system->get_mysqli(), $query
                .' and dtl_DetailTypeID='.DT_THUMBNAIL.' limit 1'); 
    }
    if($fileid == null){
        $query = $query
            .' and (dtl_UploadedFileID is not null)'    // no dty_ID of zero so undefined are ignored
            ." and (fxm_MimeType like 'image%' or fxm_MimeType='video/youtube' or fxm_MimeType='video/vimeo'  or fxm_MimeType='audio/soundcloud' "
            ." or ulf_OrigFileName LIKE '_iiif%')"
            .' limit 1';
        $fileid = mysql__select_value($system->get_mysqli(), $query);
    }
    
    if($fileid){

        $thumbfile = 'ulf_'.$fileid.'.png';

        if(defined('HEURIST_THUMB_URL') && file_exists(HEURIST_THUMB_DIR . $thumbfile)){
            $thumb_url = HEURIST_THUMB_URL.$thumbfile;
        }else{
            //it will be redirected to hsapi/controller/file_download.php
            $thumb_url = HEURIST_BASE_URL."?db=".HEURIST_DBNAME."&thumb=".$fileid;
        }
        
        if($get_bgcolor){
            $background_file  = 'ulf_'.$fileid.'.bg';
            if(false && file_exists(HEURIST_THUMB_DIR . $background_file)){
                $bg_color = file_get_contents(HEURIST_THUMB_DIR.$background_file);
            }else if(file_exists(HEURIST_THUMB_DIR . $thumbfile)){
                $bg_color = getPrevailBackgroundColor( HEURIST_THUMB_DIR . $thumbfile );
            }else{
                $bg_color = 'rgb(223, 223, 223)';
            }
        }
        
    }

    return array('url'=>$thumb_url, 'bg_color'=>$bg_color);
}

//
// return image object for given file
//
function getImageFromFile($filename){
    $image = null;
    if(file_exists($filename)){
        
            $path_parts = pathinfo($filename);
        
            try{
        
                switch($path_parts['extension']) {
                    case 'jpeg':
                    case 'jfif':
                    case 'jpg':
					case 'jpe':
                        $image = @imagecreatefromjpeg($filename);
                        break;
                    case 'gif':
                        $image = @imagecreatefromgif($filename);
                        break;
                    case 'png':
                        $image = @imagecreatefrompng($filename);
                        break;
                }
            
            }catch(Exception $e) {
                $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, 'Image corruption '.HEURIST_DBNAME, 
                    $filename.'. System message: ' .$e->getMessage());
            }

            
    }
    return $image;
}

/**
* find prevail background color need php version 5.5
* 
* @param mixed $file
*/
function getPrevailBackgroundColor2($filename){
    
    $image = getImageFromFile($filename);
    if($image){
        
        $scaled = imagescale($image, 1, 1, IMG_BICUBIC);  //since v5.5
        $index = imagecolorat($scaled, 0, 0);
        $rgb = imagecolorsforindex($scaled, $index); 
        /* $red = round(round(($rgb['red'] / 0x33)) * 0x33); 
        $green = round(round(($rgb['green'] / 0x33)) * 0x33); 
        $blue = round(round(($rgb['blue'] / 0x33)) * 0x33); 
        return sprintf('#%02X%02X%02X', $red, $green, $blue);     
        */
        return sprintf('#%02X%02X%02X', $rgb['red'], $rgb['green'], $rgb['blue']);
    }else{
        return '#FFFFFF';
    }
    
}

//
//
//
function rgb2hex($rgb) {
   $hex = "#";
   $hex .= str_pad(dechex($rgb[0]), 2, "0", STR_PAD_LEFT);
   $hex .= str_pad(dechex($rgb[1]), 2, "0", STR_PAD_LEFT);
   $hex .= str_pad(dechex($rgb[2]), 2, "0", STR_PAD_LEFT);

   return $hex; // returns the hex value including the number sign (#)
}

//
//
//
function getPrevailBackgroundColor($filename){
    // histogram options

    $maxheight = 300;
    $barwidth = 2;

    $image = getImageFromFile($filename);
    if($image){

        $im = $image;

        $imgw = imagesx($im);
        $imgh = imagesy($im);

        // n = total number or pixels

        $n = $imgw*$imgh;

        $histo = array();
        for ($i=0; $i<256; $i++) $histo[]=0;

        for ($i=0; $i<$imgw; $i++)
        {
            for ($j=0; $j<$imgh; $j++)
            {

                // get the rgb value for current pixel

                $rgb = imagecolorat($im, $i, $j);

                // extract each value for r, g, b

                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // get the Value from the RGB value

                $V = round(($r + $g + $b) / 3);

                // add the point to the histogram
                $histo[$V] += $V / $n;
                $histo_color[$V] = rgb2hex(array($r,$g,$b));
            }
        }

        // find the maximum in the histogram in order to display a normated graph

        $max = 0;
        for ($i=0; $i<256; $i++)
        {
            if ($histo[$i] > $max)
            {
                $max = $histo[$i];
            }
        }

        $key = array_search ($max, $histo);
        $col = $histo_color[$key];
        return $col;
        /*
        echo "<div style='width: ".(256*$barwidth)."px; border: 1px solid'>";
        for ($i=0; $i<255; $i++)
        {
        $val += $histo[$i];

        $h = ( $histo[$i]/$max )*$maxheight;

        echo "<img src=\"img.gif\" width=\"".$barwidth."\"
        height=\"".$h."\" border=\"0\">";
        }
        echo "</div>";
        */
    }else{
        return '#FFFFFF';   
    }
}

/**
* @TODO there are places with the same code - 1) use this function everywhere 2) move to utils_file.php
* 
* resolve path relatively db root or file_uploads
* 
* @param mixed $path
*/
function resolveFilePath($path, $db_name=null){

        if( $path ){
            
            if(!file_exists($path) ){
                
                if($db_name!=null){
                    $dir_folder = HEURIST_FILESTORE_ROOT . $db_name . '/';
                    $db_folder_files = $dir_folder . 'file_uploads/';
                }else{
                    $dir_folder = HEURIST_FILESTORE_DIR;
                    $db_folder_files = HEURIST_FILES_DIR;
                }
            
                chdir($dir_folder);  // relatively db root
                $fpath = realpath($path);
                if(file_exists($fpath)){
                    return $fpath;
                }else{
                    chdir($db_folder_files);          // relatively file_uploads 
                    $fpath = realpath($path);
                    if(file_exists($fpath)){
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
                if(file_exists($fpath)){
                    return $fpath;
                }
            }

        }
        return $path;
}

/**
* @todo - to be removed 
* Blocked because of possible Remote file disclosure
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
    $rawdata = loadRemoteURLContent($url, $bypassProxy); //blocked
    
    if($rawdata!==false){

        fileSave($rawdata, $filename);

        if(file_exists($filename)){
            downloadFile($mimeType, $filename, $originalFileName);
        }
    
    }
*/    
}

/** 
* Direct file download - move to utils_file
* 
* Usage in 2 cases only
* 1) Download database backup (exportMyDataPopup)
* 2) Download registered file from Heurist storage folder (file_download)
*
* @param mixed $mimeType
* @param mixed $filename
*/
function downloadFile($mimeType, $filename, $originalFileName=null){

    if (file_exists($filename)) {

        header('Content-Description: File Transfer');
        $is_zip = false;
        if(!$mimeType || $mimeType == 'application/octet-stream'){
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
                . sprintf('filename="%s"; ', rawurlencode($originalFileName))
                . sprintf("filename*=utf-8''%s", rawurlencode($originalFileName));            
            
        }else{
            $contentDispositionField = 'Content-Disposition: inline';
        }
        header($contentDispositionField);
        
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        @ob_clean();
        ob_end_flush(); //flush();
        
        if($is_zip){
            ob_start(); 
            readfile($filename);
            $output = gzencode(ob_get_contents(),6); 
            ob_end_clean(); 
            echo $output; 
            unset($output);         
        }else{
            if(false && filesize($filename)<10*1024*1024){
                readfile($filename);  //if less than 10MB download at once  
            }else{
                $handle = fopen($filename, "rb");
                while (!feof($handle)) {
                    echo fread($handle, 1000);  //by chunks
                }            
            }
        }
    }
}

//
// $rec_ID -  metadata for record_id
//
function downloadFileWithMetadata($system, $fileinfo, $rec_ID){
  
    $filepath = $fileinfo['fullPath'];  //concat(ulf_FilePath,ulf_FileName as fullPath
    $external_url = $fileinfo['ulf_ExternalFileReference'];     //ulf_ExternalFileReference
    $mimeType = $fileinfo['fxm_MimeType'];  // fxm_MimeType
    $params = $fileinfo['ulf_Parameters'];  // not used anymore
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
                $downloadFileName = fileNameSanitize(array_values($record['details'][DT_NAME])[0]);
        }
    }
    if(!$downloadFileName) $downloadFileName = 'Dataset_'.$rec_ID;
    
    
    $finfo = pathinfo($originalFileName);
    $ext = @$finfo['extension'];
    if($ext==null || $ext==''){
        if($is_local){
            $finfo = pathinfo($filepath);  //take from path
        }else{
            $finfo = array();
        }
        if(@$finfo['extension']){
            $originalFileName = $originalFileName.'.'.@$finfo['extension'];   
        }else if($fileExt){
            $originalFileName = $originalFileName.'.'.$fileExt;   
        }
    }    
    
    
    $need_remove_tmpfile = false;
    
    if($is_local){
        
    }else if($external_url && strpos($originalFileName,'_tiled')!==0){
        $need_remove_tmpfile = true;
        $filepath = tempnam(HEURIST_SCRATCH_DIR, '_remote_');
        saveURLasFile($external_url, $filepath); //save to temp in scratch folder
    }
    
    $file_zip = $downloadFileName.'.zip';
    $file_zip_full = tempnam(HEURIST_SCRATCHSPACE_DIR, "arc");

    $zip = new ZipArchive();
    if (!$zip->open($file_zip_full, ZIPARCHIVE::CREATE)) {
        $system->error_exit_api("Cannot create zip $file_zip_full");
    }else if(strpos($originalFileName,'_tiled')!==0) {
        $zip->addFile($filepath, $originalFileName);
    }

    $zip->addFromString($downloadFileName.'.txt', 
                    recordLinksFileContent($system, $record));    
    
    $zip->close();
    
    //remove temp file
    if($need_remove_tmpfile) unlink($filepath);
    
    //donwload
    $contentDispositionField = 'Content-Disposition: attachment; '
        . sprintf('filename="%s"; ', rawurlencode($file_zip))
        . sprintf("filename*=utf-8''%s", rawurlencode($file_zip));            
    
    header('Content-Type: application/zip');
    header($contentDispositionField);
    header('Content-Length: ' . filesize($file_zip_full));
    readfile($file_zip_full);
    
}

//
// output the appropriate html tag to view media content
// $params - array of special parameters for audio/video playback AND for IIIF 
//
function fileGetPlayerTag($fileid, $mimeType, $params, $external_url, $size=null, $style=null){ 

    $result = '';    
    
    
    $is_video = (strpos($mimeType,"video/")===0); // || @$params['video']
    $is_audio = (strpos($mimeType,"audio/")===0); // || @$params['audio']
    $is_image = (strpos($mimeType,"image/")===0);
    $is_iiif = (strpos(@$params['var'][0]['ulf_OrigFileName'],'_iiif')===0);
    
    if($style==null) $style='';

    if($external_url && strpos($external_url,'http://')!==0){ //download non secure external resource via heurist
        $filepath = $external_url;  //external 
    }else{
        //to itself
        $filepath = HEURIST_BASE_URL_PRO."?db=".HEURIST_DBNAME."&file=".$fileid;
        
        //to avoid download via proxy 
        $filepath = $filepath.'&fancybox=1';
    }
    $thumb_url = HEURIST_BASE_URL_PRO."?db=".HEURIST_DBNAME."&thumb=".$fileid;

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
            if($params && @$params['auto_play']){
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
            if($params && @$params['auto_play']){
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
        
        $iiif_type = $params['var'][0]['ulf_OrigFileName']; //image or manifest
        
        $miradorViewer = HEURIST_BASE_URL.'hclient/widgets/viewers/miradorViewer.php?db='
                    .HEURIST_DBNAME;
        if($iiif_type=='_iiif_image' && @$params['var'][0]['rec_ID']>0){
            $miradorViewer = $miradorViewer.'&q=ids:'.$params['var'][0]['rec_ID'];
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
            if(@$params['fancybox']){
                $fancybox =' class="fancybox-thumb" data-id="'.$fileid.'" ';               
            }else if(!$external_url){
                $fancybox =' data-id="'.$fileid.'" ';               
            }
            $result = '<img '.$size.$style.' src="'.$filepath.'"'
                .$fancybox
                .'/>';
                
                

    }else if($mimeType=='application/pdf'){
        //error_log($filepath);                 
        if(($size==null || $size=='') && $style==''){
            $size = '';
            $style = 'style="width:80% !important; height:90% !important"';
        }
        
        $result = '<embed width="100%" height="100%" name="plugin" src="'
        .$filepath.'&embedplayer=1'
        .'"'
        .($external_url?'':' data-id="'.$fileid.'"')
        .' type="application/pdf" internalinstanceid="9">';

        
//<object width="100%" height="100%" name="plugin" data="'+ fileURL_forembed+ '" type="application/pdf"></object>'        
        
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
    
    if(@$params['fancybox']){
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
            || strpos($url, 'youtube.com')>0){ //match('http://(www.)?youtube|youtu\.be')
            
        $url = 'https://www.youtube.com/embed/'.youtube_id_from_url($url);
        
    }else if( $mimeType == 'video/vimeo' || strpos($url, 'viemo.com')>0){
        
        $hash = json_decode(loadRemoteURLContent("https://vimeo.com/api/oembed.json?url=".rawurlencode($url), false), true); //get vimeo video id
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
//
//
function fileGetWidthHeight($fileinfo){

    
    $filepath = $fileinfo['fullPath'];  //concat(ulf_FilePath,ulf_FileName as fullPath
    $external_url = $fileinfo['ulf_ExternalFileReference'];     //ulf_ExternalFileReference
    $mimeType = $fileinfo['fxm_MimeType'];  //fxm_MimeType
    $originalFileName = $fileinfo['ulf_OrigFileName'];
    
    $type_media = null;
    $ext = null;
    if($mimeType && strpos($mimeType, '/')!=false){
        list($type_media, $ext) = explode('/', $mimeType);
    }

    $image = null;
    
    if(strpos($originalFileName,'_tiled')!==0 && $type_media=='image'){
    
        if(file_exists($filepath)){
            
            $image = getImageFromFile($filepath);
            
        }else if($external_url){

            $image = UtilsImage::getRemoteImage( $external_url );
        }
    
        if($image){

            try{    
                $imgw = imagesx($image);
                $imgh = imagesy($image);

                $res = array('width'=>$imgw,'height'=>$imgh);
                
            }catch(Exception  $e){
                
                $res = 'Cannot get image dimensions';
            }
        }else{
            $res = 'Cannot load image file to get dimensions';
        }
        
    }else{
        $res = 'Resource is not an image';
    }
    

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
    
    if($file!==false){
        $file = $file[0];    
    
        $thumbnail_file = HEURIST_THUMB_DIR."ulf_".$file['ulf_ObfuscatedFileID'].".png";    
        
        if(@$file['ulf_ExternalFileReference']==null || @$file['ulf_ExternalFileReference']==''){
            
            if (@$file['fullPath']){
                $filename = $file['fullPath'];
            }else if (@$file['ulf_FileName']) {
                $filename = $file['ulf_FilePath'].$file['ulf_FileName']; // post 18/11/11 proper file path and name
            } else {
                $filename = HEURIST_FILESTORE_DIR . $file['ulf_ID']; // pre 18/11/11 - bare numbers as names, just use file ID
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
                preg_match('/\\.([^.]+)$/', $file["ulf_OrigFileName"], $matches);    //find the extention
                $mimeExt = $matches[1];
            }

            //special case for pdf        
            if($mimeExt=='application/pdf' || $mimeExt=='pdf'){
                UtilsImage::getPdfThumbnail($filename, $thumbnail_file);
            }else{
                
                //get real image type from exif
                $mimeExt = UtilsImage::getImageType($filename);
                
                $errorMsg = UtilsImage::checkMemoryForImage($filename, $mimeExt);
                    
                if($errorMsg){
                    //database, record ID and name of bad image
                    sendEmail(HEURIST_MAIL_TO_ADMIN, 'Cant create thumbnail image. DB:'.HEURIST_DBNAME, 
                            'File ID#'.$file['ulf_ID'].'  '.$filename.'. '.$errorMsg);
                 
                    $img = UtilsImage::createFromString('Thumbnail not created. '.$errorMsg);
                    
                }else{
                    
                    //load content
                    $img = UtilsImage::safeLoadImage($filename, $mimeExt);
                    
                    if($img===false){
                        //this is not an image
                        $desc = '***' . strtoupper(preg_replace('/.*[.]/', '', $file['ulf_OrigFileName'])) . ' file';
                        $img = UtilsImage::createFromString($desc); //from string
                    }
                    
                }                    
                
            }
            
        }
        else if(@$file['ulf_ExternalFileReference']){  //remote 
        
            if(@$file['ulf_OrigFileName'] && strpos($file['ulf_OrigFileName'],'_tiled')===0){
                
                $img = UtilsImage::createFromString('tiled images stack'); //from string
        
            }else if(@$file['fxm_MimeType'] && strpos($file['fxm_MimeType'], 'image/')===0){
                //@todo for image services (flikr...) take thumbnails directly
                $img = UtilsImage::getRemoteImage($file['ulf_ExternalFileReference']);
            }else    
            if( @$file['fxm_MimeType'] == 'video/youtube' 
                || strpos($file['ulf_ExternalFileReference'], 'youtu.be')>0
                || strpos($file['ulf_ExternalFileReference'], 'youtube.com')>0){ //match('http://(www.)?youtube|youtu\.be')
                
                //@todo - youtube changed the way of retrieving thumbs !!!!
                $url = $file['ulf_ExternalFileReference'];
                
                preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);
                
                $youtubeid = $matches[1];
                
                $img = UtilsImage::getRemoteImage('http://img.youtube.com/vi/'.$youtubeid.'/default.jpg');
                
                //$youtubeid = preg_replace('/^[^v]+v.(.{11}).*/' , '$1', $url);
                //$img = get_remote_image("http://img.youtube.com/vi/".$youtubeid."/0.jpg"); //get thumbnail
            }else if($file['fxm_MimeType'] == 'video/vimeo'){

                $url = $file['ulf_ExternalFileReference'];
                
                $hash = json_decode(loadRemoteURLContent("https://vimeo.com/api/oembed.json?url=".rawurlencode($url), false), true); //get vimeo thumbnail
                $thumb_url = @$hash['thumbnail_url'];
                if($thumb_url){
                    $img = UtilsImage::getRemoteImage($thumb_url);    
                }
                
                //it works also - except regex is wrong for some vimeo urls
                /*
                if(preg_match('(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([??0-9]{6,11})[?]?.*', $url, $matches)>0){
                    $vimeo_id = $matches[5];
                    $hash = unserialize(file_get_contents("http://vimeo.com/api/v2/video/$vimeo_id.php"));
                    $thumb_url = $hash[0]['thumbnail_medium']; 
                    $img = UtilsImage::getRemoteImage($thumb_url);
                }
                */
            }else if($file['fxm_MimeType'] == 'audio/soundcloud'){

                $url = $file['ulf_ExternalFileReference'];

                $hash = json_decode(loadRemoteURLContent('https://soundcloud.com/oembed?format=json&url='   //get soundcloud thumbnail
                                .rawurlencode($url), false), true);
                $thumb_url = @$hash['thumbnail_url'];
                if($thumb_url){
                    $img = UtilsImage::getRemoteImage($thumb_url);    
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
        UtilsImage::resizeImage($img, $thumbnail_file); //$img will be destroyed inside this function
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
?>
