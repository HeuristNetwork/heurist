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
   
    /**
    * put your comment there...
    * 
    * @param mixed $dir
    */
    function folderDelete($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") {
                        deleteFolder($dir."/".$object); //delte files
                    } else {
                        unlink($dir."/".$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir); //delete folder itself
        }
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

 
    /**
     * Returns the target path as relative reference from the base path.
     *
     * Only the URIs path component (no schema, host etc.) is relevant and must be given, starting with a slash.
     * Both paths must be absolute and not contain relative parts.
     * Relative URLs from one resource to another are useful when generating self-contained downloadable document archives.
     * Furthermore, they can be used to reduce the link size in documents.
     *
     * Example target paths, given a base path of "/a/b/c/d":
     * - "/a/b/c/d"     -> ""
     * - "/a/b/c/"      -> "./"
     * - "/a/b/"        -> "../"
     * - "/a/b/c/other" -> "other"
     * - "/a/x/y"       -> "../../x/y"
     *
     * @param string $basePath   The base path
     * @param string $targetPath The target path
     *
     * @return string The relative target path
     */
    function getRelativePath($basePath, $targetPath)
    {
        
        $targetPath = str_replace("\0", '', $targetPath);
        $targetPath = str_replace('\\', '/', $targetPath);
        
        if( substr($targetPath, -1, 1) != '/' )  $targetPath = $targetPath.'/';
        
        if ($basePath === $targetPath) {
            return '';
        }
        //else  if(strpos($basePath, $targetPath)===0){
        //    $relative_path = $dirname;


        $sourceDirs = explode('/', isset($basePath[0]) && '/' === $basePath[0] ? substr($basePath, 1) : $basePath);
        $targetDirs = explode('/', isset($targetPath[0]) && '/' === $targetPath[0] ? substr($targetPath, 1) : $targetPath);
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);

        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', count($sourceDirs)).implode('/', $targetDirs);

        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see http://tools.ietf.org/html/rfc3986#section-4.2).
        return '' === $path || '/' === $path[0]
            || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? './'.$path : $path;
    }
    
/**
* Zips everything in a directory
*
* @param mixed $source       Source folder or array of folders
* @param mixed $destination  Destination file
*/
function zip($source, $folders, $destination, $verbose=true) {
    if (!extension_loaded('zip')) {
        echo "<br/>PHP Zip extension is not accessible";
        return false;
    }
    if (!file_exists($source)) {
        echo "<br/>".$source." is not found";
        return false;
    }


    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        if($verbose) echo "<br/>Failed to create zip file at ".$destination;
        return false;
    }


    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {


        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;

            // Determine real path
            $file = realpath($file);

            //ignore files that are not in list of specifiede folders
            $is_filtered = true;
            if( is_array($folders) ){

                $is_filtered = false;
                foreach ($folders as $folder) {
                    if( strpos($file, $source."/".$folder)===0 ){
                        $is_filtered = true;
                        break;
                    }
                }
            }

            if(!$is_filtered) continue;

            if (is_dir($file) === true) { // Directory
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            }
            else if (is_file($file) === true) { // File
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    } else if (is_file($source) === true) {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    // Close zip and show output if verbose
    $numFiles = $zip->numFiles;
    $zip->close();
    $size = filesize($destination) / pow(1024, 2);

    if($verbose) {
        echo "<br/>Successfully dumped data from ". $source ." to ".$destination;
        echo "<br/>The zip file contains ".$numFiles." files and is ".sprintf("%.2f", $size)."MB";
    }
    return true;
}

function unzip($zipfile, $destination, $entries=null){

    if(file_exists($zipfile) && filesize($zipfile)>0 &&  file_exists($destination)){

        $zip = new ZipArchive;
        if ($zip->open($zipfile) === TRUE) {

            /*debug to find proper name in archive 
            for($i = 0; $i < $zip->numFiles; $i++) { 
            $entry = $zip->getNameIndex($i);
            error_log( $entry );
            }*/
            if($entries==null){
                $zip->extractTo($destination, array());
            }else{
                $zip->extractTo($destination, $entries);
            }
            $zip->close();
            return true;
        } else {
            return false;
        }

    }else{
        return false;
    }
}
    
        
//-----------------------  LOAD REMOTE CONTENT (CURL)
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

        curl_close($ch);
        return false;
    } else {
        curl_close($ch);
        if(!$data){
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

        }
        return $data;
    }
}

