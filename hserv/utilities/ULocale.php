<?php
/**
* ULocale.php - Utility functions for localization
* 
* Localization utility functions for Heurist.
* This file provides a collection of global functions for tasks such as:
* - Initializing and retrieving standard language codes (initLangCodes, getLangCode3, getLangCode2).
* - Extracting language prefixes from strings (extractLangPrefix).
* - Retrieving translations for content, including integration with Smarty (getTranslation, getCurrentTranslation).
* - Performing external translations using services like DeepL API (getDeepLTranslation).
* - Preparing a list of languages for UI presentation (getPreparedLanguageList).
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay   <blmckay13@gmail.com>
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

use hserv\utilities\DeepL;

    /**
     * Initializes global language code arrays if they haven't been already.
     * Reads language codes from a JSON file and populates $glb_lang_codes and $glb_lang_codes_index.
     * $glb_lang_codes: Array of language code objects.
     * $glb_lang_codes_index: An associative array mapping 3-letter codes (uppercase) to 2-letter codes (uppercase).
     *
     * @global array $glb_lang_codes Holds the list of language code objects.
     * @global array $glb_lang_codes_index Holds an index mapping 3-letter to 2-letter language codes.
     * @return void
     */
    function initLangCodes(){
        global $glb_lang_codes, $glb_lang_codes_index;

        if(!isset($glb_lang_codes)){
            $glb_lang_codes = json_decode(file_get_contents(HEURIST_DIR.'hclient/assets/language-codes-active-list.json'),true);
            foreach($glb_lang_codes as $codes){
                $glb_lang_codes_index[strtoupper($codes['a3'])] = strtoupper($codes['a2']);
            }
        }
    }

    /**
     * Validates a given language code (2 or 3 letters) and returns its 3-letter ISO 639-2 code (uppercase).
     *
     * @global array $glb_lang_codes_index An index mapping 3-letter to 2-letter language codes.
     * @param string|null $lang The language code to validate (e.g., "en", "ENG").
     * @return string|null The 3-letter ISO 639-2 language code (uppercase) if valid, otherwise null.
     */
    function getLangCode3($lang){
        global $glb_lang_codes, $glb_lang_codes_index; // $glb_lang_codes is not directly used here but initLangCodes loads it.

        $res = null;

        if ($lang) {

            initLangCodes();

            $lang = strtoupper($lang);
            if(strlen($lang)==3){
                $lang = strtoupper($lang);
                if(@$glb_lang_codes_index[$lang]!=null){
                    $res = $lang;
                }
            }else{
                $res = array_search($lang, $glb_lang_codes_index);
            }

            /*
            $key = (strlen($lang)==2)?'a2':'a3';
            foreach($glb_lang_codes as $codes){
                if(strcasecmp($codes[$key], $lang)===0){
                    $res = $codes['a3'];
                    break;
                }
            }*/

        }

        return $res;
    }

    /**
     * Validates a given language code (2 or 3 letters) and returns its 2-letter ISO 639-1 code (uppercase).
     *
     * @global array $glb_lang_codes_index An index mapping 3-letter to 2-letter language codes.
     * @param string|null $lang The language code to validate (e.g., "en", "ENG").
     * @return string|null The 2-letter ISO 639-1 language code (uppercase) if valid, otherwise null.
     */
    function getLangCode2($lang){

        global $glb_lang_codes, $glb_lang_codes_index; // $glb_lang_codes is not directly used here but initLangCodes loads it.

        $res = null;

        if ($lang) {

            initLangCodes();

            $lang = strtoupper($lang);
            if(strlen($lang)==3){
                $lang = strtoupper($lang);
                if(@$glb_lang_codes_index[$lang]!=null){
                    $res = $glb_lang_codes_index[$lang];
                }
            }else{
                $res = array_search($lang, $glb_lang_codes_index) === false ? null : $lang;
            }
        }

        return $res;
    }

    /**
     * Splits and extracts a language code and value from a string formatted as "code:value" or "code: html_value".
     * If the extracted language code is a 2-letter ISO 639-1 code, it's converted to its 3-letter ISO 639-2 equivalent.
     * Handles cases where the value might be wrapped in <p> or <span> tags.
     *
     * @param string|mixed $val The input string potentially containing a language prefix. If not a string or too short, it's returned as is with no lang.
     * @return array An array containing two elements:
     *               0: The extracted 3-letter language code (uppercase) or "ALL", or null if no valid prefix is found.
     *               1: The value part of the string. If a prefix was found, this is the substring after the prefix. Otherwise, it's the original value.
     */
    function extractLangPrefix($val){

        $lang = null;

        if(is_string($val) && mb_strlen($val)>4){

            $val = trim($val);
            $val_orig = $val;
            $tag_to_remove = null;
            if(strpos($val,'<p')===0 || strpos($val,'<span')===0){
                /*
                $document = DOMDocument::loadHTML( $val );
                $childToRemove = $document->getElementsByTagName('p')->item(0);
                $childToRemove->parentNode->removeChild($childToRemove);
                $val = $document->saveHTML();
                */
                $tag_to_remove = strpos($val,'<p')===0?'</p>':'</span>';
                $val = trim(strip_tags($val));
            }

            $pos = 0;
            if(substr($val,0,2)=='*:'){
                $lang = 'ALL';
                $pos = 2;
            }else{

                if($val[2]==':'){
                    $lang = substr($val,0,2);
                    $pos = 3;
                }elseif($val[3]==':'){
                    $lang = substr($val,0,3);
                    $pos = 4;
                }

                if($lang){
                    $lang = getLangCode3($lang);//validate
                }
            }

            if($lang){ //lang detected

                //if (strcasecmp($lang,'ALL')===0 || in_array($lang, $commonLanguagesForTranslation)){
                if($tag_to_remove == null){
                    $val = trim(substr($val_orig, $pos));
                }else{
                    //remove first p or span
                    $val = trim(substr(strstr($val_orig, $tag_to_remove), strlen($tag_to_remove)));
                }

            }else{
                $val = $val_orig;
            }
        }

        return array($lang, $val);
    }

    /**
     * Retrieves a translation for a given input, typically used as a Smarty modifier.
     * It can handle translations for Heurist terms (labels or descriptions) or regular record detail fields.
     *
     * @global \Smarty\Smarty|null $smarty The Smarty template engine instance.
     * @param string|array $input The input value to translate. Can be a string (for record details) or an array (for terms).
     *                            If an array for a term, it should contain 'id' and the field to translate (e.g., 'label').
     * @param string $lang The target language code (2 or 3 letters).
     * @param string|null $field Optional. If translating a term, specifies which field of the term to translate (e.g., 'label', 'desc').
     *                           Defaults to 'label' for terms.
     * @return string|array|null The translated string if found. If no translation is available for the specified language,
     *                    it returns the original input (for strings) or the default language value.
     *                    Returns null if input is invalid or Smarty context is unavailable for term translation.
     */
    function getTranslation($input, $lang, $field=null){
        global $smarty;

        $res = null;
        $lang = getLangCode3($lang);

        //detect if it is usual record or term
        $isArray = !isEmptyArray($input);
        if($isArray && (@$input['term'] || is_array(@$input[0]) && @$input[0]['term'])){

            if($field==null) {$field = 'label';}

            $trm = @$input[0] ? $input[0] : $input;

            if(isset($smarty)){

                $heuristRec = $smarty->getTemplateVars('heurist');
                if($heuristRec){
                    return $heuristRec->getTranslation('trm', $trm['id'], $field, $lang);
                }
            }

            return $trm[$field];

        }elseif($isArray && (@$input['ulf_ID'] || is_array(@$input[0]) && @$input[0]['ulf_ID'])){

            $field = $field === null || strpos($field, 'cap') !== false ? 'ulf_Caption' : 'ulf_Description';

            $file = @$input[0] ? $input[0] : $input;

            if(isset($smarty)){

                $heuristRec = $smarty->getTemplateVars('heurist');
                if($heuristRec){
                    return $heuristRec->getTranslation('ulf', $file['ulf_ID'], $field, $lang);
                }
            }

            return $file[$field];
        }

        // this is record detail field;
        $res = getCurrentTranslation($input, $lang);

        $ret = ($res==null)?$input:$res;
        return $ret;
    }

    /**
     * Retrieves the translation for a specific language from a potentially multi-lingual input.
     * The input can be an array of values (where each value might have a language prefix) or a single string.
     * If $input is an array, it iterates through values, looking for one matching the target $lang.
     * If no match is found, it returns a default (non-prefixed) value if available.
     * If $input is a string, it simply extracts the language prefix and value.
     *
     * @param string|array $input The input value or array of values. Values can be strings like "ENG:Hello" or "Bonjour".
     * @param string $lang The target language code (2 or 3 letters).
     * @return string|null The translated string for the target language, the default language string,
     *                     or null if no suitable translation is found or input is invalid.
     */
    function getCurrentTranslation($input, $lang){

        $res = null;

        if(is_array($input)){

            $lang = getLangCode3($lang);
            $def = null;
            $fnd = null;
            $cnt = 0;
            //all values except one must be with lang: prefix
            foreach($input as $val){

                list($lang_, $val) = extractLangPrefix($val);

                if ($lang_!=null && $lang_==$lang){
                    $cnt++;
                    $fnd = $val;
                }elseif($lang_==null){
                    $def = $val;
                }else{
                    $cnt++;
                }

            } //foreach
            if($fnd && ($cnt>=count($input)-1)){
                $res = $fnd;
            }else{
                $res = $def;
            }

        }elseif(is_string($input)) {
            list($lang_, $res) = extractLangPrefix($input);//there is no localization
        }

        return $res;
    }

    /**
     * Translates a given string to a target language using the DeepL API.
     * Requires a valid DeepL API key to be configured in `$accessToken_DeepLAPI`.
     * Handles HTML and XML content by attempting to preserve tags using DeepL's tag handling.
     *
     * @global array $glb_lang_codes_index Global array mapping 3-letter to 2-letter language codes.
     * @global string|null $accessToken_DeepLAPI The DeepL API authentication key.
     * @param \hserv\System $system Heurist's initialized system object.
     * @param string $string The string to be translated.
     * @param string $targetLanguage The target language code (2 or 3 letters, e.g., "EN", "FRA").
     * @param string|null $sourceLanguage Optional. The source language code (2 or 3 letters).
     *                                     If null, DeepL attempts auto-detection.
     * @return string|false The translated string on success, or false on failure (e.g., API error, invalid language).
     *                      Error details are added to the $system object.
     */
    function getDeepLTranslation($system, $string, $targetLanguage, $sourceLanguage = null){

        global $accessToken_DeepLAPI, $serverName_DeepL; // $glb_lang_codes is loaded by initLangCodes

        $DeepL = null;

        try{
            $DeepL = new DeepL($system, $accessToken_DeepLAPI, $serverName_DeepL);
        }catch(\Exception $exception){

            $errorCode = $exception->getCode();
            if($errorCode === 1){
                $system->addError(HEURIST_ACTION_BLOCKED, $exception->getMessage());
            }

            return false;
        }

        $string = $DeepL->translateText($string, $sourceLanguage, $targetLanguage);

        return $string;
    }

    /**
     * Prepares a list of common languages for translation and available UI localization files.
     * Used to populate language selection UI elements.
     *
     * @global array $commonLanguagesForTranslation Array of common language codes (3-letter) defined in heuristConfigIni.php.
     * @global array $glb_lang_codes Global array of language code objects.
     * @param \hserv\System $system Heurist's initialized system object.
     * @return array An array containing two elements:
     *               0: An associative array of common languages (uppercase 3-letter code => language object).
     *               1: An array of available UI locale file language codes (2-letter, lowercase).
     */
    function getPreparedLanguageList($system = null){

        global $commonLanguagesForTranslation, $glb_lang_codes;

        // extracts from $glb_lang_codes names and alpha2 codes to be sent to client
        initLangCodes();

        $languages = $commonLanguagesForTranslation;
        if($system && is_a($system, 'hserv\System')){
            $languages = $system->settings->getDatabaseSetting('Languages');
            if(empty($languages)){
                $languages = $commonLanguagesForTranslation;
                $system->settings->setDatabaseSetting('Languages', $languages);
            }else{
                $languages = array_unique(array_map('strtoupper', $languages));
                $system->settings->setDatabaseSetting('Languages', $languages);
            }
        }

        // ordered as in $commonLanguages (defined in heuristConfigIni)
        $commonLanguages = [];
        foreach($languages as $code){

            $lang = strtolower($code);

            $key = array_search($lang, array_column($glb_lang_codes, 'a3'));
            if($key!==false){
                $commonLanguages[strtoupper($lang)] = $glb_lang_codes[$key];
            }
        }

        // Get list of available localisation files
        $localisationDir = __DIR__ . '/../../hclient/assets/localization/';
        $localeFiles = [];
        $localisationFiles = is_dir($localisationDir) ? scandir($localisationDir) : null;
        if(!empty($localisationFiles)){

            foreach($localisationFiles as $filename){

                if($filename == '.' || $filename == '..' || is_dir($localisationDir.$filename)){
                    continue;
                }

                $filename = explode('.', $filename)[0];
                $language = explode('_', $filename)[1];
                $localeFiles[] = $language;
            }
        }

        return [$commonLanguages, $localeFiles];
    }
