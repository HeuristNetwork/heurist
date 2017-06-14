<?php
  
    $system_folders = array(HEURIST_THUMB_DIR,
        HEURIST_ICON_DIR,
        HEURIST_FILES_DIR,
        HEURIST_FILESTORE_DIR."backup/",
        HEURIST_FILESTORE_DIR.'documentation_and_templates/',
        HEURIST_FILESTORE_DIR."faims/",
        HEURIST_FILESTORE_DIR."generated-reports/",
        HEURIST_FILESTORE_DIR."scratch/",
        HEURIST_FILESTORE_DIR."settings/",
        HEURIST_FILESTORE_DIR.'term-icons/',
        HEURIST_SMARTY_TEMPLATES_DIR,
        HEURIST_XSL_TEMPLATES_DIR);
    if(defined('HEURIST_HTML_DIR')) array_push($system_folders, HEURIST_HTML_DIR);
    if(defined('HEURIST_HML_DIR')) array_push($system_folders, HEURIST_HML_DIR);

    $rep_counter = null;
    $rep_issues = null;
    $reg_info = array('reg'=>array(),'nonreg'=>array());
  

//
// return folders and extents to index
//
function getMediaFolders() {
    
    // Find out which folders to parse for XML manifests - specified for FieldHelper indexing in Advanced Properties
    $query1 = "SELECT sys_MediaFolders, sys_MediaExtensions from sysIdentification where 1";
    $res1 = mysql_query($query1);
    if (!$res1 || mysql_num_rows($res1) == 0) {
        return array('error'=>'Sorry, unable to read the sysIdentification from the current database '.HEURIST_DBNAME
            .'. Possibly wrong database format, please consult Heurist team');
    }

    // Get the set of directories defined in Advanced Properties as FieldHelper indexing directories
    $row1 = $row = mysql_fetch_row($res1);
    $mediaFolders = $row1[0];
    
    if($mediaFolders==null || $mediaFolders==''){
        $mediaFolders = HEURIST_FILESTORE_DIR;
    }
    $dirs = explode(';', $mediaFolders); // get an array of folders

    //sanitize folder names
    $dirs = array_map('sanitizeFolderName', $dirs);
    //$mediaFolders = implode(';', $dirs);
    
    // The defined list of file extensions for FieldHelper indexing.
    if($row1[1]==null){
        $mediaExts = "jpg,jpeg,sid,png,gif,tif,tiff,bmp,rgb,doc,docx,odt,xsl,xslx,mp3,mp4,mpeg,avi,wmv,wmz,".
        "aif,aiff,mid,midi,wms,wmd,qt,evo,cda,wav,csv,tsv,tab,txt,rtf,xml,xsl,xslt,hml,kml,shp,".
        "htm,html,xhtml,ppt,pptx,zip,gzip,tar";
    }else{
        $mediaExts = $row1[1]; // user gets to define from scratch so they can restrict what's indexed
    }
    
    $mediaExts = explode(',', $mediaExts);

    if (count($dirs) == 0) {
        $dirs = array(HEURIST_FILESTORE_DIR); // default to the data folder for this database
        //print ("<p><b>It seems that there are no media folders specified for this database</b>");
    }
                
    return array('dirs'=>$dirs, 'exts'=>$mediaExts);

}


//
//
//
function sanitizeFolderName($folder) {
    $folder = str_replace("\0", '', $folder);
    $folder = str_replace('\\', '/', $folder);
    if( substr($folder, -1, 1) != '/' )  {
        $folder = $folder.'/';
    }
    return $folder;
}

