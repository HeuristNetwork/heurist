<?php

/*
* Copyright (C) 2005-2020 University of Sydney
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
* resizeImage.php
*
* Thumbnail service
* It loads thumbnail from recUploadedFiles.ulf_Thumbnail
* Or creates new thumbnail and stores it in this field in case this is standard thumnail request
*
* parameters
* w, h or maxw, maxh - size of thumbnail, if they are omitted - it returns standard thumbnail 100x100
* ulf_ID - file ID from recUploadedFiles, if defined and standard thumbnail - thumbnail is stored in table
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
require_once (dirname(__FILE__)."/../../hsapi/System.php");
require_once (dirname(__FILE__)."/../../hsapi/utilities/utils_mail.php");
require_once (dirname(__FILE__).'/../../hsapi/dbaccess/db_files.php');

$system = new System();

if(!$system->init(@$_REQUEST['db'])){
//    include dirname(__FILE__).'/../../hclient/framecontent/infoPage.php';
    header('Location: ../images/100x100-check.gif');
    exit();
}

$force_recreate = (@$_REQUEST['refresh']==1);

if (! @$_REQUEST['w']  &&  ! @$_REQUEST['h']  &&  ! @$_REQUEST['maxw']  &&  ! @$_REQUEST['maxh']) {
    $standard_thumb = true;
    $x = 200;
    $y = 200;
    $no_enlarge = false;
} else {
    $standard_thumb = false;
    $x = @$_REQUEST['w'] ? intval($_REQUEST['w']) : 0;
    $y = @$_REQUEST['h'] ? intval($_REQUEST['h']) : 0;
    $x = @$_REQUEST['maxw'] ? intval($_REQUEST['maxw']) : $x;
    $y = @$_REQUEST['maxh'] ? intval($_REQUEST['maxh']) : $y;
    $no_enlarge = @$_REQUEST['maxw']  ||  @$_REQUEST['maxh'];
}

$img = null;
$mysqli = $system->get_mysqli();

$mysqli->query('set character set binary');

if (array_key_exists('ulf_ID', $_REQUEST))
{
    $thumbnail_file = HEURIST_THUMB_DIR."ulf_".$_REQUEST['ulf_ID'].".png";
    /* if we here we create file. See uploadFile, there we check the existence of file
    if($standard_thumb && file_exists($thumbnail_file)){
    header("Location: ".HEURIST_THUMB_URL."ulf_".$_REQUEST['ulf_ID'].".png");
    return;
    }*/
    
    if (!$force_recreate && $standard_thumb  &&  file_exists($thumbnail_file)) {
       header('Content-type: image/png');
       echo readfile($thumbnail_file);
       return;
    }  
  
    $res = fileGetFullInfo($system, $mysqli->real_escape_string($_REQUEST['ulf_ID']), true);
    if($res!==false){
        $file = $res[0];    
    }else{
        header('Location: ../images/100x100-check.gif');
        exit();
    }
    
