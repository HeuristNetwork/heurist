<?php
/**
* recordFile.php - Function library for recUploadedFiles
*
* file - prefix for functions

* fileRegister - register file in recUploadedFiles
* fileGetByObfuscatedId - get id by obfuscated id (NOT USED)
* fileGetByFileName
* fileGetByOriginalFileName
* fileRenameToOriginal
* fileGetFullInfo  - local paths, external links, mimetypes and parameters (mediatype and source)
* fileGetThumbnailURL - URL to thumbnail for given record ID and specified bg color
* fileGetMetadata - return metadata for registered media, plus width,height for images
*
* getPrevailBackgroundColor2  - not used
* getPrevailBackgroundColor
*
* fileGetPlayerTag - produce appropriate html tag to view media content
* getPlayerURL  - get player url for youtube, vimeo, soundcloud
*
* getWebImageCache - get scaled down jpeg version of a image, to reduce load times
*
* @todo move to UFile.php
* resolveFilePath
* downloadFile
*
* downloadViaProxy - Blocked because of possible Remote file disclosure
* detect3D_byExt - returns 3d or 3dhop depends on extension parameter
* 
* @todo - move 1) some methods to dbRecUploadedFiles 2) some to uImage
*
* @project     Heurist academic knowledge management system
* @package Records\Search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

use hserv\entity\DbRecUploadedFiles;
use hserv\utilities\USanitize;
use hserv\utilities\UImage;

require_once dirname(__FILE__).'/../../structure/dbsUsersGroups.php';

/**
 * Registers a local file in the `recUploadedFiles` table if it's not already registered.
 * Used for importing files via CSV or indexing a directory.
 *
 * @todo This function could potentially be a method of the DbRecUploadedFiles class.
 *
 * @param \hserv\System $system The Heurist system object, providing access to database and file system configurations.
 * @param string $fullname The full absolute path to the file on the server.
 * @param string|null $description Optional description for the file.
 * @return int|false The ulf_ID of the registered file (newly registered or existing), or false on failure.
 */
function fileRegister($system, $fullname, $description=null){

    $file_id = fileGetByFileName($system, $fullname);//check if it is already registered

    if(!($file_id>0)) {

        $filesize = filesize($fullname);
        $fileinfo = pathinfo($fullname);

        $mimetypeExt = strtolower($fileinfo['extension']);
        $filename_base = $fileinfo['basename'];
        $dir = $fileinfo['dirname'];

        // get relative path to db root folder
        $relative_path = getRelativePath($system->getSysDir(), $dir);

        $fileinfo = array(
            'entity'=>'recUploadedFiles',
            'fields'=>array(
                'ulf_OrigFileName' => $filename_base,
                'ulf_MimeExt' => $mimetypeExt, //extension or mimetype allowed
                'ulf_FileSizeKB' => ($filesize<1024?1:intval($filesize/1024)),
                'ulf_FilePath' => $relative_path, //relative path to $system->getSysDir() - db root
                'ulf_FileName' => $filename_base
            )
        );
        if($description!=null){
            $fileinfo['fields']['ulf_Description'] = $description;
        }

        $entity = new DbRecUploadedFiles($system, $fileinfo);
        $ret = $entity->save();
        if($ret!==false){
            $records = $entity->records();
            $file_id = $records[0]['ulf_ID'];
        }
    }


    return $file_id;
}

/**
 * Get file ulf_ID by its Obfuscated ID (ulf_ObfuscatedFileID).
 * Note: This function is marked as NOT USED
 *
 * @param \hserv\System $system The Heurist system object.
 * @param string $ulf_ObfuscatedFileID The obfuscated file ID to search for.
 * @return int|null The ulf_ID if found, otherwise null.
 */
function fileGetByObfuscatedId($system, $ulf_ObfuscatedFileID){

    if(!$ulf_ObfuscatedFileID || strlen($ulf_ObfuscatedFileID)<1) {return null;}

    $res = mysql__select_value($system->getMysqli(), 'select ulf_ID from recUploadedFiles where ulf_ObfuscatedFileID="'.
        $system->getMysqli()->real_escape_string($ulf_ObfuscatedFileID).'"');

    return $res;
}

/**
 * Get file ulf_ID by its full path name.
 * It calculates the relative path to the database's root directory.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param string $fullname The full absolute path to the file.
 * @return int|null The ulf_ID if the file is found registered with that path and name, otherwise null.
 */
function fileGetByFileName($system, $fullname){

    $path_parts = pathinfo($fullname);
    if($path_parts['dirname']!='.'){
        $dirname = $path_parts['dirname'].'/';
        $relative_path = getRelativePath($system->getSysDir(), $dirname);
    }else{
        $relative_path = null;
    }
    $filename = $path_parts['basename'];

    // get relative path to db root folder

    $mysqli = $system->getMysqli();

    $query = 'select ulf_ID from recUploadedFiles '
        .'where ulf_FileName = "'.$mysqli->real_escape_string($filename).'"';
    if($relative_path!=null){
        $query = $query
            .' and (ulf_FilePath = "file_uploads/" or ulf_FilePath = "'
            .$mysqli->real_escape_string($relative_path).'")';
    }

    $file_id = mysql__select_value($mysqli, $query);
    return $file_id;

}


/**
 * Get file information (as an associative array) from `recUploadedFiles` by its original file name.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param string $orig_name The original name of the file (ulf_OrigFileName).
 * @return array|null An associative array of the file's record from `recUploadedFiles` if found, otherwise null.
 */
function fileGetByOriginalFileName($system, $orig_name){

        $mysqli = $system->getMysqli();

        $fileinfo = mysql__select_row_assoc($mysqli, 'select * from recUploadedFiles '
            .'where ulf_OrigFileName = "'.$mysqli->real_escape_string($orig_name).'"');

        return $fileinfo;

}

/**
 * Finds a registered file by its original name, renames the actual stored file
 * (which might have a prefixed name like ulf_xxx_) to the new name (or original name if new_name is null)
 * within the standard 'file_uploads' directory, and updates its record in `recUploadedFiles`.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param string $orig_name The original filename to search for in `ulf_OrigFileName`.
 * @param string|null $new_name (Optional) The new filename to use. If null, `$orig_name` is used.
 * @return string|null The full path to the renamed file if successful, or null if the file
 *                     was not found, the registered file is missing, or if it already exists at the target path.
 */
function fileRenameToOriginal($system, $orig_name, $new_name=null){

    if($new_name==null) {$new_name = $orig_name;}
    $file_fullpath = $system->getSysDir(DIR_FILEUPLOADS).$new_name;

    if(!file_exists($file_fullpath)){
        //find by original file name
        $fileinfo = fileGetByOriginalFileName($system, $orig_name);
        if($fileinfo){

            $reg_name = HEURIST_FILESTORE_DIR.$fileinfo['ulf_FilePath'].$fileinfo['ulf_FileName'];
            if(file_exists($reg_name)){
                //rename file to original (without prefix ulf_xxx_) and update in database
                rename($reg_name, $file_fullpath);

                $mysqli = $system->getMysqli();
                $new_name = $mysqli->real_escape_string($new_name);

                $qupdate = 'UPDATE recUploadedFiles set ulf_FilePath="file_uploads/", '
                .' ulf_FileName="'.$new_name.'", ulf_OrigFileName="'.$new_name.'" '
                .'WHERE ulf_ID='.$fileinfo['ulf_ID'];

                $mysqli->query($qupdate);

            }else{
                return null;  //registered file missed
            }
        }else{
            return null;  //registered file not found by original name
        }
    }

    return $file_fullpath;
}

/**
 * Retrieves full information for one or more files from `recUploadedFiles` table.
 *
 * Accepts a single file ID, a comma-separated string of file IDs, or an array of file IDs.
 * IDs can be either standard `ulf_ID` (numeric) or `ulf_ObfuscatedFileID` (string).
 * The function auto-detects the type of ID based on format.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int|string|array $file_ids A single file ID (numeric or obfuscated string),
 *                                   a comma-separated string of IDs, or an array of IDs.
 * @param bool $all_fields (Currently not fully implemented for specific field selection,
 *                         though original comment mentioned `ulf_Thumbnail` which is no longer in DB).
 *                         If true, might imply fetching more fields in future, but currently fetches a fixed set.
 * @return array|false An array of associative arrays, each representing a file's record,
 *                     or false if no file IDs provided or a database error occurs.
 *                     Returns an empty array if no files are found matching the IDs.
 */
