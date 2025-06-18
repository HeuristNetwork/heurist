<?php
/**
* DbUsrRecPermissions.php - Class DbUsrRecPermissions
*
* Operations for the `usrRecPermissions` table.
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv\entity 
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
* Class DbUsrRecPermissions
*
* Provides database access and operations for the `usrRecPermissions` table.
* This table stores record-level permissions, granting specific groups ('rcp_UGrpID')
* defined levels of access (e.g., 'view', 'edit') to specific records ('rcp_RecID').
*
*/
class DbUsrRecPermissions extends DbEntityBase
{
    /** @var bool Flag indicating if the usrRecPermissions table exists. Checked/set in init(). */
    private $is_table_exists = false;

    /**
     * Initializes the DbUsrRecPermissions entity.
     *
     * Checks if the `usrRecPermissions` table exists and attempts to create it if it doesn't.
     * This table is crucial for storing record-specific permissions for user groups.
     * Also ensures the `rcp_composite_key` index is dropped if it exists (as it's replaced or managed differently).
     *
     * @return void
     */
    public function init(){

        $mysqli = $this->system->getMysqli();

        $this->is_table_exists = hasTable($mysqli, 'sysImportFiles');

        if(!$this->is_table_exists){

            $query = 'CREATE TABLE IF NOT EXISTS `usrRecPermissions` ('
              ."`rcp_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary table key',"
              ."`rcp_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'ID of group',"
              ."`rcp_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which permission is linked',"
              ."`rcp_Level` enum('view','edit') NOT NULL default 'view' COMMENT 'Level of permission',"
              ."PRIMARY KEY  (rcp_ID)"
              //."UNIQUE KEY rcp_composite_key (rcp_RecID,rcp_UGrpID)"
            .") ENGINE=InnoDB COMMENT='Permissions for groups to records'";

            if ($mysqli->query($query)) {
                $this->is_table_exists = true;
            }

            $query = 'DROP INDEX IF EXISTS rcp_composite_key ON usrRecPermissions';
            $res = $mysqli->query($query);
        }

    }

    /**
     * Checks if the entity is valid.
     *
     * An entity is valid if its underlying table (`usrRecPermissions`) exists
     * and the parent `isvalid()` check (configuration loaded) also passes.
     *
     * @return bool True if the entity is valid, false otherwise.
     */
    public function isvalid(){
        return $this->is_table_exists && parent::isvalid();
    }


    /**
     * Searches for record permission entries (`usrRecPermissions`) based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters from `$this->data`.
     *
     * It then adds specific predicates for this entity:
     * - `rcp_RecID`: If provided in `$this->data['rcp_RecID']`.
     * - `rcp_UGrpID`: If provided in `$this->data['rcp_UGrpID']`.
     *
     * The fields returned in the search results depend on `$this->data['details']`:
     * - 'id': Returns only `rcp_ID`.
     * - 'full' (or default if 'details' is not 'id'): Returns all fields defined in `$this->fields` for this entity.
     * - If `$this->data['details']` is an array or comma-separated string, those specific fields are selected.
     *
     * Ordering is not explicitly defined in this method, relying on `DbEntitySearch::setOrderBy()`
     * or default database order.
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

        //compose WHERE
        $where = array();

        $pred = $this->searchMgr->getPredicate('rcp_RecID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('rcp_UGrpID');
        if($pred!=null) {array_push($where, $pred);}

        $needCheck = false;

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'rcp_ID';

        }elseif(@$this->data['details']=='full'){

            $this->data['details'] = implode(',', $this->fields );
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

        $is_ids_only = (count($this->data['details'])==1);

        $from_table = $this->config['tableName'];

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details']).' FROM '.$from_table;

         if(!empty($where)){
            $query = $query.SQL_WHERE.implode(SQL_AND,$where);
         }
         $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $res = $this->searchMgr->execute($query, $is_ids_only, $from_table);
        return $res;
    }

    //
    // at the moment we assign only "view" permissions
    // thus, check whether current user can change/edit the record
    //
    // similar see recordCanChangeOwnerwhipAndAccess
    //
    /**
     * Validates if the current user has permission to set/change permissions for the specified records.
     *
     * An admin can always change permissions. Other users must be the owner (or member of the owner group)
     * of all records for which permissions are being set.
     * This method checks ownership of records in `$this->records` (expected to be populated by `prepareRecords`).
     *
     * @return bool True if the user has sufficient permissions, false otherwise.
     *              Errors are added to the system object on permission failure.
     */
    protected function _validatePermission(){

        if($this->system->isAdmin()){  //admin can always change any record
            return true;
        }else{
            $recids = array();
            foreach($this->records as $record){
                $recids[] = $record['rcp_RecID'];
            }
            $recids = array_unique($recids);
            $grp_ids = $this->system->getUserGroupIds();//current user groups ids + itself

            //verify that current owner is "everyone" or current user is member of owner group
            $query = 'SELECT count(rec_OwnerUGrpID) FROM Records WHERE '
                .predicateId('rec_ID',$recids)
                .SQL_AND
                .'(rec_OwnerUGrpID=0 OR '
                .predicateId('rec_OwnerUGrpID',$grp_ids).')';

            $cnt = mysql__select_value($this->system->getMysqli(), $query);
            if($cnt<count($recids)){

                if(count($recids)==1){
                    $sMsg = 'the record ID:'.$recids[0];
                }else{
                    $sMsg =  (($cnt==0)?'all':((count($recids)-$cnt).' of '.count($recids)))
                                .' records provided in request';
                }

                $this->system->addError(HEURIST_REQUEST_DENIED,
                    'Current user does not have sufficient authority to change '.$sMsg
                    .'. User must be either the owner or member of the group that owns record');
                    return false;

            }

            return true;
        }
    }

