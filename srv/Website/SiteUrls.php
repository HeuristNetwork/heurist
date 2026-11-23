<?php
/**
* SiteUrls.php - URLs of the pages of a v4 website (plan 27a §5)
*
* Pretty form `/{db}/site/{id}[/{page id}]` (RequestRouter, action "site") or
* the parameter form `?db={db}&site={id}[&pageid={page id}]`, which works
* without the router. The form of the current request is kept.
* A built site (plan 27b §7, `files()`) links its pages as files
* `../{lang}/{page id}.html` (`index.html` for the home or a standalone page);
* instance frames stay on the live server. The sitemap of a site (plan 27m §5):
* `/{db}/site/{id}/sitemap.xml` (`&sitemap=1`).
* Plan 27m: a built site links its generated record pages as files `{lang}/{page}/{record}.html` (a file one folder
* deeper: `../../`); with the public base URL of a ZIP (§4) absolute() gives the URLs of canonical, alternates and
* sitemap. An own domain (§5, `domain()`): the pages of its website are `https://host/[{page}[/{record}]]`.
*
* @project     Heurist academic knowledge management system
* @package     Website
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Website;

/** Builds page and frame URLs. */
final class SiteUrls
{
    private string $database;
    private string $baseUrl;
    /** Server root of the pretty URLs ("https://host"), or '' for the parameter form. */
    private string $prettyRoot;
    /** Built site: folder of the default language ('' = live URLs). */
    private string $fileLanguage = '';
    /** Built site: folders between the written file and the site root (1: `{lang}/x.html`, 2: a record page). */
    private int $fileDepth = 1;
    /** Built site: its public URL (ends with "/"), '' = not known (no absolute URLs). */
    private string $fileBase = '';
    /** @var array<int,array<int,bool>> Built site: generated record pages, page id => record id => true. */
    private array $fileRecords = array();
    /** Own domain: its root ("https://host") and its website, '' / 0 = none. */
    private string $domainRoot = '';
    private int $domainSite = 0;