function fileGetFullInfo($system, $file_ids, $all_fields=false){

    // @todo Use prepareIds() for numeric IDs and a similar function for string IDs if needed.
    if(is_string($file_ids)){
        $file_ids = explode(',', $file_ids);
    }elseif(!is_array($file_ids)){
        $file_ids = array($file_ids);
    }

    if(!isEmptyArray($file_ids)){

        $mysqli = $system->getMysqli();


        foreach ($file_ids as $idx=>$testcase) {
            if (is_string($testcase)){
                if (ctype_alnum($testcase)) {
                    $file_ids[$idx] = $mysqli->real_escape_string($testcase);
                }else{
                    $system->addError(HEURIST_INVALID_REQUEST,
                        'Wrong file id parametrer provided to fileGetFullInfo.',
                        $mysqli->error);
                    return false;
                }
            }
        }

        if(is_string($file_ids[0]) && strlen($file_ids[0])>15){

            $query = 'ulf_ObfuscatedFileID';

            $filed_ids2 = array();
            foreach($file_ids as $idx=>$v){
                $filed_ids2[] = preg_replace('/[^a-z0-9]/', "", $v);//for snyk
            }

            if(count($filed_ids2)>1){
                //escapeValues($mysqli, $file_ids);
                $query = $query.' in ("'.implode('","', $filed_ids2).'")';
            }else{
                $query = $query.' = "'.$filed_ids2[0].'"';
            }

        }elseif(is_numeric($file_ids[0]) && $file_ids[0]>0){
            $query = 'ulf_ID';

            if(count($file_ids)>1){
                $file_ids = prepareIds($file_ids);
                $query = $query.' in ('.implode(',', $file_ids).')';
            }else{
                $query = $query.' = '.intval($file_ids[0]);
            }
        }else{
            $system->addError(HEURIST_INVALID_REQUEST,
                'Wrong file id parametrer provided to fileGetFullInfo.',
                $mysqli->error);
            return false;
        }

        $query = 'select ulf_ID, concat(ulf_FilePath,ulf_FileName) as fullPath, ulf_ExternalFileReference,'
        .'fxm_MimeType, ulf_PreferredSource, ulf_OrigFileName, ulf_FileSizeKB,'
        .' ulf_ObfuscatedFileID, ulf_Description, ulf_Added, ulf_MimeExt,'
        .' ulf_Caption, ulf_Copyright, ulf_Copyowner, ulf_Parameters, ulf_WhoCanView'
        //.($all_fields?', ulf_Thumbnail':'') we don't store thumbnail in database anymore
        .' from recUploadedFiles '
        .' left join defFileExtToMimetype on fxm_Extension = ulf_MimeExt where '
        .$query;


        $res = $mysqli->query($query);

        if ($res){
            $result = array();

            while ($row = $res->fetch_assoc()){
                array_push($result, $row);

                /*
                $filename = $row[0];
                $extURL = $row[1];
                $mimeType = $row[2];

                if( $filename && file_exists($filename) ){
                array_push($result, $filename);
                }elseif($extURL && $type!='local'){
                array_push($result, $extURL);
                }
                */

            }
            $res->close();
            return $result;
        }else{
            $system->addError(HEURIST_DB_ERROR,
                'Cannot get file info in fileGetFullInfo. Count of files '
                        .count($file_ids).'. Ask thumb img: '.($all_fields?'YES':'NO'),
                $mysqli->error);
            return false;
        }
    }else{
        return false;
    }

}

/**
 * Returns the thumbnail URL and prevailing background color for a media file associated with a record.
 *
 * It first attempts to find a specifically designated thumbnail field (DT_THUMBNAIL).
 * If not found, it searches for other suitable media (images, YouTube/Vimeo videos, IIIF resources)
 * linked to the record.
 * If still not found and `$check_linked_media` is true, it recursively checks records of type RT_MEDIA_RECORD
 * linked from the current record.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int $recID The ID of the record for which to find the thumbnail.
 * @param bool $get_bgcolor If true, attempts to calculate the prevailing background color of the thumbnail.
 * @param bool $check_linked_media If true, recursively checks linked media records (type RT_MEDIA_RECORD)
 *                                 if no direct thumbnail is found. Defaults to false.
 * @return array|null An associative array with 'url' (string|null) and 'bg_color' (string|null),
 *                    or null if `recID` is invalid. 'url' will be the thumbnail URL or null.
 *                    'bg_color' will be the calculated color string or a default if not calculable/requested.
 */
function fileGetThumbnailURL($system, $recID, $get_bgcolor, $check_linked_media = false){
    
    if(!isPositiveInt($recID)){
        return null;
    }

    $thumb_url = null;
    $bg_color = null;
    $fileid = null; // This will store the ulf_ObfuscatedFileID

    $base_query = "select ruf.ulf_ObfuscatedFileID"
                . " from recDetails rd"
                . " left join recUploadedFiles ruf on ruf.ulf_ID = rd.dtl_UploadedFileID"
                . " left join defFileExtToMimetype fxm on fxm.fxm_Extension = ruf.ulf_MimeExt"
                . " where rd.dtl_RecID = " . intval($recID);

    // 1. Try to find a specifically designated thumbnail field
    if($system->defineConstant('DT_THUMBNAIL') && DT_THUMBNAIL > 0){
        $fileid = mysql__select_value($system->getMysqli(), $base_query
                .' and rd.dtl_DetailTypeID = '.DT_THUMBNAIL.' limit 1');
    }

    // 2. If not found, try to find any suitable image or remote resource (YouTube, Vimeo, IIIF)
    if($fileid == null){
        $suitable_media_condition = " and (rd.dtl_UploadedFileID is not null)" // Ensure there's an uploaded file linked
                                  ." and (fxm.fxm_MimeType like 'image%'"
                                  ." OR fxm.fxm_MimeType='video/youtube'"
                                  ." OR fxm.fxm_MimeType='video/vimeo'"
                                  ." OR fxm.fxm_MimeType='audio/soundcloud'" // Soundcloud also considered for thumb
                                  ." OR ruf.ulf_OrigFileName LIKE '".ULF_IIIF."%'" // IIIF original filename marker
                                  ." OR ruf.ulf_PreferredSource LIKE 'iiif%')" // IIIF preferred source marker
                                  ." LIMIT 1";
        $fileid = mysql__select_value($system->getMysqli(), $base_query . $suitable_media_condition);
    }

    // 3. If still not found and allowed, check linked media records
    if(!$fileid && $check_linked_media &&
        $system->defineConstant('RT_MEDIA_RECORD') && RT_MEDIA_RECORD > 0){

        $linked_query = "SELECT lr.rec_ID FROM Records lr"
                      . " LEFT JOIN recLinks rlink ON rlink.rl_TargetID = lr.rec_ID"
                      . " WHERE rlink.rl_SourceID = " . intval($recID)
                      . " AND lr.rec_RecTypeID = " . RT_MEDIA_RECORD;
        $linked_rec_ids = mysql__select_list2($system->getMysqli(), $linked_query);

        foreach($linked_rec_ids as $linked_rec_id){
            $file_details = fileGetThumbnailURL($system, $linked_rec_id, $get_bgcolor, false); // Recursion, but ensure $check_linked_media is false
            if(!empty($file_details) && !empty($file_details['url'])){
                return $file_details; // Return first found thumbnail from linked records
            }
        }
    }

    // 4. If a file ID (obfuscated) was found, construct URL and get background color
    if($fileid){
        $fileid_clean = preg_replace('/[^a-z0-9]/', "", $fileid); // Sanitize for Snyk/security
        $thumb_filename_on_server = 'ulf_'.$fileid_clean.'.png';

        if(defined('HEURIST_THUMB_URL') && file_exists(HEURIST_THUMB_DIR . $thumb_filename_on_server)){
            $thumb_url = HEURIST_THUMB_URL . $thumb_filename_on_server;
        }else{
            // Fallback to dynamic generation via fileDownload.php (or similar controller)
            $thumb_url = HEURIST_BASE_URL."?db=".$system->dbnameEnv()."&thumb=".$fileid_clean;
        }

        if($get_bgcolor){
            $bg_color_cache_file  = HEURIST_THUMB_DIR . 'ulf_'.$fileid_clean.'.bg';
            $actual_thumb_path = HEURIST_THUMB_DIR . $thumb_filename_on_server;

            if(false && file_exists($bg_color_cache_file)){ // BG color caching seems disabled (false && ...)
                // $bg_color = file_get_contents($bg_color_cache_file);
            } elseif(file_exists($actual_thumb_path)){
                $bg_color = UImage::getPrevailBackgroundColor($actual_thumb_path);
            } else {
                $bg_color = 'rgb(223, 223, 223)'; // Default BG color
            }
        }
    }

    return array('url'=>$thumb_url, 'bg_color'=>$bg_color);
}

