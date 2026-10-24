<?php
/**
* GroupRecordMapping.php - Workgroups (sysUGrps, ugr_Type workgroup) as records of the System User Group type
*
* Plan 20, Phase 4d (§10.13). The fields are named by DT_* constants (UserGroupCodes):
* name, long name (DT_SHORT_SUMMARY), description and URLs. The members (sysUsrGrpLinks)
* are the relmarker DT_GROUP_MEMBERSHIP (plan 24): not a column, given by the keyword
* `members` and written by the members action. A field the database lacks is left out.
* The title is the name.
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
    /** Field constant => sysUGrps column. */
    public const FIELDS = array(
        'DT_NAME' => array('column' => 'ugr_Name'),
        'DT_SHORT_SUMMARY' => array('column' => 'ugr_LongName'),
        'DT_EXTENDED_DESCRIPTION' => array('column' => 'ugr_Description'),
        'DT_URL' => array('column' => 'ugr_URLs')
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
     * Record of a UserGroupRepository row.
     *
     * @return array{headers:array<string,?string>,details:array<int,array<int,string>>}
     */
    public function toRecord(array $row): array
    {
        $details = $this->detailsOf($row);
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
}
