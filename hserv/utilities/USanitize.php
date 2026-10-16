<?php
/**
* USanitize.php - Class USanitize
*
* Utility class for input sanitization and HTML purification.
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
namespace hserv\utilities;

/**
* Class USanitize
* 
* Utility class for input sanitization and HTML purification within Heurist.
*
* Provides static methods to sanitize various types of input data including request parameters,
* paths, URLs, strings, and filenames. It helps prevent common web vulnerabilities like
* Cross-Site Scripting (XSS), path traversal, and log injection.
* This class also integrates HTMLPurifier for robust HTML cleaning.
*
* Key methods include:
* - sanitizeRequest: Recursively sanitizes an array of parameters.
* - sanitizePath: Normalizes and secures file system paths.
* - sanitizeURL: Validates and sanitizes URLs.
* - sanitizeString: Strips tags and optionally HTML entities from a string.
* - purifyHTML: Cleans HTML content using HTMLPurifier.
* - sanitizeFileName: Cleans and standardizes filenames.
* - errorLog: Safely logs messages, preventing log injection.
* - cleanupSpaces: Removes extraneous whitespace from strings or arrays of strings.
*/
class USanitize {

    private static $purifier = null;
    /**
     * Retrieves a password (typically admin) from the $_REQUEST global array and then unsets it.
     * This is a security measure to prevent accidental exposure of the password in logs or error messages.
     *
     * @param string $name Optional. The key in the $_REQUEST array where the password is expected. Defaults to 'pwd'.
     * @return string|null The password string if found, otherwise null.
     */
    public static function getAdminPwd($name='pwd'){
        if(@$_REQUEST[$name]){
            $sysadmin_pwd  = $_REQUEST[$name];
            unset($_REQUEST[$name]);
        }else{
            $sysadmin_pwd = null;
        }
        return $sysadmin_pwd;
    }

    /**
     * Retrieves and filters superglobal input arrays (POST or GET) based on the request method.
     * Uses `filter_input_array`. Note: This function does not apply deep sanitization beyond what filter_input_array does by default.
     *
     * @return array|null The filtered input array (POST or GET), or null if the respective superglobal is not set.
     */
    public static function sanitizeInputArray()
    {
        if (@$_SERVER['REQUEST_METHOD'] === 'POST') {
            $req_params = filter_input_array(INPUT_POST) ?: [];
        } else {
            $req_params = filter_input_array(INPUT_GET) ?: [];
        }

        // Merge pretty-url router params (query wins; router fills missing)
        // Prefer a dedicated global to avoid cookies pollution in $_REQUEST.
        $route_params = $GLOBALS['HEURIST_ROUTE_PARAMS'] ?? null;
        if (is_array($route_params)) {
            foreach ($route_params as $k => $v) {
                if ($k === '' || $k === null) continue;
                if (!array_key_exists($k, $req_params)) {
                    $req_params[$k] = $v;
                }
            }
        }

        return $req_params;
    }


    /**
     * Recursively sanitizes an array of parameters by trimming whitespace and applying `filter_var` with `FILTER_SANITIZE_STRING`.
     * Note: `FILTER_SANITIZE_STRING` is deprecated in PHP 8.0. Consider alternatives for future compatibility.
     * This method is marked to be removed as it's used only once in usr_info.php.
     *
     * @param array &$params The array of parameters to sanitize (passed by reference).
     * @return void
     */
    public static function sanitizeRequest(&$params){

        foreach($params as $k => $v)
        {
            if($v!=null){

                if(!isEmptyArray($v)){
                    USanitize::sanitizeRequest($v);

                }else{
                    $v = trim($v);//so we are sure it is whitespace free at both ends

                    //sanitise string
                    $v = filter_var($v, FILTER_SANITIZE_STRING);

                }
                $params[$k] = $v;
            }
        }

    }

