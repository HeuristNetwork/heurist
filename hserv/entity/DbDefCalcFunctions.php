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

class DbDefCalcFunctions extends DbEntityBase
{

    public function init(){
        $this->duplicationCheck = array('cfn_Name'=>'Field calculation cannot be saved. The provided name already exists');

        $this->foreignChecks = array(
                    array('select count(rst_DetailTypeID) from defRecStructure where rst_CalcFunctionID',
                          'Cannot delete calculation that in use in record types')
                );
    }

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
