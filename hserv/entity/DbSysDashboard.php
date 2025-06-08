<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
     * Class DbSysDashboard
     *
     * Provides database access and operations for the `sysDashboard` table,
     * which stores configuration for dashboard entries (shortcuts, links, actions).
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

require_once dirname(__FILE__).'/../records/search/recordFile.php';


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
     * Searches for dashboard entries.
     *
     * Supports searching by `dsh_ID`, `dsh_Label`, `dsh_Enabled`, and `dsh_ShowIfNoRecords`.
     * The level of detail returned (`id`, `name`, `list`, or `full`) is controlled by `$this->data['details']`.
     *
     * @return array|false An array of found dashboard entries, or false on error.
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
