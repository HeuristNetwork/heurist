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

    //------------------------------------------------------
    //Remove non-registred files
    $files_to_remove = @$data['files_notreg'];
    if(is_array($files_to_remove)){
        
        $res = array();
        foreach ($files_to_remove as $file) {

            if(file_exists($file)){
                if(unlink($file)) array_push($res, $file);
            }
        }
        $rv['result'] = $res;
        print json_format($rv);   
        exit;
    }
    
    //------------------------------------------------------
    // remove registration for nonused entries in ulf
    $regs_to_remove = @$data['unused_file_local'];
    if(!is_array($regs_to_remove)){
        $regs_to_remove = @$data['unused_file_remote'];        
    }
    if(is_array($regs_to_remove) && count($regs_to_remove)>0){

        mysql_query('delete from recUploadedFiles where ulf_ID in ('.implode(',',$regs_to_remove).')');
        if (mysql_error()) {
            $rv['error'] = "Cannot delete entries from recUploadedFiles. mySQL error: " . mysql_error();
            print json_format($rv);
        }else{
            $rv['result'] = $regs_to_remove;
            print json_format($rv);
        }
        exit;
    }    
    
    /*
    foreach ($files as $file) {
        $ulf_ID = $file[0];
        $isfound = $file[1]; //if true - find and delete file if in db root or uploaded files
        if($isfound==1){
            deleteUploadedFiles($ulf_ID);
        }
        $ids[] = $ulf_ID;
    }
    */
    
    //------------------------------------------------------
    // remove missed files
    $file_ids = @$data['files_notfound'];
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
        $rv['result'] = $file_ids;
        print json_format($rv);
        exit;
    }
    
    $rv['error'] = "Wrong parameters. No data defined";
    print json_format($rv);
?>
