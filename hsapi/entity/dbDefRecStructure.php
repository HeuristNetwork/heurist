<?php

    /**
    * db access to defRecStructure.php table
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


class DbDefRecStructure extends DbEntityBase
{
    
    //
    // constructor - load configuration from json file
    //    
    function __construct( $system, $data ) {
        
       if($data==null){
           $data = array();
       } 
       if(!@$data['entity']){
           $data['entity'] = 'defRecStructure';
       }
        
       parent::__construct( $system, $data );
    }
    

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
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rst_RecTypeID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('rst_DetailTypeID');
        if($pred!=null) array_push($where, $pred);

        $needCheck = false;
        $is_structure = false;

        if(@$this->data['details']==null) $this->data['details'] = 'full';

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'rst_ID';

        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'rst_ID,rst_DisplayName';

        }else if(@$this->data['details']=='list'){
        
            $is_structure = true;
            $this->data['details'] = 'rst_ID,rst_RecTypeID,rst_DetailTypeID,rst_DisplayName'
            .',if(rst_DisplayHelpText is not null and (dty_Type=\'separator\' OR CHAR_LENGTH(rst_DisplayHelpText)>0),rst_DisplayHelpText,dty_HelpText) as rst_DisplayHelpText'
            .',rst_RequirementType, rst_DisplayOrder, rst_DisplayWidth, rst_DisplayHeight, rst_DefaultValue, rst_MaxValues'
            .',rst_CreateChildIfRecPtr, rst_PointerMode, rst_PointerBrowseFilter, rst_NonOwnerVisibility, rst_Status';
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
            "rst_DisplayOrder", "rst_DisplayWidth", "rst_DisplayHeight", "rst_DefaultValue", 
            //XXX "rst_RecordMatchOrder", "rst_CalcFunctionID",
            
            "rst_NonOwnerVisibility", "rst_Status", "rst_OriginatingDBID", "rst_MaxValues", "rst_MinValues",
            //here we check for an override in the recTypeStrucutre for displayGroup
            //XXX "dty_DetailTypeGroupID as rst_DisplayDetailTypeGroupID",
            //here we check for an override in the recTypeStrucutre for TermIDTree which is a subset of the detailType dty_JsonTermIDTree
            "dty_JsonTermIDTree as rst_FilteredJsonTermIDTree",
            //here we check for an override in the recTypeStrucutre for Pointer types which is a subset of the detailType dty_PtrTargetRectypeIDs
            "dty_PtrTargetRectypeIDs as rst_PtrFilteredIDs",
            "rst_CreateChildIfRecPtr", "rst_PointerMode", "rst_PointerBrowseFilter",
            "rst_OrderForThumbnailGeneration", "rst_TermIDTreeNonSelectableIDs", "rst_Modified", "rst_LocallyModified",
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

        if(!$this->system->is_admin() && (count($this->recordIDs)>0 || count($this->records)>0)){ //there are records to update/delete

            $this->system->addError(HEURIST_REQUEST_DENIED, 
                'You are not admin and can\'t edit record type structure. Insufficient rights for this operation');
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
                .' WHERE rst_DetailTypeID='.$mysqli->real_escape_string( $this->records[$idx]['rst_DetailTypeID'])
                .' AND rst_RecTypeID='.$mysqli->real_escape_string( $this->records[$idx]['rst_RecTypeID']) );

            $isInsert = !(@$row['rst_ID']>0);

            if($isInsert){
                $this->records[$idx]['rst_ID'] = -1;
                $this->records[$idx]['rst_LocallyModified'] = 0;
                if(!@$this->records[$idx]['rst_Status']) $this->records[$idx]['rst_Status'] = 'open';
            }else{
                $this->records[$idx]['rst_ID'] = $row['rst_ID'];
                $this->records[$idx]['rst_LocallyModified'] = ($row['rst_OriginatingDBID']>0)?1:0;
                if(@$this->records[$idx]['rst_Status']=='') $this->records[$idx]['rst_Status'] = 'open';
            }

            if(@$this->records[$idx]['rst_MaxValues']==null ||
                !(intval(@$this->records[$idx]['rst_MaxValues'])>=0)) $this->records[$idx]['rst_MaxValues'] = 1;

            
            $this->records[$idx]['rst_Modified'] = date('Y-m-d H:i:s'); //reset

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
        
        if(@$this->data['recID'] && strpos($this->data['recID'],'.')){
            list($rty_ID, $dty_ID) = explode('.', $this->data['recID']);
            
            $this->recordIDs = 0;
            if(is_numeric($rty_ID) && $rty_ID>0 && is_numeric($dty_ID) && $dty_ID>0){
                $mysqli = $this->system->get_mysqli();
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
                    .' WHERE rst_DetailTypeID='.$mysqli->real_escape_string( $dty_ID )
                    .' AND rst_RecTypeID='.$mysqli->real_escape_string( $rty_ID  );
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
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);
            
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
        $newfields = $this->data['newfields'];
        
        if(count($newfields)==0){             
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
        if(isset($this->data['order'])){ $order = $this->data['order']; }
        
        $dt_fields = dbs_GetDetailTypes($this->system, $fields);
        $dt_fields = $dt_fields['typedefs'];
        $di = $dt_fields['fieldNamesToIndex'];
        
        
        $records = array();        
        foreach($fields as $dty_ID)
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
            
            if(@$newfields_values[$dty_ID]['rst_DefaultValue']){
                $recvalues['rst_DefaultValue'] = $newfields_values[$dty_ID]['rst_DefaultValue'];
            }
            
            $records[] = $recvalues;
            
            if(isset($this->data['order'])){ $order = $this->data['order']; }
            else { $order = $order+10; }
        }
        
        if(count($records)>0){
            $this->data['fields'] = $records;
            $this->is_addition = true;
            return $this->save();
        }else{
            return false;
        }
    }
    
    
}
?>
