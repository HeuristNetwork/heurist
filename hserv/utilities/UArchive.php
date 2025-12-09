<?php
/**
* UArchive.php - Class UArchive
*
* Utility class for creating and extracting ZIP and BZ2 archives.
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
namespace hserv\utilities;
use hserv\utilities\USanitize;

define('MAX_FILES', 10000);
define('MAX_SIZE', 1073741824);// 1 GB
define('MAX_RATIO', 90);
define('READ_LENGTH', 1024);
define('WRITE_LENGTH', 4096);//16384

/**
* Class UArchive
* 
* Utility class for creating and extracting ZIP and BZ2 archives.
* Provides methods to zip directories/files, unzip archives, and handle BZ2 compression/decompression.
* Used for database backup, dropping, and other archival purposes within Heurist.
* 
*/
class UArchive {

    /**
     * Creates a ZIP archive from a source folder or file.
     * Can selectively include specific folders within the source.
     *
     * @param string $source The path to the source directory or file to archive.
     * @param array|null $only_these_folders Optional. An array of specific subfolder paths (relative to $source or absolute)
     *                                       to include. If null or not an array, all contents of $source are considered.
     * @param string $destination The path to the destination ZIP file to be created.
     * @param bool $verbose Optional. If true, outputs progress messages. Defaults to true.
     * @return bool|string True on success, or an error message string on failure if $verbose is true, otherwise false on failure.
     */
    public static function zip($source, $only_these_folders, $destination, $verbose=true) {

        if (!extension_loaded('zip')) {
            return $verbose?'PHP Zip extension is not accessible':false;
        }
        if (!file_exists($source)) {
            return $verbose?(htmlspecialchars($source).' was not found'):false;
        }



        $zip = new \ZipArchive();
        if (!$zip->open($destination, \ZipArchive::CREATE)) {
            return $verbose?('Failed to create zip file at '.htmlspecialchars($destination)):false;
        }

        try{

            $src = realpath($source);
            if(!$src) {
                return $verbose?('Cannot create zip archive '.htmlspecialchars($source).' is not a folder'):false;
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

                $entry_idx = 0;
                $do_not_compress = array('jpg','jpeg','jfif','jpe','gif','png','mp3','mp4','mpg','mpeg','tif','tiff','zip','gzip','kmz','tar');

                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);

                foreach ($files as $file) {

                    $file = str_replace('\\', '/', $file);

                    // Ignore "."   and   ".." folders
                    if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) ) {continue;}

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

                    if(!$is_filtered) {continue;} //exclude not in $only_these_folders

                    // Determine real path
                    $file = realpath($file);

                    if($file!==false){

                        $file2 = str_replace('\\', '/', $file);

                        if (is_dir($file) === true) { // Directory
                            //remove root path
                            $newdir = str_replace($source.'/', '', $file2.'/');
                            if(!$zip->addEmptyDir( $newdir )){
                                //$zip->getStatusString()
                                return $verbose?('Can not add folder '.$newdir.' to archive'):false;
                            }
                            $entry_idx++;
                        }
                        elseif (is_file($file) === true) { // File
                            $newfile = str_replace($source.'/', '', $file2);//without folder name
                            if(!$zip->addFile($file, $newfile)){
                                return $verbose?('Can not add file '.$newfile.' to archive'):false;
                            }
                            $type = strtolower(substr(strrchr($newfile, '.'), 1));
                            if(in_array($type, $do_not_compress)){
                                $zip->setCompressionIndex($entry_idx, \ZipArchive::CM_STORE);
                            }
                            $entry_idx++;

                            //$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                        }
                    }
                }//recursion

            } elseif (is_file($source) === true) {
                $zip->addFile($source, basename($source));
                //$zip->addFromString(basename($source), file_get_contents($source));
            }


