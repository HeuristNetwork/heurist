<?php
/**
* LegacyFileStore.php - File records read from recUploadedFiles
*
* The /files domain while files are kept in the legacy table (plan 20): the
* query is compiled by LegacyFileQueryBuilder, rows are read by FileRepository
* and turned into records by FileRecordMapping. The response has the same
* envelope as /records (records, meta.fields, pagination), so record lists can
* show files without changes.
*
* @project     Heurist academic knowledge management system
* @package     Files
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Files;

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Data\RecordDataService;
use Heurist\Records\Data\RecordFieldSelector;
use Heurist\Records\Files\FileRepository;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\SearchRequest;
use Heurist\Records\Query\SearchResult;
use Heurist\Records\Query\UnsupportedQueryException;
use Heurist\Runtime\RuntimeContext;
use Heurist\Users\UserGroupRepository;

/** Reads file records from recUploadedFiles. */
final class LegacyFileStore implements FileStoreInterface
{
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private FileRepository $files;
    private DefinitionLookup $definitions;
    private FileRecordMapping $mapping;
    private LegacyFileQueryBuilder $builder;

    /** Field metadata and `defs` of the records API (they only read definitions). */
    private RecordDataService $metadata;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime)
    {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->files = FileRepository::shared($database);
        $this->definitions = DefinitionLookup::shared($database);
        $this->mapping = new FileRecordMapping($this->definitions, $this->files);
        $this->builder = new LegacyFileQueryBuilder($database, $runtime, $this->mapping);
        $this->metadata = new RecordDataService($database, $runtime);
    }

    /** {@inheritdoc} */
    public function search(array $params): array
    {
        $request = $this->request($params);
        $detail = $request->detail;
        if(in_array($detail, SearchRequest::AGGREGATE_OPS, true)){
            throw new QueryValidationException('detail='.$detail.' is not supported by /files; use /files/aggregate?op='.$detail);
        }
        if(!in_array($detail, array('ids', 'records'), true)){
            throw new UnsupportedQueryException('detail='.$detail.' is not supported by /files');
        }
        $compiled = $this->builder->build($request->query, $request->limit, $request->offset, $request->sort);
        $total = !$request->countTotal ? -1
            : intval($this->database->fetchValue($compiled['count']->sql, $compiled['count']->values, 0));
        $ids = array_map('intval', $this->database->fetchColumn($compiled['ids']->sql, $compiled['ids']->values));
        $result = new SearchResult($ids, $total, $request->offset, $request->limit);
        if($detail === 'ids'){ return $result->toArray(); }

        $selection = (new RecordFieldSelector())->parse($request->fields);
        $records = $this->records($ids, $selection, $request->resolveDetails);
        $response = array(
            'records' => $records,
            'meta' => $this->meta($params, $selection, $records, $request->defs),
            'pagination' => $this->pagination($result, $params)
        );
        return $response;
    }

    /** {@inheritdoc} */
    public function aggregate(array $params): array
    {
        $op = strtolower(trim((string)($params['op'] ?? '')));
        unset($params['detail']);
        $request = $this->request($params);
        $compiled = $this->builder->build($request->query, $request->limit, $request->offset, $request->sort);
        $total = intval($this->database->fetchValue($compiled['count']->sql, $compiled['count']->values, 0));
        $summary = array('query' => $params['query'] ?? $params['q'] ?? $request->query, 'total' => $total);
        if($op === 'rectypes'){
            $summary['rectypes'] = $total > 0
                ? array(array('rec_RecTypeID' => $this->mapping->rectypeId(), 'count' => $total)) : array();
        }
        return $summary;
    }

    /** {@inheritdoc} */
    public function item(array $params, int $fileId): array
    {
        unset($params['query'], $params['q'], $params['filter'], $params['sort'], $params['detail']);
        $params['ids'] = (string)$fileId;
        $params['limit'] = 1;
        $params['offset'] = 0;
        $params['total'] = 0;
        if(!isset($params['fields']) || $params['fields'] === '' || $params['fields'] === array()){
            $params['fields'] = '_all';
        }
        $response = $this->search($params);
        unset($response['pagination']);
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if($uri !== ''){ $response['meta']['self'] = $uri; }
        return $response;
    }

    /** SearchRequest of the parameters (the same parameters as /records). */
    private function request(array $params): SearchRequest
    {
        $query = $params['query'] ?? $params['q'] ?? null;
        if(($query === null || $query === '') && isset($params['ids'])){
            $query = array(array('ids' => $params['ids']));
        }
        if($query === null || $query === ''){
            $query = array(array('_all' => true));
        }
        $normalized = $this->builder->normalize($this->structured($query));
        $filter = $this->structured($params['filter'] ?? null);
        if($filter !== null && $filter !== '' && $filter !== array()){
            $normalized[] = array('all' => $this->builder->normalize($filter));
        }
        $options = array(
            'limit' => $params['limit'] ?? 1000,
            'offset' => $params['offset'] ?? 0,
            'fields' => $params['fields'] ?? null,
            'detail' => $params['detail'] ?? null,
            'total' => $params['total'] ?? null,
            'resolveDetails' => $params['resolveDetails'] ?? false,
            'defs' => $params['defs'] ?? false
        );
        if(array_key_exists('sort', $params)){ $options['sort'] = $this->structured($params['sort']); }
        return new SearchRequest($normalized, $options);
    }

    /** Record objects of the files, in the order of the ids. */
    private function records(array $ids, array $selection, bool $resolveDetails): array
    {
        if(empty($ids)){ return array(); }
        $admin = $this->runtime->isAdmin || $this->runtime->isDbOwner;
        $headers = array_values(array_unique(array_merge(array('rec_ID', 'rec_RecTypeID', 'rec_Title'), $selection['headers'])));
        $fieldIds = array();
        foreach($selection['details'] as $field){
            if(empty($field['traversal']) && empty($field['header'])){ $fieldIds[intval($field['fieldId'])] = (string)$field['key']; }
        }
        $wantsDetails = !empty($selection['all']) || !empty($fieldIds);
        $rows = $this->files->rows($ids);
        $owners = in_array('rec_OwnerName', $selection['virtuals'], true)
            ? UserGroupRepository::shared($this->database)->names(array_values(array_unique(array_map(static function($row){
                return intval($row['ulf_UploaderUGrpID']);
            }, $rows)))) : array();

        $records = array();
        foreach($ids as $id){
            if(!isset($rows[$id])){ continue; }
            $row = $rows[$id];
            $mapped = $this->mapping->toRecord($row, $admin);
            $record = array();
            foreach($headers as $header){
                $record[$header] = $header === 'rec_ID' ? (string)$id : ($mapped['headers'][$header] ?? null);
            }
            if(!empty($selection['all']) && $this->canEdit($row)){
                // as /records with fields=_all for editors (plan 14); files have no edit or view groups
                $record['rec_EditGroups'] = array();
                $record['rec_NonOwnerVisibilityGroups'] = array();
                $record['rec_FlagTemporary'] = '0';
            }
            if($wantsDetails){
                $record['details'] = array();
                foreach($mapped['details'] as $fieldId => $values){
                    if(empty($selection['all']) && !isset($fieldIds[$fieldId])){ continue; }
                    $record['details'][(string)$fieldId] = $resolveDetails
                        ? array_map(function($value) use ($fieldId){ return $this->resolved($fieldId, $value); }, $values)
                        : $values;
                }
            }
            foreach($selection['virtuals'] as $virtual){
                if($virtual === 'rec_ThumbnailURL'){
                    $record[$virtual] = $this->thumbnailUrl($row);
                }elseif($virtual === 'rec_OwnerName'){
                    $record[$virtual] = $owners[intval($row['ulf_UploaderUGrpID'])] ?? null;
                }elseif($virtual === 'rec_Bookmarked'){
                    $record[$virtual] = false;
                }
            }
            $records[] = $record;
        }
        return $records;
    }

    /** Whether the user may change a file: its uploader or the database owner (legacy rule). */
    private function canEdit(array $row): bool
    {
        if($this->runtime->userId < 1){ return false; }
        return $this->runtime->isDbOwner || intval($row['ulf_UploaderUGrpID']) === $this->runtime->userId;
    }

    /** Resolved form of a value (resolveDetails): term values as in /records. */
    private function resolved(int $fieldId, string $value)
    {
        if($this->definitions->fieldType($fieldId) !== 'enum'){ return $value; }
        $term = $this->definitions->term(intval($value));
        return array(
            'value' => $value,
            'trm_ID' => $value,
            'trm_Label' => $term['term'] ?? null,
            'trm_Code' => $term['code'] ?? null,
            'trm_ConceptCode' => $term['conceptid'] ?? null
        );
    }

    /** Thumbnail URL (legacy ?thumb=) of a file that gives one (image, media service, IIIF). */
    private function thumbnailUrl(array $row): ?string
    {
        $key = preg_replace('/[^a-z0-9]/i', '', (string)($row['ulf_ObfuscatedFileID'] ?? ''));
        if($key === '' || !FileRepository::hasThumbnail($row) || !defined('HEURIST_BASE_URL')){ return null; }
        return HEURIST_BASE_URL.'?db='.rawurlencode($this->runtime->databaseName).'&thumb='.$key;
    }

    private function meta(array $params, array $selection, array $records, bool $defs): array
    {
        $fields = array();
        if(!empty($selection['all'])){
            $present = array();
            foreach($records as $record){
                foreach(array_keys($record['details'] ?? array()) as $key){ $present[intval($key)] = true; }
            }
            ksort($present);
            foreach(array_keys($present) as $fieldId){
                $fields[] = array('key' => (string)$fieldId, 'fieldId' => $fieldId, 'pathCode' => null, 'traversal' => null);
            }
        }else{
            foreach($selection['details'] as $field){
                if(isset($this->mapping->fields()[intval($field['fieldId'])])){ $fields[] = $field; }
            }
        }
        $meta = array(
            'database' => $params['db'] ?? $this->runtime->databaseName,
            'entity' => 'files',
            'fields' => array(
                'headers' => array_values(array_unique(array_merge(array('rec_ID', 'rec_RecTypeID', 'rec_Title'),
                    $selection['headers'], $selection['virtuals']))),
                'details' => $this->metadata->loadFieldMetadata($fields)
            )
        );
        if($defs){
            $definitions = $this->metadata->loadDefinitions($records);
            $meta['recordTypes'] = (object)$definitions['recordTypes'];
            $meta['terms'] = (object)$definitions['terms'];
        }
        return $meta;
    }

    private function pagination(SearchResult $result, array $params): array
    {
        $pagination = array('total' => $result->total, 'offset' => $result->offset, 'limit' => $result->limit);
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if($uri !== ''){
            $pagination['self'] = $uri;
            if($result->total > 0 && $result->offset + $result->limit < $result->total){
                $next = $params;
                $next['offset'] = $result->offset + $result->limit;
                $next['limit'] = $result->limit;
                unset($next['query']);
                $pagination['next'] = strtok($uri, '?').'?'.http_build_query($next);
            }
        }
        return $pagination;
    }

    /** Decode JSON given as text (GET parameters). */
    private function structured($value)
    {
        if(!is_string($value)){ return $value; }
        $text = trim($value);
        if($text === '' || ($text[0] !== '[' && $text[0] !== '{')){ return $value; }
        $decoded = json_decode($text, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
