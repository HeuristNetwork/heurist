<?php
/**
* FieldValueAction.php - Add, replace or delete values of one field of one record
* (record action, plan 33)
*
* Every record is saved by the field-level PATCH of plan 32 (RecordSaveService, one
* transaction per record, force: no rec_Modified check). The rules of PATCH stay: a
* record that would break them is not changed and is reported as skipped:
*   structure        the record type has no such field (or it is forbidden)
*   rights           the user may not edit the record
*   required         the last value of a required field would be deleted
*   single_value     a value added to a single-value field
*   max              more values than the field allows
*   language_exists  the translation exists (page translations)
* Other refusals (an invalid value, ...) are errors.
*
* @project     Heurist academic knowledge management system
* @package     Records\Actions
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Actions;

use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Edit\RecordInput;
use Heurist\Records\Edit\RecordSaveService;
use Throwable;

/** Field value change of one record. */
final class FieldValueAction implements RecordActionInterface
{
    /** Codes of PATCH refusals reported as skipped records. */
    public const SKIP_CODES = array('required', 'single_value', 'max', 'language_exists');

    private RecordSaveService $save;
    private DefinitionLookup $definitions;
    private RecordActionRequest $request;
    private RecordInput $input;

    /**
     * @param RecordSaveService $save Record writes.
     * @param DefinitionLookup $definitions Record structures.
     * @param RecordActionRequest $request The action.
     */
    public function __construct(RecordSaveService $save, DefinitionLookup $definitions, RecordActionRequest $request)
    {
        $this->save = $save;
        $this->definitions = $definitions;
        $this->request = $request;
        $this->input = RecordInput::fromBody($request->patchBody());
    }

    /**
     * Whether a record type has the field (not forbidden).
     *
     * @param int $rectype Record type.
     */
    public function hasField(int $rectype): bool
    {
        $row = $this->definitions->structureRows($rectype)[$this->request->field] ?? null;
        return $row !== null && $row['requirement'] !== 'forbidden';
    }

    /**
     * Change one record.
     *
     * @param int $recordId Record id.
     * @param int $rectype Its record type.
     * @return array{status:string,reason?:string,message?:string,found:int,added:int,removed:int}
     *         status changed | unchanged | skipped | error
     */
    public function run(int $recordId, int $rectype): array
    {
        $none = array('found' => 0, 'added' => 0, 'removed' => 0);
        if(!$this->hasField($rectype)){
            return array('status' => 'skipped', 'reason' => 'structure') + $none;
        }
        try{
            $result = $this->save->save('PATCH', $recordId, $this->input, true);
        }catch(RecordEditException $exception){
            return self::refusal($exception) + $none;
        }catch(Throwable $exception){
            return array('status' => 'error', 'message' => $exception->getMessage()) + $none;
        }
        $values = $result['values'][$this->request->field] ?? $none;
        $counts = array(
            'found' => intval($values['found'] ?? 0),
            'added' => intval($values['added'] ?? 0),
            'removed' => intval($values['removed'] ?? 0)
        );
        return array('status' => $counts['added'] + $counts['removed'] > 0 ? 'changed' : 'unchanged') + $counts;
    }

    /**
     * Outcome of a refused write: 403 → skipped "rights", 404 → skipped "missing", a PATCH rule
     * (SKIP_CODES) → skipped with its code, else an error (also used by the other actions).
     *
     * @return array{status:string,reason?:string,message:string}
     */
    public static function refusal(RecordEditException $exception): array
    {
        if($exception->status() === 403){
            return array('status' => 'skipped', 'reason' => 'rights', 'message' => $exception->getMessage());
        }
        if($exception->status() === 404){
            return array('status' => 'skipped', 'reason' => 'missing', 'message' => $exception->getMessage());
        }
        foreach($exception->issues() as $issue){
            if(in_array($issue['code'] ?? '', self::SKIP_CODES, true)){
                return array('status' => 'skipped', 'reason' => (string)$issue['code'],
                    'message' => (string)($issue['message'] ?? $exception->getMessage()));
            }
        }
        return array('status' => 'error', 'message' => self::message($exception));
    }

    /** Message of a refusal with the first field issue. */
    private static function message(RecordEditException $exception): string
    {
        $issue = $exception->issues()[0] ?? null;
        $text = $issue !== null ? (string)($issue['message'] ?? '') : '';
        return $text !== '' && $text !== $exception->getMessage() ? $exception->getMessage().': '.$text : $exception->getMessage();
    }
}
