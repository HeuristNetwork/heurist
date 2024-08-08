<?php

    /**
    * db access to defRecStructure.php table
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
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

require_once dirname(__FILE__).'/../System.php';
require_once dirname(__FILE__).'/dbEntityBase.php';
require_once dirname(__FILE__).'/dbEntitySearch.php';
require_once dirname(__FILE__).'/../structure/dbsTerms.php';


class DbDefRecStructure extends DbEntityBase
{
    
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
        $from_table = array($this->config['tableName']);

        $pred = $this->searchMgr->getPredicate('rst_ID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('rst_RecTypeID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('rst_DetailTypeID');
        if($pred!=null) {array_push($where, $pred);}
        
        $pred = $this->searchMgr->getPredicate('rst_CalcFunctionID');
        if($pred!=null) {array_push($where, $pred);}

        $needCheck = false;
        $is_structure = false;

        if(@$this->data['details']==null) {$this->data['details'] = 'full';}

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'rst_ID';

        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'rst_ID,rst_DisplayName';

        }else if(@$this->data['details']=='rectype'){

            $this->data['details'] = 'rst_ID,rst_RecTypeID,rst_DetailTypeID';
            
        }else if(@$this->data['details']=='list'){
        
            $is_structure = true;
            $this->data['details'] = 'rst_ID,rst_RecTypeID,rst_DetailTypeID,rst_DisplayName'
            .',if(rst_DisplayHelpText is not null and (dty_Type=\'separator\' OR CHAR_LENGTH(rst_DisplayHelpText)>0),rst_DisplayHelpText,dty_HelpText) as rst_DisplayHelpText'
            .',if(rst_DisplayExtendedDescription is not null and CHAR_LENGTH(rst_DisplayExtendedDescription)>0,rst_DisplayExtendedDescription,dty_ExtendedDescription) as rst_DisplayExtendedDescription'
			.',rst_RequirementType, rst_DisplayOrder, rst_DisplayWidth, rst_DisplayHeight, rst_DefaultValue, rst_MaxValues'
            .',rst_CreateChildIfRecPtr, rst_PointerMode, rst_PointerBrowseFilter, rst_NonOwnerVisibility, rst_Status, rst_MayModify, rst_SemanticReferenceURL, rst_TermsAsButtons, rst_CalcFunctionID ';
            //dty_Type, rst_FilteredJsonTermIDTree/dty_JsonTermIDTree, rst_PtrFilteredIDs/dty_PtrTargetRectypeIDs 
        
        }else if(@$this->data['details']=='full'){
            //all fields from configuration json

            $this->data['details'] = implode(',', $this->fieldNames);

        }else if(@$this->data['details']=='structure'){

            //$this->data['details'] = implode(',', $this->fieldNames);
            $is_structure = true;

            $colNames = array("rst_RecTypeID", "rst_DetailTypeID",
            //here we check for an override in the recTypeStrucutre for displayName which is a rectype specific name, use detailType name as default
            "if(rst_DisplayName is not null and CHAR_LENGTH(rst_DisplayName)>0,rst_DisplayName,dty_Name) as rst_DisplayName",
            //here we check for an override in the recTypeStrucutre for HelpText which is a rectype specific HelpText, use detailType HelpText as default
            "if(rst_DisplayHelpText is not null and (dty_Type='separator' OR CHAR_LENGTH(rst_DisplayHelpText)>0),rst_DisplayHelpText,dty_HelpText) as rst_DisplayHelpText",
            //here we check for an override in the recTypeStrucutre for ExtendedDescription which is a rectype specific ExtendedDescription, use detailType ExtendedDescription as default
            "if(rst_DisplayExtendedDescription is not null and CHAR_LENGTH(rst_DisplayExtendedDescription)>0,rst_DisplayExtendedDescription,dty_ExtendedDescription) as rst_DisplayExtendedDescription",
            "rst_RequirementType",
            "rst_DisplayOrder", "rst_DisplayWidth", "rst_DisplayHeight", "rst_DefaultValue","rst_CalcFunctionID", 
            //XXX "rst_RecordMatchOrder"
            
            "rst_NonOwnerVisibility", "rst_Status", "rst_MayModify", "rst_OriginatingDBID", "rst_MaxValues", "rst_MinValues",
            //here we check for an override in the recTypeStrucutre for displayGroup
            //XXX "dty_DetailTypeGroupID as rst_DisplayDetailTypeGroupID",
            //here we check for an override in the recTypeStrucutre for TermIDTree which is a subset of the detailType dty_JsonTermIDTree
            "dty_JsonTermIDTree as rst_FilteredJsonTermIDTree",
            //here we check for an override in the recTypeStrucutre for Pointer types which is a subset of the detailType dty_PtrTargetRectypeIDs
            "dty_PtrTargetRectypeIDs as rst_PtrFilteredIDs",
            "rst_CreateChildIfRecPtr", "rst_PointerMode", "rst_PointerBrowseFilter",
            "rst_OrderForThumbnailGeneration", "rst_TermIDTreeNonSelectableIDs", "rst_Modified", "rst_LocallyModified",
            "rst_SemanticReferenceURL","rst_TermsAsButtons",
            "dty_TermIDTreeNonSelectableIDs",
            "dty_FieldSetRectypeID",
            "dty_Type");
            
            $this->data['details'] = implode(',', $colNames);
        
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
        $order = array('rst_DisplayOrder ASC');

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('rst_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'],'rst_ID');
        }

        $is_ids_only = (count($this->data['details'])==1);

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);
        
        if($is_structure){
            $query = $query.' left join defDetailTypes on rst_DetailTypeID = dty_ID ';
        }

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

        if(!$this->system->is_admin() && 
            ((is_array($this->recordIDs) && count($this->recordIDs)>0) 
            || (is_array($this->records) && count($this->records)>0))){ //there are records to update/delete

            $this->system->addError(HEURIST_REQUEST_DENIED, 
                'You are not admin and can\'t edit record type structure. Insufficient rights (logout/in to refresh) for this operation');
            return false;
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

            //find real rst_ID 
            $mysqli = $this->system->get_mysqli();
                
            $row = mysql__select_row_assoc($mysqli,
                'SELECT rst_ID, rst_OriginatingDBID FROM '.$this->config['tableName']
                .' WHERE rst_DetailTypeID='.intval( $this->records[$idx]['rst_DetailTypeID'] )
                .' AND rst_RecTypeID='.intval( $this->records[$idx]['rst_RecTypeID']) );

            $isInsert = !(@$row['rst_ID']>0);

            if($isInsert){
                $this->records[$idx]['rst_ID'] = -1;
                $this->records[$idx]['rst_LocallyModified'] = 0;
                if(!@$this->records[$idx]['rst_Status']) {$this->records[$idx]['rst_Status'] = 'open';}
                
                if($this->records[$idx]['rst_DefaultValue']=='tabs' && !@$this->records[$idx]['rst_DisplayName']){
                    $this->records[$idx]['rst_DisplayName'] = 'Divider '.$idx;
                }
            }else{
                $this->records[$idx]['rst_ID'] = $row['rst_ID'];
                $this->records[$idx]['rst_LocallyModified'] = ($row['rst_OriginatingDBID']>0)?1:0;
                if(@$this->records[$idx]['rst_Status']=='') {$this->records[$idx]['rst_Status'] = 'open';}
            }

            if(@$this->records[$idx]['rst_MaxValues']==null ||
                !(intval(@$this->records[$idx]['rst_MaxValues'])>=0)) {$this->records[$idx]['rst_MaxValues'] = 1;}

            
            $this->records[$idx]['rst_Modified'] = date('Y-m-d H:i:s');//reset

            $this->records[$idx]['is_new'] = $isInsert;
        }

        return $ret;

    }    

    public function save(){
        
        $results = parent::save();
        if($results!==false){
            $results = array();
            foreach($this->records as $rec_idx => $record){
                $results[] = $this->records[$rec_idx]['rst_DetailTypeID'];
            }
        }
        return $results;    
    } 
    
    public function delete($disable_foreign_checks = false){
        
        $mysqli = $this->system->get_mysqli();

        if(@$this->data['recID'] && strpos($this->data['recID'],'.')){
            list($rty_ID, $dty_ID) = explode('.', $this->data['recID']);
            
            $this->recordIDs = 0;
            if(is_numeric($rty_ID) && $rty_ID>0 && is_numeric($dty_ID) && $dty_ID>0){
                $this->recordIDs = mysql__select_value($mysqli,
                    'SELECT rst_ID FROM '.$this->config['tableName']
                    .' WHERE rst_DetailTypeID='.$mysqli->real_escape_string( $dty_ID )
                    .' AND rst_RecTypeID='.$mysqli->real_escape_string( $rty_ID  ));
            }
            if(!($this->recordIDs>0)){
                $this->system->addError(HEURIST_NOT_FOUND, 'Cannot delete. No entries found for given record and field type');
                return false;    
            }else{
                $this->recordIDs = array($this->recordIDs);
            }
        }else if(@$this->data['dtyID']){
            $dty_ID = $this->data['dtyID'];

            $this->recordIDs = null;
            if(is_numeric($dty_ID) && $dty_ID > 0){
                $this->recordIDs = mysql__select_list2($mysqli, 
                    'SELECT rst_ID FROM '.$this->config['tableName']
                    .' WHERE rst_DetailTypeID='.$mysqli->real_escape_string($dty_ID));
            }
            if(!$this->recordIDs || !is_array($this->recordIDs) || count($this->recordIDs) == 0){
                $this->system->addError(HEURIST_NOT_FOUND, 'Cannot delete. No entries found for field ID ' . $dty_ID);
                return false;
            }else if(!is_array($this->recordIDs)){
                $this->recordIDs = array($this->recordIDs);
            }
        }

        return parent::delete();
    }

    //    
    // A. update order for fields in record type - see parameter "orders"
    // B. add set of new fields - see parameter "newfields"
    //
    public function batch_action(){
        
        if(!(@$this->data['rtyID']>0)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Record type identificator not defined');
            return false;
        }
        if(@$this->data['newfields']){
            return $this->addNewFields();
        }else if (@$this->data['orders']){
            return $this->setNewFieldOrder();
        }
    }
        
    //
    //
    //
    private function setNewFieldOrder(){
        
        $rty_ID = $this->data['rtyID'];
        
        //dty_ID
        $this->recordIDs = prepareIds(@$this->data['recID']);
        if(count($this->recordIDs)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid field identificators');
            return false;
        }
        
        $orders = prepareIds(@$this->data['orders'], true);
        if(count($orders)==0){             
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid values for fields order');
            return false;
        }
        
        $ret = true;
        $mysqli = $this->system->get_mysqli();
        $keep_autocommit = mysql__begin_transaction($mysqli);
        
        foreach ($this->recordIDs as $idx => $dty_ID){        
            
            $order = $orders[$idx];
            
            $query = 'UPDATE '.$this->config['tableName'].' SET rst_DisplayOrder='.$order
                    .' WHERE rst_DetailTypeID='.intval( $dty_ID )
                    .' AND rst_RecTypeID='.intval( $rty_ID  );
            $res = $mysqli->query($query);
                if(!$res){
                    $ret = false;
                    $mysqli->rollback();
                    $this->system->addError(HEURIST_DB_ERROR, 'Can\'t set order for fields in rectord type #'.$rty_ID, $mysqli->error );
                    break;
                }
        }
        if($ret){
            $mysqli->commit();
        }
        if($keep_autocommit===true) {$mysqli->autocommit(TRUE);}
            
        return $ret;        
    }
    
    //
    // newfields=>array(
    //        fields=>  array of ids 
    //        reqs=>   array of ids 
    //        values=>  [dty_ID][fieldName]=>value
    //
    private function addNewFields(){
        
        $rty_ID = $this->data['rtyID'];
        $newfields = @$this->data['newfields'];
        
        if(!is_array($newfields) || count($newfields)==0){             
            //if rt structure has zero fields adds 2 default fields: DT_NAME and DT_DESCRIPTION
            $mysqli = $this->system->get_mysqli();
            if(mysql__select_value($mysqli,
                    'SELECT count(*) FROM '.$this->config['tableName']
                    .' WHERE rst_RecTypeID='.$mysqli->real_escape_string( $rty_ID ))===0){
                        
                        $newfields['fields'] = array(DT_NAME, DT_DESCRIPTION);
                        $newfields['reqs'] = array(DT_NAME);
            }else{
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid values for new fields');
                return false;
            }
        }
        
        $fields = prepareIds($newfields['fields'], false);
        $reqs   = @$newfields['reqs']?$newfields['reqs']:array();
        $newfields_values  = @$newfields['values']?$newfields['values']:array();
        $order = 0;
        if(isset($this->data['order'])){ $order = $this->data['order'];}
        
        $dt_fields = dbs_GetDetailTypes($this->system, $fields);
        $dt_fields = $dt_fields['typedefs'];
        $di = $dt_fields['fieldNamesToIndex'];
        
        
        $records = array();
        foreach($fields as $dty_ID){
            if(@$dt_fields[$dty_ID])
            {
            
                $dt = $dt_fields[$dty_ID]['commonFields'];
                
                $recvalues = array(
                'rst_ID'=> $dty_ID,
                'rst_RecTypeID'=> $rty_ID,
                'rst_DisplayOrder'=> $order,
                'rst_DetailTypeID'=> $dty_ID,
                'rst_DisplayName'=> @$newfields_values[$dty_ID]['dty_Name']
                                         ?$newfields_values[$dty_ID]['dty_Name'] 
                                         :$dt[$di['dty_Name']],
                'rst_DisplayHelpText'=> @$newfields_values[$dty_ID]['dty_HelpText']
                                         ?$newfields_values[$dty_ID]['dty_HelpText'] 
                                         :$dt[$di['dty_HelpText']],
                'rst_RequirementType'=> in_array($dty_ID,$reqs)?'required':'recommended',
                'rst_MaxValues'=> 1,
                'rst_DisplayWidth'=>($dt[$di['dty_Type']]=='date')?20:100);


                if(@$dt[$di['dty_SemanticReferenceURL']]){
                    $recvalues['rst_SemanticReferenceURL'] = $dt[$di['dty_SemanticReferenceURL']];
                }
                if(@$newfields_values[$dty_ID]['dty_DefaultValue']){
                    $recvalues['rst_DefaultValue'] = $newfields_values[$dty_ID]['dty_DefaultValue'];
                }else if(@$newfields_values[$dty_ID]['rst_DefaultValue']){
                    $recvalues['rst_DefaultValue'] = $newfields_values[$dty_ID]['rst_DefaultValue'];
                }
                
                $records[] = $recvalues;
                
                if(isset($this->data['order'])){ $order = $this->data['order'];}
                else { $order = $order+10; }
            }
        }
        
        if(count($records)>0){
            $this->data['fields'] = $records;
            $this->is_addition = true;
            return $this->save();
        }else{
            return false;
        }
    }
    
    //
    // Counts:
    //  rectype_field_usage: count all bits of data for all records of the provided record type
    //
    public function counts(){

        $mysqli = $this->system->get_mysqli();
        $res = null;

        if(@$this->data['mode'] == 'rectype_field_usage'){

            $rty_ID = intval(@$this->data['rtyID'], 10);

            // For checking relation types
            $defTerms = dbs_GetTerms($this->system);
            $defTerms = new DbsTerms($this->system, $defTerms);

            if(isset($rty_ID) && is_numeric($rty_ID) && $rty_ID > 0){

                // Get count for all details, except relmarkers
                $query = 'SELECT dtl_DetailTypeID, count(dtl_ID) '
                    . 'FROM recDetails '
                    . 'INNER JOIN Records ON rec_ID=dtl_RecID '
                    . 'WHERE rec_RecTypeID=' . $rty_ID . ' '
                    . 'GROUP BY dtl_DetailTypeID';
                $detail_usage = mysql__select_assoc2($mysqli, $query);// [ dty_ID1 => count1, ... ]
                if($detail_usage){
                    $res = $detail_usage; 
                }else if(empty($mysqli->error)){
                    $res = array();
                }else{
                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve field usages for record type #'.$rty_ID, $mysqli->error);
                    return false;
                }

                // Check for relmarkers
                $query = 'SELECT dty_ID, dty_PtrTargetRectypeIDs, dty_JsonTermIDTree '
                    . 'FROM defRecStructure '
                    . 'INNER JOIN defDetailTypes ON rst_DetailTypeID=dty_ID '
                    . 'WHERE dty_Type="relmarker" AND rst_RecTypeID=' . $rty_ID;
                $relmarker_filters = mysql__select_assoc($mysqli, $query);// [ relmarker_fld_ID1 => [rty_id_list1, trm_id1], ... ]
                if($relmarker_filters && count($relmarker_filters) > 0){

                    // Retrieve record ids that are relevant
                    $query = 'SELECT DISTINCT rec_ID FROM Records, recLinks WHERE rec_RecTypeID=' . $rty_ID 
                        . ' AND rl_RelationID > 0 AND (rl_SourceID=rec_ID OR rl_TargetID=rec_ID)';
                    $ids = mysql__select_list2($mysqli, $query);// returns array of rec ids
                    if(is_array($ids) && count($ids) > 0){

                        $rec_ids = implode(',', $ids);
                        foreach ($relmarker_filters as $dty_id => $fld_details) {

                            $allowed_recs = array();// records that meet the rectype requirement
                            $not_allowed_recs = array();// records that don't meet the rectype requirement
                            $count = 0;

                            // Get possible related types (relation terms)
                            $terms = $defTerms->treeData($fld_details['dty_JsonTermIDTree'], 'set');
                            // Split possible related rectypes
                            $rectypes = explode(',', $fld_details['dty_PtrTargetRectypeIDs']);
                            $allow_all = empty($rectypes);

                            if(is_array($terms) && count($terms) > 0){
                                // Retrieve relmarker count - from
                                $query = 'SELECT rl_TargetID '
                                    . 'FROM recLinks '
                                    . 'WHERE rl_RelationTypeID IN (' . implode(',', $terms) . ') AND rl_SourceID IN ('. $rec_ids .')';
                                $rel_usage_from = mysql__select_list2($mysqli, $query);// returns count
                                if(!empty($mysqli->error)){
                                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve relationship marker usage for field #'.$dty_id.' from record type #'.$rty_ID, $mysqli->error);
                                    return false;
                                }

                                $query = 'SELECT rl_SourceID '
                                . 'FROM recLinks '
                                . 'WHERE rl_RelationTypeID IN (' . implode(',', $terms) . ') AND rl_TargetID IN ('. $rec_ids .')';
                                $rel_usage_to = mysql__select_list2($mysqli, $query);// returns count
                                if(!empty($mysqli->error)){
                                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve reverse relationship marker usage for field #'.$dty_id.' from record type #'.$rty_ID, $mysqli->error);
                                    return false;
                                }

                                $rel_usages = array_merge($rel_usage_from, $rel_usage_to);

                                foreach($rel_usages as $rec_id){
                                    if(in_array($rec_id, $allowed_recs)){
                                        $count ++;
                                        continue;
                                    }else if(in_array($rec_id, $not_allowed_recs)){
                                        continue;
                                    }

                                    $check_res = mysql__select_value($mysqli, "SELECT rec_RecTypeID FROM Records WHERE rec_ID = $rec_id");
                                    if(!empty($mysqli->error)){
                                        $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve record type id for record #'.$rec_id, $mysqli->error);
                                        return false;
                                    }

                                    if($allow_all || in_array($check_res, $rectypes)){
                                        $count ++;
                                        $allowed_recs[] = $rec_id;
                                    }else{
                                        $not_allowed_recs[] = $rec_id;
                                    }
                                }
                            }

                            $res[$dty_id] = $count;
                        }
                    }else if(!empty($mysqli->error)){
                        $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve related records for counting relationship marker field usage for record type #'.$rty_ID, $mysqli->error);
                        return false;
                    }
                }else if(!empty($mysqli->error)){
                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot check record type #'.$rty_ID.' for relationship marker fields', $mysqli->error);
                    return false;
                }

                if(@$this->data['get_meta_counts'] == 1){ // Include count of rec_ field counts

                    // Get number of records
                    $query = "SELECT count(rec_ID) "
                        . "FROM Records "
                        . "WHERE rec_RecTypeID = $rty_ID AND rec_FlagTemporary = 0";

                    $rec_counts = mysql__select_value($mysqli, $query);
                    $res['rec_ID'] = !$rec_counts ? 0 : $rec_counts;

                    // Get number of rec URLs
                    $query = "SELECT count(rec_URL) "
                        . "FROM Records "
                        . "WHERE rec_RecTypeID = $rty_ID AND rec_FlagTemporary = 0";

                    $url_counts = mysql__select_value($mysqli, $query);
                    $res['rec_URL'] = !$url_counts ? 0 : $url_counts;

                    // Get number of records with tags
                    $query = "SELECT DISTINCT count(rtl_RecID)"
                        . "FROM usrRecTagLinks "
                        . "INNER JOIN Records ON rec_ID = rtl_ID "
                        . "WHERE rec_RecTypeID = $rty_ID AND rec_FlagTemporary = 0";

                    $tag_count = mysql__select_value($mysqli, $query);
                    $res['rec_Tags'] = !$tag_count ? 0 : $tag_count;
                }

                if(!$res || count($res) == 0){
                    $res = [0];
                }
            }else{
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Invalid record type id provided '.$rty_ID);
                $res = false;
            }
        }

        return $res;
    }
}
?>
