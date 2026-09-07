<?php
/**
* TimeFieldSelector.php - Parser for native and linked temporal fields
*
* Normalizes the public `timefields` parameter. It deliberately performs no
* SQL and no record traversal; those responsibilities remain in the shared
* record-data and expansion services.
*
* @project     Heurist academic knowledge management system
* @package     Records\Time
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Records\Time;

use Heurist\Records\Query\QueryValidationException;

/** Normalizes direct detail IDs and linked detail paths requested by timeline. */
final class TimeFieldSelector
{
    /**
     * @return array{allNative:bool,native:array<int,int>,linked:array<int,array<string,mixed>>}
     */
    public function parse($timeFields): array
    {
        if($timeFields === null || $timeFields === '' || $timeFields === array()){
            return array('allNative'=>true, 'native'=>array(), 'linked'=>array());
        }

        $values = is_array($timeFields) ? $timeFields : explode(',', (string)$timeFields);
        $native = array();
        $linked = array();
        $allNative = false;

        foreach($values as $value){
            if(is_array($value)){
                throw new QueryValidationException('Timeline timefields must be detail IDs or linked field paths');
            }
            $code = trim((string)$value);
            if($code === ''){ continue; }
            if(strtolower($code) === 'all'){
                $allNative = true;
            }elseif(ctype_digit($code) && intval($code)>0){
                $native[intval($code)] = intval($code);
            }else{
                $field = $this->parseCode($code);
                if($field['traversal'] === null){
                    $native[$field['fieldId']] = $field['fieldId'];
                }else{
                    $linked[$code] = $field;
                }
            }
        }

        return array(
            'allNative'=>$allNative,
            'native'=>array_values($native),
            'linked'=>array_values($linked)
        );
    }

    /** @return array{code:string,fieldId:int,traversal:?string} */
    private function parseCode(string $code): array
    {
        $tokens = array_values(array_map('trim', explode(':', $code)));

        // As in geofields, `recordTypeID:detailTypeID` is a direct field
        // qualification rather than a traversal.
        if(count($tokens)===2 && ctype_digit($tokens[0]) && ctype_digit($tokens[1])){
            return array('code'=>$code, 'fieldId'=>intval($tokens[1]), 'traversal'=>null);
        }

        if(count($tokens)<4 || count($tokens)%2!==0 || !ctype_digit($tokens[0])){
            throw new QueryValidationException('Invalid temporal field path: '.$code);
        }
        $terminal = array_pop($tokens);
        if(!ctype_digit($terminal) || intval($terminal)<1){
            throw new QueryValidationException('Temporal field path must end with a detail-type ID: '.$code);
        }
        for($index=1; $index<count($tokens); $index+=2){
            if(!preg_match('/^(lt|lf|rt|rf)[0-9]*$/i', $tokens[$index])
                || !isset($tokens[$index+1]) || !ctype_digit($tokens[$index+1])){
                throw new QueryValidationException('Invalid temporal field path: '.$code);
            }
        }

        return array(
            'code'=>$code,
            'fieldId'=>intval($terminal),
            'traversal'=>implode(':', $tokens)
        );
    }
}