/*2019-03-06 never use thumbnail from database    
    if (!$force_recreate && $standard_thumb  &&  $file['ulf_Thumbnail']) {

        //save as file - recreate from thumb blob from database
        $img = @imagecreatefromstring($file['ulf_Thumbnail']);
        if($img){
            header('Content-type: image/png');
            imagepng($img, $thumbnail_file);
            // thumbnail exists
            echo $file['ulf_Thumbnail'];
        }
        return;
    }
*/    
    
    $type_source = null;
    $type_media = null;
    $ext = null;
    if(@$file['fxm_MimeType'] && strpos($file['fxm_MimeType'], '/')!=false){
        list($type_media, $ext) = explode('/',$file['fxm_MimeType']);
    }
    
    if(@$file['ulf_ExternalFileReference']==null || @$file['ulf_ExternalFileReference']==''){
        $type_source = 'heurist';    
    }else if( $file['fxm_MimeType'] == 'video/youtube' 
        || strpos($file['ulf_ExternalFileReference'], 'youtu.be')>0
        || strpos($file['ulf_ExternalFileReference'], 'youtube.com')>0){ //match('http://(www.)?youtube|youtu\.be')
        $type_source = 'youtube';
    }

    if($type_source==null || $type_source=='heurist') {
        if (@$file['fullPath']){
            $filename = $file['fullPath'];
        }else
        if (@$file['ulf_FileName']) {
            $filename = $file['ulf_FilePath'].$file['ulf_FileName']; // post 18/11/11 proper file path and name
        } else {
            $filename = HEURIST_FILESTORE_DIR . $file['ulf_ID']; // pre 18/11/11 - bare numbers as names, just use file ID
        }
        $filename = str_replace('/../', '/', $filename);
    }

    if(isset($filename)){
        //add database media storage folder for relative paths
        $filename = resolveFilePath($filename);
    }

    if (isset($filename) && file_exists($filename)){

        /*if ($file['ulf_FileName']) {
        $filename = $file['ulf_FilePath'].$file['ulf_FileName']; // post 18/11/11 proper file path and name
        } else {
        $filename = HEURIST_FILESTORE_DIR . $file['ulf_ID']; // pre 18/11/11 - bare numbers as names, just use file ID
        }
        $filename = str_replace('/../', '/', $filename);*/

        $mimeExt = '';
        if ($file['ulf_MimeExt']) {
            $mimeExt = $file['ulf_MimeExt'];
        } else {
            preg_match('/\\.([^.]+)$/', $file["ulf_OrigFileName"], $matches);	//find the extention
            $mimeExt = $matches[1];
        }
        
        //special case for pdf        
        if(@$file && ($mimeExt=='application/pdf' || $mimeExt=='pdf')){
            
            //$thumbnail_file = HEURIST_THUMB_DIR.'ulf_'.$file['ulf_ObfuscatedFileID'].'.png';
            
            if(!extension_loaded('imagick')){
                //print 'imagick not loaded<br>';
                //print phpinfo();
                //exit();
                
                $cmd = 'convert -thumbnail x200 -flatten '; //-background white -alpha remove 
                $cmd .= ' '.escapeshellarg($filename.'[0]');
                $cmd .= ' '.escapeshellarg($thumbnail_file);
                exec($cmd, $output, $error);
                
//error_log($cmd);        

                if ($error) {
                    error_log ('ERROR on pdf thumbnail creation: '.$filename.'  '.$cmd.'   '.implode('\n', $output));
                    exit();
                }
                $im = file_get_contents($thumbnail_file);
                
            }else{
//error_log('imagic loaded!!!');                        
                
                try {
                    
                    $im =  new Imagick($filename.'[0]'); 
                    $im->setImageFormat('png'); 
                    $im->thumbnailImage(200,200);

                    if ($standard_thumb) {
                        // store to database
                        //save_thumnail_indb($mysqli, $file, $resized);
                        
                        if(file_exists($thumbnail_file)){
                            unlink($thumbnail_file);
                        }
                        $im->writeImage($thumbnail_file);
                    }
                } catch(ImagickException $e) {
                    error_log($e . ', From Database: ' . HEURIST_DBNAME);
                    exit();
                }

            }
            header('Content-Type: image/png');
            echo $im;
            exit();
        }
        
        
        
        if (function_exists('exif_imagetype')) {
            switch(@exif_imagetype($filename)){
                case IMAGETYPE_JPEG:
                    $mimeExt = 'jpg';
                    break;
                case IMAGETYPE_PNG:
                    $mimeExt = 'png';
                    break;
                case IMAGETYPE_GIF:
                    $mimeExt = 'gif';
                    break;
            }
        }
        
        $is_image = false;
        $too_large = false;
        
        //if img check memory to be allocated
        switch($mimeExt) {
            case 'image/jpeg':
            case 'jpeg':
            case 'jfif':
            case 'jpg':
            case 'image/gif':
            case 'gif':
            case 'image/png':
            case 'png':
                $is_image = true;
            
                $mem_limit = get_php_bytes('memory_limit');
                
                $imageInfo = getimagesize($filename); 
                if(is_array($imageInfo)){
                    if(array_key_exists('channels', $imageInfo) && array_key_exists('bits', $imageInfo)){
                        $memoryNeeded = round(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $imageInfo['channels'] / 8 + Pow(2,16)) * 1.65); 
                    }else{ //width x height
                        $memoryNeeded = round($imageInfo[0] * $imageInfo[1]*3);  
                    } 
                    $mem_usage = memory_get_usage();

                    if ($mem_usage+$memoryNeeded > $mem_limit - 10485760){ // $mem_limit - 10485760){ // min($mem_limit, 41943040)){ //40M
                        $too_large = true;
                        
                        //database, record ID and name of bad image
                        sendEmail(HEURIST_MAIL_TO_ADMIN, 'Cant create thumbnail image. DB:'.HEURIST_DBNAME, 
                            'File ID#'.$file['ulf_ID'].'  '.$filename.' requires '.$memoryNeeded.
                            ' bytes to be resized.  Available '.$mem_limit.' bytes.', null);
                    }                
                }
                break;
            default:
                break;
        }        

        $img = null;

        if(!$too_large){
            
                
            $errline_prev = 0;
            
            set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
                global $errline_prev, $filename, $file;
                
                //it may report error several times with different messages - send for the first one
                if($errline_prev!=$errline){
                    
                        $errline_prev=$errline;
                        //database, record ID and name of bad image
                        $res = sendEmail(HEURIST_MAIL_TO_ADMIN, 'Cant create thumbnail image. DB:'.HEURIST_DBNAME, 
                        'File ID#'.$file['ulf_ID'].'  '.$filename.' is corrupted. System message: '.$errstr, null);
                    
                }
                return false;
            });//, E_WARNING);                
            
            switch($mimeExt) {
                case 'image/jpeg':
                case 'jpeg':
                case 'jfif':
                case 'jpg':
                    $img = @imagecreatefromjpeg($filename);
                    break;
                case 'image/gif':
                case 'gif':
                    $img = @imagecreatefromgif($filename);
                    break;
                case 'image/png':
                case 'png':
                    $img = @imagecreatefrompng($filename);
                    break;
                default:
                    $desc = '***' . strtoupper(preg_replace('/.*[.]/', '', $file['ulf_OrigFileName'])) . ' file';
                    $img = make_file_image2($desc); //from string
                    break;
            }
            
            //$rv = sendEmail(HEURIST_MAIL_TO_ADMIN, 'Image corruption '.HEURIST_DBNAME, 
            //        $filename.'. System message: ' .$e->getMessage() , null);
            
            
        }
        restore_error_handler();

    }else if(@$file['ulf_ExternalFileReference']){  //remote 

        if($type_media=='image'){ //$type_source=='generic' &&
            //@todo for image services (panoramio, flikr) take thumbnails directly
            $img = get_remote_image($file['ulf_ExternalFileReference']);
            
        }else if($type_source=='youtube' || $file['fxm_MimeType'] == 'video/youtube'){
            
//@todo - youtube change the way of retrieving thumbs !!!!
            $url = $file['ulf_ExternalFileReference'];
            
            preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);
            
            $youtubeid = $matches[1];
            
            $img = get_remote_image('http://img.youtube.com/vi/'.$youtubeid.'/default.jpg');
            
            //$youtubeid = preg_replace('/^[^v]+v.(.{11}).*/' , '$1', $url);
            //$img = get_remote_image("http://img.youtube.com/vi/".$youtubeid."/0.jpg"); //get thumbnail
        }else if($file['fxm_MimeType'] == 'video/vimeo'){

            $url = $file['ulf_ExternalFileReference'];

            $hash = json_decode(loadRemoteURLContent("http://vimeo.com/api/oembed.json?url=".rawurlencode($url), false), true);
            $thumb_url = @$hash['thumbnail_url'];
            if($thumb_url){
                $img = get_remote_image($thumb_url);    
            }
            
            
            //it works also - except regex is wrong for some vimeo urls
            /*
            if(preg_match('(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([??0-9]{6,11})[?]?.*', $url, $matches)>0){
                $vimeo_id = $matches[5];
                $hash = unserialize(file_get_contents("http://vimeo.com/api/v2/video/$vimeo_id.php"));
                $thumb_url = $hash[0]['thumbnail_medium']; 
                $img = get_remote_image($thumb_url);
            }
            */
            
        }else if (@$_REQUEST['offer_download']){
            // image placeholder
            header('Location: ../../hclient/assets/200x200-warn.gif');
            return;
        }
    }

}
else if (array_key_exists('file_url', $_REQUEST))   //get thumbnail for any URL
{

    $img = get_remote_image($_REQUEST['file_url']);

}

