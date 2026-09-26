<?php

/**
 * DeepL.php - Utility classs to contain all DeepL related functions used by Heurist
 * 
 * DeepL related functions used by Heurist.
 * This class provides a set of functions such as:
 * - Getting the DeepL account's rate limits
 * - Retrieving the list of available languages to translate from and to
 * - Performing the request to translate a string
 *
 * @project     Heurist academic knowledge management system
 * @package     Utilities
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Brandon McKay   <blmckay13@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @since       6.0
 */

namespace hserv\utilities;

use hserv\System;
use Exception;
use DOMDocument;
use DOMXPath;
use DOMNode;

use function defined;
use function array_key_exists;
use function intval;
use function count;
use function in_array;
use function strlen;
use function is_array;

require_once __DIR__ . '/../../autoload.php';

class DeepL{

    private System $system;
    private const DEEPL_CHUNK_SIZE = 3000;

    private string $DeepLKey = '';
    private string $DeepLBaseURL = '';
    private array $DeepLLanguages = [];

    /** @var \CurlHandle|resource|null $CurlHandle */
    private $CurlHandle = null;
    private array $CurlHandlers = [];
    private bool $returningHeaders = false;

    /** @var mixed $DeepLResponse */
    private $DeepLResponse = null;
    private array $DeepLHeaders = [];

    public function __construct(System $system, string $apiKey, ?string $serverURL){

        $this->system = $system;

        if(empty($apiKey)){
            throw new Exception('DeepL API Key is missing');
        }
        $this->DeepLKey = $apiKey;

        $this->DeepLBaseURL = !empty($serverURL) ? $serverURL : 'https://api-free.deepl.com';
        $this->DeepLBaseURL = rtrim($this->DeepLBaseURL, '/');

        $serviceAvailable = $this->isServiceAvailable();

        if(!$serviceAvailable){
            throw new Exception('The DeepL rate limit has been exceeded');
        }
    }

    /**
     * Get the DeepL account's rate limits
     *
     * @param string $type Which limits to get [translate, write]
     * @return array{limit: int, usage: int}|array{translate: array{limit: int, usage: int}, write: array{limit: int, usage: int}} | false} Array on success, false on failure
     */
    public function getRateLimits(string $type = 'translate'){

        if(!array_key_exists('rateCheck', $this->CurlHandlers)){
            $this->CurlHandlers['rateCheck'] = $this->initCurlHandler(false, 'get');
            $this->returningHeaders = false;
        }
        if(!$this->CurlHandlers['rateCheck']){
            return false;
        }

        $this->CurlHandle = $this->CurlHandlers['rateCheck'];

        $limitURL = "{$this->DeepLBaseURL}/v2/usage";
        curl_setopt($this->CurlHandle, CURLOPT_URL, $limitURL);

        $this->CurlHandle = $this->CurlHandlers['rateCheck'];
        if(!$this->performDeepLRequest()){
            return false;
        }

        $rateLimits = $type !== 'both' ? [$type => ['limit' => 0, 'usage' => 0]] : ['translate' => ['limit' => 0, 'usage' => 0], 'write' => ['limit' => 0, 'usage' => 0]];

        if($type !== 'write'){
            $rateLimits['translate'] = [
                'limit' => array_key_exists('translate', $this->DeepLResponse) ? $this->DeepLResponse['translate']['character_limit'] : $this->DeepLResponse['character_limit'],
                'usage' => array_key_exists('translate', $this->DeepLResponse) ? $this->DeepLResponse['translate']['character_count'] : $this->DeepLResponse['character_count']
            ];
        }
        if($type !== 'translate'){
            $rateLimits['write'] = [
                'limit' => array_key_exists('write', $this->DeepLResponse) ? $this->DeepLResponse['write']['character_limit'] : $this->DeepLResponse['character_limit'],
                'usage' => array_key_exists('write', $this->DeepLResponse) ? $this->DeepLResponse['write']['character_count'] : $this->DeepLResponse['character_count']
            ];
        }

        return $rateLimits;
    }

    /**
     * Checks if the request will work, and if DeepL is available
     *
     * @param int $offset For translation, if the text translation will fit within the limits
     * @return bool Whether to service can be considered available
     */
    public function isServiceAvailable(int $offset = 0) : bool{

        $limits = $this->getRateLimits('translate');

        return !$limits ? false : $limits['translate']['limit'] > $limits['translate']['usage'] + $offset;
    }

