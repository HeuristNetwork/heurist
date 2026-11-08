<?php
/**
* PageContent.php - Semi-static content of a website page (plan 27e)
*
* Values of the elements with `data-h-field` (`207439.2-1`, `website.2-1`, `page.2-1`) and the HTML of the
* widgets `report`, `cards` and `gallery`. Used by PageRenderer on a dynamic page (per request, the rights of
* the visitor) and on a generated page (at build, guest rights): the same classes, no HTTP.
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

use Heurist\Reports\ReportOutputStore;
use Heurist\Utilities\LanguageCodes;
use Heurist\Utilities\Temporal;

/** Field values and widget HTML of a page. */
final class PageContent
{
    /** Hard limit of the records of a cards widget (plan 27e §9). */
    public const CARD_LIMIT = 50;

    /** Bytes of a generated report shown with `display: cut` (plan 27e §28). */
    public const REPORT_CUT = 102400;

    private WebsiteRepository $repository;
    private LanguageCodes $languages;
    /** @var array{search:callable,render:callable,generated:string,baseUrl:string,database:string} */
    private array $services;
    /** @var array<int,array|null> */
    private array $records = array();

    /**
     * @param WebsiteRepository $repository Records with the rights of the visitor.
     * @param array $services `search` (query, limit) => record ids; `render` (template file, record id, language)
     *        => html or null; `generated` the folder of generated reports; `baseUrl`; `database`.
     */
    public function __construct(WebsiteRepository $repository, LanguageCodes $languages, array $services)
    {
        $this->repository = $repository;
        $this->languages = $languages;
        $this->services = $services;
    }

    /**
     * The value of a `data-h-field` reference as HTML.
     *
     * @param string $reference `<record id|website|page>.<field id or concept code>`.
     * @param array $context WebsiteService::context.
     * @return string|null HTML; '' for an empty field and for a record the visitor may not see (the snapshot must not show it,
     *         plan 27e §22); null when the reference or field is not valid: the snapshot stays.
     */
    public function field(string $reference, array $context): ?string
    {
        if(preg_match('/^(website|page|\d+)\.(\d+(?:-\d+)?)$/', $reference, $parts) !== 1){ return null; }
        $record = $this->sourceRecord($parts[1], $context);
        if($record === null){ return ''; }
        $definitions = $this->repository->definitions();
        $fieldId = $definitions->localId('dty', $parts[2]);
        if($fieldId < 1){ return null; }
        $values = WebsiteRepository::values($record, $fieldId);
        if(empty($values)){ return ''; }
        $language = (string)($context['language'] ?? 'ENG');
        switch($definitions->fieldType($fieldId)){
            case 'file':
                return $this->image($values);
            case 'enum':
            case 'relationtype':
                return self::text(implode(', ', $this->termLabels($values, $language)));
            case 'date':
                return self::text(implode(', ', array_map(static function($value) use ($language): string {
                    return (string)Temporal::toHumanReadable((string)$value, true, 0, '|', 'both', $language);
                }, $values)));
            case 'blocktext':
                // stored HTML, already purified on save
                return implode('', $this->inLanguage($values, $language));
            default:
                return self::text(implode(', ', $this->inLanguage($values, $language)));
        }
    }

    /**
     * The HTML of a widget for its host.
     *
     * @param array $instance Instance of the configuration.
     * @param array $context WebsiteService::context.
     * @return array{html:string,class:string}|null Null: not a widget of this class, or nothing to show.
     */
    public function widget(array $instance, array $context): ?array
    {
        $settings = is_array($instance['settings'] ?? null) ? $instance['settings'] : array();
        $language = strtolower((string)($context['language'] ?? 'eng'));
        // one visible: slideshow (`slider`) or carousel (plan 27e §29)
        $list = !in_array((string)($settings['presentation'] ?? 'list'), array('slider', 'carousel'), true);
        switch((string)($instance['type'] ?? '')){
            case 'report':
                $html = $this->report($settings, $language, $context);
                return $html === null ? null : array('html' => $html, 'class' => 'h-site-report');
            case 'cards':
                return array('html' => $this->cards($settings, $language), 'class' => $list ? 'h-site-cards h-site-widget-list' : 'h-site-cards h-slider-pending');
            case 'badges':
                // the hosting organisation (block Hosted at, plan 27f §9.6), written now from the files of the installation
                if(($settings['source'] ?? '') === 'host'){
                    $html = HostInfo::badgeHtml((string)$this->services['baseUrl'], intval($settings['height'] ?? 48));
                    return array('html' => $html, 'class' => $html === '' ? 'h-site-badges h-site-badges-empty' : 'h-site-badges');
                }
                // logos with links (plan 27f §7): HTML made by the designer per language, as a gallery
                $html = is_array($settings['html'] ?? null) ? ($settings['html'][$language] ?? $settings['html']['def'] ?? null) : null;
                return is_string($html) ? array('html' => $html, 'class' => 'h-site-badges') : null;
            case 'gallery':
                $html = is_array($settings['html'] ?? null) ? ($settings['html'][$language] ?? $settings['html']['def'] ?? null) : null;
                return is_string($html) ? array('html' => $html, 'class' => $list ? 'h-site-gallery h-site-widget-list' : 'h-site-gallery h-slider-pending') : null;
        }
        return null;
    }

    /** The record of a reference: the website, the page, or a record the visitor may see. */
    private function sourceRecord(string $source, array $context): ?array
    {
        if($source === 'website'){ return $context['site'] ?? $context['page']; }
        if($source === 'page'){ return $context['page']; }
        $id = intval($source);
        if(!array_key_exists($id, $this->records)){ $this->records[$id] = $this->repository->record($id); }
        return $this->records[$id];
    }

    /** The values of the language if there are any, else the values without a language, else all (draft 1 §5b). */
    private function inLanguage(array $values, string $language): array
    {
        $code = $this->languages->code3($language);
        $own = array();
        $plain = array();
        $all = array();
        foreach($values as $value){
            list($valueLanguage, $text) = $this->languages->splitPrefix((string)$value);
            $all[] = (string)$text;
            if($valueLanguage === null){ $plain[] = (string)$text; }
            elseif($valueLanguage === $code){ $own[] = (string)$text; }
        }
        return !empty($own) ? $own : (!empty($plain) ? $plain : $all);
    }

    /** Term labels in a language, the label itself without a translation. */
    private function termLabels(array $termIds, string $language): array
    {
        $definitions = $this->repository->definitions();
        $translated = $definitions->translations('trm_Label', array_map('intval', $termIds), (string)$this->languages->code3($language));
        $labels = array();
        foreach($termIds as $termId){
            $labels[] = $translated[(string)intval($termId)] ?? (string)($definitions->term(intval($termId))['term'] ?? '');
        }
        return array_values(array_filter($labels, 'strlen'));
    }

    /** An image of a file field: the first file. */
    private function image(array $fileIds): string
    {
        $url = $this->fileUrl(intval($fileIds[0] ?? 0));
        return $url === '' ? '' : '<img src="'.self::attribute($url).'" alt="">';
    }

    /** URL of a registered file (the icon of a menu entry, an image field), or ''. */
    public function fileUrl(int $fileId): string
    {
        $key = $fileId > 0 ? $this->repository->fileKey($fileId) : '';
        return $key === '' ? '' : $this->services['baseUrl'].'?db='.rawurlencode($this->services['database']).'&file='.rawurlencode($key);
    }

    /**
     * Content of a generated report: only the body of a whole document. The author of the website chose the
     * output, as a report of the cards (WebsiteRepository). `display`: `full` (default), `cut` (the first
     * REPORT_CUT bytes and a link Show more), `link` (only a link to the file). The link needs an output record
     * the visitor may see (plan 27e §28).
     */
    private function report(array $settings, string $language, array $context): ?string
    {
        $outputs = is_array($settings['files'] ?? null) ? $settings['files'] : array();
        $outputId = self::reportOutput($outputs, $language, strtolower((string)($context['languages'][0] ?? '')));
        // the file may hold data the visitor can not see (generated with the rights of its owner): only an output record
        // the visitor may see is shown, the same rule as its link (plan 27e §30)
        if($outputId < 1 || $this->repository->record($outputId) === null){ return null; }
        $file = (new ReportOutputStore((string)$this->services['generated']))->find($outputId);
        if($file === null || $file['format'] !== 'html'){ return null; }
        $display = (string)($settings['display'] ?? 'full');
        $url = self::attribute($this->services['baseUrl'].'api/'.rawurlencode($this->services['database']).'/output/'.$outputId.'/asset');
        if($display === 'link'){
            return '<a class="h-site-report-link" href="'.$url.'" target="_blank" rel="noopener">Open report</a>';
        }
        $html = (string)file_get_contents($file['path']);
        $html = preg_match('/<body\b[^>]*>(.*)<\/body\s*>/is', $html, $body) === 1 ? $body[1] : $html;
        if($display !== 'cut' || strlen($html) <= self::REPORT_CUT){ return $html; }
        return '<div class="h-site-report-part">'.CmsHtml::cut($html, self::REPORT_CUT).'</div>'
            .'<a class="h-site-report-more" href="'.$url.'" target="_blank" rel="noopener">Show more</a>';
    }

    /**
     * The output of a page language: the language, else `def`, else the default language of the website, else
     * the first one. As with texts, a language without its own output shows another one.
     *
     * @param array $outputs `{language: output id}`.
     * @param string $language Language of the page (lower case).
     * @param string $default Default language of the website (lower case), '' when not known.
     */
    public static function reportOutput(array $outputs, string $language, string $default = ''): int
    {
        $outputs = array_filter(array_map('intval', $outputs), static function(int $id): bool { return $id > 0; });
        foreach(array($language, 'def', $default) as $key){
            if($key !== '' && isset($outputs[$key])){ return $outputs[$key]; }
        }
        return empty($outputs) ? 0 : (int)reset($outputs);
    }

    /** Cards of the records of the query, each one rendered with the card template. */
    private function cards(array $settings, string $language): string
    {
        $query = $settings['query'] ?? null;
        if(!is_array($query) || empty($query) || self::hasParameters($query)){ return ''; }
        $file = $this->repository->reportTemplate($settings['template'] ?? '');
        if($file === ''){ return ''; }
        $ids = array_slice(array_map('intval', (array)call_user_func($this->services['search'], $query, self::CARD_LIMIT)), 0, self::CARD_LIMIT);
        $urls = (string)($settings['action'] ?? '') === 'url' ? $this->repository->recordUrls($ids) : array();
        $html = '';
        foreach($ids as $id){
            $card = call_user_func($this->services['render'], $file, $id, $language);
            if(!is_string($card) || $card === ''){ continue; }
            $url = $urls[$id] ?? '';
            $html .= '<div class="h-site-card" data-h-rec="'.$id.'"'.($url !== '' ? ' data-h-url="'.self::attribute($url).'"' : '').'>'.$card.'</div>';
        }
        return $html;
    }

    /** A query with `$NAME$` parameters can not run without a form. */
    private static function hasParameters(array $query): bool
    {
        return preg_match('/\$[A-Za-z][A-Za-z0-9_]*\$/', (string)json_encode($query)) === 1;
    }

    private static function text(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function attribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
