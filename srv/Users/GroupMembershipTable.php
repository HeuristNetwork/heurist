<?php
/**
* GroupMembershipTable.php - Group membership writes (sysUsrGrpLinks)
*
* The members of a group and their roles (admin, member), as the group records and
* POST /sys/group/{id}/members write them (plan 20, Phase 4d).
*
* @project     Heurist academic knowledge management system
* @package     Users
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Users;

use Heurist\Database\WritableDatabaseInterface;

/** Writes of sysUsrGrpLinks. */
final class GroupMembershipTable
{
    private WritableDatabaseInterface $database;

    public function __construct(WritableDatabaseInterface $database)
    {
        $this->database = $database;
    }

    /**
     * Set the role of users in a group (added when they are not members).
     *
     * @param array<int,int> $userIds
     * @param string $role admin|member
     */
    public function set(int $groupId, array $userIds, string $role): void
    {
        $role = $role === 'admin' ? 'admin' : 'member';
        foreach(array_values(array_unique(array_map('intval', $userIds))) as $userId){
            if($userId < 1){ continue; }
            $this->database->execute('DELETE FROM sysUsrGrpLinks WHERE ugl_GroupID=? AND ugl_UserID=?', array($groupId, $userId));
            $this->database->execute('INSERT INTO sysUsrGrpLinks (ugl_GroupID,ugl_UserID,ugl_Role) VALUES (?,?,?)',
                array($groupId, $userId, $role));
        }
    }

    /**
     * Remove users from a group.
     *
     * @param array<int,int> $userIds
     */
    public function remove(int $groupId, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static function($id){ return $id > 0; })));
        if(empty($userIds)){ return; }
        $this->database->execute('DELETE FROM sysUsrGrpLinks WHERE ugl_GroupID=? AND ugl_UserID IN ('
            .implode(',', array_fill(0, count($userIds), '?')).')', array_merge(array($groupId), $userIds));
    }
}
