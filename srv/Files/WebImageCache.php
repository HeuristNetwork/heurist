<?php
/**
* WebImageCache.php - Reduced web copies of large images (GET /files/{key}/web)
*
* The srv side of the legacy web image cache (recordFile.php getWebImageCache): a JPEG of at most
* 400×400 pixels of a local image of 200 KB or more, in `webimagecache/`. Copies are named by the
* obfuscated key (`<key>.jpg`, 2026-11-16); the legacy code named them by the stored file name without
* extension, so files of the same name shared one copy. Such an old copy is taken (renamed to the key)
* only when no other file has that name. Blurred copies (`blurredimagescache/`) are removed with them.
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

/** Web copies of large images, named by the file key. */
final class WebImageCache
{
    /** Largest width and height of a web copy (legacy). */
    public const SIZE = 400;

    /** Smaller files are sent as they are (legacy, KB). */
    public const MIN_FILE_KB = 200;

    private FilePaths $paths;

    public function __construct(FilePaths $paths)
    {
        $this->paths = $paths;
    }

    /**
     * Path of the web copy of a file, made when missing; null when the original is to be sent (not a local
     * image, a small file, or GD can not read it).
     *
     * @param array $row File row (ulf_ObfuscatedFileID, ulf_FileName, ulf_FileSizeKB).
     * @param string $source Local path of the file (FileStorage::localPath).
     * @param FileRowsInterface $rows To check that an old copy is this file's.
     */
    public function get(array $row, string $source, FileRowsInterface $rows): ?string
    {
        $key = self::key($row);
        if($key === '' || intval($row['ulf_FileSizeKB'] ?? 0) < self::MIN_FILE_KB || !is_file($source)){ return null; }
        $path = $this->paths->webCache.$key.'.jpg';
        if(is_file($path)){ return $path; }
        if(!is_dir($this->paths->webCache) && !@mkdir($this->paths->webCache, 0777, true)){ return null; }

        $old = $this->paths->webCache.self::oldName($row).'.jpg';
        if(self::oldName($row) !== '' && is_file($old) && $rows->storedNameUnique(self::oldName($row))
            && @rename($old, $path)){
            return $path;
        }
        return ImageThumbnail::create($source, $path, self::SIZE, true) ? $path : null;
    }

    /**
     * Remove the cached copies of a file (deleted, or new content): by key, and by the old name (as legacy: a
     * copy shared by files of the same name is made again when needed).
     */
    public function remove(array $row): void
    {
        $names = array_filter(array(self::key($row), self::oldName($row)), static function($name){ return $name !== ''; });
        foreach($names as $name){
            foreach(array($this->paths->webCache.$name.'.jpg', $this->paths->webCache.$name.'.png',
                $this->paths->blurredCache.$name.'.png') as $cached){
                if(is_file($cached)){ @unlink($cached); }
            }
        }
    }

    /** Obfuscated key of a row, only letters and digits. */
    private static function key(array $row): string
    {
        return (string)preg_replace('/[^a-z0-9]/i', '', (string)($row['ulf_ObfuscatedFileID'] ?? ''));
    }

    /** Legacy name of the copies: the stored file name without extension (no folder parts). */
    private static function oldName(array $row): string
    {
        $name = basename(str_replace('\\', '/', (string)($row['ulf_FileName'] ?? '')));
        return $name === '' ? '' : pathinfo($name, PATHINFO_FILENAME);
    }
}
