<?php

    /**
    * db access to usrTags table
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


class DbUsrTags extends DbEntityBase
{

    /**
    *  search tags
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

        //fields - from configuration - list of field names
        //data - from request - values

        //if usergroup is not defined search for user groups of current user
        if(!@$this->data['tag_UGrpID']){
            $this->data['tag_UGrpID'] = $this->system->get_user_group_ids();
        }
        
        if(parent::search()===false){
              return false;   
        }
        
        $needCount = false;
        $needCheck = false;
        
        //compose WHERE 
        $where = array();
        $from_table = array($this->config['tableName']);
        
        $pred = $this->searchMgr->getPredicate('tag_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('tag_Text');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('tag_Modified');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('tag_UGrpID', true);
        if($pred!=null) {
            array_push($where, $pred);   
        }

        
        $value = @$this->data['rtl_RecID'];
        if($value>0){
            array_push($where, '(rtl_TagID=tag_ID and rtl_RecID='.$value.')');
            array_push($from_table, 'usrRecTagLinks');
        }

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'tag_ID';

        }else if(@$this->data['details']=='label'){
            
            $this->data['details'] = 'tag_ID,tag_Text';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'tag_ID,tag_Text,tag_UGrpID';
            
        }else if(@$this->data['details']=='list' || @$this->data['details']=='full'){

            $this->data['details'] = 'tag_ID,tag_Text,tag_Description,tag_Modified,tag_UGrpID';
            $needCount = true;
            
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
        
        //$pred = $this->searchMgr->getSortPredicate('ulf_UploaderUGrpID');
        //if($pred!=null) array_push($order, $pred);
        $value = @$this->data['sort:tag_Modified'];
        if($value!=null){
            array_push($order, 'tag_Modified '.($value>1?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:tag_Usage'];
            if($value!=null){
                array_push($order, 'tag_Usage '.($value>1?'ASC':'DESC'));
                $needCount = true;
            }else{
                $value = @$this->data['sort:tag_Text'];
                if($value!=null){
                    array_push($order, 'tag_Text '.($value>1?'ASC':'DESC'));
                }
            }
        }           
        
        if($needCount){    
            array_push($this->data['details'],'(select count(*) from usrRecTagLinks where (tag_ID=rtl_TagID)) as tag_Usage');
            //array_push($where, "(tag_ID=rtl_TagID)");
            //array_push($from_table, 'usrRecTagLinks');
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
        
        if(!$this->system->is_dbowner() && count($this->recordIDs)>0){ //there are tags to update/delete
            
            $ugrs = $this->system->get_user_group_ids();
            
            $mysqli = $this->system->get_mysqli();
             
            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField, 
                    'tag_ID in ('.implode(',', $this->recordIDs).') AND tag_UGrpID not in ('.implode(',',$ugrs).')');
            
            
            $cnt = count($recIDs_norights);       
                    
            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED, 
                (($cnt==1 && (!isset($this->records) || count($this->records)==1))
                    ? 'Tag belongs'
                    : $cnt.' tags belong')
                    .' to other user or workgroup you are not a member. Insufficient rights for this operation');
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
            if($isinsert && !($this->records[$idx]['tag_UGrpID']>0)){
                $this->records[$idx]['tag_UGrpID'] = $this->system->get_user_id();
            }
            $this->records[$idx]['tag_Modified'] = date('Y-m-d H:i:s'); //reset
        }

        return $ret;
        
    }    

    /**
    * 1. exclude non numeric
    * 2. find wrong permission   
    * 3. find in use 
    * 
    * @returns  array of 'deleted', 'no enough right' and 'in use' ids
    */
    public function delete(){
        
        $this->recordIDs = prepareIds($this->data[$this->primaryField]);

        if(count($this->recordIDs)>0){
            
            $mysqli = $this->system->get_mysqli();
            
            $recIDs_inuse = mysql__select_list2($mysqli, 'SELECT DISTINCT rtl_RecID '
                        .'FROM usrRecTagLinks WHERE rtl_TagID in ('.implode(',', $this->recordIDs).')');
            $cnt = count($recIDs_inuse);       
                        
            if($cnt>0){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 
                (($cnt==1 && count($this->records)==1)
                ? 'There is a record'
                : 'There are '.$cnt.' records')
                .' with this tag.<br>You must delete the record(s)'
                .' or remove the tag in order to be able to delete the tag.<br><br>'
                .'<a href="#" onclick="window.open(\''
                . HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&q=ids:'.implode(',', $recIDs_inuse)
                .'\',\'_blank\')">Open records in search</a> to allow deletion or removal of this tag');
                return false;
            }
        }

        return parent::delete();
    }
    
    //
    //  Replace one or several tags ($this->recordIDs) to new ONE ($this->newTagID)
    //
    private function replaceTags(){
        
        $ret = false;
        
        if(count($this->recordIDs)>0 && count($this->newTagID)>0){
        
            $newTagID = $this->newTagID[0];
            
            $update_query = 'UPDATE IGNORE usrRecTagLinks set rtl_TagID = '.$newTagID.' WHERE rtl_TagID in ('
                 . implode(',', $this->recordIDs) . ')';
                 
            $mysqli = $this->system->get_mysqli();

            $res = $mysqli->query($update_query);
            if(!$res){
                $this->system->addError(HEURIST_DB_ERROR, 'Cannot replace tags', $mysqli->error );
            }else{
                $ret = true;
                if(@$this->data['removeOld']==1){
                    $ret = parent::delete();
                }
                if($ret){
                    //calculate new usage
                    $query = 'SELECT COUNT(*) FROM usrRecTagLinks WHERE rtl_TagID = '.$newTagID;
                    $ret = mysql__select_value($mysqli, $query);
                    if($ret==null){
                        $this->system->addError(HEURIST_DB_ERROR, 'Cannot find tag usage', $mysqli->error );
                        $ret = false;
                    }
                }
            }
            
        }else{
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of tag identificators');    
        }
        
        return $ret;
    }
    
    //
    // batch actions for tags 
    //  - see table usrRecTagLinks
    //
    // parameter mode 
    // A) replace all for set of records (recIDs) - remove all old tags and replace with new set (tagIDs) 
    // B) assign tags (tagIDs)  to records (recIDs) 
    // C) remove tags (tagIDs)  to records (recIDs) 
    //
    // D) replace several old tags (tagIDs) to new ONE (newTagID) see $this->replaceTags()
    //
    public function batch_action(){
        
        //tags ids
        $this->recordIDs = prepareIds($this->data['tagIDs']);
        if(count($this->recordIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of tag identificators');
            return false;
        }

        // MODE D  replace several old tags (tagIDs) to new ONE
        $this->newTagID = prepareIds(@$this->data['newTagID']);
        if(count($this->newTagID)>0){             
            return $this->replaceTags();   
        }

        if(!$this->_validatePermission()){ //check that all tags belongs to current user
            return false;
        }
        
        //record ids
        $assignIDs = prepareIds($this->data['recIDs']);
        if(count($assignIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of record identificators');
            return false;
        }

        $res_tag_added = 0; //tags assigned
        $res_tag_removed = 0; //tags removed
        $res_bookmarks = 0; //new bookmarks
        
        $mysqli = $this->system->get_mysqli();
        
        //narrow by record type
        $rec_RecTypeID = @$this->data['rec_RecTypeID'];
        if($rec_RecTypeID>0){ 
            $assignIDs = mysql__select_list2($mysqli, 'SELECT rec_ID from Records where rec_ID in ('
                .implode(',', $assignIDs).') and rec_RecTypeID='. $rec_RecTypeID);
                
            if($assignIDs==null || count($assignIDs)==0){             
                $this->system->addError(HEURIST_NOT_FOUND, 'No record found for provided record type');
                return false;
            }
        }
        

        $keep_autocommit = mysql__begin_transaction($mysqli);
        
        $mode = @$this->data['mode'];
        
        if($mode=='replace'){
        
            // detach/remove all assignments for given records        
            $query = 'DELETE usrRecTagLinks FROM usrRecTagLinks'
                . ' WHERE rtl_RecID in (' . implode(',', $assignIDs) . ')';
            $res = $mysqli->query($query);
            if(!$res){
                $mysqli->rollback();
                if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                
                $this->system->addError(HEURIST_DB_ERROR,"Cannot detach tags from records", $mysqli->error );
                return false;
            }
            $res_tag_removed = $mysqli->affected_rows; 
        
        }else if($mode=='remove'){
            
            // detach/remove all assignments for given records        
            $query = 'DELETE usrRecTagLinks FROM usrRecTagLinks'
                . ' WHERE rtl_TagID in (' . implode(',', $this->recordIDs) 
                . ') and rtl_RecID in (' . implode(',', $assignIDs) . ')';
            $res = $mysqli->query($query);
            if(!$res){
                $mysqli->rollback();
                if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                
                $this->system->addError(HEURIST_DB_ERROR,"Cannot detach tags from records", $mysqli->error );
                return false;
            }
            $res_tag_removed = $mysqli->affected_rows; 

            
        }else { //assign by default
            $mode=='assign';
        }
        
        //create new assignments
        if($mode!='remove'){
            
            $insert_query = 'INSERT IGNORE INTO usrRecTagLinks (rtl_RecID, rtl_TagID) '
                . 'SELECT rec_ID, tag_ID FROM usrTags, Records '
                . ' WHERE rec_ID in (' . implode(',', $assignIDs) . ') '
                . ' AND tag_ID in (' . implode(',', $this->recordIDs) . ')';

            $res = $mysqli->query($insert_query);
            if(!$res){
                $mysqli->rollback();
                if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                
                $this->system->addError(HEURIST_DB_ERROR,"Cannot assign tags", $mysqli->error );
                return false;
            }
            $res_tag_added = $mysqli->affected_rows; 
        
            //if at least one tag is private
            //add bookmarks if tags are private and record is not bookmarked yet
            $ugrID = $this->system->get_user_id();
            
            if(null != mysql__select_value($mysqli, 'SELECT tag_ID from usrTags where tag_ID in (' 
                . implode(',', $this->recordIDs) . ') AND tag_UGrpID ='.$ugrID.' LIMIT 1')){
            
                $insert_query = 'INSERT INTO usrBookmarks '
                    .' (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)'
                    .' SELECT ' . $ugrID . ', now(), now(), rec_ID FROM Records '
                    .' LEFT JOIN usrBookmarks ON bkm_recID=rec_ID AND bkm_UGrpID='.$ugrID
                    .' WHERE bkm_ID IS NULL AND rec_ID IN (' . implode(',', $assignIDs) . ')';
            
                $res = $mysqli->query($insert_query);
                if(!$res){
                    $mysqli->rollback();
                    if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                    
                    $this->system->addError(HEURIST_DB_ERROR,"Cannot create bookmarks", $mysqli->error );
                    return false;
                }
                $res_bookmarks = $mysqli->affected_rows;
            }
        }
        
        //commit
        $mysqli->commit();
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);
        
        return array('processed'=>count($assignIDs), //afffected records
                'added'=>$res_tag_added, //tags assigned
                'removed'=>$res_tag_removed, //tags removed
                'bookmarks'=>$res_bookmarks); //new bookmarks
        
    }
    
}
?>
