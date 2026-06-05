<?php
/**
* USystem.php - Class USystem
*
* Utility class for retrieving system, PHP configuration, and user environment details.
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

require_once dirname(__FILE__).'/../../admin/utilities/checkMembershipLib.php';

/**
* Class USystem
* 
* Utility class for retrieving system, PHP configuration, and user environment details.
*
* Provides static methods for:
* - Determining host parameters (server name, URLs, installation directory).
* - Checking memory limits and converting configuration byte values.
* - Handling potential integer overflows.
* - Parsing user agent strings to identify OS and browser.
* - Retrieving user IP addresses.
* - Getting host-specific logo and URL.
* - Checking if Apache rewrite rules are enabled.
* - Managing session-related information like recent databases and cookie updates.
* - Executing daily maintenance scripts.
* - Fetching Heurist code and database version information.
* - Calculating script runtime.
* - Inserting Matomo analytics logging script.
*/
class USystem {

    /**
     * Detects host parameters (base URL, server name) or takes them from configuration file.
     * Handles both web server and command-line interface (CLI) environments.
     *
     * @global string|null $serverName Manually configured server name (from heuristConfigIni.php).
     * @global string|null $heuristBaseURL Manually configured base URL for Heurist (from heuristConfigIni.php).
     * @global string|null $heuristBaseURL_pro Manually configured base URL for production Heurist (from heuristConfigIni.php).
     * @param array|null $argv Optional. Arguments passed to a CLI script (e.g., $argv from a command line call).
     *                         Used to help determine path in CLI mode.
     * @return array An associative array with the following keys:
     *   'server_name' (string): Server name, including port if not standard (e.g., "heuristref.net:80").
     *   'domain' (string): Server domain name without port (e.g., "heuristref.net").
     *   'server_url' (string): Full server URL with scheme (e.g., "https://heuristref.net:80").
     *   'heurist_dir' (string): Detected Heurist code root directory path on the server.
     *   'baseURL' (string): Base URL for the current Heurist installation (e.g., "https://heuristref.net/hx-alpha/").
     *   'baseURL_pro' (string): Base URL for the production Heurist installation (e.g., "https://heuristref.net/heurist/").
     */
    public static function getHostParams( $argv=null )
    {
        global $serverName, $heuristBaseURL, $heuristBaseURL_pro;

        $host_params = array();

        $localhost = '127.0.0.1';

        $installDir = '';
        $installDir_pro = '';

        if (php_sapi_name() == 'cli'){

            if(!isset($serverName) || !$serverName){
                $serverName = $localhost;
            }

            $k = strpos($serverName,":");
            $host_params['domain'] = ($k>0)?substr($serverName,0,$k):$serverName;
            $isSecure = true;

            if($argv==null || !is_array($argv)){
                $sDir = getcwd();
            }else{
                $sDir = dirname(realpath($argv[0]));
            }


            $sDir = str_replace('\\','/',$sDir);

            
            $iDir = explode('/', $sDir);
            $cntDir = count($iDir) - 1;
            $path = [];

            for ($i = $cntDir; $i >= 0; $i--) {
                $segment = $iDir[$i];
                if(self::isHeuristCodeFolder($segment)){
                    $installDir = '/' . $segment . '/';
                    $path = array_slice($iDir, 0, $i);
                    break;
                }
            }   
            
            if ($installDir === '') {
                exit(
                    'Sorry, it is not possible to detect heurist installation folder from CLI path: '
                    . $sDir
                );
            }

            $installDir_pro = '/heurist/';
            $host_params['heurist_dir'] = implode('/',$path).'/';
            $host_params['server_name'] = $serverName;

            //echo "Install dir      $installDir \n";
            //echo "3>>> ".$host_params['heurist_dir']."\n";

        }else{

            // server name or IP address of your Web server, null will pull SERVER_NAME from the request header
            $always_detect = true;
            if ($always_detect){ //always detect dynamically  !@$serverName) {
                if(@$_SERVER["SERVER_NAME"]){

                    $host_params['server_name'] = $_SERVER["SERVER_NAME"] .
                    ((is_numeric(@$_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443")
                        ? ":" . $_SERVER["SERVER_PORT"] : "");
                    $host_params['domain'] = $_SERVER["SERVER_NAME"];
                }else{
                    $host_params['server_name'] = $localhost;
                    $host_params['domain'] = $localhost;
                }

            }else{
                $k = strpos($serverName,":");
                $host_params['domain'] = ($k>0)?substr($serverName,0,$k-1):$serverName;
                $host_params['server_name'] = $serverName;
            }

            $dir = realpath(dirname(__FILE__).'/../../'); //@$_SERVER["DOCUMENT_ROOT"];
            $dir = str_replace('\\', '/', $dir);
            if( substr($dir, -1, 1) != '/' )  {
                $dir .= '/';
            }
            $host_params['heurist_dir'] = $dir;

            $isSecure = false;
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
                $isSecure = true;
            }
            elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
                $isSecure = true;
            }

            if(!isset($heuristBaseURL)){
                //try to detect installation and production folders
                list($installDir, $installDir_pro) = USystem::detectInstalltionDir();
            }
        }

        $serverUrl = ($isSecure ? 'https' : 'http') . "://" . $host_params['server_name'];

        if(isset($heuristBaseURL)){
            $baseUrl = $heuristBaseURL;
            $baseUrl_pro = $heuristBaseURL_pro ?? $heuristBaseURL;

            if(strpos($baseUrl, $serverUrl)===false){ //alpha version is on different domain
                $baseUrl = $baseUrl_pro;
            }
            if( substr($baseUrl, -1, 1) != '/' )  {
                $baseUrl .= '/';
            }
            if( substr($baseUrl_pro, -1, 1) != '/' )  {
                $baseUrl_pro .= '/';
            }

        }else{
            //for auto detect both alpha and pro version must be on the same domain
            $baseUrl = $serverUrl . $installDir;
            $baseUrl_pro = $serverUrl . $installDir_pro;
        }

        $host_params['server_url']  = $serverUrl;
        $host_params['baseURL']     = $baseUrl;
        $host_params['baseURL_pro'] = $baseUrl_pro;

        return $host_params;

    }


