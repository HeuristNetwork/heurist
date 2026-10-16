<?php
/**
* ExportRequest.php - Validated parameters of one record export (job type "export")
*
*   format   csv | tsv | json | geojson | kml | xml | gephi
*   scope    {query, ids?, rectypes?}  query of the data source; ids = the selection;
*            rectypes = only these record types of the result
*   rules    expansion rules (as in a DataSource); empty = the result only; used by
*            json, xml and gephi (ignored for csv, tsv, geojson, kml)
*   columns  {"<rtyId>"|"*": [field code | {field, ext}]}  output fields per record type;
*            "*" applies to every record type without its own list; a record type without
*            columns gets MINIMAL_COLUMNS
*   values   {date: raw|readable, file: url|details|id, pointerTitle: bool,
*             termHierarchy: bool, enum: term|code|conceptid|desc|internalid (default
*             output of term columns without ext)}
*   geofields  geojson, kml: geo field codes of the data source (direct or linked paths)
*            giving the geometry; empty = every direct geo field
*   timefields kml: date field codes of the data source giving the time span; empty =
*            the Date or Start/End date fields
*   csv      {sep, quote, mvsep, header, eol: nix|win}
*   limit    records of the result to export (0 = all, up to the database maximum);
*            gephi: at most GEPHI_MAX records in all (expanded records included)
*   names    json, xml: include human-readable names and local ids; without it records,
*            fields and terms are identified by concept codes only
*   fileName base name of the file (A-Za-z0-9 -_() only)
*   title    caption for job lists (the data source title)
*
* Field codes are the codes of the /records "fields" parameter (rec_Title, 1,
* 10:lf134:12:1, ...). An enum column may carry "ext" (its output); the column
* fields of a Query Source store "id" for the internal id, read as "internalid".
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

use InvalidArgumentException;

/** Normalized export parameters. */
final class ExportRequest
{
    public const FORMATS = array('csv', 'tsv', 'json', 'geojson', 'kml', 'xml', 'gephi');

    /** Records of a Gephi export (nodes), expanded records included. */
    public const GEPHI_MAX = 10000;

    /** Enum outputs: the names of the report field tree and the Smarty subfields. */
    public const ENUM_OUTPUTS = array('term', 'code', 'conceptid', 'desc', 'internalid');

    private const VALUE_CHOICES = array(
        'date' => array('raw', 'readable'),
        'file' => array('url', 'details', 'id'),
        'enum' => self::ENUM_OUTPUTS
    );

    /** Columns of a record type without its own list: id, record type and title. */
    public const MINIMAL_COLUMNS = array('rec_ID', 'rec_RecTypeID', 'rec_Title');

    /** Formats that write the expanded records too. */
    public const EXPANDING_FORMATS = array('json', 'xml', 'gephi');

    public string $format;
    /** @var mixed Query of the data source (text or JSON array). */
    public $query;
    /** @var int[] Selected record ids (scope "selection"); empty = the whole result. */
    public array $ids;
    /** @var int[] Record types of the result to export; empty = all. */
    public array $rectypes;
    /** @var mixed Expansion rules; null = none. */
    public $rules;
    /** @var array<string,array<int,array{field:string,ext:?string}>> Columns per record type ("*" = any). */
    public array $columns;
    /** @var array{date:string,file:string,enum:string,pointerTitle:bool,termHierarchy:bool} */
    public array $values;
    /** @var string[] geojson, kml: geo field codes of the geometry (empty = every direct geo field). */
    public array $geoFields;
    /** @var string[] kml: date field codes of the time span (empty = Date or Start/End). */
    public array $timeFields;
    /** @var array{sep:string,quote:string,mvsep:string,header:bool,eol:string} */
    public array $csv;
    public int $limit;
    public string $fileName;
    public string $title;
    /** json, xml: human-readable names and local ids besides the concept codes. */
    public bool $names;

