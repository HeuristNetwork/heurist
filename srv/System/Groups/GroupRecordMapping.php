<?php
/**
* GroupRecordMapping.php - Workgroups (sysUGrps, ugr_Type workgroup) as records of the System User Group type
*
* Plan 20, Phase 4d (§10.13). The fields are named by DT_* constants (UserGroupCodes):
* name, long name (DT_SHORT_SUMMARY), description and URLs; the members (sysUsrGrpLinks)
* are two repeatable pointer fields to users, Admins and Members. A constant without a
* concept code yet, or a field the database lacks, is left out. The title is the name.
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

use Heurist\System\Entity\EntityRecordMapping;
use Heurist\System\Users\UserGroupCodes;

/** Converts rows of sysUGrps (workgroups) with their members to group records. */
final class GroupRecordMapping extends EntityRecordMapping
{
    /** Field constant => sysUGrps column; the members are not columns (role => the users of the role). */
    public const FIELDS = array(
        'DT_NAME' => array('column' => 'ugr_Name'),
        'DT_SHORT_SUMMARY' => array('column' => 'ugr_LongName'),
        'DT_EXTENDED_DESCRIPTION' => array('column' => 'ugr_Description'),
        'DT_URL' => array('column' => 'ugr_URLs'),
        'DT_GROUP_ADMINS' => array('column' => 'admin', 'members' => true),
        'DT_GROUP_MEMBERS' => array('column' => 'member', 'members' => true)
    );

    /** Fields a client may write (the members are written by the write service, not as columns). */
    public const WRITABLE_FIELDS = array('DT_NAME', 'DT_SHORT_SUMMARY', 'DT_EXTENDED_DESCRIPTION', 'DT_URL');

    /** {@inheritdoc} */
    public function rectypeConcept(): string
    {
        return UserGroupCodes::RECORD_TYPES['RT_SYS_GROUP'];
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

    /** {@inheritdoc} The fields are DT_* constants. */
    protected function fieldLocalId(string $key): int
    {
        $concept = UserGroupCodes::concept($key);
        return $concept === '' ? 0 : $this->definitions->localId('dty', $concept);
    }

    /**
     * Record of a UserGroupRepository row and the members of the group.
     *
     * @param array<int,string> $members user id => admin|member
     * @return array{headers:array<string,?string>,details:array<int,array<int,string>>}
     */
    public function toRecord(array $row, array $members = array()): array
    {
        $details = $this->detailsOf($row);
        foreach($this->fields() as $fieldId => $mapping){
            if(empty($mapping['members'])){ continue; }
            $users = array_keys(array_filter($members, static function($role) use ($mapping){ return $role === $mapping['column']; }));
            if(!empty($users)){ $details[$fieldId] = array_map('strval', $users); }
        }
        return array(
            'headers' => array(
                'rec_ID' => (string)intval($row['ugr_ID']),
                'rec_RecTypeID' => (string)$this->rectypeId(),
                'rec_Title' => (string)($row['ugr_Name'] ?? ''),
                'rec_Modified' => isset($row['ugr_Modified']) ? (string)$row['ugr_Modified'] : null
            ),
            'details' => $details
        );
    }

    /**
     * The members of a request: user id => role, from the Admins and Members fields; null
     * when the request gives neither field (the members stay as they are).
     *
     * @param array<int,array<int,mixed>> $details field id => values as given
     * @return array<int,string>|null
     */
    public function membersOfRequest(array $details): ?array
    {
        $given = false;
        $members = array();
        foreach(array('member', 'admin') as $role){
            $fieldId = $this->fieldId($role === 'admin' ? 'DT_GROUP_ADMINS' : 'DT_GROUP_MEMBERS');
            if($fieldId < 1 || !array_key_exists($fieldId, $details)){ continue; }
            $given = true;
            foreach((array)$details[$fieldId] as $item){
                if(is_array($item)){ $item = $item['value'] ?? $item['rec_ID'] ?? null; }
                $userId = intval($item);
                // an admin given in both fields is an admin
                if($userId > 0){ $members[$userId] = $role; }
            }
        }
        return $given ? $members : null;
    }

    /** {@inheritdoc} The members are not columns. */
    protected function fieldValue(int $fieldId, array $mapping, array $row): ?string
    {
        return empty($mapping['members']) ? parent::fieldValue($fieldId, $mapping, $row) : null;
    }
}
