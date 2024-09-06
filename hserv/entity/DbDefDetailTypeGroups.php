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
    public function init(){
        $this->duplicationCheck = array('dtg_Name'=>'Field type group cannot be saved. The provided name already exists');

        $this->foreignChecks = array(
                    array('select count(dty_ID) from defDetailTypes where dty_DetailTypeGroupID',
                          'Cannot delete non empty group')
                );
    }
    
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
