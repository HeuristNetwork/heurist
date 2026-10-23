<?php
/**
* CalculatedFields.php - Values of calculated fields on save
*
* Port of recordUpdateCalcFields() for one saved record (hserv/records/edit/recordModify.php):
* each field of the record type with a calculation function (rst_CalcFunctionID ->
* defCalcFunctions.cfn_FunctionSpecification) gets the output of its Smarty formula,
* run for the record with $r and $heurist. NAN, INF and NULL are stored as no value;
* an unchanged value is not written; a formula error leaves the field as it is and is
* reported. Records of other types that depend on this record
* (findAndUpdateAffectedCalcFields) are not updated here (plan 14).
*
* @project     Heurist academic knowledge management system
* @package     Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Edit;

use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Store\RecordStore;
use Throwable;

/** Runs the calculated fields of a record. */
final class CalculatedFields
{
    private DefinitionLookup $definitions;
    private RecordStore $store;
    /** @var callable fn(string $formula, int $recordId): array{output:string,error:?string} */
    private $evaluate;
    private ?RecordValidator $validator;

    /**
     * @param callable $evaluate Runs a Smarty formula for a record (SmartyTemplateRunner, txt, $r assigned).
     */
    public function __construct(DefinitionLookup $definitions, RecordStore $store, callable $evaluate, ?RecordValidator $validator = null)
    {
        $this->definitions = $definitions;
        $this->store = $store;
        $this->evaluate = $evaluate;
        $this->validator = $validator;
    }

    /**
     * Calculate and store the calculated fields of a saved record.
     *
     * @return array<int,string> field id => error message of formulas that failed
     */
    public function update(int $recordId, int $rectypeId): array
    {
        $errors = array();
        foreach($this->definitions->calculatedFields($rectypeId) as $fieldId => $formula){
            if(trim($formula) === ''){
                $errors[$fieldId] = 'Formula not defined';
                continue;
            }
            try{
                $result = call_user_func($this->evaluate, $formula, $recordId);
            }catch(Throwable $exception){
                $result = array('output' => '', 'error' => $exception->getMessage());
            }
            if(!empty($result['error'])){
                $errors[$fieldId] = (string)$result['error'];
                continue;
            }
            $value = trim((string)($result['output'] ?? ''));
            if(in_array($value, array('NAN', 'INF', 'NULL'), true)){ $value = ''; }
            $type = $this->definitions->fieldType($fieldId);
            if(in_array($type, array('freetext', 'blocktext'), true)){
                $value = $this->validator !== null ? $this->validator->cleanText($rectypeId, $fieldId, $type, $value)
                    : \Heurist\Utilities\StoredText::clean($value, $type);
            }
            if($value !== '' && $this->definitions->fieldType($fieldId) === 'resource'){
                $target = ctype_digit($value) && intval($value)>0
                    ? $this->store->records()->header(intval($value), array('rec_ID', 'rec_RecTypeID', 'rec_FlagTemporary')) : null;
                $types = $this->definitions->fieldConstraints($fieldId)['targetTypes'];
                if($target === null || !empty($target['rec_FlagTemporary'])
                    || (!empty($types) && !in_array(intval($target['rec_RecTypeID']), $types))){
                    $errors[$fieldId] = 'Calculated record link must be one existing record ID of an allowed target type';
                    continue;
                }
            }
            $currentValues = $this->store->records()->values(array($recordId), array($fieldId))[$recordId][$fieldId] ?? array();
            $current = (string)($currentValues[0] ?? '');
            $pointerCardinalityValid = $this->definitions->fieldType($fieldId) !== 'resource'
                || count($currentValues) === ($value === '' ? 0 : 1);
            if($current === $value && $pointerCardinalityValid){ continue; }
            $this->store->setFieldValue($recordId, $fieldId, $value === '' ? null : $value);
        }
        return $errors;
    }
}
