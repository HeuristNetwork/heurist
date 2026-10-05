<?php
/**
* JobHandlerInterface.php - One kind of background job
*
* A handler validates the request before the job is created (prepare) and does
* the work later (run). run() must call JobContext::progress() and check() often
* enough for Stop and the time limit to work.
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

/** Validates and runs one job type (e.g. "report-generate"). */
interface JobHandlerInterface
{
    /** Job type name used in POST /jobs {"type": ...}. */
    public function type(): string;

    /** Time limit of one job in seconds. */
    public function limitSeconds(): int;

    /**
     * True: a new job of this type stops the user's running one instead of being
     * refused (a test run whose result only the page that started it uses).
     * False: one running job of this type per user; a second start is refused.
     */
    public function replacesPrevious(): bool;

    /**
     * Validate the request and check the rights of the current user.
     * Throws InvalidArgumentException (400), OutOfBoundsException (404) or
     * DomainException (403).
     *
     * @param array $params Request parameters.
     * @return array{title:string,params:array} Title shown in job lists and the
     *         normalized parameters stored with the job and passed to run().
     */
    public function prepare(array $params): array;

    /**
     * Do the work.
     *
     * @param array $params Normalized parameters from prepare().
     * @param JobContext $context Progress, Stop/time limit checks, result file.
     * @return array Result stored with the job (e.g. file name and URL).
     */
    public function run(array $params, JobContext $context): array;
}