    /**
    * if $heuristBaseURL is not defined in configuration detect installation folder and base url
    *
    */
    private static function detectInstalltionDir()
    {
        $installDir = '/heurist/';
        $installDir_pro = '/heurist/';

        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        $is_own_domain = (
            strpos($serverName, '.huma-num.fr') > 0
            && $serverName !== 'heurist.huma-num.fr'
        );
        
        $wasExplicitInstallDir = false;

        if (!$is_own_domain) {

            /*
             * Detect installation folder from the first path segment only.
             *
             * Valid installation folders:
             *   /heurist/
             *   /h7-alpha/
             *   /h7-test/
             *   /h7-<anything made of letters, numbers, _, ->
             *
             * Anything else is treated as a pretty URL:
             *   /dbname/anything
             *   /123/117
             *   /web/page
             *   /documentation/...
             *
             * In these cases the real installation folder is /heurist/.
             */
            $path = parse_url($scriptName, PHP_URL_PATH) ?: '';
            $path = trim($path, '/');

            $segments = $path === '' ? array() : explode('/', $path);
            $firstSegment = $segments[0] ?? '';

            if (self::isHeuristCodeFolder($firstSegment)) {
                $installDir = '/' . $firstSegment . '/';
                $wasExplicitInstallDir = true;
            }
        }

        /*
         * Production installation is always /heurist/.
         * This is intentionally no longer calculated from $installDir.
         */
        $installDir_pro = '/heurist/';

        // Validate detected installation folder.
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {

            $documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
            $testFile = $documentRoot . $installDir . 'configIni.php';

            if (!is_file($testFile)) {

                if ($wasExplicitInstallDir) {
                    exit(
                        'Sorry, detected Heurist installation folder does not exist: '
                        . htmlspecialchars($installDir)
                    );
                }

                $found = false;
                $entries = @scandir($documentRoot);

                if (is_array($entries)) {
                    foreach ($entries as $entry) {

                        if (!self::isHeuristCodeFolder($entry)) {
                            continue;
                        }

                        $candidateInstallDir = '/' . $entry . '/';
                        $candidateFile = $documentRoot . $candidateInstallDir . 'configIni.php';

                        if (is_file($candidateFile)) {
                            $installDir = $candidateInstallDir;
                            $found = true;
                            break;
                        }
                    }
                }

                if (!$found) {
                    exit(
                        'Sorry, it is not possible to detect heurist installation folder. '
                        . 'Please ask system administrator to verify server configuration.'
                    );
                }
            }
        }

        return array($installDir, $installDir_pro);
    }
    
    private static function isHeuristCodeFolder(string $folderName): bool
    {
        return $folderName === 'heurist'
            || preg_match('/^h7-[A-Za-z0-9_-]+$/', $folderName) === 1;
    }  
    
    /**
     * Checks if a specified amount of memory can be allocated within PHP's memory_limit.
     * Considers current memory usage and leaves a 10MB buffer.
     *
     * @param int $memoryNeeded The amount of memory required, in bytes.
     * @return bool|string True if memory is allowed, or an error string message if not.
     */
    public static function isMemoryAllowed( $memoryNeeded ){

        $mem_limit = self::getConfigBytes('memory_limit');
        $mem_usage = memory_get_usage();

        if ($mem_usage+$memoryNeeded > $mem_limit - 10485760){
            return 'It requires '.((int)($memoryNeeded/1024/1024)).
            ' Mb.  Available '.((int)($mem_limit/1024/1024)).' Mb';
        }else{
            return true;
        }
    }

    /**
     * Converts a PHP configuration string value (like '256M', '2G') into bytes.
     *
     * @param string $php_var The name of the PHP configuration variable (e.g., 'memory_limit', 'post_max_size').
     *                        This is used to fetch the value via `ini_get` if $val is not provided.
     * @param string|null $val Optional. The configuration value string to parse. If null, `ini_get($php_var)` is used.
     * @return int|float The value in bytes. Can be float for very large values due to PHP_INT_MAX.
     */
    public static function getConfigBytes( $php_var, $val=null ){

        if($val==null){
            $val = ini_get($php_var);
        }
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);

        if($last){
            $val = intval(substr($val,0,strlen($val)-1));
        }

