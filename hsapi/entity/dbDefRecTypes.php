<?php

    /**
    * db access to defRecTypes table 
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


class DbDefRecTypes extends DbEntityBase
{
    private $where_for_count = null;
    private $rty_counts = null;

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

        $calculatedFields = null;
        
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

        if(@$this->data['details']==null) $this->data['details'] = 'full';

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'rty_ID';

        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'rty_ID,rty_Name';

        }else if(@$this->data['details']=='count'){

            $this->data['details'] = 'rty_ID,rty_Name';
            $needCount = true;

        }else if(@$this->data['details']=='list'){

            $this->data['details'] = 'rty_ID,rty_Name,rty_Description,rty_ShowInLists,rty_Status,rty_RecTypeGroupID';
            //$needCount = true;  //need count only for all groups

        }else if(@$this->data['details']=='full'){

            $this->data['details'] = 'rty_ID,rty_Name,rty_OrderInGroup,rty_Description,rty_TitleMask,'
            .'IF((rty_Plural IS NULL OR rty_Plural=\'\'),rty_Name,rty_Plural) as rty_Plural,'
            .'rty_Status,rty_OriginatingDBID,rty_IDInOriginatingDB,rty_ShowInLists,rty_RecTypeGroupID,rty_ReferenceURL,'
            .'rty_ShowURLOnEditForm,rty_ShowDescriptionOnEditForm,rty_Modified';

            $needCount = false; //now we calculate counts beforehand and use $calculatedFields to add column rty_RecCount 

            $usr_ID = $this->system->get_user_id();

            $this->where_for_count = array();
            if(($usr_ID>0) || ($usr_ID===0)){
                $conds = $this->_getRecordOwnerConditions($usr_ID);
                $this->where_for_count[0] = $conds[0];
                $this->where_for_count[1] =  ' AND '.$conds[1];      
            }else{
                $this->where_for_count[0] = '';
                $this->where_for_count[1] = 'AND (not r0.rec_FlagTemporary)';
            }
            
            $query2 = 'SELECT rec_RecTypeID,count(*) FROM Records WHERE (not rec_FlagTemporary) GROUP BY rec_RecTypeID';
            $this->rty_counts = mysql__select_assoc2($this->system->get_mysqli(), $query2);
            
        
            $calculatedFields = function ($fields, $row=null) {

                if($row==null){
                    array_push($fields, 'rty_CanonicalTitleMask');
                    array_push($fields, 'rty_RecCount');
                    return $fields;   
                }else{

                    $idx = array_search('rty_TitleMask', $fields);
                    if($idx!==false){
                        $fileid = $row[$idx]; 
                        
                        $mask_concept_codes = $row[$idx];
                        array_push($row, $mask_concept_codes); //keep
                        //convert to human readable
                        $row[$idx] = TitleMask::execute($mask_concept_codes, $row[0], 2, null, _ERR_REP_SILENT);
                    }else{
                        array_push($row, '');
                    }
                    
                    $idx = array_search('rty_RecCount', $fields);
                    if($idx!==false){
                        
                            if(@$this->rty_counts[$row[0]]>0){
                                $cnt = $this->rty_counts[$row[0]];
                            }else{
                                $cnt = 0;
                            }
                            array_push($row, $cnt);
                    }else{
                        array_push($row, '');
                    }
                    

                    return $row;
                }
            };

            
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


        if($needCount){ //find count of records by rectype

            $query2 = 'SELECT count(r0.rec_ID) from Records r0 ';
            $where2 = ' WHERE (r0.rec_RecTypeID=rty_ID) ';

            $usr_ID = $this->system->get_user_id();

            if(($usr_ID>0) || ($usr_ID===0)){
                $conds = $this->_getRecordOwnerConditions($usr_ID);
                $query2 = $query2 . $conds[0];
                $where2 = $where2 . ' AND '.$conds[1];      
            }else{  
                $where2 = $where2 . 'AND (not r0.rec_FlagTemporary)';
            }

            array_push($this->data['details'], '('.$query2.$where2.') as rty_RecCount');

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

        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName'], $calculatedFields);

        return $result;
    }

    //
    //
    //
    public function delete($disable_foreign_checks = false){

        $this->recordIDs = prepareIds($this->data[$this->primaryField]);

        if(count($this->recordIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid record type identificator');
            return false;
        }        
        if(count($this->recordIDs)>1){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'It is not possible to remove record types in batch');
            return false;
        }        
        
        $rtyID = $this->recordIDs[0];
        
        $mysqli = $this->system->get_mysqli();
        
        $query = 'SELECT dty_ID, dty_Name FROM defDetailTypes where FIND_IN_SET('.$rtyID.', dty_PtrTargetRectypeIDs)>0';
        
        $fields = mysql__select_assoc2($mysqli, $query);
        $dtCount = count($fields);
        
        if ($dtCount>0) { // there are fields that use this rectype, need to return error and the dty_IDs
                $errMsg = "You cannot delete record type $rtyID. "
                            ." It is referenced in $dtCount base field defintions "
                            ."- please delete field definitions or remove rectype from pointer constraints to allow deletion of this record type.<div style='text-align:left'><ul>";
                foreach($fields as $dty_ID => $dty_Name){
                    $errMsg = $errMsg.("<li>".$dty_ID."&nbsp;".$dty_Name."</li>");
                }
                $errMsg= $errMsg."</ul></div>";
                
                $this->system->addError(HEURIST_ACTION_BLOCKED, $errMsg);
                return false;
        }
        
        //-----------
        $query = 'SELECT sys_TreatAsPlaceRefForMapping FROM sysIdentification where 1';
        
        $val = mysql__select_value($mysqli, $query);
        if($val!=null && $val!=''){
                $places = explode(',', $val);
                if (in_array($rtyID, $places)) {
                    $this->system->addError(HEURIST_ACTION_BLOCKED, "You cannot delete record type $rtyID. "
                                ." It is referenced as 'treat as places for mapping' in database properties");
                    return false;
                }
        }
        
        //--------------
        $query = "select rec_ID from Records where rec_RecTypeID=$rtyID and rec_FlagTemporary=0 limit 1";
        $dtCount = mysql__select_value($mysqli, $query);
        
        if($dtCount>0){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 
                "You cannot delete record type $rtyID as it has existing data records");
            return false;
        }
        
        $keep_autocommit = mysql__begin_transaction($mysqli);

        
        //delete temporary records
        $res = true;
        $query = "select rec_ID from Records where rec_RecTypeID=$rtyID and rec_FlagTemporary=1";
        $recIds = mysql__select_list2($mysqli, $query);
        if(count($recIds)>0) {
            $res = recordDelete($this->system, $recIds, false);   
            $res = ($res['status']==HEURIST_OK);
        }

        $query = "DELETE FROM defRecStructure where rst_RecTypeID=$rtyID";
        $ret = $mysqli->query($query);
        $affected = $mysqli->affected_rows;
        if(!$ret){
            $this->system->addError(HEURIST_DB_ERROR, 
                    "Cannot delete from table defRecStructure", $mysqli->error);
            $res = false;
        }else if($affected===0){
            //$this->system->addError(HEURIST_NOT_FOUND, 'Cannot delete structure for rectype. No entries found');
            //$res = false;
        }
       
        
        if($res){
            $res = parent::delete(true);        
        }
        if($res){
            $mysqli->commit();   
        }else{
            $mysqli->rollback();
        }
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);

        return $res;
    }
 
    
    //
    // validate permission for edit record type
    // for delete and assign see appropriate methods
    //    
    protected function _validatePermission(){
        
        if(!$this->system->is_admin() && (count($this->recordIDs)>0 || count($this->records)>0)){ //there are records to update/delete
            
            $this->system->addError(HEURIST_REQUEST_DENIED, 
                    'You are not admin and can\'t edit record types. Insufficient rights (logout/in to refresh) for this operation');
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
        $mysqli = $this->system->get_mysqli();
        
        //add specific field values
        foreach($this->records as $idx=>$record){

            //validate duplication
            if(@$this->records[$idx]['rty_Name']){
                $res = mysql__select_value($mysqli,
                        "SELECT rty_ID FROM defRecTypes  WHERE rty_Name='"
                        .$mysqli->real_escape_string( $this->records[$idx]['rty_Name'])."'");
                if($res>0 && $res!=@$this->records[$idx]['rty_ID']){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Record type cannot be saved. The provided name already exists');
                    return false;
                }
            }

            if(!(@$this->records[$idx]['rty_ID']>0)){
                $this->records[$idx]['rty_LocallyModified'] = 0; //default value for new
                
                if(@$this->records[$idx]['rty_IDInOriginatingDB']==''){
                    $this->records[$idx]['rty_IDInOriginatingDB'] = 0;
                }
                if(@$this->records[$idx]['rty_NonOwnerVisibility']==''){
                    $this->records[$idx]['rty_NonOwnerVisibility'] = 'viewable';
                }
            }else{
                
                if (array_key_exists('rty_IDInOriginatingDB',$this->records[$idx]) 
                     && $this->records[$idx]['rty_IDInOriginatingDB']==''){ 
                         
                    $this->records[$idx]['rty_IDInOriginatingDB'] = null;
                    unset($this->records[$idx]['rty_IDInOriginatingDB']);
                }
                
                if(array_key_exists('rty_LocallyModified',$this->records[$idx])){ 
                    $this->records[$idx]['rty_LocallyModified'] = null;
                    unset($this->records[$idx]['rty_LocallyModified']);
                }
            }
            
            $this->records[$idx]['rty_Modified'] = date('Y-m-d H:i:s'); //reset
            
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
            
            $dbID = $this->system->get_system('sys_dbRegisteredID');
            if(!($dbID>0)) $dbID = 0;
            
            $mysqli = $this->system->get_mysqli();
            
            foreach($this->records as $idx=>$record){
                $rty_ID = @$record['rty_ID'];
                if($rty_ID>0 && in_array($rty_ID, $ret)){
                    
                    $query = null;
                    if($record['is_new']){
                        //1. if new add default set of fields TODO!
                        /*if($isAddDefaultSetOfFields){
                            //add default set of detail types
                            addDefaultFieldForNewRecordType($rtyID, $newfields);
                        }*/
                        
                        //2. set dbid or update modified locally
                        $query= 'UPDATE defRecTypes SET rty_OriginatingDBID='.$dbID
                                .', rty_NameInOriginatingDB=rty_Name'
                                .', rty_IDInOriginatingDB='.$rty_ID
                                .' WHERE (NOT rty_OriginatingDBID>0 OR rty_OriginatingDBID IS NULL) AND rty_ID='.$rty_ID;
                                   
                    }else{
                        $query = 'UPDATE defRecTypes SET rty_LocallyModified=IF(rty_OriginatingDBID>0,1,0)'
                                . ' WHERE rty_ID = '.$rty_ID;
                    }
                    $res = $mysqli->query($query);
                    
                        
                    //3. update titlemask - from names to ids
                    $mask = @$record['rty_TitleMask'];
                    if($mask){
                            $parameters = array("");
                            $val = TitleMask::execute($mask, $rty_ID, 1, null, _ERR_REP_SILENT);//convert from human to coded
                            $parameters = addParam($parameters, "s", $val);

                            $query = "update defRecTypes set rty_TitleMask = ? where rty_ID = $rty_ID";

                            $res = mysql__exec_param_query($mysqli, $query, $parameters, true);
                            if(!is_numeric($res)){
                                $this->system->addError(HEURIST_DB_ERROR, 
                                    'SQL error updating title mask for record type '.$rty_ID, $res);
                            }
                    }
            
            
                    //4. treat thumbnail
                    $thumb_file_name = @$record['rty_Thumb'];
                    //rename it to recID.png and copy to entity/defRecTypes
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $rty_ID, 'thumbnail');
                    }
                    
                    //treat icon
                    $icon_file_name = @$record['rty_Icon'];
                    //rename it to recID.png and copy to entity/defRecTypes
                    if($icon_file_name){
                        parent::renameEntityImage($icon_file_name, $rty_ID, 'icon');
                    }
                    
                    //backward capability - copy icon and thumb to rectype-icons
                    $this->copyIconIntoRectypeIcons($rty_ID);
                    
                }
            }
        }        
        return $ret;
    }  
    
    //
    // backward capability - copy icon and thumb to rectype-icons
    //
    private function copyIconIntoRectypeIcons( $rty_ID ){
        
        $entity_name = $this->config['entityName'];
        $path = HEURIST_FILESTORE_DIR . 'entity/'.$entity_name.'/';
        
        $icon = $path.'icon/'.$rty_ID.'.png';
        if(file_exists( $icon )){
                $new_name = HEURIST_ICON_DIR.$rty_ID.'.png';
                $isSuccess = fileCopy($icon, $new_name);
        }        
        $thumb = $path.'thumbnail/'.$rty_ID.'.png';
        if(file_exists( $thumb )){
                $new_name = HEURIST_ICON_DIR.'thumb/th_'.$rty_ID.'.png';
                $isSuccess = fileCopy($thumb, $new_name);
        }        
    }
            
    //
    // batch action for rectypes
    // 1) import rectype from another db - @todo
    // 2) import rectype from CSV import
    //
    public function batch_action(){

        $mysqli = $this->system->get_mysqli();

        $this->need_transaction = false;
        $keep_autocommit = mysql__begin_transaction($mysqli);

        $ret = true;

        if(@$this->data['csv_import']){ // import new rectypes via CSV

            if(@$this->data['fields'] && is_string($this->data['fields'])){ // new to perform extra validations first
                $this->data['fields'] = json_decode($this->data['fields'], true);
            }

            if(count($this->data['fields'])>0){

                $ret = array();
                foreach($this->data['fields'] as $idx => $record){

                    $ret[$idx] = array();
                    if(empty(@$record['rty_Name'])){ // check that a name has been provided
                        $ret[$idx][] = 'A record type name is required';
                    }else{ // check that the name hasn't been used yet
                        $exists = mysql__select_value($mysqli, 'SELECT rty_ID FROM defRecTypes WHERE rty_Name="'. $record['rty_Name'] .'"');
                        if($exists){
                            $ret[$idx][] = $record['rty_Name'] . ' is already in use by record type ID#' . $exists;
                        }
                    }
                    if(empty(@$record['rty_Description'])){ // check that a description has been provided
                        $ret[$idx][] = 'A record type description is required';
                    }

                    if(count($ret[$idx]) != 0){ // has error

                        unset($this->data['fields'][$idx]);
                        $ret[$idx] = implode(' ', $ret[$idx]);
                    }else{

                        $ret[$idx] = '';

                        if(!array_key_exists($this->data['fields'][$idx], 'rty_Plural')){ // add plural
                            $this->data['fields'][$idx]['rty_Plural'] = $record['rty_Name'] . 's';
                        }
                        if(!array_key_exists($this->data['fields'][$idx], 'rty_TitleMask')){ // add default title mask
                            $this->data['fields'][$idx]['rty_TitleMask'] = 'Please edit any <b>' . $record['rty_Name'] . '</b> record to choose fields for the constructed title';
                        }
                        if(!array_key_exists($this->data['fields'][$idx], 'rty_RecTypeGroupID') && isset($this->data['rtg_ID'])){ // add rectype group
                            $this->data['fields'][$idx]['rty_RecTypeGroupID'] = $this->data['rtg_ID']; error_log(print_r($this->data['fields'][$idx], TRUE));
                        }
                    }
                }

                $idx_to_do = array_keys($this->data['fields']);
                $this->data['fields'] = array_values($this->data['fields']); // re-write indexes

                $result = true;
                if(count($this->data['fields']) > 0){ // ensure there are still rectypes to define
                    $result = $this->save();
                }

                if(!$result){
                    $ret = false;
                }else if(count($idx_to_do) > 0){ // check if success messages need to be added

                    $i = 0;
                    foreach ($idx_to_do as $idx){
                        if(strlen($ret[$idx]) > 0){
                            continue;
                        }

                        $ret[$idx] = 'Created ID#'.$this->records[$i]['rty_ID'];
                        $i++;
                    }
                }
            }else{
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'No import data has been provided. Ensure that you have enter the necessary CSV rows.<br>Please contact the Heurist team if this problem persists.');
            }
        }

        if($ret===false){
            $mysqli->rollback();
        }else{
            $mysqli->commit();    
        }

        if($keep_autocommit===true) $mysqli->autocommit(TRUE);

        return $ret;
    }    
    
    //
    // returns where conditions for record ownership/visibility
    //
    private function _getRecordOwnerConditions($ugr_ID){
        
        $from = '';
        $wg_ids = array();
        if($ugr_ID>0){
            
            $currentUser = $this->system->getCurrentUser();
            
            if($currentUser['ugr_ID']==$ugr_ID){
                if(@$currentUser['ugr_Groups']){
                    $wg_ids = array_keys($currentUser['ugr_Groups']);
                    array_push($wg_ids, $ugr_ID);
                }else{
                    $wg_ids = $this->system->get_user_group_ids();    
                }
            }
        }
        array_push($wg_ids, 0); // be sure to include the generic everybody workgroup    
        
        $where2 = '';
        $where2_conj = '';
        if($ugr_ID!=2){ //by default always exclude "hidden" for not database owner
                    //$where2 = '(not r0.rec_NonOwnerVisibility="hidden")';
                
                $where2 = '(r0.rec_NonOwnerVisibility in ("public","pending"))';
                if ($ugr_ID>0){ //logged in
                    
                        //if there is entry for record in usrRecPermissions current user must be member of allowed groups
                        $from = ' LEFT JOIN usrRecPermissions ON rcp_RecID=r0.rec_ID ';
                        
                        $where2 = $where2
                            .' or (r0.rec_NonOwnerVisibility="viewable" and (rcp_UGrpID is null or rcp_UGrpID in ('
                            .join(',', $wg_ids).')))';
                }
                    
                $where2_conj = ' or ';
        }else{
            $wg_ids = array(); //all groups for admin    
        }        
        if($ugr_ID>0 && count($wg_ids)>0){
            $where2 = '( '.$where2.$where2_conj.'r0.rec_OwnerUGrpID in (' . join(',', $wg_ids).') )';
        }
        return array($from, '(not r0.rec_FlagTemporary)'.($where2?' and ':'').$where2);
    }
    
    //
    //
    //
    public function counts(){

        $res = null;
                
        if(@$this->data['mode']=='record_count')
        {
            
            $query = 'SELECT r0.rec_RecTypeID, count(r0.rec_ID) as cnt FROM Records r0 ';
            $where = '';
/*        
        LEFT OUTER JOIN usrRecPermissions ON rcp_RecID=r0.rec_ID  
WHERE
 (not r0.rec_FlagTemporary) and ( (r0.rec_NonOwnerVisibility in ("public","pending")) 
 or (r0.rec_NonOwnerVisibility="viewable" and (rcp_UGrpID is null or rcp_UGrpID in (14,0))) 
 or r0.rec_OwnerUGrpID in (14,0) ) GROUP BY r0.rec_RecTypeID            
            $query = 'SELECT d.rty_ID, count(r0.rec_ID) FROM defRecTypes d ';
            $where = ' LEFT OUTER JOIN Records r0 ON r0.rec_RectypeID=d.rty_ID AND ';
*/              
            if((@$this->data['ugr_ID']>0) || (@$this->data['ugr_ID']===0)){
                $conds = $this->_getRecordOwnerConditions($this->data['ugr_ID']);
                $query = $query . $conds[0];
                $where = $where . $conds[1];      
            }else{
                $where = $where . '(not r0.rec_FlagTemporary)';
            }
            if(@$this->data['rty_ID']>0){
                $where = $where . ' AND (r0.rec_RecTypeID='.$this->data['rty_ID'].')';
            }
            
            $query = $query . ' WHERE '.$where . ' GROUP BY r0.rec_RecTypeID'; // ORDER BY cnt DESC
          
           $res = mysql__select_assoc2($this->system->get_mysqli(), $query);
           
        }else if(@$this->data['mode']=='cms_record_count'){
            
            $this->system->defineConstant('RT_CMS_HOME');
            $this->system->defineConstant('RT_CMS_MENU');
            
            $query = 'SELECT count(r0.rec_ID) as cnt FROM Records r0 ';
            $where = 'WHERE (r0.rec_RecTypeID='.RT_CMS_HOME.') ';


            if((@$this->data['ugr_ID']>0) || (@$this->data['ugr_ID']===0)){
                $conds = $this->_getRecordOwnerConditions($this->data['ugr_ID']);
                if(@$conds[1]) $conds[1] = ' AND '.$conds[1];
            }else{
                $conds = array('', ' AND (not r0.rec_FlagTemporary)');
            }
           
            $res = mysql__select_value($this->system->get_mysqli(), $query.$conds[0].$where.$conds[1]); //total count
            
            $query = 'SELECT r0.rec_ID FROM Records r0 ';
            $where = 'WHERE (r0.rec_NonOwnerVisibility!="public") '
                                .'AND (r0.rec_RecTypeID='.RT_CMS_HOME.')';

            $res2 = mysql__select_list2($this->system->get_mysqli(), $query.$conds[0].$where.$conds[1]);
            if($res2==null) $res2 = array();
            
            $query = 'SELECT r0.rec_ID FROM Records r0 ';
            $where = 'WHERE (r0.rec_NonOwnerVisibility!="public") '
                                .'AND (r0.rec_RecTypeID='.RT_CMS_MENU.')';

            $res3 = mysql__select_list2($this->system->get_mysqli(), $query.$conds[0].$where.$conds[1]);
            if($res3==null) $res3 = array();
            
            $res = array('all'=>$res, 'private_home'=>count($res2), 'private_menu'=>count($res3), 
                'private'=>array_merge($res2, $res3), 'private_home_ids'=>$res2);
            
        }
        
        return $res;
    }
}
?>
