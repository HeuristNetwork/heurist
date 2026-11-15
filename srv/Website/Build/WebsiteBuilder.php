<?php
/**
* WebsiteBuilder.php - Writes a v4 website into files (plan 27b §7, first build)
*
* Every page of a website (home and menu pages) or a standalone page, in every
* site language, rendered by PageRenderer with the rights of a visitor who is
* not logged in (the WebsiteService given here must read as a guest: no author
* session or private record goes into the output). Files: `{lang}/index.html`
* (home or standalone page), `{lang}/{page id}.html`, and `index.html` at the
* root opening the default language. Links between pages are relative; the site
* runtime, module bundles and data still come from the Heurist server (a built
* site is not an offline copy). Serving built sites is plan 27, phase 6.
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

use Heurist\Website\PageRenderer;
use Heurist\Website\WebsiteException;
use Heurist\Website\WebsiteService;
use RuntimeException;

/** Renders all pages of a site into a folder. */
final class WebsiteBuilder
{
    private WebsiteService $service;
    /** @var callable(string):PageRenderer Renderer with file links, by default language folder. */
    private $renderer;

    /**
     * @param WebsiteService $service Service reading with guest rights.
     * @param callable(string):PageRenderer $renderer Renderer for a default language (SiteUrls::files()).
     */
    public function __construct(WebsiteService $service, callable $renderer)
    {
        $this->service = $service;
        $this->renderer = $renderer;
    }

    /**
     * Write the site into a folder.
     *
     * @param int $id Website or standalone page.
     * @param string $directory Empty or new folder.
     * @param callable|null $check Called before each page (Stop, time limit).
     * @param callable|null $progress fn(int done, int total, string message).
     * @return array{files:array<int,string>,pages:int,languages:array<int,string>,title:string}
     * @throws WebsiteException When a visitor can not see the site (not public) or it is not v4.
     */
    public function build(int $id, string $directory, ?callable $check = null, ?callable $progress = null): array
    {
        try{
            $start = $this->service->context($id);
        }catch(WebsiteException $exception){
            throw $exception->status() === 403
                ? new WebsiteException(403, 'A visitor can not see record '.$id.'. Make the website and its pages public first.')
                : $exception;
        }
        $languages = !empty($start['languages']) ? $start['languages'] : array('ENG');
        $folders = array_map('strtolower', $languages);
        $renderer = ($this->renderer)($folders[0]);
        $pageIds = array_merge(array(0), $start['site'] !== null ? array_map('intval', array_keys($start['pages'])) : array());
        // a Startup page is index.html: the home page is written as {website id}.html (its links)
        if(!empty($start['startPage'])){ $pageIds[] = intval($start['site']['rec_ID']); }
        $total = count($pageIds) * count($languages);
        $files = array();
        $done = 0;

        foreach($languages as $position => $language){
            foreach($pageIds as $pageId){
                if($check !== null){ $check(); }
                $context = $this->service->context($id, $pageId, $language);
                // a record page shows a record of its URL: live only, its links go to the server (plan 27g)
                if(!empty($context['recordPage'])){
                    $done++;
                    continue;
                }
                $name = $folders[$position].'/'.($pageId > 0 ? $pageId : 'index').'.html';
                $this->write($directory, $name, $renderer->render($context));
                $files[] = $name;
                $done++;
                if($progress !== null){ $progress($done, $total, 'Page '.$done.' of '.$total); }
            }
        }
        $this->write($directory, 'index.html', self::redirect($folders[0].'/index.html'));
        $files[] = 'index.html';
        $title = (string)(($start['site'] ?? $start['page'])['rec_Title'] ?? '');
        return array('files' => $files, 'pages' => count($pageIds), 'languages' => $folders, 'title' => $title);
    }

    /**
     * Zip a built folder.
     *
     * @return int Size of the zip file in bytes.
     */
    public static function zip(string $directory, array $files, string $zipFile): int
    {
        if(!class_exists('ZipArchive')){
            throw new RuntimeException('The PHP zip extension is needed to generate a website');
        }
        $zip = new \ZipArchive();
        if($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true){
            throw new RuntimeException('Cannot create the website zip file');
        }
        foreach($files as $name){
            $zip->addFile(rtrim($directory, '/\\').'/'.$name, $name);
        }
        $zip->close();
        return (int)filesize($zipFile);
    }

    /** Remove a built folder (after zipping). */
    public static function remove(string $directory, array $files): void
    {
        $root = rtrim($directory, '/\\');
        foreach($files as $name){ @unlink($root.'/'.$name); }
        foreach(array_unique(array_map('dirname', $files)) as $folder){
            if($folder !== '.'){ @rmdir($root.'/'.$folder); }
        }
        @rmdir($root);
    }

    private function write(string $directory, string $name, string $content): void
    {
        $path = rtrim($directory, '/\\').'/'.$name;
        $folder = dirname($path);
        if(!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)){
            throw new RuntimeException('Cannot create folder '.$folder);
        }
        if(file_put_contents($path, $content) === false){
            throw new RuntimeException('Cannot write '.$name);
        }
    }

    /** Root page that opens the default language. */
    private static function redirect(string $target): string
    {
        $url = htmlspecialchars($target, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0; url='.$url.'">'
            .'<link rel="canonical" href="'.$url.'"><title>Heurist website</title></head>'
            .'<body><p><a href="'.$url.'">Open the website</a></p></body></html>';
    }
}
