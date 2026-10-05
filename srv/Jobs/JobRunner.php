<?php
/**
* JobRunner.php - Starts, runs, lists and stops background jobs
*
* Rules:
* - only logged-in users start jobs; a user sees and stops their own jobs, the
*   database owner and managers see and stop all;
* - one active job of the same type per user, at most $maxPerDatabase active
*   jobs in the database;
* - a job ends as done, failed, cancelled or timeout (or lost, see JobStore).
*
* @project     Heurist academic knowledge management system
* @package     Jobs
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Jobs;

use DomainException;
use Heurist\Runtime\RuntimeContext;
use InvalidArgumentException;
use OutOfBoundsException;
use Throwable;

/** Job life cycle. */
final class JobRunner
{
    private JobStore $store;
    private RuntimeContext $runtime;
    /** @var array<string,JobHandlerInterface> */
    private array $handlers = array();
    private int $maxPerDatabase;
    /** @var callable|null fn(int $connectionId): bool */
    private $killQuery;

    /**
     * @param JobStore $store Job storage.
     * @param RuntimeContext $runtime Current user.
     * @param array<int,JobHandlerInterface> $handlers Available job types.
     * @param int $maxPerDatabase Active jobs allowed in one database.
     * @param callable|null $killQuery Stops the statement on a connection (KILL QUERY).
     */
    public function __construct(
        JobStore $store,
        RuntimeContext $runtime,
        array $handlers,
        int $maxPerDatabase = 3,
        ?callable $killQuery = null
    ) {
        $this->store = $store;
        $this->runtime = $runtime;
        foreach($handlers as $handler){
            $this->handlers[$handler->type()] = $handler;
        }
        $this->maxPerDatabase = max(1, $maxPerDatabase);
        $this->killQuery = $killQuery;
    }

    /**
     * Validate a request and create a queued job. The caller runs it with run().
     *
     * @param string $type Job type.
     * @param array $params Request parameters for the handler.
     * @return array Public job state.
     */
    public function start(string $type, array $params): array
    {
        $this->requireMember();
        $handler = $this->handlers[$type] ?? null;
        if($handler === null){
            throw new InvalidArgumentException('Unknown job type: '.$type);
        }
        $this->store->cleanup();
        if($handler->replacesPrevious()){
            // a new test run replaces the user's older one (its page no longer needs it)
            foreach($this->store->activeJobs($this->runtime->userId, $type, true) as $previous){
                $this->cancel((string)$previous['id']);
            }
        }
        // jobs asked to stop end at their next check; they still count for the database limit
        if($this->store->countActive($this->runtime->userId, $type, true) > 0){
            throw new DomainException('You already have a running job of this kind. Wait for it or stop it.');
        }
        if($this->store->countActive() >= $this->maxPerDatabase){
            throw new DomainException('Too many jobs are running in this database. Please try again later.');
        }
        $prepared = $handler->prepare($params);
        $job = $this->store->create(
            $type,
            $this->runtime->userId,
            (string)($prepared['title'] ?? $type),
            (array)($prepared['params'] ?? array()),
            $handler->limitSeconds()
        );
        return $this->publicJob($job);
    }

    /**
     * Create and run a job for a system task (cron) without user checks.
     * The parameters must already be normalized (as returned by prepare()).
     *
     * @return array Public job state after the run.
     */
    public function runSystemJob(string $type, string $title, array $params): array
    {
        $handler = $this->handlers[$type] ?? null;
        if($handler === null){
            throw new InvalidArgumentException('Unknown job type: '.$type);
        }
        $this->store->cleanup();
        $job = $this->store->create($type, $this->runtime->userId, $title, $params, $handler->limitSeconds());
        return $this->run((string)$job['id']);
    }