            // Close zip and show output if verbose
            $numFiles = $zip->numFiles;
            $zip->close();
            if(file_exists($destination)){
                $size = filesize($destination) / pow(1024, 2);

                if($verbose) {
                    echo "<br>Created full backup file (".htmlspecialchars($numFiles." files, ".sprintf("%.2f", $size))."MB) ".htmlspecialchars($destination);
                    }
            }else{
                return $verbose?($destination.' archive not created. Directory may be non-writeable or archive function is not installed on server'):false;
            }
            return true;

        } catch (\Exception  $e){
            error_log( $e->getMessage() );
            return $verbose?('Cannot create zip archive '.htmlspecialchars($destination).' '.$e->getMessage()):false;
        }
    }

    /**
     * Extracts a ZIP archive to a specified destination folder.
     * Performs security checks on filenames within the archive.
     *
     * @param \hserv\System $system The Heurist system instance.
     * @param string $zipfile Path to the ZIP archive file.
     * @param string $destination Path to the destination directory for extraction.
     * @return int The number of files successfully extracted.
     * @throws \Exception If the archive file is not found, destination is invalid, archive contains unsecure entries,
     *                    or other extraction errors occur (e.g., max files/size/ratio exceeded, cannot create subfolder).
     */
    public static function unzip($system, $zipfile, $destination){

        if(!(file_exists($zipfile) && filesize($zipfile)>0 &&  file_exists($destination))){
            throw new \Exception('Archive file not found');
        }

        $root_folder = $system->getFileStoreRootFolder();
        chdir($root_folder);
        $root_folder = realpath($root_folder);

        if (strpos($root_folder, '\\')!==false){
            $root_folder = str_replace('\\','/',$root_folder);
        }

        //set current folder
        chdir($destination);// relatively db root  or HEURIST_FILES_DIR??
        $destination_dir = realpath($destination);

        if ($destination_dir !== false) {
            if (strpos($destination_dir, '\\')!==false){
                $destination_dir = str_replace('\\','/',$destination_dir);
            }
            if( substr($destination_dir, -1, 1) != '/' ){
                $destination_dir = $destination_dir.'/';
            }
            if (strpos($destination_dir, $root_folder) !== 0) {
                //HEURIST_SCRATCH_DIR
                //HEURIST_TILESTACKS_DIR
                throw new \Exception('Destination folder must within database storage folder ');//$destination_dir.'  '.$root_folder
            }
        }

        $fileCount = 0;
        $totalSize = 0;

        $zip = new \ZipArchive();
        if ($zip->open($zipfile) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $stats = $zip->statIndex($i);

                if (strpos($filename, '../') !== false || substr($filename, 0, 1) === '/') {
                    throw new \Exception('Archive contains unsecure entry '.$filename);
                }

                if (substr($filename, -1) !== '/') {
                    $fileCount++;
                    if ($fileCount > MAX_FILES) {
                        // Reached max. number of files
                        throw new \Exception('Archive contains more than '.MAX_FILES.' entries');
                    }

                    $destination_file = $destination_dir.$filename;

                    $fp = $zip->getStream($filename); // Compliant
                    if (!$fp) {
                        throw new \Exception('Unable to open stream for '.$filename);
                    }

                    // make sure the destination subdirectory exists
                    $destination_file = $destination_dir . $filename;
                    $parentDir = dirname($destination_file);
                    if (!is_dir($parentDir) && !mkdir($parentDir, 0777, true)) {
                        throw new \Exception('Cannot create subfolder on unzip: '.$parentDir);
                    }

                    // open output in truncate mode so we never append to leftovers
                    $ofp = fopen($destination_file, 'wb');
                    if (!$ofp) {
                        fclose($fp);
                        throw new \Exception('Cannot open destination file for write: '.$destination_file);
                    }

                    $currentSize = 0;
                    while (!feof($fp)) {
                        $chunk = fread($fp, READ_LENGTH);        // READ_LENGTH is 1024 in your file
                        if ($chunk === false) {                  // read error
                            fclose($fp);
                            fclose($ofp);
                            @unlink($destination_file);
                            throw new \Exception('Read error while extracting '.$filename);
                        }
                        if ($chunk === '') {                     // nothing more to read
                            break;
                        }

                        $readLen = strlen($chunk);
                        $currentSize += $readLen;
                        $totalSize   += $readLen;

                        if ($totalSize > MAX_SIZE) {
                            fclose($fp);
                            fclose($ofp);
                            @unlink($destination_file);
                            throw new \Exception('Maximum allowed extraction size achieved ('.MAX_SIZE.')');
                        }

                        if ($stats['comp_size'] > 0 && $stats['comp_size'] > READ_LENGTH) {
                            $ratio = floor($currentSize / $stats['comp_size']);
                            if ($ratio > MAX_RATIO) {
                                fclose($fp);
                                fclose($ofp);
                                @unlink($destination_file);
                                throw new \Exception('Maximum allowed compression ratio detected ('.$ratio.' > '.MAX_RATIO.')');
                            }
                        }

                        if (fwrite($ofp, $chunk) === false) {
                            fclose($fp);
                            fclose($ofp);
                            @unlink($destination_file);
                            throw new \Exception('Write error while extracting '.$destination_file);
                        }
                    }
                    fclose($fp);
                    fclose($ofp);

                } else {
                    if (!file_exists($destination_dir.$filename) && !mkdir($destination_dir.$filename, 0777, true)) {
                        throw new \Exception('Cannot create subfolder on unzip');
                    }
                }
            }
            $zip->close();
        }

        return $fileCount;

    }
    /**
     * Extracts files from a ZIP archive into a single destination folder, flattening the directory structure.
     * All files from the archive will be placed directly into the $destination directory.
     * File names are sanitized.
     *
     * @param string $zipfile Path to the ZIP archive file.
     * @param string $destination Path to the destination directory.
     * @return array|false An array of extracted file paths on success, or false if the archive cannot be opened or paths are invalid.
     * @throws \Exception If unable to extract a file from the archive.
     */
    public static function unzipFlat($zipfile, $destination){

        if(file_exists($zipfile) && filesize($zipfile)>0 &&  file_exists($destination)){

            $res = array();
            $zip = new \ZipArchive;
            if ( $zip->open( $zipfile ) === true)
            {
                for ( $i=0; $i < $zip->numFiles; $i++ )
                {
                    $entry = $zip->getNameIndex($i);
                    if ( substr( $entry, -1 ) == '/' ) {continue;} // skip directories

                    $fp = $zip->getStream( $entry );
                    if (!$fp ) {
                        throw new \Exception('Unable to extract the file.');
                    }else{
                        $filename = $destination.USanitize::sanitizeFileName(basename($entry));//snyk SSRF
                        $ofp = fopen($filename, 'w' );
                        while ( ! feof( $fp ) ) {
                            fwrite( $ofp, fread($fp, 8192) );
                        }

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

    /**
     * Creates a BZ2 compressed TAR archive (.tar.bz2) from a source folder or file.
     * First creates a .tar archive, then compresses it using BZ2.
     * Can selectively include specific folders.
     *
     * @param string $source The path to the source directory or file.
     * @param array|null $only_these_folders Optional. An array of specific subfolder paths to include.
     * @param string $destination The base path for the destination TAR archive ('.bz2' will be appended).
     * @param bool $verbose Optional. If true, outputs progress messages. Defaults to true.
     * @return bool|string True on success, or an error message string on failure if $verbose is true, otherwise false on failure.
     */
    public static function createBz2($source, $only_these_folders, $destination, $verbose=true) {

        if (!extension_loaded('bz2')) {
            return $verbose?'PHP Bz2 extension is not accessible':false;
        }
        if (!file_exists($source)) {
            return $verbose?(htmlspecialchars($source).' was not found'):false;
        }else {
            $numFiles = 0;
        }

        $phar = new \PharData($destination);

        if (false === $phar) {
            return $verbose?('Failed to create bz2 file at '.htmlspecialchars($destination)):false;
        }

        try{
            $src = realpath($source);

            if(!$src) {
                return $verbose?('Cannot create bz2 archive '.htmlspecialchars($source).' is not a folder'):false;
            }

            if($verbose){
                echo '<br>Source '.htmlspecialchars($source.' '.$src);
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

                $entry_idx = 0;
                //$do_not_compress = array('jpg','jpeg','jfif','jpe','gif','png','mp3','mp4','mpg','mpeg','tif','tiff','zip','gzip','kmz','tar');

                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);

                foreach ($files as $file) {

                    $file = str_replace('\\', '/', $file);

                    // Ignore "."  and  ".." folders
                    if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) ) {continue;}

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

                    if(!$is_filtered) {continue;} //exclude not in $only_these_folders

                    // Determine real path
                    $file = realpath($file);

                    if($file!==false){

                        $file2 = str_replace('\\', '/', $file);

                        if (is_dir($file) === true) { // Directory
                            //remove root path
                            $newdir = str_replace($source.'/', '', $file2.'/');
                            $phar->addEmptyDir( $newdir );
                            $entry_idx++;
                        }
                        elseif (is_file($file) === true) { // File
                            $newfile = str_replace($source.'/', '', $file2);//without folder name

                            $phar->addFile($file, $newfile);

                            // THERE IS NO WAY TO SET INDIVIDUAL COMPRESSION LEVEL PER FILE

                            $entry_idx++;

                            $numFiles++;
                        }

                    }
                }//recursion

            } elseif (is_file($source) === true) {

                $size_mb = filesize($source) / pow(1024, 2);
                if($size_mb>128){
                    if($size_mb>256){
                        ini_set('memory_limit','1024M');
                    }else{
                        ini_set('memory_limit','256M');//'2048M');
                    }
                }

                if($verbose) {echo "Add file ".htmlspecialchars(basename($source))." (size $size_mb)\n";}

                $phar->addFile($source, basename($source));
                $numFiles++;
                //$phar->addFromString(basename($source), file_get_contents($source));
            }

            $res = self::bzip2($destination, $destination.'.bz2');

            if($res!==true){
                return $verbose?$res:false;
            }

            //$phar->compress(\Phar::BZ2); it does not work for large data

            if(file_exists($destination.'.bz2')){ //

                unlink($destination);

                $size = filesize($destination.'.bz2') / pow(1024, 2);

                if($verbose) {
                    echo "<br>Created SQL-only backup file (".htmlspecialchars($numFiles." files, ".sprintf("%.2f", $size))."MB) ".htmlspecialchars($destination);
                }
            }else{
                return $verbose?($destination.'.bz2 archive not created Directory may be non-writeable or archive function is not installed on server'):false;
            }

            return true;

        } catch (\Exception  $e){
            error_log( $e->getMessage() );
            return $verbose? ('Cannot create archive '.htmlspecialchars($destination).' '.$e->getMessage()) :false;
        }
    }

    /**
     * @return true|string or error message
     * @param string $in - filename to be compressed
     * @param string $out - name of archive if not set it renames $in with bz2 ext and place in the same folder
     * @desc compressing the file with the bzip2-extension
    */

    private static function bzip2 ($in, $out)
    {

        if (!file_exists ($in) || !is_readable ($in)){
            return 'Source file to be archived doesn\'t exists';
        }

        if($out==null){
            $out = $in.'.bz2';
            if(file_exists($out)){
                unlink($out);
            }
        }

        if (  (!file_exists($out) && !is_writeable(dirname($out)))
            ||
              (file_exists($out) && !is_writable($out)) ){
            return 'Destination folder is not writeable';
        }

        $in_file = fopen ($in, "rb");
        $out_file = bzopen ($out, "w");

        while (!feof ($in_file)) {
            $buffer = fgets ($in_file, WRITE_LENGTH);
            bzwrite ($out_file, $buffer, WRITE_LENGTH);
        }

        fclose ($in_file);
        bzclose ($out_file);

        return true;
    }


    /**
     * Decompresses a BZ2 compressed file.
     *
     * @param string $in Path to the input BZ2 compressed file.
     * @param string $out Path to the output decompressed file.
     * @return bool True on successful decompression.
     * @throws \Exception If the input file doesn't exist/is not readable, or the output path is not writable.
     */
    public static function bunzip2($in, $out)
    {
        if (!file_exists ($in) || !is_readable ($in)){
             throw new \Exception('Archive file doesn\'t exists');
        }

        if (!file_exists ($out) && !is_writeable (dirname ($out)) || (file_exists($out) && !is_writable($out)) ){
             throw new \Exception('Destination folder or file is not writeable');
        }

        $in_file = bzopen ($in, "r");
        $out_file = fopen ($out, "wb");

        while ($buffer = bzread ($in_file, 4096)) {
            fwrite ($out_file, $buffer, 4096);
        }

        bzclose ($in_file);
        fclose ($out_file);

        return true;
    }
}
?>
