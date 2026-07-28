<?php
namespace hserv\records\batch\field;

use hserv\records\batch\RecordsBatchAction;

/**
 * Converts newline characters (`\n`, `\r\n`) to HTML line breaks (`<br>`)
 * and sequences of multiple spaces to non-breaking spaces (`&nbsp;`)
 * within a specified blocktext field for a batch of records.
 *
 * Key operations:
 * - Validates parameters, record accessibility, and ensures the target field (`dtyID`) is of type 'blocktext'.
 * - For each record and its values in the specified field:
 *   - Skips if the value already contains HTML tags.
 *   - Replaces double spaces with a non-breaking space followed by a regular space (`&nbsp; `).
 *   - Converts newlines to `<br>` using `nl2br()`.
 *   - If the conversion results in changes:
 *     - Replaces sequences of two or more `<br>` tags with `</p><p>` and wraps the whole value in `<p>...</p>`.
 *       Empty paragraphs resulting from this are removed.
 *     - Updates the `dtl_Value` and `dtl_Modified` for the detail.
 *     - Updates `rec_Modified` for the record.
 * - Assigns system tags if enabled and reports outcomes.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs', 'rtyID' (optional), 'dtyID', 'dtyName' (optional), 'tag': Common batch parameters.
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records whose blocktext values were converted.
 * - undefined: records with no value, existing HTML, or no resulting change.
 * - errors: records with SQL errors.
 * - *_list and optional *_tag / *_tag_error entries accompany these outcomes.
 *
 * @return array|false The result array (`$this->result_data`) summarizing the operation (counts for 'processed',
 *                     'undefined' (no values in field or value unchanged/contained HTML), 'errors', and tag info).
 *                     Returns `false` on critical validation failure (e.g., invalid field type).
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchNl2BrConversion extends RecordsBatchAction
{

    public function execute(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();
        $date_mode = date(DATE_8601);// for tags, rec_modified and dtl_modified

        // Field details
        $dtyID = intval($this->data['dtyID']);
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~replace nl2br $dtyName $date_mode";

        // Check field is freetext or blocktext
        $fld_type = $this->getDetailType($dtyID);
        if($dtyID < 1 || ($fld_type != 'blocktext')){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Multiline to HTML conversion only works on valid blocktext fields');
            return false;
        }

        //$keep_autocommit = mysql__begin_transaction($mysqli);

        // Setup report variable
        $completed_recs = array();
        $skipped_recs = array();
        $sql_errors = array();

        // Cycle through records
        foreach ($this->recIDs as $progressIdx => $recID){
            if(!$this->_progressStep($progressIdx, null, 'Processing records', 10)){
                break;
            }

            $res = $mysqli->query("SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_DetailTypeID = $dtyID AND dtl_RecID = $recID");

            if(!$res){
                $sql_errors[$recID] = $mysqli->error;
                continue;
            }elseif($res->num_rows == 0){ // no values within field
                array_push($skipped_recs, $recID);
                continue;
            }

            $sql_errors[$recID] = array();

            // Cycle through values
            while($values = $res->fetch_row()){

                $value = '';

                if($values[1] == strip_tags($values[1])){ // skip values with HTML
                    $value = nl2br(str_replace('  ', '&nbsp; ', $values[1]));
                    
                    if($value==$values[1]){
                        array_push($skipped_recs, $recID);
                        continue;
                    }
                    //replace repeated <br> with <p>
                    $value2 = preg_replace('#(?:<br\s*/?>\s*?){2,}#', '</p><p>', $value);
                    if($value!=$value2){
                        //remove empty para
                        $value = "<p>$value2</p>";    
                        $pattern = "/<p[^>]*>\s*<\/p[^>]*>/";
                        $value = preg_replace($pattern, '', $value);
                    }
                }

                if(empty($value)){ // ensure there is a value to save
                    array_push($skipped_recs, $recID);
                    continue;
                }

                // Update details value + modified
                $dtl_rec = array('dtl_ID' => intval($values[0]), 'dtl_Value' => $value, 'dtl_Modified' => $date_mode);

                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl_rec);
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }

                // Update record modified
                $ret = mysql__insertupdate($mysqli, 'Records', 'rec', array('rec_ID' => $recID, 'rec_Modified' => $date_mode));
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                }
            }//for

            array_push($completed_recs, $recID);
            if(!empty($sql_errors[$recID])){
                $sql_errors[$recID] = implode(' ;', $sql_errors[$recID]);
            }else{
                unset($sql_errors[$recID]);
            }
        }

        // Final touches to report
        $this->_assignTagsAndReport('processed', $completed_recs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skipped_recs, $baseTag);
        $this->_assignTagsAndReport('errors',  $sql_errors, $baseTag);

        $this->result_data['undefined'] = count($skipped_recs);
        $this->result_data['undefined_list'] = $skipped_recs;

        return $this->result_data;
    }
}