/**
 * Resolves a potentially relative file path to an absolute path.
 *
 * It tries to resolve the path in the following order:
 * 1. Directly, if it's already an absolute path or relative to the current working directory (if set appropriately).
 * 2. Relative to the database's root directory (`HEURIST_FILESTORE_ROOT . $db_name . '/'`).
 * 3. Relative to the database's 'file_uploads' directory (`HEURIST_FILESTORE_ROOT . $db_name . '/file_uploads/'`).
 * 4. Special cases for known absolute path prefixes on different server setups (e.g., '/srv/HEURIST_FILESTORE/', '/misc/heur-filestore/').
 *
 * @todo This function has similarities with other path resolution logic and could potentially be moved to a utility class like UFile.
 *
 * @param string $path The file path to resolve. Can be absolute or relative.
 * @param string|null $db_name (Optional) The name of the database, used to construct paths relative to the DB's storage.
 *                             If null, uses `HEURIST_FILESTORE_DIR` and `HEURIST_FILES_DIR` as base.
 * @return string The resolved absolute path if successful and file exists, otherwise the original path.
 */
function resolveFilePath($path, $db_name=null){

    if( $path ){

        if(!file_exists($path) ){

            if($db_name!=null){
                $dir_folder = USanitize::sanitizePath(HEURIST_FILESTORE_ROOT . $db_name . '/');
                $db_folder_files = $dir_folder . 'file_uploads/';
            }else{
                $dir_folder = HEURIST_FILESTORE_DIR;
                $db_folder_files = HEURIST_FILES_DIR;
            }

            chdir($dir_folder);// relatively db root
            $fpath = realpath($path);
            if($fpath!==false && file_exists($fpath)){
                return $fpath;
            }else{
                chdir($db_folder_files);// relatively file_uploads
                $fpath = realpath($path);
                if($fpath!==false && file_exists($fpath)){
                    return $fpath;
                }else{
                    //special case to support absolute path on file server
                    if(strpos($path, '/srv/HEURIST_FILESTORE/')===0){
                        $fpath = str_replace('/srv/HEURIST_FILESTORE/', HEURIST_FILESTORE_ROOT, $path);
                        if(file_exists($fpath)){
                            return $fpath;
                        }
                    }elseif(strpos($path, '/misc/heur-filestore/')===0){
                        $fpath = str_replace('/misc/heur-filestore/', HEURIST_FILESTORE_ROOT, $path);
                        if(file_exists($fpath)){
                            return $fpath;
                        }
                    }elseif(strpos($path, '/data/HEURIST_FILESTORE/')===0){ //for huma-num
                        $fpath = str_replace('/data/HEURIST_FILESTORE/', HEURIST_FILESTORE_ROOT, $path);
                        if(file_exists($fpath)){
                            return $fpath;
                        }
                    }
                }
            }
        }else{
            //current dir already set
            $fpath = realpath($path);
            if($fpath!==false && file_exists($fpath)){
                return $fpath;
            }
        }

    }
    return $path;
}

/**
 * Downloads a remote URL's content and serves it as a file.
 * Heurist acts as a proxy to download the remote resource.
 *
 * Note: This function's core functionality (`loadRemoteURLContent`) is currently blocked
 * due to potential "Remote file disclosure" security concerns, as indicated in the source code.
 * The function body is commented out.
 *
 * Intended Usages (historical):
 * 1. Proxy HTTP (unsecure) resources registered in the database.
 * 2. Proxy for HTTP tiled map image servers.
 * 3. Adelaide website styles (marked as not used anymore).
 * 4. Annotated templates (marked as not used anymore).
 *
 * @todo This function is marked to be removed.
 *
 * @param string $filename The desired local filename for the downloaded content (not directly used as content is echoed).
 * @param string $mimeType The MIME type of the content to be served.
 * @param string $url The remote URL to download.
 * @param bool $bypassProxy (Parameter for `loadRemoteURLContent`) Whether to bypass any configured proxy. Default true.
 * @param string|null $originalFileName (Optional) The filename to suggest to the user for download.
 * @return void
 */
function downloadViaProxy($filename, $mimeType, $url, $bypassProxy = true, $originalFileName=null){
/*
    $rawdata = loadRemoteURLContent($url, $bypassProxy);//blocked

    if($rawdata!==false){

        fileSave($rawdata, $filename);

        if(file_exists($filename)){
            downloadFile($mimeType, $filename, $originalFileName);
        }

    }
*/
}

/**
 * Serves a local file for download to the client.
 *
 * This function handles setting appropriate HTTP headers for file download,
 * including `Content-Type`, `Content-Disposition`, `Content-Length`, etc.
 * It supports byte range requests (for resumable downloads/streaming).
 * For certain MIME types (like JSON or generic octet-stream), it applies gzip encoding.
 *
 * Usage cases mentioned:
 * 1. Download database backup (from `exportMyDataPopup`).
 * 2. Download registered files from Heurist storage folder (via `fileDownload` controller action).
 *
 * @todo This function could potentially be moved to a utility class like `UFile`.
 *
 * @param string|null $mimeType The MIME type of the file. If null or 'application/octet-stream' or 'application/json', gzip encoding is applied.
 * @param string $filename The full server path to the file to be downloaded.
 * @param string|null $originalFileName (Optional) The filename to suggest to the client.
 *                                      If provided, `Content-Disposition` is set to 'attachment'. Otherwise, 'inline'.
 * @return void
 */
function downloadFile($mimeType, $filename, $originalFileName=null){

    if (file_exists($filename)) {

        $range = @$_SERVER['HTTP_RANGE'];
        $range_max = 0;
        if($range!=null){
            //get bytes range  bytes=0-88
            list($dim, $range) = explode('=', $range);
            list($range_min,$range_max) = explode('-', $range);
            $range_min = intval($range_min);
            $range_max = intval($range_max);
        }

        header('Content-Description: File Transfer');
        $is_zip = false;
        if(!$mimeType || $mimeType == 'application/octet-stream' || $mimeType == MIMETYPE_JSON){
            $is_zip = true;
            header('Content-Encoding: gzip');
        }
        if ($mimeType) {
            header('Content-type: ' .$mimeType);
        }else{
            header('Content-type: application/octet-stream'); //was binary/download
        }
        if($mimeType!="video/mp4"){
            header(HEADER_CORS_POLICY);
            header('access-control-allow-credentials: true');
        }

        //force download  - important for embed element DO NOT include this atttibute!
        if($originalFileName!=null){
            $contentDispositionField = 'Content-Disposition: attachment; '
                . sprintf('filename="%s";', rawurlencode($originalFileName))
                . sprintf("filename*=utf-8''%s", rawurlencode($originalFileName));

        }else{
            $contentDispositionField = 'Content-Disposition: inline';
        }
        header($contentDispositionField);

        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . ($range_max>0?($range_max-$range_min+1):filesize($filename))); //CONTENT_LENGTH
        @ob_clean();
        ob_end_flush();//flush();

        if($is_zip){
            ob_start();
            readfile($filename);
            $output = gzencode(ob_get_contents(),6); //memory overflow may happen here
            ob_end_clean();
            echo $output;
            unset($output);
        }else{

            fileReadByChunks($filename, $range_min, $range_max);
/*
            if(false && filesize($filename)<10*1024*1024){
                readfile($filename);//if less than 10MB download at once
            }else{
                $handle = fopen($filename, "rb");
                if($handle!==false){
                    if($range_max>0){
                        if($range_min>0) {fseek($handle,$range_min);}
                        $chunk = fread($handle, $range_max-$range_min+1);
                        echo $chunk;
                    }else{
                        while (!feof($handle)) {
                            echo fread($handle, 1000);//by chunks
                        }
                    }
                }else{
                    //error_log('file not found: '.htmlspecialchars($filename));
                }
            }
*/
        }
    }
}

