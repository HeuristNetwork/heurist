<?php
/**
* RecordRepository.php - All SQL that reads records
*
* The one srv class (with RecordStore for writes and the search compiler for
* search SQL) that reads the record tables: headers, detail values, links and
* relationships, owners and access, tags and bookmarks, counts (plan 15,
* heurist-explorer docs/development/15 DONE Data-Access-Layer-Plan.md). Table names
* come from RecordSchema, so a move of records to other tables changes the
* schema only. Services never query these tables themselves - add a method here.
*
* All methods take id lists and read in batches. Rows are plain arrays.
* Visibility has one implementation (accessSql); field-level rules of the
* definitions are applied by the callers.
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

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use InvalidArgumentException;
use WeakMap;

/** Record reads on one set of record tables. */
final class RecordRepository
{
    /** Ids per statement. */
    public const BATCH_SIZE = 1000;

    /** Default header columns. */
    public const HEADERS = array('rec_ID', 'rec_RecTypeID', 'rec_Title');

    /** Header columns of the edit services (RecordStore). */
    public const EDIT_HEADERS = array('rec_ID', 'rec_RecTypeID', 'rec_OwnerUGrpID', 'rec_NonOwnerVisibility', 'rec_URL',
        'rec_ScratchPad', 'rec_Modified', 'rec_FlagTemporary', 'rec_Title');

    /** @var WeakMap<DatabaseInterface,self>|null */
    private static ?WeakMap $instances = null;

    private DatabaseInterface $database;
    private RecordSchema $schema;

    public function __construct(DatabaseInterface $database, ?RecordSchema $schema = null)
    {
        $this->database = $database;
        $this->schema = $schema ?? new RecordSchema();
    }

    /** The repository of a connection on the default tables (one instance per connection). */
    public static function shared(DatabaseInterface $database): self
    {
        if(self::$instances === null){ self::$instances = new WeakMap(); }
        if(!isset(self::$instances[$database])){
            self::$instances[$database] = new self($database);
        }
        return self::$instances[$database];
    }

    public function schema(): RecordSchema
    {
        return $this->schema;
    }

    /** Whether the record and detail tables of the schema exist in the database (fileRecords, plan 20). */
    public function tablesExist(): bool
    {
        $tables = array($this->schema->records(), $this->schema->details());
        return intval($this->database->fetchValue('SELECT COUNT(*) FROM information_schema.TABLES '
            .'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)', $tables, 0)) === count($tables);
    }

    // ------------------------------------------------------------ headers

    /**
     * Header columns of records, in the order of the ids; records that do not exist
     * (or that the user may not view, with $access) are left out.
     *
     * @param array<int,int> $ids
     * @param array<int,string> $columns rec_* columns; rec_ID is always given.
     * @return array<int,array<string,mixed>> id => row (database values)
     */
    public function headers(array $ids, array $columns = self::HEADERS, ?RecordAccess $access = null): array
    {
        $ids = self::ids($ids);
        if(empty($ids)){ return array(); }
        $columns = $this->columns($columns);
        $found = array();
        foreach(array_chunk($ids, self::BATCH_SIZE) as $chunk){
            $sql = 'SELECT '.implode(',', $columns).' FROM '.$this->schema->records()
                .' WHERE rec_ID IN ('.self::placeholders($chunk).')';
            $parameters = $chunk;
            if($access !== null){
                list($condition, $values) = $this->accessSql('', $access);
                $sql .= ' AND '.$condition;
                $parameters = array_merge($parameters, $values);
            }
            foreach($this->database->fetchAll($sql, $parameters) as $row){
                $found[intval($row['rec_ID'])] = $row;
            }
        }
        $result = array();
        foreach($ids as $id){
            if(isset($found[$id])){ $result[$id] = $found[$id]; }
        }
        return $result;
    }

    /** Header of one record (edit columns by default), null when it does not exist. */
    public function header(int $id, array $columns = self::EDIT_HEADERS): ?array
    {
        return $this->headers(array($id), $columns)[$id] ?? null;
    }

