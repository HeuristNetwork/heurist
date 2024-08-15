<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to dbDefCalcFunctions table
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

require_once dirname(__FILE__).'/../System.php';

class DbDefCalcFunctions extends DbEntityBase
{
    /**
    *  search claculcated fields
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
    public function delete($disable_foreign_checks = false){

        $this->recordIDs = prepareIds($this->data[$this->primaryField]);

        if(count($this->recordIDs)==0){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }

        $query = 'select count(rst_DetailTypeID) from defRecStructure where rst_CalcFunctionID in ('.implode(',', $this->recordIDs).')';
        $ret = mysql__select_value($this->system->get_mysqli(), $query);

        if($ret>0){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Cannot delete calculation that in use in '.$ret.' record types');
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
                    'You are not admin and can\'t edit field calculations. Insufficient rights (logout/in to refresh) for this operation '
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
            if(@$this->records[$idx]['cfn_Name']){
                $mysqli = $this->system->get_mysqli();
                $res = mysql__select_value($mysqli,
                        "SELECT cfn_ID FROM ".$this->config['tableName']."  WHERE cfn_Name='"
                        .$mysqli->real_escape_string( $this->records[$idx]['cfn_Name'] )."'");
                if($res>0 && $res!=@$this->records[$idx]['cfn_ID']){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Field calculation cannot be saved. The provided name already exists');
                    return false;
                }
            }

            $this->records[$idx]['cfn_Modified'] = date(DATE_8601);//reset

            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['cfn_ID']>0));
        }

        return $ret;

    }
}
?>
