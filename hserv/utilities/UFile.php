<?php

/**
* UFile.php - Utility functions for file and folder manipulation in Heurist
*
* This file provides a collection of global functions for tasks such as:
* - Checking existence, creating, and deleting folders (folderExists, folderCreate, folderDelete, folderDelete2, folderCreate2).
* - Managing folder content and structure (folderAddIndexHTML, allowWebAccessForForlder, folderContent, folderSize, folderSize2, folderFirstFile, folderGetSubFolders, folderTree, folderRecurseCopy, folderSubs).
* - Basic file operations (fileCopy, fileSave, fileOpen, fileDelete, fileAdd, getUniqueFileName).
* - Path manipulation (getRelativePath, isPathInHeuristUploadFolder).
* - Remote content retrieval using cURL (saveURLasFile, getTitleFromURL, loadRemoteURLContentSpecial, loadRemoteURLContent, loadRemoteURLContentWithRange, loadRemoteURLContentType).
* - MIME type recognition (recognizeMimeTypeFromURL, getURLExtension).
* - Script output capture (getScriptOutput).
* - CSV separator detection (autoDetectSeparators).
* - XML file check (isXMLfile).
* - Action progress locking (isActionInProgress).
* - Uploading files to Nakala (uploadFileToNakala).
* - Memory-efficient file reading and size retrieval (fileReadByChunks, getFileSize).
*
* Note: This file was previously described as a class "UFile", but currently contains global functions.
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
use hserv\utilities\USanitize;
use hserv\utilities\USystem;

$glb_curl_code = null;
$glb_curl_error = '';


/**
 * Checks if a folder exists and optionally if it's writable.
 * Also handles cases where a file with the same name as the folder exists.
 *
 * @param string $folder The path to the folder.
 * @param bool $testWrite If true, also checks if the folder is writable.
 * @return int 1 if folder exists (and is writable if $testWrite is true),
 *             -1 if path does not exist (or a conflicting file was deleted),
 *             -2 if folder exists but is not writable (and $testWrite is true),
 *             -3 if a file with the same name exists but cannot be deleted.
 */
function folderExists($folder, $testWrite)
{

    if (file_exists($folder)) {

        if (is_dir($folder)) {

            if ($testWrite && !is_writable($folder)) {
                //echo ("<h3>Warning:</h3> Folder $folder already exists and it is not writeable. Check permissions! ($msg)<br>");
                return -2;
            }
        } else {
            if (!unlink($folder)) {
                //echo ("<h3>Warning:</h3> Unable to remove file $folder. We need to create a folder with this name ($msg)<br>");
                return -3;
            }
            return -1;
        }

        return 1;

    } else {
        return -1;
    }

}

/**
 * Provides a verbose error message based on the result of folderExists.
 *
 * @param string $folder The path to the folder.
 * @param bool $testWrite If true, write permissions were tested.
 * @param string $folderName A descriptive name for the folder (used in error messages).
 * @return string|true True if folder exists and meets conditions, otherwise an error message string.
 */
function folderExistsVerbose($folder, $testWrite, $folderName)
{

    $res = folderExists($folder, $testWrite);
    if ($res < 0) {
        $s = '';
        if ($res == -1) {
            $s = 'Cant find folder "' . $folderName . '" in database directory';
        } elseif ($res == -2) {
            $s = 'Folder "' . $folderName . '" in database directory is not writeable';
        } elseif ($res == -3) {
            $s = 'Cant create folder "' . $folderName . '" in database directory. It is not possible to delete file with the same name';
        }

        return $s;
    }

    return true;
}


/**
 * Creates a folder if it doesn't exist.
 * Relies on folderExists to check status before creation.
 *
 * @param string $folder The path to the folder to create.
 * @param bool $testWrite If true, checks if the parent directory (or existing folder) is writable.
 * @return bool True if the folder exists or was successfully created, false on failure to create.
 */
function folderCreate($folder, $testWrite)
{

    // -1  not exists
    // -2  not writable
    // -3  file with the same name cannot be deleted
    $res = folderExists($folder, $testWrite);

    if ($res == -1) {
        if (!mkdir($folder, 0775, true)) {
            //echo ("<h3>Warning:</h3> Unable to create folder $folder ($msg)<br>");
            return false;
        }
    }

    return true;
}


/**
 * Creates a folder, checks write permissions, adds an index.html file to prevent browsing,
 * and optionally adds an .htaccess file to allow web access.
 *
 * @param string $folder The path to the folder to create.
 * @param string $message A descriptive message used in error reporting if folder creation fails (related to the purpose of the folder).
 * @param bool $allowWebAccess Optional. If true, attempts to copy an .htaccess file to allow web access. Defaults to false.
 * @return string An error message string if folder creation or setup fails, otherwise an empty string.
 */
function folderCreate2($folder, $message, $allowWebAccess = false)
{

    $swarn = '';

    $check = folderExists($folder, true);

    if ($check == -2) {
        $swarn = 'Cannot access folder (it, or a subdirectory, is not writeable) ' . $folder . '  ' . $message . '<br>';

    } elseif ($check == -1) {
        if (!mkdir($folder, 0777, true)) {
            $swarn = 'Unable to create folder ' . $folder . '  ' . $message . '<br>';
        } else {
            $check = 1;
        }
    }

    if ($check > 0) {

        folderAddIndexHTML($folder);

        if ($allowWebAccess) {
            //copy htaccess
            $res = allowWebAccessForForlder($folder);
            if (!$res) {
                $swarn = "Cannot copy htaccess file for folder $folder<br>";
            }
        }
    }
    return $swarn;
}

/**
 * Adds an index.html file to the specified folder to prevent directory browsing.
 * The file contains a simple "Sorry, this folder cannot be browsed" message.
 *
 * @param string $folder The path to the folder.
 * @return void
 */
function folderAddIndexHTML($folder)
{

    $filename = $folder . "/index.html";
    if (!file_exists($filename)) {
        $file = fopen($filename, 'x');
        if ($file) { // returns false if file exists - don't overwrite
            fwrite($file, "Sorry, this folder cannot be browsed");
            fclose($file);
        }
    }
}


/**
 * Allows web access to a folder by copying a pre-configured .htaccess file into it.
 * This .htaccess typically allows direct access to files but not directory listing.
 *
 * @param string $folder The path to the folder.
 * @return bool True if the .htaccess file was copied successfully or already exists, false on failure to copy.
 */
function allowWebAccessForForlder($folder)
{
    $res = true;
    $folder = USanitize::sanitizePath($folder);
    if (file_exists($folder) && is_dir($folder) && !file_exists($folder . '.htaccess')) {
        $res = copy(HEURIST_DIR . 'admin/setup/.htaccess_via_url', $folder . '.htaccess');
    }
    return $res;
}

/**
 * Recursively deletes a folder and its contents.
 *
 * @param string $dir The path to the directory to delete.
 * @param bool $rmdir Optional. If true (default), removes the directory itself after deleting its contents.
 * @param bool $verbos Optional. If true, returns an array of messages about deleted files/folders. Defaults to false.
 * @return array|null If $verbos is true, an array of messages. Otherwise, null.
 */
function folderDelete($dir, $rmdir = true, $verbos = false)
{

    $msgs = [];

    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != '.' && $object != '..') {
                if (filetype($dir . '/' . $object) == 'dir') {

                    $rtn = folderDelete($dir . '/' . $object, true, $verbos);//delete files

                    $msgs[] = "Deleting sub directory $object";
                    if ($verbos && !empty($rtn)) { // merge messages
                        $msgs = array_merge($msgs, $rtn);
                    }
                } else {
                    $msgs[] = "Deleted file $object, size: " . filesize("$dir/$object");
                    unlink($dir . '/' . $object);
                }
            }
        }
        reset($objects);
        if ($rmdir) {
            rmdir($dir);//delete folder itself
        }
    }

    return $verbos ? $msgs : null;
}

/**
 * Removes a folder and all its contents using RecursiveIteratorIterator.
 *
 * @param string $dir The path to the directory to delete.
 * @param bool $rmdir If true, removes the directory itself after deleting its contents.
 * @return bool True if the operation was successful or the directory didn't exist, false if rmdir failed.
 */
function folderDelete2(string $dir, bool $rmdir = true): bool
{
    if (!is_dir($dir)) {
        return true; // nothing to do
    }

    try {
        $it = new \RecursiveDirectoryIterator(
            $dir,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO,
        );
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            // Delete links and regular files without resolving outside the tree
            if ($file->isLink() || $file->isFile()) {
                if (!@unlink($file->getPathname())) {
                    return false;
                }
                continue;
            }
            if ($file->isDir()) {
                if (!@rmdir($file->getPathname())) {
                    return false;
                }
            }
        }
    } catch (\Throwable $e) {
        return false; // unreadable path or iterator error
    }

    return $rmdir ? @rmdir($dir) : true;
}


/**
 * Gets a list of files in specified directories, optionally filtered by extensions.
 * Formats the output similarly to a Heurist search result list.
 * Used for tasks like finding entity configuration files or browsing icon libraries.
 *
 * @param string|array $dirs A single directory path or an array of directory paths to scan.
 * @param string|array|null $exts Optional. A single file extension or an array of extensions to filter by (e.g., 'cfg', ['jpg', 'png']).
 *                                If null, all files are included.
 * @param bool $include_dates Optional. If true, attempts to parse dates from filenames (e.g., "file.sql.bz2.YYYY-MM-DD")
 *                            and adjust the extension accordingly. Defaults to false.
 * @return array An array structured like a Heurist search result, containing file details.
 *               Keys: 'pageno', 'offset', 'count', 'reccount', 'fields', 'records', 'order', 'entityName'.
 * @todo Implement $is_reqursive parameter for recursive scanning.
 */
function folderContent($dirs, $exts = null, $include_dates = false)
{

    $records = [];
    $order = [];
    $fields = ['file_id', 'file_name', 'file_dir', 'file_url', 'file_size'];
    $idx = 1;
    if (!is_array($dirs)) {
        $dirs = [$dirs];
    }
    if ($exts != null && !is_array($exts)) {
        $exts = [$exts];
    }

    foreach ($dirs as $dir) {

        $dir = USanitize::sanitizePath($dir);
        if (substr($dir, -1, 1) != '/') {
            $dir .= '/';
        }


        if (!defined('HEURIST_FILESTORE_ROOT') || strpos($dir, HEURIST_FILESTORE_ROOT) !== false) {
            //in database filestore
            $folder = $dir;
            $url = null;
        } elseif (strpos($dir, '/srv/BACKUP') === 0) {  //in /srv/BACKUP
            $folder = $dir;
            $url = null;
        } else {
            //relative to heurist folder
            $folder =  HEURIST_DIR . $dir;
            $url = HEURIST_BASE_URL . $dir;
        }

        if (!(file_exists($folder) && is_dir($folder))) {
            continue;
        }


        $files = scandir($folder);
        foreach ($files as $filename) {

            $fullPath = "{$folder}{$filename}";
            $path_parts = pathinfo($filename);
            if (in_array('folders', $exts) && is_dir($fullPath) && $filename != '.' && $filename != '..') {

                $fsize = folderSize($fullPath);

                $records[$idx] = [$idx, $filename, $folder, $url, $fsize];
                $order[] = $idx;
                $idx++;

            } elseif (array_key_exists('extension', $path_parts)) {

                $ext = strtolower($path_parts['extension']);
                if ($include_dates && (strlen($ext) == 10) && (DateTime::createFromFormat('Y-m-d', $ext) !== false)) {
                    $fname = substr($filename, 0, -11);
                    $path_parts = pathinfo($fname);
                    if (array_key_exists('extension', $path_parts)) {
                        $ext = strtolower($path_parts['extension']);
                    }
                }

                if (file_exists($fullPath) && ($exts == null || in_array($ext, $exts))) {
                    $fsize = (is_file($fullPath)) ? filesize($fullPath) : 0;

                    $records[$idx] = [$idx, $filename, $folder, $url, $fsize];
                    $order[] = $idx;
                    $idx++;
                }
            }
        }//for
    }


    $response = [
        'pageno' => 0,  //page number to sync
        'offset' => 0,
        'count' => count($records),
        'reccount' => count($records),
        'fields' => $fields,
        'records' => $records,
        'order' => $order,
        'entityName' => 'files'];

    return $response;

}

