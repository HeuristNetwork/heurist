<?php
/**
* ErrorReporter.php - Safe modern workflow error logging
*
* Logs unexpected failures with database and user context while controllers
* return a non-sensitive public error message.
*
* @project     Heurist academic knowledge management system
* @package     Runtime
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Runtime;

use Throwable;

/** Records unexpected server-side exceptions through PHP's configured logger. */
final class ErrorReporter
{
    /** Log an exception with the current request context. */
    public function report(Throwable $exception, RuntimeContext $runtime): void
    {
        error_log(sprintf(
            '[modern-records] db=%s user=%d %s: %s',
            $runtime->databaseName,
            $runtime->userId,
            get_class($exception),
            $exception->getMessage()
        ));
    }
}
