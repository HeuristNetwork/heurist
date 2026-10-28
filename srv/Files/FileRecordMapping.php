<?php
/**
* FileRecordMapping.php - recUploadedFiles columns as a record of RT_REGISTERED_FILE
*
* The one mapping between the legacy file table and the record format of the
* /files domain (plan 20, §3.1): headers and fields by concept code, with the
* value conversions (visibility, size, file type and registered file type terms).
* Used for reading and for writing. The concept-code fields, the writable columns
* and the term lookup are the base EntityRecordMapping (plan 20, §10.4).
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

use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Files\FileRepository;
use Heurist\System\Entity\EntityRecordMapping;

/** Converts rows of recUploadedFiles to file records and back. */
final class FileRecordMapping extends EntityRecordMapping
{
    /** Concept code of the record type "Registered file" (RT_REGISTERED_FILE). */
    public const RECTYPE = '2-1106';

    /** Record header => legacy column. rec_Title is made from the caption, name or URL. */
    public const HEADERS = array(
        'rec_ID' => 'ulf_ID',
        'rec_Added' => 'ulf_Added',
        'rec_Modified' => 'ulf_Modified',
        'rec_OwnerUGrpID' => 'ulf_UploaderUGrpID',
        'rec_AddedByUGrpID' => 'ulf_UploaderUGrpID',
        'rec_NonOwnerVisibility' => 'ulf_WhoCanView',
        'rec_URL' => 'ulf_ExternalFileReference',
        'rec_Hash' => 'ulf_ObfuscatedFileID',
        'rec_AddedByImport' => 'ulf_AddedByImport',
        'rec_OriginatingDBID' => 'ulf_OriginatingDBID',
        'rec_IDInOriginatingDB' => 'ulf_IDInOriginatingDB'
    );

    /**
     * Field concept code => legacy column. convert: ext (extension <-> term of 2-533), term (label <-> term),
     * size (KB <-> bytes); admin: given to database administrators only (server paths).
     */
    public const FIELDS = array(
        '2-1185' => array('column' => 'ulf_OrigFileName'),                    // DT_ORIGINAL_NAME
        '2-1' => array('column' => 'ulf_Caption'),                            // DT_NAME
        '2-4' => array('column' => 'ulf_Description'),                        // DT_EXTENDED_DESCRIPTION
        '2-35' => array('column' => 'ulf_Copyright'),                         // DT_COPYRIGHT
        '2-1188' => array('column' => 'ulf_Copyowner'),                       // DT_COPYOWNER
        '2-29' => array('column' => 'ulf_MimeExt', 'convert' => 'ext'),       // DT_FILE_EXT (File type)
        '2-67' => array('column' => 'ulf_FileSizeKB', 'convert' => 'size'),   // DT_FILE_SIZE (bytes)
        '2-68' => array('column' => 'ulf_MD5Checksum'),                       // DT_FILE_MD5
        '2-1186' => array('column' => 'ulf_PreferredSource', 'convert' => 'term'), // DT_REGISTERED_FILE_TYPE
        '2-1187' => array('column' => 'ulf_Parameters'),                      // DT_CONFIGURATION
        '2-62' => array('column' => 'ulf_FileName', 'admin' => true),         // DT_FILE_NAME (stored name)
        '2-63' => array('column' => 'ulf_FilePath', 'admin' => true)          // DT_FILE_FOLDER
    );

    /** Fields a client may write (concept codes); the others are set by the server (size, MD5, names, type). */
    public const WRITABLE_FIELDS = array('2-1185', '2-1', '2-4', '2-35', '2-1188', '2-1187');

    /** ulf_WhoCanView => rec_NonOwnerVisibility (an empty value is "viewable"). */
    private const VISIBILITY = array('viewable' => 'public', 'loginrequired' => 'viewable');

    private FileRepository $files;

    public function __construct(DefinitionLookup $definitions, FileRepository $files)
    {
        parent::__construct($definitions);
        $this->files = $files;
    }

