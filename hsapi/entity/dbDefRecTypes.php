<?php

    /**
    * db access to defRecTypes table 
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


class DbDefRecTypes extends DbEntityBase
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
        
        $needCount = false; //find usage by records
        $needCheck = false;
        
        //compose WHERE 
        $where = array();
        $from_table = array($this->config['tableName']);
        
        $pred = $this->searchMgr->getPredicate('rty_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rty_Name');
        if($pred!=null) array_push($where, $pred);
        
        //find rectype belong to group
        $pred = $this->searchMgr->getPredicate('rty_RecTypeGroupID');
        if($pred!=null) array_push($where, $pred);
        
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'rty_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'rty_ID,rty_Name';

        }else if(@$this->data['details']=='count'){
            
            $this->data['details'] = 'rty_ID,rty_Name';
            $needCount = true;
            
        }else if(@$this->data['details']=='list'){
            
            $this->data['details'] = 'rty_ID,rty_Name,rty_Description,rty_ShowInLists,rty_RecTypeGroupID';
            //$needCount = true;  //need count only for all groups
            
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = 'rty_ID,rty_Name,rty_OrderInGroup,rty_Description,rty_TitleMask,rty_Plural,'
            .'rty_Status,rty_OriginatingDBID,rty_IDInOriginatingDB,rty_ShowInLists,rty_RecTypeGroupID,rty_ReferenceURL,'
            .'rty_ShowURLOnEditForm,rty_ShowDescriptionOnEditForm,rty_Modified';
            
            //$needCount = true;  //need count only for all groups
            
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
        
        $value = @$this->data['sort:rty_Modified'];
        if($value!=null){
            array_push($order, 'rty_Modified '.($value>0?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:rty_Name'];
            if($value!=null){
                array_push($order, 'rty_Name '.($value>0?'ASC':'DESC'));
            }else{
                $value = @$this->data['sort:rty_ID'];
                if($value!=null){
                    array_push($order, 'rty_ID '.($value>0?'ASC':'DESC'));
                }
            }
        }  
         
        if($needCount){ //find count of groups where given user is a memmber   
            array_push($this->data['details'],
                '(select count(rec_ID) from Records where (rec_RecTypeID=rty_ID)) as rty_Usage');
        }
        
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '.implode(',', $this->data['details'])
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
    // validate permission for edit record type
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_admin() && count($this->recordIDs)>0){ //there are records to update/delete
            
            $this->system->addError(HEURIST_REQUEST_DENIED, 
                    'You are not admin and can\'t edit record types. Insufficient rights for this operation');
                return false;
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

            $this->records[$idx]['rty_Modified'] = null; //reset

            //validate duplication
            $mysqli = $this->system->get_mysqli();
            $res = mysql__select_value($mysqli,
                    "SELECT rty_ID FROM defRecTypes  WHERE rty_Name='"
                    .$mysqli->real_escape_string( $this->records[$idx]['rty_Name'])."'");
            if($res>0 && $res!=@$this->records[$idx]['rty_ID']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Record type cannot be saved. The provided name already exists');
                return false;
            }

            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['rty_ID']>0));
            
        }
        
        return $ret;
        
    }    

    //      
    //
    //
    public function save(){


        $ret = parent::save();

       
        if($ret!==false){
            
            foreach($this->records as $idx=>$record){
                $rty_ID = @$record['rty_ID'];
                if($rty_ID>0 && in_array($rty_ID, $ret)){
                    
                    //treat thumbnail
                    $thumb_file_name = @$record['rty_Thumb'];
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $rty_ID, 'thumbnail');
                    }
                    
                    //treat icon
                    $icon_file_name = @$record['rty_Icon'];
                    //rename it to recID.png
                    if($icon_file_name){
                        parent::renameEntityImage($icon_file_name, $rty_ID, 'icon');
                    }
                    
                }
            }
        }        
        return $ret;
    }  
            
    //
    //
    //
    public function delete(){
         //@todo
    }
    
    //
    // batch action for rectypes
    // 1) import rectype from another db
    //
    public function batch_action(){
         //@todo
    }    
    
    //
    //
    //
    public function counts(){

        $res = null;
                
        if(@$this->data['mode']=='record_count'){
            $query = 'SELECT d.rty_ID, count(r.rec_ID) FROM defRecTypes d '
            .'LEFT OUTER JOIN Records r ON r.rec_RectypeID=d.rty_ID AND r.rec_FlagTemporary=0'
            .' GROUP BY d.rty_ID';
          
           $res = mysql__select_assoc2($this->system->get_mysqli(), $query);
        }
        
        return $res;
    }    
}
?>
