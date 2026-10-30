<?php
/**
* RecordEntityRegistry.php - Record-based entities of the API (plan 28 §6, plan 27d)
*
* Names of record types used in URLs, /api/{db}/{entity}/{id}/{action}, with the
* record type, the job type of the action "execute" and the hooks and actions
* (RecordEntityHandler). Records of these types are in the Records table pair
* (plan 28 moves them later; then only the table pair of an entity changes).
* Entities kept in legacy tables (tag, user, group ...) are in
* System/Query/SystemEntitySchemaRegistry.
*
* @project     Heurist academic knowledge management system
* @package     Records\Entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Entity;

/** Entity names, their record types, jobs and handlers. */
final class RecordEntityRegistry
{
    /**
     * Entity name => record type constant and job type of "execute" (null: none).
     * api.php routes these names before it knows the database.
     */
    public const ENTITIES = array(
        'website' => array('rectype' => 'RT_CMS_HOME', 'job' => 'website-build'),
        'page' => array('rectype' => 'RT_CMS_PAGE', 'job' => 'website-build')
    );

    /** @var array<string,int> name => local record type id */
    private array $rectypes;
    /** @var array<string,callable():RecordEntityHandler> */
    private array $factories;
    /** @var array<string,RecordEntityHandler> */
    private array $handlers = array();

    /**
     * @param array<string,int> $rectypes Entity name => local record type id (0: not in this database).
     * @param array<string,callable():RecordEntityHandler> $factories Entity name => handler factory.
     */
    public function __construct(array $rectypes, array $factories = array())
    {
        $this->rectypes = array_map('intval', $rectypes);
        $this->factories = $factories;
    }

    /** Whether a name is a record-based entity. */
    public static function isEntity(string $name): bool
    {
        return isset(self::ENTITIES[$name]);
    }

    /** Local record type id of an entity (0: unknown or not in this database). */
    public function rectype(string $name): int
    {
        return $this->rectypes[$name] ?? 0;
    }

    /** Job type of the action "execute" of an entity, or null. */
    public static function job(string $name): ?string
    {
        return self::ENTITIES[$name]['job'] ?? null;
    }

    /** Handler of an entity (the default one when it has no own). */
    public function handler(string $name): RecordEntityHandler
    {
        if(!isset($this->handlers[$name])){
            $factory = $this->factories[$name] ?? null;
            $this->handlers[$name] = $factory !== null ? $factory() : new class extends RecordEntityHandler {};
        }
        return $this->handlers[$name];
    }

    /** Handler of the entity of a record type, or null when the type is not an entity. */
    public function handlerOf(int $rectype): ?RecordEntityHandler
    {
        if($rectype < 1){ return null; }
        $name = array_search($rectype, $this->rectypes, true);
        return $name === false ? null : $this->handler((string)$name);
    }
}
