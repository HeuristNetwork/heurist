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
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     This software may be installed and operated only on servers operated by Heurist Network, or with the prior written permission of Heurist Network. No right is granted to copy, distribute, modify, install or operate this software elsewhere.
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
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
