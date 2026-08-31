<?php
/**
* CompiledQuery.php - Parameterised SQL query value object
*
* Stores SQL, ordered values and normalized source query produced by the query
* compiler. Legacy type metadata is retained temporarily for compatibility.
*
* @project     Heurist academic knowledge management system
* @package     Records\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\Records\Query;

/** Immutable output of the record query compiler. */
final class CompiledQuery
{
    public string $sql;
    public string $types;
    public array $values;
    public array $query;

    /** Initialise compiled SQL and its ordered bound values. */
    public function __construct(string $sql, string $types, array $values, array $query)
    {
        $this->sql=$sql; $this->types=$types; $this->values=array_values($values); $this->query=$query;
    }

    /** Return diagnostic query data without executing it. */
    public function toArray(): array
    {
        return array('sql'=>$this->sql, 'types'=>$this->types, 'values'=>$this->values, 'query'=>$this->query);
    }
}
