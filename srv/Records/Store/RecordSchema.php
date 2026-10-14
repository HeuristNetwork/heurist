<?php
/**
* RecordSchema.php - Table names of one set of record tables
*
* The record tables (headers, detail values, links, date index, permissions and
* the user tables that refer to records) are given once, on init. The current
* tables are the defaults; system records can later use their own tables:
*   new RecordSchema()                                   Records, recDetails, ...
*   new RecordSchema(['records' => 'sysRecords', 'details' => 'sysRecDetails'])
* RecordRepository (reads), RecordStore (writes) and the search compiler take the
* same object, so they always use the same tables (plan 15, agreed 2026-10-13).
* Names only, validated as identifiers; no queries.
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

use InvalidArgumentException;

/** Table names of the record tables. */
final class RecordSchema
{
    /** Default (and current) tables of all records. */
    public const DEFAULT_TABLES = array(
        'records' => 'Records',
        'details' => 'recDetails',
        'links' => 'recLinks',
        'dateIndex' => 'recDetailsDateIndex',
        'permissions' => 'usrRecPermissions',
        'bookmarks' => 'usrBookmarks',
        'tagLinks' => 'usrRecTagLinks',
        'tags' => 'usrTags',
        'reminders' => 'usrReminders',
        'forwarding' => 'recForwarding',
        'comments' => 'recThreadedComments',
        'worksets' => 'usrWorkingSubsets'
    );

    /** User tables that refer to a record and are cleaned when it is deleted (key => record column). */
    private const REFERENCES = array(
        'reminders' => 'rem_RecID',
        'forwarding' => 'rfw_NewRecID',
        'tagLinks' => 'rtl_RecID',
        'comments' => 'cmt_RecID',
        'bookmarks' => 'bkm_recID'
    );

    /** @var array<string,string> */
    private array $tables;

    /**
     * @param array<string,string> $tables Table names by key (DEFAULT_TABLES keys); others stay default.
     * @throws InvalidArgumentException For an unknown key or an invalid name.
     */
    public function __construct(array $tables = array())
    {
        $unknown = array_diff(array_keys($tables), array_keys(self::DEFAULT_TABLES));
        if(!empty($unknown)){
            throw new InvalidArgumentException('Unknown record table '.implode(', ', $unknown));
        }
        $tables = array_merge(self::DEFAULT_TABLES, $tables);
        foreach($tables as $name){
            if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string)$name)){
                throw new InvalidArgumentException('Invalid table name '.$name);
            }
        }
        $this->tables = $tables;
    }

    /** Table name by key (records, details, links, dateIndex, permissions, bookmarks, tagLinks, tags, ...). */
    public function table(string $key): string
    {
        if(!isset($this->tables[$key])){
            throw new InvalidArgumentException('Unknown record table '.$key);
        }
        return $this->tables[$key];
    }

    /** @return array<string,string> All table names by key. */
    public function tables(): array
    {
        return $this->tables;
    }

    public function records(): string
    {
        return $this->tables['records'];
    }

    public function details(): string
    {
        return $this->tables['details'];
    }

    public function links(): string
    {
        return $this->tables['links'];
    }

    public function permissions(): string
    {
        return $this->tables['permissions'];
    }

    /**
     * User tables that refer to a record (deleted with it): table name => record column.
     *
     * @return array<string,string>
     */
    public function references(): array
    {
        $result = array();
        foreach(self::REFERENCES as $key => $column){
            $result[$this->tables[$key]] = $column;
        }
        return $result;
    }
}
