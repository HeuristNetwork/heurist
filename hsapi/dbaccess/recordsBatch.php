<?php

/**
* Class to perform actions in batch of records 
*   1) add/replace and delete details
*   2) change record type
*   3) add reverse parent pointer field
* 
* Controller is record_batch.php
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

// Include Composer autoloader if not already done.
require_once (dirname(__FILE__).'/../../vendor/autoload.php');

require_once (dirname(__FILE__).'/../System.php');
require_once (dirname(__FILE__).'/../dbaccess/db_records.php');
require_once (dirname(__FILE__).'/../dbaccess/db_recsearch.php');
require_once (dirname(__FILE__).'/../utilities/titleMask.php');
require_once (dirname(__FILE__).'/../../vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php');

/**
* Methods for batch actions for list of records (recIDs) OR by record type rtyID
* 
* 
* detailsAdd - add details 
* replace
* detailsReplace - replace + detailsAdd
* detailsDelete
* multiAction  - several actions in tuen: add,replace,delete
* 
* addRevercePointerForChild - Adds parent pointer field converts - converts existing 
*                             records to child record for given rectype/detailtype
* changeRecordTypeInBatch - Changes rec_RecTypeID in batch
* 
* extractPDF - extracts PDF file content is put it into DT_EXTRACTED_TEXT field
* 
*/
class RecordsBatch
{
    private $system;  
    
    /*  
    *       recIDs - list of records IDS to be processed or 'ALL'
    *       rtyID  - filter by record type
    *       dtyID  - detail field to be added,replaced or deleted
    *       for addition: val: | geo: | ulfID: - value to be added
    *       for edit sVal - search value (if missed - replace all occurences),  rVal - replace value,  subs= 1 | 0
    *       for delete: sVal, subs= 1 | 0   
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
    
    
    private $session_id = null;
    
    private $not_putify = null; //fields that will not html purified
    private $purifier = null;   //html purifier instance
    
    function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
       
       $this->session_id = @$this->data['session'];
       if($this->session_id!=null){
            mysql__update_progress($system->get_mysqli(), $this->session_id, true, '0,1');
       }
       
       //refresh list of current user groups
       $this->system->get_user_group_ids(null, true);
    }
    
    //
    // Fills the list of exclusions for purrifier
    // And inits HTML purifier 
    //
    private function _initPutifier(){
        if($this->purifier==null){
            $not_purify = array();
            if($this->system->defineConstant('DT_CMS_SCRIPT')){ array_push($not_purify, DT_CMS_SCRIPT); }
            if($this->system->defineConstant('DT_CMS_CSS')){ array_push($not_purify, DT_CMS_CSS); }
            if($this->system->defineConstant('DT_SYMBOLOGY')){ array_push($not_purify, DT_SYMBOLOGY); }
            if($this->system->defineConstant('DT_KML')){ array_push($not_purify, DT_KML); }
            if($this->system->defineConstant('DT_QUERY_STRING')){ array_push($not_purify, DT_QUERY_STRING); }
            if($this->system->defineConstant('DT_SERVICE_URL')){ array_push($not_purify, DT_SERVICE_URL); }
            
            $this->not_purify = $not_purify;
            //$this->purifier = getHTMLPurifier();  DISABLED
        }
    }

    //
    //
    //
    function  setData($data){
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
        if (!( $this->system->get_user_id()>0 )) { //not logged in
            $this->system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }
        
        if(!$this->_validateDetailType()){
            return false;
        }

        if (!( @$this->data['recIDs'])){ //record ids to be updated
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Insufficent data passed: records not defined');
            return false;
        }
        
        $mysqli = $this->system->get_mysqli();

        if($this->system->is_admin() && $this->data['recIDs']=='ALL'){
            
            $query = 'select count(*) from Records';

            $rty_ID = @$this->data['rtyID'];
            if($rty_ID >0){
                $query = ' WHERE rec_RecTypeID = '.$rty_ID;
                $this->rtyIDs = array($rty_ID);
            }
            
            $passedRecIDCnt = mysql__select_value($mysqli, $query);
            
            $this->result_data = array('passed'=>$passedRecIDCnt,
                        'noaccess'=>0,'processed'=>0);
            
            $this->recIDs = array('all');
            
        }else{
            
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
                    if($this->system->is_admin()){ //admin of database managers
                        $this->recIDs = $recIDs;
                    }else{
                        $this->recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_ID in ("
                            .implode(",",$recIDs).") and rec_OwnerUGrpID in (0,"
                            .join(",",$this->system->get_user_group_ids()).")");
                    }

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
                $this->rtyIDs = mysql__select_list($mysqli, 'Records','distinct(rec_RecTypeID)',"rec_ID in ("
                    .implode(",",$this->recIDs).")");
            }

        }        

    
        return true;
    }
    
    
    /**
    * Converts existing records to child record for given rectype/detailtype
    * (adds rever pointer field DT_PARENT_ENTITY)
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
        
        //1. find resource (child) records for given record type and detail
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
                    
                    if(!($row[3]>0)){  //rec_RecTypeID
                        array_push($childNotFound, $row[0]);
                    }else
                    if(in_array($row[2], $groups)){  //rec_OwnerUGrpID
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

        $keep_autocommit = mysql__begin_transaction($mysqli);
        
        if (count($toProcess)>0){
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
        
        
        }
        //set rst_CreateChildIfRecPtr=1 
        $query = 'UPDATE defRecStructure set rst_CreateChildIfRecPtr=1 WHERE rst_RecTypeID='
            .$this->data['rtyID'].' and rst_DetailTypeID='.$this->data['dtyID'];
            
        $res = $mysqli->query($query);
        if(!$res){
            $syserror = $mysqli->error;
            $mysqli->rollback();
            if($keep_autocommit===true) $mysqli->autocommit(TRUE);
            return $this->system->addError(HEURIST_DB_ERROR, 
                'Unable to set value in record sructure table', $syserror);
        }
        
        
        $mysqli->commit();
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);
        
        return $this->result_data;
    }
        
    /**
    *  parameters of  data: recIDs, dtyID, val (or geo or ulfID)
    */
    public function detailsAdd(){

        if( !@$this->data['val'] && @$this->data['rVal']){
            $this->data['val'] = $this->data['rVal'];
        }
        
        if (!(@$this->data['val']!=null || @$this->data['geo']!=null || @$this->data['ulfID']!=null)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed. New field value not defined");
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

        if(@$this->data['geo']==null){
            $basetype = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$dtyID);
            if($basetype=='geo'){
                $this->data['geo'] = $this->data['val'];
            }
        }
        
        $now = date('Y-m-d H:i:s');
        $dtl = Array('dtl_DetailTypeID'  => $dtyID,
                     'dtl_Modified'  => $now);
        $rec_update = Array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);
                     
