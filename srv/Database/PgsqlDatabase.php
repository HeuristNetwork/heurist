<?php
/**
* PgsqlDatabase.php - PostgreSQL adapter for the modern srv layer
*
* Provides the same DatabaseInterface contract as the MySQL adapter, keeping the
* application layer driver-neutral while enabling PostgreSQL support.
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

/** PostgreSQL-backed implementation of DatabaseInterface. */
final class PgsqlDatabase extends AbstractDatabase
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /** Create a PostgreSQL connection for the current Heurist configuration. */
    public static function fromHeuristConfiguration(string $databaseName): self
    {
        $required = array('HEURIST_DBSERVER_NAME', 'HEURIST_DB_PORT', 'ADMIN_DBUSERNAME', 'ADMIN_DBUSERPSWD');
        foreach($required as $constant){
            if(!defined($constant)){
                throw new DatabaseException('Missing database configuration: '.$constant);
            }
        }

        $host = (string)HEURIST_DBSERVER_NAME;
        $port = intval(HEURIST_DB_PORT ?: 5432);
        $user = (string)ADMIN_DBUSERNAME;
        $password = (string)ADMIN_DBUSERPSWD;
        $dsn = 'pgsql:host='.$host.';port='.$port.';dbname='.$databaseName;

        try{
            return new self(new PDO($dsn, $user, $password));
        }catch(PDOException $exception){
            throw new DatabaseException('Unable to connect to the PostgreSQL database', 0, $exception);
        }
    }

}