    /**
     * Records with a header value (rec_Hash or rec_URL; registered files, plan 20).
     *
     * @return array<int,int> rec_ID in id order
     */
    public function idsByHeader(string $column, string $value): array
    {
        if(!in_array($column, array('rec_Hash', 'rec_URL'), true)){
            throw new InvalidArgumentException('Records cannot be found by '.$column);
        }
        return array_map('intval', $this->database->fetchColumn('SELECT rec_ID FROM '.$this->schema->records()
            .' WHERE '.$column.'=? ORDER BY rec_ID', array($value)));
    }

    /**
     * Records with a value of a field (exact).
     *
     * @return array<int,int> rec_ID in id order
     */
    public function idsByValue(int $fieldId, string $value): array
    {
        return array_map('intval', $this->database->fetchColumn('SELECT DISTINCT dtl_RecID FROM '.$this->schema->details()
            .' WHERE dtl_DetailTypeID=? AND dtl_Value=? ORDER BY dtl_RecID', array($fieldId, $value)));
    }

    /**
     * Records whose file fields refer to registered files (dtl_UploadedFileID).
     *
     * @param array<int,int> $fileIds
     * @return array<int,array<int,int>> file id => record ids
     */
    public function fileReferences(array $fileIds): array
    {
        $fileIds = self::ids($fileIds);
        $result = array();
        foreach(array_chunk($fileIds, self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchRows('SELECT DISTINCT dtl_UploadedFileID,dtl_RecID FROM '.$this->schema->details()
                .' WHERE dtl_UploadedFileID IN ('.self::placeholders($chunk).') ORDER BY dtl_RecID', $chunk) as $row){
                $result[intval($row[0])][] = intval($row[1]);
            }
        }
        return $result;
    }

    /**
     * Record type of records.
     *
     * @return array<int,int> id => record type
     */
    public function rectypeOf(array $ids): array
    {
        $result = array();
        foreach($this->headers($ids, array('rec_ID', 'rec_RecTypeID')) as $id => $row){
            $result[$id] = intval($row['rec_RecTypeID']);
        }
        return $result;
    }

    /**
     * Records of a record type (temporary records left out), in database order.
     *
     * @param array<int,string> $columns rec_* columns.
     * @param RecordAccess|null $access Only records the user may view (null = all).
     * @param array<int,int>|null $ids Only these records.
     * @return array<int,array<string,mixed>>
     */
    public function find(int $rectypeId, array $columns = self::HEADERS, ?RecordAccess $access = null, ?array $ids = null): array
    {
        $sql = 'SELECT '.implode(',', $this->columns($columns)).' FROM '.$this->schema->records().' WHERE rec_RecTypeID=?';
        $parameters = array($rectypeId);
        if($ids !== null){
            $ids = self::ids($ids);
            if(empty($ids)){ return array(); }
            $sql .= ' AND rec_ID IN ('.self::placeholders($ids).')';
            $parameters = array_merge($parameters, $ids);
        }
        if($access !== null){
            list($condition, $values) = $this->accessSql('', $access);
            $sql .= ' AND '.$condition;
            $parameters = array_merge($parameters, $values);
        }else{
            $sql .= ' AND rec_FlagTemporary=0';
        }
        return $this->database->fetchAll($sql, $parameters);
    }

    /**
     * Number of records by record type: of the given records, or of all records
     * that are not temporary.
     *
     * @param array<int,int>|null $ids
     * @return array<int,int> record type => count, by record type
     */
    public function rectypeCounts(?array $ids = null): array
    {
        $counts = array();
        if($ids === null){
            foreach($this->database->fetchRows('SELECT rec_RecTypeID,count(*) FROM '.$this->schema->records()
                .' WHERE NOT rec_FlagTemporary GROUP BY rec_RecTypeID') as $row){
                $counts[intval($row[0])] = intval($row[1]);
            }
        }else{
            foreach(array_chunk(self::ids($ids), self::BATCH_SIZE) as $chunk){
                foreach($this->database->fetchRows('SELECT rec_RecTypeID,COUNT(*) FROM '.$this->schema->records()
                    .' WHERE rec_ID IN ('.self::placeholders($chunk).') GROUP BY rec_RecTypeID', $chunk) as $row){
                    $counts[intval($row[0])] = ($counts[intval($row[0])] ?? 0) + intval($row[1]);
                }
            }
        }
        ksort($counts, SORT_NUMERIC);
        return $counts;
    }

    // ------------------------------------------------------------ detail values

    /**
     * Detail values of records.
     *
     * Options:
     *   - order: "id" (dtl_ID, default) or "field" (record, field, dtl_ID);
     *   - owner: true adds rectype and owner of the record to each row;
     *   - extent: [west, south, east, north] only geo values in this box (antimeridian aware).
     *
     * @param array<int,int> $ids Record ids.
     * @param array<int,int>|null $fieldIds Only these fields (null = all).
     * @return array<int,array{id:int,record:int,field:int,value:?string,wkt:?string,file:?int,hidden:bool,rectype?:int,owner?:int}>
     */
    public function details(array $ids, ?array $fieldIds = null, array $options = array()): array
    {
        $ids = self::ids($ids);
        if($fieldIds !== null){ $fieldIds = self::ids($fieldIds); }
        if(empty($ids) || ($fieldIds !== null && empty($fieldIds))){ return array(); }
        $withOwner = !empty($options['owner']);
        $extent = $this->extentCondition($options['extent'] ?? null);
        $order = ($options['order'] ?? 'id') === 'field' ? 'd.dtl_RecID,d.dtl_DetailTypeID,d.dtl_ID' : 'd.dtl_ID';
        $rows = array();
        foreach(array_chunk($ids, self::BATCH_SIZE) as $chunk){
            $sql = 'SELECT d.dtl_ID,d.dtl_RecID,d.dtl_DetailTypeID,d.dtl_Value,ST_AsText(d.dtl_Geo),d.dtl_UploadedFileID,'
                .'d.dtl_HideFromPublic'.($withOwner ? ',r.rec_RecTypeID,r.rec_OwnerUGrpID' : '')
                .' FROM '.$this->schema->details().' d'
                .($withOwner ? ' JOIN '.$this->schema->records().' r ON r.rec_ID=d.dtl_RecID' : '')
                .' WHERE d.dtl_RecID IN ('.self::placeholders($chunk).')';
            $parameters = $chunk;
            if($fieldIds !== null){
                $sql .= ' AND d.dtl_DetailTypeID IN ('.self::placeholders($fieldIds).')';
                $parameters = array_merge($parameters, $fieldIds);
            }
            $sql .= $extent['sql'].' ORDER BY '.$order;
            foreach($this->database->fetchRows($sql, array_merge($parameters, $extent['values'])) as $row){
                $item = array(
                    'id' => intval($row[0]),
                    'record' => intval($row[1]),
                    'field' => intval($row[2]),
                    'value' => $row[3],
                    'wkt' => $row[4],
                    'file' => $row[5] === null ? null : intval($row[5]),
                    'hidden' => intval($row[6]) === 1
                );
                if($withOwner){
                    $item['rectype'] = intval($row[7]);
                    $item['owner'] = intval($row[8]);
                }
                $rows[] = $item;
            }
        }
        return $rows;
    }

    /**
     * Values of fields: record => field => [value, ...] in dtl_ID order (files as their id).
     *
     * @return array<int,array<int,array<int,string>>>
     */
    public function values(array $ids, ?array $fieldIds = null): array
    {
        $result = array();
        foreach($this->details($ids, $fieldIds) as $row){
            $result[$row['record']][$row['field']][] = $row['file'] !== null && $row['value'] === null
                ? (string)$row['file'] : (string)$row['value'];
        }
        return $result;
    }

    /**
     * Stored values of a record in the write format (RecordInput details):
     * dty => values; files as ulf_ID, geo as {geo:{type,wkt}}, hidden values as
     * {value, hidden:true}.
     *
     * @param array<int,int>|null $fieldIds Only these fields (null = all).
     * @return array<int,array<int,mixed>>
     */
    public function editValues(int $recordId, ?array $fieldIds = null): array
    {
        $result = array();
        foreach($this->details(array($recordId), $fieldIds) as $row){
            if($row['file'] !== null){
                $value = (string)$row['file'];
            }elseif($row['wkt'] !== null){
                $value = array('geo'=>array('type'=>(string)$row['value'], 'wkt'=>(string)$row['wkt']));
            }else{
                $value = $row['value'];
            }
            if($row['hidden']){
                $value = is_array($value) ? $value + array('hidden'=>true) : array('value'=>$value, 'hidden'=>true);
            }
            $result[$row['field']][] = $value;
        }
        return $result;
    }

    /**
     * Fields used by records: distinct field, record type, owner and hidden flag
     * (to check the field rules of the definitions).
     *
     * @return array<int,array{field:int,rectype:int,owner:int,hidden:bool}>
     */
    public function fieldUse(array $ids, array $fieldIds): array
    {
        $ids = self::ids($ids);
        $fieldIds = self::ids($fieldIds);
        if(empty($ids) || empty($fieldIds)){ return array(); }
        $result = array();
        foreach(array_chunk($ids, self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchRows(
                'SELECT DISTINCT d.dtl_DetailTypeID,r.rec_RecTypeID,r.rec_OwnerUGrpID,IFNULL(d.dtl_HideFromPublic,0) FROM '
                .$this->schema->details().' d JOIN '.$this->schema->records().' r ON r.rec_ID=d.dtl_RecID '
                .'WHERE d.dtl_RecID IN ('.self::placeholders($chunk).') AND d.dtl_DetailTypeID IN ('.self::placeholders($fieldIds).')',
                array_merge($chunk, $fieldIds)
            ) as $row){
                $result[] = array('field'=>intval($row[0]), 'rectype'=>intval($row[1]), 'owner'=>intval($row[2]),
                    'hidden'=>intval($row[3]) === 1);
            }
        }
        return $result;
    }

    /**
     * Uploaded files of records (file field values) in dtl_ID order.
     *
     * @return array<int,array<int,int>> record id => [ulf_ID, ...]
     */
    public function fileIds(array $ids): array
    {
        $result = array();
        foreach(array_chunk(self::ids($ids), self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchRows('SELECT dtl_RecID,dtl_UploadedFileID FROM '.$this->schema->details()
                .' WHERE dtl_RecID IN ('.self::placeholders($chunk).') AND dtl_UploadedFileID IS NOT NULL ORDER BY dtl_ID',
                $chunk) as $row){
                $result[intval($row[0])][] = intval($row[1]);
            }
        }
        return $result;
    }

    /**
     * Count, sum or average of fields over records (one statement, as legacy
     * getRecordsAggr): [[dty, avg|sum|count], ...]; dty 0 counts the records.
     *
     * @return array<int,mixed>|null The values in the order of the functions; null when nothing is asked.
     */
    public function aggregate(array $ids, array $functions): ?array
    {
        $ids = self::ids($ids);
        $select = array();
        $joins = array();
        foreach($functions as $index => list($fieldId, $type)){
            if(!in_array($type, array('avg', 'sum', 'count'), true)){
                throw new InvalidArgumentException('Unknown aggregate function '.$type);
            }
            $fieldId = intval($fieldId);
            if($fieldId > 0){
                $select[] = $type.'(d'.$index.'.dtl_Value)';
                $joins[] = 'JOIN '.$this->schema->details().' d'.$index.' ON rec_ID=d'.$index.'.dtl_RecID AND d'.$index
                    .'.dtl_DetailTypeID='.$fieldId;
            }else{
                $select[] = 'count(DISTINCT rec_ID)';
            }
        }
        if(empty($select) || empty($ids)){ return null; }
        $rows = $this->database->fetchRows('SELECT '.implode(',', $select).' FROM '.$this->schema->records().' '
            .implode(' ', $joins).' WHERE rec_ID IN ('.self::placeholders($ids).')', $ids);
        return $rows[0] ?? null;
    }

    // ------------------------------------------------------------ links

