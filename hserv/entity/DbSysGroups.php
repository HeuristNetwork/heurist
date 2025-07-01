<?php
/**
* DbSysGroups.php - Class DbSysGroups
*
* Operations for workgroups stored in the `sysUGrps` table.
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

/**
* Class DbSysGroups
*
* Provides database access and operations for workgroups stored in the `sysUGrps` table.
* It handles searching, creating, updating, and deleting workgroups, as well as
* managing user memberships and roles within these groups.
*
*/
require_once dirname(__FILE__).'/../records/edit/recordModify.php';//for recordDelete
require_once dirname(__FILE__).'/../records/search/recordFile.php';


class DbSysGroups extends DbEntityBase
{

    /**
     * Constructor for DbSysGroups.
     *
     * Calls the parent constructor and sets `requireAdminRights` to false,
     * as group management might be delegated. Specific actions are still
     * permission-checked.
     *
     * @param \hserv\System $system The main Heurist system object.
     * @param array|null $data Optional data to initialize the entity with.
     */
    public function __construct( $system, $data=null ) {
       parent::__construct( $system, $data );
       $this->requireAdminRights = false;
    }

    /**
     * Searches for workgroups (records in `sysUGrps` where `ugr_Type="workgroup"`)
     * based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters from `$this->data`.
     *
     * A mandatory filter `ugr_Type="workgroup"` is always applied.
     * It then adds specific predicates for:
     * - `ugr_ID`: If provided in `$this->data['ugr_ID']`.
     * - `ugr_Name`: If provided in `$this->data['ugr_Name']`.
     * - User membership: If `ugl_UserID` is provided in `$this->data`, it joins with `sysUsrGrpLinks`
     *   to find groups the user is part of. `$this->data['ugl_Role']` can further filter by role.
     *   The `$this->data['ugl_Join']` parameter can affect how this join is constructed (LEFT JOIN vs. implicit JOIN in WHERE).
     *
     * The fields returned depend on `$this->data['details']`:
     * - 'id': Returns only `ugr_ID`.
     * - 'name': Returns `ugr_ID`, `ugr_Name`.
     * - 'count': Returns `ugr_ID` and a calculated `ugr_Members` count (number of users in the group).
     * - 'list' or 'full': Returns core group fields (`ugr_ID`, `ugr_Name`, `ugr_LongName`, `ugr_Description`, `ugr_Enabled`).
     *   If user membership was part of the criteria (`ugl_UserID` provided), `ugl_Role` is also included.
     *   Both 'list' and 'full' modes also include the calculated `ugr_Members` count.
     * - If `$this->data['details']` is an array or comma-separated string, those specific fields are selected (plus `ugr_Members` if sorting by it).
     *
     * The order of results is determined by `$this->searchMgr->setOrderBy()`.
     *
     * @return array|false An array containing the search results as structured by `DbEntitySearch::execute()`,
     *                     typically including 'records', 'count', 'total_count', etc.
     *                     Returns `false` if `parent::search()` fails (e.g., parameter validation error)
     *                     or if the database query fails.
     */
    public function search(){


        if(parent::search()===false){
              return false;
        }

        $needCheck = false;
        $needRole = false;
        $needCount = false;  //find members count
        $is_ids_only = false;

        //compose WHERE
        $where = array('ugr_Type="workgroup"');
        $from_table = array($this->config['tableName']);

        $pred = $this->searchMgr->getPredicate('ugr_ID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ugr_Name');
        if($pred!=null) {array_push($where, $pred);}

        //find groups where this user is member or admin
        $pred = $this->searchMgr->getPredicate('ugl_UserID');
        if($pred!=null) {

                $needRole = true;
                $where2 = array();
                array_push($where2, $pred);
                $pred = $this->searchMgr->getPredicate('ugl_Role');
                if($pred!=null) {
                    array_push($where2, $pred);
                }
                array_push($where2, '(ugl_GroupID = ugr_ID)');

                if(@$this->data['ugl_Join']){ //always search for role

                    $from_table[0] = $from_table[0].' LEFT JOIN sysUsrGrpLinks ON '.implode(SQL_AND,$where2);

                }else{
                    $where = array_merge($where,$where2);
                    array_push($from_table, 'sysUsrGrpLinks');
                }
        }

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'ugr_ID';
            $is_ids_only = true;

        }elseif(@$this->data['details']=='name'){

            $this->data['details'] = 'ugr_ID,ugr_Name';

        }elseif(@$this->data['details']=='count'){

            $this->data['details'] = 'ugr_ID';
            $needCount = true;

        }elseif(@$this->data['details']=='list' || @$this->data['details']=='full'){

            $this->data['details'] = 'ugr_ID,ugr_Name,ugr_LongName,ugr_Description,ugr_Enabled';
            if($needRole) {
                $this->data['details'] .= ',ugl_Role';
            }
            $needCount = true;

        }else{
            $needCheck = true;
        }

        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }

        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        //----- order by ------------
        $orderby = $this->searchMgr->setOrderBy();

        //$is_ids_only = (count($this->data['details'])==1);

        if($needCount || strpos($orderby, 'ugr_Members')!==false){
            array_push($this->data['details'],
                '(select count(*) from sysUsrGrpLinks where (ugl_GroupID=ugr_ID)) as ugr_Members');
        }

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

         if(!empty($where)){
            $query = $query.SQL_WHERE.implode(SQL_AND,$where);
         }
         if($orderby!=null){
            $query = $query.' ORDER BY '.$orderby;
         }

         $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $calculatedFields = null;

        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName'], $calculatedFields);

        return $result;
    }


    //
    // validate permission for edit tag
    // for delete and assign see appropriate methods
    //
    /**
     * Validates if the current user has permission to modify/delete the specified workgroups.
     *
     * Users can only manage groups they are an admin of, unless they are the database owner.
     * This method overrides the parent `_validatePermission`.
     *
     * @return bool True if the user has permission, false otherwise.
     *              Errors are added to the system object on permission failure.
     */
    protected function _validatePermission(){

        if(!$this->system->isDbOwner() && !isEmptyArray($this->recordIDs)){ //there are records to update/delete

            $ugrID = $this->system->getUserId();

            $mysqli = $this->system->getMysqli();

            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'].',sysUsrGrpLinks',
                $this->primaryField,
                    '( usr_ID in ('.implode(',', $this->recordIDs)
                    .') ) AND ( ugl_GroupID=ugr_ID ) AND ( ugl_Role=\'admin\' ) AND ugl_UserID!='.$ugrID);


            $cnt = is_array($recIDs_norights)?count($recIDs_norights):0;

            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED,
                    'You are not an admin of group. Insufficient rights (logout/in to refresh) for this operation');
                return false;
            }
        }

        return true;
    }

    //
    //
    //
    /**
     * Prepares workgroup records before saving.
     *
     * - Sets `ugr_Type` to 'workgroup'.
     * - Sets `ugr_Modified` to the current date/time.
     * - Sets default `ugr_Password` and `ugr_eMail` placeholders (as these are not directly used for workgroups).
     * - Validates `ugr_Name` for duplication.
     *
     * @return bool True if preparation is successful and validation passes, false otherwise.
     */
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){
            $this->records[$idx]['ugr_Type'] = 'workgroup';
            $this->records[$idx]['ugr_Modified'] = date(DATE_8601);//reset
            $this->records[$idx]['ugr_Password'] = 'PASSWORD NOT REQUIRED';
            $this->records[$idx]['ugr_eMail'] = 'EMAIL NOT SET FOR '.$this->records[$idx]['ugr_Name'];

            //validate duplication
            if(!$this->doDuplicationCheck($idx, 'ugr_Name', 'Workgroup cannot be saved. The provided name already exists')){
                    return false;
            }

        }

        return $ret;

    }

    //
    // add current user as admin for new group
    //
    /**
     * Saves workgroup records.
     *
     * After calling `parent::save()`:
     * - Handles renaming any associated temporary image file (for `ugr_Thumb`).
     * - For new groups, adds the current user as an 'admin' in `sysUsrGrpLinks`.
     *
     * @return array|false The result from `parent::save()` (array of saved IDs or false).
     */
    public function save(){

        $savedRecIds = parent::save();

        if($savedRecIds!==false){

            //treat group image
            foreach($this->records as $record){
                $group_ID = @$record['ugr_ID'];
                if($group_ID && in_array($group_ID, $savedRecIds)){
                    $thumb_file_name = @$record['ugr_Thumb'];

                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $group_ID);
                    }

                    if(!in_array($group_ID, $this->recordIDs )){ //add current user as admin for new group

                        $admin_role = array();
                        $admin_role['ugl_GroupID'] = $group_ID;
                        $admin_role['ugl_UserID'] = $this->system->getUserId();
                        $admin_role['ugl_Role'] = 'admin';
                        $res = mysql__insertupdate($this->system->getMysqli(), 'sysUsrGrpLinks', 'ugl', $admin_role);

                        //$fname = HEURIST_FILESTORE_DIR.$this->system->getUserId();
                        //fileSave('X',$fname); on save
                    }
                }
            }
        }

        return $savedRecIds;

    }

    //
    // delete group
    //
    /**
     * Deletes workgroup(s).
     *
     * Prevents deletion of the "Database Owners" group (ID 1) or groups that own non-temporary records.
     * Before deleting the group from `sysUGrps`:
     * - Deletes associated temporary records owned by the group.
     * - Deletes links from `sysUsrGrpLinks`.
     * - Deletes associated entries from `usrSavedSearches`, `usrTags`, and `usrRecPermissions`.
     * - Deletes any associated group image file.
     *
     * @param bool $disable_foreign_checks Passed to `parent::delete()`.
     * @return bool True on successful deletion of the group and associated data, false otherwise.
     */
    public function delete($disable_foreign_checks = false){

        $this->recordIDs = null; //reset to obtain ids from $data

        $this->foreignChecks = array(
                    array('SELECT FIND_IN_SET(1, "#IDS#")','Cannot remove "Database Owners" group'),
                    array('SELECT count(rec_ID) FROM Records WHERE rec_FlagTemporary=0 AND rec_OwnerUGrpID IN (#IDS#) LIMIT 1',
                          'Deleting Group with existing Records not allowed')
                );

        if(!$this->deletePrepare()){
            return false;
        }

        $mysqli = $this->system->getMysqli();

        $keep_autocommit = mysql__begin_transaction($mysqli);

        //remove temporary records
        $query = 'SELECT rec_ID FROM Records WHERE rec_OwnerUGrpID in ('
                        . implode(',', $this->recordIDs) . ') and rec_FlagTemporary=1';
        $rec_ids_to_delete = mysql__select_list2($mysqli, $query);
        if(!isEmptyArray($rec_ids_to_delete)){
            $res = recordDelete($this->system, $rec_ids_to_delete, false);
            if(@$res['status']!=HEURIST_OK) {return false;}
        }

        $ret = true;

        //find affected users
        $query = 'SELECT ugl_UserID FROM sysUsrGrpLinks'
            . SQL_WHERE . predicateId('ugl_GroupID',$this->recordIDs);

        $affectedUserIds = mysql__select_list2($mysqli, $query);

        //remove from roles table
        $query = 'DELETE FROM sysUsrGrpLinks'
            . SQL_WHERE . predicateId('ugl_GroupID',$this->recordIDs);

        $res = $mysqli->query($query);
        if(!$res){
            $this->system->addError(HEURIST_DB_ERROR,
                            'Cannot remove entries from user/group links (sysUsrGrpLinks)',
                            $mysqli->error );
            $ret = false;
        }
        $query = 'DELETE FROM usrSavedSearches  WHERE svs_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $mysqli->query($query);
        $query = 'DELETE FROM usrTags  WHERE tag_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $mysqli->query($query);
        $query = 'DELETE FROM usrRecPermissions  WHERE rcp_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $mysqli->query($query);

        if($ret){
            $ret = parent::delete();

            if(!isEmptyArray(@$affectedUserIds)){
                foreach($affectedUserIds as $usrID)  //affected users
                {
                    if($usrID!=$this->system->getUserId()){
                            $usrID = intval($usrID);
                            $fname = $this->getEntityImagePath($usrID);
                            if(file_exists($fname)){
                                unlink($fname);
                            }
                    }
                }
            }
        }

        mysql__end_transaction($mysqli, $ret, $keep_autocommit);

        return $ret;
    }

    //
    // batch action for groups - add/remove users to/from group
    // parameters
    // groupID  - affected group
    // userIDs  - user roles to be changed
    // role - remove admin member
    //
    /**
     * Performs batch actions on workgroup memberships.
     *
     * Allows adding users to a group, removing users from a group, or changing a user's role
     * within a group ('admin' or 'member').
     * Prevents removing the last admin from a group.
     *
     * Expects `$this->data` to contain:
     * - `role`: The action to perform ('remove', 'admin', 'member').
     * - `groupID`: ID(s) of the group(s) to affect.
     * - `userIDs`: ID(s) of the user(s) whose membership/role to change.
     *
     * @return bool True on success, false on failure (e.g., invalid parameters, permission issues, DB error).
     */
    public function batch_action(){

        if(!in_array(@$this->data['role'],array('remove','admin','member'))){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid parameter "role"');
            return false;
        }

        //group ids
        $this->recordIDs = prepareIds(@$this->data['groupID']);
        if(empty($this->recordIDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid workgroup identificator');
            return false;
        }

        //user ids
        $assignIDs = prepareIds(@$this->data['userIDs']);
        if(empty($assignIDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid user identificators');
            return false;
        }

        if(!$this->_validatePermission()){
            return false;
        }

        $mysqli = $this->system->getMysqli();

        $ret = true;


        //group cannot be without admin.
        if($this->data['role']=='remove' || $this->data['role']=='member'){

            //verification
            foreach ($this->recordIDs as $groupID){
                foreach ($assignIDs as $usrID){

                    $query = 'SELECT count(g2.ugl_ID) FROM sysUsrGrpLinks AS g2 LEFT JOIN sysUsrGrpLinks AS g1 '
                                .'ON g1.ugl_GroupID=g2.ugl_GroupID AND g2.ugl_Role="admin" '                             //is it the only admin
                                .'WHERE g1.ugl_UserID='.$usrID.' AND g1.ugl_Role="admin" AND g1.ugl_GroupID='.$groupID;  //is this user admin

                    //can't remove last admin
                    $cnt = mysql__select_value($mysqli, $query);
                    if($cnt==1){
                        $this->system->addError(HEURIST_ACTION_BLOCKED,
                            'It is not possible to '.(($this->data['role']=='remove')?'remove':' change role to" member" for')
                            .' user #'.$usrID.' from group #'.$groupID.'. This user is the only admin of the workgroup');
                        return false;
                    }
                }
            }

        }


        $keep_autocommit = mysql__begin_transaction($mysqli);

        $query2 = 'DELETE FROM sysUsrGrpLinks'
            . SQL_WHERE . predicateId('ugl_GroupID',$this->recordIDs)
            . SQL_AND . predicateId('ugl_UserID',$assignIDs);

        $res = $mysqli->query($query2);
        if(!$res){
            $this->system->addError(HEURIST_DB_ERROR, 'Can\'t remove users from workgroup', $mysqli->error );
            $ret = false;
        }

        if($this->data['role']!='remove'){

            foreach ($this->recordIDs as $groupID){
                $query = array();
                foreach ($assignIDs as $usrID){
                    array_push($query, ' ('. $groupID .' , '. $usrID .', "'.$this->data['role'].'")');
                }
                $query = 'INSERT INTO sysUsrGrpLinks (ugl_GroupID, ugl_UserID, ugl_Role) VALUES '
                        .implode(',', $query);
                $res = $mysqli->query($query);
                if(!$res){
                    $ret = false;
                    $this->system->addError(HEURIST_DB_ERROR,
                        'Can\'t set role in workgroup #'.$groupID, $mysqli->error );
                    break;
                }
            }//foreach

        }

        mysql__end_transaction($mysqli, $ret, $keep_autocommit);

        return $ret;
    }

}
?>