    //
    //
    //
    /**
     * Saves record permissions.
     *
     * This method first deletes all existing permissions for the specified `rcp_RecID`(s)
     * and then inserts the new permissions provided in `$this->records`.
     * Currently, it only sets the `rcp_Level` to 'view'.
     * Operations are performed within a database transaction.
     *
     * @return array|false An array containing the insert ID of the first new permission record on success,
     *                     or false on failure. Errors are added to the system object on failure.
     */
    public function save(){

        //extract records from $_REQUEST data
        if(!$this->prepareRecords()){
                return false;
        }

        //validate permission for current user and set of records see $this->recordIDs
        if(!$this->_validatePermission()){ //is records permission to be set belongs to owner
            return false;
        }

        $recids = array();
        //validate values and check mandatory fields
        foreach($this->records as $record){

            $this->data['fields'] = $record;

            //validate mandatory fields
            if(!$this->_validateMandatory()){
                return false;
            }

            //validate values
            if(!$this->_validateValues()){
                return false;
            }

            $recids[] = $record['rcp_RecID'];
        }
        $recids = array_unique($recids);

        //array of inserted or updated record IDs
        $results = array();

        //start transaction
        $mysqli = $this->system->getMysqli();

        $keep_autocommit = mysql__begin_transaction($mysqli);

        //remove all current permissions
        $query = SQL_DELETE.$this->config['tableName']
                                .SQL_WHERE
                                .predicateId('rcp_RecID',$recids);
        $res = $mysqli->query( $query );
        if(!$res){
             $this->system->addError(HEURIST_DB_ERROR,
                        'Cannot delete current permissions', $mysqli->error);
        }else{

            //add new permissions
            $query = array();
            foreach($this->records as $rec_idx => $record){
                $query[] = '(' .$record['rcp_UGrpID'] .',' . $record['rcp_RecID'] . ', "view" )';
            }
            $query = ' INSERT INTO '.$this->config['tableName']
               .' (rcp_UGrpID,rcp_RecID,rcp_Level) VALUES '.implode(',', $query);

            $res = $mysqli->query( $query );
            if(!$res){
                $this->system->addError(HEURIST_DB_ERROR,
                        'Cannot save data in table '.$this->config['entityName'], $mysqli->error);
            }else{
                $res = array($mysqli->insert_id);
            }
        }

        mysql__end_transaction($mysqli, $res, $keep_autocommit);

        return $res;

    }


    //
    // delete permissions for given Record IDs or Group IDs
    // see parameters $this->data['rcp_RecID'] or $this->data['rcp_UGrpID']
    //
    /**
     * Deletes record permissions.
     *
     * This method can delete permissions based on:
     * 1. Record IDs (`$this->data['rcp_RecID']`): Deletes all permissions associated with the specified records.
     *    Requires the current user to have ownership/admin rights over these records.
     * 2. Group IDs (`$this->data['rcp_UGrpID']`): Deletes all permissions granted to the specified groups.
     *    Requires the current user to be a member of the groups whose permissions are being deleted.
     *
     * @param bool $disable_foreign_checks Unused in this implementation.
     * @return bool True on successful deletion, false on failure.
     *              Errors are added to the system object on failure or permission denial.
     */
    public function delete($disable_foreign_checks = false){

        //extract records from $_REQUEST data
        $mysqli = $this->system->getMysqli();

        if(!@$this->data['rcp_RecID']){ //array of record ids

            $this->records = array();//need to validate permissions
            $recids = prepareIds($this->data['rcp_RecID']);
            foreach ($recids as $id){
                $this->records = array('rcp_RecID'=>$id);
            }

            if(!$this->_validatePermission()){
                return false;
            }

            $query = SQL_DELETE.$this->config['tableName']
                                .SQL_WHERE
                                .predicateId('rcp_RecID',$recids);

            $res = $mysqli->query( $query );
            if(!$res){
                 $this->system->addError(HEURIST_DB_ERROR,
                            'Cannot delete permissions', $mysqli->error);
                 return false;
            }

        }elseif(!@$this->data['rcp_UGrpID']){ //array of group ids

            $group_ids_to_delete = prepareIds($this->data['rcp_UGrpID']);

            //current user must be a member of all provided groups
            $grp_ids = $this->system->getUserGroupIds();//current user groups ids + itself

            foreach ($group_ids_to_delete as $id){
                if(!in_array($id, $grp_ids)){
                    $this->system->addError(HEURIST_REQUEST_DENIED,
                        'Current user does not have sufficient authority to remove permissions. '
                        .' User must be either the owner or member of the group that owns record');
                    return false;
                }
            }

            $query = SQL_DELETE.$this->config['tableName']
                                .SQL_WHERE
                                .predicateId('rcp_UGrpID',$group_ids_to_delete);

            $res = $mysqli->query( $query );
            if(!$res){
                 $this->system->addError(HEURIST_DB_ERROR,
                            'Cannot delete permissions for given groups', $mysqli->error);
                 return false;
            }

        }


        return true;
    }


}
?>
