<?php
/**
* CmsHtml.php - Reads and changes the HTML of a v4 website page (plan 27a)
*
* A page HTML is a fragment. Elements known to the designer have
* `data-h-node="<id>"`; a host of a module or widget also has
* `data-h-instance="<id>"`; a text block that can be translated has
* `data-h-text`. This class lists them and puts the translated inner HTML of
* text blocks in place. It never cleans HTML (StoredText does that on save).
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

/** Nodes, hosts and text blocks of a page HTML fragment. */
final class CmsHtml
{
    /** Id of the wrapper element the fragment is parsed in. */
    private const ROOT = 'h-cms-fragment-root';

    /**
     * Elements with `data-h-node` in document order.
     *
     * @return array<int,array{node:string,instance:?string,text:bool,control:?string,tag:string}>
     */
    public static function nodes(string $html): array
    {
        $root = self::parse($html);
        if($root === null){ return array(); }
        $nodes = array();
        $xpath = new \DOMXPath($root->ownerDocument);
        foreach($xpath->query('.//*[@data-h-node or @data-h-instance]', $root) as $element){
            /** @var \DOMElement $element */
            $nodes[] = array(
                'node' => $element->getAttribute('data-h-node'),
                'instance' => $element->hasAttribute('data-h-instance') ? $element->getAttribute('data-h-instance') : null,
                'text' => $element->hasAttribute('data-h-text'),
                'control' => $element->hasAttribute('data-h-control') ? $element->getAttribute('data-h-control') : null,
                'tag' => strtolower($element->tagName)
            );
        }
        return $nodes;
    }

    /**
     * Put translated inner HTML into the text blocks (`data-h-text`) found in $texts;
     * other text blocks keep the first-language text.
     *
     * @param array<string,string> $texts node id => inner HTML in the current language.
     */
    public static function translate(string $html, array $texts): string
    {
        if(empty($texts) || strpos($html, 'data-h-text') === false){ return $html; }
        $root = self::parse($html);
        if($root === null){ return $html; }
        $xpath = new \DOMXPath($root->ownerDocument);
        $replacements = array();
        // markers are plain text (libxml drops control characters), unique for this call
        $token = 'hcmstext'.bin2hex(random_bytes(6));
        foreach($xpath->query('.//*[@data-h-text and @data-h-node]', $root) as $element){
            /** @var \DOMElement $element */
            $id = $element->getAttribute('data-h-node');
            if(!isset($texts[$id]) || !is_string($texts[$id])){ continue; }
            while($element->firstChild){ $element->removeChild($element->firstChild); }
            // a marker, replaced by the HTML after serialization (no second parse)
            $marker = $token.'x'.count($replacements).'x';
            $element->appendChild($root->ownerDocument->createTextNode($marker));
            $replacements[$marker] = $texts[$id];
        }
        if(empty($replacements)){ return $html; }
        return strtr(self::inner($root), $replacements);
    }

    /** Inner HTML of the parsed fragment root. */
    private static function inner(\DOMElement $root): string
    {
        $html = '';
        foreach($root->childNodes as $child){
            $html .= $root->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    /** The fragment parsed in a wrapper element (UTF-8), or null when it is empty. */
    private static function parse(string $html): ?\DOMElement
    {
        if(trim($html) === ''){ return null; }
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true); // HTML5 elements are unknown to libxml
        $document->loadHTML('<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
            .'</head><body><div id="'.self::ROOT.'">'.$html.'</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById(self::ROOT);
        return $root instanceof \DOMElement ? $root : null;
    }
}