//
// $imode - 0 - registration
//          1 - get registered and nonreg files
//
function doHarvest($dirs_and_exts, $is_report, $imode) {

    global $rep_counter, $rep_issues, $system_folders;
    
    if(@$dirs_and_exts['error']){
        print "<div style=\"color:red\">".$dirs_and_exts['error']."</div>";
        return;
    }
    
    $dirs = $dirs_and_exts['dirs'];
    $mediaExts = $dirs_and_exts['exts'];

    foreach ($dirs as $dir){

        if($dir=="*"){

            $dir = HEURIST_FILESTORE_DIR;

        }else{

            if(substr($dir, -1) != '/'){
                $dir .= "/";
            }

            $dir = str_replace('\\','/',$dir);
            if(!( substr($dir, 0, strlen(HEURIST_FILESTORE_DIR)) === HEURIST_FILESTORE_DIR )){
                $orig = $dir;
                chdir(HEURIST_FILESTORE_DIR);
                $dir = realpath($dir);
                $dir = str_replace('\\','/',$dir);     
                if(!( substr($dir, 0, strlen(HEURIST_FILESTORE_DIR)) === HEURIST_FILESTORE_DIR )){
                    if($is_report){
                        print "<div style=\"color:red\">$orig is ignored. Folder must be in heurist filestore directory.</div>";    
                    }
                    continue;
                }
            }

            if(substr($dir, -1) != '/'){
                $dir .= "/";
            }

        }

        if(in_array($dir, $system_folders)){

            if($is_report){
                print "<div style=\"color:red\">Files are not scanned in system folder $dir</div>";
            }

        }else if($dir && file_exists($dir) && is_dir($dir))
        {

            $files = scandir($dir);
            if($files && count($files)>0)
            {
                $subdirs = array();

                $isfirst = true;

                foreach ($files as $filename){

                    if(!($filename=="." || $filename=="..")){
                        if(is_dir($dir.$filename)){
                            $subdir = $dir.$filename."/";
                            if(!in_array($subdir, $system_folders)){
                                    array_push($subdirs, $subdir);
                            }
                        }else if($isfirst){ //if($filename == "fieldhelper.xml"){
                            $isfirst = false;
                            if($dir == HEURIST_FILESTORE_DIR){
                                if($is_report){
                                    print "<div style=\"color:red\">Files are not scanned in root upload folder $dir</div>";
                                }
                            }else{
                                getFilesInDir($dir, $mediaExts, $imode);
                            }
                        }
                    }
                }

                if(count($subdirs)>0){
//error_log(print_r($subdirs,true));                    
                    doHarvest(array("dirs"=>$subdirs, "exts"=>$mediaExts), $is_report, $imode);
                    if($is_report) flush();
                }
            }
        }else if ($dir) {
            if($is_report){
                print "<div style=\"color:red\">Folder is not found: $dir</div>";
            }
        }
    }
} //doHarvest 

//
// @todo - move code here from syncWithFieldHelper
//
function doHarvestInDir($dir) {
    
}

//
// remove all files from given array
//
function cleanFiles($files) {
    
}

function getRegInfoResult(){
    global $reg_info;
    return $reg_info;
}

//
// return arrays registered and non-registered files
// $imode 
// 0 - all
// 1 - reg and unreg separately
//
function getFilesInDir($dir, $mediaExts, $imode) {
    
    global $reg_info;
    
    $all_files = scandir($dir);
    $registered = array();
    $non_registered = array();
    
    foreach ($all_files as $filename){
        if(!($filename=="." || $filename==".." || is_dir($dir.$filename) || $filename=="fieldhelper.xml")){

            $filename_base = $filename;
            $filename = $dir.$filename;
            $currfile = $filename;
            $flleinfo = pathinfo($filename);
            $recordNotes = null;

            //checks for allowed extensions
            if(in_array(strtolower(@$flleinfo['extension']),$mediaExts))
            {
                if($imode==1){
                
                    $file_id = check_if_register_file($filename, false);

                    if($file_id>0){
                        array_push($reg_info['reg'], $filename);
                    }else{
                        array_push($reg_info['nonreg'], $filename);
                    }
                
                }else{
                    array_push($reg_info, $filename);
                }
            }
            
            
        }
    }  //for all_files
}
?>
