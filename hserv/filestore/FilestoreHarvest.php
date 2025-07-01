<?php
/**
* FilestoreHarvest.php - Class FilestoreHarvest
* 
* Searches and indexes (registers in recUploadedFiles) files in specified folders
*
* @project     Heurist academic knowledge management system
* @package Core
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
namespace hserv\filestore;
use hserv\utilities\USanitize;

/**
 * Class FilestoreHarvest
 *
 * Searches for files within specified directories of the Heurist database filestore.
 * It can categorize these files as registered (present in `recUploadedFiles`) or
 * non-registered based on database lookups. This is typically used for media
 * indexing and management tasks.
 */
class FilestoreHarvest
{
    /** @var \hserv\System The main Heurist system object. */
    private $system;
    
    /** @var mixed Stores issues found during reporting (currently not fully utilized in provided code). */
    private $rep_issues;
    /** @var array Stores information about registered and non-registered files.
         *             Format: `['reg' => [...filenames...], 'nonreg' => [...filenames...]]`
         */
    private $reg_info;

    /**
     * Constructor for FilestoreHarvest.
     *
     * Initializes the system object and resets report/registration info containers.
     *
     * @param \hserv\System $system The main Heurist system object.
     */
    public function __construct( $system ) {
        
        $this->system = $system;
        
        $this->rep_counter = null;
        $this->rep_issues = null;
        $this->reg_info = array('reg'=>array(),'nonreg'=>array());
    }

    //
    // return folders and extents to index
    //
    /**
     * Retrieves the list of media folders and allowed file extensions for indexing.
     *
     * Reads `sys_MediaFolders` and `sys_MediaExtensions` settings from the database.
     * If not set, defaults to the 'uploaded_files' directory and `HEURIST_ALLOWED_EXT`.
     * Ensures 'file_uploads' is always included. Folder paths are sanitized.
     *
     * @return array An associative array with 'dirs' (array of folder paths)
     *               and 'exts' (array of allowed extensions).
     */
    public function getMediaFolders() {
        
        $mediaFolders = $this->system->settings->get('sys_MediaFolders');
        $mediaExts = $this->system->settings->get('sys_MediaExtensions'); //user define list - what is allowed to index

        if($mediaFolders==null || $mediaFolders == ''){ // by default
            $mediaFolders = $this->system->getSysDir('uploaded_files');
            folderCreate( $mediaFolders, true );
        }
       
        $mediaFolders = explode(';', $mediaFolders);// get an array of folders

        //always include file_uploads
        if(!in_array('file_uploads', $mediaFolders)){
                $mediaFolders[] = 'file_uploads';
        }
        
        //sanitize folder names
        $mediaFolders = array_map(array('hserv\utilities\USanitize', 'sanitizePath'), $mediaFolders);

        // The defined list of file extensions for FieldHelper indexing.
        if($mediaExts==null || $mediaExts==''){
            $mediaExts = HEURIST_ALLOWED_EXT;
        }

        $mediaExts = explode(',', $mediaExts);

        if (empty($mediaFolders)) {
            //It seems that there are no media folders specified for this database
            $dirs = array($this->system->getSysDir('file_uploads'));// default to the data folder for this database
        }

        return array('dirs'=>$mediaFolders, 'exts'=>$mediaExts);
    }
    
    /**
     * Returns the currently stored registration information.
     *
     * This information is populated by `getFilesInDir` and `doHarvest`.
     *
     * @return array An associative array: `['reg' => [...registered files...], 'nonreg' => [...non-registered files...]]`.
     */
    public function getRegInfoResult(){
        return $this->reg_info;
    }
    
    
    //
    // fills reg_info array with registered and non-registered files
    // $imode
    // 0 - all
    // 1 - reg and unreg separately
    //
    /**
     * Scans a directory for files and categorizes them based on registration status and mode.
     *
     * Populates `$this->reg_info` with found files.
     * Skips directories, specific system files (fieldhelper.xml, index.html, .htaccess),
     * and files not matching `$mediaExts`.
     *
     * @param string $dir The directory path to scan.
     * @param array $mediaExts An array of allowed file extensions (lowercase).
     * @param int $imode Categorization mode:
     *                   - 0: Adds all matching files to `$this->reg_info` (assumes flat list, current usage might differ).
     *                   - 1: Categorizes files into `$this->reg_info['reg']` (registered in `recUploadedFiles`)
     *                        and `$this->reg_info['nonreg']` (not registered). Checks to avoid adding thumbnails
     *                        of already processed non-registered files.
     * @return void
     */
    private function getFilesInDir($dir, $mediaExts, $imode) {

        $all_files = scandir($dir);

        foreach ($all_files as $filename){

            if(is_dir($dir.$filename) || $filename=="." || $filename==".."
                || $filename=="fieldhelper.xml" || $filename=="index.html" || $filename==".htaccess"){
                continue;
            }

            $filename_base = $filename;
            $filename = $dir.$filename;
            $flleinfo = pathinfo($filename);

            //checks for allowed extensions
            if(in_array(strtolower(@$flleinfo['extension']),$mediaExts)){

                if($imode==1){

                    //find file in dbRecUploadedFiles by name    
                    $file_id = fileGetByFileName( $this->system, $filename );//see recordFile.php

                    if($file_id <= 0 && strpos($filename, "/thumbnail/$filename_base") !== false){
                        //Check if this is just a thumbnail version of an image

                        $temp_name = str_replace("thumbnail/$filename_base", $filename_base, $filename);

                        if(in_array($temp_name, $this->reg_info['nonreg'])){
                            continue;
                        }
                    }

                    if($file_id>0){
                        array_push($this->reg_info['reg'], $filename);
                    }else{
                        array_push($this->reg_info['nonreg'], $filename);
                    }

                }else{
                    array_push($this->reg_info, $filename);
                }
            }
        }  //for all_files
    }    