/**
 * Calculates the total size of all files within a directory (recursively).
 * This is an alternative implementation of folderSize.
 *
 * @param string $dir The path to the directory.
 * @return int The total size of files in the directory in bytes. Returns 0 if the directory is not valid.
 */
function folderSize2($dir)
{

    $size = 0;

    $dir = realpath($dir);

    if ($dir !== false && file_exists($dir) && is_dir($dir) === true) {

        $arr = glob(rtrim($dir, '/') . '/*', GLOB_NOSORT);
        foreach ($arr as $each) {
            $size += is_file($each) ? filesize($each) : folderSize($each);
        }

    }

    return $size;
}

/**
 * Calculates the total size of a directory or a file.
 * For directories, it attempts to use system commands (`du`) for efficiency on non-Windows systems,
 * or COM objects on Windows. Falls back to recursive PHP iteration if system calls fail.
 *
 * @param string $dir The path to the directory or file.
 * @return int The total size in bytes.
 */
function folderSize($dir)
{
    $dir = rtrim(str_replace('\\', '/', $dir), '/');

    if (file_exists($dir) && (is_dir($dir) === true)) {

        $totalSize = 0;

        $dir = USanitize::sanitizePath($dir);

        $dir = realpath($dir);

        if ($dir !== false) {

            $os        = strtoupper(substr(PHP_OS, 0, 3));
            // If on a Unix Host (Linux, Mac OS)
            if ($os !== 'WIN') {
                $cmd = escapeshellcmd('/usr/bin/du -sb ' . $dir);
                $io = popen($cmd, 'r');
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
            // If System calls did't work, use slower PHP
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {

                if (!$file->isDir()) {
                    $totalSize += $file->getSize();
                }
            }
        }
        return $totalSize;
    } elseif (is_file($dir) === true) {
        return filesize($dir);
    }
}

/**
 * Finds the first file in a directory, optionally matching a specific extension and recursing into subdirectories.
 *
 * @param string $dir The path to the directory to search.
 * @param string|null $ext Optional. The file extension to filter by (e.g., 'jpg'). If null, the first file found is returned.
 * @param bool $recursion Optional. If true (default), searches recursively into subdirectories.
 * @return string|null The full path to the first matching file found, or null if no file is found or directory is invalid.
 */
function folderFirstFile($dir, $ext = null, $recursion = true)
{
    $dir = realpath($dir);

    if ($dir !== false) {

        $dirs = scandir($dir);
        foreach ($dirs as $node) {
            if (($node == '.') || ($node == '..')) {
                continue;
            }
            $file = $dir . '/' . $node;
            if (is_dir($file)) {
                if ($recursion) {
                    return folderFirstFile($file, $ext, $recursion);
                }
            } else {
                if ($ext != null) {
                    $path_parts = pathinfo($file);
                    if (array_key_exists('extension', $path_parts)) {
                        $fext = strtolower($path_parts['extension']);
                        if ($ext == $fext) {
                            return $file;
                        }
                    }
                } else {
                    return $file;
                }
            }
        }
    }

    return null;
}

/**
 * Returns an array of subfolder names within a given directory.
 *
 * @param string $dir The path to the directory to scan.
 * @return array An array of subfolder names. Returns an empty array if the directory is invalid or has no subfolders.
 */
function folderGetSubFolders($dir)
{
    $dir = realpath($dir);

    $res = [];

    if ($dir !== false) {

        $dirs = scandir($dir);
        foreach ($dirs as $node) {
            if (($node == '.') || ($node == '..')) {
                continue;
            }
            $file = $dir . '/' . $node;
            if (is_dir($file)) {
                $res[] = $node;
            }
        }
    }

    return $res;
}

/**
 * Creates a tree-structured array of directories and files from a given root folder.
 *
 * @param string|null $dir The root directory path. Defaults to HEURIST_FILESTORE_DIR if null. Can also be a DirectoryIterator object.
 * @param array $params An associative array of parameters:
 *                      'withFiles' (bool): False to exclude files, true to include. Default false.
 *                      'regex' (string): A regex to filter file names. Default empty (no filter).
 *                      'ignoreEmtpty' (bool): True to not include empty folders in the tree. Default false.
 *                      'systemFolders' (array|null): An array keyed by folder names to mark them as system folders.
 *                      'format' (string): If 'fancy', formats output for FancyTree with 'key', 'title', 'folder', 'issystem', 'children', 'files_count'.
 *                                       Otherwise, returns a nested associative array structure.
 * @return array The tree structure. If 'format' is 'fancy', returns `['children' => ..., 'count' => ...]`.
 *               Otherwise, returns a nested array where keys are folder names and values are their subtrees or arrays of file names.
 */
function folderTree($dir, $params)
{
    if ($dir == null) {
        $dir = HEURIST_FILESTORE_DIR;
    }

    if (!$dir instanceof DirectoryIterator) {
        $dir = new DirectoryIterator((string) $dir);
    }
    $dirs  = [];
    $files = [];
    $file_count = 0;

    $withFiles = (@$params['withFiles'] == true);
    $regex = @$params['regex'];
    $ignoreEmpty = (@$params['ignoreEmtpty'] == true);
    $systemFolders = @$params['systemFolders'];
    $isFancy = (@$params['format'] == 'fancy');
    if (is_array($params)) {
        $params['systemFolders'] = null;
    } //use on first level only
    if ($regex == null) {
        $regex = '';
    }

    $fancytree = [];

    foreach ($dir as $node) {
        if ($node->isDir() && !$node->isDot()) {

            $folder_name = $node->getFilename();
            $is_system = (@$systemFolders[$folder_name] != null);
            //(@$params['is_system']==true) ||
            //$params['is_system'] = $is_system;

            $tree = folderTree($node->getPathname(), $params);
            if (!$ignoreEmpty || count($tree)) {

                if ($isFancy) {
                    $arr = [ 'key' => $folder_name, 'title' => $folder_name,
                        'folder' => true, 'issystem' => $is_system,
                        'children' => $tree['children'], 'files_count' => $tree['count'] ];

                    if ($is_system) {
                        $arr['unselectable'] = true;
                        $arr['unselectableStatus'] = false;
                    }

                    $fancytree[] = $arr;

                } else {
                    $dirs[$folder_name] = $tree;
                }
            }

        } elseif ($node->isFile()) {
            if ($withFiles) {
                $name = $node->getFilename();
                if ('' == $regex || preg_match($regex, $name)) { //file filter
                    $files[] = $name;
                    $fancytree[] = [ 'key' => $name, 'title' => $name];
                }
            }
            $file_count++;
        }
    }

    if ($isFancy) {
        usort($fancytree, "__cmpTitleInTree");
        return ['children' => $fancytree, 'count' => $file_count];
    } else {
        asort($dirs);
        sort($files);
        return array_merge($dirs, $files);
    }
}


function __cmpTitleInTree($a, $b)
{
    if ($a['title'] == $b['title']) {
        return 0;
    }
    $ret = (strtolower($a['title']) < strtolower($b['title'])) ? -1 : 1;
    return $ret;
}

/**
 * Converts a nested array structure (as returned by folderTree without 'fancy' format)
 * into a format suitable for the FancyTree jQuery plugin.
 * NOTE: This function is marked as NOT USED in the original source code comments.
 *
 * @param array $data The nested array data representing the folder structure.
 * @param int $lvl Current recursion level (internal use).
 * @param array|null $sysfolders Optional. Array to mark system folders.
 * @return array An array formatted for FancyTree.
 */
function folderTreeToFancyTree($data, $lvl = 0, $sysfolders = null)
{
    //for fancytree
    $fancytree = [];
    foreach ($data as $folder => $children) {

        $item = [ 'key' => $folder, 'title' => $folder,
            'folder' => ($folder >= 0), 'issystem' => (@$sysfolders[$folder] != null) ];

        if (!isEmptyArray($children)) {
            $item['children'] = folderTreeToFancyTree($children, $lvl + 1);
        }
        $fancytree[] = $item;
    }
    usort($fancytree, "__cmpTitleInTree");
    return $fancytree;

}



/**
 * Checks file existence, readability, and opens it for binary reading.
 *
 * @param string $file The path to the file.
 * @return resource|int Returns a file handle resource on success.
 *                      Returns -1 if the file does not exist or is not a file.
 *                      Returns -2 if the file is not readable.
 *                      Returns -3 if fopen fails.
 */
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

/**
 * Copies a file from source to destination.
 * Creates the destination directory if it doesn't exist.
 *
 * @param string $s1 Source file path.
 * @param string $s2 Destination file path.
 * @return bool True on successful copy, false otherwise.
 */
function fileCopy($s1, $s2)
{
    $path = pathinfo($s2);

    if (folderCreate($path['dirname'], true)) {
        if (!copy($s1, $s2)) {
            // "copy failed";
            return false;
        }
    } else {
        //can't create folder or it is not writeable
        return false;
    }
    return true;
}

/**
 * Deletes a file if it exists.
 *
 * @param string $filename The path to the file to delete.
 * @return void
 */
function fileDelete($filename)
{
    if (!empty($filename) && file_exists($filename)) {
        unlink($filename);
    }
}

/**
 * Saves raw data to a file. If the file exists, it's deleted first.
 * Used for saving system version or temporary remote content.
 *
 * @param string $rawdata The data to save.
 * @param string $filename The path to the file.
 * @return int|false The number of bytes written to the file on success, or 0 if $rawdata is empty or $filename is not a string.
 */
function fileSave($rawdata, $filename)
{
    if (!empty($rawdata) && is_string($filename)) {
        fileDelete($filename);
        $fp = fopen($filename, 'x');
        fwrite($fp, $rawdata);
        fclose($fp);

        return filesize($filename);
    } else {
        return 0;
    }
}
/**
 * Appends raw data to the end of a file.
 *
 * @param string $rawdata The data to append.
 * @param string $filename The path to the file.
 * @return int|false The new file size after appending, or 0 if $rawdata is empty.
 */
function fileAdd($rawdata, $filename)
{
    if ($rawdata) {
        try {
            $fp = fopen($filename, 'a');//open for add
            if ($fp === false) {
                // 'Cannot open file '.$filename
            } else {
                fwrite($fp, $rawdata);
                fclose($fp);
            }

        } catch (\Exception  $e) {
            // Cannot open file '.$filename.'  Error:'.$e->getMessage()
        }

        return filesize($filename);
    } else {
        return 0;
    }
}

/**
 * Generates a unique filename in a given directory by appending a counter if a file with the same name already exists.
 * Example: if "file.txt" exists, it will try "file(1).txt", then "file(2).txt", and so on.
 *
 * @param string $folder The directory path where the file should be unique.
 * @param string $filename The base name of the file (without extension as it's extracted via pathinfo).
 * @param string $ext The file extension (e.g., ".txt" or "txt").
 * @return string The full path to a unique filename.
 */
function getUniqueFileName($folder, $filename, $ext)
{

    $path_parts = pathinfo($filename);
    $filename = $path_parts['filename'];
    if (strpos($ext, '.') == false) {
        $ext = '.' . $ext;
    }

    $file_fullpath = $folder . $filename . $ext;

    $k = strpos($filename, '(');
    $k2 = strpos($filename, ')');
    if ($k > 0 && $k2 > 0) {
        $cnt = intval(substr($filename, $k + 1, $k2 - $k));
        $filename = substr($filename, 0, $k);
    } else {
        $cnt = 0;
    }

    do {
        if (file_exists($file_fullpath)) {
            $cnt++;
            $file_fullpath = $folder . $filename . "($cnt)$ext";
        }
    } while (file_exists($file_fullpath));

    return $file_fullpath;
}



/**
 * Returns the target path as relative reference from the base path.
 *
 * Only the URIs path component (no schema, host etc.) is relevant and must be given, starting with a slash.
 * Both paths must be absolute and not contain relative parts.
 *
 * @param string $basePath   The base path
 * @param string $targetPath The target path
 *
 * @return string The relative target path
 */
/*  does not work
    function getRelativePath($basePath, $targetPath)
    {
   // Normalize path separators for cross-platform compatibility
   $basePath = str_replace('\\', '/', rtrim($basePath, '/'));
   $targetPath = str_replace('\\', '/', rtrim($targetPath, '/'));

   // If both paths are identical, return an empty string (they are the same)
   if ($basePath === $targetPath) {
       return '';
   }

   // Split both paths into their individual components
   $baseDirs = explode('/', ltrim($basePath, '/'));
   $targetDirs = explode('/', ltrim($targetPath, '/'));

   // Remove identical segments from the start of both paths
   while (isset($baseDirs[0], $targetDirs[0]) && $baseDirs[0] === $targetDirs[0]) {
       array_shift($baseDirs);
       array_shift($targetDirs);
   }

   // Build the relative path by going up for each remaining base directory
   $relativePath = str_repeat('../', count($baseDirs)) . implode('/', $targetDirs);

   // If the relative path is empty (pointing to the same directory), return './'
   if ($relativePath === '') {
       return './';
   }

   // Special case: ensure the result does not start with a schema-like structure (e.g., "file:colon")
   if (strpos($relativePath, ':') !== false && strpos($relativePath, '/') === false) {
       return './' . $relativePath;
   }

   return $relativePath;
    }
    */

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

    //add last one
    if (substr($targetPath, -1, 1) != '/') {
        $targetPath = $targetPath . '/';
    }

    if ($basePath === $targetPath) {
        return '';
    } elseif (substr($targetPath, 0, 1) != '/' && !preg_match('/^[A-Z]:/i', $targetPath)) { //it is already relative
        return $targetPath;
    }

    $baseDirs = explode('/', ltrim($basePath, '/'));
    $targetDirs = explode('/', ltrim($targetPath, '/'));
    array_pop($baseDirs);
    $targetFile = array_pop($targetDirs);

    // Remove identical segments from the start of both paths
    while (isset($baseDirs[0], $targetDirs[0]) && $baseDirs[0] === $targetDirs[0]) {
        array_shift($baseDirs);
        array_shift($targetDirs);
    }/*
        foreach ($baseDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($baseDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }*/

    $targetDirs[] = $targetFile;
    $path = str_repeat('../', count($baseDirs)) . implode('/', $targetDirs);

    // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
    // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
    // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
    // (see http://tools.ietf.org/html/rfc3986#section-4.2).
    return '' === $path || '/' === $path[0]
        || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
        ? './' . $path : $path;
}



