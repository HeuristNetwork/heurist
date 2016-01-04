<?php

    /**
    * CRUD for tags and bookmarks (usrTags, usrRecTagLinks, usrBookmarks)
    *
    * tag and bookmark - prefixes for functions
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
    * Get tag IDs by tag names
    *
    * @param mixed $tag_names
    * @param mixed $ugrID
    */
    function tagGetByName($system, $tag_names, $ugrID=null){

        if (!$ugrID) {
            $ugrID = $system->get_user_id();
        }
        if(!$ugrID) return null;

        if(is_string($tag_names)){
            $tag_names = explode(",", $tag_names);
        }

        $tag_ids = array();
        foreach ($tag_names as $tag_name) {
            $tag_name = preg_replace('/\\s+/', ' ', trim($tag_name));
            if(strlen($tag_name)>0){

                $res = mysql__select_value($system->get_mysqli(), 'select tag_ID from usrTags where lower(tag_Text)=lower("'.
                    mysqli_real_escape_string($tag_name).'") and tag_UGrpID='.$ugrID);
                if($res){
                    array_push($tag_ids, $res);
                }
            }
        }
        $tag_ids = array_unique($tag_ids, SORT_NUMERIC);

        return $tag_ids;
    }

    /**
    * not used
    * Get tags for given usergroup ID
    *
    * @param mixed $system
    * @param mixed $isfull
    * @param mixed $ugrID
    */
    function tagGetByUser($system, $isfull, $ugrID=null){

        if (!$ugrID) {
            $ugrID = $system->get_user_id();
        }
        if(!$ugrID) return null;

        $mysqli = $system->get_mysqli();

        if($isfull){
            $query = "SELECT tag_ID, tag_Text, tag_Description, count(*) as tag_Usage"
            ." FROM usrTags, usrRecTagLinks WHERE rtl_TagID = tag_ID and tag_UGrpID=".$ugrID
            ." group by tag_ID, tag_Text, tag_Description";
        }else{
            $query = "SELECT tag_ID, tag_Text, tag_Description FROM usrTags WHERE tag_UGrpID=".$ugrID;
        }

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
            $system->addError(HEURIST_DB_ERROR, 'Cannot get tags', $mysqli->error);
            return false;
        }
    }

    /**
    * Returns tags for given array of records for specified users
    *
    * @param mixed $system
    * @param mixed $isfull if true returns name and description
    * @param mixed $recIDs - array of record ids
    * @param mixed $ugrIDs - cs list of user/groups or 'all' - all groups of current user
    */
    function tagGetByRecords($system, $isfull, $recIDs, $ugrIDs=null){

        if ($ugrIDs=='all'){
            $ugrIDs = $system->get_user_group_ids();
        }else if (!$ugrIDs) {
            $ugrIDs = $system->get_user_id();
            if($ugrIDs){
                $ugrIDs = array($ugrIDs);
            }
        }else if(is_string($ugrIDs) && $ugrIDs!=""){
            $ugrIDs = explode(",", $ugrIDs);
        }

        if(!$ugrIDs || count($ugrIDs)<1) {
            $system->addError(HEURIST_REQUEST_DENIED, 'No user or group defined');
            return false;
        }

        $mysqli = $system->get_mysqli();

        $supinfo = $isfull?", tag_Text, tag_Description, tag_Modified ":"";

        if(is_string($recIDs) && $recIDs!=""){
            $recIDs = explode(",", $recIDs);
        }

        if($recIDs && count($recIDs)>0){
            $recs = "and rtl_RecID in (".implode(",", $recIDs).") ";
        }else{
            $recs = "";
        }

        $query = "SELECT tag_UGrpID, tag_ID ".$supinfo.", count(rtl_RecID) as tag_Usage "
        ." FROM usrTags left join usrRecTagLinks on rtl_TagID = tag_ID ".$recs
        ." WHERE tag_UGrpID in (".implode(",", $ugrIDs)
        .") group by tag_UGrpID, tag_ID".$supinfo
        ." order by tag_UGrpID";


        $res = $mysqli->query($query);

        if ($res){
            $result = array();
            $cur_usr_id = null;
            while ($row = $res->fetch_row()){
                $usr_id = array_shift($row);
                if($cur_usr_id!=$usr_id){
                    $cur_usr_id = $usr_id;
                    $result[$usr_id] = array();
                }
                $id = array_shift($row);
                $result[$usr_id][$id] = $row;
            }
            $res->close();
            return $result;
        }else{
            $system->addError(HEURIST_DB_ERROR, 'Cannot get tags', $mysqli->error);
            return false;
        }
    }

    /**
    * insert/update tag
    *
    * @param mixed $system
    * @param mixed $tag  - array [ ID, UGrpID, Text, Description, AddedByImport ]
    */
    function tagSave($system, $tag){

        if(!@$tag['tag_Text']){
            $system->addError(HEURIST_INVALID_REQUEST, "Text not defined");
            return false;
        }

        $tag['tag_UGrpID'] = $system->is_admin2(@$tag['tag_UGrpID']);
        if (!$tag['tag_UGrpID']) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if(intval(@$tag['tag_ID'])<1){
                $samename = tagGetByName($system, $tag['tag_Text'], $tag['tag_UGrpID']);

                if(count($samename)>0){
                    $tag['tag_ID'] = $samename[0];
                }
            }

            $res = mysql__insertupdate($system->get_mysqli(), "usrTags", "tag", $tag);
            if(is_numeric($res) && $res>0){
                return $res; //returns affected record id
            }else{
                $system->addError(HEURIST_DB_ERROR, 'Cannot update record in database', $res);
                return false;
            }

        }
    }

    /**
    * Delete tags
    *
    * @param mixed $system
    * @param mixed $tag_ids - list of tags to be deleted
    */
    function tagDelete($system, $tag_ids, $ugrID=null){

        //verify that current user can delete
        $ugrID = $system->is_admin2($ugrID);
        if (!$ugrID) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if(is_string($tag_ids)){
                $tag_ids = explode(",", $tag_ids);
            }

            //$kwd_ids = array_map('intval', array_keys($_REQUEST['delete_kwds']));
            if (is_array($tag_ids) && count($tag_ids)>0) {
                $mysqli = $system->get_mysqli();
                $res = $mysqli->query('delete usrTags, usrRecTagLinks from usrTags left join usrRecTagLinks on rtl_TagID = tag_ID where tag_ID in ('. implode(', ', $tag_ids) .') and tag_UGrpID='.$ugrID);

                if($res){
                    $cnt = $mysqli->affected_rows;
                    return $cnt;
                }else{
                    $system->addError(HEURIST_DB_ERROR,"Cannot delete tag", $mysqli->error );
                    return false;
                }

            }else{
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }

        }
    }

    /**
    * Replace old tags with new one and delete old ones
    *
    * @param mixed $system
    * @param mixed $tag_ids - to be replaced and deleted
    * @param mixed $tag_id_new
    * @param mixed $ugrID
    */
    function tagReplace($system, $tag_ids, $tag_id_new, $ugrID=null)
    {


        $ugrID = $system->is_admin2($ugrID);
        if (!$ugrID) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if(is_string($tag_ids)){
                $tag_ids = explode(",", $tag_ids);
            }
            if (is_array($tag_ids) && count($tag_ids)>0 && intval($tag_id_new)) {

                $mysqli = $system->get_mysqli();

                $query = 'select count(*) from usrTags where tag_ID in ('.$tag_id_new.','.implode(', ', $tag_ids).') and tag_UGrpID='.$ugrID;
                $cnt = mysql__select_value($mysqli, $query);


                if($cnt!=(count($tag_ids)+1)){
                    $system->addError(HEURIST_INVALID_REQUEST, "Not found all tags");
                    return false;
                }

                //add new links
                $query = 'insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
                . 'select distinct rtl_RecId, '.$tag_id_new.' from usrRecTagLinks where rtl_TagID in ('. implode(', ', $tag_ids) .')';

                $res = $mysqli->query($query);


                $res = $mysqli->query($query);
                if($res){
                    $cnt = $mysqli->affected_rows;
                    //delete old ones
                    $res = tagDelete($system, $tag_ids, $ugrID);
                    if(is_numeric($res) && $res>0){
                        return array('tags_deleted'=>$res, 'taglinks_replased'=>$cnt);
                    }else{
                        return false;
                    }
                }else{
                    $system->addError(HEURIST_DB_ERROR,"Cannot replace tag links", $mysqli->error );
                    return false;
                }

            }else{
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }

        }
    }

    //
    function tagReplace_OLD_TODELETE($system, $tag_id_old, $tag_id_new, $ugrID=null){
        $ugrID = $system->is_admin2($ugrID);
        if (!$ugrID) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{
            if(intval($tag_id_old) && intval($tag_id_new) && $tag_id_new!=$tag_id_old)
            {
                $mysqli = $system->get_mysqli();
                $cnt = mysql__select_value($mysqli, 'select count(*) from usrTags where tag_ID in ('.$tag_id_new.','.$tag_id_old.') and tag_UGrpID='.$ugrID);
                if($cnt==2){
                    $res = $mysqli->query('update usrRecTagLinks set rtl_TagID = '.$tag_id_new.' where rtl_TagID = '.$tag_id_old);
                    if($res){
                        $cnt = $mysqli->affected_rows;
                        $res->close();
                        return $cnt; //array("status"=>HEURIST_OK, "data"=> $cnt);
                    }else{
                        $system->addError(HEURIST_DB_ERROR,"Cannot replace tag", $mysqli->error );
                        return false;
                    }
                }else{
                    $system->addError(HEURIST_REQUEST_DENIED);
                    return false;
                }

            }else{
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }
        }
    }

    /**
    * Assign tags to records and bookmark records
    *
    * @param mixed $system
    * @param mixed $record_ids - array of record ids
    * @param mixed $tag_ids - array of tag ids
    * @param mixed $tag_names - if tag ids are not defined, we use $tag_names to get ids
    * @param mixed $ugrID
    */
    function tagsAssign($system, $record_ids, $tag_ids, $tag_names=null, $ugrID=null){

        $ugrID = $system->is_admin2($ugrID);
        if (!$ugrID) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{
            //find tag_ids by tag name
            if($tag_ids==null){
                if($tag_names=null){
                    $system->addError(HEURIST_INVALID_REQUEST);
                    return false;
                }else{
                    $tag_ids = tagGetByName($system, array_filter(explode(',', $tag_names)), $ugrID);
                }
            }
            if(!is_array($record_ids) || count($record_ids)<0 || !is_array($tag_ids) || count($tag_ids)<0){
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }

            $mysqli = $system->get_mysqli();

            //assign links
            $res = $mysqli->query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
                . 'select rec_ID, tag_ID from usrTags, Records '
                . ' where rec_ID in (' . implode(',', $record_ids) . ') '
                . ' and tag_ID in (' . implode(',', $tag_ids) . ')'
                . ' and tag_UGrpID = '.$ugrID);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR,"Cannot assign tags", $mysqli->error );
                return false;
            }
            $tag_count = $mysqli->affected_rows;


            /*$new_rec_ids = mysql__select_column($mysqli,
            'select rec_ID from Records '
            .' left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.$ugrID
            .' where bkm_ID is null and rec_ID in (' . join(',', $record_ids) . ')');*/

            //if $ugrID is not a group - create bookmarks
            $bookmarks_added = 0;
            if ($ugrID==$system->get_user_id() ||
                mysql__select_value($mysqli, 'select ugr_Type from sysUGrps where ugr_ID ='.$ugrID)=='user')
            { //not bookmarked yet
                $query = 'insert into usrBookmarks '
                .' (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)'
                .' select ' . $ugrID . ', now(), now(), rec_ID from Records '
                .' left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.$ugrID
                .' where bkm_ID is null and rec_ID in (' . implode(',', $record_ids) . ')';

                //$stmt = $mysqli->query($query);

                $res = $mysqli->prepare($query);

                if(!$res){
                    $system->addError(HEURIST_DB_ERROR,"Cannot add bookmarks", $mysqli->error);
                    return false;
                }
                $bookmarks_added = $mysqli->affected_rows;
            }

            return array('tags_added'=>$tag_count, 'bookmarks_added'=>$bookmarks_added);
        }
    }

    /**
    * Remove record-tag links
    *
    * @param mixed $system
    * @param mixed $record_ids - array of record ids
    * @param mixed $tag_ids - array of tag ids
    * @param mixed $tag_names - if tag ids are not defined, we use $tag_names to get ids
    * @param mixed $ugrID
    */
    function tagsRemove($system, $record_ids, $tag_ids=null, $tag_names=null, $ugrID=null){

        $ugrID = $system->is_admin2($ugrID);
        if (!$ugrID) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{
            //find tag_ids by tag name
            if($tag_ids==null && $tag_names!=null){
                $tag_ids = tagGetByName($system, array_filter(explode(',', $tag_names)), $ugrID);
            }
            if(!is_array($record_ids) || count($record_ids)<0){
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }

            $query = 'delete usrRecTagLinks from usrRecTagLinks'
            . ' left join usrTags on tag_ID = rtl_TagID'
            . ' where rtl_RecID in (' . implode(',', $record_ids) . ')'
            . ' and tag_UGrpID = ' . $ugrID;
            if($tag_ids){
                $query = $query. ' and tag_ID in (' . implode(',', $tag_ids) . ')';
            }

            $mysqli = $system->get_mysqli();

            $res = $mysqli->query($query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR,"Cannot remove tags", $mysqli->error );
                return false;
            }
            $tag_count = $mysqli->affected_rows;

            return array('tags_removed'=>$tag_count);
        }
    }

    /**
    * Remove bookmark for records
    *
    * @param mixed $system
    * @param mixed $record_ids - array of record ids
    * @param mixed $ugrID
    */
    function bookmarkRemove($system, $record_ids, $ugrID=null){

        $ugrID = $system->is_admin2($ugrID);
        if (!$ugrID) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if(!is_array($record_ids) || count($record_ids)<0){
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }

            $mysqli = $system->get_mysqli();

            $res = $mysqli->query('delete usrRecTagLinks from usrBookmarks left join usrRecTagLinks on rtl_RecID=bkm_RecID where bkm_RecID in ('.implode(',', $record_ids).') and bkm_UGrpID=' . $ugrID);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR,"Cannot remove tags", $mysqli->error );
                return false;
            }
            $res = $mysqli->query('delete from usrBookmarks where bkm_RecID in ('.implode(',', $record_ids).') and bkm_UGrpID=' . $ugrID);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR,"Cannot remove bookmarks", $mysqli->error );
                return false;
            }
            $bookmarks_removed = $mysqli->affected_rows;
            return $bookmarks_removed;

        }
    }

    /**
    * Set rating for bookmarked records
    *
    * @param mixed $system
    * @param mixed $record_ids - array of record ids
    * @param mixed $rating
    * @param mixed $ugrID
    */
    function bookmarkRating($system, $record_ids, $rating, $ugrID=null)
    {

        $ugrID = $system->is_admin2($ugrID); //get current user
        if (!$ugrID) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if( !is_array($record_ids) || count($record_ids)<0 || !is_numeric($rating) ){
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }
            $rating = intval($rating);

            $mysqli = $system->get_mysqli();
            $res = $mysqli->query('update usrBookmarks set bkm_Rating='
                .$rating
                .' where bkm_RecID in ('.implode(',', $record_ids).') and bkm_UGrpID=' . $ugrID);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR,"Cannot set rating", $mysqli->error );
                return false;
            }
            $bookmarks_updated = $mysqli->affected_rows;
            return $bookmarks_updated;

        }
    }
?>
