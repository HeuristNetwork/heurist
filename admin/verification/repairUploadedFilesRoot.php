<?php
    /*
    * Copyright (C) 2005-2016 University of Sydney
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
    * Correct file paths in recUploadFiles table, find missed and orphaned images
    *
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @copyright   (C) 2005-2016 University of Sydney
    * @link        http://HeuristNetwork.org
    * @version     3.1
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
    require_once(dirname(__FILE__).'/../../records/files/uploadFile.php');
    require_once(dirname(__FILE__).'/../../import/fieldhelper/harvestLib.php');

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    $rv = array();

    header('Content-type: text/javascript');

    if (!is_admin()) {
        $rv['error'] = "Sorry, you need to be a database owner to perform this action";
        print json_format($rv);
        return;
    }
    
/*

move all the files in the root into resources/ulfFromRoot, resources/xslFromRoot, resources/xmlFromRoot, resources/otherFromRoot, and update the ULF records.
 
1.Select all ulf  where ulf_ExternalFileReference is null and (ulf_FilePath='' OR ulf_FilePath is null)  - 691 entries
2.Copy file to designated folder update ulf_FilePath
3.Copy remaining (unregistered) files to the appropriate folders

*/    
    //create folder resources
    $folder = HEURIST_FILESTORE_DIR.'resources/';
    if(!makeFolderIfNotExists($folder)) return;


    $query2 = 'SELECT ulf_ID, ulf_FileName FROM recUploadedFiles '
                .'where ulf_ExternalFileReference is null and (ulf_FilePath=\'\' OR ulf_FilePath is null)';
    $res2 = mysql_query($query2);
    if ($res2 && mysql_num_rows($res2) > 0) {
        
            //create folder resources/ulfFromRoot
            $folder = HEURIST_FILESTORE_DIR.'resources/ulfFromRoot/';
            if(!makeFolderIfNotExists($folder)) return;
            
            $files = array();
            //
            while ($res = mysql_fetch_array($res2)) {
                $fpath = $res[1];
                chdir(HEURIST_FILESTORE_DIR);  // relatively db root
                $fpath = realpath($fpath);
                if(file_exists($fpath)){
                    //file to move
                    $files[$res[0]] = $res[1];//$fpath;
                }
            }//while
            
            if(count($files)>0){
            //update entries - change path
            mysql_query('update recUploadedFiles set ulf_FilePath="resources/ulfFromRoot/" where ulf_ID in ('
                            .implode(',',array_keys($files)).')');
            if (mysql_error()) {
                $rv['error'] = "Cannot update paths from recUploadedFiles. mySQL error: " . mysql_error();
            }else{
                //move all files
                foreach ($files as $ulf_ID=>$file){
                    
                    rename(HEURIST_FILESTORE_DIR.$file, HEURIST_FILESTORE_DIR.'resources/ulfFromRoot/'.$file);
                    //copy($fpath, );
                    //unlink($file);
                }                
                $rv['ulfFromRoot'] = count($files);
            }
            }
    }
    
    
    //harvest for xsl, xml and others
    $all_files = scandir(HEURIST_FILESTORE_DIR);
    $moved = 0;
    foreach ($all_files as $filename){
        if(!($filename=="." || $filename==".." || is_dir(HEURIST_FILESTORE_DIR.$filename))){
            
            if($filename=='userInteraction.log' || $filename=='index.html') continue;

            $fullpath = HEURIST_FILESTORE_DIR.$filename;
            $flleinfo = pathinfo($fullpath);
            $file_ext = strtolower(@$flleinfo['extension']);

            //checks for allowed extensions
            if($file_ext=='xsl')
            {
                $folder = HEURIST_FILESTORE_DIR.'resources/xslFromRoot/';
            }else if($file_ext=='xml'){
                $folder = HEURIST_FILESTORE_DIR.'resources/xmlFromRoot/';
            }else{
                $folder = HEURIST_FILESTORE_DIR.'resources/otherFromRoot/';
            }
            if(!makeFolderIfNotExists($folder)) return;

            rename(HEURIST_FILESTORE_DIR.$filename, $folder.$filename);
            $moved++;
        }
    }  //for all_files
    
    $rv['xsl_xml_otherFromRoot'] = $moved;
    
/*

balipaintings/old_dir_labelled_balipaintings seems to contain a backup of data, including 1200 files in old_dir_labelled_balipaintings/backup_from_balipaintings/Johnson. Can we get rid of this altogether (move to /srv/HEURISTFILESTORE/MISCELLANEOUS)? Many of media record refers to images in these directories. Then move to resources/earlyFiles as the current naming is jsut confusing and should not be left the way it is.
 
Select all ulf  where ulf_ExternalFileReference is null and ulf_FilePath like 'old_dir_labelled_balipaintings%'  (1901 entries at the moment)
Move files to resources/earlyFiles update ulf_FilePath
Remove remnants files in old_dir_labelled_balipaintings to MISCELLANEOUS
Remove folder old_dir_labelled_balipaintings

*/

    $query2 = 'SELECT ulf_ID, ulf_FilePath, ulf_FileName FROM recUploadedFiles '
                .'where (ulf_ExternalFileReference is null) and (ulf_FilePath like \'old_dir_labelled_balipaintings%\')';
    $res2 = mysql_query($query2);
    if ($res2 && mysql_num_rows($res2) > 0) {
        
            //create folder resources/earlyFiles
            $folder = HEURIST_FILESTORE_DIR.'resources/earlyFiles/';
            if(!makeFolderIfNotExists($folder)) return;
            
            
            $moved_files = 0;
            //
            while ($res = mysql_fetch_array($res2)) {
                $fpath = $res[1].$res[2];
                chdir(HEURIST_FILESTORE_DIR);  // relatively db root
                $fpath = realpath($fpath);
                if(file_exists($fpath)){
                    //file to move
                    //update entries - change path
                    mysql_query('update recUploadedFiles set ulf_FilePath=\'resources/earlyFiles/\' where ulf_ID='.$res[0]);
                    if (mysql_error()) {
                        $rv['error'] = "Cannot update path from recUploadedFiles. mySQL error: " . mysql_error();
                        print json_format($rv);
                        exit;
                    }else{
                        rename($fpath, HEURIST_FILESTORE_DIR.'resources/earlyFiles/'.$res[2]);
                        $moved_files++;
                    }
                }
            }//while
            
            $rv['earlyFiles'] = $moved_files;
    }


    //find all remain files in old_dir_labelled_balipaintings    
            
    // $reg_info - global array to be filled in doHarvest
    $reg_info = array();
    $dirs_and_exts = getMediaFolders();
    $dirs_and_exts['dirs'] = array(HEURIST_FILESTORE_DIR.'old_dir_labelled_balipaintings/');
    doHarvest($dirs_and_exts, false, 0);
    
    $moved = 0;
    if(count($reg_info)>0){

        //create folder MISCELLANEOUS
        $folder = HEURIST_FILESTORE_DIR.'MISCELLANEOUS/';
        if(!makeFolderIfNotExists($folder)) return;

        foreach ($reg_info as $filepath){

            $flleinfo = pathinfo($filepath);
            $filename = $flleinfo['basename'];

            rename($filepath, $folder.$filename);
            $moved++;

        }  //for $reg_info

    }
    
    $rv['MISCELLANEOUS'] = $moved;
    
    print json_format($rv);

//
//
//    
function makeFolderIfNotExists($folder){
     if(!file_exists($folder)){
        if (!mkdir($folder, 0777, true)) {
            $rv['error'] = "Unable to create folder $folder";
            print json_format($rv);
            return false;
        }
    }
    return true;
}    
?>
