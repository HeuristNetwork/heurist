<?php

    /**
    * Class to update records details in batch
    * to be renamed since in coinside with name scheme for entity
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
// Include Composer autoloader if not already done.
include dirname(__FILE__).'/../../vendor/autoload.php';

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../dbaccess/db_records.php');
require_once (dirname(__FILE__).'/../dbaccess/db_recsearch.php');
require_once (dirname(__FILE__).'/../utilities/titleMask.php');

class DbRecDetails
{
    private $system;  
    
    /*  
    *       recIDs - list of records IDS to be processed
    *       rtyID  - filter by record type
    *       dtyID  - detail field to be added
    *       for addition: val: | geo: | ulfID: - value to be added
    *       for edit sVal - search value (if missed - replace all occurences),  rVal - replace value
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
    limited  
    errors    - sql error on search or updata
    */
    private $result_data = array();
    
    
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
    }

    
    public function getReport(){
        return $this->result_data;
    }
    
    //
    //
    //
    private function _validateDetailType(){
        
        $rtyID = @$this->data['rtyID'];
        $dtyID = $this->data['dtyID'];    //detail to be affected
        
        if ($rtyID && !(ctype_digit($rtyID) && $rtyID>0)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter record type id $rtyID");
            return false;
        }

        if(!(ctype_digit($dtyID) && $dtyID>0)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter detail type id $dtyID");
            return false;
        }
        
        return true;
    }
    
    //
    //
    //
    private function _validateParamsAndCounts()
    {
        if (!( $this->system->get_user_id()>0 )) {
            $this->system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }
        
        if(!$this->_validateDetailType()){
            return false;
        }

        if (!( @$this->data['recIDs'])){ //record ids to be updated
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Insufficent data passed: records');
            return false;
        }
        
        $mysqli = $this->system->get_mysqli();

        //normalize recIDs to an array for code below
        $recIDs = prepareIds($this->data['recIDs']);
        
        $rtyID = @$this->data['rtyID'];
        
        $passedRecIDCnt = count(@$recIDs);
        
        if ($passedRecIDCnt>0) {//check editable access for passed records
        
            if($rtyID){ //filter for record type
                $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_RecTypeID = $rtyID and rec_ID  in ("
                                    .implode(",",$recIDs).")");
                $passedRecIDCnt = count(@$recIDs);
            }
            if($passedRecIDCnt>0){
                //exclude records if user has no right to edit
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
    * convert existing records to child record for givent rectype/detailtype
    * 
    * rtyID - record type that is parent 
    * dtyID - pointer detail type 
    * 
    */
    public function addRevercePointerForChild(){
        
        
        if (! $this->system->is_admin() ) {
            $this->system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }
        
        if(!$this->system->defineConstant('DT_PARENT_ENTITY')){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Field type 2-247 is not defined in this database');
            return false;
        }
        
        if(!$this->_validateDetailType()){
            return false;
        }
        
        $allow_multi_parent = ($this->data['allow_multi_parent']==true);
       
        $mysqli = $this->system->get_mysqli(); 
        
        //1. find resource (child) records for given recordtype and detail
        $query = 'SELECT dtl_RecID as parent_id, d.dtl_Value as child_id, child.rec_OwnerUGrpID, child.rec_RecTypeID, child.rec_Title '
            .'FROM  recDetails d LEFT JOIN Records child on child.rec_ID=d.dtl_Value, Records parent '
            .'WHERE d.dtl_RecID=parent.rec_ID and parent.rec_RecTypeID='
            .$this->data['rtyID'].' and d.dtl_DetailTypeID='.$this->data['dtyID'];

//error_log($query);
            
        $res = $mysqli->query($query);
        if ($res){
          
                $passedValues = 0;       //total values found
                $inAccessibleRecCnt = 0; //no rights for child records
                $cntDisambiguation = 0;  //more than one parent record
                
                $childNotFound = array();
                $parentRecords = array();
                $childRecords = array();
            
                $toProcess = array();
            
                $groups = $this->system->get_user_group_ids();
                array_push($groups, 0);
                
                while ($row = $res->fetch_row()){
                    
                    $passedValues++;
                    
                    if(!($row[3]>0)){
                        array_push($childNotFound, $row[0]);
                    }else
                    if(in_array($row[2], $groups)){
                        if($allow_multi_parent || !@$childRecords[$row[1]]){
                            
                            $toProcess[] = $row;  //parent_id,child_id,0,child_rectype,child_title
                            if(!in_array($row[0],$parentRecords)){
                                $parentRecords[] = $row[0];
                            }
                            $childRecords[$row[1]] = 1;
                            
                        }else{
                            $cntDisambiguation++;
                        }
                    }else{
                        $inAccessibleRecCnt++;
                    }
                }
                $res->close();
        }else{
            $this->system->addError(HEURIST_DB_ERROR, "Can't find child records ".$mysqli->error );
            return false;    
        }
        
        $this->result_data = array('passed'=> $passedValues,  
                                   'noaccess'=> $inAccessibleRecCnt,
                                   'disambiguation'=> $cntDisambiguation);

        if (count($toProcess)==0){
            return $this->result_data;
        }
/*debug
error_log('count '.count($childNotFound).'  '.count($toProcess).'  '.print_r(  $this->result_data,true) );
    return $this->result_data;
*/        
        //3. add reverse pointer field in child record to parent record 
        $processedParents = array();
        $childInserted = array();   
        $childUpdated = array();    
        $childAlready = array();    
        $titlesFailed = array();
        $childMiltiplied = array();
        
        $keep_autocommit = mysql__begin_transaction($mysqli);
        
        foreach ($toProcess as $row) {
            //parent_id,child_id,0,child_rectype,child_title
            
            //check if child record has parent already
            $parent_id = $row[0];
            $child_id = $row[1];
            $res = addReverseChildToParentPointer($mysqli, $child_id, $parent_id, 0, $allow_multi_parent);
            
            if($res<0){
                $syserror = $mysqli->error;
                $mysqli->rollback();
                if($keep_autocommit===true) $mysqli->autocommit(TRUE);
                return $this->system->addError(HEURIST_DB_ERROR, 
                    'Unable to insert reverse pointer for child record ID:'.$child_id.' - ', $syserror);
            }else if($res==0){ 
                 array_push($childAlready, $child_id);
            }else{
                
                if($res==2){ 
                    if($allow_multi_parent){
                        //if(!in_array($child_id, $childInserted)) array_push($childInserted, $child_id);    
                        if(!in_array($child_id, $childMiltiplied)) array_push($childMiltiplied, $child_id);    
                    }else{
                        array_push($childUpdated, $child_id);    
                    }
                    
                }else{
                    array_push($childInserted, $child_id);    
                }
                if(!in_array($parent_id, $processedParents)){
                    array_push($processedParents, $parent_id);    
                }
                
                //update record title for child record
                $child_rectype = $row[3];
                $child_title = $row[4];
                    
                if(!recordUpdateTitle($this->system, $child_id, $child_rectype, $child_title)){
                    $titlesFailed[] = $child_id;
                }
            }
            
            
            
        } //foreach
        
        $this->result_data['processedParents'] = $processedParents;
        $this->result_data['childInserted'] = $childInserted;
        $this->result_data['childUpdated'] = $childUpdated;
        $this->result_data['childAlready'] = $childAlready;
        $this->result_data['childMiltiplied'] = $childMiltiplied;
        $this->result_data['titlesFailed'] = $titlesFailed;
        
        $mysqli->commit();
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);
        
        return $this->result_data;
    }
        
    /**
    *  parameters of  data: recIDs, dtyID, val (or geo or ulfID)
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
        $query = "SELECT rst_RecTypeID,rst_MaxValues FROM defRecStructure WHERE rst_DetailTypeID = $dtyID and rst_RecTypeID in ("
                        .implode(',', $this->rtyIDs).')';
        $rtyLimits = mysql__select_assoc2($mysqli, $query);

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
        $limitedRecIDs = array();        //over limit - skip
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
            
            $rectype_ID = $row[0];

            if (!array_key_exists($rectype_ID,$rtyLimits)) { //limit not defined
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }else if (intval($rtyLimits[$rectype_ID])>0 && $row[1]>0 && ($rtyLimits[$rectype_ID] - $row[1]) < 1){
                array_push($limitedRecIDs, $recID);  //over limit - skip
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
            }else{
                //update record title
                if(!recordUpdateTitle($this->system, $recID, $rectype_ID, null)){
                    $sqlErrors[$recID] = 'Cannot update record title';
                }
            }
        }
        
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('limited',  $limitedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

        
        
        return $this->result_data;        
    }

    /**
    * Replace detail value for given set of records and detail type and values
    * sVal - search value to replace otherwise replace all values 
    * rVal - new value
    */
    public function detailsReplace(){

        if (!@$this->data['rVal']){
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

        if(!@$this->data['sVal']){  
            $searchClause = '1=1';
            $replace_all_occurences = true;  
        }else{
            
            $basetype = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$dtyID);
            switch ($basetype) {
                case "freetext":
                case "blocktext":
                    $searchClause = "dtl_Value like \"%".$mysqli->real_escape_string(@$this->data['sVal'])."%\"";
                    $partialReplace = true;
                    break;
                case "enum":
                case "relationtype":
                case "float":
                case "integer":
                case "resource":
                case "date":
                    $searchClause = "dtl_Value = \"".$mysqli->real_escape_string(@$this->data['sVal'])."\"";
                    $partialReplace = false;
                    break;
                default:
                    $this->system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by value-replace service");
                    return false;
            }
        
            $replace_all_occurences = false;
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
            $query = "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause";
            $valuesToBeReplaced = mysql__select_assoc2($mysqli, $query);
            
            if($mysqli->error!=null || $mysqli->error!=''){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }else if($valuesToBeReplaced==null || count($valuesToBeReplaced)==0){  //not found
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }
            
            //update the details
            $recDetailWasUpdated = false;
            $valuesToBeDeleted = array();
            
            foreach ($valuesToBeReplaced as $dtlID => $dtlVal) {
                
                if (!$replace_all_occurences && $partialReplace) {// need to replace sVal with rVal
                    $newVal = preg_replace("/".$this->data['sVal']."/",$this->data['rVal'],$dtlVal);
                }else{
                    $newVal = $this->data['rVal'];
                }
         
                $dtl['dtl_ID'] = $dtlID;
                $dtl['dtl_Value'] = $newVal;
                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
            
                if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = $ret;
                    continue;
                }
                $recDetailWasUpdated = true;

                if($replace_all_occurences && count($valuesToBeReplaced)>1){
                    $valuesToBeDeleted = array_keys($valuesToBeReplaced);
                    array_shift($valuesToBeDeleted);
                    break;
                }
            }//for
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
            if(count($valuesToBeDeleted)>0){
                //remove the rest for repalce all occurences
                $sql = 'delete from recDetails where dtl_ID in ('.implode(',',$valuesToBeDeleted).')';
                if ($mysqli->query($sql) === TRUE) {
                    $sqlErrors[$recID] = $mysqli->error;         
                }
            }
                    
            
        }//for recors
        
        //update record title
        foreach ($processedRecIDs as $recID){
                if(!recordUpdateTitle($this->system, $recID, null, null)){
                    $sqlErrors[$recID] = 'Cannot update record title';
                }
        }
        
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
        $limitedRecIDs = array(); //it is npt possible to delete requried fields
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
                    array_push($limitedRecIDs, $recID);
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
               }else{
                    if(!recordUpdateTitle($this->system, $recID, $rectype_ID, null)){
                        $sqlErrors[$recID] = 'Cannot update record title';
                    }
               }
               
            } else {
               $sqlErrors[$recID] = $mysqli->error;
            }
        }//for recors
        
        
        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('limited',  $limitedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);
        
        return $this->result_data;        
    }
    
    
    /**
    * assign child detail type (2-272) that refers to parent record
    * or change given detail type to 2-272
    */
    public function setRecordAsChild(){
        // parameters
        // 1. required. detail type (pointer) from parent record - to detect what record types will be affected
        // 2. optional. detail type in child record that already has backward reference to parent record - 
        //             if hot defined ALL records of given record type will be affected
        //             this detail will be replaced to 
        
        
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
                   //update title
                   $new_title = TitleMask::fill($recID);
                   $rec_update = Array('rec_ID'  => $recID, 'rec_Title'  => $new_title);
                   mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
               }
        }//for recors
        
        
        
        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);
        
        return $this->result_data;        
    }


    /**
    * Extract PDF change RecordType InBatch
    */
    public function extractPDF(){

        //default value to pass validation
        if(!@$this->data['dtyID']){
            if(!defined('DT_EXTRACTED_TEXT')){
                $this->system->addError(HEURIST_NOT_FOUND, 'Field "Extracted text" (2-652) not found');
                return false;
            }
            $this->data['dtyID'] = DT_EXTRACTED_TEXT;    
        }
        
        if(!$this->_validateParamsAndCounts()){
            return false;
        }else if (count(@$this->recIDs)==0){
            return $this->result_data;
        }

        $mysqli = $this->system->get_mysqli();
        
        $processedRecIDs = array();//success  
        $sqlErrors = array();
        $skippedRecIDs = array(); //values already defined
        
        $skippedNoPDF   = array();  //no assosiated records
        $skippedEmpty   = array();  //empty
        $skippedParseEx = array();  //parse exception
        
        
                     
        $now = date('Y-m-d H:i:s');
        $dtl = Array('dtl_DetailTypeID'  => $this->data['dtyID'],
                     'dtl_Modified'  => $now);
        $rec_update = Array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);
        
        $baseTag = "~extract pdf $now";

        $parser = new \Smalot\PdfParser\Parser();
        
        foreach ($this->recIDs as $recID) {
            
            $sql = 'select count(dtl_ID) from recDetails where dtl_RecID='.$recID.' AND dtl_DetailTypeID = '.$this->data['dtyID'];
            $isExistsAlready = mysql__select_value($mysqli, $sql)>0;

            if($isExistsAlready){
               $skippedRecIDs[] = $recID;
               continue;
            }
            
            $details = array();
            $hasPDFs = false;
        
            $record = recordSearchByID($this->system, $recID, array('file'));
            foreach ($record['details'] as $dtl_ID => $detailValue){
    // 2. find assosiated pdf files 
                if(is_array($detailValue))
                foreach ($detailValue as $id => $fileValue){
                    if($fileValue['file']['fxm_MimeType']=='application/pdf'){
                        
                        $hasPDFs = true;
                        
                        $file = $fileValue['file']['fullPath'];
                        $file = resolveFilePath($file);
                        if(file_exists($file)){
        // 3. Parse pdf file
                            try{
                                $pdf    = $parser->parseFile($file);
                                $text = $pdf->getText();
                                if($text==null || strlen(trim($text))==0){
                                    $skippedEmpty[] = $recID;
                                }else{
                                    if(strlen($text)>64000){
                                        $text = substr($text,0,64000)
                                            .' <more text is available. Remaining text has not been extracted from file>';
                                    }
                                    $details[] = $text;    
                                }
                            } catch (\Exception $ex) {
                                //throw new ParseException($ex);
                                $skippedParseEx[$recID] = 'PDF parser exception';//.print_r($ex,true);
                            }
                        }else{
                            $skippedNoPDF[$recID] = 'PDF file not found';
                        }
                    }
                }
            }
            
            if(!$hasPDFs){
                $skippedNoPDF[] = $recID;
            }else
            if(count($details)>0){
    
                /*
                // 4. remove old 2-652 "Extracted text"             
                $sql = 'delete from recDetails where dtl_RecID='.$recID.' AND dtl_ID = '.$this->data['dtyID'];
                if ($mysqli->query($sql) !== TRUE) {
                    $sqlErrors[$recID] = 'Cannot remove dt#'.$this->data['dtyID'].' for record # '.$recID.'  '.$mysqli->error;
                }else{}
                */    
    // 5. Add new values to 2-652 - one entry per file
                    $dtl['dtl_RecID'] = $recID;
                    foreach($details as $text){
                        $dtl['dtl_Value'] = $text;
                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
                        if (!is_numeric($ret)) {
                            $sqlErrors[$recID] = $ret;
                            break;
                        }
                    }
                    if(@$sqlErrors[$recID]) continue;

                    //update record edit date
                    $rec_update['rec_ID'] = $recID;
                    $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
                    if (!is_numeric($ret)) {
                        $sqlErrors[$recID] = 'Cannot update record "Modify date". '.$ret;
                    }
                    $processedRecIDs[] = $recID;
            }

        }//for recodrs
        
        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skippedNoPDF, null);  //no pdf assigned
        $this->_assignTagsAndReport('limited',   $skippedRecIDs, null);  //value already defined
        $this->_assignTagsAndReport('parseexception', $skippedParseEx, null);  
        $this->_assignTagsAndReport('empty', $skippedEmpty, null);  
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);
        
        return $this->result_data;        
    }
    
