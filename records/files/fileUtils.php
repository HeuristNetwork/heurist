<?php

/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* utility functions for dealing with files
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Files/Util
*/


function getInstallationDirectory2($scriptName){
    
    // a pipe delimited list of the top level directories in the heurist code base root. Only change if new ones are added.
    $topDirs = "admin|applications|common|context_help|export|hapi|hclient|hserver|import|records|redirects|search|viewers|help|ext|external";

    // calculate the dir where the Heurist code is installed, for example /h4 or /h4-ij
    $installDir = preg_replace("/\/(" . $topDirs . ")\/.*/", "", $scriptName); // remove "/top level dir" and everything that follows it.
    
    if ($installDir == $scriptName) { // no top directories in this URI must be a root level script file or blank
        $installDir = preg_replace("/\/[^\/]*$/", "", $scriptName); // strip away everything past the last slash "/index.php" if it's there
    }

    //the subdir of the server's document directory where heurist is installed
    if ($installDir == $scriptName || $installDir == '') { 
        // this should be the path difference between document root and heurist code root
        $installDir = '/';
    }else{
        $installDir = $installDir.'/';
    }
    
    return $installDir;
}

//
// if the same server - try to include script instead of full request
//
function loadRemoteURLContentSpecial($url){

    if(strpos($url, HEURIST_SERVER_URL)===0){
        
        
        $parsed = parse_url($url);
        $installDir = getInstallationDirectory2($parsed['path']);
        
        //replace http://heurist.sydney.edu.au/h4/ to script path in current installation folder
        $path = str_replace(HEURIST_SERVER_URL.$installDir, HEURIST_DIR, $url);

        //$documentRoot = @$_SERVER["DOCUMENT_ROOT"];
        //if( $documentRoot && substr($documentRoot, -1, 1) != '/' ) $documentRoot = $documentRoot.'/';
        //$path = str_replace(HEURIST_SERVER_URL, $documentRoot, $url);
        
        $path = substr($path,0,strpos($path,'?'));

        parse_str($parsed['query'], $_REQUEST);

        $out = getScriptOutput($path);
        
        return $out;
        
        //return loadRemoteURLviaSocket($url);
        //$url= str_replace(HEURIST_SERVER_URL,'localhost',$url);
    }else{
        return loadRemoteURLContentWithRange($url, null, true);
    }
}

function loadRemoteURLContent($url, $bypassProxy = true) {
    return loadRemoteURLContentWithRange($url, null, $bypassProxy);
}

function loadRemoteURLContentWithRange($url, $range, $bypassProxy = true, $timeout=30) {

    if(!function_exists("curl_init"))  {
        return false;
    }

    if(false && strpos($url, HEURIST_SERVER_URL)===0){
        return loadRemoteURLviaSocket($url);
    }
    
    $useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.6) Gecko/2009011913 Firefox/3.0.6';
                 //'Firefox (WindowsXP) - Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	//return the output as a string from curl_exec
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);	//don't include header in output
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);	// timeout after ten seconds
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections

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


// TODO: rationalise: saveURLasFile.php in same dir seems to be in the same territory
/**
* save remote url as file and returns the size of saved file
*
* @param mixed $url
* @param mixed $filename
*/
function saveURLasFile($url, $filename)
{ //Download file from remote server
    $rawdata = loadRemoteURLContent($url);
    return saveAsFile($rawdata, $filename);
}

function saveAsFile($rawdata, $filename)
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



// only used by annotatedTemplateProxy.php and downloadFile.php
/**
*
*
* @param mixed $filedata
*/
function downloadViaProxy($filename, $mimeType, $url, $bypassProxy = true){

    if(!file_exists($filename)){ // || filemtime($filename)<time()-(86400*30))

        $rawdata = loadRemoteURLContent($url, $bypassProxy);

        saveAsFile($rawdata, $filename);
        /*
        if ($raw) {

        if(file_exists($filename)){
        unlink($filename);
        }
        $fp = fopen($filename, "w");
        //$fp = fopen($filename, "x");
        fwrite($fp, $raw);
        //fflush($fp);    // need to insert this line for proper output when tile is first requestet
        fclose($fp);
        }
        */
    }

    if(file_exists($filename)){
        downloadFile($mimeType, $filename);
    }
}