    /** {@inheritdoc} */
    public function rectypeConcept(): string
    {
        return self::RECTYPE;
    }

    /** {@inheritdoc} */
    protected function fieldMappings(): array
    {
        return self::FIELDS;
    }

    /** {@inheritdoc} */
    protected function writableConcepts(): array
    {
        return self::WRITABLE_FIELDS;
    }

    /**
     * Record of one row of FileRepository::rows(): headers and detail values (database form:
     * strings, term ids for enum fields). Empty values are left out.
     *
     * @param bool $admin Whether the caller may see the server paths (DT_FILE_NAME, DT_FILE_FOLDER).
     * @return array{headers:array<string,?string>,details:array<int,array<int,string>>}
     */
    public function toRecord(array $row, bool $admin): array
    {
        $headers = array('rec_RecTypeID' => (string)$this->rectypeId(), 'rec_Title' => $this->title($row));
        foreach(self::HEADERS as $header => $column){
            $value = $row[$column] ?? null;
            if($header === 'rec_NonOwnerVisibility'){
                $value = self::VISIBILITY[strtolower(trim((string)$value))] ?? 'public';
            }
            $headers[$header] = $value === null ? null : (string)$value;
        }
        $headers['rec_ScratchPad'] = null;
        return array('headers' => $headers, 'details' => $this->detailsOf($row, $admin));
    }

    /**
     * Legacy columns of a stored file record (headers and detail values in database form) -
     * the reverse of toRecord(), used by the record store.
     *
     * @param array<string,mixed> $headers rec_* columns
     * @param array<int,array<int,string>> $details field id => values
     * @return array<string,mixed> ulf_* columns (and fxm_MimeType)
     */
    public function toColumns(array $headers, array $details): array
    {
        $columns = array();
        foreach(self::HEADERS as $header => $column){
            if($header === 'rec_AddedByUGrpID'){ continue; }
            $value = $headers[$header] ?? null;
            if($header === 'rec_NonOwnerVisibility'){ $value = self::legacyVisibility((string)$value) ?? 'viewable'; }
            $columns[$column] = $value;
        }
        $columns = array_merge($columns, $this->columnsOf($details));
        $columns['fxm_MimeType'] = $this->files->mimeTypes()[strtolower((string)($columns['ulf_MimeExt'] ?? ''))] ?? null;
        return $columns;
    }

    /** {@inheritdoc} Sizes, terms and MIME types are converted back to the legacy column values. */
    protected function columnValue(int $fieldId, array $mapping, string $value, array $details): ?string
    {
        switch($mapping['convert'] ?? ''){
            case 'size':
                $bytes = intval($value);
                return (string)($bytes > 0 && $bytes < 1024 ? 1 : intdiv($bytes, 1024));
            case 'term':
                $term = $this->definitions->term(intval($value));
                return $term === null ? null : (string)$term['term'];
            case 'ext':
                $term = $this->definitions->term(intval($value));
                return $term === null ? null : self::termExtension($term);
            default:
                return $value;
        }
    }

    /** Title of a file: the caption, else the original name, else the URL (remote files are named "_remote"). */
    public function title(array $row): string
    {
        $caption = trim((string)($row['ulf_Caption'] ?? ''));
        if($caption !== ''){ return $caption; }
        $name = trim((string)($row['ulf_OrigFileName'] ?? ''));
        if($name !== '' && strpos($name, '_') !== 0){ return $name; }
        $url = trim((string)($row['ulf_ExternalFileReference'] ?? ''));
        return $url !== '' ? $url : $name;
    }

    /**
     * SQL expression of title() on the file table alias (sorting and the title predicate).
     */
    public static function titleSql(string $alias): string
    {
        return 'COALESCE(NULLIF(TRIM('.$alias.'.ulf_Caption),\'\'),IF('.$alias.'.ulf_OrigFileName LIKE \'\\_%\','
            .'COALESCE(NULLIF('.$alias.'.ulf_ExternalFileReference,\'\'),'.$alias.'.ulf_OrigFileName),'
            .$alias.'.ulf_OrigFileName))';
    }