/**
 * Downloads a file along with metadata as a ZIP file.
 *
 * @param hserv\System $system - The system object to interact with the environment.
 * @param array $fileinfo - Information about the file (obtained by fileGetFullInfo).
 * @param int $rec_ID - The record ID associated with the file.
 */
function downloadFileWithMetadata($system, $fileinfo, $rec_ID){

    // Retrieve basic file information
    $filepath = resolveFilePath($fileinfo['fullPath']);
    $external_url = $fileinfo['ulf_ExternalFileReference'];
    $mimeType = $fileinfo['fxm_MimeType'];
    $source_type = $fileinfo['ulf_PreferredSource'];
    $originalFileName = $fileinfo['ulf_OrigFileName'];
    $fileExt = $fileinfo['ulf_MimeExt'];
    $fileSize = $fileinfo['ulf_FileSizeKB'];

    $is_local = file_exists($filepath);

    //name for zip archive
    $downloadFileName = null;
    $record = array("rec_ID"=>$rec_ID);
    $system->defineConstant('DT_NAME');

    recordSearchDetails($system, $record, array(DT_NAME)); // Populate record details
    if(!empty($record['details'][DT_NAME]) && is_array($record['details'][DT_NAME])){
            $downloadFileName = USanitize::sanitizeFileName(array_values($record['details'][DT_NAME])[0]);
    }

    if(empty($downloadFileName)) {$downloadFileName = 'Dataset_'.$rec_ID;}

/*
    $finfo = pathinfo($originalFileName);
    $ext = @$finfo['extension'];
    if($ext==null || $ext==''){
        if($is_local){
            $finfo = pathinfo($filepath);//take from path
        }else{
            $finfo = array();
        }
        if(@$finfo['extension']){
            $originalFileName = $originalFileName.'.'.@$finfo['extension'];
        }elseif($fileExt){
            $originalFileName = $originalFileName.'.'.$fileExt;
        }
    }
*/

    $_tmpfile = null;

    if($external_url && strpos($originalFileName, ULF_REMOTE) === 0){ //&& strpos($originalFileName,ULF_TILED_IMAGE)!==0 && $source_type!='tiled'

        $_tmpfile = tempnam(HEURIST_SCRATCH_DIR, '_remote_');
        $filepath = $_tmpfile;
        saveURLasFile($external_url, $_tmpfile);//save to temp in scratch folder
    }

    $file_zip = $downloadFileName.'.zip';
    $file_zip_full = tempnam(HEURIST_SCRATCHSPACE_DIR, "arc");

    $zip = new ZipArchive();
    if (!$zip->open($file_zip_full, ZIPARCHIVE::CREATE)) {
        $system->errorExitApi("Cannot create zip $file_zip_full");
    }elseif(file_exists($filepath)) {
        $zip->addFile($filepath, $originalFileName);
    }

    $zip->addFromString($downloadFileName.'.txt',
                    recordLinksFileContent($system, $record));

    $zip->close();

    //remove temp file
    if($_tmpfile!=null) {
        unlink($_tmpfile);
    }

    //donwload
    $contentDispositionField = 'Content-Disposition: attachment; '
        . sprintf('filename="%s";', rawurlencode($file_zip))
        . sprintf("filename*=utf-8''%s", rawurlencode($file_zip));

    header('Content-Type: application/zip');
    header($contentDispositionField);
    header(CONTENT_LENGTH . filesize($file_zip_full));
    readfile($file_zip_full);

}

/**
 * Generates an HTML tag (e.g., `<img>`, `<video>`, `<audio>`, `<iframe>`) for displaying media content.
 *
 * Determines the appropriate HTML based on the MIME type of the file.
 * Handles local files, external URLs, and special viewers for 3D models, YouTube, Vimeo, SoundCloud, and IIIF.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param string $fileid The obfuscated file ID (ulf_ObfuscatedFileID) of the media file.
 * @param string $mimeType The MIME type of the media file.
 * @param array|null $params An associative array of parameters that can affect playback or display.
 *                           Expected keys:
 *                           - 'var': Array containing file info (e.g., from `fileGetFullInfo`), used for IIIF and 3D model detection.
 *                             Example: `['var'][0]['ulf_OrigFileName']`, `['var'][0]['ulf_PreferredSource']`, `['var'][0]['rec_ID']`.
 *                           - 'auto_play': (bool) For audio/video, if true, adds autoplay attributes.
 *                           - 'show_artwork': (int) For SoundCloud, 0 to hide artwork.
 *                           - 'fancybox': (bool) If true, wraps image in a div and adds fancybox-related attributes.
 * @param string|null $external_url The external URL of the media, if applicable. If provided and not HTTP, it's used as the source.
 *                                  Otherwise, a local URL using `fileDownload.php` (or similar) is constructed.
 * @param string|null $size (Optional) HTML size attributes (e.g., 'width="640" height="480"'). Defaults for some types if not set.
 * @param string|null $style (Optional) HTML style attribute string (e.g., 'border:1px solid red;').
 * @return string The generated HTML string for embedding the media player/viewer.
 */