/**
* copy folder recursively
*
* @param mixed $src
* @param mixed $dst
* @param array $folders - folders to copy (first level only)
*/
function folderRecurseCopy($src, $dst, $folders = null, $copy_files_in_root = true)
{
    $res = false;

    $src =  $src . ((substr($src, -1) == '/') ? '' : '/');

    $dir = opendir($src);
    if ($dir !== false) {

        if (file_exists($dst) || @mkdir($dst, 0777, true)) {

            $res = true;

            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($src . $file)) {

                        if (isEmptyArray($folders) || in_array($src . $file . '/', $folders)) {
                            $res = folderRecurseCopy($src . $file, $dst . '/' . $file, null, true);
                            if (!$res) {
                                break;
                            }
                        }

                    } elseif ($copy_files_in_root) {
                        copy($src . $file, $dst . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);

    }

    return $res;
}


/**
* Gets list of subfolders for given folder
*
* @param mixed $src
*/
function folderSubs($src, $exclude = null, $full_path = true)
{
    $res = [];

    $src =  $src . ((substr($src, -1) == '/') ? '' : '/');

    if (file_exists($src)) {

        $dir = opendir($src);
        if ($dir !== false) {


            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..') && is_dir($src . $file)) {

                    if (is_array($exclude) && in_array($file, $exclude)) {
                        continue;
                    }

                    if ($full_path) {
                        $res[] = $src . $file . '/';
                    } else {
                        $res[] = $file;
                    }

                }
            }
            closedir($dir);
        }
    }

    return $res;
}

//------------------------------------------
//
// Returns false if given folder is not in heurist upload folder
// Returns null  if given folder does not exist
// Otherwise return real path
//
function isPathInHeuristUploadFolder($path, $check_existance = true)
{

    chdir(HEURIST_FILESTORE_DIR);// relatively db root  or HEURIST_FILES_DIR??
    $heurist_dir = realpath(HEURIST_FILESTORE_DIR);
    $r_path = realpath($path);

    if ($check_existance && !$r_path) { //does not exist
        return null;
    }

    if ($r_path) {
        $r_path = str_replace('\\', '/', $r_path);
        $heurist_dir = str_replace('\\', '/', $heurist_dir);

        //realpath gives real path on remote file server
        if (strpos($r_path, '/srv/HEURIST_FILESTORE/') === 0
           || strpos($r_path, '/misc/heur-filestore/') === 0     //heurx
           || strpos($r_path, '/data/HEURIST_FILESTORE/') === 0  //huma-num
           || strpos($r_path, $heurist_dir) === 0) {
            return $r_path;
        }
    } elseif (strpos($path, HEURIST_FILESTORE_DIR) === 0) {
        return $path;
    }

    return false;
}


//-----------------------  LOAD REMOTE CONTENT (CURL) --------------------------


/**
     * Saves content from a remote URL to a local file.
     * Uses `loadRemoteURLContent` to fetch data. Data is saved in the scratch folder as a temporary file.
     * Primarily used for saving imports from other databases or remote images for thumbnail generation.
     *
     * @param string $url The URL to fetch content from.
     * @param string $filename The local path to save the file to.
     * @return int The size of the saved file in bytes, or 0 if fetching or saving fails.
     */
function saveURLasFile($url, $filename)
{
    //Download file from remote server
    $rawdata = loadRemoteURLContent($url, false);//use proxy
    if (is_string($rawdata)) {
        return fileSave($rawdata, $filename);//returns file size
    } else {
        //error_log('Can not access remote resource '.filter_var($url,FILTER_SANITIZE_URL)); //snyk security
        return 0;
    }
}

/**
     * Fetches the content of a remote URL and extracts the HTML title tag.
     *
     * @param string $url The URL of the HTML document.
     * @return string|null The extracted title string, or null if not found or on error.
     */
function getTitleFromURL($url)
{

    $title = null;

    $url = str_replace(' ', '+', $url);

    $data = loadRemoteURLContentWithRange($url, "0-10000");//get title of webpage

    if ($data) {

        // "/<title>(.*)<\/title>/siU"
        preg_match('!<\s*title[^>]*>\s*([^<]+?)\s*</title>!is', $data, $matches);
        if ($matches) {
            // Clean up title: remove EOL's and excessive whitespace.
            $title = preg_replace('/\s+/', ' ', $matches[1]);
            $title = trim($title);
        }
    }

    return $title;
}

/**
 * Loads content from a URL, with special handling for URLs on the same Heurist server.
 * If the URL is local to the server, it attempts to include and execute the script directly
 * instead of making an HTTP request via cURL. Otherwise, falls back to `loadRemoteURLContentWithRange`.
 * Used for fetching registered database URLs, database registration, and getting current DB version.
 *
 * @param string $url The URL to load content from.
 * @return string|false The content fetched from the URL, or false on failure.
 */
function loadRemoteURLContentSpecial($url)
{

    if (strpos($url, HEURIST_SERVER_URL) === 0) {

        //if requested url is on the same server
        //replace URL to script path in current installation folder
        //and execute script
        if (strpos(strtolower($url), strtolower(HEURIST_INDEX_BASE_URL)) === 0) {
            $path = str_replace(HEURIST_INDEX_BASE_URL, HEURIST_DIR, $url);
        } else {
            $path = str_replace(HEURIST_BASE_URL, HEURIST_DIR, $url);
        }

        $path = substr($path, 0, strpos($path, '?'));

        $parsed = parse_url($url);
        parse_str($parsed['query'], $_REQUEST);

        $out = getScriptOutput($path);

        return $out;
    } else {
        return loadRemoteURLContentWithRange($url, null, true);
    }
}

/**
 * Loads content from a remote URL using cURL. This is a wrapper for `loadRemoteURLContentWithRange` without a specific byte range.
 *
 * @global int|string|null $glb_curl_code Stores cURL error code or status.
 * @global string|null $glb_curl_error Stores cURL error message.
 * @param string $url The URL to fetch content from.
 * @param bool $bypassProxy Optional. If true, attempts to bypass any configured HTTP proxy. Defaults to true.
 * @return string|false The fetched content as a string on success, or false on failure.
 */
function loadRemoteURLContent($url, $bypassProxy = true)
{
    return loadRemoteURLContentWithRange($url, null, $bypassProxy);
}

/**
 * Loads content from a remote URL using cURL, with options for range requests, proxy bypass, and timeout.
 * Updates global variables $glb_curl_code and $glb_curl_error with cURL status.
 *
 * @global int|string|null $glb_curl_code Stores cURL error code or status.
 * @global string|null $glb_curl_error Stores cURL error message.
 * @param string $url The URL to fetch content from.
 * @param string|null $range Optional. A specific byte range to fetch (e.g., "0-500"). Null to fetch entire content.
 * @param bool $bypassProxy Optional. If true, attempts to bypass any configured HTTP proxy. Defaults to true.
 * @param int $timeout Optional. cURL timeout in seconds. Defaults to 30.
 * @param array|null $additional_headers Optional. Additional HTTP headers to send with the request.
 * @param bool $includeContentType Optional. Whether to include the content type with the response
 * @return array|string|false The fetched content as a string (or array if includeContentType is true) on success, or false on failure.
 */
