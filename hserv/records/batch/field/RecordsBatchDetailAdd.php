<?php
namespace hserv\records\batch\field;

use hserv\records\batch\RecordsBatchAction;

/**
 * RecordsBatchDetailAdd.php - Add a detail value to a batch of records
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
 * Adds a new detail value to a batch of records for a specified detail type.
 *
 * This method processes a list of record IDs (`$this->recIDs`), adding a new detail value
 * (`$this->data['val']`, `['geo']`, or `['ulfID']`) for the specified detail type (`$this->data['dtyID']`).
 *
 * Key operations:
 * - Validates that a value to add is provided.
 * - Decodes URL-encoded values if `details_encoded` is set.
 * - Validates general parameters and record counts/accessibility using `_validateParamsAndCounts`.
 * - Checks field limits (`rst_MaxValues`) for each record; skips addition if the limit would be exceeded.
 * - Prepares the detail value:
 *   - For geo fields, uses `prepareGeoValue`.
 *   - For date fields, uses `Temporal::getValueForRecDetails`.
 *   - For text fields, applies HTML purification (unless field is exempt) via `_initPutifier` and `_removeScriptTag`.
 * - Inserts the new detail into `recDetails`.
 * - Updates the `rec_Modified` timestamp for the affected record.
 * - Updates the record's title using `recordUpdateTitle`.
 * - Assigns system tags to records based on the outcome (processed, undefined field limit, over limit, SQL errors)
 *   if tagging is enabled (`$this->data['tag'] == 1`).
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs': (string|array) List of record IDs or 'ALL'.
 * - 'rtyID': (int|array, optional) Filter by record type(s).
 * - 'dtyID': (int) The detail type ID for which to add the value.
 * - 'dtyName': (string, optional) Name of the detail type (for tagging).
 * - 'val': (string, optional) The textual value to add.
 * - 'geo': (string, optional) The geographic WKT string to add.
 * - 'ulfID': (int, optional) The uploaded file ID to link.
 * - 'details_encoded': (int, optional) 1 or 2 if 'val' is URL-encoded.
 * - 'tag': (int, optional) 1 to enable system tagging of processed records.
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records where the detail was added.
 * - undefined: records where the field is not defined in the record structure.
 * - limited: records where rst_MaxValues prevented insertion.
 * - errors: records with SQL, modification-date or title-update errors.
 * - *_list and optional *_tag / *_tag_error entries accompany these outcomes.
 *
 * @return array|false The result array (`$this->result_data`) summarizing the operation (counts for 'passed',
 *                     'noaccess', 'processed', 'undefined', 'limited', 'errors', and tag info).
 *                     Returns `false` if critical validation fails (e.g., missing value, invalid params).
 *                     Errors are added to the system object.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchDetailAdd extends RecordsBatchAction
{
    public function execute(){

        if( !@$this->data['val'] && @$this->data['rVal']){
            $this->data['val'] = $this->data['rVal'];
        }

        if (!(@$this->data['val']!=null || @$this->data['geo']!=null || @$this->data['ulfID']!=null)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed. New field value not defined");
            return false;
        }

        if(@$this->data['val']!=null){
            //attempt to pass server filters against malicious code
            if(@$this->data['details_encoded']==1 || @$this->data['details_encoded']==2){
                //$this->data['val'] = json_decode(str_replace( ' xxx_style=', ' style=',
                //        str_replace( '^^/', '../', urldecode($this->data['val']))));
                //}elseif(@$this->data['details_encoded']==2){
                $this->data['val'] = urldecode( $this->data['val'] );
            }
        }

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray($this->recIDs)){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']."");

        $mysqli = $this->system->getMysqli();

        //get array of max allowed values per record type
        $query = "SELECT rst_RecTypeID,rst_MaxValues FROM defRecStructure WHERE rst_DetailTypeID = $dtyID and rst_RecTypeID in ("
                        .implode(',', $this->rtyIDs).')';
        $rtyLimits = mysql__select_assoc2($mysqli, $query);

        $basetype = null;
        if(@$this->data['geo']==null){
            $basetype = $this->getDetailType($dtyID);
            if($basetype=='geo'){
                $this->data['geo'] = $this->data['val'];
            }
        }

        $now = date(DATE_8601);
        $dtl = array('dtl_DetailTypeID'  => $dtyID,
                     'dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        $baseTag = "~add field $dtyName $now";//name of tag assigned to modified records

        if(@$this->data['geo']!=null){

            list($geoType, $geoValue) = prepareGeoValue($mysqli, $this->data['geo']);
            if($geoType===false){
                $this->system->addError(HEURIST_INVALID_REQUEST, $geoValue);
                return false;
            }
            $dtl['dtl_Value'] = $geoType;
            $dtl['dtl_Geo'] = $geoValue;
            //$dtl['dtl_Geo'] = array("ST_GeomFromText(\"" . $this->data['geo'] . "\")");
        }elseif($basetype=='date'){

            $useNewTemporalFormatInRecDetails = ($this->system->settings->get('sys_dbSubSubVersion')>=14);

            $dtl['dtl_Value'] = Temporal::getValueForRecDetails( $this->data['val'], $useNewTemporalFormatInRecDetails );


        }elseif(@$this->data['val']!=null){ //sanitize new value

            $this->_initPutifier();
            if(!in_array($dtyID, $this->not_purify)){

                //remove html script tags
                $dtl['dtl_Value'] = $this->_removeScriptTag($this->data['val']);

                //$s = $this->purifier->purify( $this->data['val']);
                //$dtl['dtl_Value'] = htmlspecialchars_decode( $this->data['val'] );
            }else{
                $dtl['dtl_Value'] = $this->data['val'];
            }

        }

        if(@$this->data['ulfID']>0){
            $dtl['dtl_UploadedFileID'] = $this->data['ulfID'];
        }

        $undefinedFieldsRecIDs = array();//limit not defined
        $processedRecIDs = array();//success
        $limitedRecIDs = array();//over limit - skip
        $sqlErrors = array();

        foreach ($this->recIDs as $recID) {
            $recID = intval($recID);//redundant for snyk
            //check field limit for this record
            $query = "select rec_RecTypeID, tmp.cnt from Records ".
            "left join (select dtl_RecID as recID, count(dtl_ID) as cnt ".
            "from recDetails ".
            "where dtl_RecID = $recID and dtl_DetailTypeID = $dtyID group by dtl_RecID) as tmp on rec_ID = tmp.recID ".
            "where rec_ID = $recID";

            $res = $mysqli->query($query);
            if(!$res){
                array_push($undefinedFieldsRecIDs, $recID);//cannot retrieve limit
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }

            $row = $res->fetch_row();

            $rectype_ID = $row[0];

            if (!array_key_exists($rectype_ID,$rtyLimits)) { //limit not defined
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }elseif (intval($rtyLimits[$rectype_ID])>0 && $row[1]>0 && ($rtyLimits[$rectype_ID] - $row[1]) < 1){
                array_push($limitedRecIDs, $recID);//over limit - skip
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
                $sqlErrors[$recID] = ERR_REC_MODDATE.$ret;
            }else{
                //update record title
                if(!recordUpdateTitle($this->system, $recID, $rectype_ID, null)){ //on add details
                    $sqlErrors[$recID] = ERR_REC_TITLE;
                }
            }
        }

        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('limited',  $limitedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);



        return $this->result_data;
    }
}
?>
