<?php
/**
* RecordAccess.php - The user that record reads are checked for
*
* User id, groups and roles, from the runtime or from a search context. The
* rules themselves are in RecordRepository (accessSql, visibleIds, editableIds).
*
* @project     Heurist academic knowledge management system
* @package     Records\Store
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Store;

use Heurist\Runtime\RuntimeContext;

/** User of record access checks. */
final class RecordAccess
{
    public int $userId;
    /** @var array<int,int> Groups the user is a member of. */
    public array $groupIds;
    public bool $isDbOwner;
    public bool $isAdmin;

    /**
     * @param int $userId 0 = guest.
     * @param array<int,int> $groupIds
     */
    public function __construct(int $userId, array $groupIds = array(), bool $isDbOwner = false, bool $isAdmin = false)
    {
        $this->userId = max(0, $userId);
        $this->groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), static function($id){
            return $id >= 0;
        })));
        $this->isDbOwner = $isDbOwner;
        $this->isAdmin = $isAdmin;
    }

    public static function fromRuntime(RuntimeContext $runtime): self
    {
        return new self($runtime->userId, $runtime->groupIds, $runtime->isDbOwner, $runtime->isAdmin);
    }

    /** From a search context array (userId, groupIds, isDbOwner). */
    public static function fromContext(array $context): self
    {
        return new self(intval($context['userId'] ?? 0),
            is_array($context['groupIds'] ?? null) ? $context['groupIds'] : array(),
            !empty($context['isDbOwner']), !empty($context['isAdmin']));
    }

    public static function guest(): self
    {
        return new self(0);
    }

    /** Owners whose records the user sees: the groups, the user itself and 0 (everyone). */
    public function viewerGroups(): array
    {
        $groups = array_values(array_filter($this->groupIds, static function($id){ return $id > 0; }));
        if($this->userId > 0 && !in_array($this->userId, $groups, true)){ $groups[] = $this->userId; }
        $groups[] = 0;
        return $groups;
    }

    /** The groups of the user and the user itself (owners and edit groups the user may change). */
    public function memberOf(): array
    {
        $groups = array_values(array_filter($this->groupIds, static function($id){ return $id > 0; }));
        if($this->userId > 0){ $groups[] = $this->userId; }
        return array_values(array_unique($groups));
    }
}
