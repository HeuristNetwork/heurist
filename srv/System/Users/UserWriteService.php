<?php
/**
* UserWriteService.php - User writes of /sys (plan 20, Phase 4d)
*
*   POST /sys/user/0, PUT / PATCH / DELETE /sys/user/{id}, POST /sys/user/{id}/validate
*
* The rules of legacy DbSysUsers:
* - an administrator adds users and changes any user; a user changes only their own account;
*   only the database owner (user 2) changes user 2;
* - login name and e-mail are required and unique; the e-mail and the ORCID iD are checked;
* - the password is written only, hashed as legacy (hash_it); an empty password leaves it;
* - the account status is set by administrators only; a new user is enabled;
* - an approval (status n -> y of a user who never logged in) and a new user send the
*   legacy e-mails;
* - delete: legacy DbSysUsers::delete (never user 2, nor a user who owns records or is the
*   last admin of a group; it also removes the user's temporary records, links, saved
*   filters and tags).
* Self-registration, import from another database and transfer of ownership stay legacy.
* The password hashing, the e-mails and the delete are legacy code, given as closures.
*
* @project     Heurist academic knowledge management system
* @package     System\Users
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Users;

use Heurist\Database\WritableDatabaseInterface;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Entity\EntityTransaction;
use Heurist\System\Entity\EntityWriteService;
use Heurist\Users\LegacyUserGroupTable;
use Heurist\Users\UserGroupRepository;

/** User writes. */
final class UserWriteService extends EntityWriteService
{
    /** Column => largest length (sysUGrps). */
    private const LENGTHS = array(
        'ugr_Name' => 63, 'ugr_eMail' => 100, 'ugr_FirstName' => 40, 'ugr_LastName' => 63, 'ugr_Department' => 120,
        'ugr_Organisation' => 120, 'ugr_City' => 63, 'ugr_State' => 40, 'ugr_Postcode' => 20, 'ugr_Interests' => 255,
        'ugr_URLs' => 2000
    );

    private WritableDatabaseInterface $database;
    private UserRecordMapping $mapping;
    private LegacyUserGroupTable $table;
    private UserGroupRepository $users;

    /** @var array{hash?:callable,delete?:callable,notify?:callable} Legacy code: hash_it, delete, e-mails. */
    private array $legacy;

    /**
     * @param array{hash?:callable(string):string,delete?:callable(string,int):void,notify?:callable(int,?string,bool):void} $legacy
     *        `hash`: the password hash; `delete`: legacy delete of a user or group (throws on refusal);
     *        `notify`: the e-mail of a new or approved user (user id, plain password, approval).
     */
    public function __construct(WritableDatabaseInterface $database, RuntimeContext $runtime, UserRecordMapping $mapping,
        array $legacy = array())
    {
        parent::__construct($runtime);
        $this->database = $database;
        $this->mapping = $mapping;
        $this->legacy = $legacy;
        $this->table = new LegacyUserGroupTable($database);
        $this->users = UserGroupRepository::shared($database);
    }

    /** {@inheritdoc} */
    protected function noun(): string
    {
        return 'user';
    }

    /** {@inheritdoc} */
    protected function route(): string
    {
        return 'sys/user';
    }

