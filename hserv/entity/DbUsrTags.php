<?php
/**
* DbUsrTags.php - Class DbUsrTags
*
* Operations for the `usrTags` table.
*
* @project     Heurist academic knowledge management system
* @package Entity 
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
namespace hserv\entity;
use hserv\entity\DbEntityBase;

require_once dirname(__FILE__).'/../records/search/recordFile.php';

/**
* Class DbUsrTags
*
* Provides database access and operations for the `usrTags` table,
* which stores user-created tags that can be applied to records.
*
* @package Entity
*/
class DbUsrTags extends DbEntityBase
{

    /** @var array Tag ID to replace the original tag */
    private $newTagID = [];

    /**
     * Searches for user tags (`usrTags`) based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters. If `tag_UGrpID` is not provided in `$this->data`,
     * it defaults to the current user's group IDs.
     *
     * It then adds specific predicates for this entity:
     * - `tag_ID`: If provided in `$this->data['tag_ID']`.
     * - `tag_Text`: If provided in `$this->data['tag_Text']`.
     * - `tag_Modified`: If provided in `$this->data['tag_Modified']`.
     * - `tag_UGrpID`: If provided or defaulted.
     * - `rtl_RecID`: If provided in `$this->data['rtl_RecID']`, it joins with `usrRecTagLinks`
     *   to find tags associated with specific record(s).
     *
     * The fields returned depend on `$this->data['details']`:
     * - 'id': Returns only `tag_ID`.
     * - 'label': Returns `tag_ID`, `tag_Text`.
     * - 'name': Returns `tag_ID`, `tag_Text`, `tag_UGrpID`.
     * - Default ('full'): Returns `tag_ID`, `tag_Text`, `tag_Description`, `tag_Modified`, `tag_UGrpID`,
     *   and a calculated `tag_Usage` (count of records using the tag from `usrRecTagLinks`).
     * - If `$this->data['details']` is an array or comma-separated string, those specific fields are selected.
     *
     * The order of results is determined by `$this->searchMgr->setOrderBy()`.
     *
     * @return array|false An array containing the search results as structured by `DbEntitySearch::execute()`,
     *                     typically including 'records', 'count', 'total_count', etc.
     *                     Returns `false` if `parent::search()` fails or a database query fails.
     */
    public function search(){

        //fields - from configuration - list of field names
        //data - from request - values

        //if usergroup is not defined search for user groups of current user
        if(!@$this->data['tag_UGrpID']){
            $this->data['tag_UGrpID'] = $this->system->getUserGroupIds();
        }

        if(parent::search()===false){
              return false;
        }

        $sup_tables = null;
        $sup_where = null;

        $this->searchMgr->addPredicate('tag_ID');
        $this->searchMgr->addPredicate('tag_Text');
        $this->searchMgr->addPredicate('tag_Modified');
        $this->searchMgr->addPredicate('tag_UGrpID');

        if(@$this->data['rtl_RecID']){

            $where = predicateId('rtl_RecID',$this->data['rtl_RecID'],SQL_AND);
            if($where!=''){
                $sup_tables = ',usrRecTagLinks';
                $sup_where  = '(rtl_TagID=tag_ID)'.$where;
            }
        }

        switch (@$this->data['details']){
            case 'id': $this->searchMgr->setSelFields('tag_ID'); break;
            case 'label': $this->searchMgr->setSelFields('tag_ID,tag_Text'); break;
            case 'name':  $this->searchMgr->setSelFields('tag_ID,tag_Text,tag_UGrpID'); break;
            default: //case 'full':
                $this->searchMgr->setSelFields('tag_ID,tag_Text,tag_Description,tag_Modified,tag_UGrpID'
                    .',(select count(*) from usrRecTagLinks where (tag_ID=rtl_TagID)) as tag_Usage');
        }

        $orderby = $this->searchMgr->setOrderBy();

        return $this->searchMgr->composeAndExecute($orderby, $sup_tables, $sup_where);
    }


