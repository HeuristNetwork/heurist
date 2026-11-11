<?php
/**
* RecordWriteAction.php - The record actions other than field values (plan 33):
*   ownership     owner and visibility by the field-level PATCH (header only; unchanged
*                 records are not written)
*   titles        RecordSaveService::rebuildTitle (a blank new title is not written: skipped "blank")
*   calculations  RecordSaveService::rebuildCalculated (failed formulas: error with the messages)
*   delete        RecordDeleteService::delete with force (pointers to the record removed,
*                 its relationship records deleted)
* Refusals: FieldValueAction::refusal (403 rights, 404 missing).
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

use Heurist\Records\Edit\RecordDeleteService;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Edit\RecordInput;
use Heurist\Records\Edit\RecordSaveService;
use InvalidArgumentException;
use Throwable;

/** Ownership, titles, calculated fields or delete of one record. */
final class RecordWriteAction implements RecordActionInterface
{
    private string $action;
    private RecordSaveService $save;
    private ?RecordDeleteService $delete;
    private ?RecordInput $input = null;

    /**
     * @param RecordActionRequest $request The action (ownership, titles, calculations or delete).
     * @param RecordSaveService $save Record writes.
     * @param RecordDeleteService|null $delete Record deletes (delete only).
     */
    public function __construct(RecordActionRequest $request, RecordSaveService $save, ?RecordDeleteService $delete = null)
    {
        if(!in_array($request->action, array('ownership', 'titles', 'calculations', 'delete'), true)){
            throw new InvalidArgumentException('RecordWriteAction does not run "'.$request->action.'"');
        }
        $this->action = $request->action;
        $this->save = $save;
        $this->delete = $delete;
        if($this->action === 'ownership'){
            $this->input = RecordInput::fromBody($request->patchBody());
        }
    }

    /** @inheritDoc */
    public function run(int $recordId, int $rectype): array
    {
        try{
            switch($this->action){
                case 'ownership':
                    $result = $this->save->save('PATCH', $recordId, $this->input, true);
                    return array('status' => !empty($result['header']) ? 'changed' : 'unchanged');
                case 'titles':
                    $result = $this->save->rebuildTitle($recordId);
                    return $result['status'] === 'blank'
                        ? array('status' => 'skipped', 'reason' => 'blank', 'message' => 'The new title would be blank')
                        : array('status' => $result['status']);
                case 'calculations':
                    $result = $this->save->rebuildCalculated($recordId);
                    $outcome = array('status' => $result['status']);
                    if(!empty($result['errors'])){
                        $outcome['message'] = implode('; ', array_map(static function($field, $message): string {
                            return 'field '.$field.': '.$message;
                        }, array_keys($result['errors']), $result['errors']));
                    }
                    return $outcome;
                default:
                    $result = $this->delete->delete($recordId, true);
                    return array('status' => 'changed',
                        'relationships' => intval($result['relationshipsDeleted'] ?? 0),
                        'bookmarks' => intval($result['bookmarks'] ?? 0));
            }
        }catch(RecordEditException $exception){
            return FieldValueAction::refusal($exception);
        }catch(Throwable $exception){
            return array('status' => 'error', 'message' => $exception->getMessage());
        }
    }
}
