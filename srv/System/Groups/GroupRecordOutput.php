<?php
/**
* GroupRecordOutput.php - Group records of the /sys domain
*
* Plan 20, Phase 4d (§10.13): /sys/group in the record format, with the members as the
* Admins and Members fields. The former keyword `role` (the current user's role in the
* group: admin, member or none) is still given as details.role (UserGroupManager); the keyword
* `members` (and _all) gives details.members [{id, role}], also while the database lacks the
* Admins and Members fields.
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

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Runtime\RuntimeContext;
use Heurist\System\Entity\EntityRecordOutput;
use Heurist\Users\UserGroupRepository;

/** Group records of the /sys domain. */
final class GroupRecordOutput extends EntityRecordOutput
{
    /** Headers of a group record (in this order). */
    public const HEADERS = array('rec_ID', 'rec_RecTypeID', 'rec_Title', 'rec_Modified');

    private GroupRecordMapping $mapping;
    /** @var array<int,array<int,string>> group id => members of the rows loaded last */
    private array $members = array();
    /** @var array<int,string>|null The current user's roles. */
    private ?array $roles = null;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime)
    {
        parent::__construct($database, $runtime);
        $this->mapping = new GroupRecordMapping(DefinitionLookup::shared($database));
    }

    /** {@inheritdoc} */
    public function mapping(): GroupRecordMapping
    {
        return $this->mapping;
    }

    /** {@inheritdoc} */
    protected function headers(): array
    {
        return self::HEADERS;
    }

    /** {@inheritdoc} Every group (their names are public, as legacy); the members with them. */
    protected function rows(array $ids): array
    {
        $repository = UserGroupRepository::shared($this->database);
        $rows = $repository->rows($ids, 'workgroup');
        $this->members = $repository->members(array_keys($rows));
        return $rows;
    }

    /** {@inheritdoc} */
    protected function toRecord(array $row): array
    {
        return $this->mapping->toRecord($row, $this->members[intval($row['ugr_ID'])] ?? array());
    }

    /** {@inheritdoc} A group is its own owner. */
    protected function ownerColumn(): string
    {
        return 'ugr_ID';
    }

    /** {@inheritdoc} The former keyword "role" and the keyword "members"; _all gives both. */
    protected function selectSpecial(string $name, array &$parsed, array &$selection): bool
    {
        if($name === '_all'){
            $selection['role'] = true;
            $selection['members'] = true;
            return false;
        }
        if($name !== 'role' && $name !== 'members'){ return false; }
        $selection[$name] = true;
        return true;
    }

    /** {@inheritdoc} details.role: [{value: admin|member|none}]; details.members: [{id, role}]. */
    protected function addSpecial(array &$record, array $row, array $mapped, array $selection): void
    {
        $special = $selection['special'] ?? array();
        if(empty($special['role']) && empty($special['members'])){ return; }
        $groupId = intval($row['ugr_ID']);
        $details = isset($record['details']) && is_array($record['details']) ? $record['details'] : array();
        if(!empty($special['role'])){
            if($this->roles === null){ $this->roles = UserGroupRepository::shared($this->database)->roles($this->runtime->userId); }
            $details['role'] = array(array('value' => $this->roles[$groupId] ?? 'none'));
        }
        if(!empty($special['members'])){
            $details['members'] = array();
            foreach($this->members[$groupId] ?? array() as $userId => $role){ $details['members'][] = array('id' => $userId, 'role' => $role); }
        }
        $record['details'] = $details;
    }
}
