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
    
    public function init(){
        $this->duplicationCheck = array('rtg_Name'=>'Record type group cannot be saved. The provided name already exists');
        
        $this->foreignChecks = array(
                    array('select count(rty_ID) from defRecTypes where `rty_RecTypeGroupID`',
                          'Cannot delete non empty group')
                );        

    }
    
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
