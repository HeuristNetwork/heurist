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
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
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
