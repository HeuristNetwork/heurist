<?php
/**
* JobContext.php - What a running job sees: progress, Stop, time limit
*
* progress() writes the job state at most every WRITE_INTERVAL seconds and also
* serves as the heartbeat. check() throws JobInterruptedException after Stop or
* when the time limit is over; interruption() returns the same as a value for
* code that must not throw (legacy callbacks).
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

/** Progress and interruption access for one running job. */
final class JobContext
{
    /** Minimum seconds between two state writes. */
    private const WRITE_INTERVAL = 0.5;

    private JobStore $store;
    private array $job;
    private float $startedAt;
    private float $lastWrite = 0.0;

    /**
     * @param JobStore $store Job storage.
     * @param array $job Job state (status "running").
     */
    public function __construct(JobStore $store, array $job)
    {
        $this->store = $store;
        $this->job = $job;
        $this->startedAt = microtime(true);
    }

    /** Job id. */
    public function id(): string
    {
        return (string)$this->job['id'];
    }

    /** Current job state as last written. */
    public function job(): array
    {
        return $this->job;
    }

    /**
     * Report progress (also the heartbeat).
     *
     * @param int $done Items done.
     * @param int $total Items in total (0 = unknown).
     * @param string|null $message Optional phase text; a new text is written at once.
     */
    public function progress(int $done, int $total, ?string $message = null): void
    {
        $changed = $message !== null && $message !== $this->job['progress']['message'];
        $this->job['progress']['done'] = $done;
        $this->job['progress']['total'] = $total;
        if($message !== null){ $this->job['progress']['message'] = $message; }
        $now = microtime(true);
        if($changed || $now - $this->lastWrite >= self::WRITE_INTERVAL){
            $this->write();
        }
    }

    /** Throw JobInterruptedException after Stop or when the time limit is over. */
    public function check(): void
    {
        $reason = $this->interruption();
        if($reason !== null){
            throw new JobInterruptedException($reason);
        }
    }

    /** "cancelled", "timeout" or null. */
    public function interruption(): ?string
    {
        if($this->store->isCancelRequested($this->id())){ return 'cancelled'; }
        $limit = intval($this->job['limitSeconds'] ?? 0);
        if($limit > 0 && microtime(true) - $this->startedAt > $limit){ return 'timeout'; }
        return null;
    }

    /** Seconds left before the time limit (0 = no limit). */
    public function secondsLeft(): int
    {
        $limit = intval($this->job['limitSeconds'] ?? 0);
        return $limit > 0 ? max(1, (int)ceil($limit - (microtime(true) - $this->startedAt))) : 0;
    }

    /** Remember a database connection id; Stop runs KILL QUERY on it. */
    public function addConnectionId(int $connectionId): void
    {
        if($connectionId > 0 && !in_array($connectionId, $this->job['connectionIds'], true)){
            $this->job['connectionIds'][] = $connectionId;
            $this->write();
        }
    }

    /** Store result content (e.g. preview HTML), read back by GET /jobs/{id}/result. */
    public function writeResult(string $content): void
    {
        $this->store->writeResult($this->id(), $content);
    }

    /** Write the current state with a fresh heartbeat. */
    private function write(): void
    {
        $this->job['heartbeat'] = time();
        $this->store->save($this->job);
        $this->lastWrite = microtime(true);
    }
}