    /** ulf_WhoCanView values of a record visibility (public, viewable). */
    public static function legacyVisibility(string $visibility): ?string
    {
        $legacy = array_search(strtolower(trim($visibility)), self::VISIBILITY, true);
        return $legacy === false ? null : $legacy;
    }

    /**
     * Legacy column values of one value of an enum field (a term id, or a label): the extensions
     * of a file type term or group (with its child terms), the registered file type of a term.
     *
     * @return array<int,string> lower-case column values
     */
    public function legacyEnumValues(int $fieldId, $value): array
    {
        $mapping = $this->fields()[$fieldId] ?? null;
        if($mapping === null){ return array(); }
        $isExt = ($mapping['convert'] ?? '') === 'ext';
        $text = strtolower(trim((string)$value));
        $labels = array();
        if(ctype_digit($text)){
            foreach($this->definitions->termDescendants(array(intval($text))) as $termId){
                $term = $this->definitions->term($termId);
                if($term === null){ continue; }
                // a group of file types has no extension
                $label = $isExt ? self::termExtension($term) : strtolower(trim((string)$term['term']));
                if($label !== null && $label !== ''){ $labels[] = $label; }
            }
        }elseif($text !== ''){
            $labels[] = $text;
        }
        if(!$isExt){ return array_values(array_unique($labels)); }
        // file type: a label is an extension ("jpg"), also a MIME type ("image/jpeg") or a MIME group ("image/*")
        $result = array();
        foreach($labels as $label){
            if(strpos($label, '/') === false){ $result[] = $label; continue; }
            $prefix = substr($label, -2) === '/*' ? substr($label, 0, -1) : null;
            foreach($this->files->mimeTypes() as $extension => $mime){
                if($prefix !== null ? strpos($mime, $prefix) === 0 : $mime === $label){ $result[] = $extension; }
            }
        }
        return array_values(array_unique($result));
    }

    /** {@inheritdoc} Sizes in bytes, terms and MIME types from the legacy column values. */
    protected function fieldValue(int $fieldId, array $mapping, array $row): ?string
    {
        $value = $row[$mapping['column']] ?? null;
        if($value === null || trim((string)$value) === ''){ return null; }
        switch($mapping['convert'] ?? ''){
            case 'size':
                return (string)(intval($value) * 1024);
            case 'term':
                return $this->termId($fieldId, (string)$value);
            case 'ext':
                // the term of the extension; a database without the vocabulary of extensions yet: its MIME type term
                $extension = strtolower(trim((string)$value));
                return $this->termId($fieldId, $extension)
                    ?? $this->termId($fieldId, $this->files->mimeTypes()[$extension] ?? '');
            default:
                return (string)$value;
        }
    }

    /**
     * MIME type of a file record (header fxm_MimeType of fields=_all, 2026-10-27): from its file type
     * value - a term id, or a resolved value {value, trm_Code} - through defFileExtToMimetype.
     *
     * @param mixed $value Value of field 2-29.
     */
    public function mimeTypeOfValue($value): ?string
    {
        if(is_array($value)){ $value = $value['value'] ?? null; }
        $term = ctype_digit((string)$value) ? $this->definitions->term(intval($value)) : null;
        $extension = $term === null ? null : self::termExtension($term);
        return $extension === null ? null : ($this->files->mimeTypes()[$extension] ?? null);
    }

    /** MIME type of an extension (defFileExtToMimetype), null when unknown. */
    public function mimeTypeOfExtension(string $extension): ?string
    {
        return $this->files->mimeTypes()[strtolower(trim($extension))] ?? null;
    }

    /**
     * Extension of a file type term: its code (label = code = extension since 2026-10-27; the code
     * of a MIME type term of the older vocabulary is its main extension). Null for a group (no code).
     */
    private static function termExtension(array $term): ?string
    {
        $code = strtolower(trim((string)($term['code'] ?? '')));
        return $code === '' ? null : $code;
    }
}