/*    
public methods

    detailsAdd
    detailsReplace
    detailsDelete
    changeRecordTypeInBatch    
    addRevercePointerForChild
    setRecordAsChild
    extractPDF
    -------------
*/

// all tags routine must be in dbUsrTags
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
            
            if($baseTag!=null && $needBookmark){
                
                if($type!='processed'){
                    $baseTag = $baseTag.' '.$type;
                }
                
                $success = $this->_tagsAssign($recordIds, null, $baseTag);
                if($success){
                    $this->result_data[$type.'_tag'] = $baseTag;
                }else{
                    //error on tag assign
                    $this->result_data[$type.'_tag_error'] = $this->system->getError();
                }
            }
        }
    }
    
    /**
    * Assign tags to records and bookmark records
    *
    * @param mixed $system
    * @param mixed $record_ids - array of record ids
    * @param mixed $tag_ids - array of tag ids
    * @param mixed $tag_names - if tag ids are not defined, we use $tag_names to get ids
    * @param mixed $ugrID
    * 
    * returns false if error
    *     or array ('tags_added'=>$tag_count, 'bookmarks_added'=>$bookmarks_added)
    */
    private function _tagsAssign($record_ids, $tag_ids, $tag_names=null, $ugrID=null){

        $system = $this->system;
        
        if($ugrID<1) $ugrID = $system->get_user_id();
        
        if (!$system->has_access($ugrID)) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{
            //find tag_ids by tag name
            if($tag_ids==null){
                if($tag_names==null){
                    $system->addError(HEURIST_INVALID_REQUEST, 'Tag name is not defined');
                    return false;
                }else{
                    
                    $tag_ids = $this->_tagGetByName(array_filter(explode(',', $tag_names)), true, $ugrID);
                }
            }
            if( !is_array($record_ids) || count($record_ids)<0 ){
                $system->addError(HEURIST_INVALID_REQUEST, 'Record ids are not defined');
                return false;
            }

            if( !is_array($tag_ids) || count($tag_ids)<0 ){
                $system->addError(HEURIST_INVALID_REQUEST, 'Tags ids either not found or not defined');
                return false;
            }

            $mysqli = $system->get_mysqli();

            //assign links
            $insert_query = 'insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
                . 'select rec_ID, tag_ID from usrTags, Records '
                . ' where rec_ID in (' . implode(',', $record_ids) . ') '
                . ' and tag_ID in (' . implode(',', $tag_ids) . ')'
                . ' and tag_UGrpID = '.$ugrID;
            $res = $mysqli->query($insert_query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR,"Cannot assign tags", $mysqli->error );
                return false;
            }
            $tag_count = $mysqli->affected_rows;

            /*$new_rec_ids = mysql__select_column($mysqli,
            'select rec_ID from Records '
            .' left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.$ugrID
            .' where bkm_ID is null and rec_ID in (' . join(',', $record_ids) . ')');*/

            //if $ugrID is not a group - create bookmarks
            $bookmarks_added = 0;
            if ($ugrID==$system->get_user_id() ||
                mysql__select_value($mysqli, 'select ugr_Type from sysUGrps where ugr_ID ='.$ugrID)=='user')
            { //not bookmarked yet
                $query = 'insert into usrBookmarks '
                .' (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)'
                .' select ' . $ugrID . ', now(), now(), rec_ID from Records '
                .' left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.$ugrID
                .' where bkm_ID is null and rec_ID in (' . implode(',', $record_ids) . ')';

                //$stmt = $mysqli->query($query);

                $res = $mysqli->prepare($query);

                if(!$res){
                    $system->addError(HEURIST_DB_ERROR,"Cannot add bookmarks", $mysqli->error);
                    return false;
                }
                $bookmarks_added = $mysqli->affected_rows;
            }

            return array('tags_added'=>$tag_count, 'bookmarks_added'=>$bookmarks_added);
        }
    }
    
    /**
    * Get tag IDs by tag names
    *
    * @param mixed $tag_names
    * @param mixed $ugrID
    */
    private function _tagGetByName($tag_names, $isadd, $ugrID=null){

        $system = $this->system;
        
        if (!$ugrID) {
            $ugrID = $system->get_user_id();
        }
        if(!$ugrID) return null;

        if(is_string($tag_names)){
            $tag_names = explode(",", $tag_names);
        }

        $tag_ids = array();
        foreach ($tag_names as $tag_name) {
            $tag_name = preg_replace('/\\s+/', ' ', trim($tag_name));
            if(strlen($tag_name)>0){

                $res = mysql__select_value($system->get_mysqli(), 'select tag_ID from usrTags where lower(tag_Text)=lower("'.
                    $system->get_mysqli()->real_escape_string($tag_name).'") and tag_UGrpID='.$ugrID);
                if($res){
                    array_push($tag_ids, $res);
                }else if($isadd){
                    $res = $this->_tagSave( array('tag_UGrpID'=>$ugrID, 'tag_Text'=>$tag_name));
                    if($res){
                        array_push($tag_ids, $res);
                    }
                }
            }
        }
        $tag_ids = array_unique($tag_ids, SORT_NUMERIC);

        return $tag_ids;
    }        

    /**
    * insert/update tag
    *
    * @param mixed $system
    * @param mixed $tag  - array [ ID, UGrpID, Text, Description, AddedByImport ]
    * 
    * return false or new tag_ID
    */
    private function _tagSave($tag){
        
        $system = $this->system;

        if(!@$tag['tag_Text']){
            $system->addError(HEURIST_INVALID_REQUEST, "Text not defined");
            return false;
        }

        if (!$system->has_access(@$tag['tag_UGrpID'])) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if(intval(@$tag['tag_ID'])<1){
                $samename = $this->_tagGetByName($tag['tag_Text'], false, $tag['tag_UGrpID']);

                if(count($samename)>0){
                    $tag['tag_ID'] = $samename[0];
                }
            }

            $res = mysql__insertupdate($system->get_mysqli(), "usrTags", "tag", $tag);
            if(is_numeric($res) && $res>0){
                return $res; //returns affected record id
            }else{
                $system->addError(HEURIST_DB_ERROR, 'Cannot update record in database', $res);
                return false;
            }

        }
    }    
}
?>
