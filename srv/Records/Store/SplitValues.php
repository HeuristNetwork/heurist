<?php
/**
* SplitValues.php - Long values saved in several rows, given as one value again
*
* Two kinds of long text are saved in parts (RecordValidator) and joined on read:
*   website page content (RT_CMS_PAGE / DT_EXTENDED_DESCRIPTION): the parts in order;
*   page translations (DT_CMS_PAGE_TRANSLATIONS, plan 27a): every part starts with its
*   language code ("fre:"), one value per language.
* Used by RecordDataService (reads) and RecordSaveService (PATCH reads the stored rows
* and validates them again; a part alone is not a valid value, 2026-10-31). No queries.
*
* @project     Heurist academic knowledge management system
* @package     Records\Store
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Store;

final class SplitValues
{
    /**
     * Join the split fields of one record's details (keys: field ids).
     *
     * @param array<int|string,array> $details Values by field id (plain or {value, hidden}).
     * @param int $rectype Record type of the record.
     * @param int $cmsPage RT_CMS_PAGE (0: none).
     * @param int $content DT_EXTENDED_DESCRIPTION (0: none).
     * @param int $translations DT_CMS_PAGE_TRANSLATIONS (0: none).
     * @return array<int|string,array>
     */
    public static function joinDetails(array $details, int $rectype, int $cmsPage, int $content, int $translations): array
    {
        foreach($details as $field => $values){
            if(!is_array($values) || count($values) < 2){ continue; }
            if($content > 0 && intval($field) === $content && $cmsPage > 0 && $rectype === $cmsPage){
                $details[$field] = self::joinContent($values);
            }elseif($translations > 0 && intval($field) === $translations){
                $details[$field] = self::joinTranslations($values);
            }
        }
        return $details;
    }

    /**
     * The parts of a page content as one value (hidden when a part is).
     *
     * @param array<int,mixed> $parts
     * @return array<int,mixed> One value.
     */
    public static function joinContent(array $parts): array
    {
        $hidden = false;
        $text = '';
        foreach($parts as $part){
            if(is_array($part)){
                $hidden = $hidden || !empty($part['hidden']);
                $part = $part['value'] ?? '';
            }
            $text .= (string)$part;
        }
        return array($hidden ? array('value'=>$text, 'hidden'=>true) : $text);
    }

    /**
     * The rows of page translations as one value per language ("fre:" + the joined parts).
     *
     * @param array<int,mixed> $rows
     * @return array<int,mixed>
     */
    public static function joinTranslations(array $rows): array
    {
        $languages = array();
        foreach($rows as $row){
            $hidden = is_array($row) && !empty($row['hidden']);
            $text = (string)(is_array($row) ? ($row['value'] ?? '') : $row);
            $language = preg_match('/^([A-Za-z]{2,3}):/', $text, $match) ? strtolower($match[1]) : '';
            $part = $language === '' ? $text : substr($text, strlen($match[0]));
            if(!isset($languages[$language])){
                $languages[$language] = array('text' => $language === '' ? '' : $language.':', 'hidden' => false);
            }
            $languages[$language]['text'] .= $part;
            $languages[$language]['hidden'] = $languages[$language]['hidden'] || $hidden;
        }
        return array_values(array_map(static function(array $joined){
            return $joined['hidden'] ? array('value'=>$joined['text'], 'hidden'=>true) : $joined['text'];
        }, $languages));
    }
}
