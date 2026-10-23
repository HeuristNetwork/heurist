<?php
/**
* ReminderRepository.php - Reads of reminder rows (usrReminders)
*
* A reminder sends a message about a record to a user, a group or e-mail
* addresses, once or repeatedly from a start date (plan 20, §10.9). The rows come
* with the title of the record.
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

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Store\RecordSchema;

/** Reminder rows. */
final class ReminderRepository
{
    /** Columns of a reminder row (the legacy table). */
    public const COLUMNS = array('rem_ID', 'rem_RecID', 'rem_OwnerUGrpID', 'rem_ToWorkgroupID', 'rem_ToUserID', 'rem_ToEmail',
        'rem_Message', 'rem_StartDate', 'rem_Freq', 'rem_Nonce', 'rem_Modified');

    private const BATCH_SIZE = 500;

    private DatabaseInterface $database;
    private RecordSchema $schema;

    public function __construct(DatabaseInterface $database, ?RecordSchema $schema = null)
    {
        $this->database = $database;
        $this->schema = $schema ?? new RecordSchema();
    }

    /** The table names (LegacyReminderTable writes the same table). */
    public function schema(): RecordSchema
    {
        return $this->schema;
    }

    /**
     * Reminder rows with the title of the record (rec_Title).
     *
     * @return array<int,array<string,mixed>> reminder id => row
     */
    public function rows(array $ids): array
    {
        $rows = array();
        foreach(array_chunk(TagRepository::ids($ids), self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchAll('SELECT m.'.implode(',m.', self::COLUMNS).',r.rec_Title FROM '
                .$this->schema->table('reminders').' m LEFT JOIN '.$this->schema->table('records')
                .' r ON r.rec_ID=m.rem_RecID WHERE m.rem_ID IN ('.implode(',', array_fill(0, count($chunk), '?')).')', $chunk) as $row){
                $rows[intval($row['rem_ID'])] = $row;
            }
        }
        return $rows;
    }

    /** One reminder row, or null. */
    public function row(int $id): ?array
    {
        return $this->rows(array($id))[$id] ?? null;
    }
}
