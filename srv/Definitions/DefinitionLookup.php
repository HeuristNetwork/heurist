<?php
/**
* DefinitionLookup.php - The one reader of database definitions for srv code
*
* Plan 15: no other srv class queries the definition tables (defRecTypes,
* defRecTypeGroups, defDetailTypes, defDetailTypeGroups, defRecStructure,
* defTerms, defTermsLinks, defTranslations, defCalcFunctions, sysWorkflowRules),
* so a move of the definitions to other tables changes this class only.
* Settings of the database (registered id, version) are DatabaseSettings.
*
* Loading: record types and fields once (small tables); structure per record
* type; terms per id (all terms only for the snapshot). DefinitionLookup::shared()
* gives one instance (and one cache) per database connection.
*
* Concept codes (one rule, agreed 2026-10-13):
*   written  - "<origin db>-<origin id>" for a definition from another database,
*              else "<registered id>-<local id>" ("0-<local id>" when not registered);
*   read     - a value without "<db>-" prefix is a local id; "0-<id>" and
*              "<registered id>-<id>" are local ids; other codes are found by origin.
*
* Terms use the names of the report field tree and the Smarty term subfields
* (also HDbDefs.term() in the client):
*   internalid  local term id
*   term        label
*   code        standard code ('' when none)
*   conceptid   concept code "<db>-<id>"
*   desc        description ('' when none)
* plus parent (trm_ParentTermID), inverse (inverse term id) and domain.
*
* @project     Heurist academic knowledge management system
* @package     Definitions
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Definitions;

use Heurist\Database\DatabaseInterface;
use Heurist\Runtime\DatabaseSettings;
use WeakMap;

/** Lazy lookups of database definitions. */
final class DefinitionLookup
{
    /** Ids per IN (...) list. */
    private const CHUNK_SIZE = 500;

    /** Tables of the kinds of definitions with concept codes. */
    private const KINDS = array('rty' => 'defRecTypes', 'dty' => 'defDetailTypes', 'trm' => 'defTerms');

    /** @var WeakMap<DatabaseInterface,DefinitionLookup>|null */
    private static ?WeakMap $instances = null;

    /** Definition tables by key, for SQL text built by the search compiler (Compiler/SqlTables). */
    public const TABLES = array(
        'rectypes' => 'defRecTypes',
        'fieldTypes' => 'defDetailTypes',
        'structure' => 'defRecStructure',
        'terms' => 'defTerms',
        'termLinks' => 'defTermsLinks',
        'translations' => 'defTranslations'
    );

    private DatabaseInterface $database;
    /** @var array<int,array>|null id => record type */
    private ?array $rectypes = null;
    /** @var array<int,array>|null id => field */
    private ?array $fields = null;
    /** @var array<int,array<int,array{name:string,order:int}>>|null rty => dty => structure (all record types) */
    private ?array $structure = null;
    /** @var array<int,array<int,array>> rty => dty => edit structure row (structureRows) */
    private array $editStructure = array();
    /** @var array<int,array|null> id => term (null when unknown) */
    private array $terms = array();
    private bool $allTermsLoaded = false;
    private ?bool $hasTermLinks = null;
    /** @var array<string,array<int,int>> */
    private array $descendants = array();
    /** @var array<int,array<int,int>> dty => record types that have the field */
    private array $owners = array();
    /** @var array<string,int> "kind:code" => local id */
    private array $localIds = array();

    /** @param DatabaseInterface $database Database of the definitions. */
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /** The lookup of a connection (one instance and one cache per connection). */
    public static function shared(DatabaseInterface $database): self
    {
        if(self::$instances === null){ self::$instances = new WeakMap(); }
        if(!isset(self::$instances[$database])){
            self::$instances[$database] = new self($database);
        }
        return self::$instances[$database];
    }

    // ------------------------------------------------------------ concept codes

    /**
     * Concept code "<db>-<id>": the originating database and id when the definition
     * comes from another database, else this database's registered id (0 when not
     * registered) and the local id.
     */
    public static function conceptCodeFor(int $registeredId, $originDb, $originId, int $localId): string
    {
        $originDb = intval($originDb);
        $originId = intval($originId);
        if($originDb > 0 && $originId > 0 && $originDb !== $registeredId){
            return $originDb.'-'.$originId;
        }
        return max(0, $registeredId).'-'.$localId;
    }