        $baseTag = "~add field $dtyName $now"; //name of tag assigned to modified records
        
        if(@$this->data['geo']!=null){
            
            list($geoType, $geoValue) = prepareGeoValue($mysqli, $this->data['geo']);
            if($geoType===false){
                $this->system->addError(HEURIST_INVALID_REQUEST, $geoValue);
                return false;
            }
            $dtl['dtl_Value'] = $geoType;
            $dtl['dtl_Geo'] = $geoValue;
            //$dtl['dtl_Geo'] = array("ST_GeomFromText(\"" . $this->data['geo'] . "\")");  
            
        }else if(@$this->data['val']!=null){
            
            $this->_initPutifier();
            if(!in_array($dtyID, $this->not_purify)){
                
                //remove html script tags
                $s = trim($this->data['val']);
                $dtl['dtl_Value'] = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $s);
                                                      
                //$s = $this->purifier->purify( $this->data['val']);                                
                //$dtl['dtl_Value'] = htmlspecialchars_decode( $this->data['val'] );
            }else{
                $dtl['dtl_Value'] = $this->data['val'];    
            }
            
        }
        
        if(@$this->data['ulfID']>0){
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
    * Executes several actions in turn 
    * Queue is defined in "actions" parameter 
    * Supports add,replace,delete
    */
    public function multiAction(){
        
        $main_data = $this->data['actions'];
        
        $mysqli = $this->system->get_mysqli();
        $keep_autocommit = mysql__begin_transaction($mysqli);
        
        foreach ($main_data as $action_data) {
                
            $this->setData($action_data);

            if(@$this->data['a'] == 'add'){

                $res = $this->detailsAdd();
                
            }else if(@$this->data['a'] == 'replace'){ //returns
            
                $res = $this->detailsReplace();

            }else if(@$this->data['a'] == 'delete'){
                
                $res = $this->detailsDelete(true);
            }
            
            if($res===false){
                break;   
            }else{
                if(!@$this->result_data['processed']) $this->result_data['processed'] = 0;
                $this->result_data['processed'] = $this->result_data['processed'] 
                    +(@$res['processed']>0?$res['processed']:0);
            }
        }
        
        if($res===false){
            $mysqli->rollback();
            $res_data = $res;
        }else{
            $mysqli->commit();
            $res_data = $this->result_data;
        }
        if($keep_autocommit===true) $mysqli->autocommit(TRUE);
        
        return $res_data;
        
    }
        
    /**
    * Replace detail value for given set of records and detail type and values
    * sVal - search value to replace otherwise replace all values 
    * rVal - new value
    */
    public function detailsReplace()
    {
        if (@$this->data['rVal']==null){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed. New value not defined");
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

        $rval = $mysqli->real_escape_string($this->data['rVal']);

        $this->_initPutifier();
        
        //split value if exceeds 64K        
        $splitValues = array();
        $this->system->defineConstant('DT_EXTENDED_DESCRIPTION');
        if(@$this->data['needSplit'] && $dtyID==DT_EXTENDED_DESCRIPTION){
            
               $lim = checkMaxLength2($rval);
               //TEST $lim = 100;
               if($lim>0){
                    $dtl_Value = $this->data['rVal'];
                    
                    $s = trim($dtl_Value);
                    $dtl_Value = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $s);
                    
                    //$dtl_Value =  $this->purifier->purify($dtl_Value);
                    //$dtl_Value = htmlspecialchars_decode( $dtl_Value );
                        
                    $iStart = 0;
                    while($iStart<mb_strlen($dtl_Value)){
                        
                        array_push($splitValues, mb_substr($dtl_Value, $iStart, $lim));
                        $iStart = $iStart + $lim;
                    }
               }
        }else{
            $err_msg = checkMaxLength($dtyName, $rval);
            if($err_msg!=null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $err_msg);
                return false;
            }
        }
        
        $basetype = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$dtyID);
        
        $partialReplace = false;
        
        if(@$this->data['sVal']==null){    //value to be replaced
            //all except file type
            //$searchClause = '1=1';
            $replace_all_occurences = true;   //search value not defined replace all

            //??? why we need it if $dtyID is defined
            $types = mysql__select_list2($mysqli, 'select dty_ID from defDetailTypes where dty_Type = "file"'); // OR dty_Type = "geo"
            $searchClause = 'dtl_DetailTypeID NOT IN ('.implode(',',$types).')';
            
        }else{
            
            switch ($basetype) {
                case "freetext":
                case "blocktext":
                    if(@$this->data['subs']==1){
                        $searchClause = "dtl_Value like \"%".$mysqli->real_escape_string(@$this->data['sVal'])."%\"";
                        $partialReplace = true;
                    }else{
                        $searchClause = "dtl_Value = \"".$mysqli->real_escape_string(@$this->data['sVal'])."\"";
                    }
                    
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
        
        if($basetype=='geo'){
            list($geoType, $geoValue) = prepareGeoValue($mysqli, $this->data['rVal']);
            if($geoType===false){
                $this->system->addError(HEURIST_INVALID_REQUEST, $geoValue);
                return false;
            }
        }

        $is_multiline = count($splitValues)>0;
        
        foreach ($this->recIDs as $recID) {

            $query = 'SELECT dtl_ID, dtl_RecID '
                    .($is_multiline?'':', dtl_Value')
                    .'  FROM recDetails ';
            
            if($recID=='all' && $this->rtyIDs && @$this->rtyIDs[0]>0){
                if($this->rtyIDs && @$this->rtyIDs[0]>0){
                    
                    $query = $query.', Records '
                            .'WHERE rec_ID=dtl_RecID AND  rec_RecTypeID = '.$this->rtyIDs[0]
                            ." AND dtl_DetailTypeID = $dtyID and $searchClause";
                }
            }else{
                $query = $query."WHERE  dtl_DetailTypeID = $dtyID and $searchClause";
                //get matching detail value for record if there is one
                if($recID!='all'){
                    $query = $query." AND  dtl_RecID = $recID ";
                }
            }
            $query = $query.' ORDER BY dtl_RecID';
            
            //$valuesToBeReplaced = mysql__select_assoc2($mysqli, $query);
            
            $res = $mysqli->query($query);
            
            if($mysqli->error!=null || $mysqli->error!=''){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            //}else if($valuesToBeReplaced==null || count($valuesToBeReplaced)==0){  //not found
            //    array_push($undefinedFieldsRecIDs, $recID);
            //    continue;
            }

            $recDetailWasUpdated = false;
            $valuesToBeDeleted = array();
            $recID = 0;
            $get_next_row = true;
            
            //update the details
            while (true) {
                
                if($get_next_row) $row = $res->fetch_row();
                $get_next_row = true;
                
                if(!$row || ($recID>0 && $row[1]!=$recID) ){ 
                    
                    //next record - update changed record and 
                    if($recID>0){
                        
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
                        }else{
                            array_push($undefinedFieldsRecIDs, $recID);
                        }
                        if(count($valuesToBeDeleted)>0){
                            //remove the rest for replace all occurences
                            $sql = 'delete from recDetails where dtl_ID in ('.implode(',',$valuesToBeDeleted).')';
                            if ($mysqli->query($sql) === false) {
                                $sqlErrors[$recID] = $mysqli->error;         
                            }
                        }
                    }
                   
                    if(!$row){ //end of loop
                        break;
                    }
                    
                    $recDetailWasUpdated = false;
                    $valuesToBeDeleted = array();
                }

            //foreach ($valuesToBeReplaced as $dtlID => $dtlVal) {
                $dtlID = $row[0];
                $recID = $row[1];
                
                if($is_multiline){ //replace with several values (long text)

                    foreach($splitValues as $val){
                        $dtl['dtl_ID']  = -1;
                        $dtl['dtl_RecID']  = $recID;
                        $dtl['dtl_DetailTypeID']  = $dtyID;
                        $dtl['dtl_Value'] = $val;
                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);    
                    }
                    $recDetailWasUpdated = true;
                    
                }else{
                    $dtlVal = $row[2];
                    
                    if (!$replace_all_occurences && $partialReplace) {// need to replace sVal with rVal
                        $newVal = preg_replace("/".$this->data['sVal']."/",$this->data['rVal'],$dtlVal);
                    }else{
                        $newVal = $this->data['rVal'];
                    }
             
                    $dtl['dtl_ID'] = $dtlID;  //detail type id
                    
                    if(($basetype=='freetext' || $basetype=='blocktext')
                        && !in_array($dtyID, $this->not_purify))
                    {
                            //remove html script tags
                            $s = trim($newVal);
                            $dtl['dtl_Value'] = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $s);
                            
                            //$s = $this->purifier->purify( $newVal );                                
                            //$dtl['dtl_Value'] = htmlspecialchars_decode( $dtl['dtl_Value'] );
                    }else if($basetype=='geo'){
                        
                        $dtl['dtl_Value'] = $geoType;        
                        $dtl['dtl_Geo'] = $geoValue;             
                        
                    }else{
                        $dtl['dtl_Value'] = $newVal;        
                    }
                    $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);    
            
                    if (!is_numeric($ret)) {
                        $sqlErrors[$recID] = $ret;
                        continue;
                    }
                    $recDetailWasUpdated = true;

                }
                
                if($replace_all_occurences || $is_multiline)
                {
                    if($is_multiline) array_push($valuesToBeDeleted, $dtlID);  //= array_keys($valuesToBeReplaced);
                    
                    while ($row = $res->fetch_row()) { //gather all old detail IDs
                        if($row[1]!=$recID){
                            break;    
                        }
                        array_push($valuesToBeDeleted, $row[0]);
                    }
                    $get_next_row = false;
                }
                
            }//while
            
        }//while records
        
        $res->close();
        
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
    public function detailsDelete($unconditionally=false){

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
                if($isDeleteAll){
                    $searchClause = '1';
                }else if(@$this->data['subs']==1){
                    $unconditionally = true;
                    $searchClause = "dtl_Value like \"%".$mysqli->real_escape_string($this->data['sVal'])."%\"";
                }else {
                    $searchClause = "dtl_Value = \"".$mysqli->real_escape_string($this->data['sVal'])."\"";
                }
                break;
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
            case "geo":
                $searchClause = '1';
                $isDeleteAll = true;
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

        if(@$this->data['subs']==1){                     
            $baseTag = "~replace field $dtyName $now";
        }else{
            $baseTag = "~delete field $dtyName $now";
        }
        
        foreach ($this->recIDs as $recID) {
            //get matching detail value for record if there is one
            $query = "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause";
            $valuesToBeDeleted = mysql__select_assoc2($mysqli, $query);
            
//$valuesToBeDeleted = mysql__select_list($mysqli, "recDetails", "dtl_ID", "dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause");
            
            if($valuesToBeDeleted==null && $mysqli->error){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }else if($valuesToBeDeleted==null || count($valuesToBeDeleted)==0){  //not found
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }

            
            if(!$unconditionally){
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
            }
            
            if(@$this->data['subs']==1){                    
                //this is not real delete - this is replacement of value part with empty string
                $now = date('Y-m-d H:i:s');
                $dtl = Array('dtl_Modified'  => $now);
                    
                
                foreach ($valuesToBeDeleted as $dtlID => $dtlVal) {
                
                    $newVal = preg_replace("/".$this->data['sVal']."/",'',$dtlVal);
                    
                    if(trim($newVal)==''){
                        $sql = 'delete from recDetails where dtl_ID = '.$dtlID;
                        if ($mysqli->query($sql) === TRUE) {
                            $sqlErrors[$recID] = $mysqli->error;
                        }
                        
                    }else{
                        $dtl['dtl_ID'] = $dtlID;  
                        $dtl['dtl_Value'] = $newVal;        
                                            
                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);    
                
                        if (!is_numeric($ret)) {
                            $sqlErrors[$recID] = $ret;
                            continue;
                        }
                    }
                }                
                
                $sql = true;
                
            }else{
                //delete the details
                $sql = 'delete from recDetails where dtl_ID in ('.implode(',',array_keys($valuesToBeDeleted)).')';
            }
            
            if ($sql===TRUE || $mysqli->query($sql) === TRUE) {
               array_push($processedRecIDs, $recID);
               //update record edit date
               $rec_update['rec_ID'] = $recID;
               $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
               if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = 'Cannot update modify date. '.$ret;
               }else{
                    if(!recordUpdateTitle($this->system, $recID, null, null)){
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
    * Changes rec_RecTypeID in batch
    * 
    * rtyID_new - new record type
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
                   $rec_update2 = Array('rec_ID'  => $recID, 'rec_Title'  => $new_title);
                   mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update2);
               }
        }//for recors
        
        
        
        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);
        
        return $this->result_data;        
    }


    /**
    * Extracts text content from PDF file that is defined in dtyID field 
    * and put this content to DT_EXTRACTED_TEXT field
    * 
    * for recIDs
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
        
        $tot_count = count($this->recIDs);
        
        $execution_counter = 0;
        
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

                                if(true){ 
                                    $pdf    = $parser->parseFile($file);

                                    if(false){
                                        $text = $pdf->getText();
                                    }else{
                                        // Retrieve all pages from the pdf file.
                                        $pages  = $pdf->getPages();
                                        $page_cnt = 0; 
                                        $text = '';
                                        // Loop over each page to extract text.
                                        foreach ($pages as $page) {

                                            $pagetext = $page->getText();

                                            if(mb_detect_encoding($pagetext, 'UTF-8', true)===false){

                                                $pagetext = iconv("UTF-8","UTF-8//IGNORE", $pagetext); // to remove

                                                //$pagetext = Encoding::fixUTF8($pagetext);
                                                if(mb_detect_encoding($pagetext, 'UTF-8', true)===false){
                                                    $pagetext = 'Page '.$page_cnt.' can not be converted to UTF-8';
                                                }    
                                            }

                                            $text = $text . $pagetext;
                                            if(strlen($text)>60000 || $page_cnt>10){
                                                break;
                                            }
                                            $page_cnt++;


                                        }//foreach     
                                    }

                                }else{
                                    //debug without real parsing 
                                    sleep(1);
                                    $text = 'test';
                                    $skippedParseEx[$recID] = $file.' Debug parse exception';
                                }

                                if($text==null || mb_strlen(trim($text))==0){
                                    $skippedEmpty[$recID] = $file;
                                }else{
                                    $orig_len = mb_strlen($text);
                                    $maxlen = 20000;
                                    if($orig_len>$maxlen){ //split by 20k
                                            
                                            $k=0;
                                            while (strlen($text)>$maxlen && $k<3){
                                                $details[] = mb_substr($text,0,$maxlen);
                                                $text = mb_substr($text,$maxlen);
                                                $k++;
                                            }
                                            if($k>2){
                                                $len = count($details)-1;
                                                $details[$len] =
                                                    $details[$len]
                                                    .' <more text is available. Remaining text has not been extracted from file>';
                                            }
                                   }else{
                                        $details[] = $text;       
                                   }
                                }

                            } catch (\Exception $ex) {
                                //throw new ParseException($ex);
                                $skippedParseEx[$recID] = $file.' '.print_r($ex, true);
                                //error_log('parse exception '.$recID.'  '.$file);
                                //error_log(print_r($ex,true));
                            }
                        }else{
                            $skippedNoPDF[$recID] = 'PDF file not found';
                        }
                    }
                }
            }//details
            
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
                if(true){
                    $dtl['dtl_RecID'] = $recID;
                    foreach($details as $text){
                        $dtl['dtl_Value'] = $text;
                        if(mb_detect_encoding($dtl['dtl_Value'], 'UTF-8', true)===false){
                            $sqlErrors[$recID] = 'Extracted text has not valid utf8 encoding';
                            break;
                            /*
                            $query = 'INSERT INTO recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value) VALUES ('
                            .$dtl['dtl_RecID'].', '.$dtl['dtl_DetailTypeID'].', '
                            .'CONVERT( CAST(? AS BINARY) USING utf8mb4))';
                            
                            $ret = mysql__exec_param_query($mysqli, $query, array($dtl['dtl_Value']));
                            */
                        }else{   
                            $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
                            if (!is_numeric($ret)) {
                                    $sqlErrors[$recID] = $ret;
                                    break;
                            }
                        }
                        
                        
                        /*DEBUG
                        $offset = 17000;
                        while($offset<mb_strlen($text)){
                            $dtl['dtl_Value'] = mb_substr($text,$offset,5000);
                            if(mb_detect_encoding($dtl['dtl_Value'], 'UTF-8', true)===false){
                                $sqlErrors[$recID] = 'String is not valid utf8';
                                break;
                            }else{   
   
                                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
                                if (!is_numeric($ret)) {
                                    $sqlErrors[$recID] = $ret; // $dtl['dtl_Value'];//($ret.'  '.$offset);
                                    break;
                                }
                                $offset = $offset + 5000;
                            }
                        }
                        if(@$sqlErrors[$recID]) break;
                        */
                        
                    }//foreach
                    if(@$sqlErrors[$recID]) continue;

                    //update record edit date
                    $rec_update['rec_ID'] = $recID;
                    $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
                    if (!is_numeric($ret)) {
                        $sqlErrors[$recID] = 'Cannot update record "Modify date". '.$ret;
                    }
                }
                $processedRecIDs[] = $recID;
            }
            
            
            if($this->session_id!=null){
                //check for termination and set new value
                $execution_counter++;
                $session_val = $execution_counter.','.$tot_count;
                $current_val = mysql__update_progress($mysqli, $this->session_id, false, $session_val);
                if($current_val=='terminate'){ //session was terminated from client side
                    break;
                }
            }

        }//for records
        
        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skippedNoPDF, null);  //no pdf assigned
        $this->_assignTagsAndReport('limited',   $skippedRecIDs, null);  //value already defined
        $this->_assignTagsAndReport('parseexception', $skippedParseEx, null);  
        $this->_assignTagsAndReport('parseempty', $skippedEmpty, null);  
        $this->_assignTagsAndReport('errors',  $sqlErrors, null);//$baseTag);
        
        return $this->result_data;        
    }
    
    //
    // remove long-time opeartion from session table
    //
    public function removeSession(){
        if($this->session_id!=null){
            mysql__update_progress($this->system->get_mysqli(), $this->session_id, false, 'REMOVE');
        }
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
            
            if($type=='errors' || $type=='parseexception' || $type=='parseempty'){
                $this->result_data[$type.'_list'] = $recordIds;    
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
