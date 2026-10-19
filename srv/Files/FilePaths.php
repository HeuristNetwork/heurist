<?php
/**
* FilePaths.php - Folders of the file store of one database
*
* The folders used by uploads and registered files (legacy System::getSysDir
* names): the database root, file_uploads, filethumbs, uploaded_tilestacks,
* scratch and the image caches. Built by ServiceFactory; tests give their own.
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

/** Absolute folder paths, each ending with "/". */
final class FilePaths
{
    /** Folder of uploaded files relative to the database root, as stored in ulf_FilePath. */
    public const UPLOADS_RELATIVE = 'file_uploads/';

    public string $root;
    public string $uploads;
    public string $thumbs;
    public string $tiles;
    public string $scratch;
    public string $webCache;
    public string $blurredCache;

    /**
     * @param string $root Database file store root.
     * @param array<string,string> $folders Optional overrides: uploads, thumbs, tiles, scratch, webCache, blurredCache.
     */
    public function __construct(string $root, array $folders = array())
    {
        $this->root = self::folder($root);
        $this->uploads = self::folder($folders['uploads'] ?? $this->root.self::UPLOADS_RELATIVE);
        $this->thumbs = self::folder($folders['thumbs'] ?? $this->root.'filethumbs/');
        $this->tiles = self::folder($folders['tiles'] ?? $this->root.'uploaded_tilestacks/');
        $this->scratch = self::folder($folders['scratch'] ?? $this->root.'scratch/');
        $this->webCache = self::folder($folders['webCache'] ?? $this->root.'webimagecache/');
        $this->blurredCache = self::folder($folders['blurredCache'] ?? $this->root.'blurredimagescache/');
    }

    /** Folder of unfinished and unregistered uploads. */
    public function uploadSessions(): string
    {
        return $this->scratch.'uploads/';
    }

    private static function folder(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        return $path === '' ? '' : rtrim($path, '/').'/';
    }
}
