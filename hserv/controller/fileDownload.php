<?php

/**
* fileDownload.php : Download (or proxy) files that are registered in Heurist database (recUploadedFiles)
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
*   url - rerurns url for uploaded_tilestacks
* size - width and height for html tag
* embedplayer - for player
* 
* Notes about thumbnails
*    for uploaded file - thumbnail is created in 
*           hserv/utilities/UploadHandler.php create_scaled_image() 
*           on time of uploading and after registration it is copied to our filethumbs folder
*    for remote file - thumbnail is created on first request fileDownload.php?thumb=  it uses common/php/resizeImage.php
*    if record has an rec_URL, the thumbnail is created with UImage::makeURLScreenshot
* 
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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
require_once dirname(__FILE__).'/../System.php';
require_once dirname(__FILE__).'/../records/search/recordFile.php';

$db = @$_REQUEST['db'];

$error = System::dbname_check($db);

if(!$error){
    
    $system = new System(); //without connection
    $fileid = @$_REQUEST['thumb'];
    if($fileid){ 
        
        if(preg_match('/^[a-z0-9]+$/', $fileid)){ //validatate obfuscation id
        
            $force_recreate = (@$_REQUEST['refresh']==1);

            if($system->initPathConstants($db)){

                $thumbfile = HEURIST_THUMB_DIR.'ulf_'.$fileid.'.png';
                
                if(!$force_recreate && file_exists($thumbfile)){
                    
                    if(defined('HEURIST_THUMB_URL')){
                        header('Location: '.HEURIST_THUMB_URL.'ulf_'.$fileid.'.png');    
                    }else{
                        downloadFile('image/png', $thumbfile);    
                    }
                    
                }else{
                    //recreate thumbnail and output it
                    $system->init($db);
                    fileCreateThumbnail( $system, $fileid, true );
                    $system->dbclose();
                }
            }
        }
    }
    else if(@$_REQUEST['file'] || @$_REQUEST['ulf_ID']) { //ulf_ID is obfuscation id here

        $fileid = @$_REQUEST['file']? $_REQUEST['file'] :intval(@$_REQUEST['ulf_ID']);
        $size = @$_REQUEST['size'];
        
        if(is_numeric($fileid)){
            //Obfuscated id is allowed only
            exit;
        }

        if(!preg_match('/^[a-z0-9]+$/', $fileid)){ //validatate obfuscation id
            //Obfuscated id is allowed only
            exit;
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
            $sourceType = $fileinfo['ulf_PreferredSource']; //not used
            $originalFileName = $fileinfo['ulf_OrigFileName'];
            $fileSize = $fileinfo['ulf_FileSizeKB'];
            $fileExt = $fileinfo['ulf_MimeExt'];
            $params = null;
            
            if( @$_REQUEST['mode']=='page')     //return full page with embed player
            {
                $mode_3d_viewer = detect3D_byExt($fileExt);
                
                if($mode_3d_viewer!=''){
                    
                    $url = HEURIST_BASE_URL.'hclient/widgets/viewers/'.$mode_3d_viewer.'Viewer.php?db='.$db.'&file='.$fileid;
                    header('Location: '.$url);
                    
                }else{
                    $url = HEURIST_BASE_URL.'?mode=tag&db='.htmlspecialchars($db).'&file='.$fileid.'&size='.$size;
                
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
                }
                    
            }else    
            if( @$_REQUEST['mode']=='tag'){

                //request may have special parameters for audio/video players
                if(@$_REQUEST['fancybox']){
                    $params = array('fancybox'=>1); //returns player in wrapper
                }else{
                    $params = null;
                }
                
                print fileGetPlayerTag($system, $fileid, $mimeType, $params, $external_url);
            }
            else  //just download file from heurist server or redirect to original remote url
            {

                $filepath = resolveFilePath( $filepath );
                
                if( @$_REQUEST['mode']=='metaonly'){ //get width and height for image file

                    fileGetMetadata($fileinfo);

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
                    
                    $is_download = (@$_REQUEST['download']==1); 

                    if(!$is_download && isset($allowWebAccessUploadedFiles) && $allowWebAccessUploadedFiles
                                        && strpos($fileinfo['fullPath'],'file_uploads/')===0){

                        //show in viewer directly
                        $direct_url = HEURIST_FILESTORE_URL.$fileinfo['fullPath'];

                        if(@$_REQUEST['fullres'] === 0 || @$_REQUEST['fullres'] === '0'){ // get web cached version

                            $org_url = $direct_url;

                            $cache_url = getWebImageCache($system, $fileid, true);
                            $direct_url = is_array($cache_url) && !empty($cache_url) ? $cache_url[0] : $org_url;
                        }

                        header('Location: '.$direct_url);
                        
                    }else if(!$is_download 
                        && ($fileExt=='nxz' || $fileExt=='nxs' || $fileExt=='ply' || $fileExt=='fbx' || $fileExt=='obj'))
                    {
                        
                        //for 3D viewer - direct url to file
                        $direct_url = HEURIST_FILESTORE_URL.$fileinfo['fullPath'];
                        
                        header('Location: '.$direct_url);
                        
                    }else {
                        //see recordFile.php
                        downloadFile($mimeType, $filepath, @$_REQUEST['embedplayer']==1?null:$originalFileName);
                    }
                }
                else if($external_url){
                    if(@$_REQUEST['mode']=='url'){
           
                        //if it does not start with http - this is relative path             
                        if(!(strpos($external_url,'http://')===0 || strpos($external_url,'https://')===0)){
                            
                            //check presence of mbtiles file within folder
                            $path = HEURIST_TILESTACKS_DIR.$external_url;
                            if(is_dir($path)){
                                $recs = folderContent($path, 'mbtiles');
                                if($recs['count']>0){
                                    $filename = $recs['records'][1][1];
                                    $filename = pathinfo($filename);
                                    $external_url = HEURIST_BASE_URL.'mbtiles.php?'.HEURIST_DBNAME.'/'.$external_url.$filename['filename'];
                                }else{
                                    $external_url = $path;    
                                }
                            }else if (file_exists($path)) {
                                $filename = pathinfo($path);
                                $external_url = HEURIST_BASE_URL.'mbtiles.php?'.HEURIST_DBNAME.'/'.$filename['filename'];
                            }
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
                        
                        
                    }else{
                        header('Location: '.$external_url);  //redirect to URL (external)    
                    }
                    
                    
                }else{
                    //File not found
                    $placeholder = '../../hclient/assets/200x200-missed2.png';
                    header('Location: '.$placeholder);
                }
            }
        }else{
            //Filedata not found
            $placeholder = '../../hclient/assets/200x200-missed.png';
            header('Location: '.$placeholder);
        }
        
        $system->dbclose();

    }
}
?>