    /**
     * Pointer and relationship links of records (recLinks).
     *
     * Options:
     *   - side: "source" (default) - the ids are the stored source; "target" - the stored target;
     *   - kind: "pointer", "relationship" or "any" (default);
     *   - fields: pointer fields (rl_DetailTypeID IN); anyField: true - any pointer field (>0);
     *   - relationTypes: relationship types (rl_RelationTypeID IN);
     *   - fromTypes / otherTypes: record types of the given end / the other end;
     *   - distinct: true - distinct rows; order: "ends" (from, other) or "id" (rl_ID);
     *   - limit: at most this many rows in all.
     *
     * @param array<int,int> $ids
     * @return array<int,array{0:int,1:int,2:int,3:int,4:int,5:int,6:int}> [from, other, source, target,
     *         field, relation type, relationship record] (0 for none)
     */
    public function links(array $ids, array $options = array()): array
    {
        $ids = self::ids($ids);
        if(empty($ids)){ return array(); }
        $target = ($options['side'] ?? 'source') === 'target';
        $from = $target ? 'rl_TargetID' : 'rl_SourceID';
        $other = $target ? 'rl_SourceID' : 'rl_TargetID';
        $limit = isset($options['limit']) ? max(0, intval($options['limit'])) : null;
        list($baseConditions, $baseValues) = $this->linkConditions($options);
        $joins = '';
        $joinValues = array();
        foreach(array('fromTypes'=>array('rp', $from), 'otherTypes'=>array('rc', $other)) as $key => list($alias, $column)){
            if(!isset($options[$key]) || $options[$key] === null){ continue; }
            $types = self::ids((array)$options[$key]);
            if(empty($types)){ return array(); }
            $joins .= ' INNER JOIN '.$this->schema->records().' '.$alias.' ON '.$alias.'.rec_ID=rl.'.$column
                .' AND '.$alias.'.rec_RecTypeID IN ('.self::placeholders($types).')';
            $joinValues = array_merge($joinValues, $types);
        }
        $order = ($options['order'] ?? null) === 'ends' ? ' ORDER BY rl.'.$from.',rl.'.$other
            : (($options['order'] ?? null) === 'id' ? ' ORDER BY rl.rl_ID' : '');
        $rows = array();
        foreach(array_chunk($ids, self::BATCH_SIZE) as $chunk){
            if($limit !== null && count($rows) >= $limit){ break; }
            $conditions = array_merge(array('rl.'.$from.' IN ('.self::placeholders($chunk).')'), $baseConditions);
            $sql = 'SELECT '.(!empty($options['distinct']) ? 'DISTINCT ' : '').'rl.'.$from.',rl.'.$other
                .',rl.rl_SourceID,rl.rl_TargetID,COALESCE(rl.rl_DetailTypeID,0),COALESCE(rl.rl_RelationTypeID,0),'
                .'COALESCE(rl.rl_RelationID,0) FROM '.$this->schema->links().' rl'.$joins
                .' WHERE '.implode(' AND ', $conditions).$order
                .($limit !== null ? ' LIMIT '.($limit - count($rows)) : '');
            foreach($this->database->fetchRows($sql, array_merge($joinValues, $chunk, $baseValues)) as $row){
                $rows[] = array_map('intval', $row);
            }
        }
        return $rows;
    }

    /**
     * Distinct ids at one or both ends of links whose other end is one of $otherIds
     * (all links when null): candidate pre-filters of the search.
     *
     * Options: sides (["source"], ["target"] or both: the ends whose ids are returned),
     * kind, fields, anyField, relationTypes (as links()), limit (stop after this many ids;
     * the count is per statement, as the callers check it).
     *
     * @param array<int,int>|null $otherIds
     * @return array<int,int>
     */
    public function linkEnds(?array $otherIds, array $options = array()): array
    {
        $sides = $options['sides'] ?? array('source', 'target');
        $limit = isset($options['limit']) ? max(1, intval($options['limit'])) : null;
        list($conditions, $values) = $this->linkConditions($options);
        $chunks = $otherIds === null ? array(null) : array_chunk(self::ids($otherIds), self::BATCH_SIZE);
        $result = array();
        foreach($chunks as $chunk){
            $parts = array();
            $parameters = array();
            foreach($sides as $side){
                $column = $side === 'target' ? 'rl.rl_TargetID' : 'rl.rl_SourceID';
                $other = $side === 'target' ? 'rl.rl_SourceID' : 'rl.rl_TargetID';
                $where = $conditions;
                $parameters = array_merge($parameters, $values);
                if($chunk !== null){
                    $where[] = $other.' IN ('.self::placeholders($chunk).')';
                    $parameters = array_merge($parameters, $chunk);
                }
                $parts[] = array($column, empty($where) ? '1=1' : implode(' AND ', $where));
            }
            if(count($parts) === 1){
                $sql = 'SELECT DISTINCT '.$parts[0][0].' FROM '.$this->schema->links().' rl WHERE '.$parts[0][1];
            }else{
                $sql = 'SELECT DISTINCT id FROM ('.implode(' UNION ', array_map(function($part){
                    return 'SELECT '.$part[0].' AS id FROM '.$this->schema->links().' rl WHERE '.$part[1];
                }, $parts)).') AS link_ends';
            }
            if($limit !== null){
                $sql .= ' LIMIT ?';
                $parameters[] = $limit;
            }
            foreach($this->database->fetchColumn($sql, $parameters) as $id){
                $result[] = intval($id);
            }
            if($limit !== null && count($result) >= $limit){ break; }
        }
        return $result;
    }

