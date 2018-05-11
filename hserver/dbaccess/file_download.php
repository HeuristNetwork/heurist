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

            $fileinfo = $listpaths[0]; //
            $filepath = $fileinfo[0];  //concat(ulf_FilePath,ulf_FileName as fullPath
            $url = $fileinfo[1];     //ulf_ExternalFileReference
            $mimeType = $fileinfo[2];  //fxm_MimeType
            $params = $fileinfo[3];  //ulf_Parameters - not used anymore (for backward capability only)
            $originalFileName = $fileinfo[4];
            $fileSize = $fileinfo[5];

                
            if( @$_REQUEST['mode']=='tag'){
                filePrintPlayerTag($fileid, $mimeType, $params, $url);
            }
            else  //just download file from heurist server or redirect to original remote url
            {

                $filepath = resolveFilePath($filepath);

//DEBUG error_log($filepath.'  '.file_exists($filepath).'  '.$mimeType);  
              
                if(file_exists($filepath)){
                    downloadFile($mimeType, $filepath, @$_REQUEST['embed']==1?null:$originalFileName);
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
?>
