<?php
/**
* DbSysDashboard.php - Class DbSysDashboard
*
* Operations for the `sysDashboard` table.
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

require_once dirname(__FILE__).'/../records/search/recordFile.php';

/**
* Class DbSysDashboard
*
* Provides database access and operations for the `sysDashboard` table,
* which stores configuration for dashboard entries (shortcuts, links, actions).
*
*/
class DbSysDashboard extends DbEntityBase
{

    /**
     * Constructor for DbSysDashboard.
     *
     * Calls the parent constructor and sets `requireAdminRights` to false,
     * as dashboard entries can often be managed by non-admin users for their own context.
     *
     * @param \hserv\System $system The main Heurist system object.
     * @param array|null $data Optional data to initialize the entity with.
     */
    public function __construct( $system, $data=null ) {
       parent::__construct( $system, $data );
       $this->requireAdminRights = false;
    }

    /**
     * Searches for dashboard entries based on criteria in `$this->data`.
     *
     * This method extends the base search functionality. It first calls `parent::search()`
     * to initialize the `DbEntitySearch` manager (`$this->searchMgr`) and validate
     * common search parameters from `$this->data`.
     *
     * It then adds specific predicates for this entity:
     * - `dsh_ID`: If provided in `$this->data['dsh_ID']`.
     * - `dsh_Label`: If provided in `$this->data['dsh_Label']`.
     * - `dsh_Enabled`: If provided in `$this->data['dsh_Enabled']`.
     * - `dsh_ShowIfNoRecords`: If provided in `$this->data['dsh_ShowIfNoRecords']`.
     *
     * The fields returned in the search results depend on `$this->data['details']`:
     * - 'id': Returns only `dsh_ID`.
     * - 'name': Returns `dsh_ID`, `dsh_Label`.
     * - Default (if 'details' is not 'id' or 'name'): Returns `dsh_ID`, `dsh_Order`, `dsh_Label`,
     *   `dsh_Description`, `dsh_Enabled`, `dsh_ShowIfNoRecords`, `dsh_CommandToRun`, `dsh_Parameters`.
     *
     * Ordering is not explicitly defined in this method (passed as `null` to `composeAndExecute`),
     * so it relies on the database's default order or an externally set order via `DbEntitySearch::setOrderBy()`.
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

        $this->searchMgr->addPredicate('dsh_ID');
        $this->searchMgr->addPredicate('dsh_Label');
        $this->searchMgr->addPredicate('dsh_Enabled');
        $this->searchMgr->addPredicate('dsh_ShowIfNoRecords');

        switch (@$this->data['details']){
            case 'id': $this->searchMgr->setSelFields('dsh_ID'); break;
            case 'name': $this->searchMgr->setSelFields('dsh_ID,dsh_Label'); break;
            default: $this->searchMgr->setSelFields('dsh_ID,dsh_Order,dsh_Label,dsh_Description,dsh_Enabled,dsh_ShowIfNoRecords,dsh_CommandToRun,dsh_Parameters');
        }

        return $this->searchMgr->composeAndExecute(null);
    }

    //
    //
    //
    /**
     * Prepares dashboard entry records before saving.
     *
     * - Sets `dsh_Enabled` to 'y' if not provided.
     * - Sets `dsh_Order` to 1 if not a positive integer.
     * - Consolidates `dsh_ParameterAddRecord` or `dsh_ParameterSavedSearch` into `dsh_Parameters`
     *   based on `dsh_CommandToRun`.
     * - Validates `dsh_Label` for duplication.
     *
     * @return bool True if preparation is successful and validation passes, false otherwise.
     */
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){
            if(!@$this->records[$idx]['dsh_Enabled']){
                    $this->records[$idx]['dsh_Enabled'] = 'y';
            }
            if(@$this->records[$idx]['dsh_Order']==null
                || !($this->records[$idx]['dsh_Order']>0)){
                    $this->records[$idx]['dsh_Order'] = 1;
            }
            if(@$this->records[$idx]['dsh_CommandToRun']=='action-AddRecord'
                            && @$this->records[$idx]['dsh_ParameterAddRecord']){
                $this->records[$idx]['dsh_Parameters'] = $this->records[$idx]['dsh_ParameterAddRecord'];
            }elseif(@$this->records[$idx]['dsh_CommandToRun']=='action-SearchById'
                            && @$this->records[$idx]['dsh_ParameterSavedSearch']){
                $this->records[$idx]['dsh_Parameters'] = $this->records[$idx]['dsh_ParameterSavedSearch'];
            }

            //validate duplication
            if(!$this->doDuplicationCheck($idx, 'dsh_Label', 'Dashboard entry cannot be saved. The provided name already exists')){
                    return false;
            }
        }

        return $ret;

    }

    //
    //
    //
    /**
     * Saves dashboard entry records.
     *
     * After calling `parent::save()`, this method handles renaming any associated
     * temporary image file (for `dsh_Image`) to its permanent name.
     *
     * @return array|false The result from `parent::save()` (array of saved IDs or false).
     */
    public function save(){

        $savedRecIds = parent::save();

        if($savedRecIds!==false){

            //treat group image
            foreach($this->records as $record){
                $dsh_ID = @$record['dsh_ID'];
                if($dsh_ID && in_array($dsh_ID, $savedRecIds)){
                    $thumb_file_name = @$record['dsh_Image'];

                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $dsh_ID);
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
     * Deletes dashboard entry/entries.
     *
     * After calling `parent::delete()` to remove the database record(s),
     * this method also deletes any associated image file for each deleted entry.
     *
     * @param bool $disable_foreign_checks Passed to `parent::delete()`.
     * @return bool True if database deletion was successful (regardless of image file deletion status),
     *              false otherwise.
     */
    public function delete($disable_foreign_checks = false){

        $ret = parent::delete();

        if($ret){

            foreach($this->recordIDs as $recID)  //affected entries
            {
                    $fname = $this->getEntityImagePath($recID);
                    if(file_exists($fname)){
                        unlink($fname);
                    }
            }
        }
        return $ret;
    }

}
?>
