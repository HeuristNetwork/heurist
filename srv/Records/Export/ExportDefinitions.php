<?php
/**
* ExportDefinitions.php - Definitions an export needs: record types, fields, terms, groups
*
* Read once per export with plain queries (no definitions snapshot cache): names
* and concept codes of record types and fields, display names of fields per
* record type, term labels, codes, descriptions and concept codes, user and
* group names. Concept codes follow DefinitionSnapshotService::conceptCode().
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Export;

use Heurist\Database\DatabaseInterface;

/** Lazy lookups of database definitions for the export writers. */
final class ExportDefinitions
{
    private DatabaseInterface $database;
    private ?int $registeredId = null;
    /** @var array<int,array{name:string,concept:string}>|null */
    private ?array $rectypes = null;
    /** @var array<int,array{name:string,type:string,concept:string}>|null */
    private ?array $fields = null;
    /** @var array<int,array<int,array{name:string,order:int}>>|null rty => dty => structure */
    private ?array $structure = null;
    /** @var array<int,array{label:string,code:string,desc:string,concept:string,inverse:int}>|null */
    private ?array $terms = null;
    /** @var array<int,string>|null */
    private ?array $groups = null;

    /** @param DatabaseInterface $database Database of the export. */
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
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
     * Term definition.
     *
     * @return array{label:string,code:string,desc:string,concept:string,inverse:int}|null
     */
    public function term(int $id): ?array
    {
        if($this->terms === null){
            $this->terms = array();
            $rows = $this->database->fetchAll(
                'SELECT trm_ID,trm_Label,trm_Code,trm_Description,trm_OriginatingDBID,trm_IDInOriginatingDB,'
                .'trm_InverseTermID FROM defTerms'
            );
            foreach($rows as $row){
                $termId = intval($row['trm_ID']);
                $this->terms[$termId] = array(
                    'label' => (string)$row['trm_Label'],
                    'code' => trim((string)$row['trm_Code']),
                    'desc' => trim((string)$row['trm_Description']),
                    'concept' => $this->conceptCode($row['trm_OriginatingDBID'], $row['trm_IDInOriginatingDB'], $termId),
                    'inverse' => intval($row['trm_InverseTermID'])
                );
            }
        }
        return $this->terms[$id] ?? null;
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

    /** Concept code "<db>-<id>" (DefinitionSnapshotService::conceptCode). */
    public function conceptCode($originDb, $originId, int $localId): string
    {
        $registeredId = $this->registeredId();
        $originDb = intval($originDb);
        $originId = intval($originId);
        if($originDb > 0 && $originId > 0 && $originDb !== $registeredId){
            return $originDb.'-'.$originId;
        }
        return max(0, $registeredId).'-'.$localId;
    }

    /** @return array<int,array{name:string,concept:string}> */
    private function allRectypes(): array
    {
        if($this->rectypes === null){
            $this->rectypes = array();
            $rows = $this->database->fetchAll(
                'SELECT rty_ID,rty_Name,rty_OriginatingDBID,rty_IDInOriginatingDB FROM defRecTypes'
            );
            foreach($rows as $row){
                $id = intval($row['rty_ID']);
                $this->rectypes[$id] = array(
                    'name' => (string)$row['rty_Name'],
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
