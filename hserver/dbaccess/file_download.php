<?php

/**
*  file_download.php : Download (or proxy) files that are registered in Heurist database
*
* db
* thumb - obfuscated file id - returns existing thumbnail or resize image
* file - obfuscated file id - uses fileGetPath_URL_Type to get path to file or URL
* 
* mode  
*   page - return
*   tag - returns html wrap iframe with embed player, video, audio or img tag 
* size - width and height for html tag
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
require_once (dirname(__FILE__).'/../dbaccess/db_files.php');

$system = new System(); //without connection
$db = @$_REQUEST['db'];

if($db){

    $fileid = @$_REQUEST['thumb'];
    if($fileid){ 
        $system->initPathConstants($db);

        $thumbfile = HEURIST_THUMB_DIR.'ulf_'.$fileid.'.png';
        if(file_exists($thumbfile)){
            downloadFile('image/png', $thumbfile);
        }else{
            //recreate thumbnail
            //@todo - change to the same script in h4  or use ImageMagic
            $thumb_url = HEURIST_BASE_URL."common/php/resizeImage.php?db=".$db."&ulf_ID=".$fileid;
            header("Location: ".$thumb_url);
            exit();
        }
    }else if(@$_REQUEST['file']) {

        $fileid = @$_REQUEST['file'];
        $size = @$_REQUEST['size'];
        
        if(is_numeric($fileid)){
            error_log('Obfuscated id is allowed only');
            exit;
        }
        
        if( @$_REQUEST['mode']=='page')     //return full page with embed player
        {
                $url = HEURIST_BASE_URL.'?mode=tag&db='.HEURIST_DBNAME.'&file='.$fileid.'&size='.$size;
            
                ?>
                <html xmlns="http://www.w3.org/1999/xhtml">
                    <head>
                        <title>Heurist mediaplayer</title>
                        <base href="<?php echo HEURIST_BASE_URL;?>">
                    </head>
                    <body>
                       <?php 
                         print file_get_contents($url);   //execute this script to get html tag for player
                       ?>
                    </body>
                </html>
                <?php
                exit();
        }
        
        $system->init($db);

        //find
        $listpaths = fileGetPath_URL_Type($system, $fileid);
        if(is_array($listpaths) && count($listpaths)>0){

            $fileinfo = $listpaths[0];
            $filepath = $fileinfo[0];  //concat(ulf_FilePath,ulf_FileName
            $url = $fileinfo[1];     //ulf_ExternalFileReference
            $mimeType = $fileinfo[2];  //fxm_MimeType
            $params = $fileinfo[3];  //ulf_Parameters - not used anymore (for backward capability only)
            $originalFileName = $fileinfo[4];

            $is_video = (strpos($mimeType,"video/")===0 || strpos($params,"video")!==false);
            $is_audio = (strpos($mimeType,"audio/")===0 || strpos($params,"audio")!==false);
            $is_image = (strpos($mimeType,"image/")===0);
                
            if( @$_REQUEST['mode']=='tag'){
                
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
            else  //just download file from heurist server or redirect to original remote url
            {

                $filepath = resolveFilePath($filepath);

//DEBUG error_log($filepath.'  '.file_exists($filepath).'  '.$mimeType);  
              
                if(file_exists($filepath)){
                    downloadFile($mimeType, $filepath, $originalFileName);
                }else if($url){
//DEBUG error_log('External '.$url);                
                    header('Location: '.$url);  //redirect to URL (external)
                    
                }else{
//DEBUG
                    error_log('File not found '.$filepath);
                }
            }
        }else{
//DEBUG
            error_log('Filedata not found '.$fileid);
        }

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