    /**
     * @param string $database Database name (without prefix).
     * @param string $baseUrl Heurist base URL (ends with "/").
     * @param bool $pretty Use /{db}/site/... URLs.
     */
    public function __construct(string $database, string $baseUrl, bool $pretty)
    {
        $this->database = $database;
        $this->baseUrl = $baseUrl;
        $this->prettyRoot = '';
        if($pretty){
            $parts = parse_url($baseUrl);
            if(!empty($parts['host'])){
                $this->prettyRoot = ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }
    }

    /** Heurist base URL (ends with "/"). */
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * URLs of a built site: pages as relative files, frames on the live server (pretty form).
     *
     * @param string $defaultLanguage Folder of the default language (3 letters).
     */
    public static function files(string $database, string $baseUrl, string $defaultLanguage, string $publicBase = '',
        array $records = array()): self
    {
        $urls = new self($database, $baseUrl, true);
        $urls->fileLanguage = strtolower($defaultLanguage !== '' ? $defaultLanguage : 'eng');
        $urls->fileBase = $publicBase !== '' ? rtrim($publicBase, '/').'/' : '';
        $urls->fileRecords = $records;
        return $urls;
    }

    /**
     * URLs of the website of an own domain (plan 27m §5, domainWebsites.json `site`): `https://host/` the home page,
     * `/{page}`, `/{page}/{record}`; other sites and frames as `https://host/site/{id}/...` (the own-domain route).
     *
     * @param string $domainRoot "https://host" of the request.
     * @param int $siteId The website of the domain.
     */
    public static function domain(string $database, string $baseUrl, string $domainRoot, int $siteId): self
    {
        $urls = new self($database, $baseUrl, true);
        $urls->domainRoot = rtrim($domainRoot, '/');
        $urls->domainSite = $siteId;
        return $urls;
    }

    /** The same URLs for a file `$depth` folders below the site root (a record page of a built site: 2). */
    public function withDepth(int $depth): self
    {
        $urls = clone $this;
        $urls->fileDepth = max(1, $depth);
        return $urls;
    }

    /**
     * An absolute URL for canonical, alternates and the sitemap: a live URL as it is; a file of a built site with its
     * public base URL; '' when the base URL is not known (plan 27m §4).
     */
    public function absolute(string $url): string
    {
        if($this->fileLanguage === '' || preg_match('#^https?://#i', $url) === 1){ return $url; }
        if($this->fileBase === ''){ return ''; }
        return $this->fileBase.preg_replace('#^(?:\.\./)+#', '', $url);
    }

    /** Whether a built site has its public base URL. */
    public function hasPublicBase(): bool
    {
        return $this->fileBase !== '';
    }

    /**
     * URL of a website page (pageId 0: home) or of a standalone page (id = the page). `recordId`: the record shown by
     * a record page (plan 27g), `/{db}/site/{id}/{page}/{record}` (`&rec=`); a built site links it to the live server.
     */
    public function page(int $id, int $pageId = 0, string $language = '', int $recordId = 0): string
    {
        $lang = $language !== '' ? strtolower($language) : '';
        $up = str_repeat('../', $this->fileDepth);
        if($this->domainRoot !== '' && $id === $this->domainSite){
            return $this->domainRoot.'/'.($pageId > 0 ? $pageId.($recordId > 0 ? '/'.$recordId : '') : '')
                .($lang !== '' ? '?lang='.rawurlencode($lang) : '');
        }
        if($this->domainRoot !== ''){
            return $this->domainRoot.'/site/'.$id.($pageId > 0 ? '/'.$pageId.($recordId > 0 ? '/'.$recordId : '') : '')
                .($lang !== '' ? '?lang='.rawurlencode($lang) : '');
        }
        if($recordId > 0 && $pageId > 0 && $this->fileLanguage !== '' && !empty($this->fileRecords[$pageId][$recordId])){
            // a generated record page of a built site (plan 27m §2.1)
            return $up.rawurlencode($lang !== '' ? $lang : $this->fileLanguage).'/'.$pageId.'/'.$recordId.'.html';
        }
        if($recordId > 0 && $pageId > 0){
            if($this->prettyRoot !== ''){
                return $this->prettyRoot.'/'.rawurlencode($this->database).'/site/'.$id.'/'.$pageId.'/'.$recordId
                    .($lang !== '' ? '?lang='.rawurlencode($lang) : '');
            }
            return $this->baseUrl.'?db='.rawurlencode($this->database).'&site='.$id.'&pageid='.$pageId.'&rec='.$recordId
                .($lang !== '' ? '&lang='.rawurlencode($lang) : '');
        }
        if($this->fileLanguage !== ''){
            return $up.rawurlencode($lang !== '' ? $lang : $this->fileLanguage).'/'.($pageId > 0 ? $pageId : 'index').'.html';
        }
        if($this->prettyRoot !== ''){
            return $this->prettyRoot.'/'.rawurlencode($this->database).'/site/'.$id.($pageId > 0 ? '/'.$pageId : '')
                .($lang !== '' ? '?lang='.rawurlencode($lang) : '');
        }
        return $this->baseUrl.'?db='.rawurlencode($this->database).'&site='.$id.($pageId > 0 ? '&pageid='.$pageId : '')
            .($lang !== '' ? '&lang='.rawurlencode($lang) : '');
    }

    /**
     * Whether the URLs are the relative files of a built site (SiteUrls::files()): no canonical, alternates or sitemap
     * without the final base URL (plan 27m §4).
     */
    public function isFiles(): bool
    {
        return $this->fileLanguage !== '';
    }

    /** URL of the sitemap of a website or standalone page (plan 27m §5): `/{db}/site/{id}/sitemap.xml` or `&sitemap=1`. */
    public function sitemap(int $id): string
    {
        // an own domain: /site/{id}/sitemap.xml (a sitemap.xml file of the web root would hide /sitemap.xml)
        if($this->domainRoot !== ''){
            return $this->domainRoot.'/site/'.$id.'/sitemap.xml';
        }
        if($this->prettyRoot !== ''){
            return $this->prettyRoot.'/'.rawurlencode($this->database).'/site/'.$id.'/sitemap.xml';
        }
        return $this->baseUrl.'?db='.rawurlencode($this->database).'&site='.$id.'&sitemap=1';
    }

    /** Start of the URL of an instance frame; the instance id is appended. */
    public function frame(int $id, int $pageId = 0): string
    {
        if($this->domainRoot !== ''){
            return $this->domainRoot.($id === $this->domainSite ? '' : '/site/'.$id).($pageId > 0 ? '/'.$pageId : '').'/frame/';
        }
        if($this->prettyRoot !== ''){
            return $this->prettyRoot.'/'.rawurlencode($this->database).'/site/'.$id.($pageId > 0 ? '/'.$pageId : '').'/frame/';
        }
        return $this->baseUrl.'?db='.rawurlencode($this->database).'&site='.$id.($pageId > 0 ? '&pageid='.$pageId : '').'&frame=';
    }

    /** URL of a legacy website or page (versions 1-3, hserv `websiteRecord.php`). */
    public function legacy(int $id): string
    {
        if($this->prettyRoot !== ''){
            return $this->prettyRoot.'/'.rawurlencode($this->database).'/web/'.$id;
        }
        return $this->baseUrl.'?db='.rawurlencode($this->database).'&website&id='.$id;
    }
}
