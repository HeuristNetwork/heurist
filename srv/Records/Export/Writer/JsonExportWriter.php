<?php
/**
* JsonExportWriter.php - JSON export
*
* Two forms (ExportRequest::$names):
*
* Concept codes only (default) - records, fields and terms are identified by concept
* codes; no local definition ids and no names:
*   {"records":[{"rec_ID", "rec_RecTypeConceptID", "rec_Title", "rec_URL", "rec_ScratchPad",
*                "rec_NonOwnerVisibility", "rec_Added", "rec_Modified",
*                "details":{"<field concept code>":[value, ...]}}, ...],
*    "meta":{"database", "registeredId", "entity":"records", "form":"concepts", "title", "exported", "total"}}
*   values: term - its concept code; pointer - the record id; file - {nonce, name, mime, url};
*   geo - {type, wkt}; date - as stored (simple date or temporal JSON); other - text.
*
* With names and local ids ("names") - the /records API envelope (fields=_all,
* resolveDetails), which has local ids, names and concept codes:
*   {"records":[ <record objects of /records> ],
*    "meta":{"database", "entity":"records", "form":"full", "title", "exported", "total",
*            "fields":{"headers":[...], "details":[{dty_ID, dty_Name, dty_Type, dty_ConceptCode}]},
*            "recordTypes":{"<id>":{"name","conceptCode"}}}}
*
* The record structure (definitions) is not exported. No pagination block.
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

use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Export\ValueFormatter;
use RuntimeException;

/** Streams records into one JSON document. */
final class JsonExportWriter implements ExportWriterInterface
{
    private const HEADERS = array('rec_ID', 'rec_RecTypeID', 'rec_Title', 'rec_URL', 'rec_ScratchPad',
        'rec_OwnerUGrpID', 'rec_NonOwnerVisibility', 'rec_Added', 'rec_Modified', 'rec_AddedByUGrpID', 'rec_Hash');

    /** Header fields of the concept-code form (no local definition or user ids). */
    private const CONCEPT_HEADERS = array('rec_Title', 'rec_URL', 'rec_ScratchPad', 'rec_NonOwnerVisibility',
        'rec_Added', 'rec_Modified');

    private DefinitionLookup $definitions;
    private ValueFormatter $formatter;
    private bool $names;
    private string $path;
    /** @var resource|null */
    private $handle = null;
    private bool $first = true;
    private array $meta = array();
    /** @var array<int,true> */
    private array $rectypes = array();
    /** @var array<int,true> */
    private array $fields = array();

    /**
     * @param DefinitionLookup $definitions Names and concept codes.
     * @param ValueFormatter $formatter File URLs.
     * @param string $workDir Folder for the file.
     * @param bool $names Names and local ids (the /records envelope); false: concept codes only.
     */
    public function __construct(DefinitionLookup $definitions, ValueFormatter $formatter, string $workDir, bool $names = false)
    {
        $this->definitions = $definitions;
        $this->formatter = $formatter;
        $this->names = $names;
        $this->path = rtrim($workDir, '/\\').'/export.json';
    }

    /** @inheritDoc */
    public function fieldCodes(): array
    {
        return array('_all');
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
            if($this->names){
                $this->rectypes[intval($record['rec_RecTypeID'] ?? 0)] = true;
                foreach(array_keys($record['details'] ?? array()) as $fieldId){
                    $this->fields[intval($fieldId)] = true;
                }
            }else{
                $record = $this->conceptRecord($record);
            }
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
        $meta = array(
            'database' => (string)($this->meta['database'] ?? ''),
            'entity' => 'records',
            'form' => $this->names ? 'full' : 'concepts',
            'title' => (string)($this->meta['title'] ?? ''),
            'exported' => date('c'),
            'total' => intval($this->meta['total'] ?? 0)
        );
        if($this->names){
            $meta['fields'] = array('headers' => self::HEADERS, 'details' => $this->fieldDefinitions());
            $meta['recordTypes'] = (object)$this->recordTypeDefinitions();
        }else{
            $meta['registeredId'] = $this->definitions->registeredId();
        }
        $this->put('],"meta":'.json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'}');
        fclose($this->handle);
        $this->handle = null;
    }

