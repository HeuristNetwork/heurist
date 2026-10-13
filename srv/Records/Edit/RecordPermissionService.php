<?php
/**
* RecordPermissionService.php - Who may add, change and delete records
*
* Port of userCheckPermissions() (hserv/structure/dbsUsersGroups.php) and of
* the ownership rules of recordAdd() and recordCanChangeOwnerwhipAndAccess()
* (hserv/records/edit/recordModify.php):
*   - account state ugr_Enabled: "n" and "y_no_add" may not write, "*_no_add*"
*     may not add, "*_no_delete*" may not delete;
*   - a record owned by 0 (everyone) may be changed by any logged-in user;
*     otherwise by the database administrator or a member of the owner or of an
*     edit group (usrRecPermissions rcp_Level="edit");
*   - only the administrator may give an "everyone" record to a group; a new
*     owner or edit group needs membership; removing one needs ownership rights
*     (the user itself, the database owner or an administrator of that group);
*   - a new record needs membership of its owner.
* Guest users have no special rights in the new API.
*
* @project     Heurist academic knowledge management system
* @package     Records\Edit
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Edit;

use Heurist\Database\DatabaseInterface;
use Heurist\Runtime\RuntimeContext;

/** Permission checks of record writes. */
final class RecordPermissionService
{
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private ?string $enabled = null;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime)
    {
        $this->database = $database;
        $this->runtime = $runtime;
    }

    /**
     * Check that the account may add, edit or delete records.
     *
     * @param string $action add|edit|delete
     * @throws RecordEditException 401 when not logged in, 403 when not allowed.
     */
    public function assertAccount(string $action): void
    {
        if($this->runtime->userId < 1){
            throw new RecordEditException(401, 'access_denied', 'Log in to change records');
        }
        if($this->enabled === null){
            $this->enabled = (string)$this->database->fetchValue(
                'SELECT ugr_Enabled FROM sysUGrps WHERE ugr_ID=?', array($this->runtime->userId), ''
            );
        }
        $enabled = $this->enabled;
        $verb = $action === 'add' ? 'create' : ($action === 'edit' ? 'modify' : $action);
        if($enabled === '' || $enabled === 'n'){
            throw RecordEditException::denied('Only enabled accounts can '.$verb.' records');
        }
        if($enabled === 'y_no_add'
            || ($action === 'add' && strpos($enabled, 'add') !== false)
            || ($action === 'delete' && strpos($enabled, 'delete') !== false)){
            throw RecordEditException::denied('Your account does not have permission to '.$verb
                .' records, please contact the database owner');
        }
    }

    /** User, groups of the user and the user itself. */
    public function memberOf(): array
    {
        $ids = $this->runtime->groupIds;
        if($this->runtime->userId > 0){ $ids[] = $this->runtime->userId; }
        return array_values(array_unique($ids));
    }

    /** True when the user is the group (or user) or a member of it; 0 (everyone) is always true. */
    public function isMember(int $groupId): bool
    {
        return $groupId === 0 || in_array($groupId, $this->memberOf(), true);
    }

    /** Ownership rights on a group: the user itself, the database owner or an administrator of the group. */
    public function hasAccess(int $groupId): bool
    {
        $userId = $this->runtime->userId;
        if($userId < 1){ return false; }
        if($groupId < 1){ return true; }
        return $groupId === $userId || $this->runtime->isDbOwner || in_array($groupId, $this->runtime->adminGroupIds, true);
    }

    /**
     * Owner and edit groups of a stored record.
     *
     * @return array{owner:int,editGroups:array<int,int>}|null Null when the record does not exist.
     */
    public function owners(int $recordId): ?array
    {
        $owner = $this->database->fetchValue('SELECT rec_OwnerUGrpID FROM Records WHERE rec_ID=?', array($recordId));
        if($owner === null){ return null; }
        $groups = array_map('intval', $this->database->fetchColumn(
            'SELECT rcp_UGrpID FROM usrRecPermissions WHERE rcp_RecID=? AND rcp_Level="edit" ORDER BY rcp_ID',
            array($recordId)
        ));
        return array('owner'=>intval($owner), 'editGroups'=>array_values(array_unique($groups)));
    }

    /** True when the user may change the record with these owner and edit groups. */
    public function canEditOwned(int $owner, array $editGroups): bool
    {
        if($this->runtime->userId < 1){ return false; }
        if($owner === 0 || $this->runtime->isAdmin){ return true; }
        return !empty(array_intersect(array_merge(array($owner), $editGroups), $this->memberOf()));
    }

    /**
     * Ids of the records the user may change (several records at once, for the
     * GET output of edit groups and hidden values).
     *
     * @param array<int,int> $recordIds
     * @return array<int,bool> rec_ID => true
     */
    public function editableIds(array $recordIds): array
    {
        $recordIds = array_values(array_unique(array_filter(array_map('intval', $recordIds))));
        if(empty($recordIds) || $this->runtime->userId < 1){ return array(); }
        if($this->runtime->isAdmin){ return array_fill_keys($recordIds, true); }
        $members = $this->memberOf();
        $editable = array();
        foreach(array_chunk($recordIds, 500) as $chunk){
            $ids = implode(',', array_fill(0, count($chunk), '?'));
            $groups = implode(',', array_fill(0, count($members), '?'));
            $sql = 'SELECT rec_ID FROM Records WHERE rec_ID IN ('.$ids.') AND (rec_OwnerUGrpID=0 '
                .'OR rec_OwnerUGrpID IN ('.$groups.') OR EXISTS(SELECT 1 FROM usrRecPermissions '
                .'WHERE rcp_RecID=rec_ID AND rcp_Level="edit" AND rcp_UGrpID IN ('.$groups.')))';
            foreach($this->database->fetchColumn($sql, array_merge($chunk, $members, $members)) as $id){
                $editable[intval($id)] = true;
            }
        }
        return $editable;
    }

    /**
     * Check the owners of a new record: they must exist and the user must be a
     * member of the owner (or an administrator).
     *
     * @throws RecordEditException 403/422
     */
    public function assertNewOwners(int $owner, array $editGroups, bool $byWorkflow = false): void
    {
        $this->assertGroupsExist(array_merge(array($owner), $editGroups));
        if(!$byWorkflow && !$this->runtime->isAdmin && !$this->isMember($owner)){
            throw RecordEditException::denied('You must be a member of the group that will own this record (group '.$owner.')');
        }
    }

    /**
     * Check a change of the owner and edit groups of a stored record
     * (legacy recordCanChangeOwnerwhipAndAccess, point 2).
     *
     * @param array{owner:int,editGroups:array<int,int>} $current Stored owners.
     * @throws RecordEditException 403
     */
    public function assertOwnerChange(array $current, int $owner, array $editGroups): void
    {
        $old = array_values(array_unique(array_merge(array($current['owner']), $current['editGroups'])));
        $new = array_values(array_unique(array_merge(array($owner), $editGroups)));
        if($old == $new){ return; }
        $this->assertGroupsExist($new);
        if($this->runtime->isAdmin){ return; }
        if($current['owner'] === 0 && $owner > 0){
            throw RecordEditException::denied('Only the database administrator can change a record owned by everyone to a group record');
        }
        foreach($new as $group){
            if(!in_array($group, $old, true) && !$this->isMember($group)){
                throw RecordEditException::denied('You cannot give the record to group '.$group.' without membership in this group');
            }
        }
        foreach($old as $group){
            if(!in_array($group, $new, true) && !$this->hasAccess($group)){
                throw RecordEditException::denied('You cannot remove group '.$group.' from the record owners; '
                    .'you must be the database administrator, the record owner or an administrator of that group');
            }
        }
    }

    /** @throws RecordEditException 422 when a user or group does not exist */
    private function assertGroupsExist(array $groupIds): void
    {
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
        if(empty($groupIds)){ return; }
        $found = array_map('intval', $this->database->fetchColumn(
            'SELECT ugr_ID FROM sysUGrps WHERE ugr_ID IN ('.implode(',', array_fill(0, count($groupIds), '?')).')',
            $groupIds
        ));
        $missing = array_diff($groupIds, $found);
        if(!empty($missing)){
            throw new RecordEditException(422, 'validation', 'User or group '.implode(', ', $missing).' does not exist', array(
                array('field'=>'rec_OwnerUGrpID', 'index'=>null, 'code'=>'owner', 'severity'=>'error',
                    'message'=>'User or group '.implode(', ', $missing).' does not exist')
            ));
        }
    }
}
