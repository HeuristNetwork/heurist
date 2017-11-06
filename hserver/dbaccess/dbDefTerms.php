<?php

    /**
    * db access to defTerms table
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
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


class DbDefTerms extends DbEntityBase
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


        if(@$this->data['withimages']==1){
             $ids = $this->data['trm_ID'];
             $lib_dir = HEURIST_FILESTORE_DIR . 'term-images/';
             $files = array();
             foreach ($ids as $id){
                $filename = $lib_dir.$id.'.png';
                if(file_exists($filename)){
                    array_push($files, $id);
                }
             }
             if(count($files)==0){
                $this->data['trm_ID'] = 999999999; 
             }else{
                $this->data['trm_ID'] = $files;    
             }
             
        }
        
//error_log(print_r($this->data,true));        
        $this->searchMgr = new dbEntitySearch( $this->system, $this->fields);

        /*
        if (!(@$this->data['val'] || @$this->data['geo'] || @$this->data['ulfID'])){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed");
            return false;
        }
        
        if(!$this->_validateParamsAndCounts()){
            return false;
        }else if (count(@$this->recIDs)==0){
            return $this->result_data;
        }
        */
        $res = $this->searchMgr->validateParams( $this->data );
        if(!is_bool($res)){
            $this->data = $res;
        }else{
            if(!$res) return false;        
        }        
        
        
        //compose WHERE 
        $where = array();    
        
        $pred = $this->searchMgr->getPredicate('trm_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('trm_Label');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('trm_Domain');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('trm_Status');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('trm_Modified');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('trm_Code');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('trm_ParentTermID');
        if($pred!=null) array_push($where, $pred);

       
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'trm_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'trm_ID,trm_Label';
            
        }else if(@$this->data['details']=='list'){

            $this->data['details'] = 'trm_ID,trm_Label,trm_InverseTermId,trm_Description,trm_Domain,trm_ParentTermID,trm_Code,trm_Status';
            
        }else if(@$this->data['details']=='full'){

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
        $idx = array_search('trm_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'], 'trm_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT '.implode(',', $this->data['details']).' FROM defTerms';

         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         $query = $query.$this->searchMgr->getOffset()
                        .$this->searchMgr->getLimit();
        
        $res = $this->searchMgr->execute($query, $is_ids_only, 'defTerms');
        return $res;

    }
    
    //
    //
    //
    public function save(){
        
        $ret = parent::save();

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
        
        return $ret;
    } 
    
}
?>
