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
        'narrowColumn' => array('label' => 'Narrow column', 'max-width' => '800px', 'margin' => '0 auto')
    );

    /**
     * Website record values: header, footer, home content and configuration.
     *
     * @param string $title Website title (shown in the header).
     * @return array{header:string,footer:string,content:string,configuration:string}
     */
    public static function website(string $title): array
    {
        $header = self::id('n');
        $brand = self::id('n');
        $menuHost = self::id('n');
        $menu = self::id('i');
        $footer = self::id('n');
        $home = self::id('n');
        $config = array(
            'schema' => CmsValidator::SCHEMA,
            'instances' => array($menu => array('kind' => 'widget', 'type' => 'menu', 'configVersion' => 1, 'settings' => new \stdClass())),
            'styles' => array(
                'base' => new \stdClass(),
                'kinds' => self::KINDS,
                'presets' => self::PRESETS,
                'nodes' => array(
                    $header => array('display' => 'flex', 'flex-wrap' => 'wrap', 'align-items' => 'center',
                        'justify-content' => 'space-between', 'gap' => '1rem', 'padding' => '0.5rem 1rem'),
                    // a widget host: not the min-height of the instance kind (meant for modules)
                    $menuHost => array('min-height' => '0'),
                    $home => array('padding' => '1rem'),
                    $footer => array('padding' => '1rem', 'text-align' => 'center')
                )
            )
        );
        return array(
            // the header is a group: the designer adds elements only into controls (plan 27c §5.1)
            'header' => '<div data-h-node="'.$header.'" data-h-control="group"><div data-h-node="'.$brand.'" data-h-text=""><h2>'.self::html($title).'</h2></div>'
                .'<nav data-h-node="'.$menuHost.'" data-h-instance="'.$menu.'"></nav></div>',
            'footer' => '<div data-h-node="'.$footer.'" data-h-text=""><p>'.self::html($title).'</p></div>',
            'content' => '<section data-h-node="'.$home.'" data-h-text=""><h1>'.self::html($title).'</h1>'
                .'<p>Welcome. Edit this text in the website editor.</p></section>',
            'configuration' => (string)json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
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
            'content' => '<section data-h-node="'.$node.'" data-h-text=""><h1>'.self::html($title).'</h1><p>About this website.</p></section>',
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