    /**
     * Retrieve the list of available DeepL languages
     *
     * @param string $resource DeepL service is this for [translate]
     * @param string $sourceOrTarget What to retrieve [source, target, both]
     * @return array{source: array}|array{target: array}|array{source: array, target: array}|false Arrays on success, false on failure
     */
    public function getDeepLLanguages(string $resource = 'translate', string $sourceOrTarget = 'both'){

        if(!array_key_exists('languages', $this->CurlHandlers)){
            $this->CurlHandlers['languages'] = $this->initCurlHandler(false, 'get');
            $this->returningHeaders = false;
        }
        if(!$this->CurlHandlers['languages']){
            return false;
        }

        $resourceParam = $resource === 'translate' ? 'translate_text' : '';
        if($resourceParam === ''){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Heurist doesn't currently support the requested translation type '{$resource}'.<br>Id you need this service, please submit a request by ticket.");
            return false;
        }

        $languageURL = "{$this->DeepLBaseURL}/v3/languages?resource=translate_text"; // /v2/languages?type=target
        curl_setopt($this->CurlHandle, CURLOPT_URL, $languageURL);

        $this->CurlHandle = $this->CurlHandlers['languages'];
        if(!$this->performDeepLRequest()){
            return false;
        }

        $languagesReturn = $sourceOrTarget !== 'both' ? [$sourceOrTarget => []] : ['source' => [], 'target' => []];

        foreach($this->DeepLResponse as $language){

            $languageName = $language['lang']; // AR2 Code
            if(strpos($languageName, '-') !== false){
                $languageName = explode('-', $languageName, 1)[0];
            }
            $languageName = strtoupper($languageName);

            if($sourceOrTarget !== 'target' && $language['usable_as_source'] && !in_array($languageName, $languagesReturn['source']) !== false){
                $languagesReturn['source'][] = $languageName;
            }
            if($sourceOrTarget !== 'source' && $language['usable_as_target'] && !in_array($languageName, $languagesReturn['target']) !== false){
                $languagesReturn['target'][] = $languageName;
            }
        }

        return $languagesReturn;
    }

    /**
     * Request the given text be translated by DeepL
     *
     * @param string $text Text to be translated
     * @param ?string $sourceLanguage Original language, can be blank
     * @param string $targetLanguage Translate into
     * @return string|false Translated text on success, false on failure
     */
    public function translateText(string $text, ?string $sourceLanguage, string $targetLanguage){

        if(empty($text)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Your request is missing a value to translate.');
            return false;
        }
        if(!$this->prepareTranslateText($sourceLanguage, $targetLanguage)){
            return false;
        }

        $isXML = strpos($text, '<?xml') === 0;

        $parameters = [
            'split_sentences' => 'nonewlines',
            'target_lang' => $targetLanguage
        ];

        if(!empty($sourceLanguage)){
            $parameters['source_lang'] = $sourceLanguage;
        }

        if($isXML){ // possible xml
            $parameters['tag_handling'] = 'xml';
            $parameters['ignore_tags'] = 'notranslate';
        }else{ // assume html
            $parameters['tag_handling'] = 'html';
            $parameters['tag_handling_version'] = 'v2';
        }

        $this->replaceEncodedEntities($text);

        if(!$this->isServiceAvailable(strlen($text)) || !$this->processTranslateText($text, $parameters)){
            return false;
        }

        return $this->replacePunctuation($this->DeepLResponse, true);
    }

    /**
     * Prepares languages (source and target) before use in the translation request.
     *
     * @param string $sourceLanguage Source/original language in AR2 or AR3 language code format
     * @param string $targetLanguage Target language in AR2 or AR3 language code format
     * @return bool Whether the process was successful or not
     */
    private function prepareTranslateText(string &$sourceLanguage, string &$targetLanguage) : bool{

        global $glb_lang_codes_index;

        initLangCodes();

        $this->getCachedLanguages();

        if(empty($targetLanguage)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Your request is missing the target language to translate to.');
            return false;
        }

        // Ensure both source and target are AR2
        $defSourceLanguage = $this->system->userGetPreference('layout_language', 'fr');
        if(strlen($sourceLanguage) == 3){
            $sourceLanguage = $glb_lang_codes_index[$sourceLanguage];
        }
        $sourceLanguage = strtoupper($sourceLanguage);

        $isValidSourceLanguage = !in_array($sourceLanguage, $this->DeepLLanguages) || array_key_exists('source', $this->DeepLLanguages) && !in_array($sourceLanguage, $this->DeepLLanguages['source']);
        $sourceLanguage = $isValidSourceLanguage ? $sourceLanguage : '';
        $sourceLanguage = $sourceLanguage === '' && !empty($defSourceLanguage) ? $defSourceLanguage : $sourceLanguage;

        if(strlen($targetLanguage) == 3){
            $targetLanguage = $glb_lang_codes_index[$targetLanguage];
        }
        $targetLanguage = strtoupper($targetLanguage);
        $isValidTargetLanguage = !in_array($targetLanguage, $this->DeepLLanguages) || array_key_exists('target', $this->DeepLLanguages) && !in_array($targetLanguage, $this->DeepLLanguages['target']);

        if($isValidTargetLanguage){
            $this->system->addError(HEURIST_INVALID_REQUEST, "The target language \"{$targetLanguage}\" is not supported by DeepL.");
            return false;
        }

        return true;
    }

