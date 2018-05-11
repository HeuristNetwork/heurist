<?php

/**
* @todo - wrap to class !!!

* file - prefix for functions
* 
* 
* fileGetByObfuscatedId - get id by obfuscated id
* fileGetPath_URL_Type  - local paths, external links, mimetypes and parameters (mediatype and source)
* fileGetThumbnailURL - URL to thumbnail for given record ID
* getImageFromFile - return image object for given file
* getPrevailBackgroundColor2  - not used
* getPrevailBackgroundColor 
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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
* Get array of local paths, external links, mimetypes and parameters (mediatype and source)
* for list of file id (may be obsfucated)
*
* @param mixed $system
* @param mixed $file_ids
*/
function fileGetPath_URL_Type($system, $file_ids){

    if(is_string($file_ids)){
        $file_ids = explode(",", $file_ids);
    }
    if(count($file_ids)>0){

        if(is_numeric($file_ids[0])){
            $query = "ulf_ID in (".implode(",", $file_ids).")";
        }else{
            $query = "ulf_ObfuscatedFileID in ('".implode("','", $file_ids)."')";
        }

        $query = 'select concat(ulf_FilePath,ulf_FileName) as fullPath, ulf_ExternalFileReference,'
        .'fxm_MimeType, ulf_Parameters, ulf_OrigFileName, ulf_FileSizeKB, ulf_ObfuscatedFileID from recUploadedFiles '
        .' left join defFileExtToMimetype on fxm_Extension = ulf_MimeExt where '
        .$query;

        $mysqli = $system->get_mysqli();
        $res = $mysqli->query($query);

        if ($res){
            $result = array();

            while ($row = $res->fetch_row()){
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
            $system->addError(HEURIST_DB_ERROR, 'Cannot get files', $mysqli->error);
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

    $query = "select recUploadedFiles.ulf_ObfuscatedFileID".
    " from recDetails".
    " left join recUploadedFiles on ulf_ID = dtl_UploadedFileID".
    " left join defFileExtToMimetype on fxm_Extension = ulf_MimeExt".
    " where dtl_RecID = $recID" .
    " and (dtl_UploadedFileID is not null)".    // no dty_ID of zero so undefined are ignored
    " and (fxm_MimeType like 'image%' or fxm_MimeType='video/youtube' or fxm_MimeType='video/vimeo')".
    " limit 1";

    $fileid = mysql__select_value($system->get_mysqli(), $query);
    if($fileid){

        $thumbfile = 'ulf_'.$fileid.'.png';

        if(file_exists(HEURIST_THUMB_DIR . $thumbfile)){
            $thumb_url = HEURIST_THUMB_URL.$thumbfile;
        }else{
            //hserver/dbaccess/file_download.php
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
* resolve path relatively db root or file_uploads
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
        }

        return $path;
}


/**
* direct file download
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
           header('Content-Disposition: attachment; filename='.$originalFileName); //basename($filename));
        }
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        @ob_clean();
        flush();
        
        if($is_zip){
            ob_start(); 
            readfile($filename);
            $output = gzencode(ob_get_contents(),6); 
            ob_end_clean(); 
            echo $output; 
            unset($output);         
        }else{
            readfile($filename);
        }
        

    }
}

//
// output the appropriate html tag to view media content
//
function filePrintPlayerTag($fileid, $mimeType, $params, $url, $size=null){ 

    $is_video = (strpos($mimeType,"video/")===0 || strpos($params,"video")!==false);
    $is_audio = (strpos($mimeType,"audio/")===0 || strpos($params,"audio")!==false);
    $is_image = (strpos($mimeType,"image/")===0);

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

            print '<iframe '.$size.' src="'.$playerURL.'" frameborder="0" '
            . ' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';                        

        }else{
            $player = HEURIST_BASE_URL."ext/mediaelement/flashmediaelement.swf";

            //preload="none"
            ?>
            <video  type="<?php echo $size;?>  controls="controls">
            <source type="<?php echo $mimeType;?>" src="<?php echo $filepath;?>" />
            <!-- Flash fallback for non-HTML5 browsers -->
            <object width="640" height="360" type="application/x-shockwave-flash" data="<?php echo $player;?>">
            <param name="movie" value="<?php echo $player;?>" />
            <param name="flashvars" value="controls=true&file=<?php echo $filepath;?>" />
            <img src="<?php echo $thumb_url;?>" width="320" height="240" title="No video playback capabilities" />
            </object>
            </video>
            <?php   
            //note: we may remove flash fallback since it is blocked in most modern browsers

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

            print '<iframe '.$size.' src="'.$playerURL.'" frameborder="0"></iframe>';                        

        }else{
            print '<audio controls="controls"><source src="'.$filepath
            .'" type="'.$mimeType.'"/>Your browser does not support the audio element.</audio>';                        
        }

    }else 
        if($is_image){

            // || strpos($url,".jpg")>0 || strpos($url,".jpeg")>0  || strpos($url,".png")>0 || strpos($url,".gif")>0

            if($size==null || $size==''){
                $size = 'width="300"';
            }
            print '<img '.$size.' src="'.$filepath.'"/>';

    }else if($mimeType=='application/pdf'){
        //error_log($filepath);                 
        print '<embed width="100%" height="100%" name="plugin" src="'
        .$filepath.'&embed=1'
        .'" type="application/pdf" internalinstanceid="9">';

    }else{
        //not media - show thumb with download link
        print '<a href="'.$filepath.'" target="_blank"><img src="'.$thumb_url.'"/></a>';

        /*                
        if($size==null || $size==''){
        $size = 'width="420" height="345"';
        }
        print '<iframe '.$size.' src="'.$filepath.'" frameborder="0"></iframe>';                        
        */    
    }
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
        
        $hash = json_decode(loadRemoteURLContent("https://vimeo.com/api/oembed.json?url=".$url, false), true);
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
