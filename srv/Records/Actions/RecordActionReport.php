<?php
/**
* RecordActionReport.php - Counts and record ids of a record action (plan 33)
*
* The report is the job result. It is also the result of a job that stops early
* (Stop, time limit, failure: JobContext::setPartialResult): every record is saved
* in its own transaction, so the records before the current one stay changed.
*
*   {action, field, fieldName, complete, stage (scope|records), done, total, lastRecordId,
*    records: {scope, changed, unchanged, skipped, errors},
*    skipped: {reason: count}, values: {found, added, removed},
*    ids: {changed, skipped, errors} (at most MAX_IDS each), idsTruncated,
*    errors: [{id, message}] (at most MAX_ERRORS), seconds}
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

/** Report of a record action. */
final class RecordActionReport
{
    /** Record ids kept per list. */
    public const MAX_IDS = 10000;

    /** Error messages kept. */
    public const MAX_ERRORS = 100;

    private array $data;
    private float $startedAt;

    /**
     * @param string $action Action.
     * @param int $field Field id.
     * @param string $fieldName Field name.
     */
    public function __construct(string $action, int $field, string $fieldName)
    {
        $this->startedAt = microtime(true);
        $this->data = array(
            'action' => $action,
            'field' => $field,
            'fieldName' => $fieldName,
            'complete' => false,
            'stage' => 'scope',
            'done' => 0,
            'total' => 0,
            'lastRecordId' => 0,
            'records' => array('scope' => 0, 'changed' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => 0),
            'skipped' => array(),
            'values' => array('found' => 0, 'added' => 0, 'removed' => 0),
            'ids' => array('changed' => array(), 'skipped' => array(), 'errors' => array()),
            'idsTruncated' => false,
            'errors' => array(),
            'seconds' => 0
        );
    }

    /** The records are found: the stage "records" starts. */
    public function scope(int $total): void
    {
        $this->data['stage'] = 'records';
        $this->data['total'] = $total;
        $this->data['records']['scope'] = $total;
    }

    /**
     * Add the outcome of one record (FieldValueAction::run).
     *
     * @param int $recordId Record id.
     * @param array $outcome {status, reason?, message?, found, added, removed}.
     */
    public function add(int $recordId, array $outcome): void
    {
        $status = (string)$outcome['status'];
        $this->data['done']++;
        $this->data['lastRecordId'] = $recordId;
        foreach(array('found', 'added', 'removed') as $key){
            $this->data['values'][$key] += intval($outcome[$key] ?? 0);
        }
        if($status === 'changed'){
            $this->data['records']['changed']++;
            $this->addId('changed', $recordId);
        }elseif($status === 'skipped'){
            $this->data['records']['skipped']++;
            $reason = (string)($outcome['reason'] ?? 'other');
            $this->data['skipped'][$reason] = ($this->data['skipped'][$reason] ?? 0) + 1;
            $this->addId('skipped', $recordId);
        }elseif($status === 'error'){
            $this->data['records']['errors']++;
            $this->addId('errors', $recordId);
            if(count($this->data['errors']) < self::MAX_ERRORS){
                $this->data['errors'][] = array('id' => $recordId, 'message' => (string)($outcome['message'] ?? ''));
            }
        }else{
            $this->data['records']['unchanged']++;
        }
    }

    /**
     * The report as stored.
     *
     * @param bool $complete True when every record was handled.
     */
    public function toArray(bool $complete = false): array
    {
        $data = $this->data;
        $data['complete'] = $complete;
        $data['skipped'] = (object)$data['skipped'];
        if($data['field'] < 1){ $data['values'] = null; } // value counts: field value actions only
        $data['seconds'] = round(microtime(true) - $this->startedAt, 1);
        return $data;
    }

    private function addId(string $list, int $recordId): void
    {
        if(count($this->data['ids'][$list]) < self::MAX_IDS){
            $this->data['ids'][$list][] = $recordId;
        }else{
            $this->data['idsTruncated'] = true;
        }
    }
}
