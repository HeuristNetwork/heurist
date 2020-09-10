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

            $this->data['details'] = 'rty_ID,rty_Name,rty_OrderInGroup,rty_Description,rty_TitleMask,rty_Plural,'
            .'rty_Status,rty_OriginatingDBID,rty_IDInOriginatingDB,rty_ShowInLists,rty_RecTypeGroupID,rty_ReferenceURL,'
            .'rty_ShowURLOnEditForm,rty_ShowDescriptionOnEditForm,rty_Modified';
            
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

        $calculatedFields = null;
        
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
            $this->system->addError(HEURIST_NOT_FOUND, 'Cannot delete structure for rectype. No entries found');
            $res = false;
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

            //validate duplication
            $mysqli = $this->system->get_mysqli();
            $res = mysql__select_value($mysqli,
                    "SELECT rty_ID FROM defRecTypes  WHERE rty_Name='"
                    .$mysqli->real_escape_string( $this->records[$idx]['rty_Name'])."'");
            if($res>0 && $res!=@$this->records[$idx]['rty_ID']){
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Record type cannot be saved. The provided name already exists');
                return false;
            }

            
            if(@$this->records[$idx]['rty_LocallyModified']==null){
                $this->records[$idx]['rty_LocallyModified'] = 0; //default value for new
            }
            if(@$this->records[$idx]['rty_IDInOriginatingDB']==''){
                $this->records[$idx]['rty_IDInOriginatingDB'] = 0;
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
            $mysqli = $this->system->get_mysqli();
            
            foreach($this->records as $idx=>$record){
                $rty_ID = @$record['rty_ID'];
                if($rty_ID>0 && in_array($rty_ID, $ret)){
                    
                    if($record['is_new']){
                        //1. if new add default set of fields TODO!
                        /*if($isAddDefaultSetOfFields){
                            //add default set of detail types
                            addDefaultFieldForNewRecordType($rtyID, $newfields);
                        }*/
                        
                        //2. set dbid or update modified locally
                        if($dbID>0){
                            $query= 'UPDATE defRecTypes SET rty_OriginatingDBID='.$dbID
                                .', rty_NameInOriginatingDB=rty_Name'
                                .', rty_IDInOriginatingDB='.$rty_ID
                                .' WHERE (NOT rty_OriginatingDBID>0) AND rty_ID='.$rty_ID;
                                $res = $mysqli->query($query);
                        }            
                    }else{
                        $query = 'UPDATE defRecTypes SET rty_LocallyModified=IF(rty_OriginatingDBID>0,1,0)'
                                . ' WHERE rty_ID = '.$rty_ID;
                    }
                    mysql__exec_param_query($mysqli, $query, null);
                        
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
                    
                    //backward capability
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
    // 1) import rectype from another db
    //
    public function batch_action(){
         //@todo
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
            
            $query = 'SELECT count(r0.rec_ID) as cnt '
                .'FROM Records r0 WHERE (not r0.rec_FlagTemporary) '
                .'AND (r0.rec_RecTypeID='.RT_CMS_HOME.')';
            
            $res = mysql__select_value($this->system->get_mysqli(), $query); //total count
            
            $query = 'SELECT r0.rec_ID '
                .'FROM Records r0 WHERE (not r0.rec_FlagTemporary) '
                .'AND (r0.rec_NonOwnerVisibility!="public") '
                .'AND (r0.rec_RecTypeID='.RT_CMS_HOME.')';

            $res2 = mysql__select_list2($this->system->get_mysqli(), $query);
            if($res2==null) $res2 = array();
            
            $query = 'SELECT r0.rec_ID '
                .'FROM Records r0 WHERE (not r0.rec_FlagTemporary) '
                .'AND (r0.rec_NonOwnerVisibility!="public") '
                .'AND (r0.rec_RecTypeID='.RT_CMS_MENU.')';

            $res3 = mysql__select_list2($this->system->get_mysqli(), $query);
            if($res3==null) $res3 = array();
            
            $res = array('all'=>$res, 'private_home'=>count($res2), 'private_menu'=>count($res3), 
                'private'=>array_merge($res2, $res3), 'private_home_ids'=>$res2);
            
        }
        
        return $res;
    }    
}
?>