if (!$img) {
    // image placeholder
    header('Location: ../images/100x100-check.gif');
    return;
}

header('Content-type: image/png');

// calculate image size
// note - we never change the aspect ratio of the image!
$orig_x = imagesx($img);
$orig_y = imagesy($img);

$rx = $x / $orig_x;
$ry = $y / $orig_y;

$scale = $rx ? $ry ? min($rx, $ry) : $rx : $ry;

if ($no_enlarge  &&  $scale > 1) {
    $scale = 1;
}

$new_x = ceil($orig_x * $scale);
$new_y = ceil($orig_y * $scale);

$img_resized = imagecreatetruecolor($new_x, $new_y)  or die;
imagecopyresampled($img_resized, $img, 0, 0, 0, 0, $new_x, $new_y, $orig_x, $orig_y)  or die;

if ($standard_thumb  &&  @$file) {
    $resized_file = $thumbnail_file;
}else{
    $resized_file = tempnam(HEURIST_SCRATCHSPACE_DIR, 'resized');
}

imagepng($img_resized, $resized_file);//save into file
imagedestroy($img);
imagedestroy($img_resized);

$resized = file_get_contents($resized_file);

if ($standard_thumb  &&  @$file) {
    // store to database
    /*2019-03-06 never use thumbnail from database    
    save_thumnail_indb($mysqli, $file, $resized);
    */
}else{
    unlink($resized_file);
}

