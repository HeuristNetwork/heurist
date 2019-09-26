<?php

    /**
    * db access to defFileExtToMimetype table
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


class DbDefFileExtToMimetype extends DbEntityBase
{
    /*
    'trm_OriginatingDBID'=>'int',
    'trm_NameInOriginatingDB'=>63,
    'trm_IDInOriginatingDB'=>'int',

    'trm_AddedByImport'=>'bool2',
    'trm_IsLocalExtension'=>'bool2',

    'trm_OntID'=>'int',
    'trm_ChildCount'=>'int',
    
    'trm_Depth'=>'int',
    'trm_LocallyModified'=>'bool2',
    */

    /**
    *  search user or/and groups
    * 
    *  sysUGrps.ugr_ID
    *  sysUGrps.ugr_Type
    *  sysUGrps.ugr_Name
    *  sysUGrps.ugr_Enabled
    *  sysUGrps.ugr_Modified
    *  sysUsrGrpLinks.ugl_UserID
    *  sysUsrGrpLinks.ugl_GroupID
    *  sysUsrGrpLinks.ugl_Role
    *  (omit table name)
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

        //compose WHERE 
        $where = array();    
        
        $pred = $this->searchMgr->getPredicate('fxm_Extension');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('fxm_MimeType');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('fxm_FiletypeName');
        if($pred!=null) array_push($where, $pred);

       
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'fxm_Extension';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'fxm_Extension,fxm_MimeType';
            
        }else if(@$this->data['details']=='list' || @$this->data['details']=='full'){
            
            $this->data['details'] = implode(',', array_keys($this->fields) );
        }
        
        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }
        
        //validate names of fields
        foreach($this->data['details'] as $fieldname){
            if(!@$this->fields[$fieldname]){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                return false;
            }            
        }

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('fxm_Extension', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'], 'fxm_Extension');
        }
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '.implode(',', $this->data['details'])
                .' FROM defFileExtToMimetype';

         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         
         $query = $query.' ORDER BY fxm_Extension ';
         
         $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();
        
        $res = $this->searchMgr->execute($query, $is_ids_only, 'defFileExtToMimetype');
        return $res;

    }
    
    protected function _validatePermission(){
        if($this->system->is_admin()){
            return true;            
        }else{
            $this->system->addError(HEURIST_REQUEST_DENIED, 
                    'Insufficient rights. You have to be Administrator of group \'Database Managers\' for this operation');
            return false;            
        }
    }
    
    //
    //
    //
    public function save(){
        
        $ret = parent::save();

        /* @todo fo icon and placeholder
        if($ret!==false){
            //treat thumbnail image
            foreach($this->records as $record){
                if(in_array(@$record['trm_ID'], $ret)){
                    $thumb_file_name = @$record['trm_Thumb'];
            
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $record['trm_ID']);
                    }
                }
            }
        }
        */
        
        return $ret;
    } 
    
    //
    // since in this table primary key is varchar need special treatment
    //
    public function delete(){

        if(!$this->_validatePermission()){
            return false;
        }
        $ret = null;

        $rec_ID = $this->data[$this->primaryField];
        if($rec_ID!=null){
            $query = "DELETE from ".$this->config['tableName']." WHERE ".$this->primaryField." = '".$rec_ID."'";
            
            $mysqli = $this->system->get_mysqli();
            $res = $mysqli->query($query);
            
            if(!$res){
                $ret = $mysqli->error;
                $this->system->addError(HEURIST_INVALID_REQUEST,
                                 "Cannot delete from table ".$this->config['entityName'], $mysqli->error);
                return false;
            }else{
                $ret = true;
            }
        }else{
            $this->system->addError(HEURIST_INVALID_REQUEST, 
                                 "Cannot delete from table ".$this->config['entityName'], 
                                 'Record ID provided is wrong value');
            return false;
        }
        return $ret;
    }    
    
}
?>