function loadRemoteURLContentWithRange($url, $range, $bypassProxy = true, $timeout = 30, $additional_headers = null, $includeContentType = false)
{

    global $glb_curl_code, $glb_curl_error;

    $glb_curl_code = null;
    $glb_curl_error = null;

    if (!function_exists("curl_init")) {

        $glb_curl_code = HEURIST_SYSTEM_FATAL;
        $glb_curl_error = 'Cannot init curl extension. Verify php installation';

        return false;
    }

    $url = filter_var($url, FILTER_VALIDATE_URL);
    if (empty($url) || $url === false) {
        $glb_curl_code = HEURIST_INVALID_REQUEST;
        $glb_curl_error = 'URL is not defined or invalid';

        return false;
    }

    if (!(strpos(strtolower($url), 'https://') === 0 || strpos(strtolower($url), 'http://') === 0)) {
        $glb_curl_code = HEURIST_INVALID_REQUEST;
        $glb_curl_error = 'URL is not started with a trusted scheme';
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
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//return the output as a string from curl_exec
    curl_setopt($ch, CURLOPT_NOBODY, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);//don't include header in output
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);// follow server header redirects
    //Vulnerability curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);// don't verify peer cert
    if (strpos(strtolower($url), strtolower(HEURIST_MAIN_SERVER)) === 0) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);// timeout after ten seconds
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);// no more than 5 redirections

    curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    //curl_setopt($ch, CURLOPT_REFERER, HEURIST_SERVER_URL);

    if ($range) {
        curl_setopt($ch, CURLOPT_RANGE, $range);
    }

    // check if the proxy needs to be used, $httpProxyActive defined in heuristConfigIni.php
    if (defined('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE') && HEURIST_HTTP_PROXY_ALWAYS_ACTIVE) {
        $bypassProxy = false;
    }

    if ((!$bypassProxy) && defined('HEURIST_HTTP_PROXY')) {
        curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if (defined('HEURIST_HTTP_PROXY_AUTH')) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }

    if (is_array($additional_headers) && !empty($additional_headers)) { // Add additional/custom headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $additional_headers);
        //curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:'));
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    $data = curl_exec($ch);

    $error = curl_error($ch);

    if ($error) {

        $glb_curl_code = 'curl';
        $glb_curl_error = $error;

        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

        if (strpos($glb_curl_error, $code) !== false) { // http error
            $glb_curl_error = explode(': ', $glb_curl_error)[1];
            $glb_curl_error = 'Error Code : ' . $error;
        }

        unset($ch);
        return false;
    } else {
        if (!$data) {
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

            $glb_curl_code = HEURIST_SYSTEM_FATAL;
            $glb_curl_error = 'HTTP Response Code: ' . $code;
        }

        $data = $includeContentType ? ['data' => $data, 'type' => curl_getinfo($ch, CURLINFO_CONTENT_TYPE)] : $data;

        unset($ch);

        return $data;
    }
}

/**
 * Detects the MIME content type of a remote URL using cURL by fetching its headers.
 *
 * @param string $url The URL to check.
 * @param bool $bypassProxy Optional. If true, attempts to bypass any configured HTTP proxy. Defaults to true.
 * @param int $timeout Optional. cURL timeout in seconds. Defaults to 30.
 * @param array|null $headers Optional. Additional headers to set, e.g. API key
 * @return string|false The MIME content type string on success, or false on failure or if URL is invalid.
 */
function loadRemoteURLContentType($url, $bypassProxy = true, $timeout = 30, $headers = null)
{

    if (!function_exists("curl_init")) {
        return false;
    }
    if (!$url) {
        return false;
    }

    if (!(strpos(strtolower($url), 'https://') === 0 || strpos(strtolower($url), 'http://') === 0)) {
        return false;
    }

    $content_type = false;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);// timeout after ten seconds
    //Vulnerability curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($ch, CURLOPT_URL, $url);

    // check if the proxy needs to be used, $httpProxyActive defined in heuristConfigIni.php
    if (defined('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE') && HEURIST_HTTP_PROXY_ALWAYS_ACTIVE) {
        $bypassProxy = false;
    }

    if ((!$bypassProxy) && defined('HEURIST_HTTP_PROXY')) {
        curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if (defined('HEURIST_HTTP_PROXY_AUTH')) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }

    if (is_array($headers) && !empty($headers)) { // Add additional/custom headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $data = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        USanitize::errorLog('CURL ERROR: http code = ' . $code . '  curl error=' . $error);
    } else {
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    }
    unset($ch);

    return $content_type;
}

/**
 * Extracts the file extension from a URL's path component.
 *
 * @param string $url The URL to parse.
 * @return string|null The file extension in lowercase, or null if no path or extension is found.
 */
function getURLExtension($url)
{
    $extension = null;
    $ap = parse_url($url);
    if (array_key_exists('path', $ap)) {
        $path = $ap['path'];
        if ($path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        }
    }
    return $extension;
}

/**
 * Retrieve and prepare details about Wikimedia hosted files
 *
 * @param string $url Wikipedia/Wikimedia URL to a file
 * @return array|array{page: string, mimeType: string, url: string, copyright: string, description: string, copyowner: string, caption: string}
 */
function getWikimediaFileType($url)
{

    if (!filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'wikipedia.org') === false && strpos($url, 'wikimedia.org') === false) {
        return [];
    }
    $url = filter_var($url, FILTER_SANITIZE_URL);

    $wikimediaParams = [
        'action' => 'query',
        'titles' => '',
        'prop' => 'imageinfo',
        'iiprop' => 'mime|url|user|extmetadata',
        'iiextmetadatafilter' => 'LicenseUrl|LicenseShortName|ImageDescription|Artist|ObjectName|DateTimeOriginal', // limit extended metadata
        'format' => 'json',
    ];

    $urlPath = parse_url($url, PHP_URL_PATH);
    $urlPath = explode('/', $urlPath);
    foreach ($urlPath as $pathPart) {

        if (empty($pathPart)) {
            continue;
        }

        if (strpos($pathPart, 'File:') !== false) {
            $wikimediaParams['titles'] = $pathPart;
            break;
        } elseif (preg_match("/\.[A-Za-z]{2,4}$/", $pathPart)) {
            $wikimediaParams['titles'] = "File:{$pathPart}";
            break;
        }
    }

    if (empty($wikimediaParams['titles'])) {
        return [];
    }

    $wikimediaURL = 'https://commons.wikimedia.org/w/api.php?';

    $wikimediaURL .= http_build_query($wikimediaParams);

    $response = loadRemoteURLContent($wikimediaURL);

    $jsonResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($jsonResponse)) {
        return [];
    }

    $results = [];
    $pages = $jsonResponse['query']['pages'];
    foreach ($pages as $page) {

        $imageInfo = $page['imageinfo'][0];
        $metadata = $imageInfo['extmetadata'];

        $results['page'] = $imageInfo['descriptionshorturl'];
        $results['mimeType'] = $imageInfo['mime'];
        $results['url'] = $imageInfo['url'];

        $results['copyright'] = '';
        $ccLicense = array_key_exists('LicenseUrl', $metadata) ? $metadata['LicenseUrl']['value'] : '';
        $ccType = array_key_exists('LicenseShortName', $metadata) ? $metadata['LicenseShortName']['value'] : '';

        $results['copyright'] = $ccLicense !== '' && $ccType !== '' ? "<a href=\"{$ccLicense}\" target=\"_blank\">{$ccType} ({$ccLicense})</a>" : '';
        $results['copyright'] = $results['copyright'] !== '' && $ccLicense !== '' ? "<a href=\"{$ccLicense}\" target=\"_blank\">{$ccType} ({$ccLicense})</a>" : $results['copyright'];
        $results['copyright'] = $results['copyright'] !== '' && $ccType !== '' ? $ccType : $results['copyright'];

        $results['description'] = array_key_exists('ImageDescription', $metadata) ? $metadata['ImageDescription']['value'] : '';

        $results['copyowner'] = array_key_exists('Artist', $metadata) ? $metadata['Artist']['value'] : '';

        $results['caption'] = '';
        $fileName = array_key_exists('ObjectName', $metadata) ? $metadata['ObjectName']['value'] : '';
        $date = array_key_exists('DateTimeOriginal', $metadata) ? " @ {$metadata['DateTimeOriginal']['value']}" : '';
        $date = $fileName === '' ? "Uploaded{$date}" : $date;
        $results['caption'] = $fileName !== '' ? $fileName : '';
        $results['caption'] .= $date !== '' ? $date : '';
        $results['caption'] = !empty($results['page']) ? "<a href=\"{$results['page']}\" target=\"_blank\">{$results['caption']}</a>" : $results['caption'];
    }

    return $results;
}

/**
 * Recognizes the MIME type from a URL, updates the `defFileExtToMimetype` table if a new mapping is found
 * (especially for known services like YouTube, Vimeo, SoundCloud), and returns the determined file extension.
 *
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param string $url The URL to analyze.
 * @param bool $use_default_ext Optional. If true (default) and no specific extension can be determined,
 *                              returns 'bin' as a generic binary extension.
 * @param array|null $headers Optional. Additional headers that are required, e.g. API Keys
 * @return array An associative array with 'extension' (string|null), 'mimeType' (string|null), and 'needrefresh' (bool).
 */
function recognizeMimeTypeFromURL($mysqli, $url, $use_default_ext = true, $headers = null)
{

    $url = filter_var($url, FILTER_SANITIZE_URL);

    //special cases for well known resources
    $force_add = null;
    $extension = null;
    $needrefresh = false;
    $mimeType = null;
    $extraDetails = [];

    if (strpos($url, 'soundcloud.com') !== false) {
        $mimeType  = MT_SOUNDCLOUD;
        $extension = 'soundcloud';
        $force_add = "('soundcloud','" . MT_SOUNDCLOUD . "', '0','','Soundcloud','')";
    } elseif (strpos($url, 'vimeo.com') !== false) {
        $mimeType  = MT_VIMEO;
        $extension = 'vimeo';
        $force_add = "('vimeo','" . MT_VIMEO . "', '0','','Vimeo Video','')";
    } elseif (strpos($url, 'youtu.be') !== false || strpos($url, 'youtube.com') !== false) {
        $mimeType  = MT_YOUTUBE;
        $extension = 'youtube';
        $force_add = "('youtube','" . MT_YOUTUBE . "', '0','','Youtube Video','')";
    } elseif (strpos($url, 'wikipedia.org') !== false || strpos($url, 'wikimedia.org') !== false) {
        $extraDetails = getWikimediaFileType($url);
        $mimeType = @$extraDetails['mimeType'];
    } else {
        $mimeType = loadRemoteURLContentType($url, true, 30, $headers);
    }


    if ($mimeType != null && $mimeType !== false) {

        //remove charset section
        if (strpos($mimeType, ';') > 0) {
            $parts = explode(';', $mimeType);
            $k = 0;
            while ($k < count($parts)) {
                if (strpos($parts[$k], 'charset') !== false) {
                    array_splice($parts, $k, 1);
                    //unset($parts[$k]);
                } else {
                    $k++;
                }
            }//while
            $mimeType = @$parts[0];
        }

        if ($mimeType) {

            if ($mimeType == MIMETYPE_JSON ||  $mimeType == 'application/ld+json') {
                $mimeType = MIMETYPE_JSON;
                $extension = 'json';
                $force_add = "('json','application/json', '0','','JSON','')";
            }

            $ext_query = 'SELECT fxm_Extension FROM defFileExtToMimetype WHERE fxm_MimeType="'
                        . $mimeType . '"';
            $f_extension = mysql__select_value($mysqli, $ext_query);

            if ($f_extension == null && $force_add != null) {
                $mysqli->query('insert into defFileExtToMimetype ('
    . '`fxm_Extension`,`fxm_MimeType`,`fxm_OpenNewWindow`,`fxm_IconFileName`,`fxm_FiletypeName`,`fxm_ImagePlaceholder`'
                . ') values ' . $force_add);
                $needrefresh = true;
            } else {
                $extension = $f_extension;
            }

        }
    }
    //if extension not found apply bin: application/octet-stream - generic mime type
    if ($extension == null && $use_default_ext) {
        $extension = 'bin';
    }

    $res = ['extension' => $extension, 'mimeType' => $mimeType, 'needrefresh' => $needrefresh];
    if (!empty($extraDetails)) {
        $res['details'] = $extraDetails;
    }

    return $res;
}