        switch($last) {
            case 'g':
                $val *= 1073741824; break;
            case 'm':
                $val *= 1048576; break;
            case 'k':
                $val *= 1024; break;
            default;
        }
        return self::fixIntegerOverflow($val);
    }


    /**
     * Corrects potential integer overflow for sizes on 32-bit systems.
     * PHP integers are signed, so large unsigned values (like file sizes > 2GB on 32-bit)
     * can appear negative. This function adjusts them to their correct positive value.
     *
     * @param int $size The integer value (potentially overflowed).
     * @return float|int The corrected size, possibly as a float if it exceeds PHP_INT_MAX.
     */
    public static function fixIntegerOverflow($size) {
        if ($size < 0) {
            $size += 2.0 * (PHP_INT_MAX + 1);
        }
        return $size;
    }

    /**
     * Parses the HTTP User-Agent string to determine the client's operating system and browser.
     *
     * @return array An associative array with 'os' (string) and 'browser' (string) keys.
     *               Values are 'Unknown' if detection fails.
     */
    public static function getUserAgent(){

        $os = 'Unknown';
        $browser = 'Unknown';

        $ret = [
            'os' => $os,
            'browser' => $browser
        ];
        $ua_string = @$_SERVER['HTTP_USER_AGENT'];

        if(empty($ua_string)){
            return $ret;
        }

        // Get OS
        if(preg_match("/Android|ADR/i", $ua_string)){
            $os = 'Android';
        }elseif(preg_match("/CrOS/i", $ua_string)){
            $os = 'Chrome OS';
        }elseif(preg_match("/Linux/i", $ua_string)){
            $os = 'Linux';
        }elseif(preg_match("/Unix/i", $ua_string)){
            $os = 'Unix';
        }elseif(preg_match("/Win/i", $ua_string)){
            $os = 'Windows';
            // Check for version number
            preg_match("/Windows NT (\d+\.\d+)/i", $ua_string, $parts);
            if(count($parts) > 1){
                if($parts[1] == 10.0){ $os .= " 10/11";}
                elseif($parts[1] >= 6.4){ $os .= " 10";}
                elseif($parts[1] >= 6.2){ $os .= " 8";}
                elseif($parts[1] >= 6.1){ $os .= " 7";}
            }
        }elseif(preg_match("/CPU (iPhone )?OS/i", $ua_string)){
            $os = 'iOS';
        }elseif(preg_match("/Mac/i", $ua_string) || preg_match("/Darwin/i", $ua_string)){
            $os = preg_match("/Darwin/i", $ua_string) ? 'Mac OS X' : 'macOS';
        }
        /*
        elseif(preg_match("/Googlebot/i", $ua_string)){
            $os = 'Google bot';
        }elseif(preg_match("/Yahoo\! Slurp/i", $ua_string)){
            $os = 'Yahoo bot';
        }elseif(preg_match("/bingbot/i", $ua_string)){
            $os = 'Bing bot';
        }
        */

        $ret['os'] = $os;

        // Get browser
        if(preg_match("/Firefox|FxiOS/i", $ua_string)){
            $browser = preg_match("/FxiOS/", $ua_string) ? 'Firefox iOS' : 'Firefox';
        }elseif(preg_match("/Opera|OPR/i", $ua_string)){
            $browser = 'Opera';
        }elseif(preg_match("/Edge|Edg|EdgA|EdgiOS/i", $ua_string)){
            $browser = preg_match("/EdgA/", $ua_string) ? 'MS Edge Android' : 'MS Edge';
            $browser = preg_match("/EdgiOS/", $ua_string) ? 'MS Edge iOS' : $browser;
        }elseif(preg_match("/Vivaldi/i", $ua_string)){
            $browser = 'Vivaldi';
        }elseif(preg_match("/YaBrowser/i", $ua_string)){
            $browser = 'Yandex';
        }elseif(preg_match("/Chrome|CriOS/i", $ua_string)){
            $browser = preg_match("/CriOS/", $ua_string) ? 'Chrome iOS' : 'Chrome';
        }elseif(preg_match("/Safari/i", $ua_string)){
            $browser = 'Safari';
        }
        /*
        elseif(preg_match("/MSIE|Trident/i", $ua_string)){
            $browser = 'Internet Explorer';
        }
        */

        $ret['browser'] = $browser;

        return $ret;
    }
    
    /**
     * Retrieves the client's IPv4 address from various HTTP headers or `$_SERVER['REMOTE_ADDR']`.
     *
     * @return string The determined IPv4 address, or 'Unknown' if not found or invalid.
     */
    public static function getUserIP(){
        
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = null;
        }
        
        $ipaddress = filter_var($ipaddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)??'Unknown';
        
        return $ipaddress;        
    }

    /**
     * Retrieves the path/URL to the host organization's logo and the organization's URL.
     * Looks for 'organisation_logo.jpg' or '.png' and 'organisation_url.txt'
     * in the root directory of the Heurist installation (alongside heuristConfigIni.php).
     *
     * @param bool $return_url Optional. If true (default), returns the logo path as a URL (HEURIST_BASE_URL . '?logo=host').
     *                         If false, returns the actual file system path to the logo.
     * @return array An array containing:
     *               0: (string|null) Path or URL to the host logo, or null if not found.
     *               1: (string|null) Content of the host URL file, or null if not found.
     *               2: (string) The MIME type extension of the logo ('jpg' or 'png').
     */
    public static function getHostLogoAndUrl($return_url = true){

        //host organization logo and url (specified in root installation folder next to heuristConfigIni.php)
        $host_logo = realpath(dirname(__FILE__)."/../../../organisation_logo.jpg");
        $mime_type = 'jpg';
        if(!$host_logo || !file_exists($host_logo)){
            $host_logo = realpath(dirname(__FILE__)."/../../../organisation_logo.png");
            $mime_type = 'png';
        }
        $host_url = null;
        if($host_logo!==false && file_exists($host_logo)){

            !$return_url || $host_logo = defined('HEURIST_BASE_URL') ? HEURIST_BASE_URL.'?logo=host' : null;

            $host_url = realpath(dirname(__FILE__)."/../../../organisation_url.txt");
            if($host_url!==false && file_exists($host_url)){
                $host_url = file_get_contents($host_url);
            }else{
                $host_url = null;
            }
        }else{
            $host_logo = null;
        }

        return array($host_logo, $host_url, $mime_type);
    }
    /**
     * Checks if Apache mod_rewrite (or equivalent URL rewriting) appears to be enabled.
     * It does this by trying to fetch a non-existent URL that should be rewritten by Heurist's rules.
     * If it gets a 404, it assumes rewrite rules are not working as expected.
     *
     * @return bool True if rewrite rules seem enabled, false otherwise (e.g., on 404 or connection error).
     */
    public static function checkRewriteRuleEnabled(){
        
        $url = HEURIST_SERVER_URL . '/abc/web'; 
         
        $rewriteRuleEnabled = true; 
                    
        $headers = @get_headers($url);
        if(!$headers || $headers[0] == 'HTTP/1.1 404 Not Found'){
            //Timeout out or 404
            $rewriteRuleEnabled = false;
        }
        
        return $rewriteRuleEnabled;
    }
       

    //======================= session routines =================================
   
    /**
    * Requests heuristref.net membership for current user or server+database
    */
    public function isMemberOfAssociation(){
        return false;
    }    

    /**
     * Checks if the PHP session save path is configured, exists, and is writable.
     * Sends an email to admin if the folder becomes inaccessible.
     *
     * @return bool True if session save path is valid and writable, or if not using files for session handling. False otherwise.
     */
    public static function sessionCheckFolder(){

        if(!ini_get('session.save_handler')=='files') { return true; }

        $folder = session_save_path();
        if(file_exists($folder) && is_writeable($folder)){ return true; }

        sendEmailToAdmin('Session folder access', 'The sessions folder has become inaccessible', true);

        return false;
    }

    /**
     * Updates the 'heurist-sessionid' cookie lifetime.
     * Extends the cookie to keep it alive, typically for 30 days from now if $lifetime is null.
     * Sets Secure, HttpOnly, and SameSite=Strict attributes.
     *
     * @param int|null $lifetime Optional. The Unix timestamp for cookie expiry. Defaults to time() + 30 days.
     * @return bool True if cookie was successfully sent, false otherwise.
     */
    public static function sessionUpdateCookies($lifetime=null){

        $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');

        //update cookie - to keep it alive for next 30 days
        if($lifetime==null){
                $lifetime = time() + 30*24*60*60;
        }

        $session_id = session_id(); //ID of current session $cookie_session_id

        if (strnatcmp(phpversion(), '7.3') >= 0) {
            $cres = setcookie('heurist-sessionid', $session_id, array(
                'expires' => $lifetime,
                'path' => '/',
                'domain' => '',
                'Secure' => $is_https,
                'HttpOnly' => true,
                'SameSite' => 'Strict' //'Lax'
            ));
        }else{
            //workaround: header("Set-Cookie: key=value; path=/; domain=example.org; HttpOnly; SameSite=Lax")
            $cres = setcookie('heurist-sessionid', $session_id, $lifetime, '/', '', $is_https, true );
        }

        return $cres;
    }


    //======================= daily actions =================================
    /**
     * Executes daily maintenance scripts/tasks.
     * Uses a flag file (e.g., "once_per_day_YYYY-MM-DD") in HEURIST_FILESTORE_ROOT to ensure tasks run only once per day.
     * Removes flag files for previous days.
     * Tasks include sending daily error reports, checking Heurist version, and updating DeepL languages.
     * @param \hserv\System $system Initialised Heurist system instance
     * @return void
     */
    public static function executeScriptOncePerDay($system){

        $now = getNow();
        $flag_file = HEURIST_FILESTORE_ROOT.'once_per_day_'.$now->format('Y-m-d');

        if(file_exists($flag_file)){
            return;
        }

        file_put_contents($flag_file,'1');

        //remove flag files for previous days
        for($i=1;$i<10;$i++){
            $d = getNow();
            $yesterday = $d->sub(new \DateInterval('P'.sprintf('%02d', $i).'D'));
            $arc_flagfile = HEURIST_FILESTORE_ROOT.'once_per_day_'.$yesterday->format('Y-m-d');
            $arc_flagfile2 = HEURIST_FILESTORE_ROOT.'flag_'.$yesterday->format('Y-m-d');
            //if yesterday log file exists
            if(file_exists($arc_flagfile)){
                unlink($arc_flagfile);
            }
            if(file_exists($arc_flagfile2)){
                unlink($arc_flagfile2);
            }
        }

        //add functions for other daily tasks
        self::sendDailyErrorReport();
        self::heuristVersionCheck();// Check if different local and server code versions are different
        self::updateDeeplLanguages();// Get list of allowed target languages from Deepl API
        self::removePreparedParameters($system);// Remove potential leftover prepared parameters
    }

    /**
     * Sends a daily error report by email to the admin.
     * Consolidates error log files from the past 30 days, archives them, and emails the content.
     *
     * @return void
     */
    private static function sendDailyErrorReport(){

        $root_folder = HEURIST_FILESTORE_ROOT;
        
        $archiveFolder = $root_folder."AAA_LOGS/";
        $logFolder = $root_folder."_LOGS/";
        $logs_to_be_emailed = array();
        $y1 = null;
        $y2 = null;
        $logFileForYesterday = null;

        //1. check if log files for previous 30 days exist
        for($i=1;$i<31;$i++){
            $now = getNow();
            $yesterday = $now->sub(new \DateInterval('P'.sprintf('%02d', $i).'D'));
            $arc_logfile = 'errors_'.$yesterday->format('Y-m-d').'.log';
            if($i==1){
                $logFileForYesterday = $logFolder.$arc_logfile;
            }
            
            //if yesterday log file exists
            if(file_exists($logFolder.$arc_logfile)){
                //2. copy to log archive folder
                fileCopy($logFolder.$arc_logfile, $archiveFolder.$arc_logfile);
                unlink($logFolder.$arc_logfile);

                $logs_to_be_emailed[] = $archiveFolder.$arc_logfile;

                $y2 = $yesterday->format('Y-m-d');
                if($y1==null) {$y1 = $y2;}
            }
        }

        if(empty($logs_to_be_emailed)){
            $msgTitle = 'No serious errors logged '.HEURIST_SERVER_NAME;
            $msg = $msgTitle . '<br>('.$logFileForYesterday.' was not created)';
        }else{
            $msgTitle = 'Error report '.HEURIST_SERVER_NAME.' for '.$y1.($y2==$y1?'':(' ~ '.$y2));
            $msg = $msgTitle;
            foreach($logs_to_be_emailed as $log_file){
                $msg = $msg.'<br>'.file_get_contents($log_file);
            }
        }
        //'Bug reporter',
        sendEmail(HEURIST_MAIL_TO_BUG, $msgTitle, $msg, true);
        
        // TODO: needs an else in case there are no logfiles corresponding with the expected path and name
        //       this code seems rather too fragile to be portable between systems


    }

    /**
     * Checks if the locally installed Heurist version is outdated compared to the main server's version.
     * Sends an email notification to the admin if an update is available.
     *
     * @return void
     */
    private static function heuristVersionCheck(){

        $local_ver = HEURIST_VERSION; // installed heurist version

        // attempt to get release version
        $server_ver = USystem::getLastCodeAndDbVersion();

        if($server_ver == "unknown"){
            error_log("Unable to retrieve Heurist server version, this maybe due to the main server being un-available. If this problem persists please contact the Heurist team.");
            return;
        }

        $local_parts = explode('.', $local_ver);
        $server_parts = explode('.', $server_ver);

        for($i = 0; $i < count($server_parts); $i++){

            if($server_parts[$i] == $local_parts[$i]){
                continue;
            }

            if($server_parts[$i] > $local_parts[$i]){ // main release is newer than installed version, send email

                $title = "Heurist version " . htmlspecialchars($local_ver)
                . " at " . HEURIST_BASE_URL . " is behind Heurist home server";

                $msg = 'Heurist on the referenced server is running version '
                . " $local_ver which can be upgraded to the newer $server_ver<br><br>"
                . 'Please check for an update package at <a href="https://heuristnetwork.org/installation/">https://heuristnetwork.org/installation/</a><br><br>'
                . 'Update packages reflect the alpha version and install in parallel with existing versions'
                . ' so you may test them before full adoption. We recommend use of the alpha package'
                . ' by any confident user, as they bring bug-fixes, cosmetic improvements and new'
                . ' features. They are safe to use and we will respond repidly to any reported bugs.';

                //Update notification
                sendEmail(HEURIST_MAIL_TO_ADMIN, $title, $msg, true);
            }
            //else main release is less than installed version, maybe missed alpha or developemental version

            break;
        }//for
    }

    /**
     * Updates the list of target languages supported by DeepL API.
     * Fetches the list from DeepL and saves it to a JSON file (DEEPL_languages.json) in HEURIST_FILESTORE_ROOT.
     * Requires $accessToken_DeepLAPI to be globally defined.
     *
     * @global string|null $accessToken_DeepLAPI The DeepL API authentication key.
     * @return void
     */
    private static function updateDeeplLanguages(){

        global $accessToken_DeepLAPI;

        if(empty($accessToken_DeepLAPI)){
            return;
        }

        $target_url = 'https://api-free.deepl.com/v2/languages?type=target';

        $language_file = HEURIST_FILESTORE_ROOT . '_EXTERNAL_LOOKUP_DATA/DEEPL_languages.json';
        if(folderExists(HEURIST_FILESTORE_ROOT . '_EXTERNAL_LOOKUP_DATA', true) !== true){
            folderCreate2(HEURIST_FILESTORE_ROOT . '_EXTERNAL_LOOKUP_DATA', '');
            fileDelete(HEURIST_FILESTORE_ROOT . 'DEEPL_languages.json');
        }

        $target_res = loadRemoteURLContentWithRange($target_url, false, true, 60, array('Authorization: DeepL-Auth-Key ' . $accessToken_DeepLAPI));

        $target_languages = array();

        if(!empty($target_res)){

            $target_res = json_decode($target_res, true);
            $target_res = json_last_error() !== JSON_ERROR_NONE ? array() : $target_res;

            // Extra processing needed, some target languages have multiple versions; e.g. ENG-GB and ENG-US
            foreach ($target_res as $lang) {

                $lang_name = $lang['language'];
                if(strpos($lang_name, '-') !== false){
                    $lang_name = explode('-', $lang_name)[0];
                }

                if(array_search($lang_name, $target_languages) !== false){
                    continue;
                }

                array_push($target_languages, $lang_name);
            }
        }

        fileSave(json_encode($target_languages), $language_file);
    }

    /**
     * Clear temporary perpared parameters from DB scratch directories
     * @param \hserv\System $system Initialised Heurist system instance
     * @return void
     */
    private static function removePreparedParameters($system){

        if(!\defined('DIR_SCRATCH') || !\defined('HEURIST_FILESTORE_ROOT')){
            return;
        }

        $types = ['export', 'log'];
        $databases = mysql__getdatabases4($system->getMysqli(), false);
        $yesterday = strtotime('-1 day');

        foreach($databases as $database){

            $dbRoot = HEURIST_FILESTORE_ROOT.basename($database).'/';
            $dbScratch = $dbRoot . DIR_SCRATCH;

            if(!folderExists($dbScratch, false)){
                continue;
            }

            $files = scandir($dbScratch);

            foreach($files as $filename){

                $file = "{$dbScratch}{$filename}";
                if(empty($filename) || $filename === '.' || $filename === '..' || $filename === 'index' || is_dir($file)){
                    continue;
                }

                [$name, $ext] = explode('.', $filename);

                if($ext !== 'json'){
                    continue;
                }

                $nameParts = explode('_', $name);
                $date = filemtime($file);

                if(!\in_array($nameParts[0], $types) || !is_numeric($nameParts[1]) || $yesterday < $date){
                    continue;
                }

                fileDelete($file);
            }
        }

    }
    
    /**
     * $context if defined, it means it is called from initPage.php for standalone app (such as crosstabs)
     * @return string true if current user or database is a member of association
     */    
    public static function checkAssociationMembership($system, $context=null):string
    {
        
        $currentUser = $system->getCurrentUser();
        $server = HEURIST_DOMAIN;
        $database = $system->dbnameFull();
       
        // 1. Check the session first
        if(!($currentUser && @$currentUser['ugr_ID']>0)){
            return false;
        }
        
        return 'database';
        
        // false && 
        if(session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[$database]['isAssociationMember'])){
            
            if($context && 'nonmember'==$_SESSION[$database]['isAssociationMember']){
                checkMembershipLogNonmember($context, $currentUser['ugr_eMail'], $server, $database);    
            }
            
            return $_SESSION[$database]['isAssociationMember'];
        }
        
        $dbowner = user_getDbOwner($system->getMysqli());
        
        $membershipStatus = checkHeuristNetworkMembership($dbowner['ugr_eMail'], $currentUser['ugr_eMail'], $server, $database, $context??'');
        //$isMember = 'individual';
        if(session_status() === PHP_SESSION_ACTIVE){
            @session_start();
            $_SESSION[$database]['isAssociationMember'] = $membershipStatus;
        }
        
        return $membershipStatus;
        
    }
    
    public static function logAssociationMembership($system, $context): void
    {
      
        $currentUser = $system->getCurrentUser();
        $server = HEURIST_DOMAIN;
        $database = $system->dbnameFull();
       
        // 1. Check the session first
        if(!($currentUser && @$currentUser['ugr_ID']>0)){
            return;
        }
        
        checkMembershipLogNonmember($currentUser['ugr_eMail'], $server, $database, $context);      
        
    }

    /**
     * Gets the latest Heurist code version from the main server and compares it with the local version.
     * Caches the fetched server version for 24 hours in a file (`_latestVersionReceived.ini`) to reduce server requests.
     * Distinguishes between alpha and stable release channels.
     *
     * @return string The latest known code version from the main server (e.g., "4.1.0"), or "unknown" if fetching fails.
     */
    public static function getLastCodeAndDbVersion($getDatabaseVersion = false){

        $fullSegment = '';
        if (preg_match('~/(h(\d+)-alpha)(?:[/?#]|$)~', HEURIST_BASE_URL, $matches)) {

            $fullSegment = $matches[1];
            $versionNo   = $matches[2];
            
            $getAlpha = true;
            $key = 'alpha';
        } else {
            $getAlpha = false;
            $key = 'stable';
        }        
        
        //old way $getAlpha = preg_match("/h\d+\-alpha|alpha\//", HEURIST_BASE_URL) === 1 ? true : false;
        //old way $key = $getAlpha ? 'alpha' : 'stable';
        $dayAgo = time() - 24 * 60 * 60;

        $fileName = HEURIST_FILESTORE_ROOT."_latestVersionReceived.ini";

        $versionDetails = [];
        $lastVersion = 'unknown';
        $lastDatabase = 'unknown';

        if(file_exists($fileName)){
            $versionDetails = parse_ini_file($fileName, true);
            if(!empty($versionDetails) && array_key_exists($key, $versionDetails) && $versionDetails[$key]['timestamp'] > $dayAgo){
                return $getDatabaseVersion ? $versionDetails[$key]['database'] : $versionDetails[$key]['version'];
            }
        }

        // Prepare for new details
        $versionDetails[$key] = [
            'version' => $lastVersion,
            'database' => $lastDatabase,
            'timestamp' => time()
        ];

        if(strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL)) === 0){
            //the same version
            $mysql_indexdb = mysql__init(HEURIST_INDEX_DATABASE);
            $db_version = getDbVersion($mysql_indexdb);
            if($db_version){
                $rawdata = HEURIST_VERSION."|{$db_version}";    
            }

        }else{
            $url = ($getAlpha ? (HEURIST_MAIN_SERVER . '/'.$fullSegment.'/') : HEURIST_INDEX_BASE_URL) . "admin/setup/dbproperties/getCurrentVersion.php?db=".HEURIST_INDEX_DATABASE."&check=1";
            $rawdata = loadRemoteURLContentSpecial($url); // it returns HEURIST_VERSION|HEURIST_DBVERSION
        }

        if($rawdata){

            [$lastVersion, $lastDatabase] = explode('|', $rawdata);

            if(!empty($lastVersion)){

                $latestVer = explode('.', $lastVersion);
                if(count($latestVer) < 2 || !isPositiveInt($latestVer[0]) || !is_numeric($latestVer[1])){
                    $lastVersion = 'unknown';
                }
            }
        }

        // Add new version numbers
        $versionDetails[$key]['version'] = $lastVersion;
        $versionDetails[$key]['database'] = $lastDatabase;

        // Re-add comment
        $versionDetails['@comment'] = <<<DOC
        ; The purpose of this file is to the retain the latest Heurist and database versions retrieved from the main server (HeuristRef.net)
        ; These details are refreshed once per day and used per instance of Heurist
        ; 'unknown' is used as a replacement when the main server cannot be contacted or provides an invalid response
        ; 'version' refers to the Heurist version (e.g. 6.5.0 or 7.0.0)
        ; 'database' refers to the Heurist database version, updated only when necessary
        ; 'timestamp' the unix timestamp of when the details were retrieved

        DOC;

        fileDelete($fileName);
        ksort($versionDetails);
        saveIniFile($fileName, $versionDetails, true);
        //fileDelete(HEURIST_FILESTORE_ROOT."lastAdviceSent.ini"); // remove original file

        return $getDatabaseVersion ? $versionDetails[$key]['database'] : $versionDetails[$key]['version'];
    }

    /**
     * Check for a specific version Heurist on the current server
     *
     * @param bool $checkForAlpha Whether to only check for an alpha version
     * @param int|string $version The specific version looking for
     * @return string URL to version, or empty if not found/available
     */
    public static function checkForVersion($checkForAlpha = false, $version = null){

        $response = '';

        // Check for a specific version
        $alphaVersion = is_numeric($version) ? intval($version) : -1;
        $specificVersion = is_string($version) ? preg_match('/h\d+\.\d{1,2}\.\d{1,2}/', $version) : -1;
        if($alphaVersion > 0 || $specificVersion !== -1){

            $version = $alphaVersion !== -1 ? "h{$version}-alpha" : $specificVersion;
            if(preg_match("/{$version}/", HEURIST_BASE_URL) === 1){
                return '';
            }

            $url = HEURIST_SERVER_URL . "/{$version}/";
            $httpResponse = get_headers($url)[0];
            $response = preg_match('/4\d{2}|5\d{2}/', $httpResponse) === 0 ? $url : $httpResponse;

            $checkForAlpha = false;
        }
        
        // Check for any available alpha
        $isAlpha = preg_match("/h\d+-alpha|\/alpha\//", HEURIST_BASE_URL) === 1 ? true : false;

        if(!defined('HEURIST_FILESTORE_ROOT') || !$checkForAlpha || $isAlpha){
            return $response;
        }

        $fname = HEURIST_FILESTORE_ROOT."_latestVersionReceived.ini";
        $versionNumbers = [];
        array_push($versionNumbers, explode('.', HEURIST_VERSION)[0]);// Check using current major version

        if(file_exists($fname)){

            $latestVersions = parse_ini_file($fname, true);
            $latestVersion = array_key_exists('alpha', $latestVersions) && strpos($latestVersions['alpha']['version'], '.') > 0
                ? explode('.', $latestVersions['alpha']['version'])[0] : 0;

            if($versionNumbers[0] < $latestVersion){
                array_unshift($versionNumbers, intval($latestVersion));
            }
        }

        foreach($versionNumbers as $number){

            $url = HEURIST_SERVER_URL . "/h{$number}-alpha/";
            $httpResponse = get_headers($url)[0];
            if(preg_match('/4\d{2}|5\d{2}/', $httpResponse) === 0){ // valid
                $response = $url;
                break;
            }
        }

        if(empty($response)){ // Finally, check last supported version
            $url = HEURIST_SERVER_URL . '/alpha/';
            $httpResponse = get_headers($url)[0];
            if(preg_match('/4\d{2}|5\d{2}/', $httpResponse) === 0){ // valid
                $response = $url;
            }
        }

        return $response;
    }

    /**
     * Store parameters to be used in an upcoming server call, this is done to avoid excessively long URLs that lead to 414 errors
     *
     * @param \hserv\System $system Initialised Heurist system instance
     * @param string $type Process type, e.g. 'export', 'import', etc...
     * @param int $replace How to handling replacements: 0 - Complete replace, 1 - Merge + maintain existing, 2 - Merge + replace existing
     * @param array $parameters Parameters to be saved, ignores 'preparedID' and 'DBGSESSID' keys
     * @return int|false prepared session ID, or no parameters prepared
     */
    public static function prepareParameters(\hserv\System $system, string $type, int $replace, array $parameters){

        if(empty($parameters)){
            return false;
        }

        $id = !is_numeric(@$parameters['preparedID']) || \intval($parameters['preparedID']) <= 0 ? time() : \intval($parameters['preparedID']);

        $replace = !is_numeric(@$replace) ? 0 : \intval($replace);
        $replace = $replace > 2 || $replace < 0 ? 0 : $replace;

        if(!defined('HEURIST_PREPARED_PARAMS_DIR')){
            $system->getSysDir('prepared-parameters');
        }

        $paramsFile = HEURIST_SCRATCH_DIR . "{$type}_{$id}.json";
        if($system->hasAccess()){

            if($type === 'export-feed'){

                if(folderCreate2(HEURIST_PREPARED_PARAMS_DIR . 'export-feed/', '', false) !== ''){
                    $system->addError(HEURIST_ERROR, 'Failed to setup the export-feed directory');
                    return false;
                }

                $format = $parameters['format'];

                $paramsFile = HEURIST_PREPARED_PARAMS_DIR . "export-feed/{$format}_{$id}.json";
            }
        }

        $storedParameters = [];
        if(file_exists($paramsFile)){
            $storedParameters = file_get_contents($paramsFile);

            $storedParameters = json_decode($storedParameters, true);
            $storedParameters = json_last_error() !== JSON_ERROR_NONE ? [] : $storedParameters;
        }

        $skipKeys = ['preparedID', 'DBGSESSID', 'preparedType'];
        foreach($parameters as $key => $value){

            if(in_array($key, $skipKeys) || $key === 'a' && $value === 'prepare_params'){
                continue;
            }elseif($replace !== 0 && \array_key_exists($key, $storedParameters) && $key !== 'db'){

                $isBothArrays = \is_array($storedParameters[$key]) && \is_array($value);
                $isBothStrings = \is_string($storedParameters[$key]) && \is_string($value);

                if($isBothArrays){
                    $storedParameters[$key] = $replace === 1 ? array_merge($storedParameters[$key], $value) : array_merge($value, $storedParameters[$key]);
                }elseif($isBothStrings){
                    $storedParameters[$key] .= $value;
                }

                if($isBothArrays || $isBothStrings || $replace === 1){
                    continue;
                }
            }

            $storedParameters[$key] = $value;
        }

        file_put_contents($paramsFile, json_encode($storedParameters));

        return $id;
    }

    /**
     * Retrieve previously saved parameters, this will not replace existing keys
     *
     * @param \hserv\System $system Initialised Heurist system instance
     * @param string $type Process type, e.g. 'export' or 'import'
     * @param array $parameters Parameters array to be updated with stored parameters
     * @return bool
     */
    public static function getPreparedParameters(\hserv\System $system, string $type, array &$parameters) : bool{

        if(!is_numeric(@$parameters['preparedID'])){
            $system->addError(HEURIST_INVALID_REQUEST, 'Wrong parameter preparedID. Must be integer');
            return false;
        }

        $id = \intval($parameters['preparedID']);
        $deleteFile = true;

        if(!\defined('HEURIST_PREPARED_PARAMS_DIR')){
            $system->getSysDir('prepared-parameters');
        }

        $paramsFile = HEURIST_SCRATCH_DIR . "{$type}_{$id}.json";
        if(!file_exists($paramsFile)){

            if($type === 'export-feed'){

                if(folderExists(HEURIST_PREPARED_PARAMS_DIR . 'export-feed/', false) !== 1){
                    $paramsFile = '';
                }else{

                    $format = $parameters['format'];

                    $paramsFile = HEURIST_PREPARED_PARAMS_DIR . "export-feed/{$format}_{$id}.json";
                    $deleteFile = false;
                }

            }
        }

        if(!file_exists($paramsFile)){
            $system->addError(HEURIST_INVALID_REQUEST, 'Pre-prepared parameters file could not be found.');
            return false;
        }

        $storedParameters = file_get_contents($paramsFile);

        $storedParameters = json_decode($storedParameters, true);
        $storedParameters = json_last_error() !== JSON_ERROR_NONE ? [] : $storedParameters;

        foreach($storedParameters as $key => $value){
            if(\array_key_exists($key, $parameters)){
                continue;
            }
            $parameters[$key] = $value;
        }

        if($deleteFile){
            fileDelete($paramsFile);
        }

        return true;
    }

    /**
     * Calculates the difference between two `getrusage` arrays for a specific index (e.g., 'utime' for user time).
     * Primarily used for benchmarking or profiling code execution time.
     *
     * @param array $ru The resource usage array at the end of the measured period (from `getrusage()`).
     * @param array $rus The resource usage array at the start of the measured period (from `getrusage()`).
     * @param string $index Optional. The specific usage index to calculate (e.g., 'utime', 'stime'). Defaults to 'utime'.
     *                      The 'ru_' prefix and '.tv_sec' / '.tv_usec' suffixes are added internally.
     * @return float The time difference in milliseconds.
     */
    public static function rutime($ru, $rus, $index='utime'){
        return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
        -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));        
    }
    
    /**
     * Inserts the Matomo (formerly Piwik) analytics tracking JavaScript code.
     * Requires $matomoUrl and $matomoSiteId to be globally defined.
     * Can set custom dimensions based on $pageType.
     *
     * @global string|null $matomoUrl The base URL of the Matomo server.
     * @global string|int|null $matomoSiteId The Site ID for Matomo tracking.
     * @param string|null $pageType Optional. Specifies the type of page for custom dimensions (e.g., 'startup').
     * @return void This function outputs JavaScript directly.
     */
    public static function insertLogScript($pageType=null){
        global $matomoUrl, $matomoSiteId;
        
        if(!(isset($matomoSiteId) && isset($matomoUrl))){
            return;
        }
?>        
<!-- Matomo -->
<script>
(function () {
  function inIFrame() {
    try { return window.self !== window.top; }
    catch (e) { return true; }
  }

  if (inIFrame()) {
    // Block Matomo when embedded
    return;
  }
  
  var _paq = window._paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */                      

  <?php if($pageType=='startup'){?>
       //per page  
      _paq.push(['setCustomDimension', 1, '' ]);  
      _paq.push(['setCustomDimension', 2, 'startup' ]);
      _paq.push(['setCustomDimension', 3, 'eng' ]);  
      _paq.push(['setCustomDimension', 4, '' ]);  
      //per visit
      _paq.push(['resetUserId']);
      _paq.push(['setCustomDimension', 5, 'visitor' ]);
  
      _paq.push(['setCustomUrl', '/startup' ]);
  <?php } ?>
  
  _paq.push(['trackPageView']);
//  _paq.push(['enableLinkTracking']);  
  
  
  (function() {
    var u="//<?php echo $matomoUrl;?>/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '<?php echo $matomoSiteId;?>']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();

})();  
</script>
<!-- End Matomo Code -->
<?php        
    }
    
   
    /**
     * Redirect legacy domains to heurist.huma-num.fr for the MPCE database.
     *
     * Requirement:
     *  - Before connecting to database "MPCE_Mapping_Print_Charting_Enlightenment", if the request is not
     *    already on heurist.huma-num.fr then redirect the current request to that domain (preserving URI).
     *  - If headers are already sent and redirection is not possible, return the fatal error message so that
     *    the caller can add it as a system fatal error.
     *
     * @param string|null $db Requested database name (short or full).
     * @return array{redirected:bool, fatal_message:?string}
     */
    public static function enforceMPCEHumaNumDomain(?string $db): array
    {
        $target_domain = 'heurist.huma-num.fr';
        $mpce_db = 'MPCE_Mapping_Print_Charting_Enlightenment';

        // Only apply to web requests
        if (php_sapi_name() == 'cli') {
            return ['redirected' => false, 'fatal_message' => null];
        }

        $db = $db ?? '';
        // Match both short and full dbname variants.
        if (stripos($db, $mpce_db) === false) {
            return ['redirected' => false, 'fatal_message' => null];
        }

        $current_domain = strtolower((string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? ''));
        $current_domain = preg_replace('/:\\d+$/', '', $current_domain); // strip port

        // Domains explicitly mentioned for redirection (kept for clarity/logical grouping)
        $checkDomains = [
            'heuristref.net',
            'intersect.org.au',
            'heuristau.net',
            'heurist.eu',
            'heuristeu.net'
        ];
        //unset($checkDomains); remove remark so redirection is enforced for all non-target domains

        if ($current_domain === '' || $current_domain === $target_domain || 
            (isset($checkDomains) && !in_array($current_domain, $checkDomains, true))) {
            return ['redirected' => false, 'fatal_message' => null];
        }

        // Build redirect URL (preserve path + query)
        /* always https
        $isSecure = false;
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $isSecure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $isSecure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            $isSecure = true;
        }
        $scheme = $isSecure ? 'https' : 'http';
        */
        
        $request_uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if ($request_uri === '') {
            $request_uri = '/';
        }
        $redirect_url = 'https://' . $target_domain . $request_uri;

        if (!headers_sent()) {
            // Permanent redirect: the DB has moved.
            header('Location: ' . $redirect_url, true, 301);
            exit;
        }

        $fatal = "Mapping Print Charting Enlightenment has been moved to the Heurist server on Huma-Num for improved performance in Europe and long term maintenance, as part of a collaboration with European researchers. It can now be accessed at: Heurist.Huma-Num.fr/MPCE_Mapping_Print_Charting_Enlightenment";
        return ['redirected' => false, 'fatal_message' => $fatal];
    }   
    
}

