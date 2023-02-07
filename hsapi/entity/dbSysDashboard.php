<?php

    /**
    * db access to sysDashboard table 
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
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
require_once (dirname(__FILE__).'/../dbaccess/db_files.php');


class DbSysDashboard extends DbEntityBase
{

    /**
    *  search groups
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
        $is_ids_only = false;

        //compose WHERE 
        $where = array();
        $from_table = array($this->config['tableName']);
        
        $pred = $this->searchMgr->getPredicate('dsh_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('dsh_Label');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('dsh_Enabled');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('dsh_ShowIfNoRecords');
        if($pred!=null) array_push($where, $pred);
        
        
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'dsh_ID';
            $is_ids_only = true;
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'dsh_ID,dsh_Label';
          
        }else if(@$this->data['details']=='list' || @$this->data['details']=='full')
        {
            $this->data['details'] = 'dsh_ID,dsh_Order,dsh_Label,dsh_Description,dsh_Enabled,dsh_ShowIfNoRecords,dsh_CommandToRun,dsh_Parameters';
        }else{
            $needCheck = true;
        }
        
        if(!is_array($this->data['details'])){ //user specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
            
        }
        
        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        //----- order by ------------
        //compose ORDER BY 
        $order = array();
        
        $value = @$this->data['sort:dsh_Order'];
        if($value!=null){
            array_push($order, 'dsh_Order '.($value>0?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:dsh_Label'];
            if($value!=null){
                array_push($order, 'dsh_Label ASC');
            }
        }  
        
        //compose query   DISTINCT
        $query = 'SELECT SQL_CALC_FOUND_ROWS '.implode(',', $this->data['details'])
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
    //
    //    
    protected function prepareRecords(){
    
        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){
            if(!@$this->records[$idx]['dsh_Enabled']){
                    $this->records[$idx]['dsh_Enabled'] = 'y';
            }
            if(@$this->records[$idx]['dsh_Order']==null 
                || !($this->records[$idx]['dsh_Order']>0)){
                    $this->records[$idx]['dsh_Order'] = 1;
            }
            if(@$this->records[$idx]['dsh_CommandToRun']=='action-AddRecord' 
                            && @$this->records[$idx]['dsh_ParameterAddRecord']){
                $this->records[$idx]['dsh_Parameters'] = $this->records[$idx]['dsh_ParameterAddRecord'];
            }else if(@$this->records[$idx]['dsh_CommandToRun']=='action-SearchById' 
                            && @$this->records[$idx]['dsh_ParameterSavedSearch']){
                $this->records[$idx]['dsh_Parameters'] = $this->records[$idx]['dsh_ParameterSavedSearch'];
            }
            
            
            
            //validate duplication
            if(@$this->records[$idx]['dsh_Label']){
                $mysqli = $this->system->get_mysqli();
                $res = mysql__select_value($mysqli,
                        "SELECT dsh_ID FROM ".$this->config['tableName']."  WHERE dsh_Label='"
                        .$mysqli->real_escape_string( $this->records[$idx]['dsh_Label'])."'");
                if($res>0 && $res!=@$this->records[$idx]['dsh_ID']){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 
                            'Dashboard entry cannot be saved. The provided name already exists');
                    return false;
                }
            }
        }

        return $ret;
        
    }    
        
    //
    // 
    //
    public function save(){

        $ret = parent::save();
   
        if($ret!==false){

            //treat group image
            foreach($this->records as $record){
                $dsh_ID = @$record['dsh_ID'];
                if($dsh_ID && in_array($dsh_ID, $ret)){
                    $thumb_file_name = @$record['dsh_Image'];
            
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $dsh_ID);
                    }
                }
            }
        }
        
        return $ret;
        
    }     
    
    //
    // delete group
    //
    public function delete($disable_foreign_checks = false){
        
        $ret = parent::delete();

        if($ret){
            
            foreach($this->recordIDs as $recID)  //affected entries
            {
                    $fname = $this->getEntityImagePath($recID);
                    if(file_exists($fname)){
                        unlink($fname);
                    }
            }
        }
        return $ret;
    }

}
?>
