<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
     * Class DbDefRecTypeGroups
     *
     * Provides database access and operations for the `defRecTypeGroups` table,
     * which stores groups for record types.
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

class DbDefRecTypeGroups extends DbEntityBase
{

    /**
     * Initializes the DbDefRecTypeGroups entity.
     *
     * Sets up duplication checks based on `rtg_Name` and foreign key checks
     * to prevent deletion of non-empty groups (groups that still contain record types).
     *
     * @return void
     */
    public function init(){
        $this->duplicationCheck = array('rtg_Name'=>'Record type group cannot be saved. The provided name already exists');

        $this->foreignChecks = array(
                    array('select count(rty_ID) from defRecTypes where `rty_RecTypeGroupID`',
                          'Cannot delete non empty group')
                );

    }

    /**
     * Searches for Record Type Groups based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters from `$this->data`.
     *
     * It then adds specific predicates for this entity:
     * - `rtg_ID`: If provided in `$this->data['rtg_ID']`.
     * - `rtg_Name`: If provided in `$this->data['rtg_Name']`.
     *
     * The fields returned in the search results depend on `$this->data['details']`:
     * - 'id': Returns only `rtg_ID`.
     * - 'name': Returns `rtg_ID`, `rtg_Name`.
     * - 'list': Returns `rtg_ID`, `rtg_Name`, `rtg_Description`, `rtg_Order`, and `rtg_RtCount` (calculated).
     * - Default ('full'): Returns all fields defined in `$this->fieldNames` plus `rtg_RtCount`.
     * The `rtg_RtCount` is a calculated field representing the number of record types within each group.
     *
     * Results are ordered by `rtg_Order`.
     *
     * @return array|false An array containing the search results as structured by `DbEntitySearch::execute()`,
     *                     typically including 'records', 'count', 'total_count', etc.
     *                     Returns `false` if `parent::search()` fails (e.g., parameter validation error)
     *                     or if the database query fails.
     */
    public function search(){

        if(parent::search()===false){ //init search mgr
              return false;
        }

        $this->searchMgr->addPredicate('rtg_ID');
        $this->searchMgr->addPredicate('rtg_Name');

        define('CNT_RTG',', (select count(rty_ID) from defRecTypes where rtg_ID=rty_RecTypeGroupID) as rtg_RtCount ');

        switch (@$this->data['details']){
            case 'id': $this->searchMgr->setSelFields('rtg_ID'); break;
            case 'name': $this->searchMgr->setSelFields('rtg_ID,rtg_Name'); break;
            case 'list': $this->searchMgr->setSelFields('rtg_ID,rtg_Name,rtg_Description,rtg_Order'.CNT_RTG); break;
            default: $this->searchMgr->setSelFields(implode(',', $this->fieldNames).CNT_RTG);
        }

        return $this->searchMgr->composeAndExecute('rtg_Order');
    }

    //
    //
    //
    /**
     * Prepares records before saving.
     *
     * Sets the `rtg_Modified` field to the current date/time, ensures `rtg_Order`
     * defaults to 2 if not set, and determines if a record is new.
     * This method overrides the parent `prepareRecords` and calls it.
     *
     * @return bool Returns the result of `parent::prepareRecords()`.
     */
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){

            $this->records[$idx]['rtg_Modified'] = date(DATE_8601);//reset

            if(!(@$this->records[$idx]['rtg_Order']>0)){
                $this->records[$idx]['rtg_Order'] = 2;
            }

            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['rtg_ID']>0));
        }

        return $ret;

    }
}
?>
