<?php
/**
* SystemCode.php - System record and detail type code resolver
*
* Isolates temporary RT_* and DT_* constant access at the migration boundary so
* presentation services receive stable IDs without depending on global state.
*
* @project     Heurist academic knowledge management system
* @package     Runtime
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Runtime;

/** Resolves required system codes from an explicit map or temporary constants. */
final class SystemCode
{
    /** @var array<string,int> */
    private array $codes;

    /** @param array<string,int> $codes Explicit code-to-local-ID mappings. */
    public function __construct(array $codes = array())
    {
        $this->codes = array_map('intval', $codes);
    }

    /** Return a local ID, temporarily falling back to an initialized constant. */
    public function id(string $name): int
    {
        if(array_key_exists($name, $this->codes)){ return $this->codes[$name]; }
        return defined($name) ? intval(constant($name)) : 0;
    }
}
