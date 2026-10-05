<?php
/**
* LanguageCodes.php - Language codes and language-prefixed values of reports
*
* Port of getLangCode3(), extractLangPrefix() and getCurrentTranslation() of
* hserv/utilities/ULocale.php: field values may start with "fre:" / "fr:" /
* "*:" to give their language.
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

/** Validates language codes and splits language prefixes. */
final class LanguageCodes
{
    /** @var array<string,string> 3-letter code => 2-letter code (upper case) */
    private array $codes = array();

    /**
     * @param string $codesFile language-codes-active-list.json ([{a2, a3, ...}]).
     */
    public function __construct(string $codesFile)
    {
        if($codesFile !== '' && is_file($codesFile)){
            $list = json_decode((string)file_get_contents($codesFile), true);
            foreach(is_array($list) ? $list : array() as $codes){
                if(!empty($codes['a3'])){
                    $this->codes[strtoupper((string)$codes['a3'])] = strtoupper((string)($codes['a2'] ?? ''));
                }
            }
        }
    }

    /**
     * 3-letter code (upper case) of a 2- or 3-letter code, or null when unknown.
     *
     * @param mixed $lang
     * @return string|null
     */
    public function code3($lang): ?string
    {
        if(!is_string($lang) || $lang === ''){ return null; }
        $lang = strtoupper($lang);
        if(strlen($lang) === 3){
            return isset($this->codes[$lang]) ? $lang : null;
        }
        $found = array_search($lang, $this->codes, true);
        return $found === false ? null : (string)$found;
    }

    /**
     * Language prefix of a value: [language (3 letters, "ALL" or null), value without prefix].
     *
     * @param mixed $value
     * @return array{0:?string,1:mixed}
     */
    public function splitPrefix($value): array
    {
        if(!is_string($value) || mb_strlen($value) <= 4){
            return array(null, $value);
        }
        $value = trim($value);
        $original = $value;
        $closingTag = null;
        if(strpos($value, '<p') === 0 || strpos($value, '<span') === 0){
            $closingTag = strpos($value, '<p') === 0 ? '</p>' : '</span>';
            $value = trim(strip_tags($value));
        }
        $lang = null;
        $position = 0;
        if(substr($value, 0, 2) === '*:'){
            $lang = 'ALL';
            $position = 2;
        }else{
            if(($value[2] ?? '') === ':'){
                $lang = substr($value, 0, 2);
                $position = 3;
            }elseif(($value[3] ?? '') === ':'){
                $lang = substr($value, 0, 3);
                $position = 4;
            }
            if($lang !== null){
                $lang = $this->code3($lang);
            }
        }
        if($lang === null){
            return array(null, $original);
        }
        if($closingTag === null){
            return array($lang, trim(substr($original, $position)));
        }
        $rest = strstr($original, $closingTag);
        return array($lang, trim(substr($rest === false ? '' : $rest, strlen($closingTag))));
    }

    /**
     * Value of a field in a language: the value with that prefix when all other
     * values have prefixes too, else the value without prefix.
     *
     * @param mixed $input One value or the values of a field.
     * @param mixed $lang Language code.
     * @return mixed
     */
    public function valueIn($input, $lang)
    {
        $lang = $this->code3($lang);
        if(is_array($input)){
            $default = null;
            $found = null;
            $count = 0;
            foreach($input as $value){
                list($valueLang, $value) = $this->splitPrefix($value);
                if($valueLang !== null && $valueLang === $lang){
                    $count++;
                    $found = $value;
                }elseif($valueLang === null){
                    $default = $value;
                }else{
                    $count++;
                }
            }
            return $found && $count >= count($input) - 1 ? $found : $default;
        }
        if(is_string($input)){
            // one value: its prefix is removed (as the legacy getCurrentTranslation)
            return $this->splitPrefix($input)[1];
        }
        return null;
    }
}
