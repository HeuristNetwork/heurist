<?php
/**
* findBrokenIamge.php
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../configIni.php');
    require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");
    require_once(dirname(__FILE__).'/../../records/files/fileUtils.php');

    if(isForAdminOnly("to get information on all databases on this server")){
        return;
    }

    set_time_limit(0); //no limit
?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Databases statistics</title>

        <link rel="icon" href="../../favicon.ico" type="image/x-icon">
        <link rel="shortcut icon" href="../../favicon.ico" type="image/x-icon">
        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
    </head>

    <body class="popup">
        <div id="titleBanner" class="banner"><h2>Broken uploaded images</h2></div>
        <div id="page-inner">

                <?php

    mysql_connection_overwrite(DATABASE);
    mysql_query('set character set binary');

    $res = mysql_query('select * from recUploadedFiles order by ulf_ID'); // where ulf_ID>12000 11710     12060
    
    $current_file;
    $cnt_jpg = 0;
    $cnt_png = 0;
    $cnt_gif = 0;
    $cnt_broken = 0;
    
    $cnt_missed = 0;
    $cnt_img = 0;
    $cnt_files = 0;
    $cnt_external = 0;
    
    $mem_usage = memory_get_usage();
    $mem_limit = ini_get ('memory_limit');

    $mem_limit = _get_config_bytes($mem_limit);
    print 'Memory limit: '.$mem_limit.' Used: '.$mem_usage.'<br>';    
    //$mem_limit = $mem_limit - $mem_usage;
   
    set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
        global $current_file, $cnt_broken;
        
        print 'Can\'t load file ID:'.$current_file.'<br>'.
                    'Error: '.$errstr.'<br>';
        $cnt_broken = $cnt_broken + 0.5;
        
        if (0 === error_reporting()) { //suppression with @
        }

        return false;
        //return false && throwErrorException($errstr, 0, $errno, $errfile, $errline);
        //throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });//, E_WARNING);                
    
    
    
    while ($file = mysql_fetch_assoc($res)) {
        
        $filename = null;
        $fileparams = parseParameters($file['ulf_Parameters']); //from uploadFile.php
        $type_media     = (array_key_exists('mediatype', $fileparams)) ?$fileparams['mediatype']:null;
        $type_source = (array_key_exists('source', $fileparams)) ?$fileparams['source']:null;
        
        if($type_source==null || $type_source=='heurist') {
            if ($file['ulf_FileName']) {
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

        
        if (isset($filename)){
            
        $current_file = $file['ulf_ID'].':  '.$filename;
        
        if (file_exists($filename)){
            

            $mimeExt = '';
            if ($file['ulf_MimeExt']) {
                $mimeExt = $file['ulf_MimeExt'];
            } else {
                preg_match('/\\.([^.]+)$/', $file["ulf_OrigFileName"], $matches);    //find the extention
                $mimeExt = $matches[1];
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
          
//http://heurist.sydney.edu.au/h4-ao/admin/verification/findBrokenImages.php?db=ExpertNation          
          
try{  

    $is_img = false;  
    $too_large = false;  

    switch($mimeExt) {
        case 'image/jpeg':
        case 'jpeg':
        case 'jpg':
        case 'image/gif':
        case 'gif':
        case 'image/png':
        case 'png':

            $is_img = true;
            $imageInfo = getimagesize($filename); 
            if(is_array($imageInfo)){
                if(array_key_exists('channels', $imageInfo) && array_key_exists('bits', $imageInfo)){
                    $memoryNeeded = round(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $imageInfo['channels'] / 8 + Pow(2,16)) * 1.65); 
                }else{
                    $memoryNeeded = round($imageInfo[0] * $imageInfo[1]*3);  
                } 
                $mem_usage = memory_get_usage();
                if($mem_usage + $memoryNeeded > $mem_limit - 10485760) { // min($mem_limit, 41943040)){ //40M
                    $too_large = true;
                    print $current_file.' requires too much memory for resize: '.$memoryNeeded.'<br>';
                    //ini_set('memory_limit', (integer) ini_get('memory_limit') + ceil(((memory_get_usage() + $memoryNeeded) - (integer) ini_get('memory_limit') * pow(1024, 2)) / pow(1024, 2)) . 'M'); 
                }                
            }
            break;
        default:
            break;
    }

    $img = null;

    if(!$too_large){
        switch($mimeExt) {
            case 'image/jpeg':
            case 'jpeg':
            case 'jpg':
                $cnt_jpg++;
                if(!$too_large) $img = @imagecreatefromjpeg($filename);
                break;
            case 'image/gif':
            case 'gif':
                $cnt_gif++;
                if(!$too_large) $img = @imagecreatefromgif($filename);
                break;
            case 'image/png':
            case 'png':
                $cnt_png++;
                if(!$too_large) $img = @imagecreatefrompng($filename);
                break;
            default:
                break;
        }
    }

        if($is_img) {
            $cnt_img++;
            
            if(!$too_large){
                if(isset($img)){
                    unset($img);
                    $img = null;
                }else{
                    $cnt_broken++;
                    print 'Can\'t load file ID:'.$current_file.'<br>';    
                }
            }

        }
    

}catch(Exception $e) {
    
    $cnt_broken++;
    print 'Can\'t load file ID:'.$current_file.'<br>'.'System error: '.$e->getMessage().'<br>';
}

        }else{
            print 'File defined but not found. ID:'.$current_file.'<br>';
            $cnt_missed++;
        }        
        }else if($file['ulf_ExternalFileReference']){
            $cnt_external++;
        }
        
        /*if($file['ulf_ID']>11710){
            print $current_file.'<br>';            
        }*/
        
        $cnt_files++;
    }//WHILE
    
    restore_error_handler();
    
    print '<div>FINISHED<br>';
    print 'Total files: '.$cnt_files.'. Missed :'.$cnt_missed.'. External:'.$cnt_external.'<br>';
    print 'jpg '.$cnt_jpg.'<br>';
    print 'png '.$cnt_png.'<br>';
    print 'gif '.$cnt_gif.'<br>';
    print 'Total uploaded images '.$cnt_img.' Broken :'.$cnt_broken.'</div>';
                
                ?>
        </div>
    </body>
</html>