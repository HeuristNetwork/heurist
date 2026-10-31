<?php
/**
* RecordDataService.php - Batched retrieval and formatting of record details
*
* Retrieves headers and native or linked detail values for record IDs already
* selected by the search and expansion services. It does not execute searches
* or traverse record links. Records are read through RecordRepository, files
* through FileRepository, definitions through DefinitionLookup (plan 15).
*
* @project     Heurist academic knowledge management system
* @package     Records\Data
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

namespace Heurist\Records\Data;

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Files\FileRepository;
use Heurist\Records\Store\RecordAccess;
use Heurist\Records\Store\RecordRepository;
use Heurist\Records\Store\SplitValues;
use Heurist\Runtime\RuntimeContext;
use Heurist\Runtime\SystemCode;
use Heurist\Users\UserGroupRepository;

/** Loads requested values without knowing how their owner records were found. */
final class RecordDataService
{
    private const BATCH_SIZE = 500;

    private RecordRepository $records;
    private ?RecordAccess $access = null;
    private RuntimeContext $runtime;
    private DatabaseInterface $database;
    /** Names, types and concept codes of definitions (the one srv loader), created on first use. */
    private ?DefinitionLookup $definitions = null;

    /** Initialise batched record retrieval with the shared database adapter. */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        ?RecordRepository $records = null
    )
    {
        $this->runtime = $runtime;
        $this->database = $database;
        $this->records = $records ?? RecordRepository::shared($database);
    }

    /** Definition lookup shared by field metadata, concept codes and `defs`. */
    public function definitions(): DefinitionLookup
    {
        if($this->definitions === null){
            $this->definitions = DefinitionLookup::shared($this->database);
        }
        return $this->definitions;
    }

    /** Build standard record objects for the ordered top-query page. */
    public function loadRecords(
        array $topIds,
        array $headers,
        array $nativeFields,
        array $options = array()
    ): array
    {
        $topIds = $this->ids($topIds);
        $records = array();
        foreach($topIds as $id){
            $records[$id] = array('rec_ID'=>(string)$id, 'details'=>array());
        }
        if(empty($records)){ return array(); }

        $columns = array_values(array_unique(array_merge(
            array('rec_ID','rec_RecTypeID','rec_Title'), $headers
        )));
        foreach($this->records->headers($topIds, $columns) as $id=>$row){
            foreach($columns as $column){
                if($column === 'rec_ID'){ continue; }
                $records[$id][$column] = in_array($column, array(
                    'rec_RecTypeID','rec_OwnerUGrpID','rec_AddedByUGrpID'
                ), true) && $row[$column] !== null
                    ? (string)$row[$column]
                    : $row[$column];
            }
        }

        $this->attachVirtualHeaders($records, $topIds, $options['virtuals'] ?? array());

        if(!empty($options['allDetails'])){
            // fields=_all - every populated detail value for these records,
            // regardless of type, keyed by the raw detail-type id (there is
            // no caller-supplied field list to key by).
            // Records the user may change also get their edit/view groups and
            // {value, hidden:true} for values hidden from the public (plan 14).
            $editable = $this->records->editableIds($topIds, RecordAccess::fromRuntime($this->runtime));
            $this->attachEditHeaders($records, array_keys($editable));
            $values = $this->loadFieldValues($topIds, null, $options + array('markHidden'=>$editable));
            foreach($topIds as $id){
                foreach($values[$id] ?? array() as $dtyId=>$fieldValues){
                    foreach($fieldValues as $value){
                        $records[$id]['details'][(string)$dtyId][] = $value;
                    }
                }
            }
            $this->joinPageContent($records);
            return array_values($records);
        }

        $fieldIds = array_values(array_unique(array_map(static function($field){
            return intval($field['fieldId']);
        }, $nativeFields)));
        if(!empty($fieldIds)){
            $values = $this->loadFieldValues($topIds, $fieldIds, $options);
            foreach($nativeFields as $field){
                foreach($topIds as $id){
                    foreach($values[$id][$field['fieldId']] ?? array() as $value){
                        $records[$id]['details'][$field['key']][] = $value;
                    }
                }
            }
        }
        $this->joinPageContent($records);
        return array_values($records);
    }

    /**
     * rec_EditGroups, rec_NonOwnerVisibilityGroups and rec_FlagTemporary of the
     * records the user may change (the write format of plan 14 needs them).
     *
     * @param array<int,array> $records Records by id.
     * @param array<int,int> $ids Editable record ids.
     */
    private function attachEditHeaders(array &$records, array $ids): void
    {
        if(empty($ids)){ return; }
        foreach($ids as $id){
            $records[$id]['rec_EditGroups'] = array();
            $records[$id]['rec_NonOwnerVisibilityGroups'] = array();
        }
        foreach($this->records->owners($ids) as $id=>$owners){
            $records[$id]['rec_EditGroups'] = array_map('strval', $owners['editGroups']);
            $records[$id]['rec_NonOwnerVisibilityGroups'] = array_map('strval', $owners['viewGroups']);
        }
        foreach($this->records->headers($ids, array('rec_FlagTemporary')) as $id=>$row){
            $records[$id]['rec_FlagTemporary'] = (string)$row['rec_FlagTemporary'];
        }
    }

    /**
     * Website page content (RT_CMS_PAGE / DT_EXTENDED_DESCRIPTION) and the page translations of a
     * v4 website (DT_CMS_PAGE_TRANSLATIONS, plan 27a) are saved in parts when they are long; give
     * them as one value (per language), as legacy recordSearch does (SplitValues).
     */
    private function joinPageContent(array &$records): void
    {
        $codes = new SystemCode($this->definitions());
        $page = $codes->id('RT_CMS_PAGE');
        $content = $codes->id('DT_EXTENDED_DESCRIPTION');
        $translations = $codes->id('DT_CMS_PAGE_TRANSLATIONS');
        foreach($records as &$record){
            if(!is_array($record['details'] ?? null)){ continue; }
            $record['details'] = SplitValues::joinDetails($record['details'], intval($record['rec_RecTypeID'] ?? 0),
                $page, $content, $translations);
        }
        unset($record);
    }

    /** Resolve presentation-only fields in one query per page, never per record. */
    private function attachVirtualHeaders(array &$records, array $topIds, array $virtuals): void
    {
        if(empty($virtuals)){ return; }
        $wanted = array_fill_keys($virtuals, true);
        $index = array();
        foreach($records as $id=>$unused){ $index[intval($id)] = $id; }
        $owners = array();
        if(isset($wanted['rec_OwnerName'])){
            foreach($this->records->headers($topIds, array('rec_OwnerUGrpID')) as $id=>$row){
                $owners[$id] = intval($row['rec_OwnerUGrpID']);
            }
            $names = UserGroupRepository::shared($this->database)->names(array_values($owners));
        }
        $bookmarked = isset($wanted['rec_Bookmarked']) ? $this->records->bookmarked($topIds) : array();
        $thumbnails = isset($wanted['rec_ThumbnailURL']) ? $this->thumbnailFiles($topIds) : array();
        foreach($topIds as $id){
            if(!isset($index[$id])){ continue; }
            $key = $index[$id];
            if(isset($wanted['rec_OwnerName'])){
                $records[$key]['rec_OwnerName'] = isset($owners[$id]) ? ($names[$owners[$id]] ?? null) : null;
            }
            if(isset($wanted['rec_Bookmarked'])){ $records[$key]['rec_Bookmarked'] = isset($bookmarked[$id]); }
            if(isset($wanted['rec_ThumbnailURL'])){
                $fileId = preg_replace('/[^a-z0-9]/i', '', (string)($thumbnails[$id] ?? ''));
                $records[$key]['rec_ThumbnailURL'] = $fileId === '' ? null
                    : HEURIST_BASE_URL.'?db='.rawurlencode($this->runtime->databaseName).'&thumb='.$fileId;
            }
        }
    }

    /**
     * Obfuscated id of the first file of each record that gives a thumbnail
     * (image, media service or IIIF).
     *
     * @return array<int,string> record id => ulf_ObfuscatedFileID
     */
    private function thumbnailFiles(array $ids): array
    {
        $byRecord = $this->records->fileIds($ids);
        $all = array();
        foreach($byRecord as $fileIds){ foreach($fileIds as $fileId){ $all[$fileId] = $fileId; } }
        $files = FileRepository::shared($this->database)->files(array_values($all));
        $result = array();
        foreach($byRecord as $recordId=>$fileIds){
            foreach($fileIds as $fileId){
                if(isset($files[$fileId]) && FileRepository::hasThumbnail($files[$fileId])){
                    $result[$recordId] = (string)$files[$fileId]['ulf_ObfuscatedFileID'];
                    break;
                }
            }
        }
        return $result;
    }

    /** Attach values reached through a concrete expansion occurrence. */
    public function attachLinkedValues(
        array &$records,
        array $field,
        array $occurrences,
        string $pathId,
        array $options = array()
    ): void
    {
        $owners = array();
        foreach($occurrences as $occurrence){
            $chain = $occurrence['recordIds'] ?? array();
            if(!empty($chain)){ $owners[intval(end($chain))] = intval(end($chain)); }
        }
        $values = $this->loadFieldValues(
            array_values($owners), array(intval($field['fieldId'])), $options
        );
        $recordIndex = array();
        foreach($records as $index=>$record){ $recordIndex[intval($record['rec_ID'])] = $index; }

        foreach($occurrences as $occurrence){
            $topId = intval($occurrence['top'] ?? 0);
            $chain = array_values(array_map('intval', $occurrence['recordIds'] ?? array()));
            if(!isset($recordIndex[$topId]) || empty($chain)){ continue; }
            $ownerId = intval(end($chain));
            foreach($values[$ownerId][$field['fieldId']] ?? array() as $value){
                $entry = array('value'=>$value, 'path'=>array(
                    'id'=>$pathId,
                    'recordIDs'=>array_map('strval', $chain)
                ));
                if(is_array($value)){
                    $entry = $value;
                    if(!array_key_exists('value', $entry)){
                        $entry = array('value'=>$value);
                    }
                    $entry['path'] = array(
                        'id'=>$pathId,
                        'recordIDs'=>array_map('strval', $chain)
                    );
                }
                $records[$recordIndex[$topId]]['details'][$field['key']][] = $entry;
            }
        }
    }

    /**
     * Attach header values (rec_Title, rec_ID, ...) of linked records reached through
     * concrete expansion occurrences (paths ending with a header field, e.g.
     * 10:lt241:12:rec_Title). Values are entries {value, path} as in attachLinkedValues.
     */
    public function attachLinkedHeaders(
        array &$records,
        array $field,
        array $occurrences,
        string $pathId
    ): void
    {
        $header = (string)($field['header'] ?? '');
        if($header === ''){ return; }
        $owners = array();
        foreach($occurrences as $occurrence){
            $chain = $occurrence['recordIds'] ?? array();
            if(!empty($chain)){ $owners[intval(end($chain))] = intval(end($chain)); }
        }
        $values = array();
        foreach($this->loadRecords(array_values($owners), array($header), array()) as $owner){
            $values[intval($owner['rec_ID'])] = $owner[$header] ?? null;
        }
        $recordIndex = array();
        foreach($records as $index=>$record){ $recordIndex[intval($record['rec_ID'])] = $index; }
        foreach($occurrences as $occurrence){
            $topId = intval($occurrence['top'] ?? 0);
            $chain = array_values(array_map('intval', $occurrence['recordIds'] ?? array()));
            if(!isset($recordIndex[$topId]) || empty($chain)){ continue; }
            $value = $values[intval(end($chain))] ?? null;
            if($value === null || $value === ''){ continue; }
            $records[$recordIndex[$topId]]['details'][$field['key']][] = array(
                'value'=>(string)$value,
                'path'=>array('id'=>$pathId, 'recordIDs'=>array_map('strval', $chain))
            );
        }
    }

    /**
     * Field definitions (meta.fields.details) once per requested detail/path:
     * dty_ID, dty_Name, dty_Type, dty_ConceptCode (DefinitionLookup), plus
     * dty_PathCode for linked fields.
     */
    public function loadFieldMetadata(array $fields): array
    {
        $result = array();
        foreach($fields as $field){
            if(!empty($field['header'])){
                // header field of linked records (path ending with rec_Title, rec_ID, ...)
                $result[] = array('dty_ID'=>null, 'dty_Name'=>$field['header'], 'dty_Type'=>'header',
                    'dty_ConceptCode'=>null, 'dty_PathCode'=>$field['pathCode']);
                continue;
            }
            $fieldId = intval($field['fieldId']);
            $known = $this->definitions()->field($fieldId);
            $definition = array(
                'dty_ID'=>(string)$fieldId,
                'dty_Name'=>$known['name'] ?? null,
                'dty_Type'=>$known['type'] ?? null,
                'dty_ConceptCode'=>$known['concept'] ?? null
            );
            if(($field['pathCode'] ?? null) !== null){ $definition['dty_PathCode'] = $field['pathCode']; }
            $result[] = $definition;
        }
        return $result;
    }

    /**
     * Definitions used by loaded records (`defs=1`): their record types and the
     * record types of resolved pointer values, and the terms of enum and
     * relationtype values (stored or resolved).
     *
     * @param array $records Record objects of loadRecords()/RecordPageAssembler.
     * @return array{recordTypes:array<string,array>,terms:array<string,array>}
     */
    public function loadDefinitions(array $records): array
    {
        $lookup = $this->definitions();
        $rectypeIds = array();
        $termIds = array();
        foreach($records as $record){
            $rectypeIds[intval($record['rec_RecTypeID'] ?? 0)] = true;
            foreach($record['details'] ?? array() as $key=>$values){
                // the key is a field id, a type-qualified id (10:20) or a path code ending with the field id
                $parts = explode(':', (string)$key);
                $type = $lookup->fieldType(intval(end($parts)));
                foreach((array)$values as $value){
                    if($type === 'enum' || $type === 'relationtype'){
                        $termId = is_array($value) ? ($value['trm_ID'] ?? $value['value'] ?? 0) : $value;
                        $termIds[intval($termId)] = true;
                    }elseif($type === 'resource' && is_array($value)){
                        $rectypeIds[intval($value['rec_RecTypeID'] ?? 0)] = true;
                    }
                }
            }
        }
        unset($rectypeIds[0], $termIds[0]);
        ksort($rectypeIds);
        ksort($termIds);

        $recordTypes = array();
        foreach(array_keys($rectypeIds) as $id){
            $recordTypes[(string)$id] = array(
                'rty_Name'=>$lookup->rectypeName($id),
                'rty_ConceptCode'=>$lookup->rectypeConcept($id)
            );
        }
        $terms = array();
        foreach(array_keys($termIds) as $id){
            $term = $lookup->term($id);
            if($term === null){ continue; }
            $terms[(string)$id] = array(
                'trm_Label'=>$term['term'],
                'trm_Code'=>$term['code'],
                'trm_ConceptCode'=>$term['conceptid']
            );
        }
        return array('recordTypes'=>$recordTypes, 'terms'=>$terms);
    }

    /**
     * Load and enrich requested details, indexed by owner and field ID.
     *
     * @param array $ownerIds Owning record ids.
     * @param array|null $fieldIds Detail-type ids to restrict to, or `null`
     *        for every populated detail value regardless of type
     *        (`fields=_all`).
     */
    public function loadFieldValues(array $ownerIds, ?array $fieldIds, array $options = array()): array
    {
        $ownerIds = $this->ids($ownerIds);
        $hasFieldFilter = $fieldIds !== null;
        if($hasFieldFilter){ $fieldIds = $this->ids($fieldIds); }
        if(empty($ownerIds) || ($hasFieldFilter && empty($fieldIds))){ return array(); }
        $values = array();
        $markHidden = $options['markHidden'] ?? array();
        $resolve = filter_var($options['resolveDetails'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $lookup = $this->definitions();
        foreach(array_chunk($ownerIds, self::BATCH_SIZE) as $chunk){
            $rows = array();
            foreach($this->records->details($chunk, $fieldIds, array(
                'order'=>'field', 'owner'=>true, 'extent'=>$options['extent'] ?? null
            )) as $row){
                // values of fields without a definition are left out
                $row['type'] = $lookup->fieldType($row['field']);
                if($row['type'] === '' || !$this->valueVisible($row)){ continue; }
                $rows[] = $row;
            }
            $resolved = $resolve ? $this->resolveTargets($rows) : array();
            foreach($rows as $row){
                $value = $this->formatValue($row, $resolve, $resolved);
                if($value !== null && $value !== ''){
                    if($row['hidden'] && isset($markHidden[$row['record']])){
                        // hidden from the public: told to users who may change the record
                        $value = is_array($value) ? $value + array('hidden'=>true) : array('value'=>$value, 'hidden'=>true);
                    }
                    $values[$row['record']][$row['field']][] = $value;
                }
            }
        }
        return $values;
    }

    /**
     * Pointer targets, terms and files of value rows (one read each).
     *
     * @return array{records:array<int,array>,terms:array<int,array>,files:array<int,array>}
     */
    private function resolveTargets(array $rows): array
    {
        $targets = array(); $terms = array(); $files = array();
        foreach($rows as $row){
            if($row['type'] === 'resource'){
                $targets[] = intval($row['value']);
            }elseif($row['type'] === 'enum' || $row['type'] === 'relationtype'){
                $terms[] = intval($row['value']);
            }elseif($row['type'] === 'file' && $row['file'] !== null){
                $files[] = $row['file'];
            }
        }
        return array(
            'records'=>$this->records->headers($targets, array('rec_RecTypeID', 'rec_Title', 'rec_Hash')),
            'terms'=>$this->definitions()->terms($terms),
            'files'=>FileRepository::shared($this->database)->files($files)
        );
    }

    /** Discover visible detail fields of a given type used by supplied records. */
    public function findFieldIdsByType(array $ownerIds, string $fieldType): array
    {
        $ownerIds = $this->ids($ownerIds);
        $fieldType = strtolower(trim($fieldType));
        if(empty($ownerIds) || !preg_match('/^[a-z]+$/', $fieldType)){ return array(); }
        $typed = array();
        foreach($this->definitions()->fields() as $id=>$field){
            if(strtolower((string)$field['type']) === $fieldType){ $typed[] = intval($id); }
        }
        $found = array();
        foreach(array_chunk($ownerIds, self::BATCH_SIZE) as $chunk){
            foreach($this->records->fieldUse($chunk, $typed) as $use){
                if($this->valueVisible(array('field'=>$use['field'], 'rectype'=>$use['rectype'],
                    'owner'=>$use['owner'], 'hidden'=>$use['hidden']))){
                    $found[$use['field']] = $use['field'];
                }
            }
        }
        return array_values($found);
    }

    private function formatValue(array $row, bool $resolveDetails, array $resolved)
    {
        $raw = $row['value'];
        $type = $row['type'];
        if($type === 'resource'){
            if(!$resolveDetails){ return (string)$raw; }
            $target = $resolved['records'][intval($raw)] ?? null;
            return array(
                'value'=>(string)$raw,
                'rec_ID'=>$target === null ? null : (string)$target['rec_ID'],
                'rec_RecTypeID'=>$target === null ? null : (string)$target['rec_RecTypeID'],
                'rec_Title'=>$target['rec_Title'] ?? null,
                'rec_Hash'=>$target['rec_Hash'] ?? null
            );
        }
        if($type === 'enum' || $type === 'relationtype'){
            if(!$resolveDetails){ return (string)$raw; }
            $term = $resolved['terms'][intval($raw)] ?? null;
            return array(
                'value'=>(string)$raw,
                'trm_ID'=>(string)$raw,
                'trm_Label'=>$term['term'] ?? null,
                'trm_Code'=>$term['code'] ?? null,
                'trm_ConceptCode'=>$term['conceptid'] ?? null
            );
        }
        if($type === 'file'){
            $fileId = $row['file'];
            if(!$resolveDetails){ return $fileId === null ? null : (string)$fileId; }
            $file = $fileId === null ? null : ($resolved['files'][$fileId] ?? null);
            $fields = array('ulf_ExternalFileReference', 'fxm_MimeType', 'ulf_PreferredSource', 'ulf_OrigFileName',
                'ulf_FileSizeKB', 'ulf_ObfuscatedFileID', 'ulf_Description', 'ulf_Added', 'ulf_MimeExt', 'ulf_Caption',
                'ulf_Copyright', 'ulf_Copyowner', 'ulf_Parameters', 'ulf_WhoCanView');
            $info = array('ulf_ID'=>$file === null ? null : (string)$file['ulf_ID'], 'fullPath'=>$file['fullPath'] ?? null);
            foreach($fields as $field){ $info[$field] = $file[$field] ?? null; }
            return array(
                'value'=>$fileId === null ? $raw : (string)$fileId,
                'file'=>array_filter($info, static function($item){ return $item !== null; })
            );
        }
        if($type === 'geo'){
            if($raw === null || $row['wkt'] === null){ return null; }
            return array('geo'=>array('type'=>$raw, 'wkt'=>$row['wkt']));
        }
        if($type === 'separator' || $type === 'relmarker'){ return null; }
        return $raw;
    }

    private function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){
            return $id>0;
        })));
    }

    /** The one value visibility rule (RecordRepository::valueVisible, plan 15). */
    private function valueVisible(array $row): bool
    {
        if($this->access === null){ $this->access = RecordAccess::fromRuntime($this->runtime); }
        return $this->records->valueVisible($row, $this->access);
    }

}