    /** @inheritDoc */
    public function files(): array
    {
        return array(array('path' => $this->path, 'name' => 'export.json'));
    }

    /** Record object of the concept-code form. */
    private function conceptRecord(array $record): array
    {
        $rectypeId = intval($record['rec_RecTypeID'] ?? 0);
        $result = array(
            'rec_ID' => (string)$record['rec_ID'],
            'rec_RecTypeConceptID' => $this->definitions->rectypeConcept($rectypeId)
        );
        foreach(self::CONCEPT_HEADERS as $header){
            if(isset($record[$header]) && $record[$header] !== ''){ $result[$header] = $record[$header]; }
        }
        $details = array();
        foreach($record['details'] ?? array() as $fieldId => $values){
            $field = $this->definitions->field(intval($fieldId));
            $key = $field['concept'] ?? (string)$fieldId;
            $type = $field['type'] ?? '';
            foreach($values as $value){
                $details[$key][] = $this->conceptValue($type, $value);
            }
        }
        $result['details'] = (object)$details;
        return $result;
    }

    /** One value of the concept-code form. */
    private function conceptValue(string $type, $value)
    {
        switch($type){
            case 'enum':
            case 'relationtype':
                if(is_array($value)){
                    return (string)($value['trm_ConceptCode']
                        ?? ($this->definitions->term(intval($value['trm_ID'] ?? $value['value'] ?? 0))['conceptid'] ?? ''));
                }
                return (string)($this->definitions->term(intval($value))['conceptid'] ?? $value);
            case 'resource':
                return is_array($value) ? (string)($value['rec_ID'] ?? $value['value'] ?? '') : (string)$value;
            case 'file':
                $file = is_array($value) && is_array($value['file'] ?? null) ? $value['file'] : array();
                return array_filter(array(
                    'nonce' => $file['ulf_ObfuscatedFileID'] ?? null,
                    'name' => $file['ulf_OrigFileName'] ?? null,
                    'mime' => $file['fxm_MimeType'] ?? null,
                    'description' => $file['ulf_Description'] ?? null,
                    'url' => empty($file) ? null : $this->formatter->fileUrl($file)
                ), static function($item){ return $item !== null && $item !== ''; });
            case 'geo':
                return is_array($value) && is_array($value['geo'] ?? null) ? $value['geo'] : $value;
            default:
                return is_array($value) ? ($value['value'] ?? $value) : $value;
        }
    }

    /** meta.fields.details of the full form. */
    private function fieldDefinitions(): array
    {
        $details = array();
        $fieldIds = array_keys($this->fields);
        sort($fieldIds);
        foreach($fieldIds as $fieldId){
            $field = $this->definitions->field($fieldId);
            $details[] = array(
                'dty_ID' => (string)$fieldId,
                'dty_Name' => $field['name'] ?? null,
                'dty_Type' => $field['type'] ?? null,
                'dty_ConceptCode' => $field['concept'] ?? null
            );
        }
        return $details;
    }

    /** meta.recordTypes of the full form. */
    private function recordTypeDefinitions(): array
    {
        $types = array();
        $rectypeIds = array_keys($this->rectypes);
        sort($rectypeIds);
        foreach($rectypeIds as $rectypeId){
            $types[(string)$rectypeId] = array(
                'name' => $this->definitions->rectypeName($rectypeId),
                'conceptCode' => $this->definitions->rectypeConcept($rectypeId)
            );
        }
        return $types;
    }

    private function put(string $text): void
    {
        if(fwrite($this->handle, $text) === false){
            throw new RuntimeException('Cannot write the export file');
        }
    }
}