function fileGetPlayerTag($system, $fileid, $mimeType, $params, $external_url, $size=null, $style=null){

    $result = '';
    $is_iiif = false;

    $is_video = (strpos($mimeType,"video/")===0);// || @$params['video']
    $is_audio = (strpos($mimeType,"audio/")===0);// || @$params['audio']
    $is_image = (strpos($mimeType,DIR_IMAGE)===0);
    if($params && is_array($params)){
        $is_iiif = (strpos(@$params['var'][0]['ulf_OrigFileName'],ULF_IIIF)===0 ||
                    strpos(@$params['var'][0]['ulf_PreferredSource'],'iiif')===0);
    }

    if($style==null) {$style='';}

    if($external_url && strpos($external_url,'http://')!==0){ //download non secure external resource via heurist
        $filepath = $external_url;  //external
    }else{
        //to itself
        $filepath = HEURIST_BASE_URL_PRO."?db=".$system->dbnameEnv()."&file=".$fileid;

        //to avoid download via proxy
        $filepath = $filepath.'&fancybox=1';
    }
    $thumb_url = HEURIST_BASE_URL_PRO."?db=".$system->dbnameEnv()."&thumb=".$fileid;

    $mode_3d_viewer = detect3D_byExt(@$params['var'][0]['ulf_MimeExt']);

    if($mode_3d_viewer!=null && $mode_3d_viewer!=''){

        $playerURL = HEURIST_BASE_URL.'hclient/widgets/viewers/'.$mode_3d_viewer.'Viewer.php?db='.$system->dbnameEnv()
                    .'&file='.$fileid;

        $result = '<a href="'.$playerURL.'" target="_blank"><img src="'.$thumb_url.'" '.$style.'/></a>';

        /* IN IFRAME
        if(($size==null || $size=='') && $style==''){
            $size = ' height="640" width="800" ';
        }
        $result = '<iframe '.$size.$style.' src="'.$playerURL.'" frameborder="0" '
            . ' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
        */
    }elseif ( $is_video ) {

        if(($size==null || $size=='') && $style==''){
            //$size = '';
            $size = 'width="640px" height="480px"';
            //$style = 'style="width:640px !important; height:480px !important"';
        }

        if ($mimeType==MT_YOUTUBE || $mimeType==MT_VIMEO
        || strpos($external_url, 'vimeo.com')>0
        || strpos($external_url, 'youtu.be')>0
        || strpos($external_url, 'youtube.com')>0)
        {

            $playerURL = getPlayerURL($mimeType, $external_url);

            $result = "<iframe $size $style src=\"$playerURL\" "
            . ' frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';

        }else{

            $autoplay = '';
            if(is_array($params) && @$params['auto_play']){
                $autoplay = ' autoplay="autoplay"  loop="" muted="" ';
            }

            //preload="none"
            $f_id = $external_url?'':$fileid;

            $result = <<<EXP
<video $autoplay $size $style controls="controls">
    <source type="$mimeType" src="$filepath" data-id="$f_id"/>
    <img src="$thumb_url" width="320" height="240" title="No video playback capabilities" />
</video>
EXP;

        }

    }
    elseif ( $is_audio )
    {

        if ($mimeType==MT_SOUNDCLOUD
        || strpos($external_url, 'soundcloud.com')>0)
        {

            if(($size==null || $size=='') && $style==''){
                $size = '';
                //$style = 'style="width:80% !important; height:166px !important"';
            }

            $playerURL = getPlayerURL($mimeType, $external_url, $params);

            $result = "<iframe $size $style src=\"$playerURL\" frameborder=\"0\"></iframe>";

        }else{

            $autoplay = '';
            if(is_array($params) && @$params['auto_play']){
                $autoplay = ' autoplay="autoplay"';
            }

            $f_id = $external_url?'':$fileid;

            $result = <<<EXP
<audio controls="controls" $autoplay>
    <source type="$mimeType" src="$filepath" data-id="$f_id"/>
    Your browser does not support the audio element
</audio>
EXP;


        }

    }elseif( $is_iiif ){

        if(($size==null || $size=='') && $style==''){
            $size = ' height="640" width="800" ';
        }

        $iiif_type = $params['var'][0]['ulf_OrigFileName'];//image or manifest

        $miradorViewer = HEURIST_BASE_URL.'hclient/widgets/viewers/miradorViewer.php?db='.$system->dbnameEnv();
        
        if(($iiif_type==ULF_IIIF_IMAGE || $params['var'][0]['ulf_PreferredSource']=='iiif_image')
            && @$params['var'][0]['rec_ID']>0){
            $miradorViewer = $miradorViewer.'&q=ids:'.intval($params['var'][0]['rec_ID']);
        }else{
            $miradorViewer = $miradorViewer.'&'.substr($iiif_type,1).'='.$fileid;
        }

        $result = "<iframe $size $style src=\"$miradorViewer\" frameborder=\"0\"></iframe>";

    }elseif($is_image){

            if(($size==null || $size=='') && $style==''){
                $size = 'width="300"';
            }
            $fancybox = '';
            if(is_array($params) && @$params['fancybox']){
                $fancybox =' class="fancybox-thumb" data-id="'.$fileid.'" ';
            }elseif(!$external_url){
                $fancybox =' data-id="'.$fileid.'" ';
            }
            $result = '<img '.$size.$style.' src="'.$filepath.'"'
                .$fancybox.'/>';



    }elseif($mimeType=='application/pdf'){
        if(($size==null || $size=='') && $style==''){
            $size = '';
            $style = 'style="width:80% !important; height:90% !important"';
        }

        $f_id = $external_url?'':$fileid;
        $result = <<<EXP
<embed width="100%" height="100%" name="plugin" src="$filepath&embedplayer=1" data-id="$f_id" type="application/pdf" internalinstanceid="9">
EXP;

    }else{
        //not media - show thumb with download link
        $result = '<a href="'.$filepath.'" target="_blank"><img src="'.$thumb_url.'" '.$style.'/></a>';

        /*
        if($size==null || $size==''){
        $size = 'width="420" height="345"';
        }
        print '<iframe '.$size.' src="'.$filepath.'" frameborder="0"></iframe>';
        */
    }

    if(is_array($params) && @$params['fancybox']){
        $result = '<div style="width:80%;height:90%">'.$result.DIV_E;
    }


    return $result;
}

/**
 * Generates the appropriate embeddable player URL for YouTube, Vimeo, or SoundCloud.
 *
 * For YouTube, it extracts the video ID and constructs an embed URL.
 * For Vimeo, it uses the oEmbed API to fetch the video ID and then constructs the player URL.
 * For SoundCloud, it constructs a player URL with options for autoplay and artwork visibility based on `$params`.
 * If the MIME type or URL doesn't match these services, the original URL is returned.
 *
 * @param string $mimeType The MIME type of the media (e.g., 'video/youtube', 'video/vimeo', 'audio/soundcloud').
 * @param string $url The original URL of the media.
 * @param array|null $params (Optional) Associative array of parameters, primarily for SoundCloud:
 *                           - 'auto_play': (bool) If true, enables autoplay for SoundCloud.
 *                           - 'show_artwork': (int) If 0, hides artwork for SoundCloud.
 * @return string The embeddable player URL.
 */
function getPlayerURL($mimeType, $url, $params=null){

    if( $mimeType == MT_YOUTUBE
            || strpos($url, 'youtu.be')>0
            || strpos($url, 'youtube.com')>0){ //match('https://(www.)?youtube|youtu\.be')

        $url = 'https://www.youtube.com/embed/'.youtube_id_from_url($url);

    }elseif( $mimeType == MT_VIMEO || strpos($url, 'viemo.com')>0){

        $hash = json_decode(loadRemoteURLContent("https://vimeo.com/api/oembed.json?url=".rawurlencode($url), false), true);//get vimeo video id
        $video_id = @$hash['video_id'];
        if($video_id>0){
           $url =  'https://player.vimeo.com/video/'.$video_id;
        }
    }elseif( $mimeType == MT_SOUNDCLOUD || strpos($url, 'soundcloud.com')>0){


        $autoplay = 'false';
        if($params && @$params['auto_play']){
            $autoplay = 'true';
        }
        $autoplay = '&amp;auto_play='.$autoplay;

        $show_artwork = 'true';
        if($params && @$params['show_artwork']==0){
            $show_artwork = 'false';
        }
        $show_artwork = '&amp;show_artwork='.$show_artwork;

        return 'https://w.soundcloud.com/player/?url='.$url
                .$autoplay.'&amp;hide_related=false&amp;show_comments=false&amp;show_user=false&amp;'
                .'show_reposts=false&amp;show_teaser=false&amp;visual=true'.$show_artwork;
    }

    return $url;
}

/**
 * Checks if a filename indicates that it is not a locally stored file.
 *
 * This is determined by checking if the original filename (`$origName`) starts with
 * specific prefixes defined as constants:
 * - `ULF_REMOTE` (e.g., "_REMOTE_") for general remote files.
 * - `ULF_IIIF` (e.g., "_IIIF_") for IIIF resources.
 * - `ULF_TILED_IMAGE` (e.g., "_TILED_") for tiled images.
 *
 * @param string|null $origName The original filename to check.
 * @return bool True if the filename suggests a remote or specially handled file, false otherwise.
 */
function isNotLocalFile($origName){
    if ($origName === null) {
        return false; // Or true, depending on desired behavior for null input
    }
    return strpos($origName, ULF_REMOTE) === 0 || // skip if not local file
           strpos($origName, ULF_IIIF) === 0 ||
           strpos($origName, ULF_TILED_IMAGE) === 0;
}

/**
 * Create a png version of an image for website usage
 *  Only performs this if the file is greater than 500 KB
 *  Also, scales the image down to at most 1000x1000 pixels
 *
 * @param hserv\System $system - initialised Heurist system object
 * @param $fileinfo - data obtained by fileGetFullInfo
 * @param bool $returnURL - return url to file instead of file path
 * @param bool $forceRefresh - refresh the web cached image, if it exists
 *
 * @return bool | string - false on error, or path or url for cached image
 */
