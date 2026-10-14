<?php
/**
* DatabaseSettings.php - Settings of the database (sysIdentification)
*
* The one reader of sysIdentification for srv code (plan 15): registered id,
* database version and the other database settings. The row is read once per
* connection; DatabaseSettings::shared() gives the instance of a connection.
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

use Heurist\Database\DatabaseInterface;
use Throwable;
use WeakMap;

/** Database settings from sysIdentification. */
final class DatabaseSettings
{
    /** @var WeakMap<DatabaseInterface,DatabaseSettings>|null */
    private static ?WeakMap $instances = null;

    private DatabaseInterface $database;
    /** @var array<string,mixed>|null */
    private ?array $row = null;

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /** The settings of a connection (one instance and one read per connection). */
    public static function shared(DatabaseInterface $database): self
    {
        if(self::$instances === null){ self::$instances = new WeakMap(); }
        if(!isset(self::$instances[$database])){
            self::$instances[$database] = new self($database);
        }
        return self::$instances[$database];
    }

    /** Registered id of the database (0 when not registered). */
    public function registeredId(): int
    {
        return max(0, intval($this->value('sys_dbRegisteredID', 0)));
    }

    /** Database version "major.sub.subsub" (e.g. "1.3.14"). */
    public function version(): string
    {
        return intval($this->value('sys_dbVersion', 0)).'.'.intval($this->value('sys_dbSubVersion', 0))
            .'.'.intval($this->value('sys_dbSubSubVersion', 0));
    }

    /** Whether the database version is the given one or later. */
    public function isVersionAtLeast(string $version): bool
    {
        return version_compare($this->version(), $version, '>=');
    }

    /** sys_SetPublicToPendingOnEdit: a changed public record becomes pending. */
    public function publicToPendingOnEdit(): bool
    {
        return intval($this->value('sys_SetPublicToPendingOnEdit', 0)) === 1;
    }

    /**
     * A column of sysIdentification.
     *
     * @param string $name Column name (sys_...).
     * @param mixed $default Value when the column does not exist or is null.
     * @return mixed
     */
    public function value(string $name, $default = null)
    {
        if($this->row === null){
            try{
                $rows = $this->database->fetchAll('SELECT * FROM sysIdentification LIMIT 1');
                $this->row = $rows[0] ?? array();
            }catch(Throwable $exception){
                $this->row = array();
            }
        }
        return array_key_exists($name, $this->row) && $this->row[$name] !== null ? $this->row[$name] : $default;
    }
}