    /**
     * New ids of merged (deleted) records (recForwarding), one step.
     *
     * @param array<int,int> $ids Old record ids.
     * @return array<int,array<int,int>> old id => [new id, ...]
     */
    public function forwarding(array $ids): array
    {
        $result = array();
        foreach(array_chunk(self::ids($ids), self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchRows('SELECT rfw_OldRecID,rfw_NewRecID FROM '.$this->schema->table('forwarding')
                .' WHERE rfw_OldRecID IN ('.self::placeholders($chunk).')', $chunk) as $row){
                $result[intval($row[0])][] = intval($row[1]);
            }
        }
        return $result;
    }

    // ------------------------------------------------------------ owners and access

    /**
     * Owner, visibility, edit groups and view groups of records (groups in rcp_ID order).
     *
     * @return array<int,array{owner:int,visibility:string,editGroups:array<int,int>,viewGroups:array<int,int>}>
     */
    public function owners(array $ids): array
    {
        $result = array();
        foreach($this->headers($ids, array('rec_ID', 'rec_OwnerUGrpID', 'rec_NonOwnerVisibility')) as $id => $row){
            $result[$id] = array('owner'=>intval($row['rec_OwnerUGrpID']), 'visibility'=>(string)$row['rec_NonOwnerVisibility'],
                'editGroups'=>array(), 'viewGroups'=>array());
        }
        foreach(array_chunk(array_keys($result), self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchRows('SELECT rcp_RecID,rcp_UGrpID,rcp_Level FROM '.$this->schema->permissions()
                .' WHERE rcp_RecID IN ('.self::placeholders($chunk).') ORDER BY rcp_ID', $chunk) as $row){
                $result[intval($row[0])][$row[2] === 'edit' ? 'editGroups' : 'viewGroups'][] = intval($row[1]);
            }
        }
        return $result;
    }

