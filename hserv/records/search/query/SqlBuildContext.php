<?php
/**
* SqlBuildContext.php - Mutable state for one parameterized SQL build
*
* @project     Heurist academic knowledge management system
* @package     Records\Search\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

namespace hserv\records\search\query;


/** Keeps bind order, request context, and alias allocation for one SQL statement. */
final class SqlBuildContext implements \ArrayAccess
{
    private $data;

    public function __construct(array $context = array())
    {
        $this->data = array('types'=>'', 'values'=>array(), 'context'=>$context, 'aliasCounter'=>0);
    }

    public function bind($value, string $type): void
    {
        $this->data['types'] .= $type;
        $this->data['values'][] = $value;
    }

    public function nextAlias(string $prefix): string
    {
        $this->data['aliasCounter']++;
        return $prefix.$this->data['aliasCounter'];
    }

    public function types(): string { return $this->data['types']; }
    public function values(): array { return $this->data['values']; }
    public function context(): array { return $this->data['context']; }
    public function offsetExists($offset): bool { return array_key_exists($offset, $this->data); }
    public function offsetGet($offset) { return $this->data[$offset] ?? null; }
    public function offsetSet($offset, $value): void { $this->data[$offset] = $value; }
    public function offsetUnset($offset): void { unset($this->data[$offset]); }
}
