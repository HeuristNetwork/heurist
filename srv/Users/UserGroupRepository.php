<?php
/**
* UserGroupRepository.php - All SQL that reads users and groups
*
* The one srv class that reads sysUGrps and sysUsrGrpLinks (plan 15). Services
* never query these tables themselves - add a method here.
* User and group names, search by name, account state and group roles.
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

use Heurist\Database\DatabaseInterface;
use WeakMap;

/** User and group reads. */
final class UserGroupRepository
{
    /** User tables by key, for SQL text built by the search compiler (Compiler/SqlTables). */
    public const TABLES = array('groups' => 'sysUGrps', 'memberships' => 'sysUsrGrpLinks');

    /** @var WeakMap<DatabaseInterface,self>|null */
    private static ?WeakMap $instances = null;

    private DatabaseInterface $database;
    /** @var array<int,string|null> id => name (null when unknown) */
    private array $names = array();

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /** The repository of a connection (one instance and one cache per connection). */
    public static function shared(DatabaseInterface $database): self
    {
        if(self::$instances === null){ self::$instances = new WeakMap(); }
        if(!isset(self::$instances[$database])){
            self::$instances[$database] = new self($database);
        }
        return self::$instances[$database];
    }

    /**
     * Names (ugr_Name) of users and groups; unknown ids left out.
     *
     * @param array<int,int> $ids
     * @return array<int,string> id => name
     */
    public function names(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id >= 0; })));
        $missing = array_values(array_filter($ids, function($id){ return !array_key_exists($id, $this->names); }));
        foreach(array_chunk($missing, 1000) as $chunk){
            foreach($chunk as $id){ $this->names[$id] = null; }
            foreach($this->database->fetchRows('SELECT ugr_ID,ugr_Name FROM sysUGrps WHERE ugr_ID IN ('
                .implode(',', array_fill(0, count($chunk), '?')).')', $chunk) as $row){
                $this->names[intval($row[0])] = (string)$row[1];
            }
        }
        $result = array();
        foreach($ids as $id){
            if($this->names[$id] !== null){ $result[$id] = $this->names[$id]; }
        }
        return $result;
    }

    /** Name of a user or group ('' when unknown). */
    public function name(int $id): string
    {
        return $this->names(array($id))[$id] ?? '';
    }

    /**
     * Users and groups whose name is the text (case-insensitive).
     *
     * @return array<int,array{0:int,1:string}> [id, name] rows
     */
    public function findByName(string $name): array
    {
        $rows = array();
        foreach($this->database->fetchRows('SELECT ugr_ID,ugr_Name FROM sysUGrps WHERE LOWER(ugr_Name)=LOWER(?)', array($name)) as $row){
            $rows[] = array(intval($row[0]), (string)$row[1]);
            $this->names[intval($row[0])] = (string)$row[1];
        }
        return $rows;
    }

    /**
     * Ids of the users and groups with these names (database collation).
     *
     * @param array<int,string> $names
     * @return array<int,int>
     */
    public function idsByName(array $names): array
    {
        $names = array_values(array_unique(array_map('strval', $names)));
        if(empty($names)){ return array(); }
        return array_values(array_filter(array_map('intval', $this->database->fetchColumn(
            'SELECT ugr_ID FROM sysUGrps WHERE ugr_Name IN ('.implode(',', array_fill(0, count($names), '?')).')', $names
        ))));
    }

    /**
     * The ids that are users or groups.
     *
     * @param array<int,int> $ids
     * @return array<int,int>
     */
    public function existing(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        if(empty($ids)){ return array(); }
        return array_map('intval', $this->database->fetchColumn(
            'SELECT ugr_ID FROM sysUGrps WHERE ugr_ID IN ('.implode(',', array_fill(0, count($ids), '?')).')', $ids
        ));
    }

    /**
     * The kind of users and groups.
     *
     * @param array<int,int> $ids
     * @return array<int,string> id => user|workgroup (the ids that exist)
     */
    public function types(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        $types = array();
        if(empty($ids)){ return $types; }
        foreach($this->database->fetchRows('SELECT ugr_ID,ugr_Type FROM sysUGrps WHERE ugr_ID IN ('
            .implode(',', array_fill(0, count($ids), '?')).')', $ids) as $row){
            $types[intval($row[0])] = (string)$row[1] === 'user' ? 'user' : 'workgroup';
        }
        return $types;
    }

    /** Account state of a user (ugr_Enabled: y, n, y_no_add, ...; '' when unknown). */
    public function enabled(int $userId): string
    {
        return (string)$this->database->fetchValue('SELECT ugr_Enabled FROM sysUGrps WHERE ugr_ID=?', array($userId), '');
    }

    /** Columns of sysUGrps given to the user and group records (never ugr_Password). */
    public const COLUMNS = array(
        'ugr_ID', 'ugr_Type', 'ugr_Name', 'ugr_LongName', 'ugr_Description', 'ugr_eMail', 'ugr_FirstName',
        'ugr_LastName', 'ugr_Department', 'ugr_Organisation', 'ugr_City', 'ugr_State', 'ugr_Postcode',
        'ugr_Interests', 'ugr_Enabled', 'ugr_LastLoginTime', 'ugr_LoginCount', 'ugr_URLs', 'ugr_ORCID', 'ugr_Modified'
    );

    /**
     * Rows of users or groups (plan 20, Phase 4d): the columns of COLUMNS, without the password.
     *
     * @param array<int,int> $ids
     * @param string $type 'user' or 'workgroup'
     * @return array<int,array<string,mixed>> id => row, in the order of the ids
     */
    public function rows(array $ids, string $type): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        $rows = array();
        foreach(array_chunk($ids, 1000) as $chunk){
            foreach($this->database->fetchAll('SELECT '.implode(',', self::COLUMNS).' FROM sysUGrps WHERE ugr_Type=? AND ugr_ID IN ('
                .implode(',', array_fill(0, count($chunk), '?')).')', array_merge(array($type), $chunk)) as $row){
                $rows[intval($row['ugr_ID'])] = $row;
            }
        }
        $ordered = array();
        foreach($ids as $id){ if(isset($rows[$id])){ $ordered[$id] = $rows[$id]; } }
        return $ordered;
    }

    /**
     * Members of groups with their roles.
     *
     * @param array<int,int> $groupIds
     * @return array<int,array<int,string>> group id => user id => admin|member
     */
    public function members(array $groupIds): array
    {
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), static function($id){ return $id > 0; })));
        $members = array();
        foreach(array_chunk($groupIds, 1000) as $chunk){
            foreach($this->database->fetchAll('SELECT ugl_GroupID,ugl_UserID,ugl_Role FROM sysUsrGrpLinks WHERE ugl_GroupID IN ('
                .implode(',', array_fill(0, count($chunk), '?')).') ORDER BY ugl_GroupID,ugl_UserID', $chunk) as $row){
                $members[intval($row['ugl_GroupID'])][intval($row['ugl_UserID'])] = strtolower((string)$row['ugl_Role']) === 'admin' ? 'admin' : 'member';
            }
        }
        return $members;
    }

    /**
     * Whether a name (ugr_Name, users and groups share it) or a user e-mail is taken by
     * another user or group than the given one.
     */
    public function taken(string $column, string $value, int $exceptId = 0): bool
    {
        if(!in_array($column, array('ugr_Name', 'ugr_eMail'), true)){ throw new \InvalidArgumentException('Not a unique column: '.$column); }
        $sql = 'SELECT COUNT(*) FROM sysUGrps WHERE LOWER('.$column.')=LOWER(?) AND ugr_ID<>?'
            .($column === 'ugr_eMail' ? " AND ugr_Type='user'" : '');
        return intval($this->database->fetchValue($sql, array($value, $exceptId), 0)) > 0;
    }

    /**
     * Groups of a user with the user's role.
     *
     * @return array<int,string> group id => admin|member
     */
    public function roles(int $userId): array
    {
        $roles = array();
        if($userId < 1){ return $roles; }
        foreach($this->database->fetchAll('SELECT ugl_GroupID,ugl_Role FROM sysUsrGrpLinks WHERE ugl_UserID=?', array($userId)) as $row){
            $roles[intval($row['ugl_GroupID'])] = strtolower((string)($row['ugl_Role'] ?? '')) === 'admin' ? 'admin' : 'member';
        }
        return $roles;
    }
}