    /** Registered id of the database (0 when not registered). */
    public function registeredId(): int
    {
        return DatabaseSettings::shared($this->database)->registeredId();
    }

    /** Concept code of a definition of this database (see conceptCodeFor). */
    public function conceptCode($originDb, $originId, int $localId): string
    {
        return self::conceptCodeFor($this->registeredId(), $originDb, $originId, $localId);
    }

    /**
     * Local id of a record type, field or term from a concept code or a local id.
     *
     * @param string $kind rty, dty or trm.
     * @param mixed $code "<origin db>-<origin id>", "<registered id>-<id>", "0-<id>" or "<id>".
     * @return int Local id, 0 when not found.
     */
    public function localId(string $kind, $code): int
    {
        if(!isset(self::KINDS[$kind])){ return 0; }
        $code = trim((string)$code);
        $key = $kind.':'.$code;
        if(isset($this->localIds[$key])){ return $this->localIds[$key]; }
        $id = 0;
        if(ctype_digit($code)){
            $id = $this->exists($kind, intval($code)) ? intval($code) : 0;
        }elseif(preg_match('/^(\d+)-(\d+)$/', $code, $parts)){
            $originDb = intval($parts[1]);
            $originId = intval($parts[2]);
            if($originDb === 0 || $originDb === $this->registeredId()){
                $id = $this->exists($kind, $originId) ? $originId : 0;
            }else{
                $table = self::KINDS[$kind];
                $id = intval($this->database->fetchValue(
                    'SELECT '.$kind.'_ID FROM '.$table.' WHERE '.$kind.'_OriginatingDBID=? AND '
                    .$kind.'_IDInOriginatingDB=? LIMIT 1', array($originDb, $originId), 0
                ));
            }
        }
        return $this->localIds[$key] = $id;
    }

    /** Whether a definition with this local id exists. */
    private function exists(string $kind, int $id): bool
    {
        if($id < 1){ return false; }
        if($kind === 'rty'){ return isset($this->allRectypes()[$id]); }
        if($kind === 'dty'){ return isset($this->allFields()[$id]); }
        return $this->term($id) !== null;
    }

    // ------------------------------------------------------------ record types

    /**
     * A record type.
     *
     * @return array{id:int,name:string,plural:string,description:string,group:int,showInLists:bool,
     *         titleMask:string,concept:string,originDb:int,originId:int}|null
     */
    public function rectype(int $id): ?array
    {
        return $this->allRectypes()[$id] ?? null;
    }

    /** Record type name ('' when unknown). */
    public function rectypeName(int $id): string
    {
        return $this->allRectypes()[$id]['name'] ?? '';
    }

    /** Record type concept code ('' when unknown). */
    public function rectypeConcept(int $id): string
    {
        return $this->allRectypes()[$id]['concept'] ?? '';
    }

    /** @return array<int,array> Every record type by id, in id order. */
    public function rectypes(): array
    {
        return $this->allRectypes();
    }

    /** Local id of the record type with this name (case-insensitive), 0 when none. */
    public function rectypeIdByName(string $name): int
    {
        return intval($this->database->fetchValue(
            'SELECT rty_ID FROM defRecTypes WHERE rty_Name=? LIMIT 1', array($name), 0
        ));
    }

    /**
     * Record types whose name or plural is the text (case-insensitive), else those
     * containing it.
     *
     * @return array<int,array{0:int,1:string,2:string}> [id, name, plural] rows.
     */
    public function findRectypes(string $text): array
    {
        $rows = $this->database->fetchRows(
            'SELECT rty_ID,rty_Name,rty_Plural FROM defRecTypes WHERE LOWER(rty_Name)=LOWER(?) OR LOWER(rty_Plural)=LOWER(?)',
            array($text, $text)
        );
        if(empty($rows)){
            $like = '%'.$text.'%';
            $rows = $this->database->fetchRows(
                'SELECT rty_ID,rty_Name,rty_Plural FROM defRecTypes WHERE rty_Name LIKE ? OR rty_Plural LIKE ? ORDER BY rty_ID',
                array($like, $like)
            );
        }
        return $rows;
    }

