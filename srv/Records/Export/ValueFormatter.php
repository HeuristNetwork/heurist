<?php
/**
* ValueFormatter.php - Flat text of field values for table-like export formats
*
* Input values are the resolved values of RecordDataService (resolveDetails):
* pointers {rec_ID, rec_Title, ...}, terms {trm_ID, trm_Label, trm_Code,
* trm_ConceptCode}, files {file: {...}}, geo {geo: {type, wkt}}, plain strings
* (dates are the stored value: a simple date or a temporal JSON object). A value
* reached through a linked path carries "path" as well.
*
* Used by CSV, KML, GEXF and GeoJSON properties; JSON and HML write the resolved
* values themselves. Enum outputs have the report names: term, code, conceptid,
* desc, internalid.
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

use Heurist\Definitions\DefinitionLookup;

use Heurist\Utilities\Temporal;

/** Converts resolved field values to cell text. */
final class ValueFormatter
{
    private DefinitionLookup $definitions;
    /** @var array{date:string,file:string,enum:string,pointerTitle:bool,termHierarchy:bool} */
    private array $values;
    private string $baseUrl;
    private string $databaseName;

    /**
     * @param DefinitionLookup $definitions Term descriptions.
     * @param array $values Value formats of the request (ExportRequest::$values).
     * @param string $baseUrl Heurist base URL (file download links).
     * @param string $databaseName Database name (file download links).
     */
    public function __construct(DefinitionLookup $definitions, array $values, string $baseUrl, string $databaseName)
    {
        $this->definitions = $definitions;
        $this->values = $values;
        $this->baseUrl = $baseUrl;
        $this->databaseName = $databaseName;
    }

    /** Value format of a field type (date, file, enum). */
    public function format(string $kind): string
    {
        return (string)($this->values[$kind] ?? '');
    }

    /** True when pointer columns get a title column. */
    public function pointerTitles(): bool
    {
        return !empty($this->values['pointerTitle']);
    }

    /**
     * Cell text of one value.
     *
     * @param string $type Field type (dty_Type).
     * @param mixed $value Resolved value.
     * @param string|null $enumOutput Output of an enum column (else the default of the request).
     */
    public function text(string $type, $value, ?string $enumOutput = null): string
    {
        switch($type){
            case 'enum':
            case 'relationtype':
                return $this->term($value, $enumOutput ?? $this->format('enum'));
            case 'resource':
                return is_array($value) ? (string)($value['rec_ID'] ?? $value['value'] ?? '') : (string)$value;
            case 'file':
                $file = is_array($value) && is_array($value['file'] ?? null) ? $value['file'] : null;
                if($file === null){ return is_array($value) ? (string)($value['value'] ?? '') : (string)$value; }
                return $this->format('file') === 'id'
                    ? (string)($file['ulf_ObfuscatedFileID'] ?? '')
                    : $this->fileUrl($file);
            case 'geo':
                return is_array($value) ? (string)($value['geo']['wkt'] ?? '') : (string)$value;
            case 'date':
                return $this->date($this->plain($value));
            default:
                return $this->plain($value);
        }
    }

    /** Title of a pointed record ('' when not resolved). */
    public function pointerTitle($value): string
    {
        return is_array($value) ? (string)($value['rec_Title'] ?? '') : '';
    }

    /**
     * File details: obfuscated id, original name, mime type, URL.
     *
     * @return array{0:string,1:string,2:string,3:string}
     */
    public function fileDetails($value): array
    {
        $file = is_array($value) && is_array($value['file'] ?? null) ? $value['file'] : array();
        return array(
            (string)($file['ulf_ObfuscatedFileID'] ?? ''),
            (string)($file['ulf_OrigFileName'] ?? ''),
            (string)($file['fxm_MimeType'] ?? ''),
            empty($file) ? '' : $this->fileUrl($file)
        );
    }

    /** Download URL of a file: its external reference, else the Heurist file link. */
    public function fileUrl(array $file): string
    {
        $external = trim((string)($file['ulf_ExternalFileReference'] ?? ''));
        if($external !== ''){ return $external; }
        $nonce = (string)($file['ulf_ObfuscatedFileID'] ?? '');
        if($nonce === ''){ return ''; }
        return rtrim($this->baseUrl, '/').'/?db='.rawurlencode($this->databaseName).'&file='.rawurlencode($nonce);
    }

    /** Record URL (HTML view). */
    public function recordUrl(int $recordId): string
    {
        return rtrim($this->baseUrl, '/').'/?db='.rawurlencode($this->databaseName).'&recID='.$recordId;
    }

    /** Icon URL of a record type. */
    public function iconUrl(int $rectypeId): string
    {
        return rtrim($this->baseUrl, '/').'/?db='.rawurlencode($this->databaseName).'&icon='.$rectypeId;
    }

    /**
     * Earliest and latest ISO dates of a date value, or null.
     *
     * @return array{0:string,1:string}|null
     */
    public function dateRange(string $value): ?array
    {
        if(trim($value) === ''){ return null; }
        $temporal = new Temporal($value);
        if(!$temporal->isValid()){ return null; }
        $range = $temporal->calcMinMax();
        if(!is_array($range) || $range[0] === null || $range[0] === ''){ return null; }
        return array((string)$range[0], (string)($range[1] ?? $range[0]));
    }

    /** Scalar text of a value (linked values are {value, path}). */
    public function plain($value): string
    {
        if(is_array($value)){
            $value = $value['value'] ?? '';
            if(is_array($value)){ return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''; }
        }
        return $value === null ? '' : (string)$value;
    }

    private function term($value, string $output): string
    {
        if(!is_array($value)){
            $value = array('trm_ID' => (string)$value);
        }
        $id = intval($value['trm_ID'] ?? $value['value'] ?? 0);
        switch($output){
            case 'internalid':
                return $id > 0 ? (string)$id : '';
            case 'code':
                return (string)($value['trm_Code'] ?? ($this->definitions->term($id)['code'] ?? ''));
            case 'conceptid':
                return (string)($value['trm_ConceptCode'] ?? ($this->definitions->term($id)['conceptid'] ?? ''));
            case 'desc':
                return (string)($this->definitions->term($id)['desc'] ?? '');
            default:
                if(!empty($this->values['termHierarchy']) && $id > 0){
                    $label = $this->definitions->termHierarchyLabel($id);
                    if($label !== ''){ return $label; }
                }
                return (string)($value['trm_Label'] ?? ($this->definitions->term($id)['term'] ?? ''));
        }
    }

    /** Date as stored (raw: simple date or temporal JSON) or human readable. */
    private function date(string $value): string
    {
        if($this->format('date') !== 'readable' || $value === ''){ return $value; }
        try{
            $temporal = new Temporal($value);
            if(!$temporal->isValid()){ return $value; }
            $text = trim((string)$temporal->toReadable('both'));
            return $text !== '' ? $text : $value;
        }catch(\Throwable $error){
            return $value;
        }
    }
}
