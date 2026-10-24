<?php
/**
* UserGroupCodes.php - The DT_* constants of the user and group records and their concept codes
*
* Users and groups (plan 20, Phase 4d, §10.13) name their fields by DT_* constants. The
* constants of the new fields are listed here with their concept codes; a code that is
* still empty (the field is not yet in the core definitions) leaves the field out, as a
* field the database lacks. The table is the one place to fill in the codes: the mappings
* and the client constants (DefinitionSnapshotService dbconst) read it.
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

/** DT_* / RT_* / TRM_* names of the user and group records => concept codes. */
final class UserGroupCodes
{
    /** Record types (in the core definitions since 2026-10-22). */
    public const RECORD_TYPES = array(
        'RT_SYS_USER' => '2-1107',
        'RT_SYS_GROUP' => '2-1108'
    );

    /** Fields: core fields (user codes given by Artem 2026-10-24); '' = no concept code yet. */
    public const FIELDS = array(
        'DT_NAME' => '2-1',                     // user: family name; group: name
        'DT_SHORT_SUMMARY' => '2-3',            // group: long name
        'DT_EXTENDED_DESCRIPTION' => '2-4',     // group: description
        'DT_GIVEN_NAMES' => '2-18',             // user: first name
        'DT_EMAIL' => '2-23',                   // user: e-mail
        'DT_DEPARTMENT' => '3-1016',            // user: department / branch / unit
        'DT_URL' => '3-1058',                   // URL(s), comma separated
        'DT_LOGIN_NAME' => '2-1194',            // user: login name (unique)
        'DT_PASSWORD' => '2-1195',              // user: password, write only
        'DT_ACCOUNT_STATUS' => '2-1199',        // user: enum TRM_ACCOUNT_STATUS (ugr_Enabled)
        'DT_ORGANISATION_NAME' => '2-1198',     // user: organisation (text)
        'DT_ORCID' => '2-37',                   // user: ORCID iD (core "Unique public identifier")
        'DT_ADDRESS' => '2-1197',               // user: city / state / postcode, one line each
        'DT_RESEARCH_INTERESTS' => '2-1196',    // user: research interests
        'DT_LAST_LOGIN' => '2-9',               // user: last login (read only; core "Date")
        'DT_LOGIN_COUNT' => '2-1189',           // user: number of logins (read only; core "Usage count")
        // group: relmarker to the users, the role as the relation type (plan 24); hidden in the
        // structure, written through POST /sys/group/{id}/members (sysUsrGrpLinks)
        'DT_GROUP_MEMBERSHIP' => '2-1200'
    );

    /** Vocabularies. */
    public const TERMS = array(
        'TRM_ACCOUNT_STATUS' => '2-10566',      // y, n, y_no_add, y_no_delete, y_no_add_delete
        'TRM_GROUP_ROLE' => '2-10572'           // term codes admin, member (ugl_Role)
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