    /** @return array<string,array{name:string,order:int}> Record type groups by id, in display order. */
    public function rectypeGroups(): array
    {
        return $this->groupsOf('defRecTypeGroups', 'rtg');
    }

    // ------------------------------------------------------------ fields

    /**
     * Field definition.
     *
     * @return array{id:int,name:string,type:string,group:int,concept:string,vocabulary:array<int,int>,
     *         targetTypes:array<int,int>,originDb:int,originId:int}|null
     */
    public function field(int $id): ?array
    {
        return $this->allFields()[$id] ?? null;
    }

    /** Field type ('' when unknown). */
    public function fieldType(int $id): string
    {
        return $this->allFields()[$id]['type'] ?? '';
    }

    /** @return array<int,array> Every field by id, in id order. */
    public function fields(): array
    {
        return $this->allFields();
    }

    /**
     * Pointer constraint and vocabulary of a field by its own definition (for values
     * of fields that are not in the record structure).
     *
     * @return array{targetTypes:array<int,int>,vocabulary:array<int,int>}
     */
    public function fieldConstraints(int $fieldId): array
    {
        $field = $this->field($fieldId);
        return array(
            'targetTypes' => $field['targetTypes'] ?? array(),
            'vocabulary' => $field['vocabulary'] ?? array()
        );
    }

    /**
     * Fields whose name or display name in a structure is the text (case-insensitive),
     * else those containing it; only in the given record types when there are any.
     *
     * @param array<int,int> $rectypeIds
     * @return array<int,array{0:int,1:string,2:string}> [id, name, display name] rows in id order.
     */
    public function findFields(string $text, array $rectypeIds = array()): array
    {
        $rectypeIds = array_values(array_map('intval', $rectypeIds));
        $scope = empty($rectypeIds) ? ''
            : ' AND rst.rst_RecTypeID IN ('.implode(',', array_fill(0, count($rectypeIds), '?')).')';
        $select = 'SELECT DISTINCT dty.dty_ID,dty.dty_Name,rst.rst_DisplayName FROM defDetailTypes dty '
            .'INNER JOIN defRecStructure rst ON rst.rst_DetailTypeID=dty.dty_ID WHERE ';
        $rows = $this->database->fetchRows(
            $select.'(LOWER(dty.dty_Name)=LOWER(?) OR LOWER(rst.rst_DisplayName)=LOWER(?))'.$scope.' ORDER BY dty.dty_ID',
            array_merge(array($text, $text), $rectypeIds)
        );
        if(empty($rows)){
            $like = '%'.$text.'%';
            $rows = $this->database->fetchRows(
                $select.'(dty.dty_Name LIKE ? OR rst.rst_DisplayName LIKE ?)'.$scope.' ORDER BY dty.dty_ID',
                array_merge(array($like, $like), $rectypeIds)
            );
        }
        return $rows;
    }

    /** @return array<int,int> Record types that have a field in their structure. */
    public function fieldOwnerTypes(int $fieldId): array
    {
        if(!isset($this->owners[$fieldId])){
            $this->owners[$fieldId] = array_values(array_filter(array_map('intval', $this->database->fetchColumn(
                'SELECT DISTINCT rst_RecTypeID FROM defRecStructure WHERE rst_DetailTypeID=?', array($fieldId)
            ))));
        }
        return $this->owners[$fieldId];
    }

    /** @return array<string,array{name:string,order:int}> Field groups by id, in display order. */
    public function fieldGroups(): array
    {
        return $this->groupsOf('defDetailTypeGroups', 'dtg');
    }

    // ------------------------------------------------------------ structure

    /** Display name of a field in a record type, else the field name. */
    public function fieldName(int $rectypeId, int $fieldId): string
    {
        $this->loadStructure();
        return $this->structure[$rectypeId][$fieldId]['name'] ?? ($this->allFields()[$fieldId]['name'] ?? (string)$fieldId);
    }

