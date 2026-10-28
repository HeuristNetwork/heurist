<?php
/**
* FileStorage.php - Files of registered files on disk (port of DbRecUploadedFiles save/delete)
*
* Moves a finished upload to file_uploads/ulf_<id>_<name>, its thumbnail to
* filethumbs/ulf_<key>.png, extracts tiled image stacks (zip) to
* uploaded_tilestacks/<id>/ or copies .mbtiles there, and removes the files of a
* deleted registration (file, thumbnail, cached copies). Names are those of the
* legacy code, so legacy readers (fileDownload.php) keep working.
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

use RuntimeException;

/** Disk operations of the file store. */
final class FileStorage
{
    /** Image types of tiles: only these entries of a tile stack zip are extracted (2026-10-27). */
    public const TILE_IMAGE_EXTENSIONS = array('png', 'jpg', 'jpeg', 'gif', 'webp');

    private FilePaths $paths;

    public function __construct(FilePaths $paths)
    {
        $this->paths = $paths;
    }

    public function paths(): FilePaths
    {
        return $this->paths;
    }

    /** New obfuscated key of a file (legacy: sha1 of the id and a random number). */
    public function newKey(int $fileId): string
    {
        return sha1($fileId.'.'.random_int(0, 99));
    }

    /** Stored name of an uploaded file (legacy ulf_FileName). */
    public static function storedName(int $fileId, string $originalName): string
    {
        $name = str_replace(array('/', '\\', "\0"), '_', $originalName);
        return 'ulf_'.$fileId.'_'.$name;
    }

    /**
     * Move a finished upload (ChunkedUpload::take) to the uploads folder; its thumbnail to the
     * thumbnails folder. A file of the same name is replaced (new content of a file).
     *
     * @return array{ulf_FilePath:string,ulf_FileName:string}
     * @throws RuntimeException When the file cannot be moved.
     */
    public function placeUpload(array $upload, int $fileId, string $originalName, string $key): array
    {
        $name = self::storedName($fileId, $originalName);
        $this->ensureFolder($this->paths->uploads);
        $this->move((string)$upload['path'], $this->paths->uploads.$name);
        $this->placeThumbnail($upload['thumbnail'] ?? null, $key);
        return array('ulf_FilePath' => FilePaths::UPLOADS_RELATIVE, 'ulf_FileName' => $name);
    }

