<?php
/**
* SqliteDatabase.php - SQLite adapter for the modern srv layer
*
* The interface is intentionally identical to the MySQL/PostgreSQL adapters so
* new engines can be added without changing application calls.
*
* @project     Heurist academic knowledge management system
* @package     Database
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Database;

use PDO;
use PDOException;

/** SQLite-backed implementation of DatabaseInterface. */
final class SqliteDatabase extends AbstractDatabase
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /** Build a SQLite database connection for the configured database file. */
    public static function fromHeuristConfiguration(string $databaseName): self
    {
        $path = $databaseName;
        if($path === ''){
            throw new DatabaseException('Missing SQLite database path');
        }

        try{
            return new self(new PDO('sqlite:'.$path));
        }catch(PDOException $exception){
            throw new DatabaseException('Unable to connect to the SQLite database', 0, $exception);
        }
    }

}
