<?php

    /**
    * db access to usrBoomarks table
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


class DbUsrBookmarks extends DbEntityBase
{

    /**
    *  search bookmarks
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
        
        $pred = $this->searchMgr->getPredicate('bkm_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('bkm_UGrpID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('bkm_RecID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('bkm_Rating');
        if($pred!=null) array_push($where, $pred);

        
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'bkm_ID';
            
        }else if(@$this->data['details']=='name' || @$this->data['details']=='list' || @$this->data['details']=='full'){

            $this->data['details'] = 'bkm_ID,bkm_UGrpID,bkm_RecID,bkm_Rating,bkm_PwdReminder,bkm_Notes';
            
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

        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         
         $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $calculatedFields = null;
        
        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName'], $calculatedFields);
        
        return $result;
    }
    
    
    //
    // validate permission for edit/delete bookmark
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_dbowner() && count($this->recordIDs)>0){ //there are records to update/delete
            
            //$ugrs = $this->system->get_user_group_ids();
            $ugrID = $this->system->get_user_id();
            
            $mysqli = $this->system->get_mysqli();
             
            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField, 
                    'bkm_ID in ('.implode(',', $this->recordIDs).') AND bkm_UGrpID!='.$ugrID); //' not in ('.implode(',',$ugrs).')');
            
            
            $cnt = count($recIDs_norights);       
                    
            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED, 
                (($cnt==1 && (!isset($this->records) || count($this->records)==1))
                    ? 'Bookmark belongs'
                    : $cnt.' Bookmark belong')
                    .' to other user. Insufficient rights for this operation'); // or workgroup you are not a member
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
            if($isinsert && !($this->records[$idx]['bkm_UGrpID']>0)){
                $this->records[$idx]['bkm_UGrpID'] = $this->system->get_user_id();
            }
            if($isinsert || !$this->records[$idx]['bkm_Added']){
                $this->records[$idx]['bkm_Added'] = date('Y/m/d H:i:s');
            }
            $this->records[$idx]['bkm_Modified'] = date('Y-m-d H:i:s'); //reset
        }

        return $ret;
        
    }    
    
    //
    //
    //
    public function delete(){

        $this->recordIDs = prepareIds($this->data[$this->primaryField]);  //bookmark ids
        
        $mysqli = $this->system->get_mysqli();
       
        
        $query = 'SELECT count(tag_ID) FROM usrBookmarks, usrTags, usrRecTagLinks where tag_ID=rtl_TagID AND tag_UGrpID='
            .$this->system->get_user_id()
            .' AND rtl_RecID=bkm_RecID AND bkm_ID in (' . implode(',', $this->recordIDs). ')';
                       
        $cnt = mysql__select_value($mysqli, $query);

        if($cnt>0){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 
                    'It is not possible to remove bookmark. Bookmarked record has personal tags');
                return false;
        }
    
        $ret = parent::delete();    
    }
    
    
    //
    // add/remove bookmarks in batch, set rating in batch
    //
    public function batch_action(){

        $is_unbookmark = (@$this->data['mode']=='unbookmark');
        
        $rec_IDs = prepareIds(@$this->data['bkm_RecID']); //these are rec_IDs from Record table
        $bkm_IDs = prepareIds(@$this->data['bkm_ID']);

        if(count($rec_IDs)==0 && count($bkm_IDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }       
        
        $mysqli = $this->system->get_mysqli(); 

        //bookmarks id not defined - find them by record ids         
        if(count($bkm_IDs)==0){   
            $query =  'bkm_RecID in (' . join(',', $rec_IDs).')';
            
            $rec_RecTypeID = @$this->data['rec_RecTypeID'];
            if($rec_RecTypeID>0){
                $query = ', Records where (rec_RecTypeID='.$rec_RecTypeID.') and (rec_ID=bkm_RecID) and '.$query;
            }else{
                $query = ' where '.$query;
            }
            
            //get bookmarks
            $query = 'select bkm_ID from usrBookmarks '.$query
                    . ' and bkm_UGrpID = ' . $this->system->get_user_id();
            
            $bkm_IDs = mysql__select_list2($mysqli, $query);
        }
            
            
        if($is_unbookmark){ 
            //remove bookmarks and detach personal tags
            
            if(count($bkm_IDs)>0){  

                $keep_autocommit = mysql__begin_transaction($mysqli);            
            
                $query = 'DELETE usrRecTagLinks FROM usrBookmarks LEFT JOIN usrRecTagLinks ON rtl_RecID=bkm_RecID '
                .' WHERE bkm_ID IN ('.implode(',', $bkm_IDs).') AND bkm_UGrpID=' .$this->system->get_user_id();
                $res = $mysqli->query($query);
                if(!$res){
                    $mysqli->rollback();
                    if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                    
                    $this->system->addError(HEURIST_DB_ERROR,"Cannot detach personal tags from records", $mysqli->error );
                    return false;
                }
                $res_tag_removed = $mysqli->affected_rows; 

                $query = 'DELETE FROM usrBookmarks '
                .' WHERE bkm_ID in ('.implode(',', $bkm_IDs).') and bkm_UGrpID=' .$this->system->get_user_id();
                $res = $mysqli->query($query);
                if(!$res){
                    $mysqli->rollback();
                    if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                    
                    $this->system->addError(HEURIST_DB_ERROR,"Cannot remove bookmarks", $mysqli->error );
                    return false;
                }
                $res_bookmark_removed = $mysqli->affected_rows; 

                return array('processed'=>count($bkm_IDs),
                             'tag_detached'=>$res_tag_removed, 
                             'deleted'=>$res_bookmark_removed);
                             
            }else{
                $this->system->addError(HEURIST_NOT_FOUND, 
                    'None of the record(s) is/are bookmarked. Nothing to unbookmark');
                return false;
            }
            
        }else{
            //set rating
            
            $rating = intval(@$this->data['rating']);
            if(!($rating>=0 && $rating<6)){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Rating is out of range (0~5)');
                return false;
            }
            
            if(count($bkm_IDs)>0){            
                $query =  'bkm_ID in (' . join(',', $bkm_IDs).')';
            
                $query =  'update usrBookmarks set bkm_Rating = ' . $rating . ' where '.$query 
                        .' and bkm_UGrpID = ' . $this->system->get_user_id();

                $res = $mysqli->query($query);
                if(!$res){
                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot set rating', $mysqli->error);
                    return false;
                }
            
                $update_count = $mysqli->affected_rows;
                return array('processed'=>count($bkm_IDs), 
                             'updated'=>$update_count);
                             
            }else{
                $this->system->addError(HEURIST_NOT_FOUND, 
                    'Rating can be set for bookmarked records only. None of provided are bookmarked');
                return false;
            }
        }
        
    }
}
?>
