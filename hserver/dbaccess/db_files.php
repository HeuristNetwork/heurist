<?php

    /**
    * CRUD for files : recUploadedFiles

    * file - prefix for functions
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
    require_once (dirname(__FILE__).'/db_users.php');


    /**
    * Get file IDs by Obfuscated ID
    *
    * @param mixed $ulf_ObfuscatedFileID
    */
    function fileGetByObfuscatedId($system, $ulf_ObfuscatedFileID){

        if(!$ulf_ObfuscatedFileID || strlen($ulf_ObfuscatedFileID)<1) return null;

        $res = mysql__select_value($system->get_mysqli(), 'select ulf_ID from recUploadedFiles where ulf_ObfuscatedFileID="'.
            mysqli_real_escape_string(ulf_ObfuscatedFileID).'"');

        return $res;
    }



    /**
    * Get array of local paths, external links, mimtypes and parameters (mediatype and source)
    * for list of file id (may be obsfucated)
    *
    * @param mixed $system
    * @param mixed $file_ids
    */
    function fileGetPath_URL_Type($system, $file_ids){

        if(is_string($file_ids)){
            $file_ids = explode(",", $file_ids);
        }
        if(count($file_ids)>0){

            if(is_numeric($file_ids[0])){
                $query = "ulf_ID in (".implode(",", $file_ids).")";
            }else{
                $query = "ulf_ObfuscatedFileID in ('".implode("','", $file_ids)."')";
            }

            $query = "select concat(ulf_FilePath,ulf_FileName) as fullPath, ulf_ExternalFileReference, fxm_MimeType, ulf_Parameters from recUploadedFiles "
            ." left join defFileExtToMimetype on fxm_Extension = ulf_MimeExt"
            ." where ".$query;

            $mysqli = $system->get_mysqli();
            $res = $mysqli->query($query);

            if ($res){
                $result = array();

                while ($row = $res->fetch_row()){
                    array_push($result, $row);

                    /*
                    $filename = $row[0];
                    $extURL = $row[1];
                    $mimeType = $row[2];

                    if( $filename && file_exists($filename) ){
                    array_push($result, $filename);
                    }else if($extURL && $type!='local'){
                    array_push($result, $extURL);
                    }
                    */

                }
                $res->close();
                return $result;
            }else{
                $system->addError(HEURIST_DB_ERROR, 'Cannot get files', $mysqli->error);
                return false;
            }
        }else{
            return false;
        }

    }

    /**
    * Return full URL to thumbnail for given record ID
    *
    * @param mixed $system
    * @param mixed $recIDs
    */
    function fileGetThumbnailURL($system, $recID){

        $thumb_url = null;

        $query = "select recUploadedFiles.ulf_ObfuscatedFileID".
        " from recDetails".
        " left join recUploadedFiles on ulf_ID = dtl_UploadedFileID".
        " left join defFileExtToMimetype on fxm_Extension = ulf_MimeExt".
        " where dtl_RecID = $recID" .
        " and (dtl_UploadedFileID is not null)".    // no dty_ID of zero so undefined are ignored
        " and (fxm_MimeType like 'image%' or ulf_Parameters like '%mediatype=image%')".
        " limit 1";

        $fileid = mysql__select_value($system->get_mysqli(), $query);
        if($fileid){

            $thumbfile = 'ulf_'.$fileid.'.png';

            if(file_exists(HEURIST_THUMB_DIR . $thumbfile)){
                $thumb_url = HEURIST_THUMB_URL.$thumbfile;
            }else{
                $thumb_url = HEURIST_BASE_URL."file.php?db=".HEURIST_DBNAME."&thumb=".$fileid;
            }
        }


        return $thumb_url;
    }

    /**
    * Returns files for given array of records for specified users
    *
    * @param mixed $system
    * @param mixed $isfull if true returns name and description
    * @param mixed $recIDs - array of record ids
    *
    * @todo search by type
    */
    function fileSearch($system, $isfull, $recIDs, $mediatype=null, $ugrIDs=null){

        $usrFilter = null;
        /* if (!$ugrIDs) {
        $ugrIDs = $system->get_user_id();
        if($ugrIDs){
        $usrFilter = "=".$ugrIDs;
        }
        }else */
        if(is_string($ugrIDs) && $ugrIDs!=""){
            $ugrIDs = explode(",", $ugrIDs);
            if(count($ugrIDs)>0){
                $usrFilter = " in (".implode(",", $ugrIDs).") ";
            }
        }

        $mysqli = $system->get_mysqli();

        $supfields = array("ulf_UploaderUGrpID", "ulf_Modified", "ulf_Description", "ulf_FileSizeKB");
        $supinfo = $isfull? ",".implode(",", $supfields) :"";

        if(is_string($recIDs) && $recIDs!=""){
            $recIDs = explode(",", $recIDs);
        }

        if($recIDs && count($recIDs)>0){
            $recs = "and dtl_RecID in (".implode(",", $recIDs).") ";
        }else{
            $recs = "";
        }

        if(!$usrFilter && $recs=="") {
            //$system->addError(HEURIST_REQUEST_DENIED, 'No user or group defined');
            $system->addError(HEURIST_INVALID_REQUEST, 'Neither users nor records id are defined');
            return false;
        }

        if($usrFilter!=null){

            $query = "SELECT ulf_ID, ulf_OrigFileName, ulf_ObfuscatedFileID ".$supinfo.", count(dtl_RecID) as ulf_Usage "
            ." FROM recUploadedFiles left join recDetails on dtl_UploadedFileID = ulf_ID ".$recs
            ." WHERE ulf_UploaderUGrpID ".$usrFilter
            ." group by ulf_ID, ulf_OrigFileName, ulf_ObfuscatedFileID ".$supinfo;


        }else{
            $query = "SELECT distinct ulf_ID, ulf_OrigFileName, ulf_ObfuscatedFileID ".$supinfo.", 0 as ulf_Usage "
            ." FROM recUploadedFiles, recDetails where dtl_UploadedFileID = ulf_ID ".$recs;
        }

        $res = $mysqli->query($query);

        if ($res){
            $result = array();
            $result[0] = array("ulf_ID", "ulf_OrigFileName", "ulf_ObfuscatedFileID");
            if($isfull){
                $result[0] = array_merge($result[0], $supfields);
            }

            while ($row = $res->fetch_row()){
                //$id = array_shift($row);
                //$result[$id] = $row;
                array_push($result, $row);
            }
            $res->close();
            return $result;
        }else{
            $system->addError(HEURIST_DB_ERROR, 'Cannot get files', $mysqli->error);
            return false;
        }
    }

    /**
    * insert/update file
    *
    * @param mixed $system
    * @param mixed $file  - array of fields (the same as in db)
    */
    function fileSave($system, $file){


        if (!$system->get_user_id()) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if(!@$file['ulf_OrigFileName']){
                $system->addError(HEURIST_INVALID_REQUEST, "Name of file not defined");
                return false;
            }
            if (!( @$file['ulf_ExternalFileReference'] || ( @$file['ulf_FilePath'] && @$file['ulf_FileName']) )){
                $system->addError(HEURIST_INVALID_REQUEST, "Path or link to file not defined");
                return false;
            }

            $isinsert = (!@$file['ulf_ID']);

            if($isinsert){ //insert
                $file['ulf_UploaderUGrpID'] = $system->get_user_id();
            }

            $res = mysql__insertupdate($system->get_mysqli(), "recUploadedFiles", "ulf", $file);
            if(is_numeric($res) && $res>0){

                if($isinsert){ //define obfuscation
                    $file2 = array();
                    $file2['ulf_ID'] = $res;
                    $file2['ulf_ObfuscatedFileID'] = addslashes(sha1($res.'.'.rand()));
                    $res = mysql__insertupdate($system->get_mysqli(), "recUploadedFiles", "ulf", $file2);
                }

                return $res; //returns affected record id
            }else{
                $system->addError(HEURIST_DB_ERROR, 'Cannot update record in database', $res);
                return false;
            }

        }
    }

    /**
    * Delete files
    *
    * @param mixed $system
    * @param mixed $file_ids - list of files to be deleted
    */
    function fileDelete($system, $file_ids, $ugrID=null){

        //verify that current user can delete
        $ugrID = $system->is_admin2($ugrID);
        if (!$ugrID) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if(is_string($file_ids)){
                $file_ids = explode(",", $file_ids);
            }

            //$kwd_ids = array_map('intval', array_keys($_REQUEST['delete_kwds']));
            if (is_array($file_ids) && count($file_ids)>0) {
                $mysqli = $system->get_mysqli();
                $res = $mysqli->query('delete recUploadedFiles, recDetails from recUploadedFiles left join recDetails on dtl_UploadedFileID = ulf_ID where ulf_ID in ('.
                    implode(', ', $file_ids) .') and ulf_UploaderUGrpID='.$ugrID);

                if($res){

                    $listpaths = fileGetPath_URL_Type($system, $file_ids); //@todo $ugrID
                    if(is_array($listpaths)){
                        foreach($listpaths as $fileinfo){
                            if(file_exists($fileinfo[0])){
                                unlink($fileinfo[0]);
                            }
                        }
                    }

                    $cnt = $mysqli->affected_rows;
                    return $cnt;
                }else{
                    $system->addError(HEURIST_DB_ERROR,"Cannot delete file", $mysqli->error );
                    return false;
                }

            }else{
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }

        }
    }

?>
