<?php
/**
* JobStore.php - Background job state in JSON files
*
* One database keeps its jobs in <filestore>/<db>/scratch/jobs/:
*   <id>.json    job state, written by the start request and the running job
*   <id>.cancel  Stop request (a separate file, so the running job never races
*                with the cancel request over the state file)
*   <id>.result  optional result content (e.g. preview HTML)
*   <id>/        optional folder of result files (e.g. an export file)
*
* A queued/running job whose heartbeat is older than LOST_SECONDS is reported as
* "lost" (the PHP process ended without finishing it). Files older than
* KEEP_DAYS are removed.
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

use InvalidArgumentException;
use RuntimeException;

/** File storage of job state. */
final class JobStore
{
    /** A running job must write a heartbeat at least this often. */
    public const LOST_SECONDS = 90;

    /** Job files are removed after this many days. */
    public const KEEP_DAYS = 7;

    /** Statuses of a job that has not finished. */
    public const ACTIVE = array('queued', 'running');

    private string $directory;

    /** @param string $directory Absolute path of the jobs folder (created on demand). */
    public function __construct(string $directory)
    {
        if($directory === ''){
            throw new RuntimeException('Job folder is not defined');
        }
        $this->directory = rtrim($directory, '/\\').'/';
    }

    /**
     * Create a queued job and return its state.
     *
     * @param string $type Job type.
     * @param int $userId Owner of the job.
     * @param string $title Title for job lists.
     * @param array $params Normalized parameters.
     * @param int $limitSeconds Time limit.
     */
    public function create(string $type, int $userId, string $title, array $params, int $limitSeconds): array
    {
        $this->ensureDirectory();
        $now = time();
        $job = array(
            'id' => bin2hex(random_bytes(16)),
            'type' => $type,
            'userId' => $userId,
            'title' => $title,
            'status' => 'queued',
            'progress' => array('done' => 0, 'total' => 0, 'message' => ''),
            'params' => $params,
            'limitSeconds' => $limitSeconds,
            'createdAt' => $now,
            'startedAt' => null,
            'finishedAt' => null,
            'heartbeat' => $now,
            'connectionIds' => array(),
            'result' => null,
            'error' => null
        );
        $this->save($job);
        return $job;
    }

    /** Job state, or null for an unknown id. A dead active job is returned as "lost". */
    public function load(string $id): ?array
    {
        $file = $this->path($id, 'json');
        if(!is_file($file)){ return null; }
        $job = json_decode((string)@file_get_contents($file), true);
        if(!is_array($job)){ return null; }
        if(in_array($job['status'] ?? '', self::ACTIVE, true)
            && time() - intval($job['heartbeat'] ?? 0) > self::LOST_SECONDS){
            $job['status'] = 'lost';
            $job['error'] = 'The job stopped without finishing (server process ended)';
            $job['finishedAt'] = $job['finishedAt'] ?? time();
            $this->save($job);
        }
        return $job;
    }

