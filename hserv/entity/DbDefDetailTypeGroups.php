<?php
/**
* DbDefDetailTypeGroups.php - Class DbDefDetailTypeGroups
*
* Operations for the `defDetailTypeGroups` table.
*
* @package     Heurist academic knowledge management system
* @subpackage  hserv\entity 
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
namespace hserv\entity;
use hserv\entity\DbEntityBase;

/**
* Class DbDefDetailTypeGroups
*
* Provides database access and operations for the `defDetailTypeGroups` table,
* which stores groups for detail types (field types).
*
*/
class DbDefDetailTypeGroups extends DbEntityBase
{
    /**
     * Initializes the DbDefDetailTypeGroups entity.
     *
     * Sets up duplication checks based on `dtg_Name` and foreign key checks
     * to prevent deletion of non-empty groups (groups that still contain detail types).
     *
     * @return void
     */
    public function init(){
        $this->duplicationCheck = array('dtg_Name'=>'Field type group cannot be saved. The provided name already exists');

        $this->foreignChecks = array(
                    array('select count(dty_ID) from defDetailTypes where dty_DetailTypeGroupID',
                          'Cannot delete non empty group')
                );
    }

   /**
     * Searches for Detail Type Groups (Field Groups) based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters from `$this->data`.
     *
     * It then adds specific predicates for this entity:
     * - `dtg_ID`: If provided in `$this->data['dtg_ID']`.
     * - `dtg_Name`: If provided in `$this->data['dtg_Name']`.
     *
     * The fields returned in the search results depend on `$this->data['details']`:
     * - 'id': Returns only `dtg_ID`.
     * - 'name': Returns `dtg_ID`, `dtg_Name`.
     * - 'list': Returns `dtg_ID`, `dtg_Name`, `dtg_Description`, `dtg_Order`, and `dtg_FieldCount` (calculated).
     * - Default ('full'): Returns all fields defined in `$this->fieldNames` plus `dtg_FieldCount`.
     * The `dtg_FieldCount` is a calculated field representing the number of detail types within each group.
     *
     * Results are ordered by `dtg_Order`.
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

        $this->searchMgr->addPredicate('dtg_ID');
        $this->searchMgr->addPredicate('dtg_Name');

        define('CNT_DTG',', (select count(dty_ID) from defDetailTypes where dtg_ID=dty_DetailTypeGroupID) as dtg_FieldCount ');

        switch (@$this->data['details']){
            case 'id': $this->searchMgr->setSelFields('dtg_ID'); break;
            case 'name': $this->searchMgr->setSelFields('dtg_ID,dtg_Name'); break;
            case 'list': $this->searchMgr->setSelFields('dtg_ID,dtg_Name,dtg_Description,dtg_Order'.CNT_DTG); break;
            default: $this->searchMgr->setSelFields(implode(',', $this->fieldNames).CNT_DTG);
        }

        return $this->searchMgr->composeAndExecute('dtg_Order');
    }

    //
    //
    //
    /**
     * Prepares records before saving.
     *
     * Sets the `dtg_Modified` field to the current date/time, ensures `dtg_Order`
     * defaults to 2 if not set, and determines if a record is new.
     * This method overrides the parent `prepareRecords` and calls it.
     *
     * @return bool Returns the result of `parent::prepareRecords()`.
     */
    protected function prepareRecords(){

        $ret = parent::prepareRecords();
        if($ret){
            //add specific field values
            foreach($this->records as $idx=>$record){

                $this->records[$idx]['dtg_Modified'] = date(DATE_8601);//reset

                if(!(@$this->records[$idx]['dtg_Order']>0)){
                    $this->records[$idx]['dtg_Order'] = 2;
                }

                $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['dtg_ID']>0));
            }
        }

        return $ret;

    }
}
?>
