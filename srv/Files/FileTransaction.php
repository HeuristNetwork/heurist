<?php
/**
* FileTransaction.php - One database transaction around a file write
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

/** Runs work in a transaction (joins one already open). */
final class FileTransaction
{
    /** @return mixed The result of the work. */
    public static function run(WritableDatabaseInterface $database, callable $work)
    {
        if($database->inTransaction()){ return $work(); }
        $database->beginTransaction();
        try{
            $result = $work();
            $database->commit();
            return $result;
        }catch(\Throwable $error){
            if($database->inTransaction()){ $database->rollback(); }
            throw $error;
        }
    }
}
