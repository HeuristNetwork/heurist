<?php
namespace hserv\records\batch\field;

use hserv\records\batch\RecordsBatchAction;

/**
 * Assigns sequential values to a field for the selected records.
 *
 * For freetext fields, the sequence is stored as a trailing, zero-padded
 * "-<integer>" suffix, preserving an existing prefix. A supplied `prefix`
 * is used for values without a prefix; otherwise the first prefix found in
 * the selected freetext values is used as the default. For integer and float
 * fields, the detail value itself is replaced by the integer sequence value.
 *
 * Modes:
 * - Default: continue after the largest existing sequence value.
 * - reset=1: replace existing sequence values and number records from 1.
 * - fillgaps=1: preserve numbered records and assign the smallest unused
 *   positive integers before continuing above the maximum.
 *
 * Sequences are calculated independently for each record type.
 *
 * Expected parameters in `$this->data`:
 * - 'recIDs': (string|array) Record IDs or 'ALL'.
 * - 'rtyID': (int|array, optional) Record type filter.
 * - 'dtyID': (int) Field receiving the sequence.
 * - 'dtyName': (string, optional) Field name used in tags.
 * - 'tag': (int, optional) If 1, assign outcome tags.
 * - 'reset': (int, optional) If 1, replace existing sequence values and number records from 1.
 * - 'fillgaps': (int, optional) If 1, retain existing sequence values and fill unused positive integers first.
 * - 'prefix': (string, optional) Default prefix for freetext values without one.
 *   If omitted, the first existing freetext prefix found in the selection is used.
 * - 'digits': (int, optional) Number of digits in the zero-padded freetext
 *   sequence suffix. Defaults to 4.
 *
 * Report format:
 * - passed, noaccess: selected and inaccessible record counts.
 * - processed: records assigned a sequence value.
 * - undefined: records skipped because the field/value could not be processed.
 * - errors: records with SQL errors.
 * - *_list and optional *_tag / *_tag_error entries accompany these outcomes.
 *
 * @return array|false Batch-operation report, or false on validation failure.
 *
 * @package Records\Batch
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchFieldIncrement extends RecordsBatchAction
{
/**
 * Returns the numeric increment suffix from a freetext value.
 *
 * Increment suffixes use the form "-<integer>" at the end of the value.
 * For example, "ABC-12" and "12" return 12. Other values return null.
 *
 * @param mixed $value Existing detail value.
 * @return int|null
 */
    private function _getIncrementSuffix($value){

        $value = trim((string)$value);

        // A freetext value containing digits only is already the sequence value.
        if(preg_match('/^\d+$/u', $value)===1){
            return intval($value);
        }

        if(preg_match('/-(\d+)$/u', $value, $matches)===1){
            return intval($matches[1]);
        }

        return null;
    }

/**
 * Returns the prefix before a trailing numeric sequence suffix.
 *
 * For example, "ABC-0012" returns "ABC". Values without a trailing
 * "-<integer>" suffix return null.
 *
 * @param mixed $value Existing detail value.
 * @return string|null
 */
    private function _getIncrementPrefix($value){

        $value = trim((string)$value);
        if(preg_match('/^(.*)-(\d+)$/u', $value, $matches)===1){
            return trim($matches[1]);
        }elseif($value!==''){
            return $value;
        }

        return null;
    }

    /**
     * Adds or replaces the trailing numeric increment suffix of a freetext value.
     *
     * Existing prefixes are preserved. Values without a prefix use the supplied
     * default prefix. The numeric suffix is left-padded with zeroes.
     *
     * @param mixed $value Existing detail value.
     * @param int $incrementValue Increment value to assign.
     * @param string $defaultPrefix Prefix used when the value has no prefix.
     * @param int $digits Number of digits in the padded suffix.
     * @return string
     */
    private function _setIncrementSuffix($value, $incrementValue, $defaultPrefix, $digits){

        $value = trim((string)$value);
        $incrementValue = intval($incrementValue);
        $digits = intval($digits);
        if($digits<1){
            $digits = 4;
        }

        $suffix = str_pad((string)$incrementValue, $digits, '0', STR_PAD_LEFT);
        $prefix = $this->_getIncrementPrefix($value);

        if($prefix===null){
            if($value!=='' && preg_match('/^\d+$/u', $value)!==1){
                $prefix = $value;
            }else{
                $prefix = trim((string)$defaultPrefix);
            }
        }

        return $prefix==='' ? $suffix : $prefix.'-'.$suffix;
    }

