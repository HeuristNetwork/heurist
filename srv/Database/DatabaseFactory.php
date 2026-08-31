<?php
/**
* DatabaseFactory.php - Database implementation factory for the modern srv layer
*
* Resolves the configured driver without binding the modern workflow to MySQL.
* The class intentionally keeps the database-specific configuration in one place
* so PostgreSQL, SQLite and future engines can be added via a new adapter.
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

/**
 * Creates the appropriate database adapter for the current configuration.
 *
 * The supported engine is determined from a driver constant, with MySQL kept as
 * the default for backwards compatibility while the interface remains neutral.
 */
final class DatabaseFactory
{
    /**
     * Build a database adapter from the configured Heurist runtime.
     *
     * @param string $databaseName Full physical database name.
     * @throws DatabaseException When the required configuration is missing or
     *         the selected driver cannot initialise.
     */
    public static function fromHeuristConfiguration(string $databaseName): DatabaseInterface
    {
        $driver = self::driverName();
        if($driver === 'pgsql' || $driver === 'postgresql'){
            return PgsqlDatabase::fromHeuristConfiguration($databaseName);
        }
        if($driver === 'sqlite' || $driver === 'sqlite3'){
            return SqliteDatabase::fromHeuristConfiguration($databaseName);
        }
        if($driver === 'mysql' || $driver === 'mariadb' || $driver === ''){
            return MysqlDatabase::fromHeuristConfiguration($databaseName);
        }
        throw new DatabaseException('Unsupported database driver: '.$driver);
    }

    /**
     * Read the Heurist DB driver from a configuration source.
     *
     * Supports the legacy MySQL default and a future explicit setting.
     */
    public static function driverName(): string
    {
        if(defined('HEURIST_DB_DRIVER') && is_string(HEURIST_DB_DRIVER) && HEURIST_DB_DRIVER !== ''){
            return strtolower(HEURIST_DB_DRIVER);
        }
        if(defined('HEURIST_DB_ENGINE') && is_string(HEURIST_DB_ENGINE) && HEURIST_DB_ENGINE !== ''){
            return strtolower(HEURIST_DB_ENGINE);
        }
        return 'mysql';
    }
}
