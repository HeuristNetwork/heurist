<?php

/**
*  file_download.php : Download (or proxy) files that are registered in Heurist database
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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

    //@todo - allow Obfuscated id only

    $fileid = @$_REQUEST['thumb'];
    if($fileid){
        $system->initPathConstants($db);

        $thumbfile = HEURIST_THUMB_DIR.'ulf_'.$fileid.'.png';
        if(file_exists($thumbfile)){
            downloadFile('image/png', $thumbfile);
        }else{
            //@todo - change to the same script in h4
            $thumb_url = HEURIST_BASE_URL."common/php/resizeImage.php?db=".$db."&ulf_ID=".$fileid;
            header("Location: ".$thumb_url);
            exit();
        }
    }else if(@$_REQUEST['id']) {

        $system->init($db);

        $fileid = @$_REQUEST['id'];
        //find
        $listpaths = fileGetPath_URL_Type($system, $fileid);
        if(is_array($listpaths) && count($listpaths)>0){

            $fileinfo = $listpaths[0];
            $filepath = $fileinfo[0];
            $url = $fileinfo[1];
            $mimeType = $fileinfo[2];
            $params = $fileinfo[3];

            $is_video = (strpos($mimeType,"video/")===0 || strpos($params,"video")!==false);
            $is_audio = (strpos($mimeType,"audio/")===0 || strpos($params,"audio")!==false);

            if( @$_REQUEST['player'] && ( $is_video || $is_audio ) )
            {

                if($url){
                    $filepath = $url;
                }else{
                    $filepath = HEURIST_BASE_URL."file.php?db=".HEURIST_DBNAME."&id=".$fileid;
                }

                ?>
                <html xmlns="http://www.w3.org/1999/xhtml">
                    <head>
                        <title>Heurist mediaplayer</title>

                        <base href="<?=HEURIST_BASE_URL?>">

                        <script type="text/javascript" src="ext/jquery-ui-1.10.2/jquery-1.9.1.js"></script>
                        <script type="text/javascript" src="ext/jquery-ui-1.10.2/ui/jquery-ui.js"></script>

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
            }else if(file_exists($filepath)){
                downloadFile($mimeType, $filepath);
            }else if($fileinfo[1]){
                header('Location: '.$fileinfo[1]);  //redirect to URL
            }
        }

    }
}

/**
* direct file download
*
* @param mixed $mimeType
* @param mixed $filename
*/
function downloadFile($mimeType, $filename){

    if (file_exists($filename)) {

        header('Content-Description: File Transfer');
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
        //force download header('Content-Disposition: attachment; filename='.basename($filename));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        @ob_clean();
        flush();

        readfile($filename);

    }
}
?>
