<?php
/**
* RecordFileRows.php - File rows in fileRecords/fileRecDetails
*
* The record store of registered files (plan 20): the columns of
* recUploadedFiles are kept as a record of RT_REGISTERED_FILE through
* FileRecordMapping (headers and fields), with RecordStore/RecordRepository on
* RecordSchema::FILE_TABLES.
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

use Heurist\Database\WritableDatabaseInterface;
use Heurist\Records\Store\RecordRepository;
use Heurist\Records\Store\RecordSchema;
use Heurist\Records\Store\RecordStore;

/** File rows kept as file records. */
final class RecordFileRows implements FileRowsInterface
{
    /** Headers read for a row. */
    private const HEADERS = array('rec_ID', 'rec_Added', 'rec_Modified', 'rec_OwnerUGrpID', 'rec_NonOwnerVisibility',
        'rec_URL', 'rec_Hash', 'rec_AddedByImport', 'rec_OriginatingDBID', 'rec_IDInOriginatingDB');

    private WritableDatabaseInterface $database;
    private FileRecordMapping $mapping;
    private RecordStore $store;
    private RecordRepository $records;

    public function __construct(WritableDatabaseInterface $database, FileRecordMapping $mapping)
    {
        $this->database = $database;
        $this->mapping = $mapping;
        $this->store = new RecordStore($database, new RecordSchema(RecordSchema::FILE_TABLES));
        $this->records = $this->store->records();
    }

    public function row(int $fileId): ?array
    {
        $header = $this->records->headers(array($fileId), self::HEADERS)[$fileId] ?? null;
        if($header === null){ return null; }
        $details = $this->records->values(array($fileId))[$fileId] ?? array();
        $columns = $this->mapping->toColumns($header, $details);
        $columns['ulf_ID'] = $fileId;
        return $columns;
    }

    public function rowByKey(string $key): ?array
    {
        $ids = $this->records->idsByHeader('rec_Hash', $key);
        return empty($ids) ? null : $this->row($ids[0]);
    }

    public function insert(array $columns): int
    {
        $mapped = $this->mapping->toRecord($columns + array('ulf_ID' => 0), true);
        $headers = $mapped['headers'];
        $fileId = $this->store->insertRecord(array(
            'rectype' => $this->mapping->rectypeId(),
            'owner' => intval($headers['rec_OwnerUGrpID']),
            'visibility' => (string)$headers['rec_NonOwnerVisibility'],
            'url' => $headers['rec_URL'],
            'scratchPad' => null,
            'addedBy' => intval($headers['rec_AddedByUGrpID'])
        ));
        $this->write($fileId, $columns);
        return $fileId;
    }

    public function update(int $fileId, array $columns): void
    {
        $current = $this->row($fileId) ?? array();
        $this->write($fileId, array_merge($current, $columns));
    }

    public function delete(int $fileId): void
    {
        $this->store->deleteRecord($fileId); // only the file tables: RecordSchema has no references for this pair
    }

    public function idsByUrl(string $url): array
    {
        return $this->records->idsByHeader('rec_URL', $url);
    }

    public function idsByChecksum(string $md5): array
    {
        $fieldId = $this->mapping->fieldId('2-68');
        return $fieldId > 0 ? $this->records->idsByValue($fieldId, strtolower($md5)) : array();
    }

    public function transaction(callable $work)
    {
        return FileTransaction::run($this->database, $work);
    }

    /** Headers and all field values of a file from its columns. */
    private function write(int $fileId, array $columns): void
    {
        $mapped = $this->mapping->toRecord(array_merge($columns, array('ulf_ID' => $fileId)), true);
        $headers = $mapped['headers'];
        $values = array();
        foreach(array('rec_Hash', 'rec_URL', 'rec_NonOwnerVisibility', 'rec_OwnerUGrpID', 'rec_AddedByUGrpID',
            'rec_Added', 'rec_Modified', 'rec_AddedByImport', 'rec_OriginatingDBID', 'rec_IDInOriginatingDB', 'rec_Title') as $header){
            if(array_key_exists($header, $headers) && ($headers[$header] !== null || in_array($header, array('rec_Hash', 'rec_URL'), true))){
                $values[$header] = $headers[$header];
            }
        }
        $this->store->setHeaders($fileId, $values);
        $rows = array();
        foreach($mapped['details'] as $fieldId => $items){
            foreach($items as $item){
                $rows[] = array('dty' => $fieldId, 'value' => $item, 'fileId' => null, 'geo' => null, 'hidden' => 0);
            }
        }
        $this->store->replaceDetails($fileId, $rows);
    }
}
