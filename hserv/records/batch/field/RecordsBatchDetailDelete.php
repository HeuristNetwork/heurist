<?php
namespace hserv\records\batch\field;

use hserv\records\batch\RecordsBatchAction;

/**
 * Deletes detail values from a batch of records based on specified criteria.
 *
 * Key operations:
 * - Validates parameters and record accessibility using `_validateParamsAndCounts`.
 * - Constructs a search clause based on `$this->data['sVal']` (search value) and the field's base type.
 *   - If `sVal` is not provided, all details of the specified `dtyID` are targeted for deletion.
 *   - For text fields, allows partial (`subs`/`substr`) or whole value (`wholeval`) matching.
 * - Checks if the detail type is 'required' for any of the record types being processed. If so, and if
 *   the deletion would remove all instances of this required field for a record, that record is skipped
 *   (unless `$unconditionally` is true).
 * - Deletes matching `recDetails` entries.
 * - If `partialRemove` is true for text fields, it replaces the `sVal` substring with an empty string instead of deleting the whole detail.
 *   If this results in an empty string, the detail is deleted.
 * - Updates `rec_Modified` and record title for affected records.
 * - Assigns system tags if enabled.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs', 'rtyID', 'dtyID', 'dtyName', 'tag': Common batch parameters.
 * - 'sVal': (string, optional) The value to search for deletion. If empty or not set, all details of `dtyID` are targeted.
 * - 'subs'/'substr': (int, optional) If 1, enables partial string match for deletion (effectively a replace-with-empty).
 * - 'wholeval': (int, optional) If 1, search for whole value exact match for deletion.
 *
 * @param bool $unconditionally If true, bypasses checks for 'required' fields and deletes them even if they are mandatory.
 *                              Also, if `sVal` is not provided, this being true implies deleting all instances of the field.
 *                              Defaults to false.
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records where values were deleted or partially removed.
 * - undefined: records where no matching value was found.
 * - limited: records skipped because deletion would remove a required field.
 * - errors: records with SQL, modification-date or title-update errors.
 * - *_list and optional *_tag / *_tag_error entries accompany these outcomes.
 * - The optimized delete-all path may return processed and error directly.
 *
 * @return array|false The result array (`$this->result_data`) summarizing the operation.
 *                     Returns `false` on critical validation failure or if the field type is unsupported for deletion (e.g., 'relmarker').
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */

class RecordsBatchDetailDelete extends RecordsBatchAction
{
    public function execute(){

        $unconditionally = (@$this->data['unconditionally'] == true);

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $isDeleteAll = (!array_key_exists("sVal",$this->data) || $this->data['sVal']=='');//without conditions
        if($isDeleteAll){
            $unconditionally = true;
        }


        $isDeleteInAllRecords = $this->recIDs[0]=='all' && !isEmptyArray($this->rtyIDs);

        $partialRemove = @$this->data['subs'] == 1 || @$this->data['substr'] == 1;
        $wholeRemove = @$this->data['wholeval'] == 1;

        $mysqli = $this->system->getMysqli();

        if($isDeleteAll){
            $searchClause = '1=1';
        }else{

            $searchClause=null;
            $is_like=false;

            $basetype = $this->getDetailType($dtyID);
            switch ($basetype) {
                case "freetext":
                case "blocktext":
                    if($partialRemove || $wholeRemove){
                        $unconditionally = true;
                        $is_like = true;
                    }
                    $searchClause = $mysqli->real_escape_string($this->data['sVal']);

                    break;
                case "enum":
                case "relationtype":
                case "float":
                case "integer":
                case "resource":
                case "date":
                    $searchClause = $mysqli->real_escape_string($this->data['sVal']);

                    break;
                case "geo":
                    $isDeleteAll = true;
                    break;
                case "relmarker":
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Relationship marker fields are not supported by batch deletion");
                    return false;
                    break;
                default:
                    $this->system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by deletion service");
                    return false;
            }

            if($searchClause!=null){
                if($is_like){
                    $searchClause = 'dtl_Value LIKE "%'.$searchClause.'%"';
                }else{
                    $searchClause = 'dtl_Value = "'.$searchClause.'"';
                }
            }else{
                $searchClause = "(1=1)";
            }
        }


        //get array of required detail types per record type
        $rtyRequired = mysql__select_list($mysqli, "defRecStructure","rst_RecTypeID",
        "rst_DetailTypeID = $dtyID and rst_RecTypeID in (".implode(",",$this->rtyIDs).") and rst_RequirementType='required'");


        $undefinedFieldsRecIDs = array();//value not found
        $processedRecIDs = array();//success
        $limitedRecIDs = array();//it is not possible to delete requried fields
        $sqlErrors = array();

        $now = date(DATE_8601);
        $dtl = array('dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        if($partialRemove){
            $baseTag = "~replace field $dtyName $now";
        }else{
            $baseTag = "~delete field $dtyName $now";
        }



        //
        if($isDeleteInAllRecords)
        {

            //special case remove field for all records of specified record type
            if($isDeleteAll){  //for admin only

                $query = 'DELETE d FROM recDetails d, Records r WHERE r.rec_ID=d.dtl_RecID AND r.rec_RecTypeID '
                        .((count($this->rtyIDs)==1)
                                ?('='.$this->rtyIDs[0])
                                :('in ('.implode(',', $this->rtyIDs).')'))
                        .' AND d.dtl_DetailTypeID='.$dtyID;
                $mysqli->query($query);

                if($mysqli->error!=null || $mysqli->error!=''){
                    $this->result_data['processed'] = 0;
                    $this->result_data['error'] = $mysqli->error;
                    return $this->result_data;
                }else{
                    $this->result_data['processed'] = $mysqli->affected_rows;
                    return $this->result_data;
                }
            }

            //find all records of particular record type
            $query = 'SELECT rec_ID FROM Records WHERE rec_RecTypeID '
                    .((count($this->rtyIDs)==1)
                            ?('='.$this->rtyIDs[0])
                            :('in ('.implode(',', $this->rtyIDs).')'));

            $this->recIDs = mysql__select_list2($mysqli, $query);
            if($mysqli->error!=null || $mysqli->error!=''){
                $this->result_data['processed'] = 0;
                $this->result_data['error'] = $mysqli->error;
                return $this->result_data;
            }
        }

        foreach ($this->recIDs as $recID) {

            $recID = intval($recID);

            //get matching detail value for record if there is one
            $query = "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause";
            $valuesToBeDeleted = mysql__select_assoc2($mysqli, $query);

//$valuesToBeDeleted = mysql__select_list($mysqli, "recDetails", "dtl_ID", "dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause");

            if($valuesToBeDeleted==null && $mysqli->error){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }elseif(isEmptyArray($valuesToBeDeleted)){  //not found
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
                if(array_search($rectype_ID, $rtyRequired)!==false){ //this is required field
                    if(!$isDeleteAll){
                        //find total count
                        $total_cnt = mysql__select_value($mysqli, "SELECT count(*) FROM recDetails ".
                            " WHERE dtl_RecID = $recID AND dtl_DetailTypeID = $dtyID");

                    }
                    if($isDeleteAll || ($total_cnt == count($valuesToBeDeleted))){
                        array_push($limitedRecIDs, $recID);
                        continue;
                    }
                }
            }

            if($partialRemove){
                //this is not real delete - this is replacement of value part with empty string
                $now = date(DATE_8601);
                $dtl = ['dtl_Modified' => $now];

                $sRegEx = "/".preg_quote($this->data['sVal'], "/")."/";

                foreach ($valuesToBeDeleted as $dtlID => $dtlVal) {

                    $newVal = preg_replace($sRegEx,'',$dtlVal);

                    if(trim($newVal)==''){
                        $sql = 'delete from recDetails where dtl_ID = '.$dtlID;
                        if ($mysqli->query($sql) === true) {
                            $sqlErrors[$recID] = $mysqli->error;
                        }

                    }else{
                        $dtl['dtl_ID'] = $dtlID;
                        $dtl['dtl_Value'] = $newVal;

                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);

                        if (!is_numeric($ret)) {
                            $sqlErrors[$recID] = $ret;
                        }
                    }
                }//for

                $sql = true;

            }elseif($wholeRemove){

                $sRegEx = "/".preg_quote($this->data['sVal'], "/")."/";

                foreach ($valuesToBeDeleted as $dtl_ID => $dtl_Value) {

                    if(preg_match($sRegEx, $dtl_Value)){
                        $sql = "DELETE FROM recDetails WHERE dtl_ID = $dtl_ID";
                        if($mysqli->query($sql) !== true){
                            $sqlErrors[$recID] = $mysqli->error;
                        }
                    }
                }

                $sql = true;

            }else{
                //delete the details
                $sql = 'delete from recDetails where dtl_ID in ('.implode(',',array_keys($valuesToBeDeleted)).')';
            }

            if ($sql===true || $mysqli->query($sql) === true) {
               array_push($processedRecIDs, $recID);
               //update record edit date
               $rec_update['rec_ID'] = $recID;
               $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
               if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = ERR_REC_MODDATE.$ret;
               }else{
                    if(!recordUpdateTitle($this->system, $recID, null, null)){ //on remove details
                        $sqlErrors[$recID] = ERR_REC_TITLE;
                    }
               }

            } else {
               $sqlErrors[$recID] = $mysqli->error;
            }
        }//for records


        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('limited',  $limitedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

        return $this->result_data;
    }
}
