<?php
/**
* DefinitionLookup.php - One loader of database definitions for srv code
*
* Record types, fields, structure display names, terms, user/group names, read
* once per instance with plain queries. Used by the definitions snapshot (client),
* the srv Smarty engine (ReportDefinitions) and record export. Holds the one
* concept-code rule of srv (conceptCodeFor), also used by RecordDataService.
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

/** Lazy lookups of database definitions. */
final class DefinitionLookup
{
    private DatabaseInterface $database;
    private ?int $registeredId = null;
    /** @var array<int,array{name:string,plural:string,concept:string}>|null */
    private ?array $rectypes = null;
    /** @var array<int,array{name:string,type:string,concept:string}>|null */
    private ?array $fields = null;
    /** @var array<int,array<int,array{name:string,order:int}>>|null rty => dty => structure */
    private ?array $structure = null;
    /** @var array<int,array{internalid:int,term:string,code:string,conceptid:string,desc:string,parent:int,inverse:int,domain:string}>|null */
    private ?array $terms = null;
    /** @var array<int,string>|null */
    private ?array $groups = null;

    /** @param DatabaseInterface $database Database of the definitions. */
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

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
        if($this->registeredId === null){
            $this->registeredId = intval($this->database->fetchValue(
                'SELECT sys_dbRegisteredID FROM sysIdentification LIMIT 1', array(), 0
            ));
        }
        return $this->registeredId;
    }

    /** Concept code of a definition of this database (see conceptCodeFor). */
    public function conceptCode($originDb, $originId, int $localId): string
    {
        return self::conceptCodeFor($this->registeredId(), $originDb, $originId, $localId);
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

    /**
     * Field definition.
     *
     * @return array{name:string,type:string,concept:string}|null
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
     * Term definition (names of the field tree and Smarty term subfields).
     *
     * @return array{internalid:int,term:string,code:string,conceptid:string,desc:string,parent:int,inverse:int,domain:string}|null
     */
    public function term(int $id): ?array
    {
        return $this->allTerms()[$id] ?? null;
    }

    /**
     * Every term, by id.
     *
     * @return array<int,array{internalid:int,term:string,code:string,conceptid:string,desc:string,parent:int,inverse:int,domain:string}>
     */
    public function allTerms(): array
    {
        if($this->terms === null){
            $this->terms = array();
            $rows = $this->database->fetchAll(
                'SELECT trm_ID,trm_Label,trm_Code,trm_Description,trm_Domain,trm_OriginatingDBID,'
                .'trm_IDInOriginatingDB,trm_InverseTermID,trm_ParentTermID FROM defTerms ORDER BY trm_ID'
            );
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
        return $this->terms;
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

    /** Name of a user or workgroup ('' when unknown). */
    public function groupName(int $id): string
    {
        if($this->groups === null){
            $this->groups = array();
            foreach($this->database->fetchAll('SELECT ugr_ID,ugr_Name FROM sysUGrps') as $row){
                $this->groups[intval($row['ugr_ID'])] = (string)$row['ugr_Name'];
            }
        }
        return $this->groups[$id] ?? '';
    }

    /** @return array<int,array{name:string,plural:string,concept:string}> */
    private function allRectypes(): array
    {
        if($this->rectypes === null){
            $this->rectypes = array();
            $rows = $this->database->fetchAll(
                'SELECT rty_ID,rty_Name,rty_Plural,rty_OriginatingDBID,rty_IDInOriginatingDB FROM defRecTypes'
            );
            foreach($rows as $row){
                $id = intval($row['rty_ID']);
                $this->rectypes[$id] = array(
                    'name' => (string)$row['rty_Name'],
                    'plural' => (string)$row['rty_Plural'],
                    'concept' => $this->conceptCode($row['rty_OriginatingDBID'], $row['rty_IDInOriginatingDB'], $id)
                );
            }
        }
        return $this->rectypes;
    }

    /** @return array<int,array{name:string,type:string,concept:string}> */
    private function allFields(): array
    {
        if($this->fields === null){
            $this->fields = array();
            $rows = $this->database->fetchAll(
                'SELECT dty_ID,dty_Name,dty_Type,dty_OriginatingDBID,dty_IDInOriginatingDB FROM defDetailTypes'
            );
            foreach($rows as $row){
                $id = intval($row['dty_ID']);
                $this->fields[$id] = array(
                    'name' => (string)$row['dty_Name'],
                    'type' => (string)$row['dty_Type'],
                    'concept' => $this->conceptCode($row['dty_OriginatingDBID'], $row['dty_IDInOriginatingDB'], $id)
                );
            }
        }
        return $this->fields;
    }

    private function loadStructure(): void
    {
        if($this->structure !== null){ return; }
        $this->structure = array();
        $rows = $this->database->fetchAll(
            'SELECT rst_RecTypeID,rst_DetailTypeID,rst_DisplayName,rst_DisplayOrder FROM defRecStructure '
            .'ORDER BY rst_RecTypeID,rst_DisplayOrder,rst_ID'
        );
        foreach($rows as $row){
            $this->structure[intval($row['rst_RecTypeID'])][intval($row['rst_DetailTypeID'])] = array(
                'name' => (string)$row['rst_DisplayName'],
                'order' => intval($row['rst_DisplayOrder'])
            );
        }
    }
}