    /** Write the job state (atomically: temporary file + rename). */
    public function save(array $job): void
    {
        $this->ensureDirectory();
        $file = $this->path((string)$job['id'], 'json');
        $temp = $file.'.'.getmypid().'.tmp';
        $json = json_encode($job, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if($json === false || file_put_contents($temp, $json) === false){
            throw new RuntimeException('Cannot write job state');
        }
        if(!@rename($temp, $file)){
            // Windows cannot replace a file that another process has open: retry once
            usleep(50000);
            if(!@rename($temp, $file)){
                @unlink($temp);
                throw new RuntimeException('Cannot write job state');
            }
        }
    }

    /** Ask a running job to stop. */
    public function requestCancel(string $id): void
    {
        $this->ensureDirectory();
        file_put_contents($this->path($id, 'cancel'), (string)time());
    }

    /** True when Stop was requested. */
    public function isCancelRequested(string $id): bool
    {
        return is_file($this->path($id, 'cancel'));
    }

    /** Store result content (e.g. preview HTML) next to the job. */
    public function writeResult(string $id, string $content): void
    {
        if(file_put_contents($this->path($id, 'result'), $content) === false){
            throw new RuntimeException('Cannot write job result');
        }
    }

    /** Stored result content, or null. */
    public function readResult(string $id): ?string
    {
        $file = $this->path($id, 'result');
        return is_file($file) ? (string)file_get_contents($file) : null;
    }

    /**
     * Folder of the job's result files (created on demand).
     *
     * @return string Absolute path with a trailing slash.
     */
    public function resultDirectory(string $id): string
    {
        $folder = substr($this->path($id, 'json'), 0, -5).'/';
        if(!is_dir($folder) && !@mkdir($folder, 0775, true) && !is_dir($folder)){
            throw new RuntimeException('Cannot create the job result folder');
        }
        return $folder;
    }

    /**
     * Path of a result file in the job's folder, or null when it does not exist.
     *
     * @param string $id Job id.
     * @param string $file File name (no folders).
     */
    public function resultFilePath(string $id, string $file): ?string
    {
        if($file === '' || $file !== basename($file) || $file[0] === '.'){ return null; }
        $path = substr($this->path($id, 'json'), 0, -5).'/'.$file;
        return is_file($path) ? $path : null;
    }

    /**
     * Jobs, newest first.
     *
     * @param int|null $userId Only the jobs of this user; null for all.
     * @return array<int,array>
     */
    public function listJobs(?int $userId = null): array
    {
        if(!is_dir($this->directory)){ return array(); }
        $jobs = array();
        foreach(glob($this->directory.'*.json') ?: array() as $file){
            $job = $this->load(basename($file, '.json'));
            if($job === null || ($userId !== null && intval($job['userId']) !== $userId)){ continue; }
            $jobs[] = $job;
        }
        usort($jobs, static function(array $a, array $b): int {
            return intval($b['createdAt']) <=> intval($a['createdAt']);
        });
        return $jobs;
    }

    /**
     * Number of queued or running jobs.
     *
     * @param int|null $userId Only this user's jobs.
     * @param string|null $type Only this job type.
     */
    public function countActive(?int $userId = null, ?string $type = null, bool $skipCancelled = false): int
    {
        return count($this->activeJobs($userId, $type, $skipCancelled));
    }

    /**
     * Queued and running jobs, newest first.
     *
     * @param int|null $userId Only the jobs of this user.
     * @param string|null $type Only jobs of this type.
     * @param bool $skipCancelled Leave out jobs asked to stop (they end at their next check).
     * @return array<int,array>
     */
    public function activeJobs(?int $userId = null, ?string $type = null, bool $skipCancelled = false): array
    {
        $active = array();
        foreach($this->listJobs($userId) as $job){
            if(!in_array($job['status'], self::ACTIVE, true) || ($type !== null && $job['type'] !== $type)){
                continue;
            }
            if($skipCancelled && $this->isCancelRequested((string)$job['id'])){
                continue;
            }
            $active[] = $job;
        }
        return $active;
    }

    /** Remove a job: state, Stop flag, result content and result files. */
    public function delete(string $id): void
    {
        foreach(array('json', 'cancel', 'result') as $extension){
            $file = $this->path($id, $extension);
            if(is_file($file)){ @unlink($file); }
        }
        $folder = substr($this->path($id, 'json'), 0, -5);
        if(is_dir($folder)){
            foreach(glob($folder.'/*') ?: array() as $inner){
                if(is_file($inner)){ @unlink($inner); }
            }
            @rmdir($folder);
        }
    }

    /**
     * Total size of the result files of a user's jobs of one type (bytes).
     *
     * @param int $userId Owner of the jobs.
     * @param string $type Job type (e.g. export).
     */
    public function resultBytes(int $userId, string $type): int
    {
        $bytes = 0;
        foreach($this->listJobs($userId) as $job){
            if($job['type'] !== $type){ continue; }
            $folder = substr($this->path((string)$job['id'], 'json'), 0, -5);
            foreach(glob($folder.'/*') ?: array() as $file){
                if(is_file($file)){ $bytes += intval(filesize($file)); }
            }
        }
        return $bytes;
    }

    /**
     * Remove finished jobs of a type older than its keep time (e.g. export results
     * after 24 hours), with their result files.
     *
     * @param string $type Job type.
     * @param int $seconds Keep time after the job ended.
     * @return int Removed jobs.
     */
    public function cleanupType(string $type, int $seconds): int
    {
        if(!is_dir($this->directory) || $seconds < 1){ return 0; }
        $removed = 0;
        $limit = time() - $seconds;
        foreach(glob($this->directory.'*.json') ?: array() as $file){
            if(filemtime($file) >= $limit){ continue; }
            $job = json_decode((string)@file_get_contents($file), true);
            if(!is_array($job) || ($job['type'] ?? '') !== $type || in_array($job['status'] ?? '', self::ACTIVE, true)){
                continue;
            }
            $this->delete((string)$job['id']);
            $removed++;
        }
        return $removed;
    }

    /** Remove the files of jobs older than KEEP_DAYS. */
    public function cleanup(): int
    {
        if(!is_dir($this->directory)){ return 0; }
        $removed = 0;
        $limit = time() - self::KEEP_DAYS * 86400;
        foreach(glob($this->directory.'*') ?: array() as $file){
            if(basename($file) !== 'index.html' && is_file($file) && filemtime($file) < $limit && @unlink($file)){
                $removed++;
            }elseif(is_dir($file) && self::isValidId(basename($file)) && filemtime($file) < $limit){
                // result files of an old job (e.g. export)
                foreach(glob($file.'/*') ?: array() as $inner){
                    if(is_file($inner)){ @unlink($inner); }
                }
                if(@rmdir($file)){ $removed++; }
            }
        }
        return $removed;
    }

    /** True for a syntactically valid job id. */
    public static function isValidId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $id) === 1;
    }

    private function path(string $id, string $extension): string
    {
        if(!self::isValidId($id)){
            throw new InvalidArgumentException('Invalid job id');
        }
        return $this->directory.$id.'.'.$extension;
    }

    private function ensureDirectory(): void
    {
        if(!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)){
            throw new RuntimeException('Cannot create the job folder');
        }
        if(!is_file($this->directory.'index.html')){
            @file_put_contents($this->directory.'index.html', '');
        }
    }
}
