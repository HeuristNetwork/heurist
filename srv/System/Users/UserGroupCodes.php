<?php
/**
* UserGroupCodes.php - The DT_* constants of the user and group records and their concept codes
*
* Users and groups (plan 20, Phase 4d, §10.13) name their fields by DT_* constants. These
* are the names the user and group mappings use; their concept codes come from
* Definitions/SystemConstants, the one table of the codes (2026-10-27).
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

use Heurist\Definitions\DefinitionLookup;
use Heurist\Definitions\SystemConstants;

/** DT_* / RT_* / TRM_* names of the user and group records => concept codes. */
final class UserGroupCodes
{
    /** Record types (in the core definitions since 2026-10-22). */
    public const RECORD_TYPES = array(
        'RT_SYS_USER' => SystemConstants::CODES['RT_SYS_USER'][1],
        'RT_SYS_GROUP' => SystemConstants::CODES['RT_SYS_GROUP'][1]
    );

    /** Fields: core fields (user codes given by Artem 2026-10-24). */
    public const FIELDS = array(
        'DT_NAME' => SystemConstants::CODES['DT_NAME'][1],                     // user: family name; group: name
        'DT_SHORT_SUMMARY' => SystemConstants::CODES['DT_SHORT_SUMMARY'][1],            // group: long name
        'DT_EXTENDED_DESCRIPTION' => SystemConstants::CODES['DT_EXTENDED_DESCRIPTION'][1],     // group: description
        'DT_GIVEN_NAMES' => SystemConstants::CODES['DT_GIVEN_NAMES'][1],             // user: first name
        'DT_EMAIL' => SystemConstants::CODES['DT_EMAIL'][1],                   // user: e-mail
        'DT_DEPARTMENT' => SystemConstants::CODES['DT_DEPARTMENT'][1],            // user: department / branch / unit
        'DT_URL' => SystemConstants::CODES['DT_URL'][1],                   // URL(s), comma separated
        'DT_LOGIN_NAME' => SystemConstants::CODES['DT_LOGIN_NAME'][1],            // user: login name (unique)
        'DT_PASSWORD' => SystemConstants::CODES['DT_PASSWORD'][1],              // user: password, write only
        'DT_ACCOUNT_STATUS' => SystemConstants::CODES['DT_ACCOUNT_STATUS'][1],        // user: enum TRM_ACCOUNT_STATUS (ugr_Enabled)
        'DT_ORGANISATION_NAME' => SystemConstants::CODES['DT_ORGANISATION_NAME'][1],     // user: organisation (text)
        'DT_ORCID' => SystemConstants::CODES['DT_ORCID'][1],                   // user: ORCID iD (core "Unique public identifier")
        'DT_ADDRESS' => SystemConstants::CODES['DT_ADDRESS'][1],               // user: city / state / postcode, one line each
        'DT_RESEARCH_INTERESTS' => SystemConstants::CODES['DT_RESEARCH_INTERESTS'][1],    // user: research interests
        'DT_LAST_LOGIN' => SystemConstants::CODES['DT_LAST_LOGIN'][1],               // user: last login (read only; core "Date")
        'DT_LOGIN_COUNT' => SystemConstants::CODES['DT_LOGIN_COUNT'][1],           // user: number of logins (read only; core "Usage count")
        // group: relmarker to the users, the role as the relation type (plan 24); hidden in the
        // structure, written through POST /sys/group/{id}/members (sysUsrGrpLinks)
        'DT_GROUP_MEMBERSHIP' => SystemConstants::CODES['DT_GROUP_MEMBERSHIP'][1]
    );

    /** Vocabularies. */
    public const TERMS = array(
        'TRM_ACCOUNT_STATUS' => SystemConstants::CODES['TRM_ACCOUNT_STATUS'][1],      // y, n, y_no_add, y_no_delete, y_no_add_delete
        'TRM_GROUP_ROLE' => SystemConstants::CODES['TRM_GROUP_ROLE'][1]           // term codes admin, member (ugl_Role)
    );

    /** Concept code of a constant ('' when unknown or not yet given). */
    public static function concept(string $name): string
    {
        return self::FIELDS[$name] ?? self::RECORD_TYPES[$name] ?? self::TERMS[$name] ?? '';
    }

    /**
     * Local ids of all the constants in a database (0 when it lacks the definition).
     *
     * @return array<string,int> name => local id
     */
    public static function localIds(DefinitionLookup $definitions): array
    {
        $ids = array();
        foreach(self::RECORD_TYPES as $name => $code){ $ids[$name] = $code === '' ? 0 : $definitions->localId('rty', $code); }
        foreach(self::FIELDS as $name => $code){ $ids[$name] = $code === '' ? 0 : $definitions->localId('dty', $code); }
        foreach(self::TERMS as $name => $code){ $ids[$name] = $code === '' ? 0 : $definitions->localId('trm', $code); }
        return $ids;
    }
}
