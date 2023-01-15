<?php

    /**
    * 
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    
// returns 
// domain
// server_url
// install dir
function getHostParams()
{
    global $serverName;
    
    $host_params = array();
    
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

    $rewrite_actions = 'web|hml|tpl|view'; //actions for redirection https://hist/heurist/[dbname]/web/
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

    if ($installDir == @$_SERVER["SCRIPT_NAME"]) { // this should be the path difference between document root and heurist code root
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
        //DEBUG - pro is the same as dev $installDir_pro = $installDir; 
    }

    $host_params['server_url'] = ($isSecure ? 'https' : 'http') . "://" . $serverName;
    $host_params['install_dir'] = $installDir;
    $host_params['install_dir_pro'] = $installDir_pro;

    return $host_params;

}//getHostParams
    
?>
