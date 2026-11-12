<?php
/**
* WebsiteStarter.php - Content of a new v4 website (plan 27b §5)
*
* Header with the site title and a menu host, home content, footer and one
* "About" page; base settings from the Heurist defaults (no values: the CSS
* variables of heurist-ui.css apply), starter kind defaults and presets (plan 27c §6a).
* Templates (plan 27, phase 4) replace it.
* Node and instance ids are new for every site (`n` / `i` + 6 characters).
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

/** Starter HTML and configuration of a new website. */
final class WebsiteStarter
{
    /** Kind defaults of a new website (plan 27c §6a, confirmed 2026-10-30). */
    public const KINDS = array(
        'group' => array('display' => 'block', 'gap' => '12px'),
        'tabs' => array('border' => '1px solid var(--h-color-border)', 'padding' => '8px'),
        'accordion' => array('border' => '1px solid var(--h-color-border)', 'padding' => '8px'),
        'text' => array('line-height' => '1.5'),
        // a media element (plan 27c §33): a dotted frame around the figure (2026-11-03)
        'media' => array('border' => '1px dotted var(--h-color-border)', 'padding' => '8px'),
        'instance' => array('min-height' => '300px')
    );

    /** Style presets of a new website (plan 27c §6a, confirmed 2026-10-30). */
    public const PRESETS = array(
        'card' => array('label' => 'Card', 'padding' => '16px', 'border' => '1px solid var(--h-color-border)',
            'border-radius' => '8px', 'box-shadow' => '0 1px 3px rgba(0, 0, 0, 0.15)'),
        'columns2' => array('label' => 'Two columns', 'display' => 'grid', 'gap' => '12px',
            'grid-template-columns' => 'repeat(2, minmax(0, 1fr))', 'narrow' => array('grid-template-columns' => '1fr')),
        'columns3' => array('label' => 'Three columns', 'display' => 'grid', 'gap' => '12px',
            'grid-template-columns' => 'repeat(3, minmax(0, 1fr))', 'narrow' => array('grid-template-columns' => '1fr')),
        'highlighted' => array('label' => 'Highlighted', 'background-color' => 'var(--h-color-primary)', 'color' => '#ffffff',
            'padding' => '16px'),
        'banner' => array('label' => 'Banner', 'width' => '100%', 'padding' => '48px 16px', 'text-align' => 'center'),
        'narrowColumn' => array('label' => 'Narrow column', 'max-width' => '200px', 'margin' => '0 auto'),
        // texts over light / dark banners and background images (2026-11-12)
        'textDarkShadow' => array('label' => 'Dark text with light shadow', 'color' => '#222222',
            'text-shadow' => '0 0 4px rgba(255, 255, 255, 0.9)'),
        'textLightShadow' => array('label' => 'Light text with dark shadow', 'color' => '#ffffff',
            'text-shadow' => '0 1px 3px rgba(0, 0, 0, 0.8)'),
        // menus with inverted colours: a dark menu on a light background, a light menu on a dark one (HMenu.css takes
        // the text colour of the element, 2026-11-12)
        'menuDark' => array('label' => 'Menu with dark background', 'background-color' => 'var(--h-color-primary)',
            'color' => 'var(--h-color-primary-text, #ffffff)', 'padding' => '4px', 'border-radius' => 'var(--h-radius)'),
        'menuLight' => array('label' => 'Menu with light background', 'background-color' => 'var(--h-color-bg, #ffffff)',
            'color' => 'var(--h-color-text)', 'padding' => '4px', 'border-radius' => 'var(--h-radius)')
    );