    /**
     * Place a tiled image stack: .mbtiles is copied to the tile folder, a zip is extracted to
     * <tiles>/<id>/ (legacy). The stack is then referred to by ulf_ExternalFileReference.
     *
     * @return array<string,mixed> Columns: ulf_ExternalFileReference, ulf_FilePath, ulf_FileName,
     *         and for a zip ulf_FileSizeKB and ulf_MimeExt (type of its first image).
     * @throws RuntimeException When the stack cannot be placed.
     */
    public function placeTiles(array $upload, int $fileId, string $originalName, string $key): array
    {
        $this->ensureFolder($this->paths->tiles);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if($ext === 'mbtiles'){
            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME)).'.mbtiles';
            $this->move((string)$upload['path'], $this->paths->tiles.$name);
            return array('ulf_ExternalFileReference' => $name, 'ulf_FilePath' => '', 'ulf_FileName' => null);
        }
        if($ext !== 'zip'){ throw new RuntimeException('A tiled image stack must be a zip or mbtiles file'); }
        $folder = $this->paths->tiles.$fileId.'/';
        $this->extract((string)$upload['path'], $folder);
        @unlink((string)$upload['path']);
        $first = $this->firstImage($folder);
        $mimeExt = 'png';
        if($first !== null){
            $mimeExt = strtolower(pathinfo($first, PATHINFO_EXTENSION)) ?: 'png';
            $this->ensureFolder($this->paths->thumbs);
            ImageThumbnail::create($first, $this->paths->thumbs.'ulf_'.$key.'.png');
        }
        return array('ulf_ExternalFileReference' => $fileId.'/', 'ulf_FilePath' => '', 'ulf_FileName' => null,
            'ulf_FileSizeKB' => max(1, intdiv($this->folderSize($folder), 1024)), 'ulf_MimeExt' => $mimeExt);
    }

    /**
     * Local path of a file row (null for remote files or a path outside the file store).
     */
    public function localPath(array $row): ?string
    {
        $name = (string)($row['ulf_FileName'] ?? '');
        if($name === ''){ return null; }
        $folder = str_replace('\\', '/', (string)($row['ulf_FilePath'] ?? ''));
        if($folder === '' || ($folder[0] !== '/' && !preg_match('/^[A-Za-z]:\//', $folder))){
            $folder = $this->paths->root.$folder;
        }
        $path = rtrim($folder, '/').'/'.$name;
        return $this->inStore($path) ? $path : null;
    }

    /** Remove the files of a deleted registration: the file, thumbnail and cached copies (tile stacks stay, as legacy). */
    public function remove(array $row): void
    {
        $path = $this->localPath($row);
        if($path !== null && is_file($path)){ @unlink($path); }
        $this->removeThumbnail((string)($row['ulf_ObfuscatedFileID'] ?? ''));
        $name = (string)($row['ulf_FileName'] ?? '');
        if($name !== ''){
            foreach(array($this->paths->webCache.$name.'.jpg', $this->paths->webCache.$name.'.png',
                $this->paths->blurredCache.$name.'.png') as $cached){
                if(is_file($cached)){ @unlink($cached); }
            }
        }
    }

    /** Remove the thumbnail of a file (new content gives a new one). */
    public function removeThumbnail(string $key): void
    {
        $key = preg_replace('/[^a-z0-9]/i', '', $key);
        if($key !== '' && is_file($this->paths->thumbs.'ulf_'.$key.'.png')){
            @unlink($this->paths->thumbs.'ulf_'.$key.'.png');
        }
    }

    /** Thumbnail file of a key, or null when it was not made yet. */
    public function thumbnailPath(string $key): ?string
    {
        $key = preg_replace('/[^a-z0-9]/i', '', $key);
        $path = $this->paths->thumbs.'ulf_'.$key.'.png';
        return $key !== '' && is_file($path) ? $path : null;
    }

    /** Bytes used by uploaded files and tile stacks (the disk quota counts these, as legacy). */
    public function usage(): int
    {
        return $this->folderSize($this->paths->uploads) + $this->folderSize($this->paths->tiles);
    }

    private function placeThumbnail(?string $thumbnail, string $key): void
    {
        $this->removeThumbnail($key);
        if($thumbnail === null || !is_file($thumbnail)){ return; }
        $this->ensureFolder($this->paths->thumbs);
        $this->move($thumbnail, $this->paths->thumbs.'ulf_'.$key.'.png');
    }

    private function move(string $source, string $target): void
    {
        if(!is_file($source)){ throw new RuntimeException('The uploaded file is missing'); }
        if(is_file($target)){ @unlink($target); }
        if(!@rename($source, $target)){
            if(!@copy($source, $target)){ throw new RuntimeException('The file cannot be stored'); }
            @unlink($source);
        }
    }

    /**
     * Extract the tile images of a zip, refusing entries that would leave the folder. Other files
     * (scripts, .htaccess, the html / xml pages of gdal2tiles) are not extracted: the folder is in
     * the file store, which the web server may serve (2026-10-27).
     */
    private function extract(string $zipPath, string $folder): void
    {
        if(!class_exists('ZipArchive')){ throw new RuntimeException('Zip files cannot be read on this server'); }
        $zip = new \ZipArchive();
        if($zip->open($zipPath) !== true){ throw new RuntimeException('The zip file cannot be read'); }
        $images = array();
        for($index = 0; $index < $zip->numFiles; $index++){
            $name = (string)$zip->getNameIndex($index);
            $entry = str_replace('\\', '/', $name);
            if($entry === '' || $entry[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $entry) || preg_match('/^[A-Za-z]:/', $entry)){
                $zip->close();
                throw new RuntimeException('The zip file has an invalid entry: '.$entry);
            }
            if(self::isTileImage($entry)){ $images[] = $name; }
        }
        if(empty($images)){
            $zip->close();
            throw new RuntimeException('The zip file has no tile images ('.implode(', ', self::TILE_IMAGE_EXTENSIONS).')');
        }
        $this->ensureFolder($folder);
        $done = $zip->extractTo($folder, $images);
        $zip->close();
        if(!$done){ throw new RuntimeException('The zip file cannot be extracted'); }
    }

    /** Whether a zip entry is a tile image: a file of TILE_IMAGE_EXTENSIONS, not of the upload stop list. */
    public static function isTileImage(string $entry): bool
    {
        if($entry === '' || substr($entry, -1) === '/' || preg_match(Upload\ChunkedUpload::DENIED, $entry)){ return false; }
        return in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), self::TILE_IMAGE_EXTENSIONS, true);
    }

    private function firstImage(string $folder): ?string
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
        foreach($files as $file){
            if(in_array(strtolower($file->getExtension()), self::TILE_IMAGE_EXTENSIONS, true)){
                return str_replace('\\', '/', $file->getPathname());
            }
        }
        return null;
    }

    private function folderSize(string $folder): int
    {
        if(!is_dir($folder)){ return 0; }
        $size = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
        foreach($files as $file){
            if($file->isFile()){ $size += $file->getSize(); }
        }
        return $size;
    }

    private function ensureFolder(string $folder): void
    {
        if(!is_dir($folder) && !@mkdir($folder, 0775, true) && !is_dir($folder)){
            throw new RuntimeException('Folder cannot be created');
        }
    }

    /** Whether a path is inside the database file store. */
    private function inStore(string $path): bool
    {
        $root = realpath($this->paths->root);
        $directory = realpath(dirname($path));
        if($root === false || $directory === false){ return false; }
        $root = rtrim(str_replace('\\', '/', $root), '/').'/';
        $directory = rtrim(str_replace('\\', '/', $directory), '/').'/';
        return strpos($directory, $root) === 0;
    }
}
