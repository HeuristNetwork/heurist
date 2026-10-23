<?php
/**
* LegacyFileTable.php - Writes of rows of recUploadedFiles
*
* The /files domain writes registered files here while they are kept in the
* legacy table (plan 20, §8.3). Rows must look like rows of the legacy code
* (DbRecUploadedFiles): the old interface reads and writes the same table.
* Reads stay in FileRepository. The writes are the base LegacyEntityTable
* (plan 20, §10.4).
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

use Heurist\System\Entity\LegacyEntityTable;

/** Insert, update and delete of recUploadedFiles rows. */
final class LegacyFileTable extends LegacyEntityTable
{
    /** {@inheritdoc} */
    protected function table(): string
    {
        return 'recUploadedFiles';
    }

    /** {@inheritdoc} */
    protected function idColumn(): string
    {
        return 'ulf_ID';
    }

    /** {@inheritdoc} FileRepository::COLUMNS. */
    protected function columnNames(): array
    {
        return FileRepository::COLUMNS;
    }
}
