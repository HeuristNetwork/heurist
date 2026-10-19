<?php
/**
* RecordFileStore.php - File records read from fileRecords/fileRecDetails
*
* The /files domain after the switch (plan 20, §2.1): the standard record
* search and output (RecordQueryController) on the file table pair, which has
* the same structure as Records/recDetails. Until the switch it runs in tests
* and on test databases only.
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

use Heurist\Controller\RecordQueryController;
use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Files\FileRepository;
use Heurist\Records\Store\RecordSchema;
use Heurist\Runtime\RuntimeContext;

/** Reads file records from fileRecords/fileRecDetails. */
final class RecordFileStore implements FileStoreInterface
{
    private RuntimeContext $runtime;
    private RecordQueryController $records;
    private FileRecordMapping $mapping;

    public function __construct(DatabaseInterface $database, RuntimeContext $runtime)
    {
        $this->runtime = $runtime;
        $this->records = new RecordQueryController($database, $runtime, null, null, null, null, null,
            new RecordSchema(RecordSchema::FILE_TABLES), 'files');
        $this->mapping = new FileRecordMapping(DefinitionLookup::shared($database), FileRepository::shared($database));
    }

    /** {@inheritdoc} */
    public function search(array $params): array
    {
        list($params, $addedHash) = $this->withHash($params);
        return $this->finish($this->records->execute($params), $addedHash);
    }

    /** {@inheritdoc} */
    public function item(array $params, int $fileId): array
    {
        list($params, $addedHash) = $this->withHash($params);
        return $this->finish($this->records->executeItem($params, $fileId), $addedHash);
    }

    /**
     * rec_ThumbnailURL of a file record is its own key (rec_Hash): request rec_Hash with it.
     *
     * @return array{0:array,1:bool} The parameters and whether rec_Hash was added.
     */
    private function withHash(array $params): array
    {
        $fields = $params['fields'] ?? null;
        $list = is_array($fields) ? $fields : ($fields === null ? array() : explode(',', (string)$fields));
        $lower = array_map(static function($field){ return strtolower(trim((string)$field)); }, $list);
        if(!in_array('rec_thumbnailurl', $lower, true) || in_array('rec_hash', $lower, true) || in_array('_all', $lower, true)){
            return array($params, false);
        }
        $list[] = 'rec_Hash';
        $params['fields'] = is_array($fields) ? $list : implode(',', $list);
        return array($params, true);
    }

    /** Thumbnail URLs from rec_Hash, and no server paths (stored name, folder) for non-administrators. */
    private function finish(array $response, bool $addedHash): array
    {
        if(!isset($response['records']) || !is_array($response['records'])){ return $response; }
        $hidden = array();
        if(!($this->runtime->isAdmin || $this->runtime->isDbOwner)){
            foreach($this->mapping->fields() as $fieldId => $mapping){
                if(!empty($mapping['admin'])){ $hidden[(string)$fieldId] = true; }
            }
        }
        foreach($response['records'] as &$record){
            if(!is_array($record)){ continue; }
            if(array_key_exists('rec_ThumbnailURL', $record)){
                $key = preg_replace('/[^a-z0-9]/i', '', (string)($record['rec_Hash'] ?? ''));
                $record['rec_ThumbnailURL'] = $key === '' || !defined('HEURIST_BASE_URL') ? null
                    : HEURIST_BASE_URL.'?db='.rawurlencode($this->runtime->databaseName).'&thumb='.$key;
            }
            if($addedHash){ unset($record['rec_Hash']); }
            foreach(array_keys($hidden) as $fieldId){ unset($record['details'][$fieldId]); }
        }
        unset($record);
        if(!empty($hidden) && isset($response['meta']['fields']['details'])){
            $response['meta']['fields']['details'] = array_values(array_filter($response['meta']['fields']['details'],
                static function($field) use ($hidden){ return !isset($hidden[(string)($field['dty_ID'] ?? '')]); }));
        }
        return $response;
    }
}