function getWebImageCache($system, $fileinfo, $returnURL = true, $forceRefresh = false){

    $skip_file = isNotLocalFile(@$fileinfo['ulf_OrigFileName']);

    if($skip_file || @$fileinfo['ulf_FileSizeKB'] < 500){ // skip
        $system->addError(HEURIST_ACTION_BLOCKED, 'File skipped');
        return false;
    }

    $file_path = resolveFilePath( @$fileinfo['fullPath'] );
    if(!file_exists($file_path)){
        $system->addError(HEURIST_ACTION_BLOCKED, 'Unable to locate file within filestore');
        return false;
    }

    $web_cache_dir = $system->getSysDir(DIR_WEBIMAGECACHE);

    $swarn = folderCreate2($web_cache_dir,'(for cached web images)', true);

    if($swarn!=''){
        $system->addError(HEURIST_ERROR, $swarn);
        return false;
    }

    //direct url to filestore folder
    $file_path_info = pathinfo($file_path);

    //return basename with extension
    $file_name_cached = "{$file_path_info['filename']}.jpg";

    $file_url_cached = $system->getSysUrl(DIR_WEBIMAGECACHE).$file_name_cached;
    $file_path_cached =  "{$web_cache_dir}/{$file_name_cached}";
    $fileExists = file_exists($file_path_cached);

    if($fileExists && $forceRefresh){ // force a refresh
        fileDelete($file_path_cached);
        $fileExists = false;
    }

    if(!$fileExists){ // already exists
        $res = UImage::createScaledImageFile($file_path, $file_path_cached, 1000, 1000, false, 'jpg');
    }

    $scaledFileSize = $res === true ? filesize($file_path_cached) : 0;
    $fileSizeLimit = floor(($fileinfo['ulf_FileSizeKB'] * 1024) / 2);
    if($res === true && !$forceRefresh && $scaledFileSize >= $fileSizeLimit){
        // if scaled image is more than half the size of original, use original
        $res = false;
    }

    if($res===true){
        return $returnURL ? $file_url_cached : $file_path_cached;
    }else{
        return false;
    }
}

/**
 * Create a blurred png version of an image that is not for public view
 *  Also overlays hclient/assests/100x100-login-required.png
 *
 * @param hserv\System $system - initialised Heurist system object
 * @param array $file_info - data obtained by fileGetFullInfo
 * @param bool $return_url - return url to file instead of file path
 * @return bool | string - false on failure, otherwise either the url or pathway to the image
 */
function getBlurredImage($system, $file_info, $return_url = true){

    // skip if not local file
    $skip_combine = isNotLocalFile(@$file_info['ulf_OrigFileName']);

    $is_public = @$file_info['ulf_WhoCanView'] !== 'loginrequired';

    $message_path = __DIR__."/../../../hclient/assets/100x100-login-required.png";

    if($is_public || $skip_combine || !file_exists($message_path)){
        return false;
    }

    $file_path = resolveFilePath(@$file_info['fullPath']);
    if(!file_exists($file_path)){
        $system->addError(HEURIST_ACTION_BLOCKED, 'Unable to determine file location');
        return false;
    }

    $file_path_info = pathinfo($file_path);

    $blur_file_dir = $system->getSysDir(DIR_BLURREDIMAGECACHE);
    $res = folderCreate2($blur_file_dir, '(for blurred due to visibility settings)', true);
    if(!empty($res)){
        $system->addError(HEURIST_ERROR, $res);
        return false;
    }

    $blur_file_name = "{$file_path_info['filename']}.png";
    $blur_file_path = "{$blur_file_dir}/{$blur_file_name}";
    $blur_file_url = $system->getSysUrl(DIR_BLURREDIMAGECACHE). $blur_file_name;
    $scaled_message = "{$blur_file_dir}/scaled_msg.png";

    $_cleanup_files = function() use ($blur_file_path, $scaled_message){
        fileDelete($blur_file_path);
        fileDelete($scaled_message);
    };

    if(file_exists($blur_file_path)){
        return $return_url ? $blur_file_url : $blur_file_path;
        //DEBUG fileDelete($blur_file_path)
    }

    // Scale down to 800x800
    $res = UImage::createScaledImageFile($file_path, $blur_file_path, 800, 800, false);
    if($res !== true){
        return false;
    }

    $blur_png = UImage::safeLoadImage($blur_file_path, 'image/png');
    if(!$blur_png){
        return false;
    }
    imagealphablending($blur_png, false);
    imagesavealpha($blur_png, true);

    // Add blur effect, needs to only be slightly blurry
    for($i = 0; $i < 50; $i++){
        imagefilter($blur_png, IMG_FILTER_GAUSSIAN_BLUR);
    }

    // Get dimensions
    $width = imagesx($blur_png);
    $height = imagesy($blur_png);

    // Scale message
    $res = UImage::createScaledImageFile($message_path, $scaled_message, $width, $height, false);
    if($res !== true){
        $_cleanup_files();
        return false;
    }

    $message_png = UImage::safeLoadImage($scaled_message, 'image/png');
    if(!$message_png){
        $_cleanup_files();
        return false;
    }
    imagealphablending($message_png, false);
    imagesavealpha($message_png, true);

    // Add and enlarge overlay, w/ opacity
    $src_width = imagesx($message_png);
    $src_height = imagesy($message_png);
    $res = imagecopymerge($blur_png, $message_png, 0, 0, 0, 0, $src_width, $src_height, 85);
    //$res = imagecopyresampled($blur_png, $message_png, 0, 0, 0, 0, 100, 100, 100, 100); much slower
    fileDelete($scaled_message);

    if(!$res){
        $_cleanup_files();
        return false;
    }

    $res = imagepng($blur_png, $blur_file_path); // save image
    if(!$res){
        $_cleanup_files();
        return false;
    }

    // Free memory
    imagedestroy($message_png);
    imagedestroy($blur_png);

    return $return_url ? $blur_file_url : $blur_file_path;
}

/**
 * Extracts the YouTube video ID from various YouTube URL formats.
 *
 * Uses a regular expression to match standard watch URLs, shortener URLs (youtu.be),
 * embed URLs, and others.
 *
 * @param string $url The YouTube URL.
 * @return string|false The extracted YouTube video ID if found, otherwise potentially an empty string or behavior dependent on regex.
 *                      The current regex `([^\?&\"'>]+)` will capture the ID part.
 *                      It should ideally return false or null if no match.
 */
function youtube_id_from_url($url) {
/*
    $pattern =
        '%^# Match any youtube URL
        (?:https?://)?  # Optional scheme. Either http or https
        (?:www\.)?      # Optional www subdomain
        (?:             # Group host alternatives
          youtu\.be/    # Either youtu.be,
        | youtube\.com  # or youtube.com
          (?:           # Group path alternatives
            /embed/     # Either /embed/
          | /v/         # or /v/
          | /watch\?v=  # or /watch\?v=
          )             # End path alternatives.
        )               # End host alternatives.
        ([\w-]{10,12})  # Allow 10-12 for 11 char youtube id.
        $%x'
        ;

    //$url = urldecode(rawurldecode($_GET["q"]));
    $result = preg_match($pattern, $url, $matches);
    if ($result) {
        return $matches[1];
    }
    return false;
*/
    # https://www.youtube.com/watch?v=nn5hCEMyE-E
    preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);
    return $matches[1];
}


/**
 * Retrieves and outputs metadata for a registered media file as a JSON response.
 * For image files (excluding tiled images), it attempts to get width and height.
 *
 * The output JSON will have a 'status' and 'data' field. 'data' contains:
 * - 'mimetype': The MIME type of the file.
 * - 'original_name': The original filename.
 * - 'size_KB': File size in kilobytes.
 * - 'description': File description.
 * - 'width', 'height': (For images) Dimensions of the image.
 * - 'error': (If image dimensions cannot be obtained) An error message string.
 *
 * @param array $fileinfo An associative array containing file information, typically from `fileGetFullInfo()`.
 *                        Expected keys: 'fullPath', 'ulf_ExternalFileReference', 'fxm_MimeType',
 *                        'ulf_OrigFileName', 'ulf_PreferredSource', 'ulf_FileSizeKB', 'ulf_Description'.
 * @return void Outputs JSON directly and terminates script execution.
 */
