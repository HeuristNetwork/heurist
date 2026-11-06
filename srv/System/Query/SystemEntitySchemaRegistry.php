<?php
/**
* SystemEntitySchemaRegistry.php - Legacy system entity storage mappings
*
* Maps stable system-record query names to current physical tables and columns.
* The public contract remains unchanged when these entities move to
* sysRecords/sysDetails.
*
* @project     Heurist academic knowledge management system
* @package     System\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);
namespace Heurist\System\Query;

use Heurist\Records\Query\QueryValidationException;

/** Provides validated, hardcoded mappings for supported system record types. */
final class SystemEntitySchemaRegistry
{
    /** Entities with a URL of their own, /{entity}/... = /sys/{entity}/... (plan 28a). api.php reads it. */
    public const ENTITIES = array('user', 'group', 'bookmark', 'reminder', 'tag');

    /** The `t` predicate of a text query. */
    public const TEXT_TYPE = '/(^|\s)(?:t|type|typeid|typename):\s*"?([a-z0-9][a-z0-9_-]*)"?/i';

    private const SCHEMAS = array(
        'filter'=>array(
            'table'=>'usrSavedSearches', 'alias'=>'s',
            'constraint'=>null,
            'headers'=>array(
                'id'=>array('column'=>'svs_ID', 'output'=>'rec_ID', 'type'=>'integer'),
                'title'=>array('column'=>'svs_Name', 'output'=>'rec_Title', 'type'=>'text'),
                'modified'=>array('column'=>'svs_Modified', 'output'=>'rec_Modified', 'type'=>'date'),
                'owner'=>array('column'=>'svs_UGrpID', 'output'=>'rec_OwnerUGrpID', 'type'=>'integer')
            ),
            'fields'=>array(
                'query'=>array('column'=>'svs_Query', 'name'=>'Query', 'type'=>'text'),
                // Temporary logical field while saved filters remain encoded in
                // usrSavedSearches.svs_Query. SystemQueryService removes its
                // predicate before SQL compilation and derives the value in PHP.
                'filtertype'=>array(
                    'column'=>'svs_Query', 'output'=>'filterType',
                    'name'=>'Filter type', 'type'=>'enum', 'virtual'=>true
                )
            )
        ),
        'user'=>array(
            'table'=>'sysUGrps', 'alias'=>'s',
            'constraint'=>'s.ugr_Type="user"',
            'headers'=>array(
                'id'=>array('column'=>'ugr_ID', 'output'=>'rec_ID', 'type'=>'integer'),
                // the title is "Given Family (login)": a title search looks in the three (plan 24 review)
                'title'=>array('column'=>'ugr_Name', 'output'=>'rec_Title', 'type'=>'text',
                    'search'=>array('ugr_Name', 'ugr_FirstName', 'ugr_LastName')),
                'modified'=>array('column'=>'ugr_Modified', 'output'=>'rec_Modified', 'type'=>'date')
            ),
            'fields'=>array(
                'email'=>array('column'=>'ugr_eMail', 'name'=>'Email', 'type'=>'text'),
                // query only: {"group":"4"} - the members of the group (plan 28a)
                'group'=>array('column'=>'ugr_ID', 'name'=>'Member of group', 'type'=>'integer', 'queryOnly'=>true)
            )
        ),
        // Tags (keywords): personal tags (owner = a user) and group tags (owner = a workgroup)
        'tag'=>array(
            'table'=>'usrTags', 'alias'=>'s',
            'constraint'=>null,
            'headers'=>array(
                'id'=>array('column'=>'tag_ID', 'output'=>'rec_ID', 'type'=>'integer'),
                'title'=>array('column'=>'tag_Text', 'output'=>'rec_Title', 'type'=>'text'),
                'modified'=>array('column'=>'tag_Modified', 'output'=>'rec_Modified', 'type'=>'date'),
                'owner'=>array('column'=>'tag_UGrpID', 'output'=>'rec_OwnerUGrpID', 'type'=>'integer')
            ),
            'fields'=>array(
                'description'=>array('column'=>'tag_Description', 'name'=>'Description', 'type'=>'text'),
                // name of the owning user or group (SystemQueryService derives it in PHP)
                'ownername'=>array('column'=>'tag_UGrpID', 'output'=>'ownerName', 'name'=>'Owner name',
                    'type'=>'text', 'virtual'=>true),
                // query only: {"record":"81"} - the tags attached to the given record(s)
                'record'=>array('column'=>'tag_ID', 'name'=>'Tagged record', 'type'=>'integer', 'queryOnly'=>true)
            )
        ),
        // Bookmarks: the personal data of a user on a record (plan 20, §10.9); only the user's own.
        // The title is the title of the record (not a column): the id stands for it in queries and sort.
        'bookmark'=>array(
            'table'=>'usrBookmarks', 'alias'=>'s',
            'constraint'=>null,
            'headers'=>array(
                'id'=>array('column'=>'bkm_ID', 'output'=>'rec_ID', 'type'=>'integer'),
                'title'=>array('column'=>'bkm_ID', 'output'=>'rec_Title', 'type'=>'integer'),
                'modified'=>array('column'=>'bkm_Modified', 'output'=>'rec_Modified', 'type'=>'date'),
                'owner'=>array('column'=>'bkm_UGrpID', 'output'=>'rec_OwnerUGrpID', 'type'=>'integer')
            ),
            'fields'=>array(
                // {"f:record":"81"} - the bookmark of the given record(s)
                'record'=>array('column'=>'bkm_RecID', 'name'=>'Bookmarked record', 'type'=>'integer'),
                'rating'=>array('column'=>'bkm_Rating', 'name'=>'Rating', 'type'=>'integer')
            )
        ),
        // Reminders about records (plan 20, §10.9): the user's own; the database owner all
        'reminder'=>array(
            'table'=>'usrReminders', 'alias'=>'s',
            'constraint'=>null,
            'headers'=>array(
                'id'=>array('column'=>'rem_ID', 'output'=>'rec_ID', 'type'=>'integer'),
                'title'=>array('column'=>'rem_ID', 'output'=>'rec_Title', 'type'=>'integer'),
                'modified'=>array('column'=>'rem_Modified', 'output'=>'rec_Modified', 'type'=>'date'),
                'owner'=>array('column'=>'rem_OwnerUGrpID', 'output'=>'rec_OwnerUGrpID', 'type'=>'integer')
            ),
            'fields'=>array(
                'record'=>array('column'=>'rem_RecID', 'name'=>'Record', 'type'=>'integer'),
                'startdate'=>array('column'=>'rem_StartDate', 'name'=>'Start date', 'type'=>'text'),
                'frequency'=>array('column'=>'rem_Freq', 'name'=>'Frequency', 'type'=>'text')
            )
        ),
        'group'=>array(
            'table'=>'sysUGrps', 'alias'=>'s',
            'constraint'=>'s.ugr_Type="workgroup"',
            'headers'=>array(
                'id'=>array('column'=>'ugr_ID', 'output'=>'rec_ID', 'type'=>'integer'),
                'title'=>array('column'=>'ugr_Name', 'output'=>'rec_Title', 'type'=>'text'),
                'modified'=>array('column'=>'ugr_Modified', 'output'=>'rec_Modified', 'type'=>'date')
            ),
            'fields'=>array(
                // The current user's membership (sysUsrGrpLinks.ugl_Role):
                // admin, member or none. SystemQueryService derives it in PHP.
                'role'=>array(
                    'column'=>'ugr_ID', 'name'=>'Role of current user',
                    'type'=>'enum', 'virtual'=>true
                )
            )
        )
    );