    /**
     * Fields of a record type in form order (dty => display name).
     *
     * @return array<int,string>
     */
    public function structureFields(int $rectypeId): array
    {
        $this->loadStructure();
        $result = array();
        foreach($this->structure[$rectypeId] ?? array() as $fieldId => $entry){
            $result[$fieldId] = $entry['name'];
        }
        return $result;
    }

    /**
     * Fields of a record type in form order (separators included) with the display
     * name, or the field name when the record type has none: dty => name.
     *
     * @return array<int,string>
     */
    public function structureNames(int $rectypeId): array
    {
        $result = array();
        foreach($this->structureFields($rectypeId) as $fieldId => $name){
            $result[$fieldId] = $name !== '' ? $name : ($this->allFields()[$fieldId]['name'] ?? '');
        }
        return $result;
    }

    /**
     * Fields of a record type with what record editing needs, in form order, loaded
     * per record type on first use. Separators and relmarkers are included.
     *
     * @return array<int,array{id:int,name:string,type:string,requirement:string,max:int,
     *         default:string,targetTypes:array<int,int>,vocabulary:array<int,int>,termFilter:array<int,int>,
     *         order:int,help:string,calcFunctionId:int,nonOwnerVisibility:string}> dty => row
     *         (requirement: required|recommended|optional|forbidden; max 0 = unlimited;
     *         targetTypes: pointer constraint, empty = any; vocabulary: term tree root ids)
     */
    public function structureRows(int $rectypeId): array
    {
        if(!isset($this->editStructure[$rectypeId])){
            $rows = $this->database->fetchAll(
                'SELECT rst_DetailTypeID,rst_DisplayName,rst_RequirementType,rst_MaxValues,rst_DefaultValue,'
                .'rst_PtrFilteredIDs,rst_FilteredJsonTermIDTree,rst_DisplayOrder,rst_DisplayHelpText,'
                .'rst_CalcFunctionID,rst_NonOwnerVisibility,dty_Name,dty_Type,dty_PtrTargetRectypeIDs,dty_JsonTermIDTree '
                .'FROM defRecStructure JOIN defDetailTypes ON dty_ID=rst_DetailTypeID '
                .'WHERE rst_RecTypeID=? ORDER BY rst_DisplayOrder,rst_ID',
                array($rectypeId)
            );
            $result = array();
            foreach($rows as $row){
                $fieldId = intval($row['rst_DetailTypeID']);
                $targets = self::idList($row['rst_PtrFilteredIDs']);
                $result[$fieldId] = array(
                    'id' => $fieldId,
                    'name' => trim((string)$row['rst_DisplayName']) !== '' ? (string)$row['rst_DisplayName'] : (string)$row['dty_Name'],
                    'type' => (string)$row['dty_Type'],
                    'requirement' => strtolower(trim((string)$row['rst_RequirementType'])),
                    'max' => max(0, intval($row['rst_MaxValues'])),
                    'default' => (string)$row['rst_DefaultValue'],
                    'targetTypes' => !empty($targets) ? $targets : self::idList($row['dty_PtrTargetRectypeIDs']),
                    'vocabulary' => self::idList($row['dty_JsonTermIDTree']),
                    'termFilter' => self::idList($row['rst_FilteredJsonTermIDTree']),
                    'order' => intval($row['rst_DisplayOrder']),
                    'help' => (string)$row['rst_DisplayHelpText'],
                    'calcFunctionId' => intval($row['rst_CalcFunctionID']),
                    'nonOwnerVisibility' => (string)$row['rst_NonOwnerVisibility']
                );
            }
            $this->editStructure[$rectypeId] = $result;
        }
        return $this->editStructure[$rectypeId];
    }