    /**
     * Run a queued job to its end in this process.
     *
     * @return array Public job state after the run.
     */
    public function run(string $id): array
    {
        $job = $this->store->load($id);
        if($job === null || $job['status'] !== 'queued'){
            throw new OutOfBoundsException('Job '.$id.' is not waiting to run');
        }
        $handler = $this->handlers[$job['type']] ?? null;
        if($handler === null){
            throw new InvalidArgumentException('Unknown job type: '.$job['type']);
        }

        $limit = intval($job['limitSeconds']);
        if($limit > 0){ @set_time_limit($limit + 60); }

        $job['status'] = 'running';
        $job['startedAt'] = time();
        $context = new JobContext($this->store, $job);
        $context->progress(0, 0, 'started');

        $final = $context->job();
        try{
            $context->check();
            $result = $handler->run((array)$job['params'], $context);
            $final = $context->job();
            // Stop or the time limit may come while the handler finishes up
            $reason = $context->interruption();
            if($reason !== null){
                $final['status'] = $reason;
                $final['error'] = (new JobInterruptedException($reason))->getMessage();
            }else{
                $final['status'] = 'done';
                $final['result'] = $result;
            }
        }catch(JobInterruptedException $error){
            $final = $context->job();
            $final['status'] = $error->reason();
            $final['error'] = $error->getMessage();
        }catch(Throwable $error){
            $final = $context->job();
            $reason = $context->interruption();
            $final['status'] = $reason ?? 'failed';
            $final['error'] = $reason !== null ? (new JobInterruptedException($reason))->getMessage() : $error->getMessage();
        }
        $final['finishedAt'] = time();
        $final['heartbeat'] = time();
        $this->store->save($final);
        return $this->publicJob($final);
    }

    /** Ask a job to stop and stop its current SQL statement. */
    public function cancel(string $id): array
    {
        $job = $this->accessibleJob($id);
        if(in_array($job['status'], JobStore::ACTIVE, true)){
            $this->store->requestCancel($id);
            if($this->killQuery !== null){
                foreach((array)$job['connectionIds'] as $connectionId){
                    ($this->killQuery)(intval($connectionId));
                }
            }
            $job['cancelRequested'] = true;
        }
        return $this->publicJob($job);
    }

    /** One job of the current user (or any job for managers). */
    public function get(string $id): array
    {
        return $this->publicJob($this->accessibleJob($id));
    }

    /**
     * Jobs of the current user; managers may ask for all jobs.
     *
     * @return array<int,array>
     */
    public function listJobs(bool $all = false): array
    {
        $this->requireMember();
        $userId = $all && $this->isManager() ? null : $this->runtime->userId;
        return array_map(array($this, 'publicJob'), $this->store->listJobs($userId));
    }

    /** Stored result content of a finished job, or null. */
    public function resultContent(string $id): ?string
    {
        $job = $this->accessibleJob($id);
        return $job['status'] === 'done' ? $this->store->readResult($id) : null;
    }

    private function accessibleJob(string $id): array
    {
        $this->requireMember();
        if(!JobStore::isValidId($id)){
            throw new InvalidArgumentException('Invalid job id');
        }
        $job = $this->store->load($id);
        if($job === null){
            throw new OutOfBoundsException('Job '.$id.' does not exist');
        }
        if(intval($job['userId']) !== $this->runtime->userId && !$this->isManager()){
            throw new DomainException('This job belongs to another user');
        }
        return $job;
    }

    /** Job state without internal fields. */
    private function publicJob(array $job): array
    {
        $job['elapsedSeconds'] = $job['startedAt'] === null ? 0
            : intval(($job['finishedAt'] ?? time()) - $job['startedAt']);
        $job['cancelRequested'] = !empty($job['cancelRequested'])
            || (in_array($job['status'], JobStore::ACTIVE, true) && $this->store->isCancelRequested((string)$job['id']));
        unset($job['connectionIds'], $job['heartbeat']);
        return $job;
    }

    private function requireMember(): void
    {
        if($this->runtime->userId < 1 || !$this->runtime->hasAccess){
            throw new DomainException('Authentication is required');
        }
    }

    private function isManager(): bool
    {
        return $this->runtime->isDbOwner || $this->runtime->isAdmin;
    }
}
