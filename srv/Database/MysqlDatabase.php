<?php
/**
* MysqlDatabase.php - MySQL implementation of modern database access
*
* Executes parameterised read queries for the new read-only records workflow.
* Driver-specific record SQL remains in the query compiler, not in this class.
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

/** MySQL-backed database adapter used by all new record services. */
final class MysqlDatabase extends AbstractDatabase
{
    /** Configure a PDO connection for exception-based parameterised access. */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * Create a MySQL connection using the currently loaded Heurist settings.
     *
     * @param string $databaseName Full physical database name.
     * @throws DatabaseException When configuration is unavailable or connection fails.
     */
    public static function fromHeuristConfiguration(string $databaseName): self
    {
        $required = array('HEURIST_DBSERVER_NAME', 'HEURIST_DB_PORT', 'ADMIN_DBUSERNAME', 'ADMIN_DBUSERPSWD');
        foreach($required as $constant){
            if(!defined($constant)){
                throw new DatabaseException('Missing database configuration: '.$constant);
            }
        }
        $dsn = 'mysql:host='.HEURIST_DBSERVER_NAME
            .';port='.intval(HEURIST_DB_PORT ?: 3306)
            .';dbname='.$databaseName.';charset=utf8mb4';
        try{
            return new self(new PDO($dsn, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD));
        }catch(PDOException $exception){
            throw new DatabaseException('Unable to connect to the Heurist database', 0, $exception);
        }
    }

}
