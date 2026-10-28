<?php
/**
* FileRepository.php - All SQL that reads uploaded files
*
* The one srv class that reads recUploadedFiles and defFileExtToMimetype (plan 15).
* A move of files to a record type changes this class only. Services never query
* these tables themselves - add a method here.
* File information (by id or obfuscated id) and existence.
*
* @project     Heurist academic knowledge management system
* @package     Records\Files
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Files;

use Heurist\Database\DatabaseInterface;
use Heurist\Definitions\DefinitionLookup;
use WeakMap;

/** Uploaded file reads. */
final class FileRepository
{
    private const BATCH_SIZE = 1000;

    /** File tables by key, for SQL text built by the search compiler (Compiler/SqlTables). */
    public const TABLES = array('files' => 'recUploadedFiles', 'mimeTypes' => 'defFileExtToMimetype');

    /** @var WeakMap<DatabaseInterface,self>|null */
    private static ?WeakMap $instances = null;

    private DatabaseInterface $database;

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /** The repository of a connection (one instance per connection). */
    public static function shared(DatabaseInterface $database): self
    {
        if(self::$instances === null){ self::$instances = new WeakMap(); }
        if(!isset(self::$instances[$database])){
            self::$instances[$database] = new self($database);
        }
        return self::$instances[$database];
    }

    /**
     * File information with its MIME type, in the order of the ids; unknown files left out.
     *
     * @param array<int,int> $ids ulf_ID
     * @return array<int,array<string,mixed>> id => ulf_ID, fullPath, ulf_ExternalFileReference, fxm_MimeType,
     *         ulf_PreferredSource, ulf_OrigFileName, ulf_FileSizeKB, ulf_ObfuscatedFileID, ulf_Description,
     *         ulf_Added, ulf_MimeExt, ulf_Caption, ulf_Copyright, ulf_Copyowner, ulf_Parameters, ulf_WhoCanView
     */
    public function files(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        return $this->load('ulf_ID', $ids);
    }

