<?php

    /**
    * CRUD for Saved Searches (usrSavedSearches)
    *
    * svs - prefix for functions
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
    //require_once (dirname(__FILE__).'/db_users.php');

    
    /**
    * Get all saved searches for given list of ids
    *
    * @param mixed $system
    */
    function svsGetByIds($system, $rec_ids=null){

        if ($rec_ids) {

            $rec_ids = prepareIds($rec_ids);
            
            if (count($rec_ids)>0) {

                $mysqli = $system->get_mysqli();
                $query = 'SELECT svs_ID, svs_Name, svs_Query, svs_UGrpID FROM usrSavedSearches WHERE svs_ID in ('
                        .implode(',', $rec_ids).')';
            
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
        }
        
        $system->addError(HEURIST_INVALID_REQUEST,
                'Cannot get filter criteria. IDs are not defined');
        return false;
    }
    
    
    /**
    * Get all saved searches for given user
    *
    * @param mixed $system
    * @param mixed $ugrID - if not defined it searches all
    * @param $keep_order - keep order as define in groups tree
    */
    function svsGetByUser($system, $ugrID=null, $keep_order=false){

        $mysqli = $system->get_mysqli();
        
        //user id is not defined - take current user
        if (!$ugrID) {
            $ugrID = $system->get_user_id();

            //$groups = user_getWorkgroups($mysqli, $ugrID); 
            //if( $groups && count($groups)>0){
            
            $ugr_groups = $system->get_user_group_ids(null, true); //always get latest
            
            $current_User = $system->getCurrentUser();
            if($current_User && @$current_User['ugr_Groups'] && count(array_keys($current_User['ugr_Groups']))>0 ){
                $ugrID = implode(',', array_keys($current_User['ugr_Groups'])).','.$ugrID;
            }
            if($system->is_admin()){ //returns guest searches for admin
                $ugrID = $ugrID.',0';
            }

        }else if(is_array($ugrID)){
            $ugrID = implode(',', $ugrID);
        }
        
        if(!$ugrID) {
            $ugrID = '0,4'; //get saved searches for guest and websearches
        }
        
        if($keep_order){
            $order = array();
            $query = 'SELECT ugr_NavigationTree FROM `sysUGrps` WHERE ugr_ID in ('.$ugrID.')';
            $res = $mysqli->query($query);
            if($res){
                while ($row = $res->fetch_row()) {
                     $treedata = json_decode($row[0],true);
                     if($treedata!=null && is_array($treedata)){
                        svsGetOrderFromTree($treedata, $order);    
                     }
                     
                }
            }
        }
        
        $query = 'SELECT svs_ID, svs_Name, svs_Query, svs_UGrpID FROM usrSavedSearches WHERE svs_UGrpID in ('.$ugrID.')';

        if($keep_order && count($order)>0){
            $query = $query.' order by FIELD(svs_ID,'.implode(',',$order).')';
        }

//error_log($query);        
        $res = $mysqli->query($query);

        if ($res){
            $order = array();
            $result = array();
            while ($row = $res->fetch_row()){
                $id = array_shift($row);
                $result[$id] = $row;
                array_push($order, $id);
            }
            $res->close();
            if($keep_order){
                return array('order'=>$order, 'svs'=>$result);                
            }else{
                return $result;    
            }
        }else{
            $system->addError(HEURIST_DB_ERROR, 'Cannot get saved searches', $mysqli->error);
            return false;
        }
    }

    //
    //
    //
    function svsGetOrderFromTree($tree, &$order){
        
        foreach($tree as $key=>$value){
            if($key=='children'){
                svsGetOrderFromTree($value, $order);
            }
            else if (is_array($value) && @$value['key']>0 && @$value['folder']!==true)
            {
                array_push($order, $value['key']);
            }
        }
    }
    
    /**
    * Duplicate given saved search
    * 
    * @param mixed $system
    * @param mixed $record
    */
    function svsCopy($system, $record){
        
        if (!(@$record['svs_ID']>0)){
            $system->addError(HEURIST_INVALID_REQUEST, 'ID for saved search to be duplicated is not defined'); //for new 
        }else{
            
            //refresh groups
            $system->get_user_group_ids(null, true);
            $mysqli = $system->get_mysqli();
            
            $row = mysql__select_row_assoc($mysqli, 
                    'select svs_UGrpID, svs_Name, svs_Query FROM usrSavedSearches WHERE svs_ID='.$record['svs_ID']);
                
            if (!$row) { 
                $system->addError(HEURIST_NOT_FOUND, 
                    'Cannot duplicate filter criteria. Original filter not found');
            }else if (!$system->is_member($row['svs_UGrpID'])) { //was has_access
                $system->addError(HEURIST_REQUEST_DENIED, 
                    'Cannot duplicate filter criteria. Current user must be member for group');
            }else{
                    //get new name
                    $new_name = $row['svs_Name'].' (copy)';
            
                    $query = 'INSERT INTO `usrSavedSearches` '
                    .'(`svs_Name`,`svs_Added`,`svs_Modified`,`svs_Query`,`svs_UGrpID`,`svs_ExclusiveXSL`)'
                    .' SELECT "'.$new_name.'",`svs_Added`,`svs_Modified`,`svs_Query`,`svs_UGrpID`,`svs_ExclusiveXSL` '
                    .' FROM usrSavedSearches WHERE svs_ID = '.$record['svs_ID'];                    

                    $res = $mysqli->query($query);
                    
                    if(!$res){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot copy saved filter #'
                             .$record['svs_ID'].' in database', $mysqli->error);
                    }else{
                        return array('svs_ID'=>$mysqli->insert_id,
                            'svs_Name'=>$new_name,'svs_Query'=>$row['svs_Query'],'svs_UGrpID'=>$row['svs_UGrpID']);
                    }
            }
            
        }
        return false;
        
    }

    /**
    * Insert/update saved search
    *
    * @param mixed $system
    * @param mixed $record  - [ svs_ID, svs_UGrpID, svs_Name, svs_Query ]
    */
    function svsSave($system, $record){

        if( !(@$record['svs_ID']>0) && !@$record['svs_Name']){
            $system->addError(HEURIST_INVALID_REQUEST, "Name not defined"); //for new 
        }else if(!(@$record['svs_ID']>0) && !@$record['svs_Query']){
            $system->addError(HEURIST_INVALID_REQUEST, "Query not defined"); //for new 
        }else{
            
            //refresh groups
            $system->get_user_group_ids(null, true);
            
            if (!$system->is_member(@$record['svs_UGrpID'])) { //was has_access
                $system->addError(HEURIST_REQUEST_DENIED, 
                    'Cannot update filter criteria. Current user must be member for group');
            }else{
                
                if(is_array(@$record['svs_ID'])){
                    $rec_IDs = $record['svs_ID'];
                }else if (@$record['svs_ID']>0){
                    $rec_IDs = array($record['svs_ID']);
                }else{
                    $rec_IDs = array(-1); //new   
                }
                
                foreach($rec_IDs as $svs_ID){
                    $record['svs_ID'] = $svs_ID;                             
                    $res = mysql__insertupdate($system->get_mysqli(), "usrSavedSearches", "svs", $record);
                    if(is_numeric($res)>0){
                        return $res; //returns affected record id
                    }else{
                        $system->addError(HEURIST_DB_ERROR, 'Cannot update saved filrer #'.$svs_ID.' in database', $res);
                    }
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
        if (!$system->has_access($ugrID)) {
            $system->addError(HEURIST_REQUEST_DENIED,
                'Cannot delete filter criteria. Current user must be an administrator for group');
            return false;
        }else{

            $rec_ids = prepareIds($rec_ids);

            if (count($rec_ids)>0) {

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
    * Save saved searches tree data into sysUGrps
    */
    function svsSaveTreeData($system, $data){

        $mysqli = $system->get_mysqli();

        $groups = json_decode($data, true);

        $personal_data = array();

        $ugrID = $system->get_user_id();
        $ugr_groups = $system->get_user_group_ids(null, true); //always get latest
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

        $system->addError(HEURIST_INVALID_REQUEST, 'No data provided to update tree on server side.'
        .' This may be due to a network outage or minor database corruption. It means the changes you have just made may not have been'
        .' written into the database - please reload the page and check to see if they have been saved, try again, and '
        . CONTACT_HEURIST_TEAM.' if the problem persists');
        return false;
    }

    //
    // $grpID - load tree data only for particular group
    //
    function svsGetTreeData($system, $grpID=null){

        $mysqli = $system->get_mysqli();

        $ugrID = $system->get_user_id();

        if($grpID!=null){
            $groups = prepareIds($grpID, true);        
        }else{
            //load personal treeviews - rules, my filters (all) and bookmarks
            $groups = $system->get_user_group_ids();
        }

        // 5 - websearch
        //if(@$grpID>0 && ($system->is_member($grpID) || $grpID==5) ){
        if(is_array($groups) && count($groups)==1){
            $where = ' = '.$groups[0];
        }else if(is_array($groups) && count($groups)>1){
            $where =  ' in ('.implode(',',$groups).')';
        }else {
            $where = ' = '.$ugrID; //only personal
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
    
    //
    // recognize mime type from url, update ext table if missed and returns extension
    //
    function recognizeMimeTypeFromURL($mysqli, $url){ 
                   
            //special cases for well known resources
            $force_add = null;
            $extension = null;
            $needrefresh = false;
            $mimeType = null;
            
            $url = strtolower($url);
            
            if(strpos($url, 'soundcloud.com')!==false){
                $mimeType  = 'audio/soundcloud';
                $extension = 'soundcloud';
                $force_add = "('soundcloud','audio/soundcloud', '0','','Soundcloud','')";
            }else if(strpos($url, 'vimeo.com')!==false){
                $mimeType  = 'video/vimeo';
                $extension = 'vimeo';
                $force_add = "('vimeo','video/vimeo', '0','','Vimeo Video','')";
            }else  if(strpos($url, 'youtu.be')!==false || strpos($url, 'youtube.com')!==false){
                $mimeType  = 'video/youtube';
                $extension = 'youtube';
                $force_add = "('youtube','video/youtube', '0','','Youtube Video','')";
            }else{
                //get extension from url - unreliable
                /*$ap = parse_url($r_value);
                if( array_key_exists('path', $ap) ){
                    $path = $ap['path'];
                    if($path){
                        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    }
                }*/    

                $mimeType = loadRemoteURLContentType($url); 
            }
            
            if($mimeType!=null && $mimeType!==false){
                $ext_query = 'SELECT fxm_Extension FROM defFileExtToMimetype WHERE fxm_MimeType="'
                            .$mimeType.'"';
                $f_extension = mysql__select_value($mysqli, $ext_query);
                
                if($f_extension==null && $force_add!=null){
                    $mysqli->query('insert into defFileExtToMimetype ('
        .'`fxm_Extension`,`fxm_MimeType`,`fxm_OpenNewWindow`,`fxm_IconFileName`,`fxm_FiletypeName`,`fxm_ImagePlaceholder`'
                    .') values '.$force_add);
                    $needrefresh = true;
                }else{
                    $extension = $f_extension;
                }
            }
            //if extension not found apply bin: application/octet-stream - generic mime type
            if($extension==null) $extension = 'bin';   
            $res = array('extension'=>$extension, 'needrefresh'=>$needrefresh);
            
            return $res;
    }                
       

?>