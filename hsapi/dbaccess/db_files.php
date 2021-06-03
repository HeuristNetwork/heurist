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
* downloadViaProxy
* downloadFile
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
        $file_ids = explode(",", $file_ids);
    }else if(!is_array($file_ids)){
        $file_ids = array($file_ids);
    }
    
    if(count($file_ids)>0){

        if(is_numeric($file_ids[0])){
            $query = "ulf_ID in (".implode(",", $file_ids).")";
        }else{
            $query = "ulf_ObfuscatedFileID in ('".implode("','", $file_ids)."')";
        }

        $query = 'select ulf_ID, concat(ulf_FilePath,ulf_FileName) as fullPath, ulf_ExternalFileReference,'
        .'fxm_MimeType, ulf_Parameters, ulf_OrigFileName, ulf_FileSizeKB,'
        .' ulf_ObfuscatedFileID, ulf_Description, ulf_Added, ulf_MimeExt'
        .($all_fields?', ulf_Thumbnail':'')
        .' from recUploadedFiles '
        .' left join defFileExtToMimetype on fxm_Extension = ulf_MimeExt where '
        .$query;

        $mysqli = $system->get_mysqli();
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
        $query = $query.  
            " and (dtl_UploadedFileID is not null)".    // no dty_ID of zero so undefined are ignored
            " and (fxm_MimeType like 'image%' or fxm_MimeType='video/youtube' or fxm_MimeType='video/vimeo')".
            " limit 1";
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
                    $filename.'. System message: ' .$e->getMessage() , null);
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
function resolveFilePath($path){

        if( $path ){
            
        if(!file_exists($path) ){
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
* download remote url as file - this is our proxy for download annotations and adelaide web site
* 
* @param mixed $filename
* @param mixed $mimeType
* @param mixed $url
* @param mixed $bypassProxy
*/
function downloadViaProxy($filename, $mimeType, $url, $bypassProxy = true, $originalFileName=null){

    //if(!file_exists($filename)){ // || filemtime($filename)<time()-(86400*30))

    $rawdata = loadRemoteURLContent($url, $bypassProxy);
    
    if($rawdata!==false){

        fileSave($rawdata, $filename);
            /*
            if ($raw) {

            if(file_exists($filename)){
            unlink($filename);
            }
            $fp = fopen($filename, "w");
            //$fp = fopen($filename, "x");
            fwrite($fp, $raw);
            //fflush($fp);    // need to insert this line for proper output when tile is first requestet
            fclose($fp);
            }
            */
        //}

        if(file_exists($filename)){
            downloadFile($mimeType, $filename, $originalFileName);
        }
    
    }
}

/**
* direct file download - move to utils_file
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
                readfile($filename);    
            }else{
                $handle = fopen($filename, "rb");
                while (!feof($handle)) {
                    echo fread($handle, 1000);
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
    $mimeType = $fileinfo['fxm_MimeType'];  //fxm_MimeType
    $params = $fileinfo['ulf_Parameters'];  //ulf_Parameters - not used anymore (for backward capability only)
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
    
    if($is_local){
        
    }else if($external_url){
        $filepath = tempnam(HEURIST_SCRATCH_DIR, '_remote_');
        saveURLasFile($external_url, $filepath);
    }
    
    $file_zip = $downloadFileName.'.zip';
    $file_zip_full = tempnam(HEURIST_SCRATCHSPACE_DIR, "arc");

    $zip = new ZipArchive();
    if (!$zip->open($file_zip_full, ZIPARCHIVE::CREATE)) {
        $system->error_exit_api("Cannot create zip $file_zip_full");
    }else{
        $zip->addFile($filepath, $originalFileName);
    }

    $zip->addFromString($downloadFileName.'.txt', 
                    recordLinksFileContent($system, $record));    
    
    $zip->close();
    
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
//
//
function fileGetPlayerTag($fileid, $mimeType, $params, $external_url, $size=null, $style=null){ 

    $result = '';    
    
    $is_video = (strpos($mimeType,"video/")===0); // || strpos($params,"video")!==false
    $is_audio = (strpos($mimeType,"audio/")===0); // || strpos($params,"audio")!==false
    $is_image = (strpos($mimeType,"image/")===0);
    
    if($style==null) $style='';

    if($external_url && strpos($external_url,'http://')!==0){
        $filepath = $external_url;  //external 
    }else{
        //to itself
        $filepath = HEURIST_BASE_URL_PRO."?db=".HEURIST_DBNAME."&file=".$fileid;
    }
    $thumb_url = HEURIST_BASE_URL_PRO."?db=".HEURIST_DBNAME."&thumb=".$fileid;

    if ( $is_video ) {

        if(($size==null || $size=='') && $style==''){
            $size = 'width="640" height="360"';
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
                $size = 'width="640" height="166"';
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
        if($is_image){

            if(($size==null || $size=='') && $style==''){
                $size = 'width="300"';
            }
            $result = '<img '.$size.$style.' src="'.$filepath.'"'
                .($external_url?'':' data-id="'.$fileid.'"')
                .'/>';

    }else if($mimeType=='application/pdf'){
        //error_log($filepath);                 
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
    return $result;
}           

//
// get player url for youtube, vimeo, soundcloud
//
function getPlayerURL($mimeType, $url, $params=null){
    
    if( $mimeType == 'video/youtube' 
            || strpos($url, 'youtu.be')>0
            || strpos($url, 'youtube.com')>0){ //match('http://(www.)?youtube|youtu\.be')
            
        $url = 'https://www.youtube.com/embed/'.youtube_id_from_url($url);
        
    }else if( $mimeType == 'video/vimeo' || strpos($url, 'viemo.com')>0){
        
        $hash = json_decode(loadRemoteURLContent("https://vimeo.com/api/oembed.json?url=".$url, false), true);
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
function fileGetWidthHeight($filepath, $external_url, $mimeType){

    $type_media = null;
    $ext = null;
    if($mimeType && strpos($mimeType, '/')!=false){
        list($type_media, $ext) = explode('/', $mimeType);
    }

    $image = null;
    
    if($type_media=='image'){
    
        if(file_exists($filepath)){
            
            $image = getImageFromFile($filepath);
            
        }else if($external_url){

            //the same code as in resizeImage get_remote_image    
            $data = loadRemoteURLContent($external_url, false); //from utils_file.php
            if($data){
                try{    
                    $image = imagecreatefromstring($data);
                }catch(Exception  $e){
                    $image = false;
                    $res = 'Cant get remote image';
                }
            }
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
?>
