<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to defDetailTypeGroups table
    *
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
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

class DbDefDetailTypeGroups extends DbEntityBase
{
    /**
    *  search field gruops
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
    public function delete($disable_foreign_checks = false){

        $this->recordIDs = prepareIds($this->data[$this->primaryField]);

        if(count($this->recordIDs)==0){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }

        $query = 'select count(dty_ID) from defDetailTypes where dty_DetailTypeGroupID in ('.implode(',', $this->recordIDs).')';
        $ret = mysql__select_value($this->system->get_mysqli(), $query);

        if($ret>0){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Cannot delete non empty group');
            return false;
        }

        return parent::delete();
    }


    //
    // validate permission
    //
    protected function _validatePermission(){

        if(!$this->system->is_admin() &&
            ((is_array($this->recordIDs) && count($this->recordIDs)>0)
            || (is_array($this->records) && count($this->records)>0))){ //there are records to update/delete

            $this->system->addError(HEURIST_REQUEST_DENIED,
                    'You are not admin and can\'t edit field type groups. Insufficient rights (logout/in to refresh) for this operation '
                        .$this->system->get_user_id().'  '.print_r($this->system->getCurrentUser(), true));
            return false;

        }

        return true;
    }

    //
    //
    //
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){

            //validate duplication
            if(@$this->records[$idx]['dtg_Name']){
                $mysqli = $this->system->get_mysqli();
                $res = mysql__select_value($mysqli,
                        "SELECT dtg_ID FROM ".$this->config['tableName']."  WHERE dtg_Name='"
                        .$mysqli->real_escape_string( $this->records[$idx]['dtg_Name'] )."'");
                if($res>0 && $res!=@$this->records[$idx]['dtg_ID']){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Field type group cannot be saved. The provided name already exists');
                    return false;
                }
            }

            $this->records[$idx]['dtg_Modified'] = date(DATE_8601);//reset

            if(!(@$this->records[$idx]['dtg_Order']>0)){
                $this->records[$idx]['dtg_Order'] = 2;
            }

            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['dtg_ID']>0));
        }

        return $ret;

    }
}
?>