<?php
namespace hserv\records\batch\field;

use hserv\records\batch\RecordsBatchAction;

/**
 * Translates the content of a specified freetext or blocktext field for a batch of records
 * to a target language using an external translation service (via `getDeepLTranslation`).
 *
 * Key operations:
 * - Validates parameters, record accessibility, and ensures the target field is text-based.
 * - For each record and its values in the specified field (`dtyID`):
 *   - Identifies the source text: Prefers text without a language prefix. If multiple values exist,
 *     the logic for selecting the definitive source value is based on the first value encountered without a prefix,
 *     or the first value if all have prefixes (source language is then detected from that prefix).
 *   - Checks if a translation to the target language (`$this->data['lang']`) already exists.
 *     - If `delete` is true: Deletes the existing translation if found.
 *     - If `replace` is true or no existing translation: Translates the source text.
 *     - If already translated and not replacing/deleting: Skips (adds to `already_translated`).
 *   - If translation occurs:
 *     - The translated text is prefixed with the target language code (e.g., "en:Translated text").
 *     - The new or updated translated detail is saved to `recDetails`.
 *     - `rec_Modified` is updated.
 * - Assigns system tags if enabled.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs', 'rtyID' (optional), 'dtyID', 'dtyName' (optional), 'tag': Common batch parameters.
 * - 'lang': (string, required) The target language code (e.g., 'en', 'fr').
 * - 'replace': (int, optional) If 1, existing translations for the target language will be replaced.
 * - 'delete': (int, optional) If 1, existing translations for the target language will be deleted.
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records where a translation was added, replaced or deleted.
 * - undefined: records where no source value was available.
 * - translated: records that already contain the requested translation.
 * - errors: records with translation or SQL errors.
 * - *_list and optional *_tag / *_tag_error entries accompany these outcomes.
 *
 * @return array|false The result array (`$this->result_data`) summarizing the operation (counts for 'processed',
 *                     'undefined' (no source value), 'translated' (already had target translation), 'errors', and tag info).
 *                     Returns `false` on critical validation/translation failure or if the target language is not defined.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchFieldTranslation extends RecordsBatchAction
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
        $baseTag = "~translation $dtyName $date_mode";

        // Check field is freetext or blocktext
        $fld_type = $this->getDetailType($dtyID);
        if($dtyID < 1 || ($fld_type != 'freetext' && $fld_type != 'blocktext')){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Translation only works on valid freetext and blocktext fields');
            return false;
        }

        $is_replacement = (@$this->data['replace']==1);
        $is_deletion = (@$this->data['delete']==1);
        $lang = @$this->data['lang'];

        $lang = getLangCode3($lang);

        if($lang==null){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Language is not defined');
            return false;
        }

        // Setup report variable
        $completed_recs = array();
        $skipped_recs = array();
        $already_translated = array();
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

            $replacement_dtl_id = -1;
            $value_to_translate = null;
            $all_detected = 0;
            $source_lang = null;

            // Cycle through values - find and source and possible replacement
            while( ($values = $res->fetch_row()) && ($all_detected<2)){

                //detect language
                list($lang2, $val) = extractLangPrefix($values[1]);
                if($lang2==null){
                    //source
                    $value_to_translate = $val;      // $is_replacement
                    $all_detected++;
                    $source_lang = null;
                }elseif($lang2==$lang){
                    //already has this translation
                    if($is_replacement || $is_deletion){
                        $replacement_dtl_id = intval($values[0]);
                    }else{
                        $replacement_dtl_id = 0;
                    }
                    $all_detected++;
                }elseif(empty($value_to_translate)){
                    // temporary source, is replaced by value w/o language prefix
                    $value_to_translate = $val;
                    $source_lang = $lang2;
                }
            }//while

            if($is_deletion){

                if($replacement_dtl_id>0){
                    $query = 'DELETE FROM recDetails WHERE dtl_ID='.$replacement_dtl_id;
                    $ret = $mysqli->query($query);
                    if(!$ret){
                        $sql_errors[$recID][] = $mysqli->error;
                    }
                }else{
                    array_push($skipped_recs, $recID);
                }
            }elseif($value_to_translate==null){
                //source not found - skip
                array_push($skipped_recs, $recID);
            }elseif($replacement_dtl_id==0){
                //already translated
                array_push($already_translated, $recID);
            }else {

                // get translated value
                $translated = getDeepLTranslation($this->system, $value_to_translate, $lang, $source_lang);

                //$translated = $lang.': TRNASLATED! '.$value_to_translate;

                if($translated===false){
                    //break;
                    $this->system->addErrorMsg('Translation has been terminated for record# '.$recID.'. <br>');
                    return false;
                }

                $translated = $lang.':'.$translated;

                // Update details value + modified
                $dtl_rec = array('dtl_Value' => $translated, 'dtl_Modified' => $date_mode);
                if($replacement_dtl_id>0){
                    $dtl_rec['dtl_ID'] = $replacement_dtl_id;
                }else{
                    $dtl_rec['dtl_RecID'] = $recID;
                    $dtl_rec['dtl_DetailTypeID'] = $dtyID;
                }

                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl_rec);
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }

                // Update record modified
                $ret = mysql__insertupdate($mysqli, 'Records', 'rec', array('rec_ID' => $recID, 'rec_Modified' => $date_mode));
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }
            }

            array_push($completed_recs, $recID);
            if(!empty($sql_errors[$recID])){
                $sql_errors[$recID] = implode(' ;', $sql_errors[$recID]);
            }else{
                unset($sql_errors[$recID]);
            }
        }//foreach records

        // Final touches to report
        $this->_assignTagsAndReport('processed', $completed_recs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skipped_recs, $baseTag);
        $this->_assignTagsAndReport('translated', $already_translated, $baseTag);
        $this->_assignTagsAndReport('errors',  $sql_errors, $baseTag);

        $this->result_data['undefined'] = count($skipped_recs);
        $this->result_data['undefined_list'] = $skipped_recs;

        return $this->result_data;
    }
}
