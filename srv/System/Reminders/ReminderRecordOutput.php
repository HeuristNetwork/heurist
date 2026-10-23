<?php
/**
* ReminderRecordOutput.php - Reminders of /sys in the record format
*
* Plan 20, §10.9: the reminders of the current user (the database owner: all; the
* /sys query gives only those) as EntityRecordOutput gives them. The keywords of the
* legacy entity are accepted in `fields` too (record, recipient, email, message,
* startdate, frequency).
*
* @project     Heurist academic knowledge management system
* @package     System\Reminders
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Reminders;

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\UserData\ReminderRepository;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Entity\EntityRecordOutput;

/** Reminder records of the /sys domain. */
final class ReminderRecordOutput extends EntityRecordOutput
{
    /** Headers of a reminder record (in this order). */
    public const HEADERS = array('rec_ID', 'rec_RecTypeID', 'rec_Title', 'rec_Modified', 'rec_OwnerUGrpID');

    private ReminderRepository $reminders;
    private ReminderRecordMapping $mapping;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime)
    {
        parent::__construct($database, $runtime);
        $this->reminders = new ReminderRepository($database);
        $this->mapping = new ReminderRecordMapping(DefinitionLookup::shared($database));
    }

    /** {@inheritdoc} */
    public function mapping(): ReminderRecordMapping
    {
        return $this->mapping;
    }

    /** {@inheritdoc} */
    protected function headers(): array
    {
        return self::HEADERS;
    }

    /** {@inheritdoc} The reminders of the current user; all for the database owner. */
    protected function rows(array $ids): array
    {
        $rows = $this->reminders->rows($ids);
        if($this->runtime->isDbOwner){ return $rows; }
        $userId = $this->runtime->userId;
        return array_filter($rows, static function($row) use ($userId){
            return $userId > 0 && intval($row['rem_OwnerUGrpID']) === $userId;
        });
    }

    /** {@inheritdoc} */
    protected function toRecord(array $row): array
    {
        return $this->mapping->toRecord($row);
    }

    /** {@inheritdoc} */
    protected function ownerColumn(): string
    {
        return 'rem_OwnerUGrpID';
    }

    /** {@inheritdoc} */
    protected function keywords(): array
    {
        return array(
            'record' => ReminderRecordMapping::RECORD_FIELD,
            'recipient' => ReminderRecordMapping::RECIPIENT_FIELD,
            'email' => ReminderRecordMapping::EMAIL_FIELD,
            'message' => ReminderRecordMapping::MESSAGE_FIELD,
            'startdate' => ReminderRecordMapping::START_FIELD,
            'frequency' => ReminderRecordMapping::FREQUENCY_FIELD
        );
    }
}