    //
    // validate permission for edit tag
    // for delete and assign see appropriate methods
    //
    /**
     * Validates if the current user has permission to modify/delete the specified tags.
     *
     * Users can only manage tags they own or that belong to groups they are part of,
     * unless they are the database owner.
     * This method overrides the parent `_validatePermission`.
     *
     * @return bool True if the user has permission, false otherwise.
     *              Errors are added to the system object on permission failure.
     */
    protected function _validatePermission(){

        if(!$this->system->isDbOwner() && !isEmptyArray($this->recordIDs)){ //there are tags to update/delete

            $ugrs = $this->system->getUserGroupIds();

            $mysqli = $this->system->getMysqli();

            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField,
                    'tag_ID in ('.implode(',', $this->recordIDs).') AND tag_UGrpID not in ('.implode(',',$ugrs).')');


            $cnt = count($recIDs_norights);

            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED,
                (($cnt==1 && (!is_array($this->records) || count($this->records)==1))
                    ? 'Tag belongs'
                    : $cnt.' tags belong')
                    .' to other user or workgroup you are not a member. Insufficient rights (logout/in to refresh) for this operation');
                return false;
            }
        }

        return true;
    }

    //
    //
    //
    /**
     * Prepares tag records before saving.
     *
     * - For new tags, sets `tag_UGrpID` to the current user's ID if not already set.
     * - Sets `tag_Modified` to the current date/time.
     *
     * @return bool Returns the result of `parent::prepareRecords()`.
     */
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){
            $rec_ID = intval(@$record[$this->primaryField]);
            $isinsert = ($rec_ID<1);
            if($isinsert && !($this->records[$idx]['tag_UGrpID']>0)){
                $this->records[$idx]['tag_UGrpID'] = $this->system->getUserId();
            }
            $this->records[$idx]['tag_Modified'] = date(DATE_8601);//reset
        }

        return $ret;

    }

    /**
    * 1. exclude non numeric
    * 2. find wrong permission
    * 3. find in use
    *
    * @param bool $disable_foreign_checks Passed to `parent::delete()`.
    * @return bool|array Result of `parent::delete()` if successful, false if deletion is blocked.
    */
    public function delete($disable_foreign_checks = false){

        $this->recordIDs = prepareIds($this->data[$this->primaryField]);

        if(!empty($this->recordIDs)){

            $mysqli = $this->system->getMysqli();

            $recIDs_inuse = mysql__select_list2($mysqli, 'SELECT DISTINCT rtl_RecID '
                        .'FROM usrRecTagLinks WHERE rtl_TagID='.intval($this->recordIDs[0]));
            $cnt = count($recIDs_inuse);

            if($cnt>0){
                $this->system->addError(HEURIST_ACTION_BLOCKED,
                (($cnt==1)
                ? 'There is a record'
                : 'There are '.$cnt.' records')
                .' with this tag.<br>You must delete the record(s)'
                .' or remove the tag in order to be able to delete the tag.<br><br>'
                .'<a href="#" onclick="window.open(\''
                . HEURIST_BASE_URL.'?db='.$this->system->dbname().'&q=ids:'.implode(',', $recIDs_inuse)
                .'\',\'_blank\')">Open records in search</a> to allow deletion or removal of this tag');
                return false;
            }
        }

        return parent::delete();
    }

    //
    //  Replace one or several tags ($this->recordIDs) to new ONE ($this->newTagID)
    //
    /**
     * Replaces occurrences of one or more old tags with a single new tag in `usrRecTagLinks`.
     *
     * Optionally removes the old tags after replacement if `$this->data['removeOld']` is true.
     *
     * @return int|false The usage count of the new tag after replacement on success, or false on failure.
     *                   Errors are added to the system object on failure.
     */
    private function replaceTags(){


        if(isEmptyArray($this->recordIDs) || isEmptyArray($this->newTagID)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of tag identificators');
            return false;
        }

        $ret = false;

        $newTagID = $this->newTagID[0];

        $update_query = 'UPDATE IGNORE usrRecTagLinks set rtl_TagID = '.$newTagID.' WHERE rtl_TagID in ('
                . implode(',', $this->recordIDs) . ')';

        $mysqli = $this->system->getMysqli();

        $res = $mysqli->query($update_query);
        if(!$res){
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot replace tags', $mysqli->error );
        }else{
            $ret = true;
            if(@$this->data['removeOld']==1){
                $ret = parent::delete();
            }
            if($ret){
                //calculate new usage
                $query = 'SELECT COUNT(*) FROM usrRecTagLinks WHERE rtl_TagID = '.$newTagID;
                $ret = mysql__select_value($mysqli, $query);
                if($ret==null){
                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot find tag usage', $mysqli->error );
                    $ret = false;
                }
            }
        }

        return $ret;
    }

    //
    // batch actions for tags
    //  - see table usrRecTagLinks
    //
    // parameter mode
    // A) replace all for set of records (recIDs) - remove all old tags and replace with new set (tagIDs)
    // B) assign tags (tagIDs)  to records (recIDs)
    // C) remove tags (tagIDs)  to records (recIDs)
    //
    // D) replace several old tags (tagIDs) to new ONE (newTagID) see $this->replaceTags()
    //
    /**
     * Performs batch actions on tags and their assignments to records (`usrRecTagLinks`).
     *
     * Supported actions:
     * 1. **Replace Tags**: If `newTagID` is provided in `$this->data`, calls `replaceTags()` to replace
     *    tags specified in `tagIDs` with `newTagID`.
     * 2. **Manage Record-Tag Links**: Otherwise, manages links between records (`recIDs`) and tags (`tagIDs`).
     *    - `mode = 'replace'`: Removes all existing tags for the specified records, then assigns the new set of tags.
     *    - `mode = 'remove'`: Removes the specified tags from the specified records.
     *    - `mode = 'assign'` (default): Assigns the specified tags to the specified records.
     *    Also handles creation of bookmarks if private tags are assigned.
     *
     * Requires permission validation for the tags being manipulated.
     *
     * @return array|bool|int Result of the batch operation:
     *                        - For replace: Usage count of the new tag or false.
     *                        - For link management: An array with counts of processed records, added/removed tags,
     *                          and new bookmarks, or false on failure.
     *                        Returns false if initial validation (e.g., missing IDs) fails.
     */
    public function batch_action(){

        //tags ids
        $this->recordIDs = prepareIds($this->data['tagIDs']);
        if(empty($this->recordIDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of tag identificators');
            return false;
        }

        // MODE D  replace several old tags (tagIDs) to new ONE
        $this->newTagID = prepareIds(@$this->data['newTagID']);
        if(!empty($this->newTagID)){
            return $this->replaceTags();
        }

        if(!$this->_validatePermission()){ //check that all tags belongs to current user
            return false;
        }

        //record ids
        $assignIDs = prepareIds($this->data['recIDs']);
        if(empty($assignIDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of record identificators');
            return false;
        }

        $res_tag_added = 0; //tags assigned
        $res_tag_removed = 0; //tags removed
        $res_bookmarks = 0; //new bookmarks

        $mysqli = $this->system->getMysqli();

        //narrow by record type
        $rec_RecTypeID = @$this->data['rec_RecTypeID'];
        if($rec_RecTypeID>0){
            $assignIDs = mysql__select_list2($mysqli, 'SELECT rec_ID from Records where rec_ID in ('
                .implode(',', $assignIDs).') and rec_RecTypeID='. $rec_RecTypeID, 'intval');
            $assignIDs = prepareIds($assignIDs);
            if($assignIDs==null || empty($assignIDs)){
                $this->system->addError(HEURIST_NOT_FOUND, 'No record found for provided record type');
                return false;
            }
        }


        $keep_autocommit = mysql__begin_transaction($mysqli);

        $mode = @$this->data['mode'];

        if($mode=='replace'){

            // detach/remove all assignments for given records
            $query = 'DELETE usrRecTagLinks FROM usrRecTagLinks'
                . ' WHERE rtl_RecID in (' . implode(',', $assignIDs) . ')';
            $res = $mysqli->query($query);
            if(!$res){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}

                $this->system->addError(HEURIST_DB_ERROR,"Cannot detach tags from records", $mysqli->error );
                return false;
            }
            $res_tag_removed = $mysqli->affected_rows;

        }elseif($mode=='remove'){

            // detach/remove all assignments for given records
            $query = 'DELETE usrRecTagLinks FROM usrRecTagLinks'
                . ' WHERE rtl_TagID in (' . implode(',', $this->recordIDs)
                . ') and rtl_RecID in (' . implode(',', $assignIDs) . ')';
            $res = $mysqli->query($query);
            if(!$res){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}

                $this->system->addError(HEURIST_DB_ERROR,"Cannot detach tags from records", $mysqli->error );
                return false;
            }
            $res_tag_removed = $mysqli->affected_rows;


        }else { //assign by default
            $mode = 'assign';
        }

        //create new assignments
        if($mode!='remove'){

            $insert_query = 'INSERT IGNORE INTO usrRecTagLinks (rtl_RecID, rtl_TagID) '
                . 'SELECT rec_ID, tag_ID FROM usrTags, Records '
                . ' WHERE rec_ID in (' . implode(',', $assignIDs) . ') '
                . ' AND tag_ID in (' . implode(',', $this->recordIDs) . ')';

            $res = $mysqli->query($insert_query);
            if(!$res){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}

                $this->system->addError(HEURIST_DB_ERROR,"Cannot assign tags", $mysqli->error );
                return false;
            }
            $res_tag_added = $mysqli->affected_rows;

            //if at least one tag is private
            //add bookmarks if tags are private and record is not bookmarked yet
            $ugrID = $this->system->getUserId();

            if(null != mysql__select_value($mysqli, 'SELECT tag_ID from usrTags where tag_ID in ('
                . implode(',', $this->recordIDs) . ') AND tag_UGrpID ='.$ugrID.' LIMIT 1')){

                $insert_query = 'INSERT INTO usrBookmarks '
                    .' (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)'
                    .' SELECT ' . $ugrID . ', now(), now(), rec_ID FROM Records '
                    .' LEFT JOIN usrBookmarks ON bkm_recID=rec_ID AND bkm_UGrpID='.$ugrID
                    .' WHERE bkm_ID IS NULL AND rec_ID IN (' . implode(',', $assignIDs) . ')';

                $res = $mysqli->query($insert_query);
                if(!$res){
                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}

                    $this->system->addError(HEURIST_DB_ERROR,"Cannot create bookmarks", $mysqli->error );
                    return false;
                }
                $res_bookmarks = $mysqli->affected_rows;
            }
        }

        //commit
        $mysqli->commit();
        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        return array('processed'=>count($assignIDs), //afffected records
                'added'=>$res_tag_added, //tags assigned
                'removed'=>$res_tag_removed, //tags removed
                'bookmarks'=>$res_bookmarks);//new bookmarks

    }

}
?>