    /**
     * SQL condition for the records a user may view, with its placeholder values.
     * The one visibility rule (agreed 2026-10-13): not temporary; guests see public
     * and pending records; the database owner sees all; others also see records of
     * their groups, of themselves and of everyone (0), and viewable records without
     * view groups or with one of their groups.
     *
     * @param string $alias Alias of the records table ('' = unqualified columns).
     * @return array{0:string,1:array<int,int>}
     */
    public function accessSql(string $alias, RecordAccess $access): array
    {
        if($alias !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)){
            throw new InvalidArgumentException('Invalid table alias '.$alias);
        }
        $r = $alias === '' ? '' : $alias.'.';
        $where = array($r.'rec_FlagTemporary=0');
        if($access->userId < 1){
            $where[] = $r.'rec_NonOwnerVisibility IN ("public","pending")';
            return array(implode(' AND ', $where), array());
        }
        if($access->isDbOwner){
            return array(implode(' AND ', $where), array());
        }
        $groups = $access->viewerGroups();
        $placeholders = self::placeholders($groups);
        $permissions = $this->schema->permissions();
        $where[] = '('.$r.'rec_NonOwnerVisibility IN ("public","pending") OR '.$r.'rec_OwnerUGrpID IN ('.$placeholders.') OR ('
            .$r.'rec_NonOwnerVisibility="viewable" AND (NOT EXISTS (SELECT 1 FROM '.$permissions.' rp0 WHERE rp0.rcp_RecID='
            .$r.'rec_ID) OR EXISTS (SELECT 1 FROM '.$permissions.' rp WHERE rp.rcp_RecID='.$r.'rec_ID AND rp.rcp_UGrpID IN ('
            .$placeholders.')))))';
        return array(implode(' AND ', $where), array_merge($groups, $groups));
    }

    /**
     * Whether the user may see a detail value (agreed 2026-10-13, plan 15). Two levels: the
     * field (rst_NonOwnerVisibility of the record type, or dty_NonOwnerVisibility when the
     * field is not in the structure) and the value (dtl_HideFromPublic):
     *   - a forbidden field is shown to nobody;
     *   - the database owner and the record owners (the user, the user's groups and 0 =
     *     everyone, for a logged-in user) see every other value;
     *   - others: hidden field - nobody; viewable - logged-in users; public/pending - everybody,
     *     except values hidden from the public.
     * Applied to output (/records values, reports, export); the search does not check it
     * (decided 2026-10-13: keeps the search SQL simple and fast).
     *
     * @param array{field:int,rectype:int,owner:int,hidden:bool} $value Row of details(owner) or fieldUse().
     */
    public function valueVisible(array $value, RecordAccess $access): bool
    {
        $definitions = DefinitionLookup::shared($this->database);
        $structure = $definitions->structureRows($value['rectype'])[$value['field']] ?? null;
        if($structure !== null && $structure['requirement'] === 'forbidden'){ return false; }
        if($access->isDbOwner){ return true; }
        if($access->userId > 0 && in_array($value['owner'], $access->viewerGroups(), true)){ return true; }
        $visibility = strtolower($structure !== null && $structure['nonOwnerVisibility'] !== ''
            ? $structure['nonOwnerVisibility'] : (string)($definitions->field($value['field'])['nonOwnerVisibility'] ?? ''));
        if($visibility === 'hidden'){ return false; }
        if($visibility === 'viewable'){ return $access->userId > 0; }
        return !$value['hidden'];
    }

    /**
     * The records the user may view, in the order of the ids.
     *
     * @return array<int,int>
     */
    public function visibleIds(array $ids, RecordAccess $access): array
    {
        $ids = self::ids($ids);
        $visible = array();
        list($condition, $values) = $this->accessSql('', $access);
        foreach(array_chunk($ids, self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchColumn('SELECT rec_ID FROM '.$this->schema->records().' WHERE rec_ID IN ('
                .self::placeholders($chunk).') AND '.$condition, array_merge($chunk, $values)) as $id){
                $visible[intval($id)] = true;
            }
        }
        return array_values(array_filter($ids, static function($id) use ($visible){ return isset($visible[$id]); }));
    }

    /**
     * The records the user may change: owned by everyone (0), by the user or one of
     * the user's groups, or with one of them as edit group; all for an administrator.
     *
     * @return array<int,bool> id => true
     */
    public function editableIds(array $ids, RecordAccess $access): array
    {
        $ids = self::ids($ids);
        if(empty($ids) || $access->userId < 1){ return array(); }
        if($access->isAdmin){ return array_fill_keys($ids, true); }
        $members = $access->memberOf();
        $groups = self::placeholders($members);
        $editable = array();
        foreach(array_chunk($ids, self::BATCH_SIZE) as $chunk){
            $sql = 'SELECT rec_ID FROM '.$this->schema->records().' WHERE rec_ID IN ('.self::placeholders($chunk).') '
                .'AND (rec_OwnerUGrpID=0 OR rec_OwnerUGrpID IN ('.$groups.') OR EXISTS(SELECT 1 FROM '
                .$this->schema->permissions().' WHERE rcp_RecID=rec_ID AND rcp_Level="edit" AND rcp_UGrpID IN ('.$groups.')))';
            foreach($this->database->fetchColumn($sql, array_merge($chunk, $members, $members)) as $id){
                $editable[intval($id)] = true;
            }
        }
        return $editable;
    }

    // ------------------------------------------------------------ user data

    /**
     * Personal tags of a user: record id => [tag, ...] in tag order.
     *
     * @return array<int,array<int,string>>
     */
    public function tags(array $ids, int $userId): array
    {
        if($userId < 1){ return array(); }
        $tags = array();
        foreach(array_chunk(self::ids($ids), self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchRows('SELECT rtl_RecID,tag_Text FROM '.$this->schema->table('tagLinks')
                .' JOIN '.$this->schema->table('tags').' ON tag_ID=rtl_TagID WHERE tag_UGrpID=? AND rtl_RecID IN ('
                .self::placeholders($chunk).') ORDER BY rtl_RecID,rtl_Order', array_merge(array($userId), $chunk)) as $row){
                $tags[intval($row[0])][] = (string)$row[1];
            }
        }
        return $tags;
    }

    /**
     * Records bookmarked or tagged by anyone.
     *
     * @return array<int,bool> id => true
     */
    public function bookmarked(array $ids): array
    {
        $result = array();
        foreach(array_chunk(self::ids($ids), self::BATCH_SIZE) as $chunk){
            $placeholders = self::placeholders($chunk);
            foreach($this->database->fetchColumn('SELECT bkm_RecID FROM '.$this->schema->table('bookmarks')
                .' WHERE bkm_RecID IN ('.$placeholders.') UNION SELECT rtl_RecID FROM '.$this->schema->table('tagLinks')
                .' WHERE rtl_RecID IN ('.$placeholders.')', array_merge($chunk, $chunk)) as $id){
                $result[intval($id)] = true;
            }
        }
        return $result;
    }

    // ------------------------------------------------------------ helpers

    /** Conditions of a links read: kind, fields, relation types. */
    private function linkConditions(array $options): array
    {
        $conditions = array();
        $values = array();
        $kind = $options['kind'] ?? 'any';
        if($kind === 'pointer'){
            $conditions[] = 'rl_RelationID IS NULL';
        }elseif($kind === 'relationship'){
            $conditions[] = 'rl_RelationID IS NOT NULL';
        }
        if(isset($options['fields']) && $options['fields'] !== null){
            $fields = self::ids((array)$options['fields']);
            $conditions[] = empty($fields) ? '0=1' : 'rl_DetailTypeID IN ('.self::placeholders($fields).')';
            $values = array_merge($values, $fields);
        }elseif(!empty($options['anyField'])){
            $conditions[] = 'rl_DetailTypeID>0';
        }
        if(isset($options['relationTypes']) && $options['relationTypes'] !== null){
            $types = self::ids((array)$options['relationTypes']);
            $conditions[] = empty($types) ? '0=1' : 'rl_RelationTypeID IN ('.self::placeholders($types).')';
            $values = array_merge($values, $types);
        }
        // qualified by the links alias where one is used
        return array(array_map(static function($condition){
            return preg_replace('/\brl_/', 'rl.rl_', $condition);
        }, $conditions), $values);
    }

    /** MBR filter of geo values for one extent, or for both parts of one crossing the antimeridian. */
    private function extentCondition($extent): array
    {
        if(!is_array($extent) || count($extent) !== 4){
            return array('sql'=>'', 'values'=>array());
        }
        list($west, $south, $east, $north) = array_map('floatval', array_values($extent));
        if(!($west <= $east)){
            return array(
                'sql'=>' AND (MBRIntersects(d.dtl_Geo,ST_GeomFromText(?)) OR MBRIntersects(d.dtl_Geo,ST_GeomFromText(?))) ',
                'values'=>array(self::polygon($west, $south, 180.0, $north), self::polygon(-180.0, $south, $east, $north))
            );
        }
        return array('sql'=>' AND MBRIntersects(d.dtl_Geo,ST_GeomFromText(?)) ',
            'values'=>array(self::polygon($west, $south, $east, $north)));
    }

    private static function polygon(float $west, float $south, float $east, float $north): string
    {
        return 'POLYGON(('.$west.' '.$south.','.$east.' '.$south.','.$east.' '.$north.','
            .$west.' '.$north.','.$west.' '.$south.'))';
    }

    /** Header columns: rec_* names only, rec_ID first. */
    private function columns(array $columns): array
    {
        $columns = array_values(array_unique(array_merge(array('rec_ID'), $columns)));
        foreach($columns as $column){
            if(!preg_match('/^rec_[A-Za-z]+$/', (string)$column)){
                throw new InvalidArgumentException('Invalid record column '.$column);
            }
        }
        return $columns;
    }

    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /** Positive unique ids in their order. */
    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
    }
}
