<?php
/**
* PresentationRecordRepository.php - Read-only presentation record access
*
* Loads Query source, Map Document, Map Layer and linked source records directly
* from Records and recDetails without DbEntityBase inheritance.
*
* @project     Heurist academic knowledge management system
* @package     Records\Presentation
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Presentation;

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Files\FileRepository;
use Heurist\Records\Store\RecordAccess;
use Heurist\Records\Store\RecordRepository;
use Heurist\Runtime\RuntimeContext;
use Heurist\Runtime\SystemCode;

/** Provides the minimal read operations shared by presentation services. */
final class PresentationRecordRepository
{
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private SystemCode $codes;

    /** Initialise direct, read-only presentation record access. */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        SystemCode $codes
    ) {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->codes = $codes;
    }

    /** Load one visible record and all of its ordered detail values. */
    public function getPublicRecord(int $recordId, ?string $recordTypeCode = null): ?array
    {
        if($recordId < 1){ return null; }
        $records = RecordRepository::shared($this->database);
        $record = $records->headers(array($recordId), RecordRepository::HEADERS, RecordAccess::fromRuntime($this->runtime))[$recordId] ?? null;
        if($record === null){ return null; }
        if($recordTypeCode !== null){
            $requiredType = $this->codes->id($recordTypeCode);
            if($requiredType < 1 || intval($record['rec_RecTypeID']) !== $requiredType){ return null; }
        }
        $record['details'] = array();
        foreach($records->details(array($recordId)) as $row){
            $record['details'][$row['field']][] = $row['wkt'] ?? $row['value'] ?? ($row['file'] === null ? null : (string)$row['file']);
        }
        return $record;
    }

    /** The current user id followed by the ids of their groups. */
    public function userAndGroupIds(): array
    {
        $groups = $this->runtime->groupIds;
        $groups[] = $this->runtime->userId;
        return array_values(array_unique(array_filter(array_map('intval', $groups))));
    }

    /** Resolve a Query source. */
    public function getQuerySource(array $querySource): ?array
    {
        $sourceId = intval($this->value($querySource, 'DT_DATA_SOURCE'));
        return $sourceId < 1 ? $querySource : $this->getPublicRecord($sourceId, 'RT_QUERY_SOURCE');
    }

    /** Resolve a Map Layer data source or return the layer itself. */
    public function getDataSource(array $layer): ?array
    {
        $sourceId = intval($this->value($layer, 'DT_DATA_SOURCE'));
        return $sourceId < 1 ? $layer : $this->getPublicRecord($sourceId);
    }

    /** Return the first value for a symbolic or numeric detail type. */
    public function value(array $record, $field)
    {
        $values = $this->values($record, $field);
        return $values[0] ?? null;
    }

    /** Return all ordered values for a symbolic or numeric detail type. */
    public function values(array $record, $field): array
    {
        $fieldId = is_numeric($field) ? intval($field) : $this->codes->id((string)$field);
        return $fieldId > 0 ? array_values($record['details'][$fieldId] ?? array()) : array();
    }

    /** Code of a term (DefinitionLookup), null when the term does not exist. */
    public function getTermCode(int $termId): ?string
    {
        $term = DefinitionLookup::shared($this->database)->term($termId);
        return $term === null ? null : $term['code'];
    }

    /** Label of a term (DefinitionLookup), null when the term does not exist. */
    public function getTermLabel(int $termId): ?string
    {
        $term = DefinitionLookup::shared($this->database)->term($termId);
        return $term === null ? null : $term['term'];
    }

    /** Return the file metadata required by tile and image presentation. */
    public function getUploadedFile($fileId): ?array
    {
        if(!is_numeric($fileId) || intval($fileId) < 1){ return null; }
        $file = FileRepository::shared($this->database)->files(array(intval($fileId)))[intval($fileId)] ?? null;
        return $file === null ? null : array_intersect_key($file,
            array_flip(array('ulf_ID', 'ulf_ObfuscatedFileID', 'ulf_ExternalFileReference', 'ulf_MimeExt')));
    }

    /** Resolve a symbolic system code for source-type dispatch. */
    public function codeId(string $name): int
    {
        return $this->codes->id($name);
    }
}