//----------------------------------
/**
 * Captures the output of a PHP script by including it within an output buffer.
 *
 * @global \hserv\System $system The global system object (though not directly used in this function's logic, it's declared global).
 * @param string $path The path to the PHP script to execute.
 * @param bool $print Optional. If false (default), returns the captured output as a string.
 *                    If true, echoes the output directly and returns nothing.
 * @return string|false|void If $print is false, returns the script's output as a string, or false if the script is not readable.
 *                           If $print is true, echoes output and returns void (implicitly null).
 */
function getScriptOutput($path, $print = false)
{
    global $system;

    ob_start();

    if (is_readable($path) && $path) {
        include_once $path;
    } else {
        return false;
    }

    if ($print == false) {
        return ob_get_clean();
    } else {
        echo ob_get_clean();
    }
}


//----------------------------------------------- PARSING

/**
 * Auto-detects CSV file delimiters (delimiter, line break, enclosure).
 * Reads the beginning of the file to infer these settings.
 * IMPORTANT: This function assumes the file is UTF-8 encoded for reliable detection.
 *
 * @param string $filename Path to the CSV file.
 * @param string $csv_linebreak Optional. Initial guess or forced line break type ('auto', 'win', 'nix', 'mac'). Defaults to 'auto'.
 * @param string $csv_enclosure Optional. CSV enclosure character. Defaults to '"'. If empty or 'none', a rare character is used internally.
 * @return array An associative array with detected 'csv_linebreak', 'csv_delimiter', 'csv_enclosure',
 *               or an 'error' key if the file cannot be read or is not UTF-8.
 */
function autoDetectSeparators($filename, $csv_linebreak = 'auto', $csv_enclosure = '"')
{

    $handle = @fopen($filename, 'r');
    if (!$handle) {
        $s = null;
        if (! file_exists($filename)) {
            $s = ' does not exist';
        } elseif (! is_readable($filename)) {
            $s = ' is not readable';
        } else {
            $s = ' could not be read';
        }

        if ($s) {
            return ['error' => ('File ' . $filename . $s)];
        }
    }

    //DETECT End of line
    if ($csv_enclosure == '' || $csv_enclosure == 'none') {
        $csv_enclosure = 'ʰ';//rare character
    }

    $eol = null;
    if ($csv_linebreak == 'win') {
        $eol = "\r\n";
    } elseif ($csv_linebreak == 'nix') {
        $eol = "\n";
    } elseif ($csv_linebreak == 'mac') {
        $eol = "\r";
    }

    if ($csv_linebreak == 'auto' || $csv_linebreak == null || $eol == null) {
        ini_set('auto_detect_line_endings', 'true');

        $line = fgets($handle, 1000000);//read line and auto detect line break
        $position = ftell($handle);
        fseek($handle, $position - 5);
        $data = fread($handle, 10);
        rewind($handle);

        if (substr_count($data, "\r\n") > 0) {
            $eol = "\r\n";
        } elseif (substr_count($data, "\n") > 0) {
            $eol = "\n";
        } else {
            $eol = "\r";
        }
    }

    //--------- DETECT FIELD SEPARATOR
    //fgetcsv и str_getcsv depends on server locale
    // it is possible to set it in  /etc/default/locale (Debian) or /etc/sysconfig/i18n (CentOS)  LANG="en_US.UTF-8"
    setlocale(LC_ALL, 'en_US.utf8');


    $delimiters = ["\t" => 0,',' => 0,';' => 0,':' => 0,'|' => 0,'-' => 0];
    $force_tabs = false; // if the first line contains tab separators, default to tabs

    foreach ($delimiters as $csv_delimiter => $val) {
        $line_no = 0;

        while (!feof($handle)) {

            $line = stream_get_line($handle, 1000000, $eol);
            /*
            if(!mb_detect_encoding($line, 'UTF-8', true)){
                fclose($handle);
                return array('error'=>('File '.$filename. ' is not UTF-8. It is not possible to autodetect separators'));
            }
            */

            $fields = str_getcsv($line, $csv_delimiter, $csv_enclosure);// $escape = "\\"

            $cnt = count($fields);
            if ($cnt > 200) { //too many fields
                $delimiters[$csv_delimiter] = 0; //not use
                break;
            } else {
                if ($line_no == 0) {
                    $delimiters[$csv_delimiter] = $cnt;

                    if ($cnt > 0 && $csv_delimiter == "\t") {
                        $force_tabs = true;
                        break 2;
                    }
                } elseif ($delimiters[$csv_delimiter] != $cnt) {
                    $delimiters[$csv_delimiter] = 0; //not use
                    break;
                }
            }

            if ($line_no > 10) {
                break;
            }
            $line_no++;
        }
        rewind($handle);
    }//for delimiters
    fclose($handle);

    if ($force_tabs) {
        $csv_delimiter = "tab";
    } else {

        $max = 0;
        $csv_delimiter = ',';//default
        foreach ($delimiters as $delimiter => $cnt) {
            if ($cnt > $max) {
                $csv_delimiter = $delimiter;
                $max = $cnt;
            }
        }
        if ($csv_delimiter == "\t") {
            $csv_delimiter = "tab";
        }
    }

    if ($eol == "\r\n") {
        $csv_linebreak = 'win';
    } elseif ($eol == "\n") {
        $csv_linebreak = 'nix';
    } elseif ($eol == "\r") {
        $csv_linebreak = 'mac';
    }

    return ['csv_linebreak' => $csv_linebreak, 'csv_delimiter' => $csv_delimiter, 'csv_enclosure' => $csv_enclosure];
}

/**
 * Checks if a file is likely an XML file by reading its first few bytes for an XML declaration.
 *
 * @param string $filename Path to the file.
 * @return bool True if the file starts with '<?xml' (possibly after BOM), false otherwise or if file is unreadable.
 */