    /**
     * Placement of the fields in every record type, separators left out (snapshot).
     *
     * @return array<int,array{rty:int,dty:int,name:string,order:int,req:string,max:int}>
     */
    public function structure(): array
    {
        $result = array();
        foreach($this->database->fetchAll(
            'SELECT rst_RecTypeID,rst_DetailTypeID,rst_DisplayName,rst_DisplayOrder,rst_RequirementType,rst_MaxValues '
            .'FROM defRecStructure '
            .'WHERE rst_DetailTypeID NOT IN (SELECT dty_ID FROM defDetailTypes WHERE dty_Type=?) '
            .'ORDER BY rst_RecTypeID,rst_DisplayOrder,rst_DetailTypeID',
            array('separator')
        ) as $row){
            $result[] = array(
                'rty' => intval($row['rst_RecTypeID']),
                'dty' => intval($row['rst_DetailTypeID']),
                'name' => (string)$row['rst_DisplayName'],
                'order' => intval($row['rst_DisplayOrder']),
                'req' => (string)$row['rst_RequirementType'],
                'max' => intval($row['rst_MaxValues'])
            );
        }
        return $result;
    }

    /**
     * Calculated fields of a record type and their formula (Smarty snippet).
     *
     * @return array<int,string> dty => formula
     */
    public function calculatedFields(int $rectypeId): array
    {
        $result = array();
        foreach($this->database->fetchRows(
            'SELECT rst_DetailTypeID,cfn_FunctionSpecification FROM defRecStructure '
            .'JOIN defCalcFunctions ON cfn_ID=rst_CalcFunctionID WHERE rst_RecTypeID=?', array($rectypeId)
        ) as $row){
            $result[intval($row[0])] = (string)$row[1];
        }
        return $result;
    }

    /**
     * Workflow rule of a stage of a record type (sysWorkflowRules), or null.
     *
     * @return array{restrictedTo:string,setOwnership:?int,setVisibility:string,sendEmail:string,
     *         emailList:string,recordEmailField:int,emailText:string}|null
     */
    public function workflowRule(int $rectypeId, int $stage): ?array
    {
        $rows = $this->database->fetchAll(
            'SELECT swf_StageRestrictedTo,swf_SetOwnership,swf_SetVisibility,swf_SendEmail,swf_EmailList,'
            .'swf_RecEmailField,swf_EmailText FROM sysWorkflowRules WHERE swf_RecTypeID=? AND swf_Stage=?',
            array($rectypeId, $stage)
        );
        $row = $rows[0] ?? null;
        if($row === null){ return null; }
        return array(
            'restrictedTo' => (string)$row['swf_StageRestrictedTo'],
            'setOwnership' => ($row['swf_SetOwnership'] === null || $row['swf_SetOwnership'] === '') ? null : intval($row['swf_SetOwnership']),
            'setVisibility' => (string)$row['swf_SetVisibility'],
            'sendEmail' => (string)$row['swf_SendEmail'],
            'emailList' => (string)$row['swf_EmailList'],
            'recordEmailField' => intval($row['swf_RecEmailField']),
            'emailText' => (string)$row['swf_EmailText']
        );
    }

    // ------------------------------------------------------------ terms

    /**
     * Term definition (names of the field tree and Smarty term subfields), loaded by id.
     *
     * @return array{internalid:int,term:string,code:string,conceptid:string,desc:string,parent:int,inverse:int,domain:string}|null
     */
    public function term(int $id): ?array
    {
        if($id < 1){ return null; }
        if(!array_key_exists($id, $this->terms)){
            $this->terms($id > 0 ? array($id) : array());
        }
        return $this->terms[$id] ?? null;
    }

    /**
     * Terms by id (one query for the ids not loaded yet).
     *
     * @param array<int,int> $ids
     * @return array<int,array> id => term, unknown ids left out
     */
    public function terms(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        $missing = array_values(array_filter($ids, function($id){ return !array_key_exists($id, $this->terms); }));
        foreach(array_chunk($missing, self::CHUNK_SIZE) as $chunk){
            foreach($chunk as $id){ $this->terms[$id] = null; }
            $this->addTerms($this->database->fetchAll(
                'SELECT trm_ID,trm_Label,trm_Code,trm_Description,trm_Domain,trm_OriginatingDBID,'
                .'trm_IDInOriginatingDB,trm_InverseTermID,trm_ParentTermID FROM defTerms WHERE trm_ID IN ('
                .implode(',', array_fill(0, count($chunk), '?')).')', $chunk
            ));
        }
        $result = array();
        foreach($ids as $id){
            if(isset($this->terms[$id])){ $result[$id] = $this->terms[$id]; }
        }
        return $result;
    }

