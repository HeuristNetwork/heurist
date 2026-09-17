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
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     This software may be installed and operated only on servers operated by Heurist Network, or with the prior written permission of Heurist Network. No right is granted to copy, distribute, modify, install or operate this software elsewhere.
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
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
