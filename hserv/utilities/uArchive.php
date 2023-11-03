<?php
/**
* uArchive.php 
* 
*   zip
*   unzip
*   unzipFlat
*   
*   createBz2
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

define('MAX_FILES', 10000);
define('MAX_SIZE', 1073741824); // 1 GB
define('MAX_RATIO', 10);
define('READ_LENGTH', 1024);

class UArchive {

    /**
    * Zips everything in a directory
    *
    * @param mixed $source       Source folder or array of folders
    * @param mixed $destination  Destination file
    */
    public static function zip($source, $only_these_folders, $destination, $verbose=true) {

        if (!extension_loaded('zip')) {
            echo "<br/>PHP Zip extension is not accessible";
            return false;
        }
        if (!file_exists($source)) {
            echo "<br/>".htmlspecialchars($source)." was not found";
            return false;
        }



        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
            if($verbose) echo "<br/>Failed to create zip file at ".htmlspecialchars($destination);
            return false;
        }

        try{

            $src = realpath($source);
            if(!$src) {
                if($verbose) {
                    echo '<br/>Cannot create zip archive '.htmlspecialchars($source).' is not a folder';
                }
                return false;
            }
            $source = str_replace('\\', '/', $src);

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
                        foreach ($only_these_folders as $folder) {
                            if( strpos( $file, $folder )===0 ){
                                $is_filtered = true;
                                break;
                            }
                        }
                    }

                    if(!$is_filtered) continue; //exclude not in $only_these_folders

                    // Determine real path
                    $file = realpath($file);

                    if($file!==false){

                        $file2 = str_replace('\\', '/', $file);

                        if (is_dir($file) === true) { // Directory
                            //remove root path
                            $newdir = str_replace($source.'/', '', $file2.'/');
                            if(!$zip->addEmptyDir( $newdir )){
                                //$zip->getStatusString()
                                return false;
                            }
                        }
                        else if (is_file($file) === true) { // File
                            $newfile = str_replace($source.'/', '', $file2); //without folder name
                            if(!$zip->addFile($file, $newfile)){
                                return false;
                            }
                            //$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                        }
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
                    echo "<br/>Successfully dumped data from ". htmlspecialchars($source) ." to ".htmlspecialchars($destination);
                    echo "<br/>The zip file contains ".htmlspecialchars($numFiles." files and is ".sprintf("%.2f", $size))."MB";
                }
            }else{
                return false;    
            }
            return true;

        } catch (Exception  $e){
            error_log( $e->getMessage() );
            if($verbose) {
                echo "<br/>Cannot create zip archive ".htmlspecialchars($destination).' '.Exception::getMessage();
            }
            return false;
        }                            
    }

    /**
    * unzip given archive to destination folder
    * 
    * @param mixed $system
    * @param mixed $zipfile
    * @param mixed $destination
    */
    public static function unzip($system, $zipfile, $destination){

        if(!(file_exists($zipfile) && filesize($zipfile)>0 &&  file_exists($destination))){
            throw new Exception('Archive file not found');
        }

        //set current folder
        chdir($destination);  // relatively db root  or HEURIST_FILES_DIR??        
        $destination_dir = realpath($destination);

        if ($destination_dir !== false) {
            if (strpos($destination_dir, '\\')!==false){
                $destination_dir = str_replace('\\','/',$destination_dir);  
            } 
            if( substr($destination_dir, -1, 1) != '/' ){
                $destination_dir = $destination_dir.'/'; 
            }  
            if (strpos($destination_dir, $system->getFileStoreRootFolder()) !== 0) {
                //HEURIST_SCRATCH_DIR
                //HEURIST_TILESTACKS_DIR
                throw new Exception('Destination folder must within database storage folder');
            }
        }

        $fileCount = 0;
        $totalSize = 0;

        $zip = new ZipArchive();
        if ($zip->open($zipfile) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $stats = $zip->statIndex($i);

                if (strpos($filename, '../') !== false || substr($filename, 0, 1) === '/') {
                    throw new Exception('Archive contains unsecure entry '.$filename);
                }

                if (substr($filename, -1) !== '/') {
                    $fileCount++;
                    if ($fileCount > MAX_FILES) {
                        // Reached max. number of files
                        throw new Exception('Archive contains more than '.MAX_FILES.' entries');
                    }

                    $destination_file = $destination_dir.$filename;

                    $fp = $zip->getStream($filename); // Compliant
                    $currentSize = 0;
                    while (!feof($fp)) {
                        $currentSize += READ_LENGTH;
                        $totalSize += READ_LENGTH;

                        if ($totalSize > MAX_SIZE) {
                            // Reached max. size
                            throw new Exception('Maximum allowed extraction size achieved ('.MAX_SIZE.')');
                        }

                        // Additional protection: check compression ratio
                        if ($stats['comp_size'] > 0  && $stats['comp_size']>READ_LENGTH) {
                            $ratio = $currentSize / $stats['comp_size'];
                            if ($ratio > MAX_RATIO) {
                                // Reached max. compression ratio
                                throw new Exception('Maximum allowed compression ration detected');
                            }
                        }

                        file_put_contents($destination_file, fread($fp, READ_LENGTH), FILE_APPEND);
                    }

                    fclose($fp);
                } else {
                    if (!mkdir($destination_dir.$filename, 0777, true)) {
                        throw new Exception('Cannot create subfolder on unzip');
                    }
                }
            }
            $zip->close();
        }

    }
    //
    // flatten zip archive - extract without structures 
    // returns list of files
    //
    public static function unzipFlat($zipfile, $destination){

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
                        $filename = $destination.USanitize::sanitizeFileName(basename($entry)); //snyk SSRF
                        $ofp = fopen($filename, 'w' ); 
                        while ( ! feof( $fp ) ) 
                            fwrite( $ofp, fread($fp, 8192) ); 

                        fclose($fp); 
                        fclose($ofp); 

                        $res[] = $filename;
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

    //
    //
    //
    public static function createBz2($source, $only_these_folders, $destination, $verbose=true) {

        if (!extension_loaded('bz2')) {
            echo "<br/>PHP Bz2 extension is not accessible";
            return false;
        }
        if (!file_exists($source)) {
            echo "<br/>".htmlspecialchars($source)." was not found";
            return false;
        }else 

            $numFiles = 0;

        $phar = new PharData($destination);

        if (false === $phar) {
            if($verbose) echo "<br/>Failed to create bz2 file at ".htmlspecialchars($destination);
            return false;
        }

        try{

            $src = realpath($source);

            if(!$src) {
                if($verbose) {
                    echo '<br/>Cannot create bz2 archive '.htmlspecialchars($source).' is not a folder';
                }
                return false;
            }

            if($verbose){
                echo "<br/>Source $source $src";
            }

            $source = str_replace('\\', '/', $src);


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
                        foreach ($only_these_folders as $folder) {
                            if( strpos( $file, $folder )===0 ){
                                $is_filtered = true;
                                break;
                            }
                        }
                    }

                    if(!$is_filtered) continue; //exclude not in $only_these_folders

                    // Determine real path
                    $file = realpath($file);

                    if($file!==false){

                        $file2 = str_replace('\\', '/', $file);

                        if (is_dir($file) === true) { // Directory
                            //remove root path
                            $newdir = str_replace($source.'/', '', $file2.'/');
                            $phar->addEmptyDir( $newdir );
                        }
                        else if (is_file($file) === true) { // File
                            $newfile = str_replace($source.'/', '', $file2); //without folder name

                            $phar->addFile($file, $newfile);

                            $numFiles++;

                            //$phar->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                        }

                    }
                }//recursion

            } else if (is_file($source) === true) {

                $size_mb = filesize($source) / pow(1024, 2);
                if($size_mb>128){
                    if($size_mb>256){
                        ini_set('memory_limit','1024M');
                    }else{
                        ini_set('memory_limit','256M');//'2048M');
                    }
                }

                if($verbose) echo "Add file ".basename($source)." (size $size_mb)\n";        

                $phar->addFile($source, basename($source));
                $numFiles++;
                //$phar->addFromString(basename($source), file_get_contents($source));
            }

            $phar->compress(Phar::BZ2);

            if(file_exists($destination.'.bz2')){ //

                unlink($destination);

                $size = filesize($destination.'.bz2') / pow(1024, 2);

                if($verbose) {
                    echo "<br/>Successfully dumped data from ". htmlspecialchars($source) ." to ".htmlspecialchars($destination);
                    echo "<br/>The archive file contains ".$numFiles." files and is ".sprintf("%.2f", $size)."MB";
                }
            }else{
                echo "$destination.bz2 archive not created\n";
                return false;    
            }

            return true;

        } catch (Exception  $e){
            error_log( $e->getMessage() );
            if($verbose) {
                echo "<br/>Cannot create archive ".htmlspecialchars($destination).' '.$e->getMessage();
            }
            return false;
        }                            
    }
}
?>
