<?php

    /**
    * File library - convert to static class
    *  File/folder utilities
    *
    * folderCreate
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
    
    /*
    sanitizeRequest   - removes all tags fro request variables
    stripScriptTagInRequest - removes only script tags
    getHTMLPurifier
    purifyHTML - clean html with HTMLPurifier
    
    get_php_bytes
    redirectToRemoteServer - redirect request to remove heurist server
    
    ----
    
    folderExists
    folderCreate
    folderDelete  
    folderDelete2   using RecursiveIteratorIterator
    
    folderCreate2  - create folder, check write permissions, add index.html, write htaccess 
    folderAddIndexHTML
    allowWebAccessForForlder
    
    folderContent  - get list of files in folder as search result (record list)
    folderSize
    folderTree
    
    fileCopy
    fileSave
    fileOpen - check existance, readability, opens and returns file handle, or -1 not exist, -2 not readable -3 can't open
    
    fileNameSanitize
    fileNameBeautify
    
    generate_thumbnail 
    
    saveURLasFile  loadRemoteURLContent + fileSave
    @todo move from record_shp? fileRetrievePath returns fullpath to file is storage, or to tempfile, 
                   if it requires it extracts zip archive to tempfile or download remote url to tempfile
    
    getRelativePath
    folderRecurseCopy
    
    ===
    createZipArchive
    unzipArchive
    unzipArchiveFlat
    
    ===
    loadRemoteURLContentSpecial tries to avoid curl if url on the same domain
    
    loadRemoteURLContent
    loadRemoteURLContentWithRange  load data with curl
    loadRemoteURLContentType
    
    getScriptOutput
    
    =====
    
    autoDetectSeparators
    */
    
    $glb_curl_code = null;
    $glb_curl_error = null;
    
    function sanitizeRequest(&$params){

        foreach($params as $k => $v)
        {
            if($v!=null){
                
                if(is_array($v) && count($v)>0){
                    sanitizeRequest($v);
                    
                }else{
                    $v = trim($v);//so we are sure it is whitespace free at both ends

                    //sanitise string
                    $v = filter_var($v, FILTER_SANITIZE_STRING);
               
                }
                $params[$k] = $v;
            }
        }
        
    }


    function stripScriptTagInRequest(&$params){

        foreach($params as $k => $v)
        {
            if($v!=null){
                
                if(is_array($v) && count($v)>0){
                    stripScriptTagInRequest($v);
                }else{
                    $v = trim($v);//so we are sure it is whitespace free at both ends

                    //remove script tag
                    $v = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $v);
               
                }
                $params[$k] = $v;
            }
        }//for
    }

    //
    //
    //
    function getHTMLPurifier(){

            $config = HTMLPurifier_Config::createDefault();  
            //$config->set('Cache.DefinitionImpl', null);
            $config->set('Cache.SerializerPath', HEURIST_SCRATCHSPACE_DIR);
            //$config->set('Core.EscapeNonASCIICharacters', true);
            $config->set('CSS.AllowImportant', true);
            $config->set('CSS.AllowTricky', true);
            $config->set('CSS.Proprietary', true);
            $config->set('CSS.Trusted', true);
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
            $def = $config->getHTMLDefinition(true);
            $def->addAttribute('div', 'id', 'Text');            
            $def->addAttribute('div', 'data-heurist-app-id', 'Text');            
            $def->addAttribute('div', 'data-inited', 'Text');
            $def->addAttribute('a', 'data-ref', 'Text');
            
            return new HTMLPurifier($config);
        
    }
    //
    //
    //    
    function purifyHTML(&$params, $purifier = null){
        
        if($purifier==null){
            $purifier = getHTMLPurifier();
        }

        foreach($params as $k => $v)
        {
            if($v!=null){
                
                if(is_array($v) && count($v)>0){
                    purifyHTML($v, $purifier);
                }else{
                    $v = $purifier->purify($v);
                    //$v = htmlspecialchars_decode($v);
                }
                $params[$k] = $v;
            }
        }//for
    }

    
    //
    // 
    //
    function get_php_bytes( $php_var ){
        
        $val = ini_get($php_var);
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);

        if($last){
            $val = intval(substr($val,0,strlen($val)-1));
        }
            
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
    
    // @todo
    // redirect request to remote heurist server 
    //
    function redirectToRemoteServer( $request ){
       /* 
       preg_match("/db=([^&]*).*$/", $url, $match);
       return $match[1]         
       */
    }
    
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
    
    function folderExistsVerbose($folder, $testWrite, $folderName){

        $res = folderExists($folder, $testWrite);
        if($res<0){
            $s='';
            if($res==-1){
                $s = 'Cant find folder "'.$folderName.'" in database directory';
            }else if($res==-2){
                $s = 'Folder "'.$folderName.'" in database directory is not writeable';
            }else if($res==-3){
                $s = 'Cant create folder "'.$folderName.'" in database directory. It is not possible to delete file with the same name';
            }
            
            return $s;
        }

        return true;        
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
    * create folder, check write permissions, add index.html, write htaccess 
    * 
    * @param mixed $folder
    * @param mixed $message
    * @param mixed $allowWebAccess
    */
    function folderCreate2($folder, $message, $allowWebAccess=false){
        
        $swarn = '';
        
        $check = folderExists($folder, true);
        
        if($check==-2){
            $swarn = 'Cannot access folder (it, or a subdirectory, is not writeable) '. $folder .'  '.$message.'<br>';
            
        }else if($check==-1){
            if (!mkdir($folder, 0777, true)) {
                $swarn = 'Unable to create folder '. $folder .'  '.$message.'<br>';
            }else{
                $check=1;
            }
        }
        
        if ($check>0){
            
            folderAddIndexHTML( $folder );
            
            if($allowWebAccess){
                //copy htaccess
                $res = allowWebAccessForForlder( $folder );
                if(!$res){
                    $swarn = "Cannot copy htaccess file for folder $folder<br>";
                }
            }
        }
        return $swarn;
    }   
    
    /**
    * add index.html to folder
    * 
    * @param mixed $directory
    */
    function folderAddIndexHTML($folder) {
        
        $filename = $folder."/index.html";
        if(!file_exists($filename)){
            $file = fopen($filename,'x');
            if ($file) { // returns false if file exists - don't overwrite
                fwrite($file,"Sorry, this folder cannot be browsed");
                fclose($file);
            }
        }
    }
    
   
    //
    //
    //
    function allowWebAccessForForlder($folder){
        $res = true;
        if(file_exists($folder) && is_dir($folder) && !file_exists($folder.'/.htaccess')){
            $res = copy(HEURIST_DIR.'admin/setup/.htaccess_via_url', $folder.'/.htaccess');
        }
        return $res;
    }
   
    /**
    * clean folder and itself
    * 
    * @param mixed $dir
    */
    function folderDelete($dir, $rmdir=true) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") {
                        folderDelete($dir."/".$object); //delte files
                    } else {
                        unlink($dir."/".$object);
                    }
                }
            }
            reset($objects);
            if($rmdir)
                rmdir($dir); //delete folder itself
        }
    }
    
    //
    // remove folder and all its content
    //
    function folderDelete2($dir, $rmdir) {

        $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        if($rmdir){
            $res = rmdir($dir);
            return $res;
        }else{
            return true;
        }
    }
    
    //
    // get list of files in folder as search result (record list)
    //
    function folderContent($dirs, $exts) {
        
        $records = array();
        $order = array();
        $fields = array('file_id', 'file_name', 'file_dir', 'file_url');
        $idx = 1;
        if(!is_array($dirs)) $dirs = array($dirs);
        if(!is_array($exts)) $exts = array($exts);

        foreach ($dirs as $dir) {
            
            if(strpos($dir, 'HEURIST_ICON_DIR')!==false){
                //$folder = constant($dir);     @todo need better algorithm
                $folder = str_replace('HEURIST_ICON_DIR/', HEURIST_ICON_DIR, $dir);
                $url = str_replace('HEURIST_ICON_DIR/', HEURIST_ICON_URL, $dir);
            }else if (strpos($dir, HEURIST_FILESTORE_DIR)!==false) {    
                
                $folder =  $dir;
                $url = null;
                
            }else{
                $folder =  HEURIST_DIR.$dir;
                $url = HEURIST_BASE_URL.$dir;
            }
            
            if (!(file_exists($folder) && is_dir($folder))) continue;
            
            $files = scandir($folder);
            foreach ($files as $filename) {
                //if (!(( $filename == '.' ) || ( $filename == '..' ) || is_dir(HEURIST_DIR.$dir . $filename))) 
                
                    $path_parts = pathinfo($filename);
                    if(array_key_exists('extension', $path_parts))
                    {
                        $ext = strtolower($path_parts['extension']);
                        if(file_exists($folder.$filename) && in_array($ext, $exts))
                        {
                            $records[$idx] = array($idx, $filename, $folder, $url);
                            $order[] = $idx;
                            $idx++;
                        }
                    }
                
            }//for
        }

        
        $response = array(
                            'pageno'=>0,  //page number to sync
                            'offset'=>0,
                            'count'=>count($records),
                            'reccount'=>count($records),
                            'fields'=>$fields,
                            'records'=>$records,
                            'order'=>$order,
                            'entityName'=>'files');

        return $response;
        
    }
    
    //
    //
    //
    function folderSize2($dir){
        $size = 0;
        $arr = glob(rtrim($dir, '/').'/*', GLOB_NOSORT);
        foreach ($arr as $each) {
            $size += is_file($each) ? filesize($each) : folderSize($each);
        }
        return $size;        
    }
    
    
    function folderSize($dir)
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');

        if (is_dir($dir) === true) {
            $totalSize = 0;
            $os        = strtoupper(substr(PHP_OS, 0, 3));
            // If on a Unix Host (Linux, Mac OS)
            if ($os !== 'WIN') {
                $io = popen('/usr/bin/du -sb ' . $dir, 'r');
                if ($io !== false) {
                    $totalSize = intval(fgets($io, 80));
                    pclose($io);
                    return $totalSize;
                }
            }
            // If on a Windows Host (WIN32, WINNT, Windows)
            if ($os === 'WIN' && extension_loaded('com_dotnet')) {
                $obj = new \COM('scripting.filesystemobject');
                if (is_object($obj)) {
                    $ref       = $obj->getfolder($dir);
                    $totalSize = $ref->size;
                    $obj       = null;
                    return $totalSize;
                }
            }
            // If System calls did't work, use slower PHP 5
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($files as $file) {
                $totalSize += $file->getSize();
            }
            return $totalSize;
        } else if (is_file($dir) === true) {
            return filesize($dir);
        }
    }    

    
    /**
     * Creates a tree-structured array of directories and files from a given root folder.
     *
     * @param string $dir
     * @param string $params
     *                  withFiles: false
     *                  refex: file filter
     *                  ignoreEmtpty: not include emoty folder
     *                  systemFolders: 
     *                  format: fancy (for fancyTree)
     * @param boolean $ignoreEmpty Do not add empty directories to the tree
     * @return array
     */
    function folderTree($dir, $params, $is_system=false)
    {
        if($dir==null){
            $dir = HEURIST_FILESTORE_DIR;
        }
        
        if (!$dir instanceof DirectoryIterator) {
            $dir = new DirectoryIterator((string)$dir);
        }
        $dirs  = array();
        $files = array();
        $file_count = 0;
        
        $withFiles = (@$params['withFiles']==true);
        $regex = @$params['regex'];
        $ignoreEmpty = (@$params['ignoreEmtpty']==true);
        $systemFolders = @$params['systemFolders'];
        $isFancy = (@$params['format']=='fancy');
        if(is_array($params)) {$params['systemFolders'] = null;} //use on first level only
        if($regex==null) $regex = '';
        
        $fancytree = array();
        
        foreach ($dir as $node) {
            if ($node->isDir() && !$node->isDot()) {
                
                $folder_name = $node->getFilename();
                $is_system = (@$systemFolders[$folder_name]!=null);
                //(@$params['is_system']==true) || 
                //$params['is_system'] = $is_system;
                
                $tree = folderTree($node->getPathname(), $params, $is_system);
                if (!$ignoreEmpty || count($tree)) {
                    
                    if($isFancy){
                        $arr = array( 'key'=>$folder_name, 'title'=>$folder_name, 
                            'folder'=>true, 'issystem'=>$is_system, 
                            'children'=>$tree['children'], 'files_count'=>$tree['count'] );
                            
                       if($is_system){
                           $arr['unselectable'] = true;
                           $arr['unselectableStatus'] = false;
                       }    
                       
                       $fancytree[] = $arr;
                            
                    }else{
                        $dirs[$folder_name] = $tree;    
                    }            
                }
                
            } else if ($node->isFile()) {
                if($withFiles){
                    $name = $node->getFilename();
                    if ('' == $regex || preg_match($regex, $name)) { //file filter
                        $files[] = $name;
                        $fancytree[] = array( 'key'=>$name, 'title'=>$name);
                    }
                }
                $file_count++;
            }
        }
        
        if($isFancy){
            usort($fancytree, "cmp");
            return array('children'=>$fancytree, 'count'=>$file_count);
        }else{
            asort($dirs);
            sort($files);
            return array_merge($dirs, $files);
        }
    }    
    
    
    function cmp($a, $b)
    {
        if ($a['title'] == $b['title']) {
            return 0;
        }
        return ( strtolower($a['title']) < strtolower($b['title'])) ? -1 : 1;
    }  
      
    //
    //
    //
    function folderTreeToFancyTree($data, $lvl=0, $sysfolders=null){
        //for fancytree
        $fancytree = array();
        foreach($data as $folder => $children){
            
            $item = array( 'key'=>$folder, 'title'=>$folder, 
                        'folder'=>($folder>=0), 'issystem'=>(@$sysfolders[$folder]!=null) );
            
            if($children && count($children)>0){
                $item['children'] = folderTreeToFancyTree($children, $lvl+1);
            }   
            $fancytree[] = $item;
        }
        usort($fancytree, "cmp");
        return $fancytree; 
        
    }
    
    
        
    //
    // check file existance, readability and opens the file
    // returns file handle, or -1 not exist, -2 not readable -3 can't open
    //
    function fileOpen($file)
    {
        if (!(file_exists($file) && is_file($file))) {
            return -1;
        }
        if (!is_readable($file)) {
            return -2;
        }
        $handle = fopen($file, 'rb');
        if (!$handle) {
            return -3;
        }
        return $handle;
    }
        
    //
    //
    //
    function fileCopy($s1, $s2) {
        $path = pathinfo($s2);

        if(folderCreate($path['dirname'], true)){
            if (!copy($s1,$s2)) {
                // "copy failed";
                error_log( 'Can not copy file '.$s1.'  to '.$s2 );    
                return false;
            }
        }else{
           //can't create folder or it is not writeable 
           error_log( 'Can not create folder '.$path['dirname'] );    
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
    //
    //
    //
    function fileAdd($rawdata, $filename)
    {
        if($rawdata){
            try{
                $fp = fopen($filename,'a'); //open for add
                if($fp===false){
                    error_log( 'Can not open file '.$filename );    
                }else{
                    fwrite($fp, $rawdata);
                    fclose($fp);
                }
            
            }catch(Exception  $e){
                error_log( 'Can not open file '.$filename.'  Error:'.Exception::getMessage() );
            }

            return filesize($filename);
        }else{
            return 0;
        }
    }


    //
    // Generates thumbnail for given URL
    //
    // returns temp file path or array with error messahe 
    //    
    function generate_thumbnail($sURL){

        if(!defined('WEBSITE_THUMBNAIL_SERVICE') || WEBSITE_THUMBNAIL_SERVICE==''){
            return array('error'=>'Thumbnail generator service not defined');
        }
        
        $res = array();
        //get picture from service
        //"http://www.sitepoint.com/forums/image.php?u=106816&dateline=1312480118";
        //https://api.thumbnail.ws/api/ab73cfc7f4cdf591e05c916e74448eb37567feb81d44/thumbnail/get?url=[URL]&width=320
        $remote_path =  str_replace("[URL]", $sURL, WEBSITE_THUMBNAIL_SERVICE);
        $heurist_path = tempnam(HEURIST_SCRATCH_DIR, "_temp_"); // . $file_id;

        $filesize = saveURLasFile($remote_path, $heurist_path);

        if($filesize>0 && file_exists($heurist_path)){

            //check the dimension of returned thumbanil in case it is less than 50 - consider it as error
            if(strpos($remote_path, substr(WEBSITE_THUMBNAIL_SERVICE,0,24))==0){

                $image_info = getimagesize($heurist_path);
                if($image_info[1]<50){
                    //remove temp file
                    unlink($heurist_path);
                    return array('error'=>'Thumbnail generator service can\'t create the image for specified URL');
                }
            }

            $file = new \stdClass();
            $file->original_name = 'snapshot.jpg';
            $file->name = $heurist_path; //pathinfo($heurist_path, PATHINFO_BASENAME); //name with ext
            $file->fullpath = $heurist_path;
            $file->size = $filesize; //fix_integer_overflow
            $file->type = 'jpg';
                                
            return $file;    
            
        }else{
            return array('error'=>'Cannot download image from thumbnail generator service. '.$remote_path.' to '.$heurist_path);
        }
        
    }    
    
    /**
    * save remote url as file and returns the size of saved file
    *
    * @param mixed $url
    * @param mixed $filename
    */
    function saveURLasFile($url, $filename)
    { //Download file from remote server
        $rawdata = loadRemoteURLContent($url, false); //use proxy 
        if($rawdata!==false){
            return fileSave($rawdata, $filename); //returns file size
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
* copy folder recursively
*
* @param mixed $src
* @param mixed $dst
* @param array $folders - zero level folders to copy
*/
function folderRecurseCopy($src, $dst, $folders=null, $file_to_copy=null, $copy_files_in_root=true) {
    $res = false;

    $src =  $src . ((substr($src,-1)=='/')?'':'/');

    $dir = opendir($src);
    if($dir!==false){

        if (file_exists($dst) || @mkdir($dst, 0777, true)) {

            $res = true;

            while(false !== ( $file = readdir($dir)) ) {
                if (( $file != '.' ) && ( $file != '..' )) {
                    if ( is_dir($src . $file) ) {

                        if($folders==null || count($folders)==0 || in_array($src.$file.'/',$folders))
                        {
                            if($file_to_copy==null || strpos($file_to_copy, $src.$file)===0 )
                            {
                                $res = folderRecurseCopy($src.$file, $dst . '/' . $file, null, $file_to_copy, true);
                                if(!$res) break;
                            }
                        }

                    }
                    else if($copy_files_in_root && ($file_to_copy==null || $src.$file==$file_to_copy)){
                        copy($src.$file,  $dst . '/' . $file);
                        if($file_to_copy!=null) return false;
                    }
                }
            }
        }
        closedir($dir);

    }

    return $res;
}   

   
    
//------------------------------------------    
/**
* Zips everything in a directory
*
* @param mixed $source       Source folder or array of folders
* @param mixed $destination  Destination file
*/
function createZipArchive($source, $only_these_folders, $destination, $verbose=true) {
    
//error_log('>>>>>createZipArchive '.$source.'  to '.$destination);    
    if (!extension_loaded('zip')) {
        echo "<br/>PHP Zip extension is not accessible";
        return false;
    }
    if (!file_exists($source)) {
        echo "<br/>".$source." was not found";
        return false;
    }

    

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        if($verbose) echo "<br/>Failed to create zip file at ".$destination;
        return false;
    }

    try{

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {

        chdir($source);
        
        $parent_dir = '';
        //$root_dir = $source;
        if( is_array($only_these_folders) ){
            foreach ($only_these_folders as $idx=>$folder) {
                $folder = str_replace('\\', '/', $folder);
                if(strpos( $folder, $source )!==0){
                    $folder = $source."/".$folder;    
                }
                $only_these_folders[$idx] = $folder;
//error_log($folder);
            }
        }
        
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {

            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;
                
            if( is_dir($file) && substr($file,-1)!='/' ){
                $file = $file.'/';
            }

            //ignore files that are not in list of specified folders
            $is_filtered = true;
            if( is_array($only_these_folders) ){
                $is_filtered = false;
//error_log($file);
                foreach ($only_these_folders as $folder) {
                    if( strpos( $file, $folder )===0 ){
                        $is_filtered = true;
                        break;
                    }
                }
            }

            if(!$is_filtered) continue; //exclude not in $only_these_folders

//error_log('OK '.$file);

            // Determine real path
            $file = realpath($file);

            $file2 = str_replace('\\', '/', $file);
            
            if (is_dir($file) === true) { // Directory
                //remove root path
                $newdir = str_replace($source.'/', '', $file2.'/');
//error_log($newdir);                    
                if(!$zip->addEmptyDir( $newdir )){
                    //error_log($zip->getStatusString());
                    return false;
                }
            }
            else if (is_file($file) === true) { // File
                $newfile = str_replace($source.'/', '', $file2); //without folder name
//error_log($file.'  >> '.$newfile);                    
                if(!$zip->addFile($file, $newfile)){
                    return false;
                }
                //$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }//recursion
        
    } else if (is_file($source) === true) {
        $zip->addFile($source, basename($source));
        //$zip->addFromString(basename($source), file_get_contents($source));
    }

    
    // Close zip and show output if verbose
    $numFiles = $zip->numFiles;
    $zip->close();
    if(file_exists($destination)){
        $size = filesize($destination) / pow(1024, 2);

        if($verbose) {
            echo "<br/>Successfully dumped data from ". $source ." to ".$destination;
            echo "<br/>The zip file contains ".$numFiles." files and is ".sprintf("%.2f", $size)."MB";
        }
    }else{
        return false;    
    }
    return true;
    
    } catch (Exception  $e){
        error_log( Exception::getMessage() );
        if($verbose) {
            echo "<br/>Can not create zip archive ".$destination.' '.Exception::getMessage();
        }
        return false;
    }                            
}

function unzipArchive($zipfile, $destination, $entries=null){

    if(file_exists($zipfile) && filesize($zipfile)>0 &&  file_exists($destination)){

        $zip = new ZipArchive;
        if ($zip->open($zipfile) === TRUE) {

            /*debug to find proper name in archive 
            for($i = 0; $i < $zip->numFiles; $i++) { 
            $entry = $zip->getNameIndex($i);
            error_log( $entry );
            }*/
            if($entries==null){
                $zip->extractTo($destination);//, array()
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

//
// flatten zip archive - extract without structures 
// returns list of files
//
function unzipArchiveFlat($zipfile, $destination){

    if(file_exists($zipfile) && filesize($zipfile)>0 &&  file_exists($destination)){
        
        $res = array();
        $zip = new ZipArchive; 
        if ( $zip->open( $zipfile ) === true) 
        { 
            for ( $i=0; $i < $zip->numFiles; $i++ ) 
            { 
                $entry = $zip->getNameIndex($i); 
                if ( substr( $entry, -1 ) == '/' ) continue; // skip directories 
                
                $fp = $zip->getStream( $entry ); 
                if (!$fp ) {
                    throw new Exception('Unable to extract the file.'); 
                }else{                
                    $ofp = fopen($destination.basename($entry), 'w' ); 
                    while ( ! feof( $fp ) ) 
                        fwrite( $ofp, fread($fp, 8192) ); 
                    
                    fclose($fp); 
                    fclose($ofp); 
                    
                    $res[] = $destination.basename($entry);
                }
            } 

            $zip->close(); 
            return $res;
        } 
        else {
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
        
        //if requested url is on the same server 
        //replace URL to script path in current installation folder
        //and execute script 
        if(strpos($url, HEURIST_INDEX_BASE_URL)===0){
            $path = str_replace(HEURIST_INDEX_BASE_URL, HEURIST_DIR, $url);
        }else{
            $path = str_replace(HEURIST_BASE_URL, HEURIST_DIR, $url);
        }

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
    
    global $glb_curl_code, $glb_curl_error;
    
    $glb_curl_code = null;
    $glb_curl_error = null;

    if(!function_exists("curl_init"))  {
        $glb_curl_code = HEURIST_SYSTEM_FATAL;
        $glb_curl_error = 'Can not init curl extension. Verify php installation';
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
    //curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:'));
    
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
        
        $glb_curl_code = 'curl';
        $glb_curl_error = $error;
        
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

//error_log($url.' http code = '.$code.'  curl error='.$error);

        curl_close($ch);
        return false;
    } else {
        if(!$data){
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            $glb_curl_code = HEURIST_SYSTEM_FATAL;
            $glb_curl_error = 'It does not return data. HTTP code '.$code;
        }
        curl_close($ch);
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
    global $system;
    
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

//
//
//
function isXMLfile($filename){
    
    $res = false;
    $handle = @fopen($filename, 'r');
    if ($handle) {
        $output = fread($handle, 10);   
        $pp = strpos($output, '<?xml'); 
        $res = ($pp === 0 || $pp === 3);
        fclose($handle);
    }
    return $res;
}

function flush_buffers($start=true){
    //ob_end_flush();
    @ob_flush();
    @flush();
    if($start) @ob_start();
}

//
//
//
function fileNameSanitize($filename, $beautify=true) {
    // sanitize filename
    $filename = preg_replace(
        '~
        [<>:"/\\|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [#\[\]@!$&\'()+,;=]|     # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
        ~x',
        '-', $filename);
    // avoids ".", ".." or ".hiddenFiles"
    $filename = ltrim($filename, '.-');
    // optional beautification
    if ($beautify) $filename = fileNameBeautify($filename);
    // maximize filename length to 255 bytes http://serverfault.com/a/9548/44086
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $filename = mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)) . ($ext ? '.' . $ext : '');
    return $filename;
}

//
//
//
function fileNameBeautify($filename) {
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

?>
