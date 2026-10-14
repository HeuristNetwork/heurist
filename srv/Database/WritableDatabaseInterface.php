<?php
/**
* WritableDatabaseInterface.php - Database access contract with writes
*
* Extends the read contract with statement execution, inserted ids and
* transactions. Used by the record editing services (srv/Records/Edit);
* read-only services keep depending on DatabaseInterface.
*
* @project     Heurist academic knowledge management system
* @package     Database
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Database;

/** Parameterised writes and transactions. */
interface WritableDatabaseInterface extends DatabaseInterface
{
    /**
     * Execute an INSERT, UPDATE, DELETE or SET statement.
     *
     * @param string $sql Parameterised SQL statement.
     * @param array<int|string,mixed> $parameters Bound values.
     * @return int Number of affected rows.
     */
    public function execute(string $sql, array $parameters = array()): int;

    /** Auto-increment id of the last INSERT on this connection. */
    public function lastInsertId(): int;

    /** Start a transaction. */
    public function beginTransaction(): void;

    /** Commit the open transaction. */
    public function commit(): void;

    /** Roll back the open transaction (no-op when none is open). */
    public function rollback(): void;

    /** True while a transaction is open. */
    public function inTransaction(): bool;
}
