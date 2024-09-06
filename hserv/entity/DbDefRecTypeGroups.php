<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to sysUGrpps table
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

class DbDefRecTypeGroups extends DbEntityBase
{
    /**
    *  search rectype groups
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
    public function delete($disable_foreign_checks = false){
        
        $this->isDeleteReady = false;
        
        $this->foreignChecks = array(
                    array('select count(rty_ID) from defRecTypes where `rty_RecTypeGroupID`',
                          'Cannot delete non empty group')
                );        

        return parent::delete();
    }

    //
    //
    //
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){

            //validate duplication
            if(@$this->records[$idx]['rtg_Name']){
                $mysqli = $this->system->get_mysqli();
                $res = mysql__select_value($mysqli,
                        "SELECT rtg_ID FROM ".$this->config['tableName']."  WHERE rtg_Name='"
                        .$mysqli->real_escape_string( $this->records[$idx]['rtg_Name'])."'");
                if($res>0 && $res!=@$this->records[$idx]['rtg_ID']){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Record type group cannot be saved. The provided name already exists');
                    return false;
                }
            }

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
