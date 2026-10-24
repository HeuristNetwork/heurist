<?php
/**
* LegacyUserGroupTable.php - Insert, update and delete of users and groups (sysUGrps)
*
* Rows of users and groups as the user and group records (plan 20, Phase 4d) write them.
* The password column is written here only (hashed by the caller), never read.
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

use Heurist\System\Entity\LegacyEntityTable;

/** Insert, update and delete of sysUGrps rows. */
final class LegacyUserGroupTable extends LegacyEntityTable
{
    /** {@inheritdoc} */
    protected function table(): string
    {
        return UserGroupRepository::TABLES['groups'];
    }

    /** {@inheritdoc} */
    protected function idColumn(): string
    {
        return 'ugr_ID';
    }

    /** {@inheritdoc} The read columns and the password. */
    protected function columnNames(): array
    {
        return array_merge(UserGroupRepository::COLUMNS, array('ugr_Password'));
    }
}
