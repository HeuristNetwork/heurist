<?php
namespace hserv\records\batch\field;

use hserv\records\batch\RecordsBatchAction;

/** Convert both freetext coordinate fields in place, retaining every repeat's detail ID.
 * Direction is selected explicitly: fixed quadrant, suffix letters, or numeric sign.
 * Invalid values are reported and preserved. Each record's valid changes are atomic.
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class RecordsBatchCoordinatesToDecimal extends RecordsBatchAction
{
    /** Parse a full DMS value (or an already converted decimal), never a partial match.
     * Return null on malformed, out-of-range or contradictory directional input.
     */
    public static function convert($value, $latitude, $method, $quadrant, $letters)
    {
        $text = preg_replace('/[\s\p{Z}]+/u', '', trim($value));
        $text = str_replace(array('−', '′', '’', '″', '”'), array('-', "'", "'", '"', '"'), $text);
        $text = str_replace("''", '"', $text);
        // Existing decimals must be preserved so rerunning does not change their hemisphere.
        if(preg_match('/^[+-]?\d+(?:\.\d+)?$/D', $text)){
            return abs((float)$text) <= ($latitude ? 90 : 180) ? $text : null;
        }
        if(!preg_match('/^([+-]?)(\d+(?:\.\d+)?)°(\d+(?:\.\d+)?)\x27(?:(\d+(?:\.\d+)?)")?([A-Za-z]?)$/Du', $text, $m)){
            return null;
        }
        $degrees = (float)$m[2]; $minutes = (float)$m[3]; $seconds = isset($m[4]) && $m[4] !== '' ? (float)$m[4] : 0;
        $limit = $latitude ? 90 : 180;
        if($minutes >= 60 || $seconds >= 60 || $degrees > $limit
                || ($degrees == $limit && ($minutes > 0 || $seconds > 0))){
            return null;
        }
        $suffix = strtoupper($m[5]);
        if($method === 'quadrant'){
            $negative = strpos($quadrant, $latitude ? 'S' : 'W') !== false;
        }elseif($method === 'sign'){
            // Letters are only interpreted in indicator mode; reject ambiguous sign input.
            if($suffix !== ''){return null;}
            $negative = $m[1] === '-';
        }else{
            $positive = $letters[$latitude ? 'north' : 'east'];
            $negativeLetter = $letters[$latitude ? 'south' : 'west'];
            if($suffix !== $positive && $suffix !== $negativeLetter){return null;}
            $negative = $suffix === $negativeLetter;
            if(($m[1] === '-' && !$negative) || ($m[1] === '+' && $negative)){return null;}
        }
        $decimal = ($degrees + $minutes / 60 + $seconds / 3600) * ($negative ? -1 : 1);
        $result = rtrim(rtrim(number_format($decimal, 8, '.', ''), '0'), '.');
        return $result === '-0' ? '0' : $result;
    }

    public function execute()
    {
        // Enforce the server flag here, including direct API and facade calls.
        global $experimental;
        if (!isset($experimental) || $experimental !== true) {
            $this->system->addError(HEURIST_ACTION_BLOCKED, HEURIST_EXPERIMENTAL_UNAVAILABLE_MESSAGE);
            return false;
        }
        $lat = intval(@$this->data['dtyID']);
        $lon = intval(@$this->data['longitudeID']);
        $method = @$this->data['method'];
        $quadrant = @$this->data['quadrant'];
        $letters = array();
        foreach(array('north'=>'N', 'south'=>'S', 'east'=>'E', 'west'=>'W') as $direction=>$default){
            $letters[$direction] = strtoupper(trim(isset($this->data[$direction]) ? $this->data[$direction] : $default));
        }
        if($lat < 1 || $lon < 1 || $lat === $lon || $this->getDetailType($lat) !== 'freetext'
                || $this->getDetailType($lon) !== 'freetext'
                || !in_array($method, array('quadrant','indicators','sign'), true)
                || ($method === 'quadrant' && !in_array($quadrant, array('NE','NW','SE','SW'), true))
                || ($method === 'indicators' && (count(array_unique($letters)) !== 4
                    || preg_match('/^[A-Z]{4}$/D', implode('', $letters)) !== 1))){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Choose two different single line text fields and valid coordinate options.');
            return false;
        }
        if(!$this->_validateParamsAndCounts()){return false;}
        $mysqli = $this->system->getMysqli();
        // The standard dialog passes explicit result IDs; also support the batch ALL convention.
        if(@$this->recIDs[0] === 'all'){
            $where = empty($this->rtyIDs) ? '' : ' WHERE rec_RecTypeID IN ('.implode(',', $this->rtyIDs).')';
            $this->recIDs = mysql__select_list($mysqli, 'Records', 'rec_ID', $where === '' ? '1=1' : substr($where, 7));
        }
        $done = array(); $empty = array(); $already = array(); $uninterpretable = array(); $errors = array();
        $csvRows = array();
        $date = date(DATE_8601);
        foreach($this->recIDs as $idx=>$recID){
            if(!$this->_progressStep($idx, null, 'Converting coordinates', 10)){break;}
            $recID = intval($recID);
            $res = $mysqli->query("SELECT dtl_ID, dtl_DetailTypeID, dtl_Value FROM recDetails WHERE dtl_RecID=$recID AND dtl_DetailTypeID IN ($lat,$lon) ORDER BY dtl_ID");
            if(!$res){$errors[$recID] = $mysqli->error; continue;}
            $changes = array(); $invalid = array(); $hasValues = false;
            $export = array('latitude'=>array(), 'longitude'=>array());
            while($row = $res->fetch_assoc()){
                $isLat = intval($row['dtl_DetailTypeID']) === $lat;
                $hasValues = $hasValues || trim($row['dtl_Value']) !== '';
                $converted = trim($row['dtl_Value']) === '' ? $row['dtl_Value'] : self::convert($row['dtl_Value'], $isLat, $method, $quadrant, $letters);
                $export[$isLat ? 'latitude' : 'longitude'][] = array($row['dtl_Value'], $converted);
                if($converted === null){
                    $invalid[] = 'Invalid '.($isLat ? 'Latitude' : 'Longitude').' '.$row['dtl_Value'];
                }elseif($converted !== $row['dtl_Value']){
                    $changes[] = array('dtl_ID'=>intval($row['dtl_ID']), 'dtl_Value'=>$converted, 'dtl_Modified'=>$date);
                }
            }
            $res->free();
            if(!empty($invalid)){$uninterpretable[] = $recID;}
            $saved = false;
            if(!empty($changes)){
                if(!$mysqli->begin_transaction()){$invalid[] = 'Cannot start transaction: '.$mysqli->error; $failed = true;}
                else{$failed = false;}
                foreach($changes as $change){
                    if($failed){break;}
                    $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $change);
                    if(!is_numeric($ret)){$invalid[] = 'Database update failed: '.$ret; $failed = true; break;}
                }
                if(!$failed && !recordUpdateTitle($this->system, $recID, null, null)){
                    $invalid[] = 'Cannot update record title'; $failed = true;
                }
                if(!$failed){
                    $ret = mysql__insertupdate($mysqli, 'Records', 'rec', array('rec_ID'=>$recID, 'rec_Modified'=>$date));
                    if(!is_numeric($ret)){$invalid[] = 'Record update failed: '.$ret; $failed = true;}
                }
                if($failed){$mysqli->rollback();}
                elseif(!$mysqli->commit()){$mysqli->rollback(); $invalid[] = 'Commit failed: '.$mysqli->error;}
                else{$saved = true; $done[] = $recID;}
            }elseif(empty($invalid)){
                if($hasValues){$already[] = $recID;}else{$empty[] = $recID;}
            }
            // One CSV row per repeat position; never imply a Cartesian pairing.
            $count = $saved ? max(count($export['latitude']), count($export['longitude'])) : 0;
            for($i=0; $i<$count; $i++){
                $a = isset($export['latitude'][$i]) ? $export['latitude'][$i] : array('', '');
                $b = isset($export['longitude'][$i]) ? $export['longitude'][$i] : array('', '');
                $latChanged = $a[1] !== null && $a[1] !== $a[0];
                $lonChanged = $b[1] !== null && $b[1] !== $b[0];
                if(!$latChanged && !$lonChanged){continue;}
                $csvRows[] = array($recID, $lonChanged ? $b[0] : '', $lonChanged ? $b[1] : '',
                    $latChanged ? $a[0] : '', $latChanged ? $a[1] : '');
            }
            if(!empty($invalid)){$errors[$recID] = htmlspecialchars(implode('  ', $invalid), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');}
        }
        $tag = '~DMS to decimal '.$date;
        $this->_assignTagsAndReport('processed', $done, $tag);
        $this->_assignTagsAndReport('undefined', $empty, $tag);
        $this->_assignTagsAndReport('errors', $errors, $tag);
        $this->result_data['undefined'] = count($empty);
        $this->result_data['undefined_list'] = $empty;
        $this->result_data['already_decimal'] = count($already);
        $this->result_data['no_values'] = count($empty);
        $this->result_data['uninterpretable_values'] = count($uninterpretable);
        $this->result_data['csv_rows'] = $csvRows;
        return $this->result_data;
    }
}
