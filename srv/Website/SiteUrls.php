<?php
/**
* SiteUrls.php - URLs of the pages of a v4 website (plan 27a §5)
*
* Pretty form `/{db}/site/{id}[/{page id}]` (RequestRouter, action "site") or
* the parameter form `?db={db}&site={id}[&pageid={page id}]`, which works
* without the router. The form of the current request is kept.
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

    /** URL of a website page (pageId 0: home) or of a standalone page (id = the page). */
    public function page(int $id, int $pageId = 0, string $language = ''): string
    {
        $lang = $language !== '' ? strtolower($language) : '';
        if($this->prettyRoot !== ''){
            return $this->prettyRoot.'/'.rawurlencode($this->database).'/site/'.$id.($pageId > 0 ? '/'.$pageId : '')
                .($lang !== '' ? '?lang='.rawurlencode($lang) : '');
        }
        return $this->baseUrl.'?db='.rawurlencode($this->database).'&site='.$id.($pageId > 0 ? '&pageid='.$pageId : '')
            .($lang !== '' ? '&lang='.rawurlencode($lang) : '');
    }

    /** Start of the URL of an instance frame; the instance id is appended. */
    public function frame(int $id, int $pageId = 0): string
    {
        if($this->prettyRoot !== ''){
            return $this->prettyRoot.'/'.rawurlencode($this->database).'/site/'.$id.($pageId > 0 ? '/'.$pageId : '').'/frame/';
        }
        return $this->baseUrl.'?db='.rawurlencode($this->database).'&site='.$id.($pageId > 0 ? '&pageid='.$pageId : '').'&frame=';
    }
}
