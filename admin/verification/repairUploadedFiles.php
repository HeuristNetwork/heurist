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

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    $rv = array();

    header('Content-type: text/javascript');

    if (!is_admin()) {
        $rv['error'] = "Sorry, you need to be a database owner to be able to modify the database structure";
        print json_format($rv);
        return;
    }
    
    $data = null;
    if(@$_REQUEST['data']){
        $data = json_decode(urldecode(@$_REQUEST['data']), true);
    }else{
        $rv['error'] = "Data not defined! Wrong request.";
        print json_format($rv);
        return;
    }

    //4. to remove from recUploadedFiles and delete file
    $files_to_remove = @$data['unlinked'];
    if(is_array($files_to_remove)){
        
        $res = array();
        foreach ($files_to_remove as $file) {

            if(file_exists($file)){
                if(unlink($file)) array_push($res, $file);
            }
        }
        $rv['result'] = $res;
        
    }else{

    
    //1. to remove from recUploadedFiles and delete file
    $files = @$data['orphaned'];
    $ids = array();
    if(is_array($files))
    foreach ($files as $file) {

        $ulf_ID = $file[0];
        $isfound = $file[1]; //if true - find and delete file if in db root or uploaded files
        
        if($isfound==1){
            deleteUploadedFiles($ulf_ID);
        }
        $ids[] = $ulf_ID;
    }
    
    if(count($ids)>0){
        mysql_query('delete from recUploadedFiles where ulf_ID in ('.implode(',',$ids).')');
        if (mysql_error()) {
            $rv['error'] = "Cannot delete entries from recUploadedFiles. mySQL error: " . mysql_error();
            print json_format($rv);
            return;
        }
    }
    
    //2. to remove from recUploadedFiles, recDetails
    $file_ids = @$data['notfound'];
    if(is_array($file_ids) && count($file_ids)>0){
        mysql_query('delete from recDetails where dtl_UploadedFileID in ('.implode(',',$file_ids).')');
        if (mysql_error()) {
            $rv['error'] = "Cannot delete entries from recDetails. mySQL error: " . mysql_error();
            print json_format($rv);
            return;
        }
        mysql_query('delete from recUploadedFiles where ulf_ID in ('.implode(',',$file_ids).')');
        if (mysql_error()) {
            $rv['error'] = "Cannot delete entries from recUploadedFiles. mySQL error: " . mysql_error();
            print json_format($rv);
            return;
        }
    }
        
    //3. correct path
    $file_ids = @$data['fixpath'];
    if(is_array($file_ids))
    foreach ($file_ids as $ulf_ID) {


        $filedata = get_uploaded_file_info_internal($ulf_ID, false);
        
        if($filedata!=null){

            $filename = $filedata['fullpath'];
            
            $is_local = (strpos($filename,'http://')===false && strpos($filename,'https://')===false);
            
            if($is_local){
            
                chdir(HEURIST_FILESTORE_DIR);  // relatively db root
                $fpath = realpath($filename);
                if(!$fpath || !file_exists($fpath)){
                    chdir(HEURIST_FILES_DIR);  // relatively db root
                    $fpath = realpath($filename);
                }
                $path_parts = pathinfo($fpath);
                $dirname = $path_parts['dirname'].'/';
                $file_name = $path_parts['filename'];
                
                $relative_path = getRelativePath(HEURIST_FILESTORE_DIR, $dirname);   //db root folder
                
                $query = 'update recUploadedFiles set ulf_FilePath="'
                                .mysql_real_escape_string($relative_path)
                                .'", ulf_FileName="'
                                .mysql_real_escape_string($file_name).'" where ulf_ID = '.$ulf_ID;
                mysql_query($query);
                
            }else{

                $query = 'update recUploadedFiles set ulf_ExternalFileReference="'
                                .mysql_real_escape_string($filename)
                                .'", ulf_FilePath=NULL, ulf_FileName=NULL where ulf_ID = '.$ulf_ID;
                mysql_query($query);
                  
            }
        }
    }
    
    
        $rv['result'] = "Uploaded files have been repaired.";
    }
    
    print json_format($rv);
?>
