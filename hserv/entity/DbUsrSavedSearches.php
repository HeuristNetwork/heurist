<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
     * Class DbUsrSavedSearches
     *
     * Provides database access and operations for the `usrSavedSearches` table,
     * which stores user-defined saved search queries.
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
require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';//send email methods

class DbUsrSavedSearches extends DbEntityBase
{

    /**
     * Searches for saved search records.
     *
     * Supports filtering by `svs_ID`, `svs_Name`, and `svs_UGrpID`.
     * The level of detail returned (`id`, `name`, or `list`/`full`) is controlled by `$this->data['details']`.
     * Results are ordered by `svs_Name` ASC by default.
     *
     * @return array|false An array of found saved search records, or false on error.
     */
    public function search(){

        if(parent::search()===false){
              return false;
        }

        $this->searchMgr->addPredicate('svs_ID');
        $this->searchMgr->addPredicate('svs_Name');
        $this->searchMgr->addPredicate('svs_UGrpID');

        switch (@$this->data['details']){
            case 'id': $this->searchMgr->setSelFields('svs_ID'); break;
            case 'name':
                $this->searchMgr->setSelFields('svs_ID,svs_Name');
                break;
            default:   // list, full
                $this->searchMgr->setSelFields('svs_ID,svs_Name,svs_UGrpID,svs_Query');
        }

        $orderby = $this->searchMgr->setOrderBy('svs_Name ASC');

        return $this->searchMgr->composeAndExecute($orderby);
    }


    //
    // validate permission for edit tag
    // for delete and assign see appropriate methods
    //
    /**
     * Validates if the current user has permission to modify/delete the specified saved searches.
     *
     * Users can only manage saved searches belonging to groups they administer, unless they are a system admin.
     * This method overrides the parent `_validatePermission`.
     *
     * @return bool True if the user has permission, false otherwise.
     *              Errors are added to the system object on permission failure.
     */
    protected function _validatePermission(){

        if(!$this->system->isAdmin() &&
            (!isEmptyArray($this->recordIDs)
            || !isEmptyArray($this->records))){ //there are records to update/delete


            $grpIDs = $this->system->getUserGroupIds('admin');

            $mysqli = $this->system->getMysqli();

            $cnt = mysql__select_value($mysqli, 'SELECT count(svs_ID) FROM '.$this->config['tableName']
            .' WHERE svs_ID in ('.implode(',', $this->recordIDs).' AND svs_UGrpID not in ('.implode(',', $grpIDs).')');


            if($cnt>0){

                $this->system->addError(HEURIST_REQUEST_DENIED,
                    'Insufficient rights (logout/in to refresh) for this operation');
                return false;
            }
        }

        return true;
    }

    //
    //
    //
    /**
     * Prepares saved search records before saving.
     *
     * - Sets `svs_Modified` to the current date/time.
     * - Validates `svs_Name` for duplication within the same `svs_UGrpID`.
     * - Sets `is_new` flag.
     *
     * @todo Add captcha validation for registration (as per comment in code).
     * @return bool True if preparation is successful and validation passes, false otherwise.
     */
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //@todo captcha validation for registration

        //add specific field values
        foreach($this->records as $idx=>$record){

            $this->records[$idx]['svs_Modified'] = date(DATE_8601);//reset

            $tbl = $this->config['tableName'];

            //validate duplication
            $mysqli = $this->system->getMysqli();
            $res = mysql__select_value($mysqli,
                    "SELECT svs_ID FROM $tbl  WHERE svs_UGrpID="
                    .$this->records[$idx]['svs_UGrpID']
                    ." AND svs_Name='"
                    .$mysqli->real_escape_string( $this->records[$idx]['svs_Name'])."'");

            if($res>0 && $res!=@$this->records[$idx]['svs_ID']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Filter cannot be saved. The provided name already exists in group');
                return false;
            }

            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['svs_ID']>0));

        }

        return $ret;

    }

}
?>
