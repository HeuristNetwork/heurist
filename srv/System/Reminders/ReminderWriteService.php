<?php
/**
* ReminderWriteService.php - Add, change, delete and send reminders (/sys/reminder)
*
* The write side of reminders in the record format (plan 20, §10.9), with the
* rules of the legacy code (DbUsrReminders):
* - the owner is the user who adds it; the owner or the database owner changes
*   and deletes it;
* - the record must be visible to the user;
* - exactly one recipient: a user or a group (Recipient 2-1193), or e-mail
*   addresses (2-23, comma separated);
* - the message has at most 1000 characters; the start date is a date
*   (YYYY-MM-DD, today when not given); the frequency defaults to once;
* - send: the reminder of the request is e-mailed now, without saving (legacy
*   "Send immediately"), through the legacy mailer (a closure of ServiceFactory).
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

use Heurist\Database\WritableDatabaseInterface;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Edit\RecordInput;
use Heurist\Records\Store\RecordAccess;
use Heurist\Records\Store\RecordRepository;
use Heurist\Records\UserData\LegacyReminderTable;
use Heurist\Records\UserData\ReminderRepository;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Entity\EntityTransaction;
use Heurist\System\Entity\EntityWriteService;
use Heurist\Users\UserGroupRepository;

/** Reminder writes. */
final class ReminderWriteService extends EntityWriteService
{
    private WritableDatabaseInterface $database;
    private ReminderRepository $reminders;
    private LegacyReminderTable $table;
    private ReminderRecordMapping $mapping;
    private RecordRepository $records;

    /** @var (callable(array):void)|null Sends a reminder (legacy columns); throws on failure. */
    private $sender;

    public function __construct(
        WritableDatabaseInterface $database,
        RuntimeContext $runtime,
        ReminderRecordMapping $mapping,
        ?callable $sender = null,
        ?RecordRepository $records = null
    ) {
        parent::__construct($runtime);
        $this->database = $database;
        $this->mapping = $mapping;
        $this->sender = $sender;
        $this->reminders = new ReminderRepository($database);
        $this->table = new LegacyReminderTable($database, $this->reminders->schema());
        $this->records = $records ?? RecordRepository::shared($database);
    }

    /** {@inheritdoc} */
    protected function noun(): string
    {
        return 'reminder';
    }

    /** {@inheritdoc} */
    protected function route(): string
    {
        return 'sys/reminder';
    }

    /**
     * Add (POST, id 0), replace (PUT) or change (PATCH) a reminder.
     *
     * @param bool $force Write over a newer rec_Modified.
     * @param bool $dryRun Only the checks (POST /sys/reminder/{id}/validate).
     * @return array{reminderId:int,created:bool,issues:array}
     * @throws RecordEditException
     */
    public function save(string $method, int $reminderId, ?array $body, bool $force = false, bool $dryRun = false): array
    {
        $this->assertAccount('Log in to set reminders');
        $input = $this->input($this->unwrap($body), $reminderId);
        $this->assertMethod($method, $reminderId);
        $row = null;
        if($reminderId > 0){
            $row = $this->existing($reminderId);
            $this->assertNotChanged($input, (string)$row['rem_Modified'], $force);
        }
        $columns = $this->columns($this->mapping->writableColumns($input->details()), $row, $method === 'PUT');
        $columns['rem_Modified'] = date('Y-m-d H:i:s');
        if($dryRun){ return array('reminderId' => $reminderId, 'created' => false, 'issues' => array()); }

        if($reminderId < 1){
            $reminderId = EntityTransaction::run($this->database, function() use ($columns){
                // the nonce of the "do not send again" link of the e-mails (as legacy)
                return $this->table->insert($columns + array('rem_OwnerUGrpID' => $this->runtime->userId,
                    'rem_Nonce' => dechex(random_int(1, 99))));
            });
            return array('reminderId' => $reminderId, 'created' => true, 'issues' => array());
        }
        EntityTransaction::run($this->database, function() use ($reminderId, $columns){ $this->table->update($reminderId, $columns); });
        return array('reminderId' => $reminderId, 'created' => false, 'issues' => array());
    }

    /**
     * Delete a reminder.
     *
     * @throws RecordEditException 404
     */
    public function delete(int $reminderId): array
    {
        $this->assertAccount('Log in to set reminders');
        $this->existing($reminderId);
        EntityTransaction::run($this->database, function() use ($reminderId){ $this->table->delete($reminderId); });
        return array('deleted' => $reminderId);
    }

