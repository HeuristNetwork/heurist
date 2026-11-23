<?php
/**
* GeneratedPages.php - Which generated file answers a page request (plan 27m §1)
*
* A request of a page URL with no other parameter than the language (`site`, `pageid`,
* `rec`, `lang`; the database and the own-domain flag) is answered from the generated site
* when its file exists; the website, the page and the record of a record page must still
* be visible to a visitor who is not logged in (one read), otherwise the live page answers
* (and its rules: 403, 404). Everything else is live: `?live=1`, `?design=1`, frames,
* Saved filter values (`source`, `filter`, `v.*`), any other parameter.
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

use Heurist\Utilities\LanguageCodes;
use Heurist\Website\WebsiteRepository;

/** Generated files of page requests. */
final class GeneratedPages
{
    /** Parameters of a page request that may be served from the generated site. */
    public const PAGE_PARAMS = array('db', 'site', 'pageid', 'rec', 'lang', 'domain');

    private GeneratedSites $sites;
    private WebsiteRepository $guest;
    private LanguageCodes $languages;

    /**
     * @param GeneratedSites $sites The generated-sites folder.
     * @param WebsiteRepository $guest Repository reading as a visitor who is not logged in.
     */
    public function __construct(GeneratedSites $sites, WebsiteRepository $guest, LanguageCodes $languages)
    {
        $this->sites = $sites;
        $this->guest = $guest;
        $this->languages = $languages;
    }

    /** The generated sites. */
    public function sites(): GeneratedSites
    {
        return $this->sites;
    }

    /**
     * The file of a page request, or null (the live page answers).
     *
     * @param array $params Request parameters (site, pageid, rec, lang ...).
     */
    public function file(array $params): ?string
    {
        foreach(array_keys($params) as $name){
            if(!in_array((string)$name, self::PAGE_PARAMS, true)){ return null; }
        }
        $id = intval($params['site'] ?? 0);
        $pageId = intval($params['pageid'] ?? 0);
        $recordId = intval($params['rec'] ?? 0);
        if($id < 1 || ($recordId > 0 && $pageId < 1)){ return null; }
        $lang = trim((string)($params['lang'] ?? ''));
        $language = $lang !== '' ? strtolower((string)$this->languages->code3($lang)) : '';
        if($lang !== '' && $language === ''){ return null; }
        $file = $this->sites->pageFile($id, $language, $pageId, $recordId);
        if($file === null){ return null; }
        // still visible to a visitor: the website, the page and the record of a record page (plan 27m §2.1)
        $ids = array_values(array_unique(array_filter(array($id, $pageId, $recordId))));
        return count($this->guest->recordHeads($ids)) === count($ids) ? $file : null;
    }

    /** The generated sitemap of a site, or null (the live one answers). */
    public function sitemap(int $id): ?string
    {
        $file = $this->sites->sitemapFile($id);
        if($file === null){ return null; }
        return count($this->guest->recordHeads(array($id))) === 1 ? $file : null;
    }
}
