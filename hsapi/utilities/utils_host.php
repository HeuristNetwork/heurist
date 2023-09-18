<?php

    /**
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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
    
// returns 
// domain
// server_url
// install dir
function getHostParams( $argv=null )
{
    global $serverName;
    
    $host_params = array();
    
    $installDir = '';
    $codeFolders = array('heurist','h6-alpha','h6-ao'); //need to cli and short url

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
      
//echo "Install dir      $installDir \n";
//echo "3>>> ".$host_params['heurist_dir']."\n";

    }else{
    
        // server name or IP address of your Web server, null will pull SERVER_NAME from the request header
        if (!@$serverName) {
            if(@$_SERVER["SERVER_NAME"]){
                $serverName = $_SERVER["SERVER_NAME"] . 
                ((is_numeric(@$_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") 
                            ? ":" . $_SERVER["SERVER_PORT"] : "");
                $host_params['domain'] = $_SERVER["SERVER_NAME"];
            }else{
                $host_params['domain'] = '127.0.0.1';
            }
        }else{
            $k = strpos($serverName,":");
            $host_params['domain'] = ($k>0)?substr($serverName,0,$k-1):$serverName;
        }

        $isSecure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $isSecure = true;
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $isSecure = true;
        }

        $rewrite_actions = 'website|web|hml|tpl|view|edit|adm'; //actions for redirection https://hist/heurist/[dbname]/web/
        
        if(@$_SERVER["SCRIPT_NAME"] && substr($_SERVER["SCRIPT_NAME"], -4 ) === '/web'){
            $_SERVER["SCRIPT_NAME"] .= '/';
        }
        
        $matches = array();
        preg_match("/\/([A-Za-z0-9_]+)\/(" . $rewrite_actions . ")\/.*/", @$_SERVER["SCRIPT_NAME"], $matches);
        if($matches){
            $installDir = preg_replace("/\/([A-Za-z0-9_]+)\/(" . $rewrite_actions . ")\/.*/", "", @$_SERVER["SCRIPT_NAME"]);
        }else{
        
            // calculate the dir where the Heurist code is installed, for example /h5 or /h5-ij
            $topdirs = 'admin|api|applications|common|context_help|export|hapi|hclient|hsapi|import|startup|records|redirects|search|viewers|help|ext|external'; // Upddate in 3 places if changed
            
            $installDir = preg_replace("/\/(" . $topdirs . ")\/.*/", "", @$_SERVER["SCRIPT_NAME"]); // remove "/top level dir" and everything that follows it.
            if ($installDir == @$_SERVER["SCRIPT_NAME"]) { // no top directories in this URI must be a root level script file or blank
                $installDir = preg_replace("/\/[^\/]*$/", "", @$_SERVER["SCRIPT_NAME"]); // strip away everything past the last slash "/index.php" if it's there
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
    
    
    $host_params['server_url'] = ($isSecure ? 'https' : 'http') . "://" . $serverName;
    $host_params['install_dir'] = $installDir;
    $host_params['install_dir_pro'] = $installDir_pro;

    return $host_params;

}//getHostParams
    
?>
