<?php
/**
* DbDefCalcFunctions.php - Class DbDefCalcFunctions
*
* Operations for the `defCalcFunctions` table
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/

namespace hserv\entity;
use hserv\entity\DbEntityBase;

/**
* Class DbDefCalcFunctions
*
* Provides database access and operations for the `defCalcFunctions` table,
* which stores definitions for calculated fields.
* 
*/
class DbDefCalcFunctions extends DbEntityBase
{

    /**
     * Initializes the DbDefCalcFunctions entity.
     *
     * Sets up duplication checks based on `cfn_Name` and foreign key checks
     * to prevent deletion of calculations used in `defRecStructure`.
     *
     * @return void
     */
    public function init(){
        $this->duplicationCheck = array('cfn_Name'=>'Field calculation cannot be saved. The provided name already exists');

        $this->foreignChecks = array(
                    array('select count(rst_DetailTypeID) from defRecStructure where rst_CalcFunctionID',
                          'Cannot delete calculation that in use in record types')
                );
    }


    /**
     * Searches for calculated field definitions based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters from `$this->data`.
     *
     * It then adds specific predicates for this entity:
     * - `cfn_ID`: If provided in `$this->data['cfn_ID']`.
     * - `cfn_Name`: If provided in `$this->data['cfn_Name']`.
     *
     * The fields returned in the search results depend on `$this->data['details']`:
     * - 'id': Returns only `cfn_ID`.
     * - 'name': Returns `cfn_ID`, `cfn_Name`.
     * - Default ('full'): Returns `cfn_ID`, `cfn_Name`, `cfn_FunctionSpecification`, `cfn_RecTypeIDs`.
     *
     * Results are ordered by `cfn_Name`.
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

        $this->searchMgr->addPredicate('cfn_ID');
        $this->searchMgr->addPredicate('cfn_Name');

        switch (@$this->data['details']){
            case 'id': $this->searchMgr->setSelFields('cfn_ID'); break;
            case 'name': $this->searchMgr->setSelFields('cfn_ID,cfn_Name'); break;
            default: $this->searchMgr->setSelFields('cfn_ID,cfn_Name,cfn_FunctionSpecification,cfn_RecTypeIDs');
        }

        return $this->searchMgr->composeAndExecute('cfn_Name');
    }

    //
    //
    //
    /**
     * Prepares records before saving.
     *
     * Sets the `cfn_Modified` field to the current date/time and determines if a record is new.
     * This method overrides the parent `prepareRecords` and calls it.
     *
     * @return bool Returns the result of `parent::prepareRecords()`.
     */
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){
            $this->records[$idx]['cfn_Modified'] = date(DATE_8601);//reset

            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['cfn_ID']>0));
        }

        return $ret;

    }
}
?>
