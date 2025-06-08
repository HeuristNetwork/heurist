<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
     * Class DbRecThreadedComments
     *
     * Provides database access and operations for the `recThreadedComments` table,
     * which stores threaded comments related to records.
     *
     * @package     Heurist academic knowledge management system
     * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

class DbRecThreadedComments extends DbEntityBase
{

    /**
     * Searches for threaded comments based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters. If `cmt_OwnerUgrpID` is not provided in `$this->data`,
     * it defaults to the current user's ID.
     *
     * It then adds specific predicates for this entity:
     * - `cmt_ID`: If provided in `$this->data['cmt_ID']`.
     * - `cmt_OwnerUgrpID`: If provided or defaulted.
     * - `cmt_RecID`: If provided in `$this->data['cmt_RecID']`.
     * - `cmt_Text`: If provided in `$this->data['cmt_Text']` (searches with LIKE).
     *
     * The fields returned in the search results depend on `$this->data['details']`:
     * - 'id': Returns only `cmt_ID`.
     * - 'list' or 'name': Returns `cmt_ID`, `cmt_RecID`, `cmt_ParentCmtID`, `cmt_OwnerUgrpID`,
     *   a substring of `cmt_Text` (50 chars), `cmt_Modified`. Additionally, if these details
     *   are requested or if sorting is by `recTitle`, it joins with the `Records` table
     *   to include `rec_Title` as `cmt_RecTitle`.
     * - Default ('full'): Returns `cmt_ID`, `cmt_RecID`, `cmt_ParentCmtID`, `cmt_OwnerUgrpID`,
     *   `cmt_Text`, `cmt_Modified`.
     *
     * The order of results is determined by `$this->searchMgr->setOrderBy()`.
     *
     * @return array|false An array containing the search results as structured by `DbEntitySearch::execute()`,
     *                     typically including 'records', 'count', 'total_count', etc.
     *                     Returns `false` if `parent::search()` fails (e.g., parameter validation error)
     *                     or if the database query fails.
     */
    public function search(){

        if(!@$this->data['cmt_OwnerUgrpID']){
            $this->data['cmt_OwnerUgrpID'] = $this->system->getUserId();
        }

        if(parent::search()===false){
              return false;
        }

        $needRecords = false;

        $this->searchMgr->addPredicate('cmt_ID');
        $this->searchMgr->addPredicate('cmt_OwnerUgrpID');
        $this->searchMgr->addPredicate('cmt_RecID');
        $this->searchMgr->addPredicate('cmt_Text');

        switch (@$this->data['details']){
            case 'id': $fieldList = 'cmt_ID'; break;
            case 'list':
                $needRecords = true;
                $fieldList = 'cmt_ID,cmt_RecID,cmt_ParentCmtID,cmt_OwnerUgrpID,SUBSTRING(cmt_Text,1,50) as cmt_Text,cmt_Modified';
                break;
            case 'name':
                $fieldList = 'cmt_ID,cmt_RecID,cmt_ParentCmtID,cmt_OwnerUgrpID,SUBSTRING(cmt_Text,1,50) as cmt_Text,cmt_Modified';
                break;
            default:   //'full'
                $fieldList = 'cmt_ID,cmt_RecID,cmt_ParentCmtID,cmt_OwnerUgrpID,cmt_Text,cmt_Modified';
                break;
        }

        $orderby = $this->searchMgr->setOrderBy();
        if($orderby!=null && strpos('recTitle',$orderby)===0){
            $needRecords = true;
        }

        if($needRecords){ //return rec_Title for comment
              $fieldList .= ', rec_Title as cmt_RecTitle';
              $sup_tables = ', Records';
              $sup_where = '(rec_ID=cmt_RecID)';
        }

        $this->searchMgr->setSelFields($fieldList);

        return $this->searchMgr->composeAndExecute($orderby, $sup_tables, $sup_where);
    }

    //
    // validate permission for edit comment
    // for delete and assign see appropriate methods
    //
    /**
     * Validates if the current user has permission to modify/delete the specified comments.
     *
     * Users can only modify/delete their own comments unless they are the database owner.
     * This method overrides the parent `_validatePermission`.
     *
     * @return bool True if the user has permission, false otherwise.
     *              Errors are added to the system object on permission failure.
     */
    protected function _validatePermission(){

        if(!$this->system->isDbOwner() && !isEmptyArray($this->recordIDs)){ //there are records to update/delete

            $ugrID = $this->system->getUserId();

            $mysqli = $this->system->getMysqli();

            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField,
                    'cmt_ID in ('.implode(',', $this->recordIDs).') AND cmt_OwnerUgrpID!='.$ugrID);

            $cnt = (is_array($recIDs_norights))?count($recIDs_norights):0;

            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED,
                (($cnt==1 && (!is_array($this->records) || count($this->records)==1))
                    ? 'Comment belongs'
                    : $cnt.' Comments belong')
                    .' to other user. Insufficient rights (logout/in to refresh) for this operation');
                return false;
            }
        }

        return true;
    }


    //
    //
    //
    /**
     * Prepares comment records before saving.
     *
     * - For new comments, sets `cmt_OwnerUgrpID` to the current user's ID if not already set.
     * - Sets `cmt_Modified` to the current date/time.
     * This method overrides the parent `prepareRecords`.
     *
     * @return bool Returns the result of `parent::prepareRecords()`.
     */
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){
            $rec_ID = intval(@$record[$this->primaryField]);
            $isinsert = ($rec_ID<1);
            if($isinsert){
                if(!($this->records[$idx]['cmt_OwnerUgrpID']>0)){
                    $this->records[$idx]['cmt_OwnerUgrpID'] = $this->system->getUserId();
                }
            }
            $this->records[$idx]['cmt_Modified'] = date(DATE_8601);//reset
        }

        return $ret;

    }

    //
    // batch action for comments - changing flag for cmt_Deleted
    //
    /**
     * Performs batch actions on comments.
     *
     * Currently, this method seems to prepare records and record IDs but does not
     * execute a specific database action like changing a `cmt_Deleted` flag.
     * It calls `prepareRecords()` and populates `$this->recordIDs`.
     *
     * @todo Implement actual batch operations like bulk delete or flag change.
     * @return bool True if `prepareRecords()` is successful, false otherwise.
     */
    public function batch_action(){

        $recordIDs = prepareIds($this->data['recIDs']);
        if(!empty($recordIDs)){
            //find record by ids  - todo

        }

        if(!$this->prepareRecords()){
                return false;
        }

        $mysqli = $this->system->getMysqli();

        foreach($this->records as $record){

            if($record[$this->primaryField]>0){
                $this->recordIDs[] = $record[$this->primaryField];
            }


        }//for comments


        return true;
    }

}
?>