    /**
     * Sanitizes a file system path to prevent path traversal attacks (e.g., removing "/../").
     * Normalizes path separators to forward slashes, optionally converting back to native OS separator.
     *
     * @param string|null $path The file path to sanitize.
     * @param bool $use_native_separator Optional. If true, converts sanitized path separators to the OS's native DIRECTORY_SEPARATOR. Defaults to false.
     * @return string The sanitized path, or an empty string if input is invalid or results in an out-of-root path.
     */
    public static function sanitizePath($path, $use_native_separator=false) {
        // Skip invalid input.
        if (!isset($path)) {
            return '';
        }
        if ($path === '') {
            return '';
        }

        // Attempt to avoid path encoding problems.
        //$path = preg_replace("/[^\x20-\x7E]/", '', $path);
        $path = str_replace("\0", '', $path);
        $path = str_replace('\\', '/', $path);

        // Remember path root.
        $prefix = substr($path, 0, 1) === '/' ? '/' : '';

        // Process path components
        $stack = array();
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                // No-op: skip empty part.
            } elseif ($part !== '..') {
                array_push($stack, $part);
            } elseif (!empty($stack)) {
                array_pop($stack);
            } else {
                return '';// Out of the root.
            }
        }

        // Return the "clean" path
        $path = $prefix . implode('/', $stack);
        if( is_dir($path) && substr($path, -1, 1) != '/' )  {
            $path = $path.'/';
        }

        if($use_native_separator && DIRECTORY_SEPARATOR!='/'){
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        }

        return $path;
    }

    /**
     * Sanitizes a URL using `FILTER_SANITIZE_URL` and then validates it using `FILTER_VALIDATE_URL`.
     *
     * @param string|null $url The URL to sanitize and validate.
     * @return string|null The sanitized and validated URL if valid, otherwise null.
     */
    public static function sanitizeURL($url){
        if($url!=null && trim($url)!=''){
            $url = filter_var($url, FILTER_SANITIZE_URL);
            if(filter_var($url, FILTER_VALIDATE_URL)){
                return $url;
            }
        }
        return null;
    }

    /**
     * Sanitizes a string by stripping tags (except allowed ones) and optionally converting special characters to HTML entities.
     * Provides a basic level of HTML sanitization. For more robust HTML cleaning, use `purifyHTML`.
     *
     * @param string|null $message The string to sanitize.
     * @param string|null|false $allowed_tags Optional. A string of allowed HTML tags (e.g., "<a><p><img>").
     *                                      If null (default), a predefined list of common safe tags is used.
     *                                      If false, all tags are stripped.
     * @param bool $allowed_entities Optional. If true (default), decodes existing HTML entities like &amp;amp; back to &amp;.
     * @return string The sanitized string.
     */
    public static function sanitizeString($message, $allowed_tags=null, $allowed_entities=true){
        if($message==null){
            $message = '';
        }else{
            if($allowed_tags==null) {
                $allowed_tags = '<a><u><i><div><em><b><strong><sup><sub><small><br><h1><h2><h3><h4><h5><h6><p><ol><ul><li><img><blockquote><pre><span><bibl><persName><audio><video><iframe><source><table><th><tr><td><article><aside><details><figcaption><figure><footer><header><main><mark><nav><section><summary><time>';
                
            }elseif($allowed_tags===false){
                $allowed_tags = null;
            }

            $message = strip_tags($message, $allowed_tags);
            if($allowed_tags!=null){
                // remove attributes except img.src and a.href a.target
                //$message = preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/si",'<$1$2>', $message);//remove all attributes

                //$clean = preg_replace("/\n(<[^ai]([\w\d]+)?).+/i","\n$1>",$clean);
                //$clean = preg_replace("/<a.+href='([:\w\d#\/\-\.]+)'.+/i","<a href=\"$1\">",$clean);
                //$clean = preg_replace("/<img.+src='([\w\d_:?.\/%=\-]+)'.+/i","<img src=\"$1\">",$clean);
            }

            $message = htmlspecialchars($message, ENT_NOQUOTES);
            if($allowed_tags!==false){
                $message = str_replace('&lt;', '<', $message);
                $message = str_replace('&gt;', '>', $message);
            }

            if($allowed_entities){
                $message = mb_ereg_replace_callback("&amp;([a-zA-Z]{2,35}|#[0-9]{1,6}|#x[a-fA-F0-9]{1,6});", function($matches){
                    return "&{$matches[1]};";
                }, $message);
            }
        }
        return $message;
    }

    /**
     * Recursively strips `<script>` tags from all string values within an array.
     *
     * @param array &$params The array of parameters to sanitize (passed by reference).
     * @return void
     */
    public static function stripScriptTagInRequest(&$params){

        foreach($params as $k => $v)
        {
            if($v!=null){

                if(!isEmptyArray($v)){
                    USanitize::stripScriptTagInRequest($v);
                }else{
                    $v = trim($v);//so we are sure it is whitespace free at both ends

                    //remove script tag
                    $v = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $v);

                }
                $params[$k] = $v;
            }
        }//for
    }

    /**
     * Gets a configured instance of the HTMLPurifier library.
     * Sets up HTMLPurifier with specific configurations for Heurist, including allowed elements,
     * CSS properties, and custom attributes.
     *
     * @return \HTMLPurifier An instance of the HTMLPurifier object.
     */
    public static function getHTMLPurifier(){

            $config = \HTMLPurifier_Config::createDefault();

            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            
            //reason: Warning: Due to a documentation error in previous version of HTML Purifier, your definitions are not being cached. If this is OK, you can remove the %$type.DefinitionRev and %$type.DefinitionID declaration. Otherwise, modify your code to use maybeGetRawDefinition, and test if the returned value is null before making any edits (if it is null, that means that a cached version is available, and no raw operations are necessary). 
            //$config->set('HTML.DefinitionID', 'html5-definitions');// unqiue id
            //$config->set('HTML.DefinitionRev', 1);

            $config->set('Cache.SerializerPath', HEURIST_SCRATCHSPACE_DIR);
            //$config->set('Core.EscapeNonASCIICharacters', true);
            $config->set('CSS.AllowImportant', true);
            $config->set('CSS.AllowTricky', true);//allow css
            $config->set('CSS.Proprietary', true);
            $config->set('CSS.Trusted', true);

            $config->set('Attr.AllowedFrameTargets','_blank');
            $config->set('HTML.SafeEmbed', true);
            $config->set('HTML.SafeIframe', true);
            /*$config->set('Core.AcceptFullDocuments',false);
            $config->set('Core.HiddenElements',array (
                    'script' => true,
                    'style' => false,
                    'head' => false,
                    ));
            $config->set('HTML.Trusted', true);
            $config->set('HTML.Allowed', array('head'=>true,'style'=>true));
            $config->set('HTML.AllowedElements', array('head'=>true,'style'=>true));
            */
            $def = $config->getHTMLDefinition(true);//non standard attributes
            $def->addAttribute('div', 'id', 'Text');
            $def->addAttribute('img', 'data-id', 'Text');
            $def->addAttribute('div', 'data-heurist-app-id', 'Text');
            $def->addAttribute('div', 'data-inited', 'Text');
            $def->addAttribute('a', 'data-ref', 'Text');

            return new \HTMLPurifier($config);

    }

    /**
     * Purifies HTML content using HTMLPurifier to prevent XSS and ensure valid markup.
     * Can purify a single string or recursively purify all string values within an array.
     * Used for cleaning HTML in mail and CMS content.
     *
     * @param string|array &$params The HTML string or array of strings to purify (passed by reference).
     * @param \HTMLPurifier|null $purifier Optional. A pre-configured HTMLPurifier instance.
     *                                     If null, a default instance is obtained via `getHTMLPurifier()`.
     * @return void
     */
    public static function purifyHTML(&$params, $purifier = null){

        if($purifier==null){
            if(self::$purifier==null){
               self::$purifier = USanitize::getHTMLPurifier();
            }
            $purifier = self::$purifier;
        }

        if(is_array($params)){

            foreach($params as $k => $v)
            {
                if($v!=null){

                    if(is_string($v) && !isEmptyArray($v)){ // Check if it's a string and not an array that isEmptyArray would misinterpret
                        $v = $purifier->purify($v);
                        //$v = htmlspecialchars_decode($v);
                    } elseif(is_array($v)) { // Only recurse if it's an array
                        USanitize::purifyHTML($v, $purifier);
                    }
                    $params[$k] = $v;
                }
            }//for
        } elseif(is_string($params)) { // Ensure it's a string before purifying
            $params = $purifier->purify($params);
        }
    }

    /**
     * Sanitizes a filename by removing control characters, reserved file system characters,
     * and optionally beautifying it (lowercase, replace spaces/multiple hyphens).
     * Limits filename length to a safe maximum.
     *
     * @param string|null $filename The filename to sanitize.
     * @param bool $beautify Optional. If true (default), applies beautification rules (lowercase, hyphenate).
     * @return string|null The sanitized filename, or null if input was null.
     */
    public static function sanitizeFileName($filename, $beautify=true) {
        // sanitize filename
        if($filename!=null){
    //            [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN - removed since it brokes utf-8 characters
    
    //            [#\[\]@!$&\'+,;=()]|     # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
    //            [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt

            $filename = preg_replace(
                '~
                [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
                [<>:"/\\|?*]             # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
                ~x',
                '-', $filename);
            // avoids ".", ".." or ".hiddenFiles"
            $filename = ltrim($filename, '.-');
            // optional beautification
            if ($beautify) {$filename = USanitize::fileNameBeautify($filename);}
            // maximize filename length to 255 bytes http://serverfault.com/a/9548/44086
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)) . ($ext ? '.' . $ext : '');
        }
        return $filename;
    }

    //
    //
    //
    protected static function fileNameBeautify($filename) {
        // reduce consecutive characters
        $filename = preg_replace(array(
            // "file   name.zip" becomes "file-name.zip"
            '/ +/',
            // "file___name.zip" becomes "file-name.zip"
            '/_+/',
            // "file---name.zip" becomes "file-name.zip"
            '/-+/'
        ), '-', $filename);
        $filename = preg_replace(array(
            // "file--.--.-.--name.zip" becomes "file.name.zip"
            '/-*\.-*/',
            // "file...name..zip" becomes "file.name.zip"
            '/\.{2,}/'
        ), '.', $filename);
        // lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
        $filename = mb_strtolower($filename, mb_detect_encoding($filename));
        // ".file-name.-" becomes "file-name"
        $filename = trim($filename, '.-');
        return $filename;
    }

    /**
    * Wraps around error_log to prevent log injection
    *
    * @param string $message The message to log.
    * @return void
    */
    public static function errorLog($message){
        $safe_message = preg_replace(REGEX_EOL, ' ', $message);
        error_log($safe_message);
    }

    /**
     * Removes leading, trailing, and multiple consecutive spaces/tabs from a string or an array of strings.
     *
     * @param string|array $value The input string or array of strings to clean up.
     * @param bool $removeSpaces Whether to completely remove the spaces
     * @return string|array The cleaned string or array of strings.
     */
    public static function cleanupSpaces($value, $removeSpaces = false){

        $regex = $removeSpaces ? '\s' : '[ \t]{2,}';
        $replace = $removeSpaces ? '' : ' ';

        if(\is_string($value)){
            $value = mb_ereg_replace($regex, $replace, $value);// strip double spaces and tabs
            return function_exists('super_trim') ? super_trim($value) : trim($value);
        }

        if(\is_array($value)){ // need to traverse through the array
            foreach($value as $idx => $val){
                $value[$idx] = self::cleanupSpaces($val, $removeSpaces);
            }
        }

        // else do nothing to avoid errors/faulty data

        return $value;
    }

}
?>
