<?php
/**
* GroupWriteService.php - Workgroup writes of /sys (plan 20, Phase 4d)
*
*   POST /sys/group/0, PUT / PATCH / DELETE /sys/group/{id}, POST /sys/group/{id}/validate
*   POST /sys/group/{id}/members   {add: [user ids], role: admin|member} | {remove: [user ids]}
*
* The rules of legacy DbSysGroups:
* - any logged-in user adds a group and becomes its admin;
* - a group is changed by its admins and the database owner;
* - the name is required and unique (users and groups share the names);
* - a group keeps at least one admin;
* - delete: legacy DbSysGroups::delete (never group 1, nor a group that owns records).
* The members (sysUsrGrpLinks, the relmarker DT_GROUP_MEMBERSHIP of plan 24) are written by
* the members action only.
*
* @project     Heurist academic knowledge management system
* @package     System\Groups
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Groups;

use Heurist\Database\WritableDatabaseInterface;
use Heurist\Records\Edit\RecordEditException;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Entity\EntityTransaction;
use Heurist\System\Entity\EntityWriteService;
use Heurist\Users\GroupMembershipTable;
use Heurist\Users\LegacyUserGroupTable;
use Heurist\Users\UserGroupRepository;

/** Workgroup writes. */
final class GroupWriteService extends EntityWriteService
{
    /** Column => largest length (sysUGrps). */
    private const LENGTHS = array('ugr_Name' => 63, 'ugr_LongName' => 128, 'ugr_Description' => 1000, 'ugr_URLs' => 2000);

    private WritableDatabaseInterface $database;
    private GroupRecordMapping $mapping;
    private LegacyUserGroupTable $table;
    private GroupMembershipTable $memberships;
    private UserGroupRepository $groups;

    /** @var callable|null Legacy delete of a user or group: (type, id), throws on refusal. */
    private $deleter;

    public function __construct(WritableDatabaseInterface $database, RuntimeContext $runtime, GroupRecordMapping $mapping,
        ?callable $deleter = null)
    {
        parent::__construct($runtime);
        $this->database = $database;
        $this->mapping = $mapping;
        $this->deleter = $deleter;
        $this->table = new LegacyUserGroupTable($database);
        $this->memberships = new GroupMembershipTable($database);
        $this->groups = UserGroupRepository::shared($database);
    }

    /** {@inheritdoc} */
    protected function noun(): string
    {
        return 'group';
    }

    /** {@inheritdoc} */
    protected function route(): string
    {
        return 'sys/group';
    }

    /**
     * Add (POST, id 0), replace (PUT) or change (PATCH) a group.
     *
     * @return array{groupId:int,created:bool,issues:array}
     * @throws RecordEditException
     */
    public function save(string $method, int $groupId, ?array $body, bool $force = false, bool $dryRun = false): array
    {
        $this->assertAccount('Log in to change groups');
        $input = $this->input($this->unwrap($body), $groupId);
        $this->assertMethod($method, $groupId);
        $row = null;
        if($groupId > 0){
            $row = $this->existing($groupId);
            $this->assertGroupAdmin($groupId);
            $this->assertNotChanged($input, (string)$row['ugr_Modified'], $force);
        }
        $columns = $this->columns($this->mapping->writableColumns($input->details()), $row, $method === 'PUT');
        $columns['ugr_Modified'] = date('Y-m-d H:i:s');
        if($dryRun){ return array('groupId' => $groupId, 'created' => false, 'issues' => array()); }

        if($row === null){
            $groupId = EntityTransaction::run($this->database, function() use ($columns){
                // users and groups share the table: a group has no password and no e-mail (legacy placeholders)
                $groupId = $this->table->insert($columns + array('ugr_Type' => 'workgroup', 'ugr_Enabled' => 'y',
                    'ugr_Password' => 'PASSWORD NOT REQUIRED', 'ugr_eMail' => 'EMAIL NOT SET FOR '.$columns['ugr_Name']));
                // its creator is its admin (as legacy)
                $this->writeMembers($groupId, array($this->runtime->userId => 'admin'), array());
                return $groupId;
            });
            return array('groupId' => $groupId, 'created' => true, 'issues' => array());
        }
        $this->table->update($groupId, $columns);
        return array('groupId' => $groupId, 'created' => false, 'issues' => array());
    }

