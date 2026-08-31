<?php
/**
* MapFieldSelector.php - Parser for native and linked geographic fields
*
* @project     Heurist academic knowledge management system
* @package     Records\Map
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson@heuristnetwork.org>
* @since       7.0
*/

namespace Heurist\Records\Map;

use Heurist\Records\Query\QueryValidationException;

/** Normalizes the geofields parameter without executing traversal or SQL. */
final class MapFieldSelector
{
    public function parse($geoFields): array
    {
        if($geoFields === null || $geoFields === '' || $geoFields === array()){
            return array('allNative'=>true, 'native'=>array(), 'linked'=>array());
        }
        $values = is_array($geoFields) ? $geoFields : explode(',', (string)$geoFields);
        $native = array();
        $linked = array();
        $allNative = false;
        foreach($values as $value){
            if(is_array($value)){
                throw new QueryValidationException('Legacy geofield query objects are not supported by the modern map pipeline');
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

    private function parseCode(string $code): array
    {
        $tokens = array_values(array_map('trim', explode(':', $code)));
        if(count($tokens)===2 && ctype_digit($tokens[0]) && ctype_digit($tokens[1])){
            return array('code'=>$code, 'fieldId'=>intval($tokens[1]), 'traversal'=>null);
        }
        if(count($tokens)<4 || count($tokens)%2!==0 || !ctype_digit($tokens[0])){
            throw new QueryValidationException('Invalid geographic field path: '.$code);
        }
        $terminal = array_pop($tokens);
        if(!ctype_digit($terminal) || intval($terminal)<1){
            throw new QueryValidationException('Geographic field path must end with a detail-type ID: '.$code);
        }
        for($index=1; $index<count($tokens); $index+=2){
            if(!preg_match('/^(lt|lf|rt|rf)[0-9]*$/i', $tokens[$index])
                || !isset($tokens[$index+1]) || !ctype_digit($tokens[$index+1])){
                throw new QueryValidationException('Invalid geographic field path: '.$code);
            }
        }
        return array(
            'code'=>$code,
            'fieldId'=>intval($terminal),
            'traversal'=>implode(':', $tokens)
        );
    }
}
