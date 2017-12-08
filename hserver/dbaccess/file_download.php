<?php

/**
*  file_download.php : Download (or proxy) files that are registered in Heurist database
*
* db
* thumb - obfuscated file id - returns existing thumbnail or resize image
* file - obfuscated file id - uses fileGetPath_URL_Type to get path to file or URL
*      player - returns html wrap
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
            //@todo - change to the same script in h4  or use ImageMagic
            $thumb_url = HEURIST_BASE_URL."common/php/resizeImage.php?db=".$db."&ulf_ID=".$fileid;
            header("Location: ".$thumb_url);
            exit();
        }
    }else if(@$_REQUEST['file']) {

        $fileid = @$_REQUEST['file'];
        
        if(is_numeric($fileid)){
            error_log('Obfuscated id is allowed only');
            exit;
        }
        
        $system->init($db);

        //find
        $listpaths = fileGetPath_URL_Type($system, $fileid);
        if(is_array($listpaths) && count($listpaths)>0){

            $fileinfo = $listpaths[0];
            $filepath = $fileinfo[0];  //concat(ulf_FilePath,ulf_FileName
            $url = $fileinfo[1];     //ulf_ExternalFileReference
            $mimeType = $fileinfo[2];  //fxm_MimeType
            $params = $fileinfo[3];  //ulf_Parameters
            $originalFileName = $fileinfo[4];

            $is_video = (strpos($mimeType,"video/")===0 || strpos($params,"video")!==false);
            $is_audio = (strpos($mimeType,"audio/")===0 || strpos($params,"audio")!==false);

            if( @$_REQUEST['player'] && ( $is_video || $is_audio ) )
            {

                if($url){
                    $filepath = $url;
                }else{
                    //to itself
                    $filepath = HEURIST_BASE_URL."?db=".HEURIST_DBNAME."&file=".$fileid;
                }

                ?>
                <html xmlns="http://www.w3.org/1999/xhtml">
                    <head>
                        <title>Heurist mediaplayer</title>

                        <base href="<?=HEURIST_BASE_URL?>">

                        <script type="text/javascript" src="ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
                        <script type="text/javascript" src="ext/jquery-ui-1.12.1/jquery-ui.js"></script>
                        
                        <script type="text/javascript" src="ext/mediaelement/mediaelement-and-player.min.js"></script>
                        <link rel="stylesheet" href="ext/mediaelement/mediaelementplayer.css" />

                        <script type="text/javascript">
                            $(document).ready(function(){
                               $('video,audio').mediaelementplayer({success: function (mediaElement, domObject) { alert('1'); mediaElement.play(); }  });
                            });
                        </script>
                    </head>
                    <body>
                        <?php
                        if(strpos($params,"youtube")!==false){
                            ?>
                            <video width="640" height="360" id="player1" preload="none">
                                <source type="video/youtube" src="<?=$filepath?>" />
                            </video>
                            <?php
                        }else if ($is_video) {
                            //poster="poster.jpg"
                            $player = HEURIST_BASE_URL."ext/mediaelement/flashmediaelement.swf";
                            ?>
                            
                            <video width="640" height="360"  controls="controls" preload="none">
                                <source type="<?=$mimeType?>" src="<?=$filepath?>" />
                                <!-- Flash fallback for non-HTML5 browsers without JavaScript -->
                                <object width="640" height="360" type="application/x-shockwave-flash" data="<?=$player?>">
                                    <param name="movie" value="<?=$player?>" />
                                    <param name="flashvars" value="controls=true&file=<?=$filepath?>" />
                                    <!-- Image as a last resort
                                    <img src="myvideo.jpg" width="320" height="240" title="No video playback capabilities" />
                                    -->
                                </object>
                            </video>
                            <?php
                        }else{
                            ?>
                            <audio controls="controls" preload="none">
                                <source type="<?=$mimeType?>" src="<?=$filepath?>" />
                            </audio>
                            <?php
                        }
                        ?>

                    </body>
                </html>
                <?php
            }else{

                $filepath = resolveFilePath($filepath);

//DEBUG error_log($filepath.'  '.file_exists($filepath).'  '.$mimeType);                
                if(file_exists($filepath)){
                    downloadFile($mimeType, $filepath, $originalFileName);
                }else if($fileinfo[1]){
                    
                    $url = $fileinfo[1];
                    
                    if( $mimeType == 'video/youtube' 
                            || strpos($url, 'youtu.be')>0
                            || strpos($url, 'youtube.com')>0){ //match('http://(www.)?youtube|youtu\.be')
                        $url = 'https://www.youtube.com/embed/'.youtube_id_from_url($url);
                    }
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
