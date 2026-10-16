<?php
/**
* ReportText.php - Text helpers of the report engine
*
* sanitize(): port of USanitize::sanitizeString with its default tag list (field
* values shown in reports keep basic formatting tags only).
*
* @project     Heurist academic knowledge management system
* @package     Reports\Smarty
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports\Smarty;

/** Static text helpers. */
final class ReportText
{
    /** Tags kept in field values. */
    public const ALLOWED_TAGS = '<a><u><i><div><em><b><strong><sup><sub><small><br><h1><h2><h3><h4><h5><h6><p><ol><ul><li><img>'
        .'<blockquote><pre><span><bibl><persName><audio><video><iframe><source><table><th><tr><td><article><aside>'
        .'<details><figcaption><figure><footer><header><main><mark><nav><section><summary><time>';

    /** Tags kept in record titles of links. */
    public const TITLE_TAGS = '<i><b><u><em><strong><sup><sub><small><br>';

    /**
     * Remove other tags, escape the text and keep the entities.
     *
     * @param mixed $text
     * @param string $allowedTags
     * @return string
     */
    public static function sanitize($text, string $allowedTags = self::ALLOWED_TAGS): string
    {
        if($text === null){ return ''; }
        $text = strip_tags((string)$text, $allowedTags);
        $text = htmlspecialchars($text, ENT_NOQUOTES);
        $text = str_replace(array('&lt;', '&gt;'), array('<', '>'), $text);
        $result = preg_replace_callback('/&amp;([a-zA-Z]{2,35}|#[0-9]{1,6}|#x[a-fA-F0-9]{1,6});/u', static function($matches){
            return '&'.$matches[1].';';
        }, $text);
        return $result === null ? $text : $result;
    }
}
