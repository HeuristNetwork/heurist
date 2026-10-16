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
