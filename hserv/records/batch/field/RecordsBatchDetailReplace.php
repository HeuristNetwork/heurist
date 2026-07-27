<?php
namespace hserv\records\batch\field;

use hserv\records\batch\RecordsBatchAction;

/**
 * RecordsBatchDetailReplace.php - Replace detail values in a batch of records
 *
 * @project     Heurist academic knowledge management system
 * @package Records\Batch
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov   <osmakov@gmail.com>
 */
use hserv\utilities\Temporal;

/**
 * Replaces existing detail values in a batch of records.
 *
 * This method allows for replacing detail values based on various criteria:
 * - Replace all occurrences of a specific detail type (`dtyID`) if search value (`sVal`) is not provided.
 * - Replace specific values (`sVal`) if provided.
 * - Partial replacement within a string (`subs` or `substr` flags).
 * - Whole value exact match (`wholeval` flag).
 * - Option to insert the new value if the search value is not found (`insert_new_values` flag).
 *
 * Key operations:
 * - Validates parameters: presence of replacement value (`rVal`), general params via `_validateParamsAndCounts`.
 * - Handles URL decoding of `rVal` if `encoded` flag is set.
 * - Checks for max length of `rVal` and splits it if it's for `DT_EXTENDED_DESCRIPTION` and `needSplit` is true.
 * - Constructs a search clause based on `sVal` and field type (`basetype`).
 * - Iterates through records, finds matching details, and updates them.
 *   - For text fields, performs string replacement or sets new value. HTML purification is applied.
 *   - For geo/date fields, prepares value using `prepareGeoValue`/`Temporal::getValueForRecDetails`.
 * - Updates `rec_Modified` and record title for affected records.
 * - Assigns system tags if enabled.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs', 'rtyID', 'dtyID', 'dtyName', 'tag': Common batch parameters.
 * - 'rVal': (string, required unless 'replace_empty' is 1) The replacement value.
 * - 'sVal': (string, optional) The value to search for. If null, all values of `dtyID` are targeted.
 * - 'encoded': (int, optional) 1 or 2 if `rVal` is URL-encoded.
 * - 'replace_empty': (int, optional) If 1, allows `rVal` to be null/empty.
 * - 'needSplit': (bool, optional) If true and `dtyID` is `DT_EXTENDED_DESCRIPTION`, splits long `rVal`.
 * - 'dt_extended_description': (int, optional) Overrides `DT_EXTENDED_DESCRIPTION` constant.
 * - 'subs'/'substr': (int, optional) If 1, enables partial string replacement.
 * - 'wholeval': (int, optional) If 1, search for whole value match.
 * - 'insert_new_values': (int, optional) If 1 and `sVal` is not found (or not provided), insert `rVal` as a new detail.
 * - 'debug': (bool, optional) If true, skips actual database updates.
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records where one or more values were replaced or inserted.
 * - undefined: records where no matching value was found.
 * - errors: records with SQL, modification-date or title-update errors.
 * - *_list and optional *_tag / *_tag_error entries accompany these outcomes.
 *
 * @return array|false The result array (`$this->result_data`) summarizing the operation.
 *                     Returns `false` on critical validation failure or DB error during main query.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchDetailReplace extends RecordsBatchAction
{
    protected $dt_extended_description = 0;

    public function execute()
    {
        if (@$this->data['rVal']==null && @$this->data['replace_empty'] != 1){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed. New value not defined");
            return false;
        }

        $useNewTemporalFormatInRecDetails = ($this->system->settings->get('sys_dbSubSubVersion')>=14);


        if(@$this->data['rVal']!=null || @$this->data['encoded']==2){
            if(@$this->data['encoded']==1){
                $this->data['rVal'] = urldecode( $this->data['rVal'] );
            }
        }

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $insert_new_value = false;

        $mysqli = $this->system->getMysqli();

        $rval = $mysqli->real_escape_string($this->data['rVal']);

        $this->_initPutifier();

        //split value if exceeds 64K
        $splitValues = array();

        if(@$this->data['dt_extended_description']>0){
            $this->dt_extended_description = $this->data['dt_extended_description'];
        }elseif(!($this->dt_extended_description>0)){
            $this->system->defineConstant('DT_EXTENDED_DESCRIPTION');
            $this->dt_extended_description = DT_EXTENDED_DESCRIPTION;
        }

        if(@$this->data['needSplit'] && $dtyID==$this->dt_extended_description){
                //split replacement value
               $lim = checkMaxLength2($rval);
               //TEST $lim = 100;
               if($lim>0){
                    $dtl_Value = $this->data['rVal'];

                    $dtl_Value = $this->_removeScriptTag($dtl_Value);

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

        $basetype = $this->getDetailType($dtyID);

        $partialReplace = @$this->data['subs'] == 1 || @$this->data['substr'] == 1;
        $wholeReplace = @$this->data['wholeval'] == 1;

        if(@$this->data['sVal']==null){    //value to be replaced
            //all except file type
            //$searchClause = '1=1';
            $replace_all_occurences = true;   //search value not defined replace all
            $insert_new_value = @$this->data['insert_new_values'] == 1;

            //??? why we need it if $dtyID is defined
            $types = mysql__select_list2($mysqli, 'select dty_ID from defDetailTypes where dty_Type = "file"');// OR dty_Type = "geo"
            $types = prepareIds($types);//redundant for snyk
            $searchClause = 'dtl_DetailTypeID NOT IN ('.implode(',',$types).')';

        }else{

            $searchClause = null;
            $is_like = false;

            switch ($basetype) {
                case "freetext":
                case "blocktext":
                    if($partialReplace || $wholeReplace){
                        $is_like = true;
                    }
                    $searchClause = $mysqli->real_escape_string(@$this->data['sVal']);

                    break;
                case "enum":
                case "relationtype":
                case "float":
                case "integer":
                case "resource":
                    $searchClause = $mysqli->real_escape_string(@$this->data['sVal']);
                    $partialReplace = false;
                    break;
                case "date":

                    $dtl_Value = Temporal::getValueForRecDetails( @$this->data['sVal'], $useNewTemporalFormatInRecDetails );

                    $searchClause = $mysqli->real_escape_string($dtl_Value);

                    $partialReplace = false;
                    break;
                case "relmarker":
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Relationship marker fields are not supported by value-replace service");
                    return false;
                    break;
                default:
                    $this->system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by value-replace service");
                    return false;
            }

            if($searchClause!=null){
                if($is_like){
                    $searchClause = 'dtl_Value LIKE "%'.$searchClause.'%"';
                }else{
                    $searchClause = 'dtl_Value = "'.$searchClause.'"';
                }
            }


            $replace_all_occurences = false;
        }

        $undefinedFieldsRecIDs = array();//value not found
        $processedRecIDs = array();//success
        $sqlErrors = array();

        $now = date(DATE_8601);
        $dtl = array('dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        $baseTag = "~replace field $dtyName $now";

        if($basetype=='geo'){
            list($geoType, $geoValue) = prepareGeoValue($mysqli, $this->data['rVal']);
            if($geoType===false){
                $this->system->addError(HEURIST_INVALID_REQUEST, $geoValue);
                return false;
            }
        }

        $is_multiline = !empty($splitValues);

        foreach ($this->recIDs as $recID) {

            $query = 'SELECT dtl_ID, dtl_RecID '
                    .($is_multiline?'':', dtl_Value')
                    .'  FROM recDetails ';

            if($recID=='all' && is_array($this->rtyIDs) && @$this->rtyIDs[0]>0){
                if(is_array($this->rtyIDs) && @$this->rtyIDs[0]>0){

                    $query = $query.', Records '
                            .'WHERE rec_ID=dtl_RecID AND  rec_RecTypeID '
            .(count($this->rtyIDs)>1?('in ('.implode(',',$this->rtyIDs).')'):('='.$this->rtyIDs[0]))
                            ." AND dtl_DetailTypeID = $dtyID and $searchClause";
                }
            }else{
                $query = $query."WHERE  dtl_DetailTypeID = $dtyID and $searchClause";
                //get matching detail value for record if there is one
                if($recID!='all'){
                    $query = $query.' AND  dtl_RecID = '.intval($recID);
                }
            }
            $query = $query.' ORDER BY dtl_RecID';

            //$valuesToBeReplaced = mysql__select_assoc2($mysqli, $query);

            $res = $mysqli->query($query);

            if($mysqli->error!=null || $mysqli->error!=''){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            //}elseif(isEmptyArray($valuesToBeReplaced)){  //not found
            //    array_push($undefinedFieldsRecIDs, $recID);
            //    continue;
            }

            $recDetailWasUpdated = false;
            $valuesToBeDeleted = array();
            $keep_recID = $recID;
            $recID = 0;
            $get_next_row = true;

            //update the details
            while (true) {

                if($get_next_row) {$row = $res->fetch_row();}
                $get_next_row = true;
                $inserting_value = $insert_new_value && $res->num_rows == 0;

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
                                $sqlErrors[$recID] = 'Cannot update modify data. '.$ret;
                            }
                        }else{
                            array_push($undefinedFieldsRecIDs, $recID);
                        }
                        if(!empty($valuesToBeDeleted) && !@$this->data['debug']){
                            //remove the rest for replace all occurences
                            $sql = 'delete from recDetails where dtl_ID in ('.implode(',',$valuesToBeDeleted).')';
                            if ($mysqli->query($sql) === false) {
                                $sqlErrors[$recID] = $mysqli->error;
                            }
                        }
                    }

                    if(!$row && ($res->num_rows > 0 || !$insert_new_value)){ //end of loop

                        if($recID==0){
                            array_push($undefinedFieldsRecIDs, $keep_recID);
                        }
                        break;
                    }

                    $recDetailWasUpdated = false;
                    $valuesToBeDeleted = array();
                }

                $dtlID = $inserting_value ? -1 : intval($row[0]);
                $recID = $inserting_value ? $keep_recID : intval($row[1]);

                if($is_multiline){ //replace with several values (long text)

                    foreach($splitValues as $val){
                        $dtl['dtl_ID'] = -1;
                        $dtl['dtl_RecID'] = $recID;
                        $dtl['dtl_DetailTypeID'] = intval($dtyID);
                        $dtl['dtl_Value'] = $val;
                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
                    }
                    $recDetailWasUpdated = true;

                }else{
                    $dtlVal = $inserting_value ? null : $row[2];

                    if($this->data['rVal']=='replaceAbsPathinCMS'){

                        $newVal = replaceAbsPathinCMS($recID, $dtlVal);

                    }elseif (!$replace_all_occurences && $partialReplace) {// need to replace sVal with rVal
                        $newVal = preg_replace("/".preg_quote($this->data['sVal'], "/")."/i", $this->data['rVal'], $dtlVal);
                    }else{
                        $newVal = $this->data['rVal'];
                    }

                    $dtl['dtl_ID'] = $dtlID;  //detail type id

                    if(($basetype=='freetext' || $basetype=='blocktext')
                        && !in_array($dtyID, $this->not_purify))
                    {
                            //remove html script tags
                            $dtl['dtl_Value'] = $this->_removeScriptTag($newVal);

                            //$s = $this->purifier->purify( $newVal );
                            //$dtl['dtl_Value'] = htmlspecialchars_decode( $dtl['dtl_Value'] );
                    }elseif($basetype=='geo'){

                        $dtl['dtl_Value'] = $geoType;
                        $dtl['dtl_Geo'] = $geoValue;

                    }elseif($basetype=='date'){

                        $dtl['dtl_Value'] = Temporal::getValueForRecDetails( $newVal, $useNewTemporalFormatInRecDetails );

                    }else{
                        $dtl['dtl_Value'] = $newVal;
                    }

                    if(!@$this->data['debug']){

                        if($insert_new_value){
                            $dtl['dtl_RecID'] = $recID;
                            $dtl['dtl_DetailTypeID'] = $dtyID;
                        }else{
                            unset($dtl['dtl_RecID']);
                            unset($dtl['dtl_DetailTypeID']);
                        }

                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);

                        if (!is_numeric($ret)) {
                            $sqlErrors[$recID] = $ret;

                            if($inserting_value){
                                break;
                            }else{
                                continue;
                            }
                        }elseif($inserting_value){
                            array_push($processedRecIDs, $recID);
                        }
                    }

                    $recDetailWasUpdated = true;

                }

                if($replace_all_occurences || $is_multiline){

                    if($is_multiline && $dtlID > 0){
                        array_push($valuesToBeDeleted, intval($dtlID));
                    }

                    while ($row = $res->fetch_row()) { //gather all old detail IDs
                        if($row[1]!=$recID){
                            break;
                        }
                        array_push($valuesToBeDeleted, intval($row[0]));
                    }
                    $get_next_row = false;
                }

                if($inserting_value){ break; }

            }//while

        }//while records

        if($res) {$res->close();}

        //update record title
        foreach ($processedRecIDs as $recID){
                if(!recordUpdateTitle($this->system, $recID, null, null)){ //on replace details
                    $sqlErrors[$recID] = ERR_REC_TITLE;
                }
        }

        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

        return $this->result_data;
    }
}
