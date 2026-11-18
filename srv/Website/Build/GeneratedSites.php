<?php
/**
* GeneratedSites.php - Generated websites in the database folder (plan 27m §2)
*
* `generated-sites/{site id}/`: `{lang}/index.html` (what the URL without a page shows), `{lang}/{page id}.html`,
* `{lang}/{page id}/{record id}.html` (record pages, §2.1), `sitemap.xml` and `manifest.json` (build time, user,
* languages, counts, size, options, the latest change of the site and its pages). The live URL of a page is served
* from these files when they exist (WebsiteController); a build is written beside the folder and replaces it only
* when it is complete; there is no previous copy.
*
* @project     Heurist academic knowledge management system
* @package     Website\Build
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Website\Build;

use RuntimeException;

/** Folders of the generated websites of one database. */
final class GeneratedSites
{
    public const MANIFEST = 'manifest.json';
    public const SITEMAP = 'sitemap.xml';

    /** The generated-sites folder ('' = not available: nothing is served). */
    private string $root;

    /** @param string $root The generated-sites folder of the database (may not exist yet). */
    public function __construct(string $root)
    {
        $this->root = $root !== '' ? rtrim(str_replace('\\', '/', $root), '/') : '';
    }

    /** Folder of a site (it may not exist). */
    public function folder(int $id): string
    {
        return $this->root.'/'.max(0, $id);
    }

    /** A new empty folder for a build of a site, beside the site folder (same disk: replaced by a rename). */
    public function buildFolder(int $id, string $suffix): string
    {
        if($this->root === ''){ throw new RuntimeException('The generated-sites folder of this database is not known'); }
        $folder = $this->root.'/.build-'.max(0, $id).'-'.preg_replace('/[^A-Za-z0-9_-]/', '', $suffix);
        self::removeFolder($folder);
        if(!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)){
            throw new RuntimeException('Cannot create the folder for the generated website. Check permissions for the generated-sites folder');
        }
        return $folder;
    }

    /** The manifest of the generated site, or null when there is none. */
    public function manifest(int $id): ?array
    {
        if($this->root === '' || $id < 1){ return null; }
        $file = $this->folder($id).'/'.self::MANIFEST;
        if(!is_file($file)){ return null; }
        $manifest = json_decode((string)file_get_contents($file), true);
        return is_array($manifest) ? $manifest : null;
    }

    /**
     * The file of a page of the generated site, or null (not generated: the live page).
     *
     * @param string $language Language folder (3 letters, lower case); '' = the default language of the build.
     * @param int $pageId Page of the URL (0: the URL without a page).
     * @param int $recordId Record of a record page (0: none).
     */
    public function pageFile(int $id, string $language, int $pageId, int $recordId = 0): ?string
    {
        $manifest = $this->manifest($id);
        if($manifest === null){ return null; }
        $languages = is_array($manifest['languages'] ?? null) ? $manifest['languages'] : array();
        $language = $language !== '' ? strtolower($language) : (string)($manifest['defaultLanguage'] ?? ($languages[0] ?? ''));
        if(!in_array($language, $languages, true) || preg_match('/^[a-z]{2,3}$/', $language) !== 1){ return null; }
        $name = $recordId > 0 ? ($pageId > 0 ? $pageId.'/'.$recordId : '') : ($pageId > 0 ? (string)$pageId : 'index');
        if($name === ''){ return null; }
        $file = $this->folder($id).'/'.$language.'/'.$name.'.html';
        return is_file($file) ? $file : null;
    }

    /** The sitemap of the generated site, or null. */
    public function sitemapFile(int $id): ?string
    {
        if($this->manifest($id) === null){ return null; }
        $file = $this->folder($id).'/'.self::SITEMAP;
        return is_file($file) ? $file : null;
    }

    /**
     * Put a complete build in place of the site folder (the former one is removed).
     *
     * @param string $buildFolder buildFolder() with all files and the manifest.
     */
    public function install(int $id, string $buildFolder): void
    {
        $target = $this->folder($id);
        $old = $this->root.'/.old-'.$id.'-'.getmypid();
        if(is_dir($target) && !@rename($target, $old)){
            throw new RuntimeException('Cannot replace the generated website. Check permissions for the generated-sites folder');
        }
        if(!@rename($buildFolder, $target)){
            if(is_dir($old)){ @rename($old, $target); }
            throw new RuntimeException('Cannot move the generated website into place');
        }
        self::removeFolder($old);
    }

    /** Remove the generated site (its live pages are shown again). @return bool Whether there was one. */
    public function remove(int $id): bool
    {
        if($this->root === '' || $id < 1 || !is_dir($this->folder($id))){ return false; }
        self::removeFolder($this->folder($id));
        if(is_dir($this->folder($id))){
            throw new RuntimeException('Cannot remove the generated website. Check permissions for the generated-sites folder');
        }
        return true;
    }

    /**
     * What the Website manager shows (`meta.generated`): date, user, languages, counts, size, options, the latest change
     * of the site and its pages at the build; null without a generated site.
     */
    public function info(int $id): ?array
    {
        $manifest = $this->manifest($id);
        if($manifest === null){ return null; }
        return array_intersect_key($manifest, array_flip(array('generatedAt', 'user', 'languages', 'defaultLanguage', 'pages',
            'records', 'recordPages', 'files', 'size', 'options', 'modified')));
    }

    /** Number of files and bytes of a folder (manifest). @return array{files:int,size:int} */
    public static function measure(string $folder): array
    {
        $files = 0;
        $size = 0;
        if(is_dir($folder)){
            $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
            foreach($items as $item){
                if($item->isFile()){
                    $files++;
                    $size += (int)$item->getSize();
                }
            }
        }
        return array('files' => $files, 'size' => $size);
    }

    /** Remove a folder with all its files. */
    public static function removeFolder(string $folder): void
    {
        if(!is_dir($folder)){ return; }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST);
        foreach($items as $item){
            if($item->isDir()){ @rmdir($item->getPathname()); }else{ @unlink($item->getPathname()); }
        }
        @rmdir($folder);
    }
}
