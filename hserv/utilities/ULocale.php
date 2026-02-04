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
* - Handling "no translate" tags for content passed to translation services (addNoTranslateTags, removeNoTranslateTags).
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
                    $val = substr($val_orig, $pos);
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
     * @global Smarty|null $smarty The Smarty template engine instance.
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
        if(is_array($input) && (@$input['term'] || (is_array(@$input[0]) && @$input[0]['term']))){

            if($field==null) {$field = 'label';}

            $trm = @$input[0]?$input[0]:$input;

            if(isset($smarty)){

                //$heuristRec = @$smarty['tpl_vars']['heurist']['value'];

                $heuristRec = $smarty->getTemplateVars('heurist');
                if($heuristRec){
                    return $heuristRec->getTranslation('trm', $trm['id'], $field, $lang);
                }
            }
            return $trm[$field];
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

        global $glb_lang_codes_index, $accessToken_DeepLAPI, $serverName_DeepL; // $glb_lang_codes is loaded by initLangCodes

        initLangCodes();

        // Default list of languages - from https://www.deepl.com/docs-api/general/get-languages
        $defLanguages = ['AR', 'BG', 'CS', 'DA', 'DE', 'EL', 'EN', 'ES', 'ET', 'FI',
                         'FR', 'HU', 'ID', 'IT', 'JA', 'KO', 'LT', 'LV', 'NB', 'NL',
                         'PL', 'PT', 'RO', 'RU', 'SK', 'SL', 'SV', 'TR', 'UK', 'ZH'];

        // Retrieve from file, created by daily script
        $languagesFile = HEURIST_FILESTORE_ROOT . '_EXTERNAL_LOOKUP_DATA/DEEPL_languages.json';
        if(!file_exists($languagesFile)){
            $languagesFile = HEURIST_FILESTORE_ROOT . 'DEEPL_languages.json';
        }
        $deeplLanguages = [];

        if(file_exists($languagesFile)){
            $langs = file_get_contents($languagesFile);

            $langs = json_decode($langs, true);
            $deeplLanguages = json_last_error() !== JSON_ERROR_NONE ? [] : $langs;

            $deeplLanguages = !empty($langs) ? $langs : $defLanguages;
        }

        if(empty($string) || empty($targetLanguage)){

            $msg = 'Your request is missing ' . (empty($string) ? 'a value to translate' : 'the target language to translate to');

            $system->addError(HEURIST_INVALID_REQUEST, $msg);
            return false;
        }

        // Check auth key has been defined
        if(empty($accessToken_DeepLAPI)){
            $system->addError(HEURIST_ACTION_BLOCKED, 'Deepl API key has not been configured - please ask your system administrator to setup the translator key');
            return false;
        }
        if(empty($serverName_DeepL)){
            $serverName_DeepL = 'https://api-free.deepl.com';
        }

        $url = '';

        $curlHandle = curl_init();
        $curlOptions = [

            CURLOPT_COOKIEFILE => '/dev/null',
            CURLOPT_RETURNTRANSFER => true, // return the output as a string from curl_exec
            CURLOPT_NOBODY => false,
            CURLOPT_HEADER => false, // don't include header in output
            CURLOPT_FOLLOWLOCATION => true, // follow server header redirects

            CURLOPT_TIMEOUT => 30, // timeout after thirty seconds
            CURLOPT_MAXREDIRS => 5, // no more than 5 redirections

            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.6) Gecko/2009011913 Firefox/3.0.6',
            CURLOPT_FAILONERROR => true,
            CURLOPT_AUTOREFERER => true
        ];

        // check if the proxy needs to be used, $httpProxyActive defined in heuristConfigIni.php
        $useProxy = defined('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE') && HEURIST_HTTP_PROXY_ALWAYS_ACTIVE && defined('HEURIST_HTTP_PROXY');
        if($useProxy){
            $curlOptions[CURLOPT_PROXY] = HEURIST_HTTP_PROXY;
            if(defined('HEURIST_HTTP_PROXY_AUTH')){
                $curlOptions[CURLOPT_PROXYUSERPWD] = HEURIST_HTTP_PROXY_AUTH;
            }
        }
        $curlOptions[CURLOPT_HTTPHEADER] = ["Authorization: DeepL-Auth-Key {$accessToken_DeepLAPI}"];

        curl_setopt_array($curlHandle, $curlOptions);

        // Handle target language
        if(strlen($targetLanguage) == 3){ // get ar2
            $targetLanguage = $glb_lang_codes_index[$targetLanguage];
        }
        if(!in_array($targetLanguage, $deeplLanguages)){
            $system->addError(HEURIST_INVALID_REQUEST, 'The provided language is not supported by Deepl.<br>If you believe this is in error, please contact the Heurist team.');
            return false;
        }

        $isXML = strpos($string, '<?xml') === 0;

        /**
         * free => api-free.deepl.com
         * pro => api.deepl.com
         */
        $url = "{$serverName_DeepL}/v2/translate?split_sentences=nonewlines&target_lang={$targetLanguage}";

        // Handle source language
        if(!empty($sourceLanguage) && strlen($sourceLanguage) == 3){ // get ar2
            $sourceLanguage = $glb_lang_codes_index[$sourceLanguage];
        }

        if(!empty($sourceLanguage) && in_array($sourceLanguage, $deeplLanguages)){
            $k = array_search($sourceLanguage, $deeplLanguages);
            $url .= "&source_lang={$deeplLanguages[$k]}";
        }

        if($isXML){ // possible xml
            $url .= '&tag_handling=xml&ignore_tags=notranslate';
        }else{ // assume html
            $url .= '&tag_handling=html';
        }

        $string = replaceEncodedEntities($string);

        $response = processDeepLRequest($curlHandle, $url, $string);
        if(is_array($response) && array_key_exists('status', $response)){
            $system->addErrorArr($response);
            return false;
        }

        return replacePunctuation($response, true);
    }

    function processDeepLRequest($curlHandle, $baseURL, $string){

        if(empty($baseURL) || empty($string)){
            return '';
        }

        $CHUNK_SIZE = 3000;

        $performRequest = function($textChunk) use ($curlHandle, $baseURL) {

            $textChunk = urlencode($textChunk);

            curl_setopt($curlHandle, CURLOPT_URL, "{$baseURL}&text={$textChunk}");
            
            $data = curl_exec($curlHandle);
    
            $error = curl_error($curlHandle);
    
            if($error){
    
                $hmsg = '';// Heurist's error message
                $herror = HEURIST_UNKNOWN_ERROR;
                $code = intval(curl_getinfo($curlHandle, CURLINFO_HTTP_CODE));
    
                switch($code){
    
                    // Deepl error codes: https://support.deepl.com/hc/en-us/articles/9773964275868-DeepL-API-error-messages
    
                    case 400: // Missing parameter
                        $herror = HEURIST_INVALID_REQUEST;
                        $hmsg = "Deepl was unable to complete this request.<br>
                        Please make a bug report if this persists.";
                        break;
    
                    case 403: // Invalid API key
                        $herror = HEURIST_REQUEST_DENIED;
                        $hmsg = "Heurist was unable to access Deepl.<br>
                        This may be due to an error in handling or the necessary API key is missing.<br>
                        Please contact your system administrator and ask them if the API key has been configured.";
                        break;
    
                    case 404: // Wrong URL, e.g. using the free version URL for paid access
                    case 504:
                        $herror = HEURIST_INVALID_REQUEST; //HEURIST_NOT_FOUND
                        $hmsg = "Deepl encountered an error with locating the desired function.<br>
                        Please make a bug report.";
                        break;
    
                    case 429: // Too many requests
                    case 529: // Deepl is busy
                        $herror = HEURIST_ACTION_BLOCKED;
                        $hmsg = "Deepl is currently busy processing other requests.<br>
                        Please re-try your request in a few minutes.";
                        $error = '';
                        break;
    
                    case 456: // [Free] Reached 500,000 character limit, [Paid] Reached cost control limit
                        $herror = HEURIST_ACTION_BLOCKED;
                        $hmsg = "Heurist has exceeded it's quota with Deepl and will be unable to attempt automatic translations of your texts.<br>
                        We apologise for the inconvenience.";
                        break;
    
                    case 413: // Request Too Large from Deepl
                    case 414: // HTTP Reuest Too Large
                        $herror = HEURIST_ACTION_BLOCKED;
                        $hmsg = "The request to Deepl's services was too large to process.<br>
                        Please either:<br>
                        Split the value into smaller parts and then re-combine them once finished, or<br>
                        Make a bug report including which record and field you were attempting to translate and into which language.";
                        break;
    
                    case 503: // Unknown Deepl error
                        $herror = HEURIST_ACTION_BLOCKED;
                        $hmsg = "Deepl encountered an unknown error.<br>
                        Please re-try your request in a few minutes.";
                        break;
    
                    default: // unknown error or no additional handling
                        $herror = HEURIST_UNKNOWN_ERROR;
                        $hmsg = "An unknown error occurred with Deepl's services.<br>
                        Please re-try your request in a few minutes.<br>
                        If this problem persists, please make a bug report.<br><br>
                        Response error: <strong>{$error}</strong>";
                        break;
                }
    
                return ['status' => $herror, 'message' => $hmsg, 'sysmsg' => $error];
            }
    
            $data = json_decode($data, true);
            if(json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !array_key_exists('translations', $data)){
                return ['status' => HEURIST_ERROR, 'message' => 'Deepl has responsed in an unknown format.<br>Please report this to the Heurist team.'];
            }

            $translation = $data['translations'];
            if(is_array($translation) && !empty($translation)){
                $textChunk = $translation[0]['text'];
            }

            return $textChunk;
        };

        if(mb_strlen($string) <= $CHUNK_SIZE){
            return $performRequest($string);
        }

        $originalString = $string;
        $replacements = [];
        if(strpos($baseURL, '=html') !== false){

            $fakeHTML = "<div>$string</div>";

            $doc = new DOMDocument;
            $doc->loadHTML(mb_encode_numericentity($fakeHTML, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD); // load html

            $xpath = new DOMXPath($doc); // to retrieve text only
            $textNodes = $xpath->query('//text()[not(ancestor::*[@translate="no"])]'); // get all text nodes not within translate=no tags

            $idx = 0;
            foreach($textNodes as $node){

                if(empty(trim($node->textContent))){
                    continue;
                }

                $text = replacePunctuation($node->textContent);

                if(mb_strlen($text) <= $CHUNK_SIZE){                    
                    $replacements["__{$idx}__"] = $text;
                    $node->textContent = "__{$idx}__";
                    $idx ++;
                    continue;
                }

                $textWords = preg_split('/(\r\n|\r|\n|\.)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                $currentChunk = '';
                foreach($textWords as $word){

                    if(!empty(trim($word)) && (mb_strlen($currentChunk . $word) < $CHUNK_SIZE || $word === '.')){
                        $currentChunk .= $word;
                    }elseif(empty($currentChunk)){

                        $replaced = false;
                        $idxKey = "__{$idx}__";
                        $replacements[$idxKey] = $currentChunk;
                        mb_ereg_replace_callback($currentChunk, function($match) use (&$replaced, $idxKey) {

                            if($replaced){
                                return $match[0];
                            }

                            $replaced = true;
                            return $idxKey;

                        }, $node->textContent);

                        $idx ++;
                    }
                }
            }

            $titleNodes = $xpath->query('//@title[not(ancestor::*[@translate="no"])]'); // get all titles not within, or used with, translate=no tags
            foreach($titleNodes as $node){

                if(empty(trim($node->nodeValue))){
                    continue;
                }

                $text = replacePunctuation($node->nodeValue);

                $replacements["__{$idx}__"] = $text;
                $node->nodeValue = "__{$idx}__";
                $idx ++;
            }

            $string = $doc->saveHTML();

            $string = mb_substr($string, 5, -7); // remove placeholders
        }

        $originalString = $string;
        foreach($replacements as $placeholder => $value){

            if(mb_strlen(trim($value)) <= 0){
                $string = mb_ereg_replace($placeholder, $value, $string);
            }else{

                $result = $performRequest($value);

                if(is_array($result)){
                    return $result;
                }
                $string = mb_ereg_replace($placeholder, $result, $string);
            }

            if(!$string){
                $string = $originalString;
            }else{
                $originalString = $string;
            }
        }

        return $string;
    }

    /**
     * Replace specific punctuation that Deepl has issues translating with a place holder
     * Deepl seems to consider any semicolon, even those within HTML attributes, invalid punctuation and cuts off the translation
     * Also replaces ampersands as Deepl encodes the ampersand
     *
     * @param string $string The string potential containing the specific punctuation
     * @param bool $reverse Whether to reverse the process, done after Deepl has translated the text
     * @param bool $swapComparison Whether to replace greater than and less than symbols
     * @return string The string prepared for translation
     */
    function replacePunctuation($string, $reverse = false, $swapComparison = false){

        $punc = [ [';', '__SC__'], [':', '__CL__'], ['&', '__AMP__'] ];
        if($swapComparison || $reverse){
            $punc[] = ['<', '__GT__'];
            $punc[] = ['>', '__LT__'];
        }

        foreach($punc as $punctuation){

            $search = $punctuation[0];
            $replace = $punctuation[1];

            if($reverse){
                $search = $punctuation[1];
                $replace = $punctuation[0];
            }

            $res = mb_ereg_replace($search, $replace, $string);

            if($res && !empty($res)){
                $string = $res;
            }
        }

        return $string;
    }

    /**
     * Replace specific HTML entities that could be translated by Deepl with their HTML code counter part
     * Deepl will translate simple words like 'copy' breaking the entities and displaying all the related ampersands and semicolons
     * HTML codes will work just as well and, realistically, shouldn't be translated by Deepl
     *
     * @param string $string The string potential containing entities that could become translated
     * @return string The string prepared for translation
     */
    function replaceEncodedEntities($string){

        $entities = [
            'copyright' => [
                '(?:&copy;|©)',
                '&#169;'
            ],
            'registered' => [
                '(?:&reg;?|®)',
                '&#174;'
            ],
            'trademark' => [
                '(?:&trade;?|™)',
                '&#8482;'
            ],
            /*'at' => [
                '(?:&commat;?|@)',
                '&#64;'
            ],*/
            'euro' => [
                '(?:&euro;?|€)',
                '&#8364;'
            ],
            'dollar' => [
                '(?:&dollar;?|\$)',
                '&#36;'
            ],
            'cent' => [
                '(?:&cent;?|¢)',
                '&#162;'
            ],
            'pound' => [
                '(?:&pound;?|£)',
                '&#163;'
            ],
            'yen' => [
                '(?:&yen;?|¥)',
                '&#165;'
            ],
            'section' => [
                '(?:&sect;?|§)',
                '&#167;'
            ],
            'ampersand' => [
                '(?:&amp;?)',
                '&#38;'
            ]
        ];

        foreach($entities as $entity){

            $search = $entity[0];
            $replace = $entity[1];

            $res = mb_ereg_replace($search, $replace, $string);

            if(!empty($res)){
                $string = $res;
            }
        }

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