    /**
     * Performs the actual process of translating text via DeepL
     *
     * @param string $text Text to be translated
     * @param array $parameters Base parameters pre-set
     * @return bool Whether the translation has succeed or failed
     */
    private function processTranslateText(string $text, array $parameters) : bool{

        if(!array_key_exists('translate', $this->CurlHandlers)){
            $this->CurlHandlers['translate'] = $this->initCurlHandler(true, 'post');
            $this->returningHeaders = false;
            $this->DeepLResponse = null;
        }
        if(!$this->CurlHandlers['translate']){
            return false;
        }
        $this->CurlHandle = $this->CurlHandlers['translate'];

        $isHTML = $parameters['tag_handling'] === 'html';
        $translateURL = "{$this->DeepLBaseURL}/v2/translate";

        curl_setopt($this->CurlHandle, CURLOPT_URL, $translateURL);

        if(mb_strlen($text) <= self::DEEPL_CHUNK_SIZE){

            $parameters['text'] = $text;
            curl_setopt($this->CurlHandle, CURLOPT_POSTFIELDS, http_build_query($parameters));

            $result = $this->performDeepLRequest();

            $translation = $result ? $this->DeepLResponse['translations'] : false;
            if(is_array($translation) && !empty($translation)){
                $translation = $translation[0]['text'];
            }

            $this->DeepLResponse = $translation;
            return $translation !== false;
        }

        $replacements = [];
        if($isHTML){
            $replacements = $this->chunkifyHTMLText($text);
        }else{
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Heurist doesn\'t currently support the translation of large XML text.<br>Please make a ticket if you require this feature.');
            return false;
        }

        $originalText = $text;
        foreach($replacements as $placeholder => $value){

            if(mb_strlen(trim($value)) <= 0){
                $text = mb_ereg_replace($placeholder, $value, $text);
            }else{

                $parameters['text'] = $value;
                curl_setopt($this->CurlHandle, CURLOPT_POSTFIELDS, http_build_query($parameters));

                $result = $this->performDeepLRequest();

                if(!$result){
                    return false;
                }

                $translation = $this->DeepLResponse['translations'];
                $result = is_array($translation) && !empty($translation) ? $translation[0]['text'] : $value;

                $text = mb_ereg_replace($placeholder, $result, $text);
            }

            $text = $text ?: $originalText;
        }

        $this->DeepLResponse = $text;

