<?php

    /**
    * db access to defTerms table
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


class DbDefTerms extends DbEntityBase
{
    private $records_all = null;
    private $labels_to_idx = null;
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
        
        if(parent::search()===false){
              return false;   
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
         $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();
        
        $res = $this->searchMgr->execute($query, $is_ids_only, 'defTerms');
        return $res;

    }

    //
    // trm_Label may have periods. Periods are taken as indicators of hierarchy.
    //
    private function saveHierarchy(){
      
        //extract records from $_REQUEST data 
        if(!$this->prepareRecords()){
                return false;    
        }
        
        //create tree array $record['trm_ParentTermID']
        if(count($this->records)>0){
            //group by parent term ID
            $records_by_prent_id = array();
            foreach($this->records as $idx => $record){
                if(!@$records_by_prent_id[$record['trm_ParentTermID']]){
                    $records_by_prent_id[$record['trm_ParentTermID']] = array();
                }
                $records_by_prent_id[$record['trm_ParentTermID']][] = $record;
            }
            
            $terms_added = array();
            
            foreach($records_by_prent_id as $parentID => $records){
            
                //root, children are record idx
                $this->records_all = array();
                
                $this->labels_to_idx = array(); //term label to records_all index
                
                //label->array(labels)
                $tree = $this->parseHierarchy( $records );

                //keep index
                foreach($records as $record_idx => $record){
                    $this->labels_to_idx[$record['trm_Label']] = $record_idx;
                }
                $this->records_all = $records;    
                
                $ret = $this->saveTree($tree, $parentID, '');
                if($ret===false){
                    return false;
                }
                if(is_array($ret))
                    $terms_added = array_merge($terms_added, $ret);
            }
        }
        return $terms_added;
    }
    
    private function parseHierarchy($input) {
        $result = array();

        foreach ($input AS $path) {
            $path = $path['trm_Label'];
            
            $prev = &$result;

            $s = strtok($path, '.');
            //iterate path
            while (($next = strtok('.')) !== false) {
                if (!isset($prev[$s])) {
                    $prev[$s] = array();
                }

                $prev = &$prev[$s];
                $s = $next;
            }
            if (!isset($prev[$s])) {
                $prev[$s] = array();
            }

            unset($prev);
        }
        return $result;
    }    

    //
    // tree: idx->array(idx->array(),.... )
    //
    private function saveTree($tree, $parentID, $parentLabel){

        //reset array of record for save        
        $this->records = array();
        
        $mysqli = $this->system->get_mysqli();
        
        //fill records array
        foreach($tree as $label => $children)
        {
            $record_idx = @$this->labels_to_idx[$parentLabel.$label];
            if($record_idx==null){ //one of parent terms not defined - add it
                $record_idx = count($this->records_all);
                $this->labels_to_idx[$parentLabel.$label] = $record_idx;
                $this->records_all[] = array();
            }
            
            $this->records_all[$record_idx]['trm_ParentTermID'] = $parentID;
            $this->records_all[$record_idx]['trm_Label'] = $label;
            $this->records_all[$record_idx]['trm_Domain'] = $this->records_all[0]['trm_Domain'];
            
            $record = $this->records_all[$record_idx];
            
            //check for term with the same name for this parent
            if(@$record['trm_ID']>0){
                //already exists
                continue;
            }else{
                $query = 'select trm_ID from defTerms where trm_ParentTermID='
                        .$parentID.' and trm_Label="'.$label.'"';    
                $trmID = mysql__select_value($mysqli, $query);
                if($trmID>0){
                    //already exists
                    $this->records_all[$record_idx]['trm_ID'] = $trmID;
                    continue;
                }
            }
            
            $this->records[$record_idx] = $record;
        }
        
        $terms_added = array();
        
        if(count($this->records)>0){
            $ret = $this->save();
            if($ret!==false) {
                $terms_added = $ret;
            }
        }else{
            $ret = true; //all terms already in db
        }
        
        if($ret!==false){
            //assign recID from records to records_all
            foreach($this->records as $record_idx => $record){
                //$this->primaryField
                $this->records_all[$record_idx]['trm_ID'] = $record['trm_ID'];
            }
            
            //go to next level
            foreach($tree as $label => $children)
            {
                if(count($children)>0){
                    $record_idx = @$this->labels_to_idx[$parentLabel.$label];
                    $ret = $this->saveTree($children, $this->records_all[$record_idx]['trm_ID'], $parentLabel.$label.'.');
                    if($ret===false){
                        return false;
                    }
                    if(is_array($ret))
                        $terms_added = array_merge($terms_added, $ret);
                }
            }            
            return $terms_added;
        }else{
            return false;
        }
        
    }
    
    //
    // returns array of saved record ids or false
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
    
    public function batch_action(){

            $mysqli = $this->system->get_mysqli();        
        
            $this->need_transaction = false;
        
            $keep_autocommit = mysql__begin_transaction($mysqli);
        
            $ret = $this->saveHierarchy();
        
            if($ret===false){
                $mysqli->rollback();
            }else{
                $mysqli->commit();    
            }
            
            if($keep_autocommit===true) $mysqli->autocommit(TRUE);
        
            return $ret;
    }
    
    
}
?>