function fileGetMetadata($fileinfo){

    $filepath = $fileinfo['fullPath'] ?? null; // Resolved full path for local files
    $external_url = $fileinfo['ulf_ExternalFileReference'] ?? null;
    $mimeType = $fileinfo['fxm_MimeType'] ?? null;
    $originalFileName = $fileinfo['ulf_OrigFileName'] ?? null;
    $sourceType = $fileinfo['ulf_PreferredSource'] ?? null;

    $type_media = null;
    // $ext = null; // $ext is not used
    if($mimeType && strpos($mimeType, '/')!==false){
        list($type_media) = explode('/', $mimeType, 2); // Get only the main type
    }

    $image_resource = null; // Stores GD image resource or similar from UImage
    $alt_image_size = null; // Stores array from getimagesize()

    $res = array(); // Array to hold metadata, including dimensions or error

    // Check if it's an image and not a special tiled image type
    if($originalFileName !== null && strpos($originalFileName, ULF_TILED_IMAGE) !== 0 && $sourceType !== 'tiled' && $type_media === 'image'){
        if($filepath && file_exists($filepath)){ // Local file
            $image_resource = UImage::getImageFromFile($filepath);
            if (!$image_resource) { // Fallback if getImageFromFile fails but file exists
                $alt_image_size = @getimagesize($filepath);
            }
        } elseif($external_url){ // Remote file
            $image_resource = UImage::getRemoteImage($external_url);
        }

        if($image_resource){
            try{
                $imgw = imagesx($image_resource);
                $imgh = imagesy($image_resource);
                $res['width'] = $imgw;
                $res['height'] = $imgh;
                if (is_resource($image_resource) || $image_resource instanceof \GdImage) { // Check if it's a GD resource
                    imagedestroy($image_resource);
                }
            } catch(Exception $e){
                $res['error'] = 'Cannot get image dimensions from resource.';
            }
        } elseif($alt_image_size && is_array($alt_image_size)){ // Dimensions from getimagesize
            $res['width'] = $alt_image_size[0];
            $res['height'] = $alt_image_size[1];
        } else {
            $res['error'] = 'Image is not loaded or dimensions not retrievable.';
        }
    }

    // Populate common metadata
    $res['mimetype'] = $mimeType;
    $res['original_name'] = $originalFileName;
    $res['size_KB'] = $fileinfo['ulf_FileSizeKB'] ?? null;
    $res['description'] = $fileinfo['ulf_Description'] ?? null;

    header(CTYPE_JSON);
    $response = array('status'=>HEURIST_OK, 'data'=>$res);
    echo json_encode($response); // Changed print to echo for consistency
    exit; // Terminate script after outputting JSON
}

/**
 * Creates or recreates a thumbnail for a specified uploaded file.
 *
 * Handles various file types:
 * - Local images: Resizes them. Handles EXIF orientation.
 * - PDFs: Generates a thumbnail using `UImage::getPdfThumbnail()`.
 * - Remote images: Fetches and resizes.
 * - YouTube, Vimeo, SoundCloud: Fetches standard thumbnail images from their APIs/URL patterns.
 * - IIIF: Uses `UImage::getIiifThumbnail()`.
 * - Tiled Images: Creates a placeholder string image "tiled images stack".
 * - Other types: Creates a placeholder string image with the file extension.
 *
 * If `$is_download` is true, the generated thumbnail is output directly to the browser with an image/png header.
 * Otherwise, the thumbnail is saved to the `HEURIST_THUMB_DIR`.
 *
 * @param \hserv\System $system The Heurist system object.
 * @param int|string $fileid The `ulf_ID` (numeric) or `ulf_ObfuscatedFileID` (string) of the file.
 * @param bool $is_download If true, outputs the thumbnail image directly to the browser.
 *                          Otherwise, saves it to the thumbnail directory.
 * @return void Outputs image or redirects if `$is_download` is true.
 */
function fileCreateThumbnail( $system, $fileid, $is_download ){

    $img = null; //image to be resized
    $file = fileGetFullInfo($system, $fileid, true); // Request all fields just in case
    $placeholder = '../../hclient/assets/100x100.gif';
    $thumbnail_file = null;
    $orientation = 0;

    if($file!==false){
        $file = $file[0];

        $thumbnail_file = HEURIST_THUMB_DIR."ulf_".$file['ulf_ObfuscatedFileID'].".png";

        if(@$file['ulf_ExternalFileReference']==null || @$file['ulf_ExternalFileReference']==''){  //local

            if (@$file['fullPath']){
                $filename = $file['fullPath'];
            }elseif (@$file['ulf_FileName']) {
                $filename = $file['ulf_FilePath'].$file['ulf_FileName'];// post 18/11/11 proper file path and name
            } else {
                $filename = HEURIST_FILESTORE_DIR . $file['ulf_ID'];// pre 18/11/11 - bare numbers as names, just use file ID
            }
            $filename = str_replace('/../', '/', $filename);

            //add database media storage folder for relative paths
            $filename = resolveFilePath($filename);
        }


        if (isset($filename) && file_exists($filename)){ //original file exists

            $mimeExt = '';
            if ($file['ulf_MimeExt']) {
                $mimeExt = $file['ulf_MimeExt'];
            } else {
                preg_match('/\\.([^.]+)$/', $file["ulf_OrigFileName"], $matches);//find the extention
                $mimeExt = $matches[1];
            }

            //special case for pdf
            if($mimeExt=='application/pdf' || $mimeExt=='pdf'){
                UImage::getPdfThumbnail($filename, $thumbnail_file);
                
            }else{

                //get real image type from exif
                $mimeExt = UImage::getImageType($filename);
                //get orientation based on exif
                $orientation = UImage::getImageOrientation($filename);

                $errorMsg = UImage::checkMemoryForImage($filename, $mimeExt);

                if($errorMsg){
                    //database, record ID and name of bad image
                    sendEmail(HEURIST_MAIL_TO_ADMIN, 'Cant create thumbnail image. DB:'.$system->dbname(),
                            'File ID#'.$file['ulf_ID'].'  '.$filename.'. '.$errorMsg);

                    $img = UImage::createFromString('Thumbnail not created. '.$errorMsg);

                }else{

                    //load content
                    $img = UImage::safeLoadImage($filename, $mimeExt);

                    if($img===false){
                        //this is not an image
                        $desc = '***' . strtoupper(preg_replace('/.*[.]/', '', $file['ulf_OrigFileName'])) . ' file';
                        $img = UImage::createFromString($desc);//from string
                    }

                }

            }

        }
        elseif(@$file['ulf_ExternalFileReference']){  //remote

            // image placeholder
            $placeholder = '../../hclient/assets/200x200-warn.gif';
        
            if(@$file['ulf_OrigFileName'] &&
                (strpos($file['ulf_OrigFileName'],ULF_TILED_IMAGE)===0 || @$file['ulf_PreferredSource']=='tiled') )  {

                $img = UImage::createFromString('tiled images stack');//from string
                
            }else if($file['ulf_MimeExt']=='json' &&  strpos($file['ulf_OrigFileName'],ULF_IIIF)===0){
                
                $thumbUrl = UImage::getIiifThumbnail($file['ulf_ExternalFileReference'], null, $thumbnail_file);
                
                if($is_download){
                    if(file_exists($thumbnail_file)){
                        header('Content-type: image/png');
                        echo file_get_contents($thumbnail_file);
                    }else{
                        redirectURL($placeholder);
                    }
                }
                
                return;

            }elseif(@$file['fxm_MimeType'] && strpos($file['fxm_MimeType'], 'image/')===0){
                //@todo for image services (flikr...) take thumbnails directly
                $img = UImage::getRemoteImage($file['ulf_ExternalFileReference'], $orientation);

            }elseif( @$file['fxm_MimeType'] == MT_YOUTUBE
                || strpos($file['ulf_ExternalFileReference'], 'youtu.be')>0
                || strpos($file['ulf_ExternalFileReference'], 'youtube.com')>0){ //match('https://(www.)?youtube|youtu\.be')

                //@todo - youtube changed the way of retrieving thumbs !!!!
                $url = $file['ulf_ExternalFileReference'];

                preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);

                $youtubeid = $matches[1];

                $img = UImage::getRemoteImage('https://img.youtube.com/vi/'.$youtubeid.'/default.jpg');

                //$youtubeid = preg_replace('/^[^v]+v.(.{11}).*/' , '$1', $url);
                //$img = get_remote_image("http://img.youtube.com/vi/".$youtubeid."/0.jpg");//get thumbnail
            }elseif($file['fxm_MimeType'] == MT_VIMEO){

                $url = $file['ulf_ExternalFileReference'];

                $hash = json_decode(loadRemoteURLContent("https://vimeo.com/api/oembed.json?url=".rawurlencode($url), false), true);//get vimeo thumbnail
                $thumb_url = @$hash['thumbnail_url'];
                if($thumb_url){
                    $img = UImage::getRemoteImage($thumb_url);
                }

                //it works also - except regex is wrong for some vimeo urls
                /*
                if(preg_match('(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([??0-9]{6,11})[?]?.*', $url, $matches)>0){
                    $vimeo_id = $matches[5];
                    $hash = unserialize(file_get_contents("http://vimeo.com/api/v2/video/$vimeo_id.php"));
                    $thumb_url = $hash[0]['thumbnail_medium'];
                    $img = UImage::getRemoteImage($thumb_url);
                }
                */
            }elseif($file['fxm_MimeType'] == MT_SOUNDCLOUD){

                $url = $file['ulf_ExternalFileReference'];

                $hash = json_decode(loadRemoteURLContent('https://soundcloud.com/oembed?format=json&url='   //get soundcloud thumbnail
                                .rawurlencode($url), false), true);
                $thumb_url = @$hash['thumbnail_url'];
                if($thumb_url){
                    $img = UImage::getRemoteImage($thumb_url);
                }else{
                    $img = '../../hclient/assets/branding/logo_soundcloud.png';
                }
            }


        }
    }

    if(!$img){
        if($is_download){
            redirectURL($placeholder);
        }
    }else{
        UImage::resizeImage($img, $thumbnail_file, 200, 200, $orientation);//$img will be destroyed inside this function
        if($is_download){
            if(file_exists($thumbnail_file)){
                header('Content-type: image/png');
                echo file_get_contents($thumbnail_file);
            }else{
                redirectURL($placeholder);
            }
        }
    }
}