function isXMLfile($filename)
{

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

/**
 * Manages a semaphore-like lock file for long-running actions to prevent concurrent execution.
 * Checks if an action is already in progress or updates/creates a timestamp for the action.
 *
 * @param string $action A unique name for the action (e.g., 'backup', 'verify_urls').
 * @param int $range_minutes The duration in minutes. If an existing lock file for the action is older than this,
 *                           it's considered stale and can be overridden. If $range_minutes < 0, the lock file is removed.
 * @param string $db_name Optional. Database name to make the lock specific to a database. Defaults to empty (global lock).
 * @return bool True if the action can proceed (no current lock or lock is stale/removed).
 *              False if an action is currently in progress and the lock is not stale.
 */
function isActionInProgress($action, $range_minutes, $db_name = '')
{

    $progress_flag = HEURIST_FILESTORE_ROOT . '_operation_locks' . ($db_name ? ('_' . $db_name) : '') . '.info';

    //flag that backup in progress
    if (file_exists($progress_flag)) {

        if ($range_minutes < 0) {
            unlink($progress_flag);
            return false;
        }

        $datetime2 = date_create('now');

        $reading = fopen($progress_flag, 'r');
        $writing = fopen('myfile.tmp', 'w');

        $replaced = false;
        $not_allowed = false;

        while (!feof($reading)) {
            $line = fgets($reading);
            if (strpos($line, $action) === 0) {

                $datetime1 = date_create(trim(substr($line, strlen($action))));
                $interval = date_diff($datetime1, $datetime2);

                $allowed = ($interval->format('%y') > 0
                || $interval->format('%m') > 0 || $interval->format('%d') > 0
                || $interval->format('%h') > 0 || $interval->format('%i') > $range_minutes);

                if ($allowed) {
                    $line = $action . ' ' . $datetime2->format(DATE_8601) . "\n";
                    $replaced = true;
                } else {
                    $not_allowed = true;
                    break;
                }
            }
            fputs($writing, $line);
        }
        fclose($reading);
        fclose($writing);
        // might as well not overwrite the file if we didn't replace anything
        if ($replaced) {
            rename('myfile.tmp', $progress_flag);
        } else {
            unlink('myfile.tmp');
        }
        if ($not_allowed) {
            return false;
        }
    } elseif ($range_minutes > 0) {
        $fp = fopen($progress_flag, 'w');
        fwrite($fp, $action . ' ' . date_create('now')->format(DATE_8601));
        fclose($fp);
    }
    return true;
}

/**
 * Uploads a file to the Nakala repository and returns its Nakala URL.
 * Handles API key authentication, file upload, metadata submission, and error checking.
 *
 * @global int|string|null $glb_curl_code Stores cURL error code from Nakala API calls.
 * @global string|null $glb_curl_error Stores cURL error message from Nakala API calls.
 * @param \hserv\System $system The Heurist system instance.
 * @param array $params An associative array of parameters:
 *                      'apiKey' (string): User's Nakala API Key.
 *                      'file' (array): Details of the file to upload:
 *                          'path' (string): Path to the local file.
 *                          'type' (string): MIME type of the file.
 *                          'name' (string): Name of the file.
 *                          'description' (string, optional): Description of the file.
 *                      'meta' (array): Array of Nakala metadata values.
 *                      'status' (string, optional): Status for the Nakala deposit (e.g., 'pending', 'published'). Defaults to 'pending'.
 *                      'returnType' (string, optional): If 'editor', returns URL to private view. Otherwise, public URL.
 * @return array|false An array containing the Nakala URL and DOI on success, or false on failure.
 */
function uploadFileToNakala($system, $params)
{

    if ($params['api_key']) { // just in case
        $params['apiKey'] = $params['api_key'];
    }

    $result = uploadFilesToNakala($system, $params, [$params['file']], $params['meta']);

    if ($result) {
        if ($result['URL'][0] !== '') {
            $result['URL'] = $result['URL'][0];
        } else {
            $system->addError(HEURIST_ACTION_BLOCKED, $result['errors'][0]);
            $result = false;
        }
    }

    return $result;
}

/**
 * @todo: Consider move to class in repoController.php
 * Uploads a bundle of files to the Nakala repository and returns its Nakala URL.
 * Handles API key authentication, file uploads, metadata submission, and error checking.
 *
 * @global int|string|null $glb_curl_code Stores cURL error code from Nakala API calls.
 * @global string|null $glb_curl_error Stores cURL error message from Nakala API calls.
 * @param \hserv\System $system The Heurist system instance.
 * @param array $parameters An associative array of parameters:
 *                      'apiKey' (string): User's Nakala API Key.
 *                      'status' (string, optional): Status for the Nakala deposit (e.g., 'pending', 'published'). Defaults to 'pending'.
 *                      'returnType' (string, optional): If 'editor', returns URL to private view. Otherwise, public URL.
 * @param array $filesToUpload An indexed array of files to group together, each containing details:
 *                      'path' (string): Path to the local file.
 *                      'type' (string): MIME type of the file.
 *                      'name' (string): Name of the file.
 *                      'description' (string, optional): Description of the file.
 * @param array $datas Array of Nakala metadata values, applied to group.
 * @return array|false [URL => Nakala URL, errors => File upload errors] on success, or false on failure.
 */
function uploadFilesToNakala($system, $parameters, $filesToUpload, $datas)
{

    global $glb_curl_code, $glb_curl_error;
    $glb_curl_code = null;
    $glb_curl_error = '';

    $herror = HEURIST_ACTION_BLOCKED;

    $apiKey = $parameters['apiKey'] ?? '';

    $nakalaUrls = getNakalaBaseUrls($apiKey);
    $useTest = $nakalaUrls['useTest'];
    $NAKALA_BASE_URL = $nakalaUrls['ui'];
    $NAKALA_BASE_URL_API_FILE = $nakalaUrls['apiFile'];
    $NAKALA_BASE_URL_API = $nakalaUrls['api'];

    $missingApiKey = '<br><br>Your Nakala API key is either missing or invalid, please check it under Design > External repositories';
    $unknownErrorMsg = 'An unknown response was received from Nakala after uploading the selected file.<br>Please create a ticket if this persists.';
    $nakalaUnavailable = '<br><br>Nakala services appear to be unavailable.<br>Please create a ticket if this persists.';

    $curlLoaded = function_exists('curl_init');
    if (!$curlLoaded || empty($filesToUpload) || empty($apiKey) || empty($datas)) {

        $glb_curl_code = !$curlLoaded ? HEURIST_SYSTEM_FATAL : HEURIST_INVALID_REQUEST;
        $glb_curl_error = !$curlLoaded ? 'Cannot init curl extension. Verify php installation' : 'Required details are missing';
        $system->addError($glb_curl_code, $glb_curl_error);

        return false;
    }

    $apiKey = "X-API-KEY: {$parameters['apiKey']}";

    $useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.6) Gecko/2009011913 Firefox/3.0.6';

    $curl = curl_init();
    if (!$curl) {
        $system->addError(HEURIST_SYSTEM_FATAL, 'Failed to initialise CURL handler');
        return false;
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, [$apiKey]);// USERs API KEY

    curl_setopt($curl, CURLOPT_COOKIEFILE, '/dev/null');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);//return the output as a string from curl_exec
    curl_setopt($curl, CURLOPT_NOBODY, 0);
    curl_setopt($curl, CURLOPT_HEADER, 0);//don't include header in output
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);// follow server header redirects

    curl_setopt($curl, CURLOPT_TIMEOUT, 60);// timeout after sixty seconds
    curl_setopt($curl, CURLOPT_MAXREDIRS, 5);// no more than 5 redirections

    curl_setopt($curl, CURLOPT_USERAGENT, $useragent);

    curl_setopt($curl, CURLOPT_AUTOREFERER, true);

    if (defined("HEURIST_HTTP_PROXY")) {
        curl_setopt($curl, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if (defined('HEURIST_HTTP_PROXY_AUTH')) {
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }

    curl_setopt($curl, CURLOPT_URL, "{$NAKALA_BASE_URL_API}/uploads");

    // Check if file has already been uploaded - may have previously failed
    $uploadedFileList = curl_exec($curl);
    $error = curl_error($curl);

    if ($error) {

        $glb_curl_code = 'curl';
        $glb_curl_error = $error;

        $code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));

        if ($code == 401 || $code == 403 || $code >= 500) { // invalid/missing api key, unknown account/user, or Nakala server problem

            $glb_curl_error .= $code < 500 ? $missingApiKey : $nakalaUnavailable;
            $herror = $code < 500 ? HEURIST_INVALID_REQUEST : HEURIST_ACTION_BLOCKED;

            unset($curl);
            $system->addError($herror, $glb_curl_error);

            return false;
        } // other error do not matter here

        $uploadedFileList = [];
    }

    $uploadedFileList = json_decode($uploadedFileList, true);
    if (JSON_ERROR_NONE == json_last_error() && is_array($uploadedFileList)) {

        if (array_key_exists('message', $uploadedFileList)) {

            $code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));

            if ($code === 401 || $code === 403 || $code >= 500) { // invalid/missing api key, or unknown account/user

                $glb_curl_error .= $code < 500 ? $missingApiKey : $nakalaUnavailable;
                $herror = $code < 500 ? HEURIST_INVALID_REQUEST : HEURIST_ACTION_BLOCKED;

                unset($curl);
                $system->addError($herror, $glb_curl_error);

                return false;
            } // other error do not matter here

            $uploadedFileList = [];
        }
    }

    $uploadedFiles = [];
    $fileErrors = [];

    foreach ($filesToUpload as $idx => $file) {

        $fileID = array_key_exists('id', $file) ? $file['id'] : $idx;

        if (!file_exists($file['path'])) {
            $fileErrors[] = "File #{$fileID}: Could not locate the file to be uploaded to Nakala";
            continue;
        }

        $sha1 = sha1_file($file['path']);
        foreach ($uploadedFileList as $nakalaFile) {
            if ($nakalaFile['sha1'] == $sha1) {
                $uploadedFiles[] = $sha1;
                continue 2;
            }
        }

        $curlFile = new CURLFile($file['path'], $file['type'], $file['name']);

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, ['file' => $curlFile]);

        $fileDetails = curl_exec($curl);

        $error = curl_error($curl);

        if ($error) {

            $glb_curl_code = 'curl';
            $glb_curl_error = $error;

            $code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));

            if ($code == 401 || $code == 403) { // invalid/missing api key, or unknown account/user
                $glb_curl_error .= $missingApiKey;
                $herror = HEURIST_INVALID_REQUEST;
            } elseif ($code >= 500) {
                $glb_curl_error .= $nakalaUnavailable;
                $herror = HEURIST_ACTION_BLOCKED;
            } else {
                $fileErrors[] = "File #{$fileID}: {$fileDetails['message']}";
                continue;
            }

            unset($curl);
            $system->addError($herror, $glb_curl_error);

            return false;
        }

        $fileDetails = json_decode($fileDetails, true);

        if (JSON_ERROR_NONE != json_last_error() || !is_array($fileDetails)) { // json error occurred | is not array | is missing information

            unset($curl);
            $system->addError(HEURIST_ACTION_BLOCKED, $unknownErrorMsg);

            return false;
        }

        if (array_key_exists('message', $fileDetails)) {

            $code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));

            if ($code == 401 || $code == 403) { // invalid/missing api key, or unknown account/user
                $glb_curl_error .= $missingApiKey;
                $herror = HEURIST_INVALID_REQUEST;
            } elseif ($code >= 500) {
                $glb_curl_error .= $nakalaUnavailable;
                $herror = HEURIST_ACTION_BLOCKED;
            } else {
                $fileErrors[] = "File #{$fileID}: {$fileDetails['message']}";
                continue;
            }

            unset($curl);
            $system->addError($herror, $glb_curl_error);

            return false;
        }

        $sha1Nakala = $fileDetails['sha1'];

        if ($sha1 != $sha1Nakala) {
            $fileErrors[] = "File #{$fileID}: SHA1 mismatch between local Heurist file and uploaded Nakala file.";
            continue;
        }

        $fileArr = ['sha1' => $sha1];
        if (array_key_exists('description', $file)) {
            $fileArr['description'] = htmlspecialchars($file['description']);
        }

        $uploadedFiles[$fileID] = $fileArr;
    }

    if ($uploadedFiles === []) {
        return ['URL' => '', 'errors' => $fileErrors];
    }

    $status = 'pending';
    if (array_key_exists('status', $parameters) && $parameters['status'] === 'pending' || $parameters['status'] === 'published') {
        $status = $parameters['status'];
    }

    $metas = [];
    prepareNakalaMetadata($datas, $metas);

    $metadata = ['status' => $status, 'files' => $uploadedFiles, 'metas' => $metas];

    curl_setopt($curl, CURLOPT_HTTPHEADER, [$apiKey, 'Content-Type:application/json']); // Reset headers to specify the return type
    curl_setopt($curl, CURLOPT_URL, "{$NAKALA_BASE_URL_API}");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($metadata));

    $result = curl_exec($curl);
    $error = curl_error($curl);

    if ($error) {

        $glb_curl_code = 'curl';
        $glb_curl_error = $error;

        $code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));

        if ($code == 401 || $code == 403) { // invalid/missing api key, or unknown account/user
            $glb_curl_error .= $missingApiKey;
            $herror = HEURIST_INVALID_REQUEST;
        } elseif ($code >= 500) {
            $glb_curl_error .= $nakalaUnavailable;
            $herror = HEURIST_ACTION_BLOCKED;
        }

        unset($curl);
        $system->addError($herror, $glb_curl_error);

        return false;
    }

    $result = json_decode($result, true);

    if (JSON_ERROR_NONE != json_last_error() || !is_array($result)) { // json error occurred | is not array | is missing information

        unset($curl);
        $system->addError(HEURIST_ACTION_BLOCKED, $unknownErrorMsg);

        return false;
    }

    $hasPayload = array_key_exists('payload', $result);
    if (!$hasPayload && array_key_exists('message', $result)) {

        $code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));

        if ($code == 401 || $code == 403) { // invalid/missing api key, or unknown account/user
            $glb_curl_error .= $missingApiKey;
            $herror = HEURIST_INVALID_REQUEST;
        } else {
            $glb_curl_error = $result['message'];
        }

        unset($curl);
        $system->addError($herror, $glb_curl_error);

        return false;

    } elseif (!$hasPayload) {

        unset($curl);
        $system->addError(HEURIST_ACTION_BLOCKED, $unknownErrorMsg);

        return false;
    }

    $externalURLs = [];
    $payload = $result['payload'];

    if(!array_key_exists('id', $payload)){

        if (array_key_exists('validationErrors', $payload)) {
            $msg = 'Invalid metadata value(s) found:<br>' . implode('<br>', $payload['validationErrors']);
        }

        unset($curl);
        $msg = is_array($payload) ? json_encode($payload) : $payload;

        $system->addError($herror, $msg);

        return false;
    }

    $nakalaID = $result['payload']['id'];
    $returnType = array_key_exists('returnType', $parameters) ? $parameters['returnType'] : '';
    $linkToUI = $returnType === 'editor';
    $linkToUIAndID = $returnType === 'editor+id';

    if($linkToUIAndID){
        $externalURLs[] = ['id' => $nakalaID, 'link' => "{$NAKALA_BASE_URL}{$nakalaID}"];
    }elseif($linkToUI){ // returns link to private view
        $externalURLs[] = "{$NAKALA_BASE_URL}{$nakalaID}";
    }else{ // returns link to publically available file
        foreach ($uploadedFiles as $file) {
            $externalURLs[] = "{$NAKALA_BASE_URL_API_FILE}{$nakalaID}/{$file['sha1']}";
        }
    }

    return ['DOI' => $nakalaID, 'URL' => $externalURLs, 'errors' => $fileErrors];
}

/**
 * Validate and prepare Nakala metadata values
 *
 * @param array $datas Metadata values
 * @param array $metas Cleaned and prepared metadata values
 * @return void
 */