    /**
     * Add (POST, id 0), replace (PUT) or change (PATCH) a user.
     *
     * @return array{userId:int,created:bool,issues:array}
     * @throws RecordEditException
     */
    public function save(string $method, int $userId, ?array $body, bool $force = false, bool $dryRun = false): array
    {
        $this->assertAccount('Log in to change user accounts');
        $input = $this->input($this->unwrap($body), $userId);
        $this->assertMethod($method, $userId);
        $admin = $this->isAdmin();
        $row = null;
        if($userId > 0){
            $row = $this->existing($userId);
            if(!$admin && $userId !== $this->runtime->userId){
                throw RecordEditException::denied('You are not an administrator and can not change another user');
            }
            if($userId === 2 && $this->runtime->userId !== 2){
                throw RecordEditException::denied('Only the database owner changes the database owner\'s account');
            }
            $this->assertNotChanged($input, (string)$row['ugr_Modified'], $force);
        }elseif(!$admin){
            throw RecordEditException::denied('Only administrators add users');
        }

        $given = $this->mapping->columnsOfRequest($input->details());
        $password = trim((string)($given['ugr_Password'] ?? ''));
        unset($given['ugr_Password']);
        $columns = $this->columns($given, $row, $method === 'PUT', $admin);
        if($row === null && $password === ''){ throw RecordEditException::invalid('A new user needs a password'); }
        if($password !== ''){
            if(!isset($this->legacy['hash'])){ throw new RecordEditException(405, 'method_not_allowed', 'Passwords cannot be set here'); }
            $columns['ugr_Password'] = (string)call_user_func($this->legacy['hash'], $password);
        }
        $columns['ugr_Modified'] = date('Y-m-d H:i:s');
        if($dryRun){ return array('userId' => $userId, 'created' => false, 'issues' => array()); }

        $issues = array();
        if($row === null){
            $userId = EntityTransaction::run($this->database, function() use ($columns){
                return $this->table->insert($columns + array('ugr_Type' => 'user'));
            });
            $this->notify($userId, $password, false, $issues);
            return array('userId' => $userId, 'created' => true, 'issues' => $issues);
        }
        EntityTransaction::run($this->database, function() use ($userId, $columns){ $this->table->update($userId, $columns); });
        // approval: an administrator enables a user who never logged in (as legacy)
        if(($columns['ugr_Enabled'] ?? null) === 'y' && (string)$row['ugr_Enabled'] === 'n' && intval($row['ugr_LoginCount'] ?? 0) === 0){
            $this->notify($userId, null, true, $issues);
        }
        return array('userId' => $userId, 'created' => false, 'issues' => $issues);
    }

    /**
     * Delete a user (legacy DbSysUsers::delete and its checks).
     *
     * @throws RecordEditException 403, 404, 409 (refused by the legacy checks), 405 (not here)
     */
    public function delete(int $userId): array
    {
        $this->assertAccount('Log in to change user accounts');
        $this->existing($userId);
        if(!$this->isAdmin() && $userId !== $this->runtime->userId){
            throw RecordEditException::denied('You are not an administrator and can not delete another user');
        }
        if($userId === 2){ throw new RecordEditException(409, 'refused', 'The database owner can not be deleted'); }
        $this->legacyDelete('user', $userId);
        return array('deleted' => $userId);
    }

    /**
     * Whether the user may change the image of a user (EntityImageService): their own;
     * administrators any (user 2: only user 2).
     *
     * @throws RecordEditException 403, 404
     */
    public function assertCanEditImage(int $userId): void
    {
        $this->assertAccount('Log in to change user accounts');
        $this->existing($userId);
        if(($userId !== $this->runtime->userId && !$this->isAdmin()) || ($userId === 2 && $this->runtime->userId !== 2)){
            throw RecordEditException::denied('You can not change the image of this user');
        }
    }

    /** Delete with the legacy entity class; a refusal is 409 with its message. */
    private function legacyDelete(string $type, int $id): void
    {
        if(!isset($this->legacy['delete'])){ throw new RecordEditException(405, 'method_not_allowed', 'Users cannot be deleted here'); }
        try{
            call_user_func($this->legacy['delete'], $type, $id);
        }catch(\RuntimeException $e){
            throw new RecordEditException(409, 'refused', $e->getMessage() !== '' ? $e->getMessage() : 'The user was not deleted');
        }
    }

