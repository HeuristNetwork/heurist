<?php

    /**
    * db access to recThreadedComments.php table
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/dbEntityBase.php');
require_once (dirname(__FILE__).'/dbEntitySearch.php');

class DbRecThreadedComments extends DbEntityBase
{

    /**
    *  search usrReminders
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
                
        if(!@$this->data['cmt_OwnerUgrpID']){
            $this->data['cmt_OwnerUgrpID'] = $this->system->get_user_id();
        }
        
        if(parent::search()===false){
              return false;   
        }
        
        $needCheck = false;
        $needRecords = false;
        
        //compose WHERE 
        $where = array();
        $from_table = array($this->config['tableName']);
        
        $pred = $this->searchMgr->getPredicate('cmt_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('cmt_OwnerUgrpID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('cmt_RecID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('cmt_Text');
        if($pred!=null) array_push($where, $pred);

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'cmt_ID';
            
        }else if(@$this->data['details']=='name' || @$this->data['details']=='list'){

            $needRecords = (@$this->data['details']=='list');
            
            $this->data['details'] = 'cmt_ID,cmt_RecID,cmt_ParentCmtID,cmt_OwnerUgrpID,SUBSTRING(cmt_Text,1,50) as cmt_Text,cmt_Modified';

        }else if(@$this->data['details']=='full'){

            $this->data['details'] = 'cmt_ID,cmt_RecID,cmt_ParentCmtID,cmt_OwnerUgrpID,cmt_Text,cmt_Modified';
            
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
        
        $value = @$this->data['sort:cmt_Modified'];
        if($value!=null){
            array_push($order, 'cmt_Modified '.($value==1?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:cmt_RecTitle'];
            if($value!=null){
                array_push($order, 'rec_Title '.($value==1?'ASC':'DESC'));
                $needRecords = true;
            }
        }           
        
        
        
        $is_ids_only = (count($this->data['details'])==1);
        
        if($needRecords){
              array_push($this->data['details'], 'rec_Title as cmt_RecTitle');
              array_push($from_table,'Records');
              array_push($where, 'rec_ID=cmt_RecID');
        }
        
            
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
    // validate permission for edit comment
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_dbowner() && is_array($this->recordIDs) &&  count($this->recordIDs)>0){ //there are records to update/delete
            
            $ugrID = $this->system->get_user_id();
            
            $mysqli = $this->system->get_mysqli();
             
            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField, 
                    'cmt_ID in ('.implode(',', $this->recordIDs).') AND cmt_OwnerUgrpID!='.$ugrID);
            
            $cnt = (is_array($recIDs_norights))?count($recIDs_norights):0;       
                    
            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED, 
                (($cnt==1 && (!is_array($this->records) || count($this->records)==1))
                    ? 'Comment belongs'
                    : $cnt.' Comments belong')
                    .' to other user. Insufficient rights (logout/in to refresh) for this operation');
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

        //add specific field values
        foreach($this->records as $idx=>$record){
            $rec_ID = intval(@$record[$this->primaryField]);
            $isinsert = ($rec_ID<1);
            if($isinsert){
                if(!($this->records[$idx]['cmt_OwnerUgrpID']>0)){
                    $this->records[$idx]['cmt_OwnerUgrpID'] = $this->system->get_user_id();
                }
            }
            $this->records[$idx]['cmt_Modified'] = date('Y-m-d H:i:s'); //reset
        }

        return $ret;
        
    }    
    
    //
    // batch action for comments - changing flag for cmt_Deleted
    //
    public function batch_action(){
        
        $recordIDs = prepareIds($this->data['recIDs']);
        if(count($recordIDs)>0){
            //find record by ids  - todo
            
        }
        
        if(!$this->prepareRecords()){
                return false;    
        }
        
        $mysqli = $this->system->get_mysqli();
        
        foreach($this->records as $record){
            
            if($record[$this->primaryField]>0){
                $this->recordIDs[] = $record[$this->primaryField];
            }
                    
            
        }//for comments
        
        
        return true;
    }
    
}
?>
