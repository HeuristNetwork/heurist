<?php

    /**
    * db access to usrSavedSearches table for saved searches
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
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
require_once (dirname(__FILE__).'/../dbaccess/db_files.php');
require_once (dirname(__FILE__).'/../dbaccess/db_users.php'); //send email methods


class DbUsrSavedSearches extends DbEntityBase
{

    /**
    *  search users
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
                
        if(parent::search()===false){
              return false;   
        }
        
        $needCheck = false;
        
        //compose WHERE 
        $where = array();
        $from_table = array($this->config['tableName']);
        
        $pred = $this->searchMgr->getPredicate('svs_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('svs_Name');
        if($pred!=null) array_push($where, $pred);
        
        //find filters belong to group
        $pred = $this->searchMgr->getPredicate('svs_UGrpID');
        if($pred!=null) array_push($where, $pred);
        
        
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'svs_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'svs_ID,svs_Name';

        }else if(@$this->data['details']=='list' || @$this->data['details']=='full'){
            
            $this->data['details'] = 'svs_ID,svs_Name,svs_UGrpID,svs_Query';
            
        }else{
            $needCheck = true;
        }
        
        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }
        
        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        //----- order by ------------
        //compose ORDER BY
        $order = array();
        
        $value = @$this->data['sort:svs_Modified'];
        if($value!=null){
            array_push($order, 'svs_Modified '.($value>0?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:svs_Name'];
            if($value!=null){
                array_push($order, 'svs_Name '.($value>0?'ASC':'DESC'));
            }else{
                $value = @$this->data['sort:svs_ID'];
                if($value!=null){
                    array_push($order, 'svs_ID '.($value>0?'ASC':'DESC'));
                }else{
                    $value = @$this->data['sort:svs_Name'];
                    if($value!=null){
                        array_push($order, 'svs_Name ASC');
                    }
                }
            }
        }  

        $is_ids_only = (count($this->data['details'])==1);

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

        if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
        }
        if(count($order)>0){
            $query = $query.' ORDER BY '.implode(',',$order);
        }

        $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $calculatedFields = null;

        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName'], $calculatedFields);

        return $result;
    }
    
    
    //
    // validate permission for edit tag
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_admin() && (count($this->recordIDs)>0 || count($this->records)>0)){ //there are records to update/delete
        
        
            $grpIDs = $this->system->get_user_group_ids('admin');
            
            $mysqli = $this->system->get_mysqli();
                                                           
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
    protected function prepareRecords(){
    
        $ret = parent::prepareRecords();

        //@todo captcha validation for registration
        
        //add specific field values
        foreach($this->records as $idx=>$record){
            $this->records[$idx]['svs_Modified'] = date('Y-m-d H:i:s'); //reset
            
            $tbl = $this->config['tableName'];
            
            //validate duplication
            $mysqli = $this->system->get_mysqli();
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