    /**
     * Checked columns of a user: the given ones over the stored row (PUT: not given = empty;
     * the status of a new user: enabled).
     *
     * @throws RecordEditException 400, 403
     */
    private function columns(array $given, ?array $row, bool $replace, bool $admin): array
    {
        $value = static function(string $column) use ($given, $row, $replace): string {
            if(array_key_exists($column, $given)){ return trim((string)$given[$column]); }
            return $replace || $row === null ? '' : trim((string)($row[$column] ?? ''));
        };
        $userId = intval($row['ugr_ID'] ?? 0);
        $columns = array();
        foreach(array('ugr_Name', 'ugr_eMail', 'ugr_FirstName', 'ugr_LastName', 'ugr_Department', 'ugr_Organisation',
            'ugr_City', 'ugr_State', 'ugr_Postcode', 'ugr_Interests', 'ugr_URLs', 'ugr_ORCID') as $column){
            // a column whose field the database lacks is kept as it is
            if(!array_key_exists($column, $given) && $row !== null && !$replace){ continue; }
            if(!array_key_exists($column, $given) && !$this->hasField($column)){ continue; }
            $text = $value($column);
            if(isset(self::LENGTHS[$column]) && mb_strlen($text) > self::LENGTHS[$column]){
                throw RecordEditException::invalid($this->label($column).' has at most '.self::LENGTHS[$column].' characters');
            }
            $columns[$column] = $text === '' ? null : $text;
        }

        $login = (string)($columns['ugr_Name'] ?? ($row['ugr_Name'] ?? ''));
        if(array_key_exists('ugr_Name', $columns) || $row === null){
            if($login === ''){ throw RecordEditException::invalid('The login name is required'); }
            if($this->users->taken('ugr_Name', $login, $userId)){
                throw RecordEditException::invalid('The login name "'.$login.'" is used by another user or group');
            }
        }
        if(array_key_exists('ugr_eMail', $columns) || $row === null){
            $email = (string)($columns['ugr_eMail'] ?? '');
            if($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false){
                throw RecordEditException::invalid('A valid e-mail address is required');
            }
            if($this->users->taken('ugr_eMail', $email, $userId)){
                throw RecordEditException::invalid('The e-mail address '.$email.' is used by another user');
            }
        }
        $orcid = (string)($columns['ugr_ORCID'] ?? '');
        if($orcid !== '' && preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid) !== 1){
            throw RecordEditException::invalid('The ORCID iD is written as 0000-0000-0000-0000');
        }

        // the account status: administrators only; a new user is enabled
        if(array_key_exists('ugr_Enabled', $given)){
            $status = $given['ugr_Enabled'];
            if($status === null){ throw RecordEditException::invalid('The account status is one of '.implode(', ', UserRecordMapping::STATUSES)); }
            if($row === null || $status !== (string)$row['ugr_Enabled']){
                if(!$admin){ throw RecordEditException::denied('Only administrators change the account status'); }
                if($userId === 2 && $status !== 'y'){ throw RecordEditException::invalid('The database owner\'s account stays enabled'); }
                $columns['ugr_Enabled'] = $status;
            }
        }elseif($row === null){
            $columns['ugr_Enabled'] = 'y';
        }
        return $columns;
    }

    /** Whether the field of a column exists in the database (the address: DT_ADDRESS). */
    private function hasField(string $column): bool
    {
        $column = in_array($column, array('ugr_State', 'ugr_Postcode'), true) ? 'ugr_City' : $column;
        foreach($this->mapping->fields() as $mapping){
            if($mapping['column'] === $column){ return true; }
        }
        return false;
    }

    /** Name of a column in messages. */
    private function label(string $column): string
    {
        $labels = array('ugr_Name' => 'The login name', 'ugr_eMail' => 'The e-mail address', 'ugr_FirstName' => 'The given name',
            'ugr_LastName' => 'The family name', 'ugr_Department' => 'The department', 'ugr_Organisation' => 'The organisation',
            'ugr_City' => 'The city', 'ugr_State' => 'The state', 'ugr_Postcode' => 'The postcode',
            'ugr_Interests' => 'The research interests', 'ugr_URLs' => 'The URLs');
        return $labels[$column] ?? $column;
    }

    /** The legacy e-mail of a new or approved user; a failure is a warning (the write is done). */
    private function notify(int $userId, ?string $password, bool $approval, array &$issues): void
    {
        if(!isset($this->legacy['notify'])){ return; }
        try{
            call_user_func($this->legacy['notify'], $userId, $password, $approval);
        }catch(\RuntimeException $e){
            $issues[] = $this->issue('not_sent', 'The e-mail to the user was not sent'.($e->getMessage() !== '' ? ': '.$e->getMessage() : ''));
        }
    }

    private function isAdmin(): bool
    {
        return $this->runtime->isAdmin || $this->runtime->isDbOwner;
    }

    /** A user (404 for a group or an unknown id). */
    private function existing(int $userId): array
    {
        $row = $this->users->rows(array($userId), 'user')[$userId] ?? null;
        if($row === null){ throw RecordEditException::notFound('User '.$userId.' not found'); }
        return $row;
    }
}