    /**
     * Add users to a group with a role (or change their role), or remove them.
     *
     * @param array|null $body {add: [ids], role: admin|member} or {remove: [ids]}
     * @return array{groupId:int,members:array<int,array{id:int,role:string}>}
     * @throws RecordEditException 400, 403, 404
     */
    public function members(int $groupId, ?array $body): array
    {
        $this->assertAccount('Log in to change groups');
        $this->existing($groupId);
        $this->assertGroupAdmin($groupId);
        $body = $body ?? array();
        $add = array_values(array_unique(array_filter(array_map('intval', (array)($body['add'] ?? array())))));
        $remove = array_values(array_unique(array_filter(array_map('intval', (array)($body['remove'] ?? array())))));
        if(empty($add) === empty($remove)){ throw RecordEditException::invalid('Give "add" (with "role") or "remove": user ids'); }
        $role = strtolower((string)($body['role'] ?? 'member'));
        if(!in_array($role, array('admin', 'member'), true)){ throw RecordEditException::invalid('The role is admin or member'); }
        $current = $this->groups->members(array($groupId))[$groupId] ?? array();
        $next = $current;
        if(!empty($add)){
            $this->assertUsers($add);
            foreach($add as $userId){ $next[$userId] = $role; }
        }
        foreach($remove as $userId){ unset($next[$userId]); }
        if(!in_array('admin', $next, true)){
            throw RecordEditException::invalid('The group keeps at least one admin: this change would leave it without one');
        }
        EntityTransaction::run($this->database, function() use ($groupId, $next, $current){ $this->writeMembers($groupId, $next, $current); });
        $members = array();
        foreach($next as $userId => $userRole){ $members[] = array('id' => $userId, 'role' => $userRole); }
        return array('groupId' => $groupId, 'members' => $members);
    }

    /**
     * Delete a group (legacy DbSysGroups::delete and its checks).
     *
     * @throws RecordEditException 403, 404, 409 (refused by the legacy checks), 405 (not here)
     */
    public function delete(int $groupId): array
    {
        $this->assertAccount('Log in to change groups');
        $this->existing($groupId);
        $this->assertGroupAdmin($groupId);
        if($groupId === 1){ throw new RecordEditException(409, 'refused', 'The group of the database owners can not be deleted'); }
        if($this->deleter === null){ throw new RecordEditException(405, 'method_not_allowed', 'Groups cannot be deleted here'); }
        try{
            call_user_func($this->deleter, 'group', $groupId);
        }catch(\RuntimeException $e){
            throw new RecordEditException(409, 'refused', $e->getMessage() !== '' ? $e->getMessage() : 'The group was not deleted');
        }
        return array('deleted' => $groupId);
    }

    /**
     * Whether the user may change the image of a group (EntityImageService): its admins and the
     * database owner.
     *
     * @throws RecordEditException 403, 404
     */
    public function assertCanEditImage(int $groupId): void
    {
        $this->assertAccount('Log in to change groups');
        $this->existing($groupId);
        $this->assertGroupAdmin($groupId);
    }

    /** The members as given: the removed users go, the others get their role. */
    private function writeMembers(int $groupId, array $next, array $current): void
    {
        $this->memberships->remove($groupId, array_keys(array_diff_key($current, $next)));
        foreach(array('admin', 'member') as $role){
            $users = array();
            foreach($next as $userId => $userRole){
                if($userRole === $role && ($current[$userId] ?? null) !== $role){ $users[] = $userId; }
            }
            $this->memberships->set($groupId, $users, $role);
        }
    }

    /**
     * Checked columns of a group: the given ones over the stored row (PUT: not given = empty).
     *
     * @throws RecordEditException 400
     */
    private function columns(array $given, ?array $row, bool $replace): array
    {
        $columns = array();
        foreach(self::LENGTHS as $column => $length){
            if(!array_key_exists($column, $given)){
                if($row !== null && !$replace){ continue; }
                if($row !== null && $replace && $column !== 'ugr_Name'){ $columns[$column] = null; }
                continue;
            }
            $text = trim((string)$given[$column]);
            if(mb_strlen($text) > $length){ throw RecordEditException::invalid($column.' has at most '.$length.' characters'); }
            $columns[$column] = $text === '' ? null : $text;
        }
        $groupId = intval($row['ugr_ID'] ?? 0);
        if(array_key_exists('ugr_Name', $columns) || $row === null){
            $name = (string)($columns['ugr_Name'] ?? '');
            if($name === ''){ throw RecordEditException::invalid('The name of the group is required'); }
            if($this->groups->taken('ugr_Name', $name, $groupId)){
                throw RecordEditException::invalid('The name "'.$name.'" is used by another group or user');
            }
        }
        return $columns;
    }

    /** @throws RecordEditException 400, 404 for an id that is not a user */
    private function assertUsers(array $userIds): void
    {
        $types = $this->groups->types($userIds);
        foreach($userIds as $userId){
            if(($types[$userId] ?? null) !== 'user'){ throw RecordEditException::invalid('Not a user: '.$userId); }
        }
    }

    /** The database owner or an admin of the group (as legacy DbSysGroups). */
    private function assertGroupAdmin(int $groupId): void
    {
        if($this->runtime->isDbOwner){ return; }
        if(($this->groups->roles($this->runtime->userId)[$groupId] ?? null) !== 'admin'){
            throw RecordEditException::denied('You are not an admin of the group');
        }
    }

    /** A group (404 for a user or an unknown id). */
    private function existing(int $groupId): array
    {
        $row = $this->groups->rows(array($groupId), 'workgroup')[$groupId] ?? null;
        if($row === null){ throw RecordEditException::notFound('Group '.$groupId.' not found'); }
        return $row;
    }
}
