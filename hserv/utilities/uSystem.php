<?php
/**
* Library to obtain system and php config value
*
* getHostParams
* isMemoryAllowed
* getConfigBytes
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

class USystem {

    /**
    * Return host doain, server url and installion folder
    *
    * @param mixed
    *
    * @return array (domain,server_url,install_dir)
    */
    public static function getHostParams( $argv=null )
    {
        global $serverName;

        $host_params = array();

        $installDir = '';
        $codeFolders = array('heurist','h6-alpha','h6-ao');//need to cli and short url

        if (php_sapi_name() == 'cli'){

            if(!isset($serverName) || !$serverName){
                $serverName = '127.0.0.1';
            }

            $k = strpos($serverName,":");
            $host_params['domain'] = ($k>0)?substr($serverName,0,$k-1):$serverName;
            $isSecure = true;

            if($argv==null || !is_array($argv)){
                $sDir = getcwd();
            }else{
                $sDir = dirname(realpath($argv[0]));
            }


            $sDir = str_replace('\\','/',$sDir);

            $iDir = explode('/', $sDir);
            $cntDir = count($iDir)-1;
            $path = null;
            for ($i=$cntDir; $i>=0; $i--){
                if(in_array($iDir[$i], $codeFolders)) {
                    $installDir = '/'.$iDir[$i].'/';
                    $path = array_slice($iDir, 0, $i);
                    break;
                }
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
                    $host_params['server_name'] = '127.0.0.1';
                    $host_params['domain'] = '127.0.0.1';
                }
            }else{
                $k = strpos($serverName,":");
                $host_params['domain'] = ($k>0)?substr($serverName,0,$k-1):$serverName;
                $host_params['server_name'] = $serverName;
            }

            $isSecure = false;
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
                $isSecure = true;
            }
            elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
                $isSecure = true;
            }

            $installDir = '/heurist';
            $is_own_domain = (strpos($_SERVER["SERVER_NAME"],'.huma-num.fr')>0 && $_SERVER["SERVER_NAME"]!='heurist.huma-num.fr');
            if(!$is_own_domain){

            $rewrite_actions = 'website|web|hml|tpl|view|edit|adm';//actions for redirection https://hist/heurist/[dbname]/web/

            if(@$_SERVER["SCRIPT_NAME"] &&
                (substr($_SERVER["SCRIPT_NAME"], -4 ) === '/web' || substr($_SERVER["SCRIPT_NAME"], -8 ) === '/website')){
                $_SERVER["SCRIPT_NAME"] .= '/';//add last slash
            }

            $matches = array();
            preg_match("/\/([A-Za-z0-9_]+)\/(" . $rewrite_actions . ")\/.*/", @$_SERVER["SCRIPT_NAME"], $matches);
            if($matches){
                $installDir = preg_replace("/\/([A-Za-z0-9_]+)\/(" . $rewrite_actions . ")\/.*/", "", @$_SERVER["SCRIPT_NAME"]);
            }else{

                // calculate the dir where the Heurist code is installed, for example /h5 or /h5-ij
                $topdirs = 'admin|api|applications|common|context_help|export|hapi|hclient|hserv|import|startup|records|redirects|search|viewers|help|ext|external';// Upddate in 3 places if changed

                $installDir = preg_replace("/\/(" . $topdirs . ")\/.*/", "", @$_SERVER["SCRIPT_NAME"]);// remove "/top level dir" and everything that follows it.
                if ($installDir == @$_SERVER["SCRIPT_NAME"]) { // no top directories in this URI must be a root level script file or blank
                    $installDir = preg_replace("/\/[^\/]*$/", "", @$_SERVER["SCRIPT_NAME"]);// strip away everything past the last slash "/index.php" if it's there
                }

            }

            }

            // this should be the path difference between document root $_SERVER["DOCUMENT_ROOT"] and heurist code root
            if ($installDir == @$_SERVER["SCRIPT_NAME"]) {
                $installDir = '/';
                $installDir_pro = '/';
            }else{
                $installDir = $installDir.'/';

                $iDir = explode('/',$installDir);
                $cntDir = count($iDir)-1;
                for ($i=$cntDir; $i>=0; $i--){
                    if($iDir[$i]!='') {
                        $iDir[$i] = 'heurist';
                        break;
                    }
                }
                $installDir_pro = implode('/', $iDir);
            }

            //validate
            if(@$_SERVER["DOCUMENT_ROOT"]){
                $i = 0;
                while ($i<=count($codeFolders)) {
                    $test_file = @$_SERVER["DOCUMENT_ROOT"].$installDir.'configIni.php';
                    if(file_exists($test_file)){
                        if($installDir_pro!=$installDir){
                            $test_file = @$_SERVER["DOCUMENT_ROOT"].$installDir_pro.'configIni.php';
                            if(!file_exists($test_file)){
                                $installDir_pro = $installDir;
                            }
                        }
                        break;
                    }
                    if($i==count($codeFolders)){
                        exit('Sorry, it is not possible to detect heurist installation folder. '
                            .'Please ask system administrator to verify server configuration.');
                    }
                    $installDir = '/'.$codeFolders[$i].'/';
                    $i++;
                }
            }


        }

        $host_params['server_url'] = ($isSecure ? 'https' : 'http') . "://" . $host_params['server_name'];
        $host_params['install_dir'] = $installDir;
        $host_params['install_dir_pro'] = $installDir_pro;

        return $host_params;

    }


    /**
    * Returns true if specified bytes can be loaded into memory
    *
    * @param mixed $memoryNeeded
    */
    public static function isMemoryAllowed( $memoryNeeded ){

        $mem_limit = USystem::getConfigBytes('memory_limit');
        $mem_usage = memory_get_usage();

        if ($mem_usage+$memoryNeeded > $mem_limit - 10485760){
            return 'It requires '.((int)($memoryNeeded/1024/1024)).
            ' Mb.  Available '.((int)($mem_limit/1024/1024)).' Mb';
        }else{
            return true;
        }
    }

    /**
    * Return amount of bytes for given php config variable
    *
    * @param mixed $php_var
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
                $val *= 1024;
            default;
        }
        //_fix_integer_overflow
        if ($val < 0) {
            $val += 2.0 * (PHP_INT_MAX + 1);
        }
        return $val;
    }

    /**
     * Return array of processed user agent details
     *
     * @return array [os, browser]
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
}
?>