    /** Return one supported entity mapping. */
    public function get(string $type): array
    {
        $type = $this->normalizeType($type);
        if(!isset(self::SCHEMAS[$type])){
            throw new QueryValidationException('Unsupported system record type: '.$type);
        }
        return array_merge(self::SCHEMAS[$type], array('type'=>$type));
    }

    /** Normalize accepted singular/plural aliases to one public type. */
    public function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        $aliases = array('filters'=>'filter', 'workgroup'=>'group',
            'tags'=>'tag', 'keyword'=>'tag', 'keywords'=>'tag', 'bookmarks'=>'bookmark', 'reminders'=>'reminder');
        return $aliases[$type] ?? $type;
    }

    /** Whether a (normalized) name has a mapping here. */
    public function has(string $type): bool
    {
        return isset(self::SCHEMAS[$type]);
    }

    /** Return logical entity-specific field names for the shared parser. */
    public function logicalFields(string $type): array
    {
        return array_keys($this->get($type)['fields']);
    }

    /** The first value of a t predicate of a query, or null: a name, a record type id or a concept code. */
    public function findTypeValue($query)
    {
        if(is_string($query)){
            $text = trim($query);
            $decoded = json_decode($text, true);
            if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
                return $this->findTypeValue($decoded);
            }
            if(preg_match(self::TEXT_TYPE, $text, $matches)){
                return $matches[2];
            }
            return null;
        }
        if(!is_array($query)){ return null; }
        foreach($query as $key=>$value){
            if(is_string($key) && in_array(strtolower($key), array('t','type','typeid','typename'), true)){
                return is_array($value) ? reset($value) : $value;
            }
            if(is_array($value)){
                $found = $this->findTypeValue($value);
                if($found !== null){ return $found; }
            }
        }
        return null;
    }
}