    //
    // $imode - 0 - registration
    //          1 - get registered and nonreg files
    // folders "thumbnail" will be skipped
    //
    /**
     * Recursively harvests files from specified directories based on allowed extensions.
     *
     * Populates `$this->reg_info` by calling `getFilesInDir` for each valid directory.
     * Skips system folders unless explicitly allowed, and skips 'thumbnail' subdirectories.
     * Can optionally print report messages during harvesting if `$is_report` is true.
     *
     * @param array $dirs_and_exts An associative array from `getMediaFolders()`:
     *                             `['dirs' => [...folder paths...], 'exts' => [...allowed extensions...]]`.
     * @param bool $is_report If true, prints status/error messages during processing.
     * @param int $imode Mode passed to `getFilesInDir` (0 for all, 1 for reg/nonreg categorization).
     * @param array|null $allowed_system_folders Optional array of system folder names that are allowed to be scanned.
     *                                           Defaults to `['file_uploads']`.
     * @return void
     */
    public function doHarvest($dirs_and_exts, $is_report, $imode, $allowed_system_folders=null) {

        $this->reg_info = array('reg'=>array(),'nonreg'=>array());
        
        if($allowed_system_folders==null){
            $allowed_system_folders = ['file_uploads'];
        }

        $db_folder = $this->system->getSysDir();
        //get exclusion list of system subfolders - where user's files don't exist
        $system_folders = $this->system->getSystemFolders();

        $dirs = $dirs_and_exts['dirs'];
        $mediaExts = $dirs_and_exts['exts'];

        foreach ($dirs as $dir){

            if($dir=="*"){

                $dir = $db_folder;

            }else{

                $dir = USanitize::sanitizePath($dir);

                $real_path = isPathInHeuristUploadFolder($dir, true);

                if(!$real_path){
                    if($is_report){
                        print errorDiv(htmlspecialchars($dir).' is ignored. Folder '
                        (($real_path==null)?'does not exist':'must be in Heurist filestore directory'));
                    }
                    continue;
                }

                if(substr($dir, -1) != '/'){
                    $dir .= "/";
                }

            }

            $is_allowed = is_array($allowed_system_folders) && !empty($allowed_system_folders) && in_array($dir, $allowed_system_folders);

            if(!$is_allowed && in_array($dir, $system_folders)){

                if($is_report){
                    print "<div style=\"color:red\">Files are not scanned in system folder $dir</div>";
                }

            }elseif($dir && file_exists($dir) && is_dir($dir))
            {

                $files = scandir($dir);
                if(!isEmptyArray($files))
                {
                    $subdirs = array();

                    $isfirst = true;

                    foreach ($files as $filename){

                        if(!($filename=="." || $filename=="..")){
                            if(is_dir($dir.$filename)){
                                $subdir = $dir.$filename."/";
                                if($filename!='thumbnail' && !in_array($subdir, $system_folders)){
                                        array_push($subdirs, $subdir);
                                }
                            }elseif($isfirst){ //if($filename == "fieldhelper.xml"){
                                $isfirst = false;
                                if($dir == $db_folder){
                                    if($is_report){
                                        print "<div style=\"color:red\">Files are not scanned in root upload folder $dir</div>";
                                    }
                                }else{
                                    $this->getFilesInDir($dir, $mediaExts, $imode);
                                }
                            }
                        }
                    }

                    if(!empty($subdirs)){

                        $this->doHarvest(array("dirs"=>$subdirs, "exts"=>$mediaExts), $is_report, $imode);
                        if($is_report) {flush();}
                    }
                }
            }elseif($dir) {
                if($is_report){
                    print "<div style=\"color:red\">Folder was not found: $dir</div>";
                }
            }
        }
    } //doHarvest    
    


    //
    // @todo - move code here from syncWithFieldHelper
    /*
    function doHarvestInDir($dir) {

    }
    */


    
}
