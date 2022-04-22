<?php

/**
* Creates archive packages for one, several or all databases.
* It is possible to expost some or all data as an HML or zip file
* Include uploaded/registered files parameter
* Runs from shell only
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$arg_skip_files = false;
$arg_skip_hml = true;
$arg_database = null;
$arg_include_docs = true;

if (@$argv) {

    // handle command-line queries
    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {                    
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[$argv[$i]] = $argv[$i + 1];
                ++$i;
            } else {
                if(strpos($argv[$i],'-db=')===0){
                    $ARGV['-db'] = substr($argv[$i],4);
                }else{
                    $ARGV[$argv[$i]] = true;    
                }


            }
        } else {
            array_push($ARGV, $argv[$i]);
        }
    }
    if (@$ARGV['-db']) $arg_database = $ARGV['-db'];   
    if (@$ARGV['-nofiles']) $arg_skip_files = true;
    if (@$ARGV['-hml']) $arg_skip_hml = false;



}else{
    exit('This function must be run from the shell');
}

if($arg_database==null){
    exit("Required parameter -db is not defined\n");
}


require_once(dirname(__FILE__).'/../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_files.php');
require_once(dirname(__FILE__).'/../../external/php/Mysqldump.php');


//retrieve list of databases
$system = new System();
if( !$system->init(null, false,false) ){
    exit("Cannot establish connection to sql server\n");
}

$mysqli = $system->get_mysqli();
$databases = mysql__getdatabases4($mysqli, false);   

if($arg_database=='all'){
    $arg_database = $databases;
}else{
    $arg_database = explode(',',$arg_database);
    if(count($arg_database)==0){
        exit("Required parameter -db is not defined\n");
    }
    foreach ($arg_database as $db){
        if(!in_array($db,$databases)){
            exit("Database $db not found\n");
        }           
    }
}

$upload_root = $system->getFileStoreRootFolder();
$backup_root = $upload_root.'_BATCH_PROCESS_ARCHIVE_PACKAGE/';

define('HEURIST_FILESTORE_ROOT', $upload_root );

if (!folderCreate($backup_root, true)) {
    exit("Failed to create backup folder $backup_root \n");
}
//set semaphore file
$progress_flag = $backup_root.'inprogress.info';

//flag that backup in progress
if(file_exists($progress_flag)){
    //if(file_exists($progress_flag)) unlink($progress_flag);
    exit("It appears that backup operation has been started already. Please try this function later $progress_flag \n");
}
$fp = fopen($progress_flag,'w');
fwrite($fp, '1');
fclose($fp);            

set_time_limit(0); //no limit

foreach ($arg_database as $idx=>$db_name){

    echo "processing ".$db_name." "; //.'  in '.$folder


    $folder = $backup_root.$db_name.'/';
    $backup_zip = $backup_root.$db_name.'.zip'; 

    $database_folder = $upload_root.$db_name.'/';

    if(file_exists($folder)){
        $res = folderDelete2($folder, true);
        if(!$res){
            if(file_exists($progress_flag)) unlink($progress_flag);
            exit("Cannot clear existing backup folder $folder \n");
        }
    }
    if (!folderCreate($folder, true)) {
        if(file_exists($progress_flag)) unlink($progress_flag);
        exit("Failed to create folder $folder in which to create the backup \n");
    }

    echo "files.. ";
    $folders_to_copy = null;

    //copy resource folders
    if($arg_include_docs){
        //Exporting system folders
        $folders_to_copy = $system->getSystemFolders( 1, $db_name );
    }

    if(!$arg_skip_files){
        if($folders_to_copy==null) $folders_to_copy = array();    
        $folders_to_copy[] = $database_folder.'file_uploads/';   //HEURIST_FILES_DIR;
        $folders_to_copy[] = $database_folder.'filethumbs/';  //HEURIST_THUMB_DIR;

        $copy_files_in_root = true; //copy all files within database folder
    }else{
        $copy_files_in_root = false;
    }

    //var_dump($folders_to_copy); 

    if($folders_to_copy==null){
        $folders_to_copy = array('no copy folders');   
    }


    if($arg_include_docs || !$arg_skip_files){     
        folderRecurseCopy( $database_folder, $folder, $folders_to_copy, null, $copy_files_in_root);
    }

    if(false){// 2016-10-25  
        folderRecurseCopy( HEURIST_DIR.'context_help/', $folder.'/context_help/', null);
    }


    if(!$arg_skip_hml){
        /* Ian J. 20/4/2022: ? THIS REQUIRES FURTHER WORK TO IMPLEMENT ?
        echo "hml.. ";
        //load hml output into string file and save it
        if(@$_REQUEST['allrecs']!="1"){
        $userid = get_user_id();
        $q = "owner:$userid"; //user:$userid OR
        $_REQUEST['depth'] = '5';
        }else{
        $q = "sortby:-m";
        $_REQUEST['depth'] = '0';
        }


        $_REQUEST['w'] = 'all';
        $_REQUEST['a'] = '1';
        $_REQUEST['q'] = $q;
        $_REQUEST['rev'] = 'no'; //do not include reverse pointers
        $_REQUEST['filename'] = $folder."/".HEURIST_DBNAME.".xml";

        echo_flush2("Exporting database as HML (Heurist Markup Language = XML)<br>(may take some time for large databases)<br>");

        $to_include = dirname(__FILE__).'/../../export/xml/flathml.php';
        if (is_file($to_include)) {
        include $to_include;
        }
        */
    }//export HML

    // Export database definitions as readable text

    echo "sql.. ";

    /*
    echo_flush2("Exporting database definitions as readable text<br>");

    $url = HEURIST_BASE_URL . "admin/describe/getDBStructureAsSQL.php?db=".HEURIST_DBNAME."&pretty=1";
    saveURLasFile($url, $folder."/Database_Structure.txt"); //save to $upload_root.'backup/'.HEURIST_DBNAME

    echo_flush2("Exporting database definitions as XML<br>");

    $url = HEURIST_BASE_URL . "admin/describe/getDBStructureAsXML.php?db=".HEURIST_DBNAME;
    saveURLasFile($url, $folder."/Database_Structure.xml"); //save to $upload_root.'backup/'.HEURIST_DBNAME
    */

    // Do an SQL dump of the whole database
    try{
        $dump = new Mysqldump( 'hdb_'.$db_name, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DBSERVER_NAME, 'mysql', array('skip-triggers' => true,  'add-drop-trigger' => false));
        $dump->start($folder."/".$db_name."_MySQL_Database_Dump.sql");
    } catch (Exception $e) {
        if(file_exists($progress_flag)) unlink($progress_flag);
        exit("Sorry, unable to generate MySQL database dump for $db_name.".$e->getMessage()."\n");
    }

     echo "zip.. ";
     
    // Create a zipfile of the definitions and data which have been dumped to disk
    $destination = $backup_zip; //$folder.'.zip';
    if(file_exists($destination)) unlink($destination);
    $res = createZipArchive($folder, null, $destination, false);

    folderDelete2($folder, true);

    if(!$res){
        if(file_exists($progress_flag)) unlink($progress_flag);
        exit("Database: $db_name Failed to create zip file at $destination \n");
    }

    echo "   ".$db_name." OK \n"; //.'  in '.$folder
}//for

if(file_exists($progress_flag)) unlink($progress_flag);

exit("\nfinished all requested databases, results in HEURIST_FILESTORE/_BATCH_PROCESS_ARCHIVE_PACKAGE/
\n\n");
?>