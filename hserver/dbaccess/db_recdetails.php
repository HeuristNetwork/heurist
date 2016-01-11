<?php

    /**
    * Library to update records details in batch
    * 
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

    /**
    * 
    * 
    * @param mixed $system
    * @param mixed $data
    *       recIDs: - list of records IDS to be processed
    *       rtyID  - filter by record type
    *       dtyID:  - detail field to be added
    *       val: | geo: | ulfID: - value to be added
    *       tag  = 0|1  - add system tag to mark processed records
    * 
    * result array of counts
    *       passed - count of given rec ids
    *       noaccess - no rights to edit
    *       processed - success
    *       undefined - max values definition not founf
    *       limitted  - new value can not be added because max allowed number of values achieved
    *       errors     - sql error on search or updata
    */
    function detailsAdd($system, $data){

        if ( $system->get_user_id()<1 ) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }

        if (!( @$data['recIDs'] && @$data['dtyID'] && (@$data['val'] || @$data['geo'] || @$data['ulfID']))){
            $system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed");
            return false;
        }
        
        $rtyID = @$data['rtyID'];
        $dtyID = $data['dtyID'];
        
        if ($rtyID && !(ctype_digit($rtyID) && $rtyID>0)){
            $system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter record type id $rtyID");
            return false;
        }
        

        if(!(ctype_digit($dtyID) && $dtyID>0)){
            $system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter detail type id $dtyID");
            return false;
        }

        $mysqli = $system->get_mysqli();

        $result = array();
        
        $needBookmark = (@$data['tag']==1);
        
        //normalize recIDs to an array for code below
        $recIDs = $data['recIDs'];
        if ($recIDs && ! is_array($recIDs)){
            $recIDs = array($recIDs);
        }
        $passedRecIDCnt = count(@$recIDs);
        if ($passedRecIDCnt) {//check editable access for passed records
            if($rtyID){
                $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_RecTypeID = $rtyID and rec_ID  in (".implode(",",$recIDs).")");
                $passedRecIDCnt = count(@$recIDs);
            }
            //exclude records user has no right to edit
            $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_ID in (".implode(",",$recIDs).") and rec_OwnerUGrpID in (0,".join(",",$system->get_user_group_ids()).")");
            $inAccessibleRecCnt = $passedRecIDCnt - count(@$recIDs);
        }

        $result['count'] = array('passed'=> ($passedRecIDCnt?$passedRecIDCnt:0),
                                 'noaccess'=> (@$inAccessibleRecCnt ?$inAccessibleRecCnt :0));


        if (count($recIDs) == 0){
            $result['count']['processed'] = 0;
            return $result;
        }else if($rtyID){
            $rtyIDs = array($rtyID);
        }else {
            $rtyIDs = mysql__select_list($mysqli, 'Records','distinct(rec_RecTypeID)',"rec_ID in (".implode(",",$recIDs).")");
        }
        
        
        $dtyName = (@$data['dtyName'] ? "'".$data['dtyName']."'" : "(".$data['dtyID'].")");
        
        //get array of max allowed values per record type
        $rtyLimits = mysql__select_assoc($mysqli, "defRecStructure","rst_RecTypeID","rst_MaxValues",
                        "rst_DetailTypeID = $dtyID and rst_RecTypeID in (".implode(",",$rtyIDs).")");

        $now = date('Y-m-d H:i:s');
        $dtl = Array('dtl_DetailTypeID'  => $dtyID,
                     'dtl_Modified'  => $now);
                     
        $baseTag = "~add field $dtyName $now"; //name of tag assigned to modified records
        
        if(@$data['val']){
            $dtl['dtl_Value'] = $data['val'];
        }
        if(@$data['geo']){
            $dtl['dtl_Geo'] = array("geomfromtext(\"" . $data['geo'] . "\")");  
        }
        if(@$data['ulfID']){
            $dtl['dtl_UploadedFileID'] = $data['ulfID'];
        }
        
        $undefinedFieldsRecIDs = array(); //limit not defined
        $processedRecIDs = array();       //success  
        $limittedRecIDs = array();        //over limit - skip
        $sqlErrors = array();

        foreach ($recIDs as $recID) {
            //check field limit for this record
            $query = "select rec_RecTypeID, tmp.cnt from Records ".
            "left join (select dtl_RecID as recID, count(dtl_ID) as cnt ".
            "from recDetails ".
            "where dtl_RecID = $recID and dtl_DetailTypeID = $dtyID group by dtl_RecID) as tmp on rec_ID = tmp.recID ".
            "where rec_ID = $recID";

            $res = $mysqli->query($query);
            if(!$res){
                array_push($undefinedFieldsRecIDs, $recID); //can not retrieve limit 
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
        }
        
        //create report
        _prepareResultReport($system, $result, $needBookmark, 
            $baseTag, $processedRecIDs, $undefinedFieldsRecIDs, $limittedRecIDs, $sqlErrors);
        
        return $result;        
    }

    /**
    * Replace detail value for given set of records and detail type and values
    *
    * @param mixed $system
    * @param mixed $data
    *       recIDs - list of records IDS to be processed
    *       dtyID  - detail field to be added
    *       sVal - search value
    *       rVal - replace value
    *       tag  = 0|1  - add system tag to mark processed records
    * 
    * result array of counts
    *       passed - count of given rec ids
    *       noaccess - no rights to edit
    *       processed - success
    *       undefined - value not found
    *       errors     - sql error on search or updata
    *
    */
    function detailsReplace($system, $data){

        if ( $system->get_user_id()<1 ) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }

        if (!( @$data['recIDs'] && @$data['dtyID'] && @$data['sVal'] && @$data['rVal'])){
            $system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed");
            return false;
        }
        
        $rtyID = @$data['rtyID'];
        $dtyID = $data['dtyID'];
        
        if($rtyID && !(ctype_digit($rtyID) && $rtyID>0)){
            $system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter $rtyID");
            return false;
        }
        if(!(ctype_digit($dtyID) && $dtyID>0)){
            $system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter $dtyID");
            return false;
        }
        
        $dtyName = (@$data['dtyName'] ? "'".$data['dtyName']."'" : "(".$data['dtyID'].")");
        
        $mysqli = $system->get_mysqli();
        $basetype = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes where dty_ID = '.$dtyID);
        switch ($basetype) {
            case "freetext":
            case "blocktext":
                $searchClause = "dtl_Value like \"%".$mysqli->real_escape_string($data['sVal'])."%\"";
                $partialReplace = true;
                break;
            case "enum":
            case "relationtype":
            case "float":
            case "integer":
            case "resource":
            case "date":
                $searchClause = "dtl_Value = \"".$mysqli->real_escape_string($data['sVal'])."\"";
                $partialReplace = false;
                break;
            default:
                $system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by value-replace service");
                return false;
        }
        
        
        
        $result = array();
        $needBookmark = (@$data['tag']==1);
        
        //normalize recIDs to an array for code below
        $recIDs = $data['recIDs'];
        if ($recIDs && ! is_array($recIDs)){
            $recIDs = array($recIDs);
        }
        $passedRecIDCnt = count(@$recIDs);
        if ($passedRecIDCnt) {//check editable access for passed records
            if($rtyID){
                $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_RecTypeID = $rtyID and rec_ID  in (".implode(",",$recIDs).")");
                $passedRecIDCnt = count(@$recIDs);
            }           //exclude records user has no right to edit
            $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_ID in (".implode(",",$recIDs).") and rec_OwnerUGrpID in (0,".implode(",",$system->get_user_group_ids()).")");
            $inAccessibleRecCnt = $passedRecIDCnt - count(@$recIDs);
        }

        $result['count'] = array('passed'=> ($passedRecIDCnt?$passedRecIDCnt:0),
                                 'noaccess'=> (@$inAccessibleRecCnt ?$inAccessibleRecCnt :0));


        if (count($recIDs) == 0){
            $result['count']['processed'] = 0;
            return $result;
        }else{
            //$rtyIDs = mysql__select_list($mysqli, 'Records','distinct(rec_RecTypeID)',"rec_ID in (".implode(",",$recIDs).")");
        }

        $undefinedFieldsRecIDs = array(); //value not found
        $processedRecIDs = array();       //success  
        $sqlErrors = array();
        
        $now = date('Y-m-d H:i:s');
        $dtl = Array('dtl_Modified'  => $now);
        $baseTag = "~replace field $dtyName $now";
        
        foreach ($recIDs as $recID) {
            //get matching detail value for record if there is one
            $valuesToBeReplaced = mysql__select_assoc($mysqli, "recDetails","dtl_ID", "dtl_Value",
                        "dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause");
            
            if($valuesToBeReplaced==null){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }else if(count($valuesToBeReplaced)==0){  //not found
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }
            
            //update the details
            $recDetailWasUpdated = false;
            foreach ($valuesToBeReplaced as $dtlID => $dtlVal) {
                
                if ($partialReplace) {// need to replace sVal with rVal
                    $newVal = preg_replace("/".$data['sVal']."/",$data['rVal'],$dtlVal);
                }else{
                    $newVal = $data['rVal'];
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
            }
        }//for recors
        
        
        $limittedRecIDs = array();
        //create report
        _prepareResultReport($system, $result, $needBookmark, 
            $baseTag, $processedRecIDs, $undefinedFieldsRecIDs, $limittedRecIDs, $sqlErrors);
        
        return $result;        
    }

    /**
    * Remove detail value for given set of records and detail type and values
    *
    * @param mixed $system
    * @param mixed $data
    *       recIDs - list of records IDS to be processed
    *       dtyID  - detail field to be added
    *       sVal - search value
    *       tag  = 0|1  - add system tag to mark processed records
    * 
    * result array of counts
    *       passed - count of given rec ids
    *       noaccess - no rights to edit
    *       processed - success
    *       undefined - value not found
    *       errors     - sql error on search or updata
    *
    */
    function detailsDelete($system, $data){

        if ( $system->get_user_id()<1 ) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }

        if (!( @$data['recIDs'] && @$data['dtyID'] )){
            $system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed");
            return false;
        }
        
        $rtyID = @$data['rtyID'];
        $dtyID = $data['dtyID'];
        
        if($rtyID && !(ctype_digit($rtyID) && $rtyID>0)){
            $system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter $rtyID");
            return false;
        }
        if(!(ctype_digit($dtyID) && $dtyID>0)){
            $system->addError(HEURIST_INVALID_REQUEST, "Wrong parameter $dtyID");
            return false;
        }
        
        $mysqli = $system->get_mysqli();

        $dtyName = (@$data['dtyName'] ? "'".$data['dtyName']."'" : "(".$data['dtyID'].")");
        $isDeleteAll = (!array_key_exists("sVal",$data) || $data['sVal']=='');
        
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
                            : "dtl_Value = \"".$mysqli->real_escape_string($data['sVal'])."\"";
                break;
            default:
                $system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by deletion service");
                return false;
        }
        
        
        $result = array();
        
        $needBookmark = (@$data['tag']==1);
        
        //normalize recIDs to an array for code below
        $recIDs = $data['recIDs'];
        if ($recIDs && ! is_array($recIDs)){
            $recIDs = array($recIDs);
        }
        $passedRecIDCnt = count(@$recIDs);
        if ($passedRecIDCnt) {//check editable access for passed records
            if($rtyID){
                $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_RecTypeID = $rtyID and rec_ID  in (".implode(",",$recIDs).")");
                $passedRecIDCnt = count(@$recIDs);
            }           //exclude records user has no right to edit
            $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_ID in (".implode(",",$recIDs).") and rec_OwnerUGrpID in (0,".implode(",",$system->get_user_group_ids()).")");
            $inAccessibleRecCnt = $passedRecIDCnt - count(@$recIDs);
        }

        $result['count'] = array('passed'=> ($passedRecIDCnt?$passedRecIDCnt:0),
                                 'noaccess'=> (@$inAccessibleRecCnt ?$inAccessibleRecCnt :0));


        if (count($recIDs) == 0){
            $result['count']['processed'] = 0;
            return $result;
        }else if($rtyID){
            $rtyIDs = array($rtyID);
        }else{
            $rtyIDs = mysql__select_list($mysqli, 'Records','distinct(rec_RecTypeID)',"rec_ID in (".implode(",",$recIDs).")");
        }
        
        //get array of required detail types per record type
        $rtyRequired = mysql__select_list($mysqli, "defRecStructure","rst_RecTypeID","rst_RequirementType",
        "rst_DetailTypeID = $dtyID and rst_RecTypeID in (".implode(",",$rtyIDs).") and rst_RequirementType='required'");
        

        $undefinedFieldsRecIDs = array(); //value not found
        $processedRecIDs = array();       //success  
        $sqlErrors = array();
        $limittedRecIDs = array(); //it is npt possible to delete requried fields
        
        $now = date('Y-m-d H:i:s');
        $dtl = Array('dtl_Modified'  => $now);
        $baseTag = "~delete field $dtyName $now";
        
        foreach ($recIDs as $recID) {
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
            if(count($rtyIDs)>1){
                //get rectype for current record   
                $rectype_ID = mysql__select_value($mysqli, 'select rec_RecTypeID from Records where rec_ID='.$recID);
            }else{
                $rectype_ID = $rtyIDs[0];
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
            } else {
               $sqlErrors[$recID] = $mysqli->error;
            }
        }//for recors
        
        //create report
        _prepareResultReport($system, $result, $needBookmark, 
            $baseTag, $processedRecIDs, $undefinedFieldsRecIDs, $limittedRecIDs, $sqlErrors);
        
        return $result;        
    }
    

    /*
    * internal function
    *       passed - count of given rec ids
    *       noaccess - no rights to edit
    *       processed - success
    *       undefined - value not found
    *       errors     - sql error on search or updata
    */
    function _prepareResultReport($system, &$result, $needBookmark, 
            $baseTag, &$processedRecIDs, &$undefinedFieldsRecIDs, &$limittedRecIDs, &$sqlErrors)
    {        
        if (count($processedRecIDs)>0){
            
            if($needBookmark){
                tagsAssign($system, $processedRecIDs, null, $baseTag);
                $result['processed'] = array('queryString' => "tag:\"$baseTag\""); //'recIDs' => $processedRecIDs, 
            }else{
                $result['processed'] = array('queryString' => '');
            }
            //@todo!!!! updateRecTitles($processedRecIDs);
        }
        $result['count']['processed'] = count($processedRecIDs);
        
        if (count($undefinedFieldsRecIDs)>0) {
            if($needBookmark){
                tagsAssign($system, $undefinedFieldsRecIDs, null, $baseTag.' undefined');
                $result['undefined'] = array('queryString' => "tag:\"$baseTag undefined\""); //'recIDs' => $undefinedFieldsRecIDs, 
            }else{
                $result['undefined'] = array('queryString' => '');
            }
            
            $result['count']['undefined'] = count($undefinedFieldsRecIDs);
        }
        if (count($limittedRecIDs)>0) {
            if($needBookmark){
                tagsAssign($system, $limittedRecIDs, null, $baseTag.' limitted');
                $result['limitted'] = array('queryString' => "tag:\"$baseTag limitted\""); //'recIDs' => $limittedRecIDs, '
            }else{
                $result['limitted'] = array('queryString' => '');
            }
            
            $result['count']['limitted'] = count($limittedRecIDs);
        }
        if (count($sqlErrors)>0) {
            if($needBookmark){
                tagsAssign($system, array_keys($sqlErrors), null, $baseTag.' error');
                $result['errors'] = array('byRecID' => $sqlErrors, 'queryString' => "tag:\"$baseTag error\"");
            }else{
                $result['errors'] = array('queryString' => '');
            }
            
            $result['count']['errors'] = count($sqlErrors);
        }
    }    
?>
