<?php
/**
* LegacyBookmarkTable.php - Writes of bookmark rows (usrBookmarks)
*
* Rows must look like rows of the legacy code (DbUsrBookmarks): the old interface
* reads and writes the same table.
*
* @project     Heurist academic knowledge management system
* @package     Records\UserData
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\UserData;

use Heurist\Database\WritableDatabaseInterface;
use Heurist\Records\Store\RecordSchema;
use Heurist\System\Entity\LegacyEntityTable;

/** Insert, update and delete of usrBookmarks rows. */
final class LegacyBookmarkTable extends LegacyEntityTable
{
    private RecordSchema $schema;

    public function __construct(WritableDatabaseInterface $database, ?RecordSchema $schema = null)
    {
        parent::__construct($database);
        $this->schema = $schema ?? new RecordSchema();
    }

    /** {@inheritdoc} */
    protected function table(): string
    {
        return $this->schema->table('bookmarks');
    }

    /** {@inheritdoc} */
    protected function idColumn(): string
    {
        return 'bkm_ID';
    }

    /** {@inheritdoc} */
    protected function columnNames(): array
    {
        return BookmarkRepository::COLUMNS;
    }
}