    /**
     * Every term, by id (snapshot).
     *
     * @return array<int,array>
     */
    public function allTerms(): array
    {
        if(!$this->allTermsLoaded){
            $this->addTerms($this->database->fetchAll(
                'SELECT trm_ID,trm_Label,trm_Code,trm_Description,trm_Domain,trm_OriginatingDBID,'
                .'trm_IDInOriginatingDB,trm_InverseTermID,trm_ParentTermID FROM defTerms ORDER BY trm_ID'
            ));
            $this->allTermsLoaded = true;
        }
        $result = array();
        foreach($this->terms as $id => $term){
            if($term !== null){ $result[$id] = $term; }
        }
        ksort($result);
        return $result;
    }

    /**
     * Term label with its parent terms, without the vocabulary: "Parent.Child"
     * (legacy DbsTerms::getTermLabel with hierarchy).
     */
    public function termHierarchyLabel(int $id): string
    {
        $term = $this->term($id);
        if($term === null){ return ''; }
        $labels = array($term['term']);
        $seen = array($id => true);
        $parentId = $term['parent'];
        while($parentId > 0 && !isset($seen[$parentId])){
            $parent = $this->term($parentId);
            if($parent === null || $parent['parent'] < 1){ break; } // the vocabulary itself is left out
            array_unshift($labels, $parent['term']);
            $seen[$parentId] = true;
            $parentId = $parent['parent'];
        }
        return implode('.', $labels);
    }

    /**
     * Terms whose label or code is the text (case-insensitive), in id order.
     *
     * @return array<int,array{0:int,1:string,2:string}> [id, label, code] rows.
     */
    public function termsByLabelOrCode(string $text): array
    {
        return $this->database->fetchRows(
            'SELECT trm_ID,trm_Label,trm_Code FROM defTerms WHERE LOWER(trm_Label)=LOWER(?) OR LOWER(trm_Code)=LOWER(?) ORDER BY trm_ID',
            array($text, $text)
        );
    }

    /**
     * Terms with their descendants through the term links (defTermsLinks; the parent
     * column trm_ParentTermID when the database has no term links).
     *
     * @param array<int,int> $ids
     * @return array<int,int> The given ids and all their descendants.
     */
    public function termDescendants(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        sort($ids);
        $key = implode(',', $ids);
        if(isset($this->descendants[$key])){ return $this->descendants[$key]; }
        $links = $this->hasTermLinks();
        $all = array_fill_keys($ids, true);
        $frontier = $ids;
        while(!empty($frontier)){
            $next = array();
            foreach(array_chunk($frontier, self::CHUNK_SIZE) as $chunk){
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $sql = $links
                    ? 'SELECT DISTINCT trl_TermID FROM defTermsLinks WHERE trl_ParentID IN ('.$placeholders.')'
                    : 'SELECT trm_ID FROM defTerms WHERE trm_ParentTermID IN ('.$placeholders.')';
                foreach($this->database->fetchColumn($sql, $chunk) as $value){
                    $id = intval($value);
                    if($id > 0 && !isset($all[$id])){ $all[$id] = true; $next[] = $id; }
                }
            }
            $frontier = $next;
        }
        return $this->descendants[$key] = array_map('intval', array_keys($all));
    }