//
//
// alternative2: get_headers()
// alternative3: https://stackoverflow.com/questions/37731544/get-mime-type-by-url
// for local file use mime_content_type
//
function loadRemoteURLContentType($url, $bypassProxy = true, $timeout=30) {

    if(!function_exists("curl_init"))  {
        return false;
    }
    if(!$url){
        return false;
    }

    $content_type = false;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);    // timeout after ten seconds
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_setopt($ch, CURLOPT_URL, $url);

    if ( (!$bypassProxy) && defined("HEURIST_HTTP_PROXY") ) {
        curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if(  defined('HEURIST_HTTP_PROXY_AUTH') ) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }

    $data = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
error_log('http code = '.$code.'  curl error='.$error);
    } else {
        //if(!$data){
            $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);    
        //}
    }
    curl_close($ch);
    
    return $content_type;
}
  
//----------------------------------  
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
 
 
//----------------------------------------------- PARSING 

//
// try to read file and detect sepeartors
// return an aray with suggestions array('csv_delimiter'=>, 'csv_delimiter'=> , 'csv_enclosure'=>)
//
// Important: it works only in case file is UTF8
//
//    
function autoDetectSeparators($filename, $csv_linebreak='auto', $csv_enclosure='"'){
    
    $handle = @fopen($filename, 'r');
    if (!$handle) {
        $s = null;
        if (! file_exists($filename)) $s = ' does not exist';
        else if (! is_readable($filename)) $s = ' is not readable';
            else $s = ' could not be read';
            
        if($s){
            return array('error'=>('File '.$filename. $s));
        }
    }
    
    //DETECT End of line
    if($csv_enclosure=='' || $csv_enclosure=='none'){
        $csv_enclosure = 'ʰ'; //rare character
    }
    
    $eol = null;
    if($csv_linebreak=='win'){
        $eol = "\r\n";
    }else if($csv_linebreak=='nix'){
        $eol = "\n";
    }else if($csv_linebreak=='mac'){
        $eol = "\r";
    }
    
    if($csv_linebreak=='auto' || $csv_linebreak==null || $eol==null){
        ini_set('auto_detect_line_endings', true);
        
        $line = fgets($handle, 1000000);      //read line and auto detect line break
        $position = ftell($handle);
        fseek($handle, $position - 5);
        $data = fread($handle, 10);
        rewind($handle);

        if(substr_count($data, "\r\n")>0){
            $eol = "\r\n";
        }else if(substr_count($data, "\n")>0){
            $eol = "\n";
        }else{
            $eol = "\r";
        }
    }

    //--------- DETECT FIELD SEPARATOR    
    //fgetcsv и str_getcsv depends on server locale
    // it is possible to set it in  /etc/default/locale (Debian) or /etc/sysconfig/i18n (CentOS)  LANG="en_US.UTF-8"
    setlocale(LC_ALL, 'en_US.utf8');        
    
    
    $delimiters = array("\t"=>0,','=>0,';'=>0,':'=>0,'|'=>0,'-'=>0);
    
    foreach ($delimiters as $csv_delimiter=>$val){
        $line_no = 0;
        
        while (!feof($handle)) {

            $line = stream_get_line($handle, 1000000, $eol);
            /*
            if(!mb_detect_encoding($line, 'UTF-8', true)){
                fclose($handle);
                return array('error'=>('File '.$filename. ' is not UTF-8. It is not possible to autodetect separators'));
            }
            */
            
            $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"
            
            $cnt = count($fields);
            if($cnt>200){ //too many fields 
                $delimiters[$csv_delimiter] = 0; //not use
                break;
            }else{
                if($line_no==0){
                    $delimiters[$csv_delimiter] = $cnt; 
                }else if($delimiters[$csv_delimiter] != $cnt){
                    $delimiters[$csv_delimiter] = 0; //not use
                    break;
                }
            }
            
            if($line_no>10) break;
            $line_no++;
        }   
        rewind($handle); 
    }//for delimiters
    fclose($handle);
    
    $max = 0;
    $csv_delimiter = ',';//default
    foreach ($delimiters as $delimiter=>$cnt){
        if($cnt>$max){
            $csv_delimiter = $delimiter;
            $max = $cnt;
        }
    }
    if($csv_delimiter=="\t") $csv_delimiter = "tab";
    
    if($eol=="\r\n"){
        $csv_linebreak='win';
    }else if($eol=="\n"){
        $csv_linebreak='nix';
    }else if($eol=="\r"){
        $csv_linebreak='mac';
    }
    
    return array('csv_linebreak'=>$csv_linebreak, 'csv_delimiter'=>$csv_delimiter, 'csv_enclosure'=>$csv_enclosure);
}
    
?>