    /**
     * Website record values: header, footer, home content and configuration.
     *
     * @param string $title Website title (shown in the header).
     * @param string $baseUrl Heurist base URL (the Heurist logo of the footer).
     * @param array{logo:string,url:?string}|null $host The host organisation (HostInfo::read); null: no hosted at line.
     * @return array{header:string,footer:string,content:string,configuration:string}
     */
    public static function website(string $title, string $baseUrl = '', ?array $host = null): array
    {
        $ids = array();
        foreach(array('header', 'logo', 'brand', 'title', 'subtitle', 'menuHost', 'langHost', 'footer',
            'credits', 'copy', 'heurist', 'hostText', 'home') as $name){
            $ids[$name] = self::id('n');
        }
        $menu = self::id('i');
        $language = self::id('i');
        $widget = static function(string $type, array $settings = array()): array {
            return array('kind' => 'widget', 'type' => $type, 'configVersion' => 1,
                'settings' => empty($settings) ? new \stdClass() : $settings);
        };
        $column = array('display' => 'flex', 'flex-direction' => 'column');
        // a widget host: not the min-height of the instance kind (meant for modules)
        $host0 = array('min-height' => '0');
        $config = array(
            'schema' => CmsValidator::SCHEMA,
            // the menu is vertical in a vertical header
            'instances' => array($menu => $widget('menu', array('orientation' => 'auto')), $language => $widget('language')),
            'styles' => array(
                'base' => new \stdClass(),
                'kinds' => self::KINDS,
                'presets' => self::PRESETS,
                'nodes' => array(
                    // the header default set (plan 27f §13), as cmsEdit.js _header makes it: arranged by the CSS of
                    // the website, the widget hosts only without the min-height of the instance kind
                    $ids['menuHost'] => $host0,
                    $ids['langHost'] => $host0,
                    // a composed footer (plan 27f §3.2): one group of copyright, powered by Heurist, hosted at (review 2026-11-10)
                    $ids['footer'] => array('display' => 'grid', 'grid-template-columns' => 'repeat(1, minmax(0, 1fr))', 'gap' => '16px',
                        'padding' => '1rem', 'align-items' => 'start', 'narrow' => array('grid-template-columns' => '1fr')),
                    $ids['credits'] => $column + array('gap' => '0.5rem'),
                    $ids['home'] => array('padding' => '1rem')
                )
            )
        );
        $group = static function(string $id, string $children, string $class = ''): string {
            return '<div data-h-node="'.$id.'" data-h-control="group"'.($class !== '' ? ' class="'.$class.'"' : '').'>'.$children.'</div>';
        };
        $text = static function(string $id, string $html, string $extra = ''): string {
            return '<div data-h-node="'.$id.'" data-h-text=""'.$extra.'>'.$html.'</div>';
        };
        return array(
            // the header is a group: the designer adds elements only into controls (plan 27c §5.1)
            'header' => $group($ids['header'],
                '<div data-h-node="'.$ids['logo'].'" data-h-text="" data-h-field="website.2-38" class="h-site-logo"></div>'
                .$group($ids['brand'],
                    $text($ids['title'], self::html($title), ' data-h-field="website.2-1" class="h-site-title"')
                    .$text($ids['subtitle'], '', ' data-h-field="website.3-1009" class="h-site-subtitle"'), 'h-site-brand')
                .'<nav data-h-node="'.$ids['menuHost'].'" data-h-instance="'.$menu.'" class="h-site-hset-menu"></nav>'
                .'<div data-h-node="'.$ids['langHost'].'" data-h-instance="'.$language.'" class="h-site-hset-lang"></div>',
                'h-site-hset'),
            'footer' => $group($ids['footer'],
                $group($ids['credits'], $text($ids['copy'], '<p>© '.date('Y').' '.self::html($title).'</p>')
                    .$text($ids['heurist'], self::poweredBy($baseUrl))
                    .($host !== null ? $text($ids['hostText'], self::hostedAt($host)) : ''))),
            'content' => $text($ids['home'], '<h1>'.self::html($title).'</h1><p>Welcome. Edit this text in the website editor.</p>'),
            'configuration' => (string)json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /** Title of the powered by Heurist link (as in the legacy websites). */
    public const POWERED_TITLE = 'This website is generated by Heurist, an academic knowledge management system developed under the '
        .'direction of Dr Ian Johnson, chief programmer Artem Osmakov';

    /**
     * The powered by Heurist line of a footer (review 2026-11-10; as cmsEdit.js cmsPoweredBy): a link with the
     * Heurist logo of the website bundle.
     */
    public static function poweredBy(string $baseUrl): string
    {
        return '<p><a class="h-site-powered" href="https://heuristnetwork.org" target="_blank" rel="noopener" title="'.self::html(self::POWERED_TITLE).'">'
            .'powered by <img src="'.self::html($baseUrl.PageRenderer::BUNDLE.'assets/branding/h6logo32.png').'" alt="" height="16"> Heurist</a></p>';
    }

    /**
     * The hosted at line of a footer (review 2026-11-10; as cmsEdit.js cmsHostedAt): the logo of the host organisation,
     * a link to it when the installation has its URL.
     *
     * @param array{logo:string,url:?string} $host HostInfo::read.
     */
    public static function hostedAt(array $host): string
    {
        $inner = '<span>hosted at: </span><img src="'.self::html((string)$host['logo']).'" alt="Host organisation" height="35">';
        return '<p>'.(!empty($host['url'])
            ? '<a class="h-site-hosted" href="'.self::html((string)$host['url']).'" target="_blank" rel="noopener">'.$inner.'</a>'
            : '<span class="h-site-hosted">'.$inner.'</span>').'</p>';
    }

    /**
     * The first menu page.
     *
     * @return array{title:string,content:string,configuration:string}
     */
    public static function page(string $title = 'About'): array
    {
        $node = self::id('n');
        return array(
            'title' => $title,
            'content' => '<div data-h-node="'.$node.'" data-h-text=""><h1>'.self::html($title).'</h1><p>About this website.</p></div>',
            'configuration' => (string)json_encode(array('schema' => CmsValidator::SCHEMA, 'instances' => new \stdClass(),
                'styles' => array('nodes' => array($node => array('padding' => '1rem')))), JSON_UNESCAPED_SLASHES)
        );
    }

    /** A new node or instance id: prefix + 6 characters. */
    public static function id(string $prefix): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $id = $prefix;
        for($i = 0; $i < 6; $i++){ $id .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
        return $id;
    }

    private static function html(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
    }
}
