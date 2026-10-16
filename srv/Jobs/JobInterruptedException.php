<?php
/**
* JobInterruptedException.php - A job was cancelled or ran out of time
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

use RuntimeException;

/** Thrown by JobContext::check(); the reason is "cancelled" or "timeout". */
final class JobInterruptedException extends RuntimeException
{
    private string $reason;

    /** @param string $reason "cancelled" or "timeout". */
    public function __construct(string $reason)
    {
        parent::__construct($reason === 'timeout' ? 'The job took longer than its time limit' : 'The job was cancelled');
        $this->reason = $reason;
    }

    /** "cancelled" or "timeout". */
    public function reason(): string
    {
        return $this->reason;
    }
}
