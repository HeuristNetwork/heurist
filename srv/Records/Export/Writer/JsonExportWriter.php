<?php
/**
* JsonExportWriter.php - JSON export
*
* The file is the /records envelope (common JSON format, 2026-10-09): the same
* record objects and meta as
*   GET /records?q=...&fields=_all&defs=1                    (ExportRequest::$names false)
*   GET /records?q=...&fields=_all&resolveDetails=1&defs=1   ($names true, names and resolved values)
*   {"records":[ <record objects of /records> ],
*    "meta":{"database", "entity":"records", "title", "exported", "total",
*            "fields":{"headers":[...], "details":[{dty_ID, dty_Name, dty_Type, dty_ConceptCode}]},
*            "recordTypes":{"<id>":{rty_Name, rty_ConceptCode}},
*            "terms":{"<id>":{trm_Label, trm_Code, trm_ConceptCode}}}}
* Field metadata and definitions come from RecordPageAssembler (addMeta), as in
* the API. Concept codes are in meta. No record structure, no pagination block.
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

namespace Heurist\Records\Export\Writer;

use RuntimeException;

/** Streams records into one JSON document. */
final class JsonExportWriter implements ExportWriterInterface
{
    private const HEADERS = array('rec_ID', 'rec_RecTypeID', 'rec_Title', 'rec_URL', 'rec_ScratchPad',
        'rec_OwnerUGrpID', 'rec_NonOwnerVisibility', 'rec_Added', 'rec_Modified', 'rec_AddedByUGrpID', 'rec_Hash');

    private string $path;
    /** @var resource|null */
    private $handle = null;
    private bool $first = true;
    private array $meta = array();
    /** @var array<int,array> meta.fields.details by field id */
    private array $fields = array();
    /** @var array<string,array> */
    private array $recordTypes = array();
    /** @var array<string,array> */
    private array $terms = array();

    /** @param string $workDir Folder for the file. */
    public function __construct(string $workDir)
    {
        $this->path = rtrim($workDir, '/\\').'/export.json';
    }

    /** @inheritDoc */
    public function fieldCodes(): array
    {
        return array('_all');
    }

    /**
     * Meta of one batch (RecordPageAssembler::fieldMetadata() and definitions()).
     *
     * @param array $fields meta.fields.details of the batch.
     * @param array{recordTypes:array,terms:array} $definitions Definitions of the batch.
     */
    public function addMeta(array $fields, array $definitions): void
    {
        foreach($fields as $field){ $this->fields[intval($field['dty_ID'] ?? 0)] = $field; }
        $this->recordTypes += $definitions['recordTypes'] ?? array();
        $this->terms += $definitions['terms'] ?? array();
    }

    /** @inheritDoc */
    public function begin(array $meta): void
    {
        $this->meta = $meta;
        $this->handle = fopen($this->path, 'wb');
        if($this->handle === false){
            throw new RuntimeException('Cannot create the export file');
        }
        $this->put('{"records":[');
    }

    /** @inheritDoc */
    public function write(array $records): void
    {
        foreach($records as $record){
            $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if($json === false){
                throw new RuntimeException('Cannot encode record '.($record['rec_ID'] ?? ''));
            }
            $this->put(($this->first ? '' : ',').$json);
            $this->first = false;
        }
    }

    /** @inheritDoc */
    public function end(): void
    {
        if($this->handle === null){ return; }
        ksort($this->fields);
        ksort($this->recordTypes);
        ksort($this->terms);
        $meta = array(
            'database' => (string)($this->meta['database'] ?? ''),
            'entity' => 'records',
            'title' => (string)($this->meta['title'] ?? ''),
            'exported' => date('c'),
            'total' => intval($this->meta['total'] ?? 0),
            'fields' => array('headers' => self::HEADERS, 'details' => array_values($this->fields)),
            'recordTypes' => (object)$this->recordTypes,
            'terms' => (object)$this->terms
        );
        $this->put('],"meta":'.json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'}');
        fclose($this->handle);
        $this->handle = null;
    }

    /** @inheritDoc */
    public function files(): array
    {
        return array(array('path' => $this->path, 'name' => 'export.json'));
    }

    private function put(string $text): void
    {
        if(fwrite($this->handle, $text) === false){
            throw new RuntimeException('Cannot write the export file');
        }
    }
}
