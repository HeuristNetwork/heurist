<?php
/**
* LegacyTagTable.php - Writes of tag rows (usrTags)
*
* Rows must look like rows of the legacy code (DbUsrTags): the old interface
* reads and writes the same table. The record links are TagLinkTable.
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

/** Insert, update and delete of usrTags rows. */
final class LegacyTagTable extends LegacyEntityTable
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
        return $this->schema->table('tags');
    }

    /** {@inheritdoc} */
    protected function idColumn(): string
    {
        return 'tag_ID';
    }

    /** {@inheritdoc} */
    protected function columnNames(): array
    {
        return TagRepository::COLUMNS;
    }
}
