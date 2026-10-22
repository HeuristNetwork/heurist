<?php
/**
* LegacyEntityTable.php - Base of the row writes of a legacy system table
*
* Insert, update and delete of the rows of one legacy table (recUploadedFiles,
* usrTags...) while its entity is kept there (plan 20, §2.1, §10.4). Only the
* known columns are written; the rows must look like rows of the legacy code,
* which reads and writes the same table. Reads stay in the repository of the
* table.
*
* @project     Heurist academic knowledge management system
* @package     System\Entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Entity;

use Heurist\Database\WritableDatabaseInterface;

/** Insert, update and delete of the rows of one legacy table. */
abstract class LegacyEntityTable
{
    protected WritableDatabaseInterface $database;

    public function __construct(WritableDatabaseInterface $database)
    {
        $this->database = $database;
    }

    /** Name of the table. */
    abstract protected function table(): string;

    /** Primary key column. */
    abstract protected function idColumn(): string;

    /** Columns that may be written (the primary key is never written). */
    abstract protected function columnNames(): array;

    /**
     * Insert a row (the id is given by the table).
     *
     * @param array<string,mixed> $columns column => value
     * @return int The new id.
     */
    public function insert(array $columns): int
    {
        $columns = $this->columns($columns);
        $this->database->execute('INSERT INTO '.$this->table().' ('.implode(',', array_keys($columns)).') VALUES ('
            .implode(',', array_fill(0, count($columns), '?')).')', array_values($columns));
        return $this->database->lastInsertId();
    }

    /** Update the given columns of a row. */
    public function update(int $id, array $columns): void
    {
        $columns = $this->columns($columns);
        if(empty($columns)){ return; }
        $this->database->execute('UPDATE '.$this->table().' SET '.implode('=?,', array_keys($columns)).'=? WHERE '
            .$this->idColumn().'=?', array_merge(array_values($columns), array($id)));
    }

    /** Delete a row. */
    public function delete(int $id): void
    {
        $this->database->execute('DELETE FROM '.$this->table().' WHERE '.$this->idColumn().'=?', array($id));
    }

    /** Known columns only, without the primary key. */
    private function columns(array $columns): array
    {
        $columns = array_intersect_key($columns, array_flip($this->columnNames()));
        unset($columns[$this->idColumn()]);
        return $columns;
    }
}
