<?php
/**
* CmsValidator.php - Checks of the website format v4 (plan 27a §7)
*
* Used on save (RecordValidator record check: JSON errors block the save, the
* rest are warnings) and by the renderer (a problem in one instance shows a
* message in its host; the rest of the page renders). Nothing is guessed or
* removed from the stored record.
*
* The same cases are checked by shared/src/site/cmsDocument.js in the client
* (heurist-explorer); both run the fixtures of shared/test/fixtures/cms-v4.
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

/** Checks the configuration JSON, the page HTML and the translations of a v4 page. */
final class CmsValidator
{
    public const SCHEMA = 'heurist-cms/4';

    /** Known instance types by kind, and the newest configuration version of each kind. */
    public const TYPES = array(
        'module' => array('explorer', 'data', 'map', 'timeline', 'graph', 'recordview'),
        'widget' => array('menu')
    );
    public const CONFIG_VERSION = 1;

    /**
     * Parse the configuration JSON (2-1187). An empty value is an empty configuration.
     *
     * @return array{config:array,issues:array} The configuration (empty parts when it is not valid).
     */
    public static function parse(string $json): array
    {
        $empty = array('schema' => self::SCHEMA, 'instances' => array(), 'styles' => array());
        if(trim($json) === ''){ return array('config' => $empty, 'issues' => array()); }
        $config = json_decode($json, true);
        if(!is_array($config) || json_last_error() !== JSON_ERROR_NONE || (isset($config[0]) && !isset($config['schema']))){
            return array('config' => $empty, 'issues' => array(self::issue('error', 'cms_json',
                'The website configuration is not a valid JSON object')));
        }
        if(($config['schema'] ?? null) !== self::SCHEMA){
            return array('config' => $empty, 'issues' => array(self::issue('error', 'cms_schema',
                'The website configuration is not "'.self::SCHEMA.'"')));
        }
        $issues = array();
        foreach(array('instances', 'styles') as $part){
            if(isset($config[$part]) && !is_array($config[$part])){
                $issues[] = self::issue('error', 'cms_json', 'The "'.$part.'" of the website configuration is not an object');
                $config[$part] = array();
            }
        }
        $config['instances'] = $config['instances'] ?? array();
        $config['styles'] = $config['styles'] ?? array();
        return array('config' => $config, 'issues' => $issues);
    }

    /**
     * All checks of one page.
     *
     * @param array $config Parsed configuration (parse()).
     * @param array<int,string> $htmlParts Page HTML, and header / footer for a website.
     * @param array<string,array> $translations Language => {node id: html}.
     * @param string $baseUrl Heurist base URL (background images).
     * @param array<int,string> $otherInstances Instance ids configured elsewhere (website header / footer).
     * @return array<int,array{level:string,code:string,message:string,node:?string}>
     */
    public static function check(array $config, array $htmlParts, array $translations = array(), string $baseUrl = '',
        array $otherInstances = array()): array
    {
        $issues = array();
        $nodes = array();
        foreach($htmlParts as $html){
            foreach(CmsHtml::nodes((string)$html) as $node){ $nodes[] = $node; }
        }
        $seenNodes = array();
        $seenHosts = array();
        $texts = array();
        foreach($nodes as $node){
            $id = $node['node'];
            if($id !== ''){
                if(isset($seenNodes[$id])){
                    $issues[] = self::issue('warning', 'duplicate_node', 'Node id "'.$id.'" is used more than once', $id);
                }
                $seenNodes[$id] = true;
                if($node['text']){ $texts[$id] = true; }
            }
            $instance = $node['instance'];
            if($instance === null){ continue; }
            if(isset($seenHosts[$instance])){
                $issues[] = self::issue('warning', 'duplicate_instance', 'Instance "'.$instance.'" has more than one host', $id);
                continue;
            }
            $seenHosts[$instance] = true;
            if(!isset($config['instances'][$instance]) && !in_array($instance, $otherInstances, true)){
                $issues[] = self::issue('warning', 'host_without_config', 'Instance "'.$instance.'" is not configured', $id);
            }
        }
        foreach($config['instances'] as $instance => $settings){
            $instance = (string)$instance;
            if(!isset($seenHosts[$instance])){
                $issues[] = self::issue('warning', 'config_without_host', 'Instance "'.$instance.'" has no host in the page');
            }
            $problem = self::instanceProblem(is_array($settings) ? $settings : array());
            if($problem !== null){
                $issues[] = self::issue('warning', $problem[0], 'Instance "'.$instance.'": '.$problem[1], null);
            }
        }
        $compiler = new StyleCompiler($baseUrl);
        $compiler->compile(is_array($config['styles']['base'] ?? null) ? $config['styles']['base'] : array(),
            array(is_array($config['styles']['nodes'] ?? null) ? $config['styles']['nodes'] : array()));
        $issues = array_merge($issues, $compiler->issues());
        foreach($translations as $language => $blocks){
            foreach(array_keys(is_array($blocks) ? $blocks : array()) as $id){
                if(!isset($texts[(string)$id])){
                    $issues[] = self::issue('warning', 'translation_orphan',
                        'Translation "'.$language.'" of "'.$id.'": the page has no text block with this id', (string)$id);
                }
            }
            foreach(array_keys($texts) as $id){
                if(!isset($blocks[$id])){
                    $issues[] = self::issue('info', 'translation_missing', 'Text block "'.$id.'" is not translated into "'.$language.'"', $id);
                }
            }
        }
        return $issues;
    }

    /**
     * Why an instance can not be mounted, or null.
     *
     * @return array{0:string,1:string}|null Issue code and message.
     */
    public static function instanceProblem(array $settings): ?array
    {
        $kind = (string)($settings['kind'] ?? '');
        $type = (string)($settings['type'] ?? '');
        if(!isset(self::TYPES[$kind]) || !in_array($type, self::TYPES[$kind], true)){
            return array('unknown_type', 'unknown kind / type "'.$kind.'" / "'.$type.'"');
        }
        if(intval($settings['configVersion'] ?? 1) > self::CONFIG_VERSION){
            return array('config_version', 'configuration version '.intval($settings['configVersion']).' is newer than this Heurist');
        }
        if(isset($settings['mount']) && !in_array($settings['mount'], array('direct', 'iframe'), true)){
            return array('mount', 'mount must be "direct" or "iframe"');
        }
        return null;
    }

    /** Translations of a record: the "xx:{json}" values of 2-1202 by language (3 letters, lower case). */
    public static function translations(array $values): array
    {
        $result = array();
        foreach($values as $value){
            $value = is_array($value) ? (string)($value['value'] ?? '') : (string)$value;
            if(!preg_match('/^([A-Za-z]{2,3}):(.*)$/s', $value, $match)){ continue; }
            $blocks = json_decode($match[2], true);
            if(is_array($blocks)){ $result[strtolower($match[1])] = $blocks; }
        }
        return $result;
    }

    /** Whether the issues include an error (a save is refused). */
    public static function hasError(array $issues): bool
    {
        foreach($issues as $issue){
            if(($issue['level'] ?? '') === 'error'){ return true; }
        }
        return false;
    }

    private static function issue(string $level, string $code, string $message, ?string $node = null): array
    {
        return array('level' => $level, 'code' => $code, 'message' => $message, 'node' => $node);
    }
}
