<?php
/**
* ReminderRecordMapping.php - usrReminders rows as records of the Reminder record type
*
* Plan 20, §10.3, §10.9: the record of the reminder is the pointer DT_RESOURCE 2-13
* (rem_RecID); the recipient is a user or a group, the pointer 2-1193 (stored in
* rem_ToUserID or rem_ToWorkgroupID by the kind of the id), or e-mail addresses
* DT_EMAIL 2-23 (rem_ToEmail); the message DT_EXTENDED_DESCRIPTION 2-4, the start
* date DT_START_DATE 2-10 and the Frequency 2-1191 (rem_Freq, a term of the
* vocabulary 2-10454: once, daily, weekly, monthly, annually). The owner is the
* user who made it (rem_OwnerUGrpID), the title is the title of the record.
* rem_Nonce is not mapped (the "do not send again" link of the e-mails).
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

use Heurist\System\Entity\EntityRecordMapping;

/** Converts rows of usrReminders to reminder records. */
final class ReminderRecordMapping extends EntityRecordMapping
{
    /** Concept code of the Reminder record type. */
    public const RECTYPE = '2-1111';

    public const RECORD_FIELD = '2-13';
    public const RECIPIENT_FIELD = '2-1193';
    public const EMAIL_FIELD = '2-23';
    public const MESSAGE_FIELD = '2-4';
    public const START_FIELD = '2-10';
    public const FREQUENCY_FIELD = '2-1191';

    /** Field concept code => usrReminders column (the recipient: rem_ToUserID or rem_ToWorkgroupID). */
    public const FIELDS = array(
        '2-13' => array('column' => 'rem_RecID'),           // DT_RESOURCE: the record, required
        '2-1193' => array('column' => 'rem_ToUserID'),      // Recipient: a user or a group
        '2-23' => array('column' => 'rem_ToEmail'),         // DT_EMAIL: e-mail addresses
        '2-4' => array('column' => 'rem_Message'),          // DT_EXTENDED_DESCRIPTION: at most 1000 characters
        '2-10' => array('column' => 'rem_StartDate'),       // DT_START_DATE: the first (or only) date
        '2-1191' => array('column' => 'rem_Freq')           // Frequency: a term of 2-10454
    );

    /** Fields a client may write. */
    public const WRITABLE_FIELDS = array('2-13', '2-1193', '2-23', '2-4', '2-10', '2-1191');

    /** Values of rem_Freq. */
    public const FREQUENCIES = array('once', 'daily', 'weekly', 'monthly', 'annually');

    public const MAX_EMAIL = 255;
    public const MAX_MESSAGE = 1000;

    /** {@inheritdoc} */
    public function rectypeConcept(): string
    {
        return self::RECTYPE;
    }

    /** {@inheritdoc} */
    protected function fieldMappings(): array
    {
        return self::FIELDS;
    }

    /** {@inheritdoc} */
    protected function writableConcepts(): array
    {
        return self::WRITABLE_FIELDS;
    }

    /**
     * Record of a ReminderRepository row.
     *
     * @return array{headers:array<string,?string>,details:array<int,array<int,string>>}
     */
    public function toRecord(array $row): array
    {
        return array(
            'headers' => array(
                'rec_ID' => (string)intval($row['rem_ID']),
                'rec_RecTypeID' => (string)$this->rectypeId(),
                'rec_Title' => (string)($row['rec_Title'] ?? ''),
                'rec_Modified' => isset($row['rem_Modified']) ? (string)$row['rem_Modified'] : null,
                'rec_OwnerUGrpID' => (string)intval($row['rem_OwnerUGrpID'] ?? 0)
            ),
            'details' => $this->detailsOf($row)
        );
    }

    /**
     * rem_Freq of a field value: a term id of the vocabulary, or the label ("weekly").
     * Null when it is neither.
     */
    public function frequencyOf(string $value): ?string
    {
        $value = strtolower(trim($value));
        if(in_array($value, self::FREQUENCIES, true)){ return $value; }
        $fieldId = $this->fieldId(self::FREQUENCY_FIELD);
        if($fieldId < 1 || !ctype_digit($value)){ return null; }
        foreach(self::FREQUENCIES as $frequency){
            if($this->termId($fieldId, $frequency) === $value){ return $frequency; }
        }
        return null;
    }

    /** {@inheritdoc} The recipient: the group or the user; the frequency: its term; no start date: empty. */
    protected function fieldValue(int $fieldId, array $mapping, array $row): ?string
    {
        if($mapping['column'] === 'rem_ToUserID'){
            $recipient = intval($row['rem_ToWorkgroupID'] ?? 0) ?: intval($row['rem_ToUserID'] ?? 0);
            return $recipient > 0 ? (string)$recipient : null;
        }
        $value = parent::fieldValue($fieldId, $mapping, $row);
        if($value === null){ return null; }
        if($mapping['column'] === 'rem_Freq'){ return $this->termId($fieldId, $value) ?? $value; }
        if($mapping['column'] === 'rem_StartDate' && strpos($value, '1970-01-01') === 0){ return null; }
        return $value;
    }
}