    /**
     * File information by obfuscated id (the "file" parameter of file URLs); unknown files left out.
     *
     * @param array<int,string> $ids ulf_ObfuscatedFileID
     * @return array<string,array<string,mixed>> obfuscated id => row of files()
     */
    public function filesByObfuscatedId(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), static function($id){
            return preg_match('/^[a-z0-9]+$/i', $id) === 1;
        })));
        return $this->load('ulf_ObfuscatedFileID', $ids);
    }

    /**
     * The ids that are uploaded files.
     *
     * @param array<int,int> $ids
     * @return array<int,int>
     */
    public function existing(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        $found = array();
        foreach(array_chunk($ids, self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchColumn('SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ID IN ('
                .implode(',', array_fill(0, count($chunk), '?')).')', $chunk) as $id){
                $found[] = intval($id);
            }
        }
        return $found;
    }

    /** Columns of rows(): every column of a file except the thumbnail blob. */
    public const COLUMNS = array('ulf_ID', 'ulf_OrigFileName', 'ulf_UploaderUGrpID', 'ulf_Added', 'ulf_Modified',
        'ulf_ObfuscatedFileID', 'ulf_ExternalFileReference', 'ulf_PreferredSource', 'ulf_Caption', 'ulf_Description',
        'ulf_Copyright', 'ulf_Copyowner', 'ulf_MimeExt', 'ulf_AddedByImport', 'ulf_FileSizeKB', 'ulf_FilePath',
        'ulf_FileName', 'ulf_Parameters', 'ulf_WhoCanView', 'ulf_MD5Checksum', 'ulf_OriginatingDBID',
        'ulf_IDInOriginatingDB');

    /** @var array<string,string>|null Extension => MIME type, loaded once. */
    private ?array $mimeTypes = null;

    /**
     * Whole rows of files (COLUMNS + fxm_MimeType) for the /files domain (plan 20), in the order of the ids.
     *
     * @param array<int,int> $ids ulf_ID
     * @return array<int,array<string,mixed>> id => row
     */
    public function rows(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){ return $id > 0; })));
        $found = array();
        foreach(array_chunk($ids, self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchAll('SELECT f.'.implode(',f.', self::COLUMNS).',m.fxm_MimeType '
                .'FROM recUploadedFiles f LEFT JOIN defFileExtToMimetype m ON m.fxm_Extension=f.ulf_MimeExt '
                .'WHERE f.ulf_ID IN ('.implode(',', array_fill(0, count($chunk), '?')).')', $chunk) as $row){
                $found[intval($row['ulf_ID'])] = $row;
            }
        }
        $result = array();
        foreach($ids as $id){
            if(isset($found[$id])){ $result[$id] = $found[$id]; }
        }
        return $result;
    }

    /**
     * Files registered for an external URL (exact address, as legacy findRegistrationByUrl).
     *
     * @return array<int,int> ulf_ID
     */
    public function idsByUrl(string $url): array
    {
        return array_map('intval', $this->database->fetchColumn(
            'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference=? ORDER BY ulf_ID', array($url)));
    }

    /**
     * Files with the same content (MD5 checksum).
     *
     * @return array<int,int> ulf_ID
     */
    public function idsByChecksum(string $md5): array
    {
        if(!preg_match('/^[a-f0-9]{32}$/i', $md5)){ return array(); }
        return array_map('intval', $this->database->fetchColumn(
            'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_MD5Checksum=? ORDER BY ulf_ID', array(strtolower($md5))));
    }

    /**
     * Known file extensions with their MIME type (defFileExtToMimetype).
     *
     * @return array<string,string> lower-case extension => MIME type
     */
    public function mimeTypes(): array
    {
        if($this->mimeTypes === null){
            $this->mimeTypes = array();
            foreach($this->database->fetchAll('SELECT fxm_Extension,fxm_MimeType FROM defFileExtToMimetype ORDER BY fxm_Extension')
                as $row){
                $this->mimeTypes[strtolower(trim((string)$row['fxm_Extension']))] = strtolower(trim((string)$row['fxm_MimeType']));
            }
        }
        return $this->mimeTypes;
    }

    /**
     * Extensions that may be uploaded or registered (2026-10-27): those of mimeTypes() that are also a
     * term of the file type vocabulary 2-533 (TRM_FILE_EXT, codes = extensions). A database whose
     * vocabulary has no extension terms yet (MIME type labels) allows all of mimeTypes().
     *
     * @return array<string,string> lower-case extension => MIME type
     */
    public function allowedTypes(DefinitionLookup $definitions): array
    {
        $vocabulary = $definitions->constant('TRM_FILE_EXT');
        if($vocabulary < 1){ return $this->mimeTypes(); }
        $extensions = array();
        foreach($definitions->terms($definitions->termDescendants(array($vocabulary))) as $term){
            $code = strtolower(trim((string)($term['code'] ?? '')));
            // an extension term: label = code; a MIME type term of the older vocabulary has a "/" label
            if($code !== '' && strtolower(trim((string)$term['term'])) === $code){ $extensions[$code] = true; }
        }
        if(empty($extensions)){ return $this->mimeTypes(); }
        return array_intersect_key($this->mimeTypes(), $extensions);
    }

    /** Rows of files() by one key column, in the order of the keys. */
    private function load(string $column, array $keys): array
    {
        $found = array();
        foreach(array_chunk($keys, self::BATCH_SIZE) as $chunk){
            foreach($this->database->fetchAll(
                'SELECT f.ulf_ID,CONCAT(f.ulf_FilePath,f.ulf_FileName) AS fullPath,f.ulf_ExternalFileReference,m.fxm_MimeType,'
                .'f.ulf_PreferredSource,f.ulf_OrigFileName,f.ulf_FileSizeKB,f.ulf_ObfuscatedFileID,f.ulf_Description,'
                .'f.ulf_Added,f.ulf_MimeExt,f.ulf_Caption,f.ulf_Copyright,f.ulf_Copyowner,f.ulf_Parameters,f.ulf_WhoCanView '
                .'FROM recUploadedFiles f LEFT JOIN defFileExtToMimetype m ON m.fxm_Extension=f.ulf_MimeExt '
                .'WHERE f.'.$column.' IN ('.implode(',', array_fill(0, count($chunk), '?')).')',
                $chunk
            ) as $row){
                $key = $column === 'ulf_ID' ? intval($row['ulf_ID']) : strtolower((string)$row[$column]);
                if(!isset($found[$key])){ $found[$key] = $row; }
            }
        }
        $result = array();
        foreach($keys as $key){
            $lookup = $column === 'ulf_ID' ? $key : strtolower($key);
            if(isset($found[$lookup])){ $result[$key] = $found[$lookup]; }
        }
        return $result;
    }

    /** Whether a file (row of files()) can give a thumbnail: an image, a media service or IIIF. */
    public static function hasThumbnail(array $file): bool
    {
        $mime = strtolower((string)($file['fxm_MimeType'] ?? ''));
        return strpos($mime, 'image') === 0
            || in_array($mime, array('video/youtube', 'video/vimeo', 'audio/soundcloud'), true)
            || strpos(strtolower((string)($file['ulf_PreferredSource'] ?? '')), 'iiif') === 0;
    }
}
