<?php
namespace hserv\records\batch\field;

use hserv\records\batch\RecordsBatchAction;

use DOMDocument;
use DOMXPath;

/**
 * Change letter cases fo values found in freetext and blocktext (memo) fields based on selection:
 *  1 - Lowercase, uppercase first letter + first letter following fullstops
 *  2 - Lowercase, uppercase first letter of each word
 *  3 - All lowercase
 *  4 - All capital
 * Also changes words/phrases based on list of exceptions (performed last to avoid further editing)
 *  1: Lowercase all, then uppercase first letter of string AND first letter following each full stop.
 *  2: Lowercase all, then uppercase first letter of each word (respects camelCase words).
 *  3: Convert all to lowercase.
 *  4: Convert all to uppercase.
 * After the primary case conversion, a list of exceptions (words/phrases) can be applied to ensure
 * they are cased exactly as provided in the exceptions list, overriding the general conversion for those specific terms.
 * This function handles HTML content by processing only text nodes, preserving HTML tags.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs', 'rtyID' (optional), 'dtyID', 'dtyName' (optional), 'tag': Common batch parameters.
 * - 'op': (int, required) The operation type (1-4 as described above).
 * - 'except': (array|string, optional) An array of exception strings, or a pipe ('|') separated string list.
 *             These strings will be enforced with their exact casing after the main operation.
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records whose field values were changed.
 * - undefined: records with no applicable value or no required change.
 * - errors: records with SQL errors.
 * - *_list and optional *_tag / *_tag_error entries accompany these outcomes.
 *
 * @return array|false The result array (`$this->result_data`) summarizing the operation (counts for 'processed',
 *                     'undefined' (no values in field or no change needed), 'errors', and tag info).
 *                     Returns `false` on critical validation failure (e.g., invalid field type, invalid operation).
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchCaseConversion extends RecordsBatchAction
{

    public function execute(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->getMysqli();
        $date_mode = date(DATE_8601);// for tags, rec_modified and dtl_modified

        $operation = intval($this->data['op']);// number corresponding to an operation below
        $doc = new DOMDocument; // for handling html text

        // Prepare exceptions list
        $exceptions = empty(@$this->data['except']) ? array() : $this->data['except'];
        if(!is_array($exceptions)){
            $exceptions = explode('|', $exceptions);
        }

        if(!empty($exceptions)){
            $new_excepts = array();

            foreach ($exceptions as $value) {
                if(empty($value)){
                    continue;
                }

                array_push($new_excepts, $mysqli->real_escape_string($value));
            }

            $exceptions = $new_excepts;
        }

        // Regular expressions for operations
        $regex = $operation == 1 ? '(\.\s+)(\w+)' : '';
        $regex = $operation != 1 ? '\w+' : $regex;

        // Temp tags for HTML handling, loadHTML will tend to add paragraph tags if no outer tag exists
        $temp_open = "<span data-t='zzz_temp'>";
        $temp_close = "</span>";

        // Callback function for regex functions
        $callback = function($match) use ($operation){

            $word = $operation == 1 ? $match[2] : $match[0];

            if($operation == 1){
                // lowercase then capitalise first letter + first letter following full stop

                $first = mb_substr($word, 0, 1);
                $remainder = mb_substr($word, 1, null);

                return $match[1] . mb_strtoupper($first) . $remainder;

            }elseif($operation == 2){
                // lowercase then capitalise first letter for all words

                if(strlen($word) == 1 || mb_ereg("[a-z][A-Z]|[A-Z][a-z]", $word)){ // skip if one letter or camel case
                    return $word;
                }

                $first = mb_substr($word, 0, 1);
                $remainder = mb_substr($word, 1, null);

                return mb_strtoupper($first) . $remainder;

            }
        };

        // Field details
        $dtyID = intval($this->data['dtyID']);
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~replace case convert $dtyName $date_mode";

        // Check field is freetext or blocktext
        $fld_type = $this->getDetailType($dtyID);
        if($dtyID < 1 || ($fld_type != 'freetext' && $fld_type != 'blocktext')){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Case conversion only works on valid freetext and blocktext fields');
            return false;
        }

        // Validate operation value
        if($operation < 1 || $operation > 4){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Provided operation is not handled by case converter');
            return false;
        }

        $use_reg = $operation < 2; // whether to use the regex functions

        //$keep_autocommit = mysql__begin_transaction($mysqli);

        // Setup report variable
        $completed_recs = array();
        $skipped_recs = array();
        $sql_errors = array();

        // Cycle through records
        foreach ($this->recIDs as $recID){

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

                if($values[1] != strip_tags($values[1])){ // potentially has HTML

                    $value = $temp_open.$values[1].$temp_close; // add temp tags, to avoid extra elements

                    $doc->loadHTML($value, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);// load html

                    $xpath = new DOMXPath($doc);// retrieve text only
                    $text_nodes = $xpath->query('//text()');

                    foreach($text_nodes as $node){

                        $text = $operation == 1 || $operation == 3 ? mb_strtolower($node->textContent) : $node->textContent;
                        $text = $operation == 4 ? mb_strtoupper($text) : $text;

                        $node->textContent = $use_reg ? mb_ereg_replace_callback($regex, $callback, $text) : $text;
                    }

                    $value = $doc->saveHTML();// save new value

                    // strip temp tags
                    $value = mb_substr($value, strlen($temp_open));
                    $value = mb_substr($value, 0, mb_strlen($value) - strlen($temp_close) - 1);

                }else{ // normal text

                    $text = $operation == 1 ? mb_strtolower($values[1]) : $values[1];
                    $text = $operation == 4 ? mb_strtoupper($text) : $text;

                    $value = $use_reg ? mb_ereg_replace_callback($regex, $callback, $text) : $text;

                    if($operation == 1 && !empty($value)){ // capitalise first letter

                        $first = mb_substr($value, 0, 1);
                        $remainder = mb_strlen($value) == 1 ? "" : mb_substr($value, 1, null);

                        $value = mb_strtoupper($first) . $remainder;
                    }
                }

                if(empty($value)){ // ensure there is a value to save
                    continue;
                }

                foreach($exceptions as $except){ // apply exceptions
                    $regex = preg_quote($except);
                    $regex = "\b$regex\b";
                    if(mb_eregi($regex, $value)){ // check if exception appears in string
                        $value = mb_eregi_replace($regex, $except, $value);// replace
                    }
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
