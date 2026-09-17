<?php
/**
* DatabaseInterface.php - Read-only database access contract
*
* Defines the database operations required by the modern record query,
* retrieval, presentation, aggregation and export workflow.
*
* @project     Heurist academic knowledge management system
* @package     Database
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     This software may be installed and operated only on servers operated by Heurist Network, or with the prior written permission of Heurist Network. No right is granted to copy, distribute, modify, install or operate this software elsewhere.
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Database;

/**
 * Provides parameterised database reads without exposing PDO or mysqli to
 * application services.
 */
interface DatabaseInterface
{
    /**
     * Return all rows using numeric column indexes.
     *
     * @param string $sql Parameterised SQL statement.
     * @param array<int|string,mixed> $parameters Bound values.
     * @return array<int,array<int,mixed>>
     */
    public function fetchRows(string $sql, array $parameters = array()): array;

    /**
     * Return all rows using column names.
     *
     * @param string $sql Parameterised SQL statement.
     * @param array<int|string,mixed> $parameters Bound values.
     * @return array<int,array<string,mixed>>
     */
    public function fetchAll(string $sql, array $parameters = array()): array;

    /**
     * Return a single column from all result rows.
     *
     * @param string $sql Parameterised SQL statement.
     * @param array<int|string,mixed> $parameters Bound values.
     * @return array<int,mixed>
     */
    public function fetchColumn(string $sql, array $parameters = array()): array;

    /**
     * Return the first column of the first row or the supplied default.
     *
     * @param string $sql Parameterised SQL statement.
     * @param array<int|string,mixed> $parameters Bound values.
     * @param mixed $default Value returned for an empty result.
     * @return mixed
     */
    public function fetchValue(string $sql, array $parameters = array(), $default = null);

    /** Return the active PDO driver name. */
    public function getDriver(): string;
}