function prepareNakalaMetadata($datas, &$metas)
{

    $W3CDTF_REGEX = '/(\d{4}-\d{2}-\d{2}T\d{2}(:\d{2}){1,2}[-+]\d{2}:\d{2})|(\d{4}-\d{2}-\d{2})|(\d{4}-\d{2})|(\d{4})/';
    foreach ($datas as $data) {

        if (array_key_exists('value', $data) && array_key_exists('lang', $data)
            && array_key_exists('typeUri', $data) && array_key_exists('propertyUri', $data)) { // pre-prepared value

            $metas[] = [
                'value' => $data['value'],
                'lang' => $data['lang'],
                'typeUri' => $data['typeUri'],
                'propertyUri' => $data['propertyUri'],
            ];

            continue;
        }

        if (!is_array($data) || !array_key_exists('values', $data) || !array_key_exists('field', $data)) {
            continue;
        }

        // value => value, lang => AR2, typeUri => URI, propertyUri => URI
        $values = $data['values'];
        $propertyURI = $data['field']; // propertyUri

        foreach ($values as $value) {

            $typeURI = null;
            $lang = null;

            if (empty($value) && empty(@$value['value'])) {
                continue;
            }

            if (is_array($value)) {
                $typeURI = @$value['type'];
                $value = @$value['value'];
            }

            if (filter_var($value, FILTER_VALIDATE_URL)) {
                $value = filter_var($value, FILTER_SANITIZE_URL);
                $typeURI = PURL_TERM_URI;
            } elseif (preg_match($W3CDTF_REGEX, $value, $matches)) {
                $value = $matches[0];
                $typeURI = PURL_TERM_DATE;
            } elseif ((strlen($value) === 2 || strlen($value) === 3) && getLangCode2($value) !== null) {
                $value = getLangCode2($value);
                $typeURI = PURL_TERM_LANG;
            } else {
                [$lang, $value] = extractLangPrefix($value);
                $typeURI = null; // W3_XML_SCHEMA_STRING
                $lang = $lang ? getLangCode2($lang) : null;
            }

            $metas[] = [
                'value' => $value,
                'lang' => $lang,
                'typeURI' => $typeURI,
                'propertyURI' => $propertyURI,
            ];
        }
    }
}

/**
 * Flushes PHP's output buffers.
 * Note: This function is marked as "not used" in the original source comments.
 *
 * @param bool $start Optional. If true (default), restarts output buffering after flushing.
 * @return void
 */
function flush_buffers($start = true)
{
    //ob_end_flush();
    @ob_flush();
    @flush();
    if ($start) {
        @ob_start();
    }
}

/**
 * Reads a file in chunks and outputs it to the client in a memory-efficient manner.
 *
 * This method is particularly useful for large files, as it reads the file in 10 MB chunks
 * and outputs it directly to the client to avoid exhausting memory.
 *
 * @param string $file_path The path to the file to be read and output.
 * @param int $range_min Optional. The starting byte position for reading a range. Defaults to 0.
 * @param int $range_max Optional. The ending byte position for reading a range. If 0, reads to the end. Defaults to 0.
 * @return int|false|void Returns the size of the file (or range) in bytes if successful and not outputting entire file through readfile(),
 *                        false on failure to open file, or void if file is empty. `readfile` output is directly to client.
 */
function fileReadByChunks($file_path, $range_min = 0, $range_max = 0)
{
    // Get the size of the file
    $file_size = getFileSize($file_path);

    if ($file_size == 0) {
        return; //file does not exist
    }

    // Set the chunk size to 10 MB (10 * 1024 * 1024 bytes)
    $chunk_size = 10 * 1024 * 1024;

    // Check if the file is larger than the chunk size
    if ($range_max == 0 && $file_size < $chunk_size) {
        // If the file is smaller than the chunk size, output the entire file
        return readfile($file_path);
    }

    // Open the file in binary read mode
    $handle = fopen($file_path, 'rb');
    if (!$handle) {
        //error_log('file not found: '.htmlspecialchars($filename));
        return 0;
    }

    if ($range_max > 0) { //output defined range only
        if ($range_min > 0) {
            fseek($handle, $range_min);
        }
        $chunk = fread($handle, $range_max - $range_min + 1);
        echo $chunk;
    } else {
        // Loop through the file and read it in chunks
        while (!feof($handle)) {
            echo fread($handle, $chunk_size); // Output the current chunk (was 1000)
            @ob_flush(); // Flush the output buffer
            @flush();    // Flush the system buffers
        }
    }
    // Close the file handle
    fclose($handle);

    // Return the file size after reading
    return $file_size;
}

/**
 * Retrieves the size of a file, with an optional cache-clearing mechanism.
 *
 * This function checks the existence of the file and returns its size in bytes. It can optionally
 * clear the file status cache to ensure the most up-to-date file size is retrieved, which is useful
 * if the file is being modified during runtime.
 *
 * @param string $file_path The path to the file whose size is to be determined.
 * @param bool $clear_stat_cache Optional. If true, clears the file status cache before checking the file size.
 *                               Defaults to false.
 * @return int The size of the file in bytes. Returns 0 if the file does not exist.
 */
function getFileSize($file_path, $clear_stat_cache = false)
{
    // If cache clearing is enabled, clear the file status cache
    if ($clear_stat_cache) {
        if (version_compare(phpversion(), '5.3.0') >= 0) {
            // Clear cache for the specific file (PHP 5.3.0 or higher)
            clearstatcache(true, $file_path);
        } else {
            // Clear entire cache (for versions lower than 5.3.0)
            clearstatcache();
        }
    }

    // Check if the file exists
    if (file_exists($file_path)) {
        // Return the file size, handling potential integer overflow on 32-bit systems
        return USystem::fixIntegerOverflow(filesize($file_path));
    } else {
        // Return 0 if the file does not exist
        return 0;
    }
}

/**
 * Saves the provided array data into a ini file
 *
 * @param string $file path to the ini file
 * @param array<string> $data configuration data to be saved
 * @param bool $keyAsSection whether the array keys are section headers
 * @return bool whether the saving has been successful
 */
function saveIniFile($file, $data, $keyAsSection = false)
{

    if (!is_array($data)) {
        return false;
    }

    if (array_key_exists('@comment', $data)) {

        $comments = $data['@comment'];
        unset($data['@comment']);

        if (is_array($comments)) {
            foreach ($comments as $comment) {

                $comment = preg_match('/(?:\r|\n)$/', $comment) === false ? $comment . PHP_EOL : $comment;
                $comment = preg_match('/^(?:;|#)/', $comment) === false ? "; {$comment}" : $comment;
                $size = fileAdd($comment, $file);

                if ($size === 0 && !empty($comment)) {
                    return false;
                }
            }
        } elseif (is_string($comments)) {

            $comments = preg_match('/(?:\r|\n)$/', $comments) === false ? $comments . PHP_EOL : $comments;
            $comments = preg_match('/^(?:;|#)/', $comments) === false ? "; {$comments}" : $comments;
            $size = fileAdd($comments, $file);

            if ($size === 0 && !empty($comments)) {
                return false;
            }
        }
    }

    $result = true;

    if ($keyAsSection) {

        foreach ($data as $section => $sectionData) {

            if (!is_array($sectionData)) {

                $size = fileAdd("{$section}={$sectionData}" . PHP_EOL, $file);
                if ($size === 0) {
                    $result = false;
                    break;
                }

                continue;
            }

            $size = fileAdd(PHP_EOL . "[{$section}]" . PHP_EOL, $file);
            if ($size === 0) {
                $result = false;
                break;
            }

            $result = saveIniFile($file, $sectionData);
            if ($result === false) {
                break;
            }
        }
    } else {

        foreach ($data as $key => $value) {

            $size = fileAdd("{$key}={$value}" . PHP_EOL, $file);
            if ($size === 0) {
                $result = false;
                break;
            }
        }
    }

    return $result;
}

function getFileDetailsForNakala($mysqli, $ulfID)
{

    $ulfID = intval($ulfID);
    if ($ulfID <= 0) {
        return [false, 'Invalid file ID provided'];
    }

    $fileQuery = "SELECT ulf_OrigFileName, concat(ulf_FilePath, ulf_FileName) AS 'fullPath', fxm_MimeType, ulf_Description, concat(ugr_FirstName, ' ', ugr_LastName) AS 'fullName', DATE(ulf_Added)
    FROM recUploadedFiles, defFileExtToMimetype, sysUGrps
    WHERE ulf_ID = {$ulfID} AND ulf_MimeExt = fxm_Extension AND ulf_UploaderUGrpID = ugr_ID";

    $fileResult = $mysqli->query($fileQuery);
    if (!$fileResult) { // another mysql error, skip
        return [false, FILE_NO . $ulfID . R_ARROW . $mysqli->error];
    }

    /** $file_dtl:
     * [0] => title
     * [1] => file path
     * [2] => mime type
     * [3] => description
     * [4] => Uploader's full name
     * [5] => created date (no time)
     */
    $fileDetails = $fileResult->fetch_row();
    $filePath = resolveFilePath($fileDetails[1]);
    if (!file_exists($filePath)) {
        return [false, FILE_NO . $ulfID . R_ARROW . 'Unable to locate the local file for transfer'];
    }

    $file = [
        'path' => $filePath,
        'type' => $fileDetails[2],
        'name' => $fileDetails[0],
        'description' => $fileDetails[3],
    ];

    $metaValues = [];
    $metaValues['title'] = [
        'value' => $fileDetails[0],
        'lang' => null,
        'typeUri' => W3_XML_SCHEMA_STRING,
        'propertyUri' => NAKALA_REPO . 'terms#title',
    ];

    $fileType = $fileDetails[2];

    /** Use fxm_MimeType
     * Nakala <=> Mime Type
     * text <=> text | pdf
     * image <=> image
     * sound <=> sound | audio
     * video <=> video
     * other <=> anything else
     */

    if (strpos($fileType, 'text') !== false || strpos($fileType, 'pdf') !== false) {
        $fileType = 'http://purl.org/coar/resource_type/c_1843';
    } elseif (strpos($fileType, 'sound') !== false || strpos($fileType, 'audio') !== false) {
        $fileType = 'http://purl.org/coar/resource_type/c_18cc';
    } elseif (strpos($fileType, 'image') !== false) {
        $fileType = 'http://purl.org/coar/resource_type/c_c513';
    } elseif (strpos($fileType, 'video') !== false) {
        $fileType = 'http://purl.org/coar/resource_type/c_12ce';
    } else { // other
        $fileType = 'http://purl.org/coar/resource_type/c_1843';
    }

    $metaValues['type'] = [
        'value' => $fileType,
        'lang' => null,
        'typeUri' => PURL_TERM_URI,
        'propertyUri' => NAKALA_REPO . 'terms#type',
    ];

    // Current Heurist user
    $metaValues['alt_creator'] = [
        'value' => $fileDetails[4],
        'lang' => null,
        'typeUri' => W3_XML_SCHEMA_STRING,
        'propertyUri' => 'http://purl.org/dc/terms/creator',
    ];

    // ulf_Added
    $metaValues['created'] = [
        'value' => $fileDetails[5],
        'lang' => null,
        'typeUri' => null,
        'propertyUri' => NAKALA_REPO . 'terms#created',
    ];

    if (!empty($fileDetails[3])) {
        $metaValues['description'] = [
            'value' => $fileDetails[3],
            'lang' => null,
            'typeUri' => W3_XML_SCHEMA_STRING,
            'propertyUri' => 'http://purl.org/dc/terms/description',
        ];
    }

    return [$metaValues, $file];
}

/* ---------------------------------------------------------------------------------------------
 * DOI retrieval / "regaining" for external repositories (Nakala now, Zenodo etc. to follow)
 *
 * Background (see Nakala documentation, eg. https://documentation.huma-num.fr/en/nakala-en/):
 * Nakala assigns its identifier in DOI format ("10.34847/nkl.xxxxxxxx" on production) to a data
 * record the instant it's created via POST /datas - this is the value already captured as
 * $nakalaID in uploadFilesToNakala() above. The nuance is that this identifier is only actually
 * *registered* with DataCite (and therefore resolvable at https://doi.org/{doi}) once the record's
 * status is 'published'. While status is 'pending' (private - the default used for whole-database
 * archive uploads, see export/dbbackup/exportMyDataPopup.php) the DOI string is reserved but not
 * live, and Nakala's API has been observed to return 403 for GET requests against the caller's own
 * pending records (see the @todo above uploadFilesToNakala()).
 *
 * Publishing is irreversible - once a Nakala record is published it cannot be unpublished or
 * deleted - so nothing here triggers publication automatically; it always requires an explicit
 * call to publishNakalaData(), which callers should only make after explicit user confirmation.
 * ------------------------------------------------------------------------------------------- */