    /**
     * Inverse terms of relation types: a term's trm_InverseTermID or any term naming it
     * as inverse; a term with no inverse is its own inverse (undirected).
     *
     * @param array<int,int> $ids
     * @return array<int,int>
     */
    public function termInverse(array $ids): array
    {
        $inverse = array();
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        foreach(array_chunk($ids, self::CHUNK_SIZE) as $chunk){
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $found = array_fill_keys($chunk, false);
            foreach($this->database->fetchRows(
                'SELECT trm_ID, trm_InverseTermID FROM defTerms'
                .' WHERE trm_ID IN ('.$placeholders.') OR trm_InverseTermID IN ('.$placeholders.')',
                array_merge($chunk, $chunk)
            ) as $row){
                list($termId, $inverseId) = array_map('intval', array_values($row));
                if(isset($found[$termId]) && $inverseId > 0){ $inverse[] = $inverseId; $found[$termId] = true; }
                if(isset($found[$inverseId]) && $termId > 0){ $inverse[] = $termId; $found[$inverseId] = true; }
            }
            foreach($found as $termId => $hasInverse){
                if(!$hasInverse){ $inverse[] = intval($termId); }
            }
        }
        return $inverse;
    }

    /**
     * Parent → child pairs of the term tree (snapshot): term links, or the parent
     * column when the database has no term links.
     *
     * @return array<int,array{parent:int,term:int}>
     */
    public function termLinks(): array
    {
        $rows = $this->hasTermLinks()
            ? $this->database->fetchRows('SELECT trl_ParentID,trl_TermID FROM defTermsLinks ORDER BY trl_ParentID,trl_TermID')
            : $this->database->fetchRows(
                'SELECT trm_ParentTermID,trm_ID FROM defTerms '
                .'WHERE trm_ParentTermID IS NOT NULL AND trm_ParentTermID>0 ORDER BY trm_ParentTermID,trm_ID'
            );
        $result = array();
        foreach($rows as $row){
            $parent = intval($row[0]);
            $term = intval($row[1]);
            if($parent > 0 && $term > 0){ $result[] = array('parent' => $parent, 'term' => $term); }
        }
        return $result;
    }

    // ------------------------------------------------------------ translations

    /** @return array<int,string> Language codes that have translations. */
    public function languages(): array
    {
        return array_values(array_filter(array_map('strval', $this->database->fetchColumn(
            'SELECT DISTINCT trn_LanguageCode FROM defTranslations ORDER BY trn_LanguageCode'
        )), static function($code){ return $code !== ''; }));
    }

