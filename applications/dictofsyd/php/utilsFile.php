<?php
    /*
    * Copyright (C) 2005-2013 University of Sydney
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
    * Utilities for working with files for Dictionary of Sydney applications
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2013 University of Sydney
    * @link        http://sydney.edu.au/heurist
    * @version     3.1.0
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  applications
    */

    /**
    * Loads remote content
    *
    * @param mixed $url
    * @param mixed $range
    */
    function loadRemoteURLContentWithRange($url, $range) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	//return the output as a string from curl_exec
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);	//don't include header in output
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);	// timeout after ten seconds
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections

        if($range){
            curl_setopt($ch, CURLOPT_RANGE, $range);
        }

        if (defined("HEURIST_HTTP_PROXY")) {
            curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);

        $error = curl_error($ch);
        if ($error) {
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            //echo "$error ($code)" . " url = ". $url;
            error_log("$error ($code)" . " url = ". $url);
            curl_close($ch);
            return false;
        } else {
            curl_close($ch);
            return $data;
        }
    }

    /**
    * Save data into file
    *
    * @param mixed $rawdata
    * @param mixed $filename
    */
    function saveAsFile($rawdata, $filename)
    {

        if($rawdata){
            if(file_exists($filename)){
                unlink($filename);
            }
            $fp = fopen($filename,'x');
            fwrite($fp, $rawdata);
            fclose($fp);

            return filesize($filename);
        }else{
            return 0;
        }
    }

    /**
    * Creates new folder if it is not exist
    *
    * @param mixed $folder
    */
    function createDir($folder){
        if(!file_exists($folder)){
            if (!mkdir($folder, 0777, true)) {
                die('Failed to create folder for '.$folder.' - please check permissions');
            }
        }
    }

    /**
    * Get information about file from database
    *
    * @param mixed $fileID
    */
    function get_uploaded_file_info_internal($fileID)
    {
        global $urlbase, $is_generation, $media_filepath;  // these are from DoS generator

        $res = null;

        //saw NOTE! these field names match thoses used in HAPI to init an HFile object.
        $query = 'select ulf_ID as id,
        ulf_ObfuscatedFileID as nonce,
        ulf_OrigFileName as origName,
        ulf_FileSizeKB as fileSize,
        fxm_MimeType as mimeType,
        ulf_Added as date,
        ulf_Description as description,
        ulf_MimeExt as ext,
        ulf_ExternalFileReference as remoteURL,
        ulf_Parameters as parameters,
        concat(ulf_FilePath,ulf_FileName) as fullpath,
        ulf_PreferredSource as prefsource

        from recUploadedFiles left join defFileExtToMimetype on ulf_MimeExt = fxm_Extension
        where '.(is_numeric($fileID)
            ?'ulf_ID = '.intval($fileID)
            :'ulf_ObfuscatedFileID = "'.addslashes($fileID).'"') ;

        $fres = mysql_query($query);
        if (mysql_num_rows($fres) == 1) {

            $res = mysql_fetch_assoc($fres);

            if($is_generation && $media_filepath)
            {
                $res["thumbURL"] = $urlbase.$media_filepath."thumbnail/".$res["nonce"];
                $res["URL"] = $urlbase.$media_filepath."full/".$res["nonce"];

            }else{

                if($is_generation){
                    $_pre = "../../";
                }else{
                    $_pre = $urlbase;
                }

                $thumbnail_file = "ulf_".$res["nonce"].".png";
                if(file_exists(HEURIST_THUMB_DIR.$thumbnail_file)){
                    $res["thumbURL"] = HEURIST_THUMB_URL.$thumbnail_file;
                }else{
                    $res["thumbURL"] = $_pre."php/getMedia.php?id=".$res["nonce"]."&thumb=1";
                    //HEURIST_BASE_URL."common/php/resizeImage.php?".(defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=".$res["nonce"];
                }

                $downloadURL = $_pre."php/getMedia.php?id=".$res["nonce"];

                //$origName = urlencode($res["origName"]);
                //HEURIST_BASE_URL."records/files/downloadFile.php/".$origName."?".(defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )."ulf_ID=".$res["nonce"];

                if($res["remoteURL"]!=null || $res["prefsource"]=="external") {
                    $res["URL"] = $res["remoteURL"];
                }else{
                    $res["URL"] = $downloadURL;
                }

            }

        }


        return $res;
    }

    /**
    * Creates thumbnail image
    *
    * @param mixed $filedata
    */
    function makeThumbnailImage($filedata, $thumb_folder, $needreturn){

        $filename = $filedata['fullpath'];
        if(!file_exists($filename)){
            return;
        }
        if($thumb_folder==HEURIST_THUMB_DIR){
            $thumbnail_file = $thumb_folder."ulf_".$filedata["nonce"].".png";    
        }else{
            $thumbnail_file = $thumb_folder.$filedata["nonce"];    
        }
        
        if(!$needreturn || !file_exists($thumbnail_file)){ //if needreturn fales - this is generation - always recreate thumb

//error_log("create for ".$filedata['ext']);
           
            switch($filedata['ext']) {
                case 'jpeg':
                case 'jpg':
                    $img = imagecreatefromjpeg($filename);
                    break;
                case 'gif':
                    $img = imagecreatefromgif($filename);
                    break;
                case 'png':
                    $img = imagecreatefrompng($filename);
                    break;
                default:
                   /* copy thumbnails for non-image files 
                   $thumbnail_file_orig = HEURIST_THUMB_DIR."ulf_".$filedata["nonce"].".png";
                   if(file_exists($thumbnail_file_orig)){
                        $res = copy($thumbnail_file_orig, $thumbnail_file);
                   }
                   */
                   return;
            }

            $x = 148;
            $y = 148;
            $no_enlarge = false;

            // calculate image size
            // note - we never change the aspect ratio of the image!
            $orig_x = imagesx($img);
            $orig_y = imagesy($img);

            $rx = $x / $orig_x;
            $ry = $y / $orig_y;

            $scale = $rx;
            
            //$scale = $rx ? $ry ? max($rx, $ry) : $rx : $ry;

            if ($no_enlarge  &&  $scale > 1) {
                $scale = 1;
            }

            $new_x = ceil($orig_x * $scale);
            $new_y = ceil($orig_y * $scale);

            $img_resized = imagecreatetruecolor($new_x, $new_y)  or die;
            imagecopyresampled($img_resized, $img, 0, 0, 0, 0, $new_x, $new_y, $orig_x, $orig_y)  or die;


            imagepng($img_resized, $thumbnail_file);
            imagedestroy($img);
            imagedestroy($img_resized);

            $img = null;
            $img_resized = null;
        }

        if($needreturn){
            return file_get_contents($thumbnail_file);
        }
    }

    /**
    * Copy full media file and create thumbnail (for images)
    *
    * @param mixed $folder
    * @param mixed $record
    */
    function publicMedia($record, $folder){

        $res = true;
        //find full info
        $fileid = $record->getDet(DT_FILE, 'dtfile');
        $filedata = get_uploaded_file_info_internal($fileid);

        $filename_orig = $filedata['fullpath'];

        if(file_exists($filename_orig)){
            
            $filename_dest = $folder."full/".$filedata["nonce"];
            
            if(!file_exists($filename_dest) || filectime($filename_orig)>filectime($filename_dest)){

                //copy full size from db to destination folder
                $res = copy($filename_orig, $filename_dest);
                
                if($filedata['ext']=="flv"){
                    $fileid = $record->getDet(DT_FILE_THUMBNAIL, 'dtfile');
                    $filedata = get_uploaded_file_info_internal($fileid);
//$thumbnail_file_orig = HEURIST_THUMB_DIR."ulf_".$filedata["nonce"].".png";    
//error_log(">>>>>".$thumbnail_file_orig.'  '.file_exists($thumbnail_file_orig));                   
                }
                
                //make thumbnail in destination folder
                makeThumbnailImage($filedata,  $folder."thumbnail/", false);
                
                print "       ".$filename_orig." => ".$filename_dest."<br />";
                
                //create thumbnail and copy
                /* Ian req: always recreate thumbnail in dest folder
                $thumbnail_file = HEURIST_THUMB_DIR."ulf_".$filedata["nonce"].".png";
                if(file_exists($thumbnail_file)){
                    $res = copy($thumbnail_file, $folder."thumbnail/".$filedata["nonce"]);
                }*/
            }            
        }else{
            add_error_log("ERROR >>> Media record#".$record->id().". File not found ".$filename_orig);
        }

        return $res;
    }

    /**
    * proxy to download file
    *
    * @param mixed $mimeType
    * @param mixed $filename
    */
    function downloadFile($mimeType, $filename){

        if ($mimeType) {
            header('Content-type: ' .$mimeType);
        }else{
            header('Content-type: binary/download');
        }

        /*
        if($mimeType!="video/mp4"){
        header('access-control-allow-origin: *');
        header('access-control-allow-credentials: true');
        }*/
        readfile($filename);
    }

    //
    function getStaticSubfolder($rec_type, $entity_type){
        $subfolder = "";
        if($rec_type==RT_MEDIA){
            $subfolder = "item";
        }else if ($rec_type==RT_ENTITY){
            $subfolder = getNameByCode($entity_type); //static function from Record
        }else{
            $subfolder = getNameByCode($rec_type);
        }
        
        //two stupid exclusions
        if($subfolder=="term"){
            $subfolder = "subject";
        }else if($subfolder=="natural"){
            $subfolder = "natural_feature";
        }
        
        return $subfolder;
    }

    //
    function getStaticFileName($rec_type, $entity_type, $rec_name, $rec_id){

        $subfolder = getStaticSubfolder($rec_type, $entity_type);

        if($rec_type==RT_MEDIA || $rec_type==RT_MAP){
            $rname = $rec_id;
        }else{
            $rname = $rec_name;
            $rname = strtolower(trim($rname));

            $rname = str_replace(" ","_",$rname);
            $rname = str_replace(",","",$rname);
            $rname = str_replace("'","",$rname);

            //$rname = preg_replace('/[^a-z\d ]/i', '', $rname);
            //$rname = str_replace(' ','_',$rname);
        }

        return $subfolder."/".$rname;
    }
?>
