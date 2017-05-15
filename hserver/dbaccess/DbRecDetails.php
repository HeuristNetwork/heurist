<?php

    /**
    * Class to update records details in batch
    * to be renamed since in coinside with name scheme for entity
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
require_once (dirname(__FILE__).'/db_tags.php');

class DbRecDetails
{
    private $system;  
    
    /*  
    *       recIDs - list of records IDS to be processed
    *       rtyID  - filter by record type
    *       dtyID  - detail field to be added
    *       for addition: val: | geo: | ulfID: - value to be added
    *       for edit sVal - search value,  rVal - replace value
    *       for delete: sVal  
    *       tag  = 0|1  - add system tag to mark processed records
    */    
    private $data;  

    /*
    * records to be processed
    */
    private $recIDs;  
    
    /*
    * distinct array of record types
    */
    private $rtyIDs;
    
    /*
    passed     _tag _error
    noaccess    
    processed - no rights to edit
    undefined - field definition not found (add) or search value not found (edit,delete)
    limitted  
    errors    - sql error on search or updata
    */
    private $result_data = array();
    
    
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
    }
    
    //
    //
    //
    private function _validateParamsAndCounts()
    {
        if ( $this->system->get_user_id()<1 ) {
            $this->system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }
        
        $rtyID = @$this->data['rtyID'];
        $dtyID = $this->data['dtyID'];
        
        if (!( @$this->data['recIDs'])){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Insufficent data passed: records');
            return false;
        }
        
        if ($rtyID && !(ctype_digit($rtyID) && $rtyID>0)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter record type id $rtyID");
            return false;
        }

        if(!(ctype_digit($dtyID) && $dtyID>0)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter detail type id $dtyID");
            return false;
        }

        $mysqli = $this->system->get_mysqli();

        //normalize recIDs to an array for code below
        $recIDs = $this->data['recIDs'];
        if ($recIDs && ! is_array($recIDs)){
            $recIDs = array($recIDs);
        }
        
        $passedRecIDCnt = count(@$recIDs);
        
        if ($passedRecIDCnt>0) {//check editable access for passed records
            if($rtyID){ //filter for record type
                $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_RecTypeID = $rtyID and rec_ID  in ("
                                    .implode(",",$recIDs).")");
                $passedRecIDCnt = count(@$recIDs);
            }
            if($passedRecIDCnt>0){
                //exclude records user has no right to edit
                $this->recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_ID in (".implode(",",$recIDs).") and rec_OwnerUGrpID in (0,".join(",",$this->system->get_user_group_ids()).")");
                $inAccessibleRecCnt = $passedRecIDCnt - count(@$this->recIDs);
            }
        }

        $this->result_data = array('passed'=> $passedRecIDCnt>0?$passedRecIDCnt:0,
                                   'noaccess'=> @$inAccessibleRecCnt ?$inAccessibleRecCnt :0);

        if (count(@$this->recIDs)==0){
            $this->result_data['processed'] = 0;
            return true;
        }
        
        if($rtyID){
            $this->rtyIDs = array($rtyID);
        }else {
            $this->rtyIDs = mysql__select_list($mysqli, 'Records','distinct(rec_RecTypeID)',"rec_ID in (".implode(",",$this->recIDs).")");
        }
    
        return true;
    }
        
    /**
    * 
    */
    public function detailsAdd(){

        if (!(@$this->data['val'] || @$this->data['geo'] || @$this->data['ulfID'])){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed");
            return false;
        }
        
        if(!$this->_validateParamsAndCounts()){
            return false;
        }else if (count(@$this->recIDs)==0){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']."");
        
        $mysqli = $this->system->get_mysqli();
        
        //get array of max allowed values per record type
        $rtyLimits = mysql__select_assoc($mysqli, "defRecStructure","rst_RecTypeID","rst_MaxValues",
                        "rst_DetailTypeID = $dtyID and rst_RecTypeID in (".implode(",",$this->rtyIDs).")");

        $now = date('Y-m-d H:i:s');
        $dtl = Array('dtl_DetailTypeID'  => $dtyID,
                     'dtl_Modified'  => $now);
        $rec_update = Array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);
                     
        $baseTag = "~add field $dtyName $now"; //name of tag assigned to modified records
        
        if(@$this->data['val']){
            $dtl['dtl_Value'] = $this->data['val'];
        }
        if(@$this->data['geo']){
            $dtl['dtl_Geo'] = array("geomfromtext(\"" . $this->data['geo'] . "\")");  
        }
        if(@$this->data['ulfID']){
            $dtl['dtl_UploadedFileID'] = $this->data['ulfID'];
        }
        
        $undefinedFieldsRecIDs = array(); //limit not defined
        $processedRecIDs = array();       //success  
        $limittedRecIDs = array();        //over limit - skip
        $sqlErrors = array();
        
        foreach ($this->recIDs as $recID) {
            //check field limit for this record
            $query = "select rec_RecTypeID, tmp.cnt from Records ".
            "left join (select dtl_RecID as recID, count(dtl_ID) as cnt ".
            "from recDetails ".
            "where dtl_RecID = $recID and dtl_DetailTypeID = $dtyID group by dtl_RecID) as tmp on rec_ID = tmp.recID ".
            "where rec_ID = $recID";

            $res = $mysqli->query($query);
            if(!$res){
                array_push($undefinedFieldsRecIDs, $recID); //cannot retrieve limit 
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }
            
            $row = $res->fetch_row();

            if (!array_key_exists($row[0],$rtyLimits)) { //limit not defined
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }else if (intval($rtyLimits[$row[0]])>0 && $row[1]>0 && ($rtyLimits[$row[0]] - $row[1]) < 1){
                array_push($limittedRecIDs, $recID);  //over limit - skip
                continue;
            }

            //limit ok so insert field
            $dtl['dtl_RecID'] = $recID;
            $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
            
            if (!is_numeric($ret)) {
                $sqlErrors[$recID] = $ret;
                continue;
            }
            array_push($processedRecIDs, $recID);
            //update record edit date
            $rec_update['rec_ID'] = $recID;
            $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
            if (!is_numeric($ret)) {
                $sqlErrors[$recID] = 'Cannot update modify date. '.$ret;
            }
        }
        
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('limitted',  $limittedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

//error_log('>>>>>'.print_r($this->result_data, true));        
        
        
        return $this->result_data;        
    }

    /**
    * Replace detail value for given set of records and detail type and values
    */
    public function detailsReplace(){

        if (!(@$this->data['sVal'] && @$this->data['rVal'])){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed");
            return false;
        }
        
        if(!$this->_validateParamsAndCounts()){
            return false;
        }else if (count(@$this->recIDs)==0){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        
        $mysqli = $this->system->get_mysqli();
 
        $basetype = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$dtyID);
        switch ($basetype) {
            case "freetext":
            case "blocktext":
                $searchClause = "dtl_Value like \"%".$mysqli->real_escape_string($this->data['sVal'])."%\"";
                $partialReplace = true;
                break;
            case "enum":
            case "relationtype":
            case "float":
            case "integer":
            case "resource":
            case "date":
                $searchClause = "dtl_Value = \"".$mysqli->real_escape_string($this->data['sVal'])."\"";
                $partialReplace = false;
                break;
            default:
                $this->system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by value-replace service");
                return false;
        }

        $undefinedFieldsRecIDs = array(); //value not found
        $processedRecIDs = array();       //success  
        $sqlErrors = array();
        
        $now = date('Y-m-d H:i:s');
        $dtl = Array('dtl_Modified'  => $now);
        $rec_update = Array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);
                     
        $baseTag = "~replace field $dtyName $now";
        
        foreach ($this->recIDs as $recID) {
            //get matching detail value for record if there is one
            $valuesToBeReplaced = mysql__select_assoc($mysqli, "recDetails","dtl_ID", "dtl_Value",
                        "dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause");
            
            if($mysqli->error!=null || $mysqli->error!=''){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }else if($valuesToBeReplaced==null || count($valuesToBeReplaced)==0){  //not found
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }
            
            //update the details
            $recDetailWasUpdated = false;
            foreach ($valuesToBeReplaced as $dtlID => $dtlVal) {
                
                if ($partialReplace) {// need to replace sVal with rVal
                    $newVal = preg_replace("/".$this->data['sVal']."/",$this->data['rVal'],$dtlVal);
                }else{
                    $newVal = $this->data['rVal'];
                }
                $newVal = $mysqli->real_escape_string($newVal);
         
                $dtl['dtl_ID'] = $dtlID;
                $dtl['dtl_Value'] = $newVal;
                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
            
                if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = $ret;
                    continue;
                }
                $recDetailWasUpdated = true;
            }
            if ($recDetailWasUpdated) {
                //only put in processed if a detail was processed, 
                // obscure case when record has multiple details we record in error array also
                array_push($processedRecIDs, $recID);
                
                //update record edit date
                $rec_update['rec_ID'] = $recID;
                $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
                if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = 'Cannot update modify date. '.$ret;
                }
            }
        }//for recors
        
        
        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);
        
        return $this->result_data;        
    }

    /**
    * Remove detail value for given set of records and detail type and values
    */
    public function detailsDelete(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }else if (count(@$this->recIDs)==0){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $isDeleteAll = (!array_key_exists("sVal",$this->data) || $this->data['sVal']=='');
        
        $mysqli = $this->system->get_mysqli();
        
        $basetype = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$dtyID);
        switch ($basetype) {
            case "freetext":
            case "blocktext":
            case "enum":
            case "relationtype":
            case "float":
            case "integer":
            case "resource":
            case "date":
                $searchClause = ($isDeleteAll) 
                            ? '1'
                            : "dtl_Value = \"".$mysqli->real_escape_string($this->data['sVal'])."\"";
                break;
            default:
                $this->system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by deletion service");
                return false;
        }
        
        
        //get array of required detail types per record type
        $rtyRequired = mysql__select_list($mysqli, "defRecStructure","rst_RecTypeID",
        "rst_DetailTypeID = $dtyID and rst_RecTypeID in (".implode(",",$this->rtyIDs).") and rst_RequirementType='required'");
        

        $undefinedFieldsRecIDs = array(); //value not found
        $processedRecIDs = array();       //success  
        $limittedRecIDs = array(); //it is npt possible to delete requried fields
        $sqlErrors = array();
        
        $now = date('Y-m-d H:i:s');
        $dtl = Array('dtl_Modified'  => $now);
        $rec_update = Array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);
                     
        $baseTag = "~delete field $dtyName $now";
        
        foreach ($this->recIDs as $recID) {
            //get matching detail value for record if there is one
            $valuesToBeDeleted = mysql__select_list($mysqli, "recDetails", "dtl_ID", 
                        "dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause");
            
            if($valuesToBeDeleted==null && $mysqli->error){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }else if($valuesToBeDeleted==null || count($valuesToBeDeleted)==0){  //not found
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }

            //validate if details can be deleted for required fields            
            if(count($this->rtyIDs)>1){
                //get rectype for current record   
                $rectype_ID = mysql__select_value($mysqli, 'select rec_RecTypeID from Records where rec_ID='.$recID);
            }else{
                $rectype_ID = $this->rtyIDs[0];
            }
            if(array_search($rectype_ID, $rtyRequired)!==FALSE){ //this is required field
                if(!$isDeleteAll){
                    //find total count
                    $total_cnt = mysql__select_value($mysqli, "select count() from recDetails where ".
                        " where dtl_RecID = $recID and dtl_DetailTypeID = $dtyID");
                    
                }
                if($isDeleteAll || ($total_cnt == count($valuesToBeDeleted))){
                    array_push($limittedRecIDs, $recID);
                    continue;
                }
            }
            
            //delete the details
            $sql = 'delete from recDetails where dtl_ID in ('.implode(',',$valuesToBeDeleted).')';
            if ($mysqli->query($sql) === TRUE) {
               array_push($processedRecIDs, $recID);
               //update record edit date
               $rec_update['rec_ID'] = $recID;
               $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
               if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = 'Cannot update modify date. '.$ret;
               }
               
            } else {
               $sqlErrors[$recID] = $mysqli->error;
            }
        }//for recors
        
        
        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('limitted',  $limittedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);
        
        return $this->result_data;        
    }


    /**
    * change RecordType InBatch
    */
    public function changeRecordTypeInBatch(){

        $this->data['dtyID'] = '1'; //dumb value to pass validation
        
        if(!$this->_validateParamsAndCounts()){
            return false;
        }else if (count(@$this->recIDs)==0){
            return $this->result_data;
        }

        $rtyID_new = $this->data['rtyID_new'];
        $rtyName = (@$this->data['rtyName'] ? "'".$this->data['rtyName']."'" : "id:".$this->data['rtyID_new']);
        
        $mysqli = $this->system->get_mysqli();
        
        $processedRecIDs = array();//success  
        $sqlErrors = array();
        
        $now = date('Y-m-d H:i:s');
        $dtl = Array('dtl_Modified'  => $now);
        $rec_update = Array('rec_ID'  => 'to-be-filled',
                            'rec_Modified'  => $now,
                            'rec_RecTypeID'  => $rtyID_new);
                     
        $baseTag = "~changed rectype $rtyName $now";
        
        foreach ($this->recIDs as $recID) {
               //update record edit date
               $rec_update['rec_ID'] = $recID;
               
               $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
               if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = 'Cannot update modify date. '.$ret;
               }else{
                   array_push($processedRecIDs, $recID);
               }
        }//for recors
        
        
        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);
        
        return $this->result_data;        
    }
    
    /*
    * helper method
    * assign special system tags
    *       passed - count of given rec ids
    *       noaccess - no rights to edit
    *       processed - success
                _tag   _tag_error
    *       undefined - value not found
    *       errors     - sql error on search or updata
                errors_list
    */
    private function _assignTagsAndReport($type, $recordIds, $baseTag)
    {        
        if (count($recordIds)>0) {
            
            if($type=='errors'){
                $this->result_data['errors_list'] = $recordIds;    
                $recordIds = array_keys($recordIds);
            }
            
            $this->result_data[$type] = count($recordIds);
            
            $needBookmark = (@$this->data['tag']==1);
            
            if($needBookmark){
                
                if($type!='processed'){
                    $baseTag = $baseTag.' '.$type;
                }
                
                $success = tagsAssign($this->system, $recordIds, null, $baseTag);
                if($success){
                    $this->result_data[$type.'_tag'] = $baseTag;
                }else{
                    $this->result_data[$type.'_tag_error'] = $this->system->getError();
                }
            }
        }
    }
        
    
}
?>