    /**
     * @param array $params Request parameters (or the normalized array from toArray()).
     * @throws InvalidArgumentException For invalid values.
     */
    public function __construct(array $params)
    {
        $format = strtolower(trim((string)($params['format'] ?? 'json')));
        if(!in_array($format, self::FORMATS, true)){
            throw new InvalidArgumentException('Unknown export format: '.$format);
        }
        $this->format = $format;

        $scope = is_array($params['scope'] ?? null) ? $params['scope'] : array();
        $this->query = $scope['query'] ?? $params['query'] ?? null;
        $this->ids = self::idList($scope['ids'] ?? array());
        $this->rectypes = self::idList($scope['rectypes'] ?? array());
        if(($this->query === null || $this->query === '' || $this->query === array()) && empty($this->ids)){
            throw new InvalidArgumentException('The export needs a query or record ids');
        }

        $rules = $params['rules'] ?? null;
        $this->rules = ($rules === null || $rules === '' || $rules === array()
            || !in_array($format, self::EXPANDING_FORMATS, true)) ? null : $rules;

        $this->columns = self::columns($params['columns'] ?? array());

        $values = is_array($params['values'] ?? null) ? $params['values'] : array();
        $this->values = array();
        foreach(self::VALUE_CHOICES as $type => $choices){
            $value = strtolower(trim((string)($values[$type] ?? $choices[0])));
            if($type === 'enum' && $value === 'id'){ $value = 'internalid'; }
            if($type === 'date' && $value === 'asis'){ $value = 'raw'; }
            if(!in_array($value, $choices, true)){
                throw new InvalidArgumentException('Unknown '.$type.' output: '.$value);
            }
            $this->values[$type] = $value;
        }
        $this->values['pointerTitle'] = filter_var($values['pointerTitle'] ?? (($values['pointer'] ?? '') === 'title'),
            FILTER_VALIDATE_BOOLEAN);
        $this->values['termHierarchy'] = filter_var($values['termHierarchy'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->geoFields = self::codeList($params['geofields'] ?? array(), 'geo');
        $this->timeFields = self::codeList($params['timefields'] ?? array(), 'time');

        $csv = is_array($params['csv'] ?? null) ? $params['csv'] : array();
        $sep = (string)($csv['sep'] ?? ($format === 'tsv' ? "\t" : ','));
        if($format === 'tsv' || $sep === 'tab' || $sep === '\t'){ $sep = "\t"; }
        if(strlen($sep) !== 1){
            throw new InvalidArgumentException('The CSV separator must be one character');
        }
        $quote = (string)($csv['quote'] ?? '"');
        if($quote === 'none'){ $quote = ''; }
        if(strlen($quote) > 1){
            throw new InvalidArgumentException('The CSV quote must be one character or none');
        }
        $mvsep = (string)($csv['mvsep'] ?? '|');
        if($mvsep === '' || strlen($mvsep) > 5){
            throw new InvalidArgumentException('The multi-value separator must have 1 to 5 characters');
        }
        $this->csv = array(
            'sep' => $sep,
            'quote' => $quote,
            'mvsep' => $mvsep,
            'header' => !array_key_exists('header', $csv) || filter_var($csv['header'], FILTER_VALIDATE_BOOLEAN),
            'eol' => ($csv['eol'] ?? 'nix') === 'win' ? 'win' : 'nix'
        );

        $this->limit = max(0, intval($params['limit'] ?? 0));
        if($format === 'gephi' && ($this->limit === 0 || $this->limit > self::GEPHI_MAX)){
            $this->limit = self::GEPHI_MAX;
        }
        $this->names = filter_var($params['names'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $fileName = trim((string)($params['fileName'] ?? ''));
        if($fileName !== '' && preg_match('/^[A-Za-z0-9 _\-()]{1,100}$/', $fileName) !== 1){
            throw new InvalidArgumentException('The file name may contain only letters, digits, spaces, "-", "_", "(" and ")"');
        }
        $this->fileName = $fileName;
        $this->title = mb_substr(trim((string)($params['title'] ?? '')), 0, 200);
    }

    /** Most records in all (expanded records included); 0 = the database maximum only. */
    public function recordCap(): int
    {
        return $this->format === 'gephi' ? self::GEPHI_MAX : 0;
    }

    /** True when the format writes one table row per record (CSV and TSV). */
    public function isTable(): bool
    {
        return $this->format === 'csv' || $this->format === 'tsv';
    }

    /**
     * Columns of one record type: its own list, else the "*" list.
     *
     * @return array<int,array{field:string,ext:?string}>
     */
    public function columnsFor(int $rectypeId): array
    {
        return $this->columns[(string)$rectypeId] ?? $this->columns['*'] ?? array();
    }

    /** Parameters stored with the job (re-read by the constructor). */
    public function toArray(): array
    {
        return array(
            'format' => $this->format,
            'scope' => array('query' => $this->query, 'ids' => $this->ids, 'rectypes' => $this->rectypes),
            'rules' => $this->rules,
            'columns' => $this->columns,
            'values' => $this->values,
            'csv' => $this->csv,
            'geofields' => $this->geoFields,
            'timefields' => $this->timeFields,
            'limit' => $this->limit,
            'fileName' => $this->fileName,
            'title' => $this->title,
            'names' => $this->names
        );
    }

    /**
     * Field codes (direct ids or linked paths) of geo or time fields.
     *
     * @return string[]
     */
    private static function codeList($value, string $kind): array
    {
        if(is_string($value)){ $value = $value === '' ? array() : explode(',', $value); }
        if(!is_array($value)){
            throw new InvalidArgumentException('"'.$kind.'fields" must be a list of field codes');
        }
        $codes = array();
        foreach($value as $code){
            $code = trim((string)(is_array($code) ? ($code['field'] ?? '') : $code));
            if($code === ''){ continue; }
            if(preg_match('/^[A-Za-z0-9_:]{1,200}$/', $code) !== 1){
                throw new InvalidArgumentException('Invalid '.$kind.' field: '.$code);
            }
            $codes[$code] = $code;
        }
        return array_values($codes);
    }

    /** @return int[] */
    private static function idList($value): array
    {
        if(is_string($value)){ $value = explode(',', $value); }
        if(!is_array($value)){ return array(); }
        $ids = array();
        foreach($value as $id){
            $id = intval($id);
            if($id > 0){ $ids[$id] = $id; }
        }
        return array_values($ids);
    }

    /** @return array<string,array<int,array{field:string,ext:?string}>> */
    private static function columns($value): array
    {
        if(!is_array($value)){
            throw new InvalidArgumentException('"columns" must be an object of record type ids');
        }
        $result = array();
        foreach($value as $rectype => $list){
            $key = (string)$rectype;
            if($key !== '*' && !(ctype_digit($key) && intval($key) > 0)){
                throw new InvalidArgumentException('Invalid record type in "columns": '.$key);
            }
            if(!is_array($list)){
                throw new InvalidArgumentException('The columns of record type '.$key.' must be a list');
            }
            $columns = array();
            foreach($list as $column){
                $field = is_array($column) ? (string)($column['field'] ?? '') : (string)$column;
                $field = trim($field);
                if($field === '' || preg_match('/^[A-Za-z0-9_:]{1,200}$/', $field) !== 1){
                    throw new InvalidArgumentException('Invalid column field: '.$field);
                }
                $ext = is_array($column) && isset($column['ext']) && $column['ext'] !== '' ? strtolower((string)$column['ext']) : null;
                if($ext === 'id'){ $ext = 'internalid'; }
                if($ext !== null && !in_array($ext, self::ENUM_OUTPUTS, true)){
                    $ext = null; // outputs of other field types (e.g. geo, file) are not export choices
                }
                $columns[] = array('field' => $field, 'ext' => $ext);
            }
            $result[$key] = $columns;
        }
        return $result;
    }
}