/**
 * Checks whether a Nakala API key is one of Nakala's published sandbox/test keys.
 * Factored out of uploadFilesToNakala() so every Nakala-related function agrees on whether a
 * given key targets the production or test/sandbox platform.
 *
 * @param string $apiKey
 * @return bool
 */
function isNakalaTestApiKey($apiKey)
{
    $testAPIKeys = [
        // Nakala => https://test.nakala.fr/
        '01234567-89ab-cdef-0123-456789abcdef',
        '33170cfe-f53c-550b-5fb6-4814ce981293',
        'f41f5957-d396-3bb9-ce35-a4692773f636',
        'aae99aba-476e-4ff2-2886-0aaf1bfa6fd2',
    ];
    return in_array($apiKey, $testAPIKeys);
}

/**
 * Returns the set of Nakala base URLs appropriate for a given API key (production vs test/sandbox).
 *
 * @param string $apiKey
 * @return array{useTest: bool, ui: string, apiFile: string, api: string}
 */
function getNakalaBaseUrls($apiKey)
{
    $useTest = isNakalaTestApiKey($apiKey);
    return [
        'useTest' => $useTest,
        'ui'      => $useTest ? 'https://test.nakala.fr/u/datas/' : 'https://nakala.fr/u/datas/',
        'apiFile' => $useTest ? 'https://apitest.nakala.fr/data/' : 'https://api.nakala.fr/data/',
        'api'     => $useTest ? 'https://apitest.nakala.fr/datas' : 'https://api.nakala.fr/datas',
    ];
}

/**
 * Fetches the current status and DOI for a single Nakala data record via GET /datas/{identifier}.
 * Use this to confirm/regain the DOI of a record that was uploaded earlier (eg. by reading the
 * identifier back out of this database's external_IDs.json - see recordExternalIdentifier()).
 *
 * If Nakala refuses the read (403/401 - currently expected for the depositor's own 'pending'
 * records, see the file-level note above) this does NOT fail outright: it falls back to the
 * reserved identifier/DOI we already have, with doiRegistered forced to false, since the caller
 * usually already has the identifier from the original upload and just wants to know whether it
 * has gone live since.
 *
 * @param \hserv\System $system
 * @param string $apiKey     Nakala API key for the account that owns/can read the record
 * @param string $identifier Nakala data identifier (= reserved DOI string), as returned at creation
 * @return array|false ['identifier'=>, 'doi'=>, 'doiRegistered'=>bool, 'status'=>, 'link'=>] or false on hard failure
 */
function getNakalaDataDetails($system, $apiKey, $identifier)
{

    if (empty($apiKey) || empty($identifier)) {
        $system->addError(HEURIST_INVALID_REQUEST, 'An API key and a data identifier are both required to retrieve a Nakala DOI');
        return false;
    }

    $urls = getNakalaBaseUrls($apiKey);

    $curl = curl_init();
    if (!$curl) {
        $system->addError(HEURIST_SYSTEM_FATAL, 'Failed to initialise CURL handler');
        return false;
    }

    curl_setopt($curl, CURLOPT_URL, $urls['api'] . '/' . rawurlencode($identifier));
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["X-API-KEY: {$apiKey}"]);
    curl_setopt($curl, CURLOPT_COOKIEFILE, '/dev/null');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 5);

    if (defined('HEURIST_HTTP_PROXY')) {
        curl_setopt($curl, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if (defined('HEURIST_HTTP_PROXY_AUTH')) {
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }

    $body = curl_exec($curl);
    $code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        $system->addError(HEURIST_ACTION_BLOCKED, "Nakala services appear to be unavailable: {$error}");
        return false;
    }

    if ($code === 401 || $code === 403) {
        // 401: "Donnée non publiée, clé d'API manquante ou compte utilisateur inexistant"
        // 403: "Droit sur la donnée insuffisant"
        // Both mean we cannot read the record right now - typically because it's still 'pending'.
        // Not fatal: fall back to what we already know rather than blocking the caller's workflow.
        // See file-level note above the function definitions.
        return [
            'identifier' => $identifier,
            'doi' => $identifier,
            'doiRegistered' => false,
            'status' => 'pending',
            'link' => "{$urls['ui']}{$identifier}",
        ];
    }

    $result = json_decode($body, true);

    if (JSON_ERROR_NONE != json_last_error() || !is_array($result) || !array_key_exists('status', $result)) {
        $system->addError(HEURIST_ACTION_BLOCKED, 'An unknown response was received from Nakala while retrieving DOI/status information.');
        return false;
    }

    $status = $result['status']; // Nakala returns 'public' for a published record (not 'published')
    // and 'pending' while still private. Check for both to be safe.
    $isPublished = ($status === 'public' || $status === 'published');

    return [
        'identifier' => $identifier,
        'doi' => $identifier, // the Nakala identifier IS the DOI string - see file-level note above
        'doiRegistered' => $isPublished,
        'status' => $status,
        'link' => "{$urls['ui']}{$identifier}",
    ];
}

/**
 * Publishes a previously 'pending' Nakala data record. This is what actually causes Nakala to
 * register the record's DOI with DataCite, making it live/resolvable at https://doi.org/{doi}.
 *
 * IMPORTANT: this makes the record (and its description) PUBLIC, and Nakala does not support
 * unpublishing or deleting published data. Callers MUST obtain explicit, informed confirmation
 * from the Heurist user before calling this - never call it automatically as part of a routine
 * DOI check (see getNakalaDataDetails()).
 *
 * @param \hserv\System $system
 * @param string $apiKey
 * @param string $identifier
 * @return bool True on success, false on failure (see $system->getError())
 */
function publishNakalaData($system, $apiKey, $identifier)
{

    if (empty($apiKey) || empty($identifier)) {
        $system->addError(HEURIST_INVALID_REQUEST, 'An API key and a data identifier are both required to publish a Nakala record');
        return false;
    }

    $urls = getNakalaBaseUrls($apiKey);

    $curl = curl_init();
    if (!$curl) {
        $system->addError(HEURIST_SYSTEM_FATAL, 'Failed to initialise CURL handler');
        return false;
    }

    // The Nakala API docs confirm: status is a PATH PARAMETER, not a request body.
    // Endpoint: PUT /datas/{identifier}/status/{status}
    // Available status values: 'published', 'moderated'
    // Success response: 204 No Content (no body)
    curl_setopt($curl, CURLOPT_URL, $urls['api'] . '/' . rawurlencode($identifier) . '/status/published');
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($curl, CURLOPT_POSTFIELDS, '');
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["X-API-KEY: {$apiKey}", 'Content-Length: 0']);
    curl_setopt($curl, CURLOPT_COOKIEFILE, '/dev/null');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);

    if (defined('HEURIST_HTTP_PROXY')) {
        curl_setopt($curl, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if (defined('HEURIST_HTTP_PROXY_AUTH')) {
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }

    $body = curl_exec($curl);
    $code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
    $error = curl_error($curl);
    curl_close($curl);

    if ($error || ($code !== 204 && $code >= 400)) {
        $msg = $error ?: "Nakala returned HTTP {$code} while attempting to publish the record";
        $system->addError(HEURIST_ACTION_BLOCKED, $msg);
        return false;
    }

    return true;
}

/**
 * Placeholder for Zenodo DOI retrieval - not yet implemented, see getRepositoryDOI().
 *
 * Zenodo has a similar nuance to Nakala: creating a deposition (POST /api/deposit/depositions)
 * immediately returns a numeric internal 'id' plus a *pre-reserved* DOI under
 * metadata.prereserve_doi.doi, but that DOI is only registered/resolvable once the deposition is
 * explicitly published (POST /api/deposit/depositions/{id}/actions/publish) - at which point
 * GET /api/deposit/depositions/{id} returns a top-level 'doi' field.
 *
 * To wire Zenodo in:
 *   1. Implement this to call that GET endpoint with a Bearer token (Authorization header), and
 *      return the same shape as getNakalaDataDetails().
 *   2. Add a 'zenodo' branch to getRepositoryDOI() below.
 *   3. No changes are needed to the credentials layer - user_getRepositoryCredentials2() in
 *      dbsUsersGroups.php already supports an arbitrary 'service' name per account.
 *
 * @param \hserv\System $system
 * @param string $apiKey
 * @param string $identifier
 * @return false
 */
function getZenodoDOI($system, $apiKey, $identifier)
{
    $system->addError(HEURIST_ACTION_BLOCKED, 'DOI retrieval for Zenodo is not yet implemented.');
    return false;
}

/**
 * Generic, repository-agnostic entry point for (re)confirming the DOI of a record previously
 * deposited in an external repository. This is the function to call from UI actions such as
 * 'Regain DOI' (see repoController.php, action=getdoi / action=publish).
 *
 * To add support for a new repository: write a get<Name>DOI($system, $apiKey, $identifier)
 * function returning the same shape as getNakalaDataDetails(), then add a branch below.
 *
 * @param \hserv\System $system
 * @param string $service    Repository service name, eg. 'nakala', 'zenodo' - this is the
 *                            'service' value from repository credentials, NOT the service_id
 *                            (see user_getRepositoryCredentials2() in dbsUsersGroups.php)
 * @param string $apiKey
 * @param string $identifier
 * @return array|false ['identifier'=>, 'doi'=>, 'doiRegistered'=>bool, 'status'=>, 'link'=>] or false
 */
function getRepositoryDOI($system, $service, $apiKey, $identifier)
{

    $service = strtolower(trim((string) $service));

    if (strpos($service, 'nakala') !== false) {
        return getNakalaDataDetails($system, $apiKey, $identifier);
    } elseif (strpos($service, 'zenodo') !== false) {
        return getZenodoDOI($system, $apiKey, $identifier);
    }

    $system->addError(HEURIST_INVALID_REQUEST, "DOI retrieval is not supported for repository service '{$service}'");
    return false;
}

/**
 * Records (or updates) a repository deposit's identifying information - service, internal ID,
 * DOI, publication status, etc - in this database's external_IDs.json settings file
 * (HEURIST_FILESTORE_DIR/{dbname}/settings/external_IDs.json, via SystemSettings::setDatabaseSetting()).
 *
 * Centralising this here (rather than each upload flow building the array by hand) keeps the
 * schema consistent across repositories and across the different places Heurist can deposit data
 * (whole-database archive export, single-file 'transfer to remote' batch action, record-edit file
 * upload, etc).
 *
 * @param \hserv\System $system
 * @param string $key   Unique key for this entry within external_IDs.json, eg. 'NakalaDBBackup' or "{serviceID}_{ulf_ID}"
 * @param array $entry  Associative array - recognised keys: Service, Label, ID, DOI, DOIRegistered, Status, URL, Date, Note, Data
 * @return bool True on success
 */
function recordExternalIdentifier($system, $key, $entry)
{

    $defaults = [
        'Service' => null, 'Label' => null, 'ID' => null, 'DOI' => null,
        'DOIRegistered' => false, 'Status' => null, 'URL' => null,
        'Date' => date('Y-m-d'), 'Note' => null, 'Data' => null,
    ];

    $entry = array_merge($defaults, $entry);

    return $system->settings->setDatabaseSetting('External IDs', [$key => $entry], 1) !== false;
}
