<?php
/**
* RecordActionJob.php - Background job "record-action": an action over a set of records
* (Explorer Actions tool, plan 33)
*
* One job type for every action, so a user runs one action at a time (JobRunner rule).
* Actions: field-add, field-replace, field-delete (FieldValueAction), ownership, titles,
* calculations, delete (RecordWriteAction). Steps:
*   1. stage "scope": the ids of the scope (RecordScope, at most maxRecords of the export
*      settings) and their record types;
*   2. stage "records": each record in its own transaction; Stop and the time limit are
*      checked before every record.
* Delete: prepare() resolves the ids, checks DeleteGuard (password above 10 records, half of
* the database only for the database owner) and stores the ids, so exactly the confirmed
* records are deleted; the password is never stored.
* The report (RecordActionReport) is the job result; it is also kept when the job stops
* early or fails (JobContext::setPartialResult), with the stage and the last record.
* A single record is changed by PATCH /records/{id} (or DELETE) directly (no job).
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
use Heurist\Jobs\JobContext;
use Heurist\Jobs\JobHandlerInterface;
use Heurist\Records\Edit\RecordDeleteService;
use Heurist\Records\Edit\RecordSaveService;
use Heurist\Records\Query\RecordScope;
use Heurist\Records\Store\RecordRepository;
use InvalidArgumentException;

/** Record action job. */
final class RecordActionJob implements JobHandlerInterface
{
    /** Record types read per request. */
    private const RECTYPE_BATCH = 5000;

    /** Names of the actions for job titles. */
    private const NAMES = array(
        'field-add' => 'Add value', 'field-replace' => 'Replace value', 'field-delete' => 'Delete value',
        'ownership' => 'Ownership and visibility', 'titles' => 'Rebuild record titles',
        'calculations' => 'Rebuild calculation fields', 'delete' => 'Delete records'
    );

    /** @var callable fn(): array{save: RecordSaveService, delete: RecordDeleteService} */
    private $writers;
    private DefinitionLookup $definitions;
    private RecordRepository $records;
    private RecordScope $scope;
    private DeleteGuard $deleteGuard;
    private bool $isDbOwner;
    private int $maxRecords;
    private int $limitSeconds;
    /** @var callable fn(): int database connection id (KILL QUERY on Stop) */
    private $connectionId;

    /**
     * @param callable $writers Returns the record save and delete services (built when the job runs).
     * @param DefinitionLookup $definitions Field definitions and record structures.
     * @param RecordRepository $records Record types of the ids, size of the database.
     * @param RecordScope $scope Ids of the scope.
     * @param DeleteGuard $deleteGuard Rules of a delete.
     * @param bool $isDbOwner The user is the database owner.
     * @param int $maxRecords At most this many records in the scope.
     * @param int $limitSeconds Time limit of one job.
     * @param callable $connectionId Returns the connection id of the job queries.
     */
    public function __construct(callable $writers, DefinitionLookup $definitions, RecordRepository $records,
        RecordScope $scope, DeleteGuard $deleteGuard, bool $isDbOwner, int $maxRecords, int $limitSeconds, callable $connectionId)
    {
        $this->writers = $writers;
        $this->definitions = $definitions;
        $this->records = $records;
        $this->scope = $scope;
        $this->deleteGuard = $deleteGuard;
        $this->isDbOwner = $isDbOwner;
        $this->maxRecords = $maxRecords;
        $this->limitSeconds = $limitSeconds;
        $this->connectionId = $connectionId;
    }

    /** @inheritDoc */
    public function type(): string
    {
        return 'record-action';
    }

    /** @inheritDoc */
    public function limitSeconds(): int
    {
        return $this->limitSeconds;
    }

    /** One action per user; a second one is refused. */
    public function replacesPrevious(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public function prepare(array $params): array
    {
        $request = new RecordActionRequest($params);
        $subject = '';
        if($request->isFieldAction()){
            if($this->definitions->field($request->field) === null){
                throw new InvalidArgumentException('Field '.$request->field.' does not exist');
            }
            $type = $this->definitions->fieldType($request->field);
            if(!in_array($type, RecordActionRequest::FIELD_TYPES, true)){
                throw new InvalidArgumentException('Values of '.$type.' fields cannot be changed by this action');
            }
            $subject = ': '.$this->fieldName($request->field);
        }
        if($request->action === 'delete'){
            // exactly the confirmed records are deleted; the password is checked here and not stored
            $ids = $this->scopeIds($request);
            if(empty($ids)){
                throw new InvalidArgumentException('There are no records to delete');
            }
            $this->deleteGuard->check(count($ids), array_sum($this->records->rectypeCounts()), $this->isDbOwner, $request->password);
            $request = $request->withIds($ids);
            $subject = ': '.count($ids);
        }
        $title = $request->title !== '' ? $request->title : (self::NAMES[$request->action] ?? $request->action).$subject;
        return array('title' => $title, 'params' => $request->toArray());
    }

    /** @inheritDoc */
    public function run(array $params, JobContext $context): array
    {
        $context->addConnectionId(intval(call_user_func($this->connectionId)));
        $request = new RecordActionRequest($params);
        $report = new RecordActionReport($request->action, $request->field,
            $request->isFieldAction() ? $this->fieldName($request->field) : '');
        $context->setPartialResult(static function() use ($report): array { return $report->toArray(false); });

        $context->progress(0, 0, 'Searching the records');
        $ids = $this->scopeIds($request);
        $context->check();
        $rectypes = array();
        foreach(array_chunk($ids, self::RECTYPE_BATCH) as $chunk){
            $rectypes += $this->records->rectypeOf($chunk);
            $context->check();
        }
        $total = count($ids);
        $report->scope($total);
        $context->progress(0, $total, $request->action === 'delete' ? 'Deleting the records' : 'Changing the records');

        $action = $this->action($request);
        foreach($ids as $index => $recordId){
            $context->check();
            $rectype = $rectypes[$recordId] ?? 0;
            $outcome = $rectype > 0 ? $action->run($recordId, $rectype)
                : array('status' => 'skipped', 'reason' => 'missing', 'message' => 'The record does not exist');
            $report->add($recordId, $outcome);
            $context->progress($index + 1, $total);
        }
        return $report->toArray(true);
    }

    /** The handler of one record. */
    private function action(RecordActionRequest $request): RecordActionInterface
    {
        $writers = call_user_func($this->writers);
        return $request->isFieldAction()
            ? new FieldValueAction($writers['save'], $this->definitions, $request)
            : new RecordWriteAction($request, $writers['save'], $writers['delete']);
    }

    /** @return int[] Ids of the scope (at most maxRecords). */
    private function scopeIds(RecordActionRequest $request): array
    {
        return $this->scope->ids($request->query, $request->ids, $request->rectypes, $this->maxRecords, 0,
            'The scope has more than {max} records; an action can change at most {max} (database setting Export: maxRecords)');
    }

    /** Field name (base name of the field). */
    private function fieldName(int $fieldId): string
    {
        $field = $this->definitions->field($fieldId);
        return (string)($field['name'] ?? ('Field '.$fieldId));
    }
}
