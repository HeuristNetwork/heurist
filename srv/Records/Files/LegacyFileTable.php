<?php
/**
* LegacyFileTable.php - Writes of rows of recUploadedFiles
*
* The /files domain writes registered files here while they are kept in the
* legacy table (plan 20, §8.3). Rows must look like rows of the legacy code
* (DbRecUploadedFiles): the old interface reads and writes the same table.
* Reads stay in FileRepository.
*
* @project     Heurist academic knowledge management system
* @package     Records\Files
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Files;

use Heurist\Database\WritableDatabaseInterface;

/** Insert, update and delete of recUploadedFiles rows. */
final class LegacyFileTable
{
    private WritableDatabaseInterface $database;

    public function __construct(WritableDatabaseInterface $database)
    {
        $this->database = $database;
    }

    /**
     * Insert a row (ulf_ID is given by the table).
     *
     * @param array<string,mixed> $columns FileRepository::COLUMNS => value
     * @return int ulf_ID
     */
    public function insert(array $columns): int
    {
        $columns = $this->columns($columns);
        unset($columns['ulf_ID']);
        $this->database->execute('INSERT INTO recUploadedFiles ('.implode(',', array_keys($columns)).') VALUES ('
            .implode(',', array_fill(0, count($columns), '?')).')', array_values($columns));
        return $this->database->lastInsertId();
    }

    /** Update the given columns of a row. */
    public function update(int $fileId, array $columns): void
    {
        $columns = $this->columns($columns);
        unset($columns['ulf_ID']);
        if(empty($columns)){ return; }
        $this->database->execute('UPDATE recUploadedFiles SET '.implode('=?,', array_keys($columns)).'=? WHERE ulf_ID=?',
            array_merge(array_values($columns), array($fileId)));
    }

    /** Delete a row. */
    public function delete(int $fileId): void
    {
        $this->database->execute('DELETE FROM recUploadedFiles WHERE ulf_ID=?', array($fileId));
    }

    /** Known columns only (FileRepository::COLUMNS). */
    private function columns(array $columns): array
    {
        return array_intersect_key($columns, array_flip(FileRepository::COLUMNS));
    }
}
