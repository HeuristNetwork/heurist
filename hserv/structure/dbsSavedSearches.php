<?php
/**
* dbsSavedSearches.php - Functions library to work with table usrSavedSearches
*
* @todo - replace with entity/DbUsrSavedSearches 
*
* @project     Heurist academic knowledge management system
* @package Structure
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/**
 * This file contains functions for managing Saved Searches within the Heurist system.
 * These functions provide CRUD (Create, Read, Update, Delete) operations for
 * saved search definitions, which are stored in the `usrSavedSearches` table.
 * They also handle the organization and storage of these saved searches within
 * hierarchical navigation trees, typically stored in the `ugr_NavigationTree`
 * field of the `sysUGrps` table for users and groups.
 *
 * Functions in this file are typically prefixed with `svs_`.
 *
 * @package Structure
 */

    /**
     * Retrieves specific saved searches by their IDs.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array|string|null $rec_ids An array or comma-separated string of saved search IDs (svs_ID).
     * @return array|false An associative array where keys are `svs_ID` and values are arrays
     *                     containing `[svs_Name, svs_Query, svs_UGrpID]`. Returns `false` on error
     *                     or if no valid IDs are provided.
     */
    function svsGetByIds($system, $rec_ids=null){

        if ($rec_ids) {

            $rec_ids = prepareIds($rec_ids);

            if (!empty($rec_ids)) {

                $mysqli = $system->getMysqli();
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
     * Retrieves all saved searches accessible to a specified user or group, or the current user by default.
     *
     * It fetches searches owned by the user and also searches owned by any groups the user is a member of.
     * Administrators can see searches belonging to group 0 (guest/public).
     * Optionally, results can be ordered according to the structure defined in the user's or group's
     * navigation tree (`ugr_NavigationTree`).
     *
     * @param \hserv\System $system The Heurist system object.
     * @param int|string|null $ugrID (Optional) A specific User/Group ID or a comma-separated list of IDs.
     *                               If null, defaults to the current user and their associated groups.
     * @param bool $keep_order (Optional) If true, the returned searches are ordered based on their
     *                         appearance in the relevant navigation tree(s). Default is false.
     * @return array|false If `$keep_order` is true, returns an associative array:
     *                     `['order' => [ordered_svs_IDs...], 'svs' => [svs_ID => [svs_Name, svs_Query, svs_UGrpID], ...]]`.
     *                     If `$keep_order` is false, returns a simpler associative array:
     *                     `[svs_ID => [svs_Name, svs_Query, svs_UGrpID], ...]`.
     *                     Returns `false` on database error.
     */
    function svsGetByUser($system, $ugrID=null, $keep_order=false){

        $mysqli = $system->getMysqli();

        //user id is not defined - take current user
        if (!$ugrID) {
            $ugrID = $system->getUserId();

            $ugr_groups = $system->getUserGroupIds(null, true);//always get latest

            $current_User = $system->getCurrentUser();
            if($current_User && @$current_User['ugr_Groups'] && count(array_keys($current_User['ugr_Groups']))>0 ){
                $ugrID = implode(',', array_keys($current_User['ugr_Groups'])).','.$ugrID;
            }
            if($system->isAdmin()){ //returns guest searches for admin
                $ugrID = $ugrID.',0';
            }

        }

        if(!$ugrID) {
            $ugrID = '0,4';//get saved searches for guest and websearches
        }

        $ugrID = prepareIds($ugrID,true);

        $query = 'SELECT svs_ID, svs_Name, svs_Query, svs_UGrpID FROM usrSavedSearches WHERE svs_UGrpID in ('.implode(',', $ugrID).')';

        if($keep_order){
            $order = array();
            $query2 = 'SELECT ugr_NavigationTree FROM `sysUGrps` WHERE ugr_ID in ('.implode(',', $ugrID).')';
            $res = $mysqli->query($query2);
            if($res){
                while ($row = $res->fetch_row()) {
                     $treedata = json_decode($row[0],true);
                     if($treedata!=null && is_array($treedata)){
                        svsGetOrderFromTree($treedata, $order);
                     }

                }
            }

            if(!empty($order)){
                $query = $query.' order by FIELD(svs_ID,'.implode(',',$order).')';
            }
        }

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
    /**
     * Recursively traverses a decoded JSON navigation tree to extract the order of saved search IDs.
     *
     * The navigation tree is expected to be an array (from `json_decode`). Nodes that are
     * not folders and have a 'key' (which is assumed to be an `svs_ID`) are added to the `$order` array.
     *
     * @param array $tree The navigation tree structure (PHP array).
     * @param array &$order Passed by reference. An array to which the ordered `svs_ID`s are appended.
     */
    function svsGetOrderFromTree($tree, &$order){

        foreach($tree as $key=>$value){
            if($key=='children'){
                svsGetOrderFromTree($value, $order);
            }
            elseif (is_array($value) && @$value['key']>0 && @$value['folder']!==true)
            {
                array_push($order, intval($value['key']));
            }
        }
    }

    /**
     * Duplicates an existing saved search.
     *
     * The new search will have "(copy)" appended to its name. The current user must be a member
     * of the group that owns the original saved search to be able to copy it.
     * The duplicated search is owned by the same group as the original.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array $record An associative array containing `svs_ID` (the ID of the saved search to duplicate).
     * @return array|false An array containing the data of the newly created saved search
     *                     (svs_ID, svs_Name, svs_Query, svs_UGrpID) on success, or `false` on error.
     */
    function svsCopy($system, $record){

        if (!(@$record['svs_ID']>0)){
            $system->addError(HEURIST_INVALID_REQUEST, 'ID for saved search to be duplicated is not defined');//for new
        }else{

            //refresh groups
            $system->getUserGroupIds(null, true);
            $mysqli = $system->getMysqli();

            $row = mysql__select_row_assoc($mysqli,
                    'select svs_UGrpID, svs_Name, svs_Query FROM usrSavedSearches WHERE svs_ID='.$record['svs_ID']);

            if (!$row) {
                $system->addError(HEURIST_NOT_FOUND,
                    'Cannot duplicate filter criteria. Original filter not found');
            }elseif (!$system->isMember($row['svs_UGrpID'])) { //was has_access
                $system->addError(HEURIST_REQUEST_DENIED,
                    'Cannot duplicate filter criteria. Current user must be member for group');
            }else{
                    //get new name
                    $new_name = $row['svs_Name'].' (copy)';//$mysqli->real_escape_string(

                    $query = 'INSERT INTO `usrSavedSearches` '
                    .'(`svs_Name`,`svs_Added`,`svs_Modified`,`svs_Query`,`svs_UGrpID`,`svs_ExclusiveXSL`)'
                    .' SELECT ?,`svs_Added`,`svs_Modified`,`svs_Query`,`svs_UGrpID`,`svs_ExclusiveXSL` '
                    .' FROM usrSavedSearches WHERE svs_ID = '.$record['svs_ID'];


                    $res= mysql__exec_param_query($mysqli, $query, array('s',$new_name));

                    //$res = $mysqli->query($query);

                    if($res!==true){
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
     * Saves (inserts or updates) a saved search definition.
     *
     * - If `svs_ID` is provided and positive, it updates an existing saved search.
     * - Otherwise, it inserts a new saved search.
     * The current user must be a member of the specified `svs_UGrpID` (group) to save the search.
     * If `svs_UGrpID` is not provided for a new search, it defaults to the current user's ID.
     * This function can also handle a batch update if `$record['svs_ID']` is an array of IDs,
     * applying the same name, query, and group to all of them (which might be unusual usage).
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array $record An associative array containing the saved search data:
     *                      - 'svs_ID': (Optional|int|array) ID(s) of the search to update. If not set or < 1, inserts new.
     *                      - 'svs_UGrpID': (Optional|int) User/Group ID to own the search.
     *                      - 'svs_Name': (string) Name of the saved search. Required for new searches.
     *                      - 'svs_Query': (string) The query string/JSON. Required for new searches.
     *                      - Other `svs_` fields can also be included.
     * @return int|string|false The ID (`svs_ID`) of the created or updated saved search on success.
     *                          If multiple IDs were processed (batch mode), it returns the ID of the last one.
     *                          Returns an error code/message from `mysql__insertupdate` or `false` on validation/permission error.
     */
    function svsSave($system, $record){

        if( !(@$record['svs_ID']>0) && !@$record['svs_Name']){
            $system->addError(HEURIST_INVALID_REQUEST, 'Name not defined');//for new
        }elseif(!(@$record['svs_ID']>0) && !@$record['svs_Query']){
            $system->addError(HEURIST_INVALID_REQUEST, 'Query not defined');//for new
        }else{

            //refresh groups
            $system->getUserGroupIds(null, true);

            if (!$system->isMember(@$record['svs_UGrpID'])) { //was has_access
                $system->addError(HEURIST_REQUEST_DENIED,
                    'Cannot update filter ' .$record['svs_Name']. '.<br>You must be a member of the ' .$record['svs_UGrpID']. ' group to edit this filter.<br><br>'
                    .'Please ask your database owner to add you to the group.');
            }else{

                $is_new = false;
                if(is_array(@$record['svs_ID'])){
                    $rec_IDs = $record['svs_ID'];
                }elseif (@$record['svs_ID']>0){
                    $rec_IDs = array($record['svs_ID']);
                }else{
                    $rec_IDs = array(-1);//new
                    $is_new = true;
                }

                //svs_UGrpID is not defined
                if(array_key_exists('svs_UGrpID', $record) && !($record['svs_UGrpID']>0)) //not defined or all|bookmark
                {
                    if($is_new){
                        $record['svs_UGrpID'] = $system->getUserId();
                    }else{
                        unset($record['svs_UGrpID']);
                    }
                }



                foreach($rec_IDs as $svs_ID){
                    $record['svs_ID'] = $svs_ID;
                    $res = mysql__insertupdate($system->getMysqli(), 'usrSavedSearches', 'svs', $record);
                    if(is_numeric($res)>0){
                        return $res; //returns affected record id
                    }else{
                        $system->addError(HEURIST_DB_ERROR, 'Cannot update saved filter #'.$svs_ID.' in database', $res);
                    }
                }


            }
        }
        return false;
    }

    /**
     * Deletes one or more saved searches.
     *
     * The current user must have administrative access to the group specified by `$ugrID`
     * (or their own user group if `$ugrID` is null) to delete its saved searches.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array|string $rec_ids An array or comma-separated string of saved search IDs (svs_ID) to delete.
     * @param int|null $ugrID (Optional) The User/Group ID context for permission checking.
     *                        If null, defaults to the current user's ID.
     * @return array|false An array `["status"=>HEURIST_OK, "data"=> affected_rows_count]` on success,
     *                     or `false` if an error occurs (permission denied, invalid IDs, DB error).
     */
    function svsDelete($system, $rec_ids, $ugrID=null){

        //verify that current user can delete
        if (!$system->hasAccess($ugrID)) {
            $system->addError(HEURIST_REQUEST_DENIED,
                'Cannot delete filter criteria. Current user must be an administrator for group');
            return false;
        }

            if(!$ugrID>0){
                $ugrID = $system->getUserId();
            }

            $rec_ids = prepareIds($rec_ids);

            if (isEmptyArray($rec_ids)) {
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }

                $query = 'delete from usrSavedSearches where svs_ID in ('. join(', ', $rec_ids) .') and svs_UGrpID='.$ugrID;

                $mysqli = $system->getMysqli();
                $res = $mysqli->query($query);

                if(!$res){
                    $system->addError(HEURIST_DB_ERROR,'Cannot delete saved search', $query.' '.$mysqli->error );
                    return false;
                }

                $cnt = $mysqli->affected_rows;
                if($cnt>0){
                    return array("status"=>HEURIST_OK, "data"=> $cnt);
                }else{
                    $system->addError(HEURIST_NOT_FOUND);
                    return false;
                }
    }

    /**
     * Saves the hierarchical tree structure for organizing saved searches (and other items)
     * for users and groups.
     *
     * The input `$data` is a JSON string representing an object where keys are User/Group IDs
     * (or special keys like "bookmark", "all" for the current user's personal tree) and
     * values are the tree structures themselves (also JSON, typically representing nested lists
     * of items with 'key', 'title', 'folder' attributes).
     * This method updates the `ugr_NavigationTree` field in the `sysUGrps` table for each
     * specified user/group ID that the current user has permission to modify.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param string $data A JSON string representing the tree data for one or more users/groups.
     * @return string|false The modification timestamp (`ugr_Modified`) of the last updated user/group
     *                      record on success, or `false` on error.
     */
    function svsSaveTreeData($system, $data){

        $mysqli = $system->getMysqli();

        $groups = json_decode($data, true);

        $personal_data = array();

        $ugrID = $system->getUserId();
        $ugr_groups = $system->getUserGroupIds(null, true);//always get latest
        $lastID = null;

        foreach($groups as $id=>$treedata){

            if($id=="bookmark" || $id=="all"){
                array_push( $personal_data, '"'.$id.'":'.json_encode($treedata) );
            }elseif(in_array($id, $ugr_groups)){
                //check date of modification
                $res = mysql__insertupdate( $mysqli, 'sysUGrps', 'ugr', array('ugr_ID'=>$id, 'ugr_NavigationTree'=>json_encode($treedata) ));
                if(!is_int($res)){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot update navigation tree (personal) on server sode', $res);
                    return false;
                }

                $lastID = $id;
            }
        }

        if(!empty($personal_data)){

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
    /**
     * Retrieves the navigation tree data for the current user and their groups, or for a specific group.
     *
     * The navigation tree (`ugr_NavigationTree` from `sysUGrps`) stores the hierarchical
     * organization of items like saved searches. This function fetches this JSON string.
     * For the current user, it fetches their personal tree and the trees of all groups they are a member of.
     * If `$grpID` is specified, it only fetches the tree for that group.
     * The individual JSON tree strings are combined into a single JSON object string.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param int|string|null $grpID (Optional) A specific Group ID or a comma-separated list of Group IDs.
     *                               If null, fetches data for the current user and their groups.
     * @return string|false A JSON string representing the combined navigation tree data
     *                      (e.g., `{"userID":{"tree..."}, "groupID":{"tree..."}}`), or `false` on error.
     */
    function svsGetTreeData($system, $grpID=null){

        $mysqli = $system->getMysqli();

        $ugrID = $system->getUserId();

        if($grpID!=null){
            $groups = prepareIds($grpID, true);
        }else{
            //load personal treeviews - rules, my filters (all) and bookmarks
            $groups = $system->getUserGroupIds();
        }

        // 5 - websearch
        if(is_array($groups) && count($groups)==1){
            $where = ' = '.$groups[0];
        }elseif(is_array($groups) && count($groups)>1){
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

?>