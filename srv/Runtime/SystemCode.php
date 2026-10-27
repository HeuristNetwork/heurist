<?php
/**
* SystemCode.php - System record type, field and term ids by their constant names
*
* A thin wrapper of DefinitionLookup::constant() (the names are in
* Definitions/SystemConstants) for the services that get their ids injected.
* The legacy global constants are not read (2026-10-27).
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

use Heurist\Definitions\DefinitionLookup;

/** Resolves system constants of the current database. */
final class SystemCode
{
    private ?DefinitionLookup $definitions;
    /** @var array<string,int> */
    private array $overrides;

    /**
     * @param DefinitionLookup|null $definitions Definitions of the database (null: only `$overrides`).
     * @param array<string,int> $overrides Explicit name => local id (tests).
     */
    public function __construct(?DefinitionLookup $definitions = null, array $overrides = array())
    {
        $this->definitions = $definitions;
        $this->overrides = array_map('intval', $overrides);
    }

    /** Local id of a constant such as RT_QUERY_SOURCE (0 when unknown or missing in the database). */
    public function id(string $name): int
    {
        if(array_key_exists($name, $this->overrides)){ return $this->overrides[$name]; }
        return $this->definitions === null ? 0 : $this->definitions->constant($name);
    }
}