    /**
     * Translations of one text of several definitions in a language.
     *
     * @param string $source Translated column: trm_Label, trm_Description, rty_Name, dty_Name,
     *        ulf_Caption, ulf_Description.
     * @param array<int,int> $ids Ids of the definitions.
     * @param string $language 3-letter language code.
     * @return array<string,string> id => translation (ids without translation left out)
     */
    public function translations(string $source, array $ids, string $language): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if(empty($ids)){ return array(); }
        $result = array();
        foreach($this->database->fetchRows(
            'SELECT trn_Code,trn_Translation FROM defTranslations WHERE trn_Source=? '
            .'AND trn_LanguageCode=? AND trn_Code IN ('.implode(',', array_fill(0, count($ids), '?')).')',
            array_merge(array($source, $language), $ids)
        ) as $row){
            $result[(string)$row[0]] = (string)$row[1];
        }
        return $result;
    }

    // ------------------------------------------------------------ loading

    /** @return array<int,array> */
    private function allRectypes(): array
    {
        if($this->rectypes === null){
            $this->rectypes = array();
            foreach($this->database->fetchAll(
                'SELECT rty_ID,rty_Name,rty_Plural,rty_Description,rty_RecTypeGroupID,rty_ShowInLists,rty_TitleMask,'
                .'rty_OriginatingDBID,rty_IDInOriginatingDB FROM defRecTypes ORDER BY rty_ID'
            ) as $row){
                $id = intval($row['rty_ID']);
                $this->rectypes[$id] = array(
                    'id' => $id,
                    'name' => (string)$row['rty_Name'],
                    'plural' => (string)$row['rty_Plural'],
                    'description' => (string)$row['rty_Description'],
                    'group' => intval($row['rty_RecTypeGroupID']),
                    'showInLists' => intval($row['rty_ShowInLists']) === 1,
                    'titleMask' => (string)$row['rty_TitleMask'],
                    'concept' => $this->conceptCode($row['rty_OriginatingDBID'], $row['rty_IDInOriginatingDB'], $id),
                    'originDb' => intval($row['rty_OriginatingDBID']),
                    'originId' => intval($row['rty_IDInOriginatingDB'])
                );
            }
        }
        return $this->rectypes;
    }

    /** @return array<int,array> */
    private function allFields(): array
    {
        if($this->fields === null){
            $this->fields = array();
            foreach($this->database->fetchAll(
                'SELECT dty_ID,dty_Name,dty_Type,dty_DetailTypeGroupID,dty_JsonTermIDTree,dty_PtrTargetRectypeIDs,'
                .'dty_OriginatingDBID,dty_IDInOriginatingDB,dty_NonOwnerVisibility FROM defDetailTypes ORDER BY dty_ID'
            ) as $row){
                $id = intval($row['dty_ID']);
                $this->fields[$id] = array(
                    'id' => $id,
                    'name' => (string)$row['dty_Name'],
                    'type' => (string)$row['dty_Type'],
                    'group' => intval($row['dty_DetailTypeGroupID']),
                    'concept' => $this->conceptCode($row['dty_OriginatingDBID'], $row['dty_IDInOriginatingDB'], $id),
                    'vocabulary' => self::idList($row['dty_JsonTermIDTree']),
                    'targetTypes' => self::idList($row['dty_PtrTargetRectypeIDs']),
                    'originDb' => intval($row['dty_OriginatingDBID']),
                    'originId' => intval($row['dty_IDInOriginatingDB']),
                    'nonOwnerVisibility' => strtolower((string)$row['dty_NonOwnerVisibility'])
                );
            }
        }
        return $this->fields;
    }

    private function loadStructure(): void
    {
        if($this->structure !== null){ return; }
        $this->structure = array();
        foreach($this->database->fetchAll(
            'SELECT rst_RecTypeID,rst_DetailTypeID,rst_DisplayName,rst_DisplayOrder FROM defRecStructure '
            .'ORDER BY rst_RecTypeID,rst_DisplayOrder,rst_ID'
        ) as $row){
            $this->structure[intval($row['rst_RecTypeID'])][intval($row['rst_DetailTypeID'])] = array(
                'name' => (string)$row['rst_DisplayName'],
                'order' => intval($row['rst_DisplayOrder'])
            );
        }
    }

    /** Add term rows to the cache. */
    private function addTerms(array $rows): void
    {
        foreach($rows as $row){
            $termId = intval($row['trm_ID']);
            $this->terms[$termId] = array(
                'internalid' => $termId,
                'term' => (string)$row['trm_Label'],
                'code' => trim((string)$row['trm_Code']),
                'conceptid' => $this->conceptCode($row['trm_OriginatingDBID'], $row['trm_IDInOriginatingDB'], $termId),
                'desc' => trim((string)$row['trm_Description']),
                'parent' => intval($row['trm_ParentTermID']),
                'inverse' => intval($row['trm_InverseTermID']),
                'domain' => strtolower(trim((string)$row['trm_Domain']))
            );
        }
    }

    /** Whether the database has term links (else the parent column is the term tree). */
    private function hasTermLinks(): bool
    {
        if($this->hasTermLinks === null){
            $this->hasTermLinks = $this->database->fetchValue('SELECT 1 FROM defTermsLinks LIMIT 1') !== null;
        }
        return $this->hasTermLinks;
    }

    /** @return array<string,array{name:string,order:int}> Groups of a group table by id. */
    private function groupsOf(string $table, string $prefix): array
    {
        $result = array();
        foreach($this->database->fetchAll(
            'SELECT '.$prefix.'_ID AS id,'.$prefix.'_Name AS name,'.$prefix.'_Order AS ord '
            .'FROM '.$table.' ORDER BY '.$prefix.'_Order,'.$prefix.'_Name'
        ) as $row){
            $result[(string)intval($row['id'])] = array('name' => (string)$row['name'], 'order' => intval($row['ord']));
        }
        return $result;
    }

    /** Positive integers of a CSV list or JSON term tree string. */
    private static function idList($value): array
    {
        if($value === null || $value === ''){ return array(); }
        preg_match_all('/\d+/', (string)$value, $matches);
        return array_values(array_unique(array_filter(array_map('intval', $matches[0]))));
    }
}
