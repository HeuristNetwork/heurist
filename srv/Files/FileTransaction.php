<?php
/**
* FileTransaction.php - One database transaction around a file write
*
* Kept for its callers; the work is done by System\Entity\EntityTransaction,
* shared with the /sys writes (plan 20, §10.4).
*
* @project     Heurist academic knowledge management system
* @package     Files
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Files;

use Heurist\Database\WritableDatabaseInterface;
use Heurist\System\Entity\EntityTransaction;

/** Runs work in a transaction (joins one already open). */
final class FileTransaction
{
    /** @return mixed The result of the work. */
    public static function run(WritableDatabaseInterface $database, callable $work)
    {
        return EntityTransaction::run($database, $work);
    }
}
