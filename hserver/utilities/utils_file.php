<?php

    /**
    *  File/folder utilities
    *
    * folderCreate
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2016 University of Sydney
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


    // 1 - OK
    // -1  not exists
    // -2  not writable
    // -3  file with the same name cannot be deleted
    function folderExists($folder, $testWrite){

        if(file_exists($folder)){

            if(is_dir($folder)){

                if ($testWrite && !is_writable($folder)) {
                    //echo ("<h3>Warning:</h3> Folder $folder already exists and it is not writeable. Check permissions! ($msg)<br>");
                    return -2;
                }
            }else{
                if(!unlink($folder)){
                    //echo ("<h3>Warning:</h3> Unable to remove file $folder. We need to create a folder with this name ($msg)<br>");
                    return -3;
                }
                return -1;
            }

            return 1;

        }else{
            return -1;
        }

    }


    /**
    *
    *
    * @param mixed $folder
    * @param mixed $testWrite
    * @return mixed
    */
    function folderCreate($folder, $testWrite){

        $res = folderExists($folder, $testWrite);

        if($res == -1){
            if (!mkdir($folder, 0777, true)) {
                //echo ("<h3>Warning:</h3> Unable to create folder $folder ($msg)<br>");
                return false;
            }
        }

        return true;
    }
   
    
    //
    //
    //
    function fileCopy($s1, $s2) {
        $path = pathinfo($s2);
        
        if(folderCreate($path['dirname'], true)){
            if (!copy($s1,$s2)) {
                // "copy failed";
                return false;
            }
        }else{
           //can't crate folder or it is not writeable 
           return false;
        }
        return true;
    }    
    //
    //
    //
    function fileSave($rawdata, $filename)
    {
        if($rawdata){
            if(file_exists($filename)){
                unlink($filename);
            }
            $fp = fopen($filename,'x');
            fwrite($fp, $rawdata);
            fclose($fp);

            return filesize($filename);
        }else{
            return 0;
        }
    }

    
//-----------------------
//
// if the same server - try to include script instead of full request
//
function loadRemoteURLContentSpecial($url){

    if(strpos($url, HEURIST_SERVER_URL)===0){
        
        //replace http://heurist.sydney.edu.au/h4/ to script path in current installation folder
        $path = str_replace(HEURIST_BASE_URL, HEURIST_DIR, $url);

        $path = substr($path,0,strpos($path,'?'));

        $parsed = parse_url($url);
        parse_str($parsed['query'], $_REQUEST);

        $out = getScriptOutput($path);
        
        return $out;
    }else{
        return loadRemoteURLContentWithRange($url, null, true);
    }
}

//
//
//
function loadRemoteURLContent($url, $bypassProxy = true) {
    return loadRemoteURLContentWithRange($url, null, $bypassProxy);
}

//
//
//
function loadRemoteURLContentWithRange($url, $range, $bypassProxy = true, $timeout=30) {

    if(!function_exists("curl_init"))  {
        return false;
    }

    /*
    if(false && strpos($url, HEURIST_SERVER_URL)===0){
        return loadRemoteURLviaSocket($url);
    }
    */
    
    $useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.6) Gecko/2009011913 Firefox/3.0.6';
                 //'Firefox (WindowsXP) - Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    //return the output as a string from curl_exec
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);    //don't include header in output
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);    // follow server header redirects
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    // don't verify peer cert
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);    // timeout after ten seconds
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);    // no more than 5 redirections

    curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    //curl_setopt($ch, CURLOPT_REFERER, HEURIST_SERVER_URL);    
    
    if($range){
        curl_setopt($ch, CURLOPT_RANGE, $range);
    }

    if ( (!$bypassProxy) && defined("HEURIST_HTTP_PROXY") ) {
        curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if(  defined('HEURIST_HTTP_PROXY_AUTH') ) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    $data = curl_exec($ch);

    $error = curl_error($ch);

    if ($error) {
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
//error_log('code = '.$code.'  '.$error);
        curl_close($ch);
        return false;
    } else {
        curl_close($ch);
        if(!$data){
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
//error_log('code2 = '.$code);
        }
        return $data;
    }
}
    
//
//
//
function getScriptOutput($path, $print = FALSE)
{
    ob_start();

    if( is_readable($path) && $path )
    {
        include $path;
    }
    else
    {
        return FALSE;
    }

    if( $print == FALSE )
        return ob_get_clean();
    else
        echo ob_get_clean();
}
    
?>