// output to browser
echo $resized;

//
// store to database 
//
function save_thumnail_indb($mysqli, $file_info, $resized){
    
    //$resized = file_get_contents($resized_file);
    
    $mysqli->query('update recUploadedFiles set ulf_Thumbnail = "' 
        . $mysqli->real_escape_string($resized) . '" where ulf_ID = ' . $file_info['ulf_ID']);
    
}

/**
* Creates image from given string
*
* @param mixed $desc - text to be inserted into resulted image
* @return resource - image with the given text
*/
function make_file_image2($desc) {
    $desc = preg_replace('/\\s+/', ' ', $desc);

    $font = 3; $fw = imagefontwidth($font); $fh = imagefontheight($font);
    $desc_lines = explode("\n", wordwrap($desc, intval(100/$fw)-1, "\n", false));
    $longlines = false;
    if (count($desc_lines) > intval(100/$fh)) {
        $longlines = true;
    } else {
        foreach ($desc_lines as $line) {
            if (strlen($line) >= intval(100/$fw)) {
                $longlines = true;
                break;
            }
        }
    }
    if ($longlines) {
        $font = 1; $fw = imagefontwidth($font); $fh = imagefontheight($font);
        $desc_lines = explode("\n", wordwrap($desc, intval(100/$fw)-1, "\n", true));
    }


    $im = imagecreate(100, 100);
    $white = imagecolorallocate($im, 255, 255, 255);
    $grey = imagecolorallocate($im, 160, 160, 160);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, 100, 100, $white);

    //imageline($im, 35, 25, 65, 75, $grey);
    imageline($im, 33, 25, 33, 71, $grey);
    imageline($im, 33, 25, 62, 25, $grey);
    imageline($im, 62, 25, 67, 30, $grey);
    imageline($im, 67, 30, 62, 30, $grey);
    imageline($im, 62, 30, 62, 25, $grey);
    imageline($im, 67, 30, 67, 71, $grey);
    imageline($im, 67, 71, 33, 71, $grey);

    $y = intval((100 - count($desc_lines)*$fh) / 2);
    foreach ($desc_lines as $line) {
        $x = intval((100 - strlen($line)*$fw) / 2);
        imagestring($im, $font, $x, $y, $line, $black);
        $y += $fh;
    }

    return $im;
}

/**
* download image from given url
*
* @param mixed $remote_url
* @return resource
*/
function get_remote_image($remote_url){

    $img = null;

    $data = loadRemoteURLContent($remote_url, false); //from utils_file.php
    if($data){
        try{    
            $img = imagecreatefromstring($data);
        }catch(Exception  $e){
            $img = false;
        }
    }

    return $img;
}
?>