        return true;
    }

    /**
     * Execute Curl request to DeepL using a prepared Curl handler
     *
     * @return bool Whether the request succeed or failed
     */
    private function performDeepLRequest() : bool{

        if(!$this->CurlHandle){
            $this->system->addError(HEURIST_ERROR, 'No CURL handler was provided to performDeepLRequest.<br>Please create a ticket.');
            return false;
        }

        $DeepLResponse = curl_exec($this->CurlHandle);

        $DeepLError = curl_error($this->CurlHandle);

        if($DeepLError){
            $this->processError($DeepLError);
            return false;
        }

        /*
        $headerSize = curl_getinfo($this->CurlHandle, CURLINFO_HEADER_SIZE);
        $headers = [];

        if($this->returningHeaders && $headerSize > 0){

            $headers = substr($DeepLResponse, 0, $headerSize);
            $this->processHeaders($headers);

            $DeepLResponse = substr($DeepLResponse, $headerSize);
        }
        */

        $jsonData = json_decode($DeepLResponse, true);

        if(json_last_error() !== JSON_ERROR_NONE){
            $this->system->addError(HEURIST_ERROR, 'DeepL returned a response not in JSON format.<br>Please create a ticket.', substr($DeepLResponse, 0, 25));
            return false;
        }

        //$this->DeepLHeaders = $headers;
        $this->DeepLResponse = $jsonData;

        return true;
    }

    /**
     * Process headers from Curl response, sets DeepLHeaders
     *
     * @param string $headerString Headers as a string from Curl response
     */
    private function processHeaders(string $headerString){

        $headers = [];

        $headerArray = explode("\r\n", trim($headerString));

        foreach($headerArray as $headerLine){

            $parts = explode(':', $headerLine, 2);

            if(count($parts) === 2){
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }

        $this->DeepLHeaders = $headers;
    }

    /**
     * Handles the error output, and set a Heurist system error.
     *
     * @param string $error Curl error message
     */
    private function processError(string $error){

        $hmsg = ''; // Heurist error message
        $herror = HEURIST_UNKNOWN_ERROR; // Heurist error status
        $code = intval(curl_getinfo($this->CurlHandle, CURLINFO_HTTP_CODE));

        $retryMessage = "Please check the <a href='https://status.deepl.com/?tab=api' target='_blank'>DeepL API status page</a> before re-trying your request in a few minutes.";

        switch($code){

            // Deepl error codes: https://developers.deepl.com/docs/best-practices/error-handling

            case 400: // Missing parameter
                $herror = HEURIST_INVALID_REQUEST;
                $hmsg = "DeepL was unable to complete this request.<br>
                Please create a ticket if this persists.";
                break;

            case 403: // Invalid API key
                $herror = HEURIST_REQUEST_DENIED;
                $hmsg = "Heurist was unable to access DeepL.<br>
                This may be due to an error in handling or the necessary API key is missing.<br>
                Please contact your system administrator and ask them if the API key has been configured.";
                break;

            case 404: // Wrong URL, e.g. using the free version URL for paid access
            case 504:
                $herror = HEURIST_INVALID_REQUEST; //HEURIST_NOT_FOUND
                $hmsg = "DeepL encountered an error with locating the desired function.<br>";
                $hmsg .= $code === 504 ? $retryMessage : "Please create a ticket.";
                break;

            case 429: // Too many requests
            case 529: // DeepL is busy
                $herror = HEURIST_ACTION_BLOCKED;
                $hmsg = "DeepL is currently busy processing other requests.<br>";
                $hmsg .= $code === 529 ? $retryMessage : "Please re-try your request in a few minutes.";
                $error = '';
                break;

            case 456: // [Free legacy] Reached 500,000 character/month limit, [Free] 1,000,000 character limit, [Paid] Reached cost control limit
                $herror = HEURIST_ACTION_BLOCKED;
                $hmsg = "Heurist has exceeded it's quota with DeepL and will be unable to attempt automatic translations of your texts.<br>
                We apologise for the inconvenience.";
                break;

            case 413: // Request Too Large from DeepL
            case 414: // HTTP Reuest Too Large
                $herror = HEURIST_ACTION_BLOCKED;
                $hmsg = "The request to DeepL's services was too large to process.<br>
                Please either:<br>
                Split the value into smaller parts and then re-combine them once finished, or<br>
                Create a ticket which includes: the record, the field and what language you are attempting to translate into.";
                break;

            case 503: // Unknown DeepL error
                $herror = HEURIST_ACTION_BLOCKED;
                $hmsg = "DeepL encountered an unknown error.<br>{$retryMessage}";
                break;

            default: // unknown error or no additional handling
                $herror = HEURIST_UNKNOWN_ERROR;
                $hmsg = "An unknown error occurred with DeepL's services.<br>{$retryMessage}<br>
                If this problem persists, please create a ticket.<br><br>Response error: <strong>{$error}</strong>";
                break;
        }

        $this->system->addError($herror, $hmsg, $error);
    }

    /**
     * Creates a Curl Handler.
     *
     * @param bool $allowHeaders Whether the headers should be returned with the response
     * @param string $httpMethod What HTTP method to use [POST, GET, PUT, DELETE]
     * @return \CurlHandle|false Curl Handler on success, false on failure
     */
    private function initCurlHandler(bool $allowHeaders = false, string $httpMethod = 'get'){

        $curlHandle = curl_init();

        if($curlHandle === false){
            $this->system->addError(HEURIST_ERROR, 'Failed to initialise Curl Handler for DeepL services');
            return false;
        }

        $curlOptions = [

            CURLOPT_COOKIEFILE => '/dev/null',
            CURLOPT_RETURNTRANSFER => true, // return the output as a string from curl_exec
            CURLOPT_NOBODY => false,
            CURLOPT_FOLLOWLOCATION => true, // follow server header redirects

            CURLOPT_TIMEOUT => 30, // timeout after thirty seconds
            CURLOPT_MAXREDIRS => 5, // no more than 5 redirections

            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.6) Gecko/2009011913 Firefox/3.0.6',
            CURLOPT_FAILONERROR => true,
            CURLOPT_AUTOREFERER => true
        ];

        $curlOptions[CURLOPT_HEADER] = false;//$allowHeaders;

        if($httpMethod === 'post'){
            $curlOptions[CURLOPT_POST] = true;
        }else{
            $curlOptions[CURLOPT_HTTPGET] = true;
        }

        // check if the proxy needs to be used, $httpProxyActive defined in heuristConfigIni.php
        $useProxy = defined('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE') && HEURIST_HTTP_PROXY_ALWAYS_ACTIVE && defined('HEURIST_HTTP_PROXY');
        if($useProxy){
            $curlOptions[CURLOPT_PROXY] = HEURIST_HTTP_PROXY;
            if(defined('HEURIST_HTTP_PROXY_AUTH')){
                $curlOptions[CURLOPT_PROXYUSERPWD] = HEURIST_HTTP_PROXY_AUTH;
            }
        }

        // add API key
        $curlOptions[CURLOPT_HTTPHEADER] = ["Authorization: DeepL-Auth-Key {$this->DeepLKey}"];

        curl_setopt_array($curlHandle, $curlOptions);

        return $curlHandle;
    }

    /**
     * Retrieves the full list of available DeepL languages, separated by source and target languages.
     *  Generally, both source and target lists will be the same, this is just in case.
     */
    private function getCachedLanguages(){

        if(!defined('HEURIST_FILESTORE_ROOT')){
            define('HEURIST_FILESTORE_ROOT', $this->system->getFileStoreRootFolder());
        }

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
        }

        $deeplLanguages = !empty($langs) ? $langs : $defLanguages;

        if(count($deeplLanguages) === 2 && array_key_exists('source', $deeplLanguages)){
            $deeplLanguages['source'] = array_map('strtoupper', $deeplLanguages['source']);
            $deeplLanguages['target'] = array_map('strtoupper', $deeplLanguages['target']);
        }else{
            $deeplLanguages = array_map('strtoupper', $deeplLanguages);
        }

        $this->DeepLLanguages = $deeplLanguages;
    }

    /**
     * Split HTML text into chunks, to avoid requests that are too large.
     *  Also performs substitutions for some punctuation that DeepL has trouble with.
     *
     * @param string $html Text to chunkify
     * @return string[] Replacements where key is the substitution and value is original text.
     */
    private function chunkifyHTMLText(string &$html) : array{

        $fakeHTML = "<div>$html</div>";

        $doc = new DOMDocument;
        $doc->loadHTML(mb_encode_numericentity($fakeHTML, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD); // load html

        $xpath = new DOMXPath($doc); // to retrieve text only
        $textNodes = $xpath->query('//text()[not(ancestor::*[@translate="no" or contains(@class, "notranslate")])]'); // get all text nodes not within translate=no tags

        $replacements = [];
        $idx = 0;
        foreach($textNodes as $node){

            if(empty(trim($node->textContent))){
                continue;
            }

            $text = $this->replacePunctuation($node->textContent);

            if(mb_strlen($text) <= self::DEEPL_CHUNK_SIZE){                    
                $replacements["__{$idx}__"] = $text;
                $node->textContent = "__{$idx}__";
                $idx ++;
                continue;
            }

            $textWords = preg_split('/(\r\n|\r|\n|\.)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            $currentChunk = '';
            foreach($textWords as $word){

                if(!empty(trim($word)) && (mb_strlen("{$currentChunk}{$word}") < self::DEEPL_CHUNK_SIZE || $word === '.')){
                    $currentChunk .= $word;
                }elseif(!empty($currentChunk)){

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

            if(empty(trim($node->nodeValue)) || !$node instanceof DOMNode){
                continue;
            }

            $text = $this->replacePunctuation($node->nodeValue);

            $replacements["__{$idx}__"] = $text;
            $node->nodeValue = "__{$idx}__";
            $idx ++;
        }

        $html = $doc->saveHTML();

        $html = mb_substr($html, 5, -7); // remove placeholders

        return $replacements;
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
    private function replacePunctuation(string $string, bool $reverse = false, bool $swapComparison = false) : string{

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
     * @param string $text The string potential containing entities that could become translated
     */
    private function replaceEncodedEntities(string &$text){

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

            $res = mb_ereg_replace($search, $replace, $text);

            if(!empty($res)){
                $text = $res;
            }
        }

        return $text;
    }

    public function __destruct(){

        foreach($this->CurlHandlers as $handle){
            unset($handle);
        }

    }
}