/**
 * Detects the appropriate 3D viewer type based on file extension.
 *
 * - Returns '3dhop' for 'nxz' or 'nxs' extensions.
 * - Returns '3d' for a list of common 3D model extensions (obj, 3ds, stl, etc.).
 * - Returns an empty string if the extension does not match known 3D types.
 *
 * @param string|null $fileExt The file extension to check.
 * @return string The viewer type ('3dhop', '3d') or an empty string.
 */
function detect3D_byExt($fileExt){

    $mode_3d_viewer = '';

    if($fileExt=='nxz' || $fileExt=='nxs'){
        $mode_3d_viewer = '3dhop';
    }else {
        $allowed_exts = array('obj', '3ds', 'stl', 'ply', 'gltf', 'glb', 'off', '3dm', 'fbx', 'dae', 'wrl', '3mf', 'ifc', 'brep', 'step', 'iges', 'fcstd', 'bim');
        if(in_array($fileExt, $allowed_exts)){
            $mode_3d_viewer = '3d';
        }
    }

    return $mode_3d_viewer;
}


/**
 * Calculates total disk usage by scanning standard file storage directories.
 *
 * Specifically, it calculates the size of `HEURIST_FILES_DIR` (typically 'file_uploads')
 * and `HEURIST_TILESTACKS_DIR` (typically 'uploaded_tilestacks') and returns their sum.
 *
 * @param \hserv\System $system The Heurist system object (currently unused in the function body, but kept for signature consistency).
 * @return int The total disk usage in bytes.
 */
function filestoreGetUsageByScan($system){

    // Note: $system parameter is not used in the current implementation of this function.
    // Original comments about $dir_root were:
    // HEURIST_FILESTORE_ROOT.$db_name.'/';
    // $dir_root = HEURIST_FILESTORE_DIR;
    //$dir_files = $dir_root.'file_uploads/';
    //$dir_tiles = $dir_root.'uploaded_tilestacks/';

    $sz1 = folderSize2(HEURIST_FILES_DIR);
    $sz2 = folderSize2(HEURIST_TILESTACKS_DIR);

    return $sz1+$sz2;
}

/**
 * Calculates disk usage for specified media folders, plus standard 'file_uploads' and 'uploaded_tilestacks'.
 *
 * It reads folder paths from the system setting 'sys_MediaFolders' (semicolon-separated).
 * For each valid folder path, it calculates its size using `folderSize2`.
 * It always includes the sizes of `HEURIST_FILES_DIR` and `HEURIST_TILESTACKS_DIR`.
 *
 * @param \hserv\System $system The Heurist system object, used to get settings.
 * @return array An associative array where keys are folder names (relative to `HEURIST_FILESTORE_DIR`
 *               or 'file_uploads', 'uploaded_tilestacks') and values are their sizes in bytes.
 */
function filestoreGetUsageByFolders($system){

    $mediaFolders_setting = $system->settings->get('sys_MediaFolders');
    if($mediaFolders_setting==null || $mediaFolders_setting == ''){ // Default if not defined
        $mediaFolders_setting = 'uploaded_files'; // This default seems unlikely to be a valid path segment.
                                             // It might intend to refer to the standard file_uploads,
                                             // but the logic below adds HEURIST_FILES_DIR separately.
    }
    $mediaFolders = explode(';', $mediaFolders_setting);
    $dirs = array();

    foreach ($mediaFolders as $dir_segment){
        if( $dir_segment && $dir_segment!="*") { // Ignore wildcard or empty segments
            $full_dir_path = USanitize::sanitizePath(HEURIST_FILESTORE_DIR.$dir_segment);
            if($full_dir_path){ // If path is valid after sanitization
                // Use the original segment as key, as it might be more descriptive than the sanitized path part
                $dirs[$dir_segment] = folderSize2($full_dir_path);
            }
        }
    }

    // Always include standard directories
    $dirs['file_uploads'] = folderSize2(HEURIST_FILES_DIR);
    $dirs['uploaded_tilestacks'] = folderSize2(HEURIST_TILESTACKS_DIR);

    return $dirs;
}

/**
 * Calculates total disk usage by summing the `ulf_FileSizeKB` column in `recUploadedFiles`.
 *
 * Note: This method relies on the accuracy of the stored file sizes in the database.
 * The result is in Kilobytes (KB).
 *
 * @param \hserv\System $system The Heurist system object, used to get the database connection.
 * @return int|null The total disk usage in KB as per database records, or null if query fails.
 */
function filestoreGetUsageByDb($system){

    $mysqli = $system->getMysqli();
    $res =  mysql__select_value($mysqli, 'SELECT SUM(ulf_FileSizeKB) FROM recUploadedFiles');

    return $res ? (int)$res : null; // Cast to int or return null
}

/**
 * Replaces occurrences of old file IDs (`$ulf_ids_replaced`) with a new file ID (`$ulf_id`)
 * in the `recDetails.dtl_UploadedFileID` column, and then deletes the records of the old files
 * from `recUploadedFiles`. This is used for de-duplication.
 *
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param int $ulf_id The `ulf_ID` of the file record to keep (the target of the replacement).
 * @param array|string $ulf_ids_replaced An array or comma-separated string of `ulf_ID`s to be replaced and deleted.
 * @return void
 */
function filestoreReplaceDuplicatesInDetails($mysqli, $ulf_id, $ulf_ids_replaced){

    // Ensure $ulf_ids_replaced is an array of sanitized integers
    $ids_to_replace_sanitized = prepareIds($ulf_ids_replaced);

    if (empty($ids_to_replace_sanitized)) {
        return; // No valid IDs to replace
    }

    $ids = implode(',', $ids_to_replace_sanitized);

    $upd_query = 'UPDATE recDetails set dtl_UploadedFileID='.intval($ulf_id).' WHERE dtl_UploadedFileID in ('.$ids.')';
    $del_query = 'DELETE FROM recUploadedFiles where ulf_ID in ('.$ids.')';

    $mysqli->query($upd_query);
    $mysqli->query($del_query);
}

function checkForExternalServer( $req_params ){
    global $envVersion, $externalServer;
         
    if(isset($envVersion) && ($externalServer ?? '') !== ''){
        // rebuild URL
        $req_params['db'] = $db;
        $url = $externalServer . '/hserv/controller/fileDownload.php?' . http_build_query($req_params);        
        redirectURL( $url );
    }
}
?>