/**
 * Builds the sequence values to assign to records without an increment.
 *
 * Existing values must be positive integers. When gap filling is enabled,
 * the sequence starts with the smallest unused positive integers. Otherwise,
 * it starts after the highest existing value.
 *
 * Examples:
 * - existing [1, 3, 4], count 3, fill gaps => [2, 5, 6]
 * - existing [1, 3, 4], count 3, continue  => [5, 6, 7]
 * - existing [], count 3                  => [1, 2, 3]
 *
 * @param array $existingValues Existing sequence values.
 * @param int $requiredCount Number of new values required.
 * @param bool $fillGaps Whether missing positive integers should be used first.
 * @return array<int>
 */
    private function _getIncrementSequence(array $existingValues, int $requiredCount, bool $fillGaps=false): array{

        if($requiredCount<=0){
            return array();
        }

        $usedValues = array();
        $maxValue = 0;

        foreach($existingValues as $value){
            if(!is_numeric($value)){
                continue;
            }

            $value = intval($value);
            if($value<=0){
                continue;
            }

            $usedValues[$value] = true;
            if($value>$maxValue){
                $maxValue = $value;
            }
        }

        $sequence = array();

        if($fillGaps){
            $candidate = 1;
            while(count($sequence)<$requiredCount){
                if(!isset($usedValues[$candidate])){
                    $sequence[] = $candidate;
                    $usedValues[$candidate] = true;
                }
                $candidate++;
            }
        }else{
            $candidate = $maxValue + 1;
            while(count($sequence)<$requiredCount){
                $sequence[] = $candidate;
                $candidate++;
            }
        }

        return $sequence;
    }


    public function execute(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif(isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        // Incrementing ALL records requires an explicit record list for grouping.
        if($this->recIDs[0]==='all'){
            $whereRecType = '';
            if(!isEmptyArray($this->rtyIDs)){
                $whereRecType = ' WHERE rec_RecTypeID IN ('.implode(',', $this->rtyIDs).')';
            }
            $this->recIDs = mysql__select_list2(
                $this->system->getMysqli(),
                'SELECT rec_ID FROM Records'.$whereRecType.' ORDER BY rec_RecTypeID, rec_ID'
            );
            if(isEmptyArray($this->recIDs)){
                $this->result_data['processed'] = 0;
                return $this->result_data;
            }
        }

        $mysqli = $this->system->getMysqli();
        $date_mode = date(DATE_8601);

        $dtyID = intval($this->data['dtyID']);
        $dtyName = (@$this->data['dtyName']
            ? "'".$this->data['dtyName']."'"
            : 'id:'.$dtyID);
        $dtyType = $this->getDetailType($dtyID);
        $baseTag = "~increment value $dtyName $date_mode";

        if(!in_array($dtyType, array('freetext', 'integer', 'float'), true)){
            $this->system->addError(
                HEURIST_INVALID_REQUEST,
                'Increment value can be assigned only to freetext, integer or float fields'
            );
            return false;
        }

        $completed_recs = array();
        $skipped_recs = array();
        $sql_errors = array();

        $resetSequence = false; //DISABLED (@$this->data['reset'] == 1);
        $continueSequence = !$resetSequence;
        $fillGaps = $continueSequence && (@$this->data['fillgaps'] == 1);

        $digits = intval(@$this->data['digits']);
        if($digits<1){
            $digits = 4;
        }

        $defaultPrefix = '';
        if(array_key_exists('prefix', $this->data)){
            $defaultPrefix = trim((string)$this->data['prefix']);
        }elseif($dtyType==='freetext'){
            
            $query = 'SELECT d.dtl_Value FROM Records r '
                .'INNER JOIN recDetails d ON d.dtl_RecID=r.rec_ID '
                .'WHERE r.rec_ID IN ('.implode(',', $this->recIDs).') '
                .'AND d.dtl_DetailTypeID='.$dtyID.' '
                ."AND d.dtl_Value REGEXP '-[0-9]+$' "
                .'ORDER BY r.rec_RecTypeID, r.rec_ID, d.dtl_ID LIMIT 1';
            $value = mysql__select_value($mysqli, $query);
            
            if($value==null){
                $query = 'SELECT d.dtl_Value FROM Records r '
                    .'INNER JOIN recDetails d ON d.dtl_RecID=r.rec_ID '
                    .'WHERE r.rec_ID IN ('.implode(',', $this->recIDs).') '
                    .'AND d.dtl_DetailTypeID='.$dtyID.' '
                    .'ORDER BY r.rec_RecTypeID, r.rec_ID, d.dtl_ID LIMIT 1';
                $value = mysql__select_value($mysqli, $query);
            }
            
            if($mysqli->error){
                $this->system->addError(
                    HEURIST_DB_ERROR,
                    'Cannot determine the default increment prefix: '.$mysqli->error
                );
                return false;
            }

            if($value!==null){
                $foundPrefix = $this->_getIncrementPrefix($value);
                if($foundPrefix!==null){
                    $defaultPrefix = $foundPrefix;
                }
            }
        }

        // mysql__select_assoc_grouped groups by its first selected column.
        $recordsByRecType = mysql__select_assoc_grouped(
            $mysqli,
            'SELECT rec_RecTypeID, rec_ID FROM Records '
            .'WHERE rec_ID IN ('.implode(',', $this->recIDs).') '
            .'ORDER BY rec_RecTypeID, rec_ID'
        );

        $progressDone = 0;
        foreach($recordsByRecType as $recTypeID => $recordIDs){

            $detailsByRecord = array();
            $existingIncrementValues = array();
            $recordsToAssign = array();

            foreach($recordIDs as $recID){
                if(!$this->_progressStep($progressDone, null, 'Preparing increment values', 10)){
                    break 2;
                }
                $recID = intval($recID);

                $res = $mysqli->query(
                    'SELECT dtl_ID, dtl_Value FROM recDetails '
                    .'WHERE dtl_DetailTypeID='.$dtyID.' AND dtl_RecID='.$recID.' '
                    .'ORDER BY dtl_ID LIMIT 1'
                );

                if(!$res){
                    $sql_errors[$recID] = $mysqli->error;
                    continue;
                }

                $dtlID = -1;
                $currentValue = null;

                if($res->num_rows>0){
                    $values = $res->fetch_row();
                    $dtlID = intval($values[0]);
                    $currentValue = $values[1];
                }
                $res->close();

                $detailsByRecord[$recID] = array(
                    'dtlID' => $dtlID,
                    'value' => $currentValue
                );

                $existingIncrement = null;
                if($dtlID>0){
                    if($dtyType==='freetext'){
                        $existingIncrement = $this->_getIncrementSuffix($currentValue);
                    }elseif(is_numeric($currentValue)){
                        $existingIncrement = intval($currentValue);
                    }
                }

                // Sequence values are positive integers; zero/negative values are replaced.
                if($continueSequence && $existingIncrement!==null && $existingIncrement>0){
                    $existingIncrementValues[] = $existingIncrement;
                    $skipped_recs[] = $recID;
                }else{
                    $recordsToAssign[] = $recID;
                }
            }

            if(isEmptyArray($recordsToAssign)){
                continue;
            }

            $sequence = $this->_getIncrementSequence(
                $continueSequence ? $existingIncrementValues : array(),
                count($recordsToAssign),
                $fillGaps
            );

            foreach($recordsToAssign as $idx => $recID){
                if(!$this->_progressStep($progressDone, null, 'Assigning increment values', 10)){
                    break 2;
                }
                $recID = intval($recID);
                $dtlID = $detailsByRecord[$recID]['dtlID'];
                $currentValue = $detailsByRecord[$recID]['value'];
                $incrementValue = $sequence[$idx];

                $value = ($dtyType==='freetext')
                    ? $this->_setIncrementSuffix($currentValue, $incrementValue, $defaultPrefix, $digits)
                    : $incrementValue;

                if($dtlID>0){
                    $dtl_rec = array(
                        'dtl_ID' => $dtlID,
                        'dtl_Value' => $value,
                        'dtl_Modified' => $date_mode
                    );
                }else{
                    $dtl_rec = array(
                        'dtl_RecID' => $recID,
                        'dtl_DetailTypeID' => $dtyID,
                        'dtl_Value' => $value,
                        'dtl_Modified' => $date_mode
                    );
                }

                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl_rec);
                if(!is_numeric($ret)){
                    $sql_errors[$recID] = $ret;
                    continue;
                }

                $ret = mysql__insertupdate(
                    $mysqli,
                    'Records',
                    'rec',
                    array('rec_ID' => $recID, 'rec_Modified' => $date_mode)
                );
                if(!is_numeric($ret)){
                    $sql_errors[$recID] = ERR_REC_MODDATE.$ret;
                    continue;
                }

                if(!recordUpdateTitle($this->system, $recID, intval($recTypeID), null)){
                    $sql_errors[$recID] = ERR_REC_TITLE;
                }

                $completed_recs[] = $recID;
                $progressDone++;
            }
        }

        $this->_assignTagsAndReport('processed', $completed_recs, $baseTag);
        $this->_assignTagsAndReport('errors', $sql_errors, $baseTag);

        $this->result_data['undefined'] = count($skipped_recs);
        $this->result_data['undefined_list'] = $skipped_recs;

        return $this->result_data;
    }
}
