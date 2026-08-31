<?php
/**
* RecordFieldSelector.php - Parser for native and linked record output fields
*
* @project     Heurist academic knowledge management system
* @package     Records\Data
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

namespace Heurist\Records\Data;

use Heurist\Records\Query\QueryValidationException;

/** Converts the fields parameter into validated header and detail selections. */
final class RecordFieldSelector
{
    private const HEADERS = array(
        'rec_id'=>'rec_ID',
        'rec_rectypeid'=>'rec_RecTypeID',
        'rec_title'=>'rec_Title',
        'rec_url'=>'rec_URL',
        'rec_scratchpad'=>'rec_ScratchPad',
        'rec_ownerugrpid'=>'rec_OwnerUGrpID',
        'rec_nonownervisibility'=>'rec_NonOwnerVisibility',
        'rec_added'=>'rec_Added',
        'rec_modified'=>'rec_Modified',
        'rec_addedbyugrpid'=>'rec_AddedByUGrpID',
        'rec_hash'=>'rec_Hash'
    );

    /** Parse CSV/array fields. Paths end with a terminal detail-type ID. */
    public function parse($fields): array
    {
        if($fields === null || $fields === ''){
            return array('headers'=>array(), 'details'=>array());
        }
        $values = is_array($fields) ? $fields : explode(',', (string)$fields);
        $headers = array();
        $details = array();
        foreach($values as $value){
            if(is_array($value)){
                throw new QueryValidationException('Record fields must be names, IDs, or compact path codes');
            }
            $field = trim((string)$value);
            if($field === ''){ continue; }
            $headerKey = strtolower($field);
            if(isset(self::HEADERS[$headerKey])){
                $headers[self::HEADERS[$headerKey]] = self::HEADERS[$headerKey];
                continue;
            }
            if(ctype_digit($field) && intval($field)>0){
                $id = intval($field);
                $details[$field] = array(
                    'key'=>$field, 'fieldId'=>$id, 'pathCode'=>null, 'traversal'=>null
                );
                continue;
            }
            if(preg_match('/^([0-9]+):([0-9]+)$/', $field, $match)
                && intval($match[1])>0 && intval($match[2])>0){
                $details[$field] = array(
                    'key'=>$field,
                    'fieldId'=>intval($match[2]),
                    'pathCode'=>$field,
                    'traversal'=>null
                );
                continue;
            }
            $details[$field] = $this->parsePath($field);
        }
        return array(
            'headers'=>array_values($headers),
            'details'=>array_values($details)
        );
    }

    private function parsePath(string $path): array
    {
        $tokens = array_values(array_map('trim', explode(':', $path)));
        if(count($tokens)<4 || count($tokens)%2!==0 || !ctype_digit($tokens[0])){
            throw new QueryValidationException('Invalid linked output field path: '.$path);
        }
        $fieldToken = array_pop($tokens);
        if(!ctype_digit($fieldToken) || intval($fieldToken)<1){
            throw new QueryValidationException('Linked output path must end with a detail-type ID: '.$path);
        }
        for($index=1; $index<count($tokens); $index+=2){
            if(!preg_match('/^(lt|lf|rt|rf)[0-9]*$/i', $tokens[$index])
                || !isset($tokens[$index+1]) || !ctype_digit($tokens[$index+1])){
                throw new QueryValidationException('Invalid linked output field path: '.$path);
            }
        }
        return array(
            'key'=>$path,
            'fieldId'=>intval($fieldToken),
            'pathCode'=>$path,
            'traversal'=>implode(':', $tokens)
        );
    }
}
