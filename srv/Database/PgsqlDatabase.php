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
