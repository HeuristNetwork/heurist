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
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);
namespace Heurist\System\Query;

use Heurist\Records\Query\QueryValidationException;

/** Provides validated, hardcoded mappings for supported system record types. */
final class SystemEntitySchemaRegistry
{
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
                'title'=>array('column'=>'ugr_Name', 'output'=>'rec_Title', 'type'=>'text'),
                'modified'=>array('column'=>'ugr_Modified', 'output'=>'rec_Modified', 'type'=>'date')
            ),
            'fields'=>array(
                'email'=>array('column'=>'ugr_eMail', 'name'=>'Email', 'type'=>'text')
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
        $aliases = array('filters'=>'filter', 'users'=>'user');
        return $aliases[$type] ?? $type;
    }

    /** Return logical entity-specific field names for the shared parser. */
    public function logicalFields(string $type): array
    {
        return array_keys($this->get($type)['fields']);
    }

    /** Resolve a top-level t predicate, defaulting the initial /sys route to filter. */
    public function typeFromQuery($query, ?string $pathType = null): string
    {
        if($pathType !== null && trim($pathType) !== ''){
            return $this->get($pathType)['type'];
        }
        $value = $this->findTypeValue($query);
        return $this->get($value === null ? 'filter' : (string)$value)['type'];
    }

    private function findTypeValue($query)
    {
        if(is_string($query)){
            $text = trim($query);
            $decoded = json_decode($text, true);
            if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
                return $this->findTypeValue($decoded);
            }
            if(preg_match('/(?:^|\s)(?:t|type):\s*"?([a-z][a-z0-9_-]*)/i', $text, $matches)){
                return $matches[1];
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
