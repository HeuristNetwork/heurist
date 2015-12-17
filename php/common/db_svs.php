<?php

    /**
    * CRUD for Saved Searches (usrSavedSearches)
    *
    * svs - prefix for functions
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
    //require_once (dirname(__FILE__).'/db_users.php');

    /**
    * Get all saved searches for given user
    *
    * @param mixed $system
    * @param mixed $ugrID - if not defined it searches all
    */
    function svsGetByUser($system, $ugrID=null){

        //user id is not defined - take current user
        if (!$ugrID) {
            $ugrID = $system->get_user_id();

            $current_User = $system->getCurrentUser();
            if($current_User && @$current_User['ugr_Groups'] && count(array_keys($current_User['ugr_Groups']))>0 ){
                $ugrID = implode(",", array_keys($current_User['ugr_Groups'])).",".$ugrID;
            }
            if($system->is_admin()){ //returns guest searches for admin
                $ugrID = $ugrID.",0";
            }

        }
        if(!$ugrID) {
            $ugrID = 0; //get saved searches for guest
        }


        $mysqli = $system->get_mysqli();

        $query = "SELECT svs_ID, svs_Name, svs_Query, svs_UGrpID FROM usrSavedSearches WHERE svs_UGrpID in (".$ugrID.")";

        $res = $mysqli->query($query);

        if ($res){
            $result = array();
            while ($row = $res->fetch_row()){
                $id = array_shift($row);
                $result[$id] = $row;
            }
            $res->close();
            return $result;
        }else{
            $system->addError(HEURIST_DB_ERROR, 'Cannot get saved searches', $mysqli->error);
            return false;
        }
    }

    /**
    * Insert/update saved search
    *
    * @param mixed $system
    * @param mixed $record  - [ svs_ID, svs_UGrpID, svs_Name, svs_Query ]
    */
    function svsSave($system, $record){

        if(!@$record['svs_Name']){
            $system->addError(HEURIST_INVALID_REQUEST, "Name not defined");
        }else if(!@$record['svs_Query']){
            $system->addError(HEURIST_INVALID_REQUEST, "Query not defined");
        }else{

            $record['svs_UGrpID'] = $system->is_admin2(@$record['svs_UGrpID']);
            if (!$record['svs_UGrpID']) {
                $system->addError(HEURIST_REQUEST_DENIED);
            }else{

                $res = mysql__insertupdate($system->get_mysqli(), "usrSavedSearches", "svs", $record);
                if(is_numeric($res)>0){
                    return $res; //returns affected record id
                }else{
                    $system->addError(HEURIST_DB_ERROR, 'Cannot update record in database', $res);
                }

            }
        }
        return false;
    }

    /**
    * Delete saved search
    *
    * @param mixed $system
    * @param mixed $rec_ids  - comma separeted list of IDs
    */
    function svsDelete($system, $rec_ids, $ugrID=null){

        //verify that current user can delete
        $ugrID = $system->is_admin2($ugrID);
        if (!$ugrID) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if(is_string($rec_ids)){
                $rec_ids = explode(",", $rec_ids);
            }

            if (is_array($rec_ids) && count($rec_ids)>0) {

                $query = 'delete from usrSavedSearches where svs_ID in ('. join(', ', $rec_ids) .') and svs_UGrpID='.$ugrID;

                $mysqli = $system->get_mysqli();
                $res = $mysqli->query($query);

                if($res){
                    $cnt = $mysqli->affected_rows;
                    if($cnt>0){
                        return array("status"=>HEURIST_OK, "data"=> $cnt);
                    }else{
                        $system->addError(HEURIST_NOT_FOUND);
                        return false;
                    }
                }else{
                    $system->addError(HEURIST_DB_ERROR,"Cannot delete saved search", $mysqli->error );
                    return false;
                }

            }else{
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }

        }
    }

    /**
    * Save saved searches tree data into HEURIST_SETTING_DIR
    *
    * in future - save into text field in user table ???
    */
    function svsSaveTreeDataFile($system, $data){

        if(defined('HEURIST_SETTING_DIR')){

            //save separately - by groups
            if(true){
                $groups = json_decode($data, true);

                foreach($groups as $id=>$treedata){
                    if($id=="bookmark"){
                        $id = $system->get_user_id()."bookmark";
                    }else if($id=="all"){
                        $id = $system->get_user_id()."all";
                    }
                    $filename = HEURIST_SETTING_DIR . $id . '_svstree.json';
                    $res = file_put_contents ( $filename, json_encode($treedata) );
                    if(!$res){
                        return $res;
                    }
                }

                return true;

            }else{
                //save everything into one file - OLD WAY
                $ugrID = $system->get_user_id();
                $filename = HEURIST_SETTING_DIR . $ugrID . '_svstree.json';
                $res = file_put_contents ( $filename, $data );
            }
            return $res;
        }else{
            $system->addError(HEURIST_SYSTEM_FATAL, "Settings folder is not accessible");
            return false;
        }
    }

    function __get_svs_treeview_file($id, $domain=""){

            $filename = HEURIST_SETTING_DIR . $id . $domain . '_svstree.json';
            if(file_exists($filename)){
                return '"'.(($domain!="")?$domain: $id.$domain).'":'.file_get_contents ($filename);
            }else{
                return null; //'"'.$group_id.'":{}';
            }
    }

    function svsGetTreeDataFile($system){

        $ugrID = $system->get_user_id();
        //load saved search tree data
        if(defined('HEURIST_SETTING_DIR')){

            //load separately - by groups
            if(true){
                $res = array();
                //load rules
                array_push($res, __get_svs_treeview_file("rules"));

                //get list of all groups for this user
                //user_getWorkgroups($system)
                $groups = $system->get_user_group_ids();
                foreach($groups as $group_id){
                    if($group_id==$ugrID){
                        $content = __get_svs_treeview_file($group_id, "bookmark");
                        if($content) array_push($res, $content);
                        $content = __get_svs_treeview_file($group_id, "all");
                        if($content) array_push($res, $content);
                    }else{
                        $content = __get_svs_treeview_file($group_id);
                        if($content) array_push($res, $content);
                    }
                }

                return '{'.implode(',', $res).'}';

            }else{

                $filename = HEURIST_SETTING_DIR . $ugrID . '_svstree.json';
                if(file_exists($filename)){
                    return file_get_contents ($filename);
                }else{
                    return false;
                }
            }
        }else{
            return false;
        }

    }

    /**
    * Save saved searches tree data into sysUGrps
    */
    function svsSaveTreeData($system, $data){

        $mysqli = $system->get_mysqli();

        $groups = json_decode($data, true);

        $personal_data = array();

        $ugrID = $system->get_user_id();
        $ugr_groups = $system->get_user_group_ids();
        $lastID = null;

        foreach($groups as $id=>$treedata){

            if($id=="bookmark" || $id=="all"){
                array_push( $personal_data, '"'.$id.'":'.json_encode($treedata) );
            }else if(in_array($id, $ugr_groups)){
                //check date of modification
                $res = mysql__insertupdate( $mysqli, 'sysUGrps', 'ugr', array('ugr_ID'=>$id, 'ugr_NavigationTree'=>json_encode($treedata) ));
                if(!is_int($res)){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot update navigation tree (personal) on server sode', $res);
                    return false;
                }

                $lastID = $id;
            }
        }

        if(count($personal_data)>0){

                $res = mysql__insertupdate( $mysqli, 'sysUGrps', 'ugr',
                   array( 'ugr_ID'=>$ugrID, 'ugr_NavigationTree'=>implode(',', $personal_data)));

                if(!is_int($res)){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot update navigation tree (personal) on server sode', $res);
                    return false;
                }

                $lastID = $ugrID;
        }

        if($lastID>0){
            //get modification time
            $date = mysql__select_value( $mysqli, 'SELECT `ugr_Modified` FROM `sysUGrps` WHERE ugr_ID='.$lastID);
            return $date;
        }

        $system->addError(HEURIST_INVALID_REQUEST, 'No data provided to update tree on server side');
        return false;
    }

    //
    // $grpID - load tree data only for particular group
    //
    function svsGetTreeData($system, $grpID=null){

        $mysqli = $system->get_mysqli();
        //verify that required column exists in sysUGrps
        $query = "SHOW COLUMNS FROM `sysUGrps` LIKE 'ugr_NavigationTree'";

        $res = $mysqli->query($query);

        $row_cnt = $res->num_rows;
        $res->close();
        if(!$row_cnt){
            //alter table
            $query = "ALTER TABLE `sysUGrps` ADD `ugr_NavigationTree` text COMMENT 'JSON array that describes treeview for filters'";
            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Cannot modify table to store filters treeview', $mysqli->error);
            }
            return false;
        }

        $ugrID = $system->get_user_id();
        //load personal treeviews - rules, my filters (all) and bookmarks
        $groups = $system->get_user_group_ids();

        if(@$grpID>0 && $system->is_member($grpID)){ // array_search($grpID, $groups)){
            $where = ' = '.$grpID;
        }else if(is_array($groups)){
            $where =  ' in ('.implode(',',$groups).')';
        }else {
            $where = ' = '.$ugrID;
        }

        $ret = array();

        $query = 'SELECT `ugr_ID`, `ugr_NavigationTree`, `ugr_Modified` FROM `sysUGrps` WHERE ugr_ID'.$where;
        $res = $mysqli->query($query);
        if(!$res){
            $system->addError(HEURIST_DB_ERROR, 'Cannot retrieve filters treeviews', $mysqli->error);
            return false;
        }
        while ($row = $res->fetch_row()) {
            if($row[1]){
                if($row[0]==$ugrID){
                    array_push($ret, $row[1] );
                }else{
                    //add modification date for groups
                    $treedata = $row[1];
                    //$datetime = new DateTime($row[2]);
                    //$datetime->format(DateTime::ISO8601)
                    $treedata = '{"modified":"'.$row[2].'",'.substr($treedata,1);

                    array_push($ret, '"'.$row[0].'":'.$treedata );
                }
            }
        }
        $res->close();

        return '{'.implode(',', $ret).'}';
    }

?>