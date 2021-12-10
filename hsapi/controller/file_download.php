<?php

/**
* file_download.php : Download (or proxy) files that are registered in Heurist database
* Usually it is callled via redirection from index.php (if there is parameter file, thumb or url)
* 
* for entity images (rt icons, user, group images) see fileGet.php
*
* db
* thumb - obfuscated file id - returns existing thumbnail or resized image
* file - obfuscated file id - uses fileGetFullInfo to get path to file or URL
* 
* mode  
*   page - return
*   tag - returns html wrap iframe with embed player, video, audio or img tag 
*   size - returns width and height (for images only!)
* size - width and height for html tag
* embedplayer - for player
* 
* Notes about thumbnails
*    for uploaded file - thumbnail is created in 
*           hsapi/utilities/UploadHandler.php create_scaled_image() 
*           on time of uploading and after registration it is copied to our filethumbs folder
*    for remote file - thumbnail is created on first request file_download.php?thumb=  it uses common/php/resizeImage.php
*    if record has an rec_URL, the thumbnail is created with UtilsImage::makeURLScreenshot
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
require_once (dirname(__FILE__).'/../dbaccess/db_files.php');

if(count($_REQUEST)>900){
    error_log('TOO MANY _REQUEST PARAMS '.count($_REQUEST).' file_download');
    error_log(print_r(array_slice($_REQUEST, 0, 100),true));
}    

$system = new System(); //without connection
$db = @$_REQUEST['db'];

if($db){
    
    $fileid = @$_REQUEST['thumb'];
    if($fileid){ 
        $force_recreate = (@$_REQUEST['refresh']==1);

        $system->initPathConstants($db);

        $thumbfile = HEURIST_THUMB_DIR.'ulf_'.$fileid.'.png';
        
        if(!$force_recreate && file_exists($thumbfile)){
            downloadFile('image/png', $thumbfile);
        }else{
            //recreate thumbnail and output it
            $system->init($db);
            fileCreateThumbnail( $system, $fileid, true );
            $system->dbclose();
        }

    }
    else if(@$_REQUEST['file'] || @$_REQUEST['ulf_ID']) { //ulf_ID need for backward support of old downloadFile.php

        $fileid = @$_REQUEST['file']? $_REQUEST['file'] :@$_REQUEST['ulf_ID'];
        $size = @$_REQUEST['size'];
        
        if(is_numeric($fileid)){
            error_log('Obfuscated id is allowed only');
            exit;
        }
        
        if( @$_REQUEST['mode']=='page')     //return full page with embed player
        {
                $url = HEURIST_BASE_URL.'?mode=tag&db='.$db.'&file='.$fileid.'&size='.$size;
            
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
        
        if(!$system->init($db, true, false)){
            exit;
        }
        
        $system->initPathConstants($db);

        //find
        $listpaths = fileGetFullInfo($system, $fileid);
        if(is_array($listpaths) && count($listpaths)>0){

            $fileinfo = $listpaths[0]; //
            $filepath = $fileinfo['fullPath'];  //concat(ulf_FilePath,ulf_FileName as fullPath
            $external_url = $fileinfo['ulf_ExternalFileReference'];     //ulf_ExternalFileReference
            $mimeType = $fileinfo['fxm_MimeType'];  //fxm_MimeType
            $params = null; //$fileinfo['ulf_Parameters'];  //not used anymore 
            $originalFileName = $fileinfo['ulf_OrigFileName'];
            $fileSize = $fileinfo['ulf_FileSizeKB'];
            $fileExt = $fileinfo['ulf_MimeExt'];
            
            if( @$_REQUEST['mode']=='tag'){

                //rquest may have special parameters for audio/video players
                if(@$_REQUEST['fancybox']){
                    $params = $_REQUEST['fancybox']; //returns player in wrapper
                }
                
                print fileGetPlayerTag($fileid, $mimeType, $params, $external_url);
            }
            else  //just download file from heurist server or redirect to original remote url
            {

                $filepath = resolveFilePath( $filepath );
                
                if( @$_REQUEST['mode']=='size'){ //get width and height for image file

                    fileGetWidthHeight($fileinfo);

                }else if(@$_REQUEST['metadata']){//download zip file: registered file and file with links to html and xml
                
                    downloadFileWithMetadata($system, $fileinfo, $_REQUEST['metadata']);
                
                }else
                if(file_exists($filepath)){
                    
                    //fix issue if original name does not have ext
                    if(@$_REQUEST['embedplayer']!=1){
                        $finfo = pathinfo($originalFileName);
                        $ext = @$finfo['extension'];
                        if($ext==null || $ext==''){
                            $finfo = pathinfo($filepath);  //take from path
                            if(@$finfo['extension']){
                                $originalFileName = $originalFileName.'.'.@$finfo['extension'];   
                            }else if($fileExt){
                                if($fileExt=='jpe') $fileExt = 'jpg';
                                $originalFileName = $originalFileName.'.'.$fileExt;   
                            }
                        }    
                    }
                    
                    if(@$_REQUEST['fancybox']==1 && strpos($fileinfo['fullPath'],'file_uploads/')===0){
                        //show in viewer directly
                        $direct_url = HEURIST_FILESTORE_URL.$fileinfo['fullPath'];
                        header('Location: '.$direct_url);
                        
                    }else{
                        //see db_files
                        downloadFile($mimeType, $filepath, @$_REQUEST['embedplayer']==1?null:$originalFileName);
                    }
                }else if($external_url){
//DEBUG error_log('External '.$external_url);

                    if(@$_REQUEST['mode']=='url'){
           
                        //if it does not start with http - this is relative path             
                        if(!(strpos($external_url,'http://')===0 || strpos($external_url,'https://')===0)){
                            $external_url = HEURIST_TILESTACKS_URL.$external_url;                 
                        }                        
                        
                        header('Content-type: application/json;charset=UTF-8');
                        $response = array('status'=>HEURIST_OK, 'data'=>$external_url);
                        print json_encode($response);
                        
                    }else
                    if(strpos($originalFileName,'_tiled')===0){
                        
                        $thumbfile = HEURIST_THUMB_DIR.'ulf_'.$fileid.'.png';
                        
                        if(file_exists($thumbfile)){
                            downloadFile('image/png', $thumbfile);
                        }else{
                            fileCreateThumbnail( $system, $fileid, true );
                        }
                        
                        
                    }else
                    if(strpos($external_url,'http')==0 || $_REQUEST['download']){
                        
                        if($fileExt){
                            $finfo = pathinfo($originalFileName);
                            $ext = @$finfo['extension'];
                            if($ext==null || $ext==''){
                                $originalFileName = $originalFileName.'.'.$fileExt;   
                            }
                        }
                        //proxy http (unsecure) resources
                        $heurist_path = tempnam(HEURIST_SCRATCH_DIR, "_proxyremote_");        
                        downloadViaProxy($heurist_path, $mimeType, $external_url, false, $originalFileName);
                        unlink($heurist_path);
                    }else{
                        header('Location: '.$external_url);  //redirect to URL (external)    
                    }
                    
                    
                }else{
//DEBUG
                    error_log('File not found '.print_r($filepath,true));
                }
            }
        }else{
//DEBUG
            error_log('Filedata not found '.$fileid);
        }
        
        $system->dbclose();

    }
    else if (@$_REQUEST['rurl']){ //
        //load remote content from uni adelaide 
        if($db=='ExpertNation'){
            $remote_path = 'http://global.adelaide.edu.au/v/style-guide2/includes/common/'.$_REQUEST['rurl'];
            $mimetype = "text/html";
        }else{
            $remote_path = $_REQUEST['rurl'];
            $mimetype = @$_REQUEST['mimetype'];    
        }
        
        $system->initPathConstants($db);
        
        if(defined('HEURIST_SCRATCH_DIR')){
            $heurist_path = tempnam(HEURIST_SCRATCH_DIR, "_proxyremote_");        
            downloadViaProxy($heurist_path, $mimetype, $remote_path, false);
            if(file_exists($heurist_path)) unlink($heurist_path);
        }
        
    }
}
?>