    /**
     * Send the reminder of the request now, without saving it (as the legacy "Send immediately").
     * The body is a reminder record (as for POST /sys/reminder/0).
     *
     * @return array{sent:bool,record:int}
     * @throws RecordEditException 400, 404, 405 (no mailer), 502 (the mail was not sent)
     */
    public function send(?array $body): array
    {
        $this->assertAccount('Log in to send reminders');
        if($this->sender === null){ throw new RecordEditException(405, 'method_not_allowed', 'Reminders cannot be sent here'); }
        $input = RecordInput::fromBody($this->unwrap($body));
        $columns = $this->columns($this->mapping->writableColumns($input->details()), null, false);
        try{
            call_user_func($this->sender, $columns + array('rem_OwnerUGrpID' => $this->runtime->userId));
        }catch(\RuntimeException $e){
            throw new RecordEditException(502, 'not_sent', $e->getMessage() !== '' ? $e->getMessage() : 'The reminder was not sent');
        }
        return array('sent' => true, 'record' => intval($columns['rem_RecID']));
    }

    /**
     * Checked columns of a reminder: the given ones, over the stored row (PUT: not given = empty).
     *
     * @param array<string,?string> $given column => value of the request
     * @throws RecordEditException 400, 404
     */
    private function columns(array $given, ?array $row, bool $replace): array
    {
        $value = static function(string $column) use ($given, $row, $replace): string {
            if(array_key_exists($column, $given)){ return trim((string)$given[$column]); }
            return $replace || $row === null ? '' : trim((string)($row[$column] ?? ''));
        };
        $columns = array();

        $recordId = intval($value('rem_RecID'));
        if($recordId < 1){ throw RecordEditException::invalid('The record of the reminder (field 2-13) is required'); }
        if($row === null || $recordId !== intval($row['rem_RecID'])){
            if(empty($this->records->visibleIds(array($recordId), RecordAccess::fromRuntime($this->runtime)))){
                throw RecordEditException::notFound('Record '.$recordId.' not found');
            }
        }
        $columns['rem_RecID'] = $recordId;

        // one recipient: a user or a group (the stored row has it in one of two columns), or e-mails
        $recipient = array_key_exists('rem_ToUserID', $given) ? intval($given['rem_ToUserID'])
            : ($replace || $row === null ? 0 : (intval($row['rem_ToWorkgroupID'] ?? 0) ?: intval($row['rem_ToUserID'] ?? 0)));
        $email = $value('rem_ToEmail');
        if(($recipient > 0) === ($email !== '')){
            throw RecordEditException::invalid('A reminder has one recipient: a user or a group, or e-mail addresses');
        }
        $columns['rem_ToUserID'] = null;
        $columns['rem_ToWorkgroupID'] = null;
        $columns['rem_ToEmail'] = null;
        if($recipient > 0){
            $type = UserGroupRepository::shared($this->database)->types(array($recipient))[$recipient] ?? null;
            if($type === null){ throw RecordEditException::notFound('User or group '.$recipient.' not found'); }
            $columns[$type === 'user' ? 'rem_ToUserID' : 'rem_ToWorkgroupID'] = $recipient;
        }else{
            $columns['rem_ToEmail'] = $this->emails($email);
        }

        $message = $value('rem_Message');
        if(mb_strlen($message) > ReminderRecordMapping::MAX_MESSAGE){
            throw RecordEditException::invalid('The message has at most '.ReminderRecordMapping::MAX_MESSAGE.' characters');
        }
        $columns['rem_Message'] = $message === '' ? null : $message;

        $start = $value('rem_StartDate');
        if($start === ''){ $start = date('Y-m-d'); }
        if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $start, $date) || !checkdate(intval($date[2]), intval($date[3]), intval($date[1]))){
            throw RecordEditException::invalid('The start date is a date as YYYY-MM-DD');
        }
        $columns['rem_StartDate'] = $date[0];

        $frequency = $value('rem_Freq');
        $columns['rem_Freq'] = $frequency === '' ? 'once' : $this->mapping->frequencyOf($frequency);
        if($columns['rem_Freq'] === null){
            throw RecordEditException::invalid('The frequency is one of '.implode(', ', ReminderRecordMapping::FREQUENCIES));
        }
        return $columns;
    }

    /** @throws RecordEditException 400 */
    private function emails(string $text): string
    {
        $emails = array_values(array_filter(array_map('trim', preg_split('/[,;]/', $text))));
        foreach($emails as $email){
            if(filter_var($email, FILTER_VALIDATE_EMAIL) === false){ throw RecordEditException::invalid('Not an e-mail address: '.$email); }
        }
        $text = implode(', ', $emails);
        if(strlen($text) > ReminderRecordMapping::MAX_EMAIL){
            throw RecordEditException::invalid('The e-mail addresses have at most '.ReminderRecordMapping::MAX_EMAIL.' characters');
        }
        return $text;
    }

    /** A reminder the user may change: their own; the database owner all. */
    private function existing(int $reminderId): array
    {
        $row = $this->reminders->row($reminderId);
        if($row === null || (!$this->runtime->isDbOwner && intval($row['rem_OwnerUGrpID']) !== $this->runtime->userId)){
            throw RecordEditException::notFound('Reminder '.$reminderId.' not found');
        }
        return $row;
    }
}
