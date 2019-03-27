<?php

    /**
    * db access to Records table - NOT IMPLEMENTED
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/dbEntityBase.php');
require_once (dirname(__FILE__).'/dbEntitySearch.php');


class DbRecords extends DbEntityBase
{
    /**
    * to be implemented
    * 
    *  search records
    * 
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    * 
    *  @todo overwrite
    */
    public function search(){
        
        $this->searchMgr = new DbEntitySearch( $this->system, $this->fields);

        $res = null;//$this->searchMgr->execute($query, $is_ids_only, 'recUploadedFiles', $calculatedFields);
        
        return $res;

    }
    
    //
    // to be implemented
    //
    public function save(){
        
        //$ret = parent::save();
        $ret = "to be implemented";
        return $ret;
    } 
    
}
?>