// TODO: REMOVE - function is duplicated in newer code in file_download.php
/**
* direct file download
*
* @param mixed $mimeType
* @param mixed $filename
*/
function downloadFile($mimeType, $filename){

    if (file_exists($filename)) {

        header('Content-Description: File Transfer');
        if ($mimeType) {
            header('Content-type: ' .$mimeType);
        }else{
            header('Content-type: binary/download');
        }
        if($mimeType!="video/mp4"){
            header('access-control-allow-origin: *');
            header('access-control-allow-credentials: true');
        }
        //header('Content-Type: application/octet-stream');
        //force fownload header('Content-Disposition: attachment; filename='.basename($filename));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        @ob_clean();
        flush();

        readfile($filename);

    }

}



// TODO: This is not used - only referenced, but commented out, in exportMyDataPopup.php
/**
*
*
* @param mixed $dir
* @param mixed $zipfile
*/
function zipDirectory($dir, $zipfile){

    // Adding files to a .zip file, no zip file exists it creates a new ZIP file

    // increase script timeout value
    ini_set('max_execution_time', 5000);

    // create object
    $zip = new ZipArchive();

    // open archive
    if ($zip->open($zipfile, ZIPARCHIVE::CREATE) !== TRUE) {
        return false;
    }

    // initialize an iterator
    // pass it the directory to be processed
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    // iterate over the directory
    // add each file found to the archive
    foreach ($iterator as $key=>$value) {
        if(! $zip->addFile(realpath($key), $key)){
            return false;
            //or die ("ERROR: Could not add file: $key");
        }
    }

    // close and save archive
    $zip->close();
    return true;

}



//
// remove folder and all its content
//
function delFolderTree($dir, $rmdir) {

    $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    if($rmdir){
        $res =rmdir($dir);
        return $res;
    }else{
        return true;
    }
    
//works for empty and flat folders only    array_map('unlink', glob($dir."/*"));
}

function loadRemoteURLviaSocket($url) {
    
    $hostname = '127.0.0.1'; //HEURIST_DOMAIN;
    $url = str_replace('http://'.HEURIST_DOMAIN, 'http://'.$hostname, $url);

    $headers[] = "GET ".$url." HTTP/1.1";
    $headers[] = "Host: ".$hostname;
    $headers[] = "Accept-language: en";

    $cookies = array();
    foreach ($_COOKIE as $id=>$val){
        array_push($cookies, $id.'='.$val );
    }
    $headers[] = "Cookie: ".implode($cookies,';').';';
    $headers[] = "";

    $CRLF = "\r\n";
    $remote = fsockopen($hostname, 80, $errno, $errstr, 5);
    if($remote===false){
       $result = '[]';//array();
    }else{
        // a pinch of error handling here
        fwrite($remote, implode($CRLF, $headers).$CRLF);
        $response = '';
        while ( ! feof($remote))
        {
            // Get 100K from buffer
            $response .= fread($remote, 102400);
        }
        fclose($remote);

//error_log("RESPONSE ".$response);        

        // split the headers and the body
        $response2 = preg_split("|(?:\r?\n){2}|m", $response, 2);
        $response2 = (isset($response2[1]))?$response2[1]:"";

        if(strpos($response2,$CRLF)>0){
            $response2 = explode($CRLF, $response2);
            $response2 = $response2[1];
        }

        $result = $response2; //json_decode($response2, true);
    }
    
    return $result;
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
  
  
function _get_config_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        //_fix_integer_overflow
        if ($val < 0) {
            $val += 2.0 * (PHP_INT_MAX + 1);
        }
        return $val;
}  
?>