<?php
/**
* dbUtils.php : Functions to create, delelet, clean the entire HEURIST database
*               and other functions to do with database file structure
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
* @subpackage  DataStore
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* @todo     Funnel all calls to functions in this file.
*           Not all system code uses these abstractions. Especially services.
* 
* @todo for H4 - implement as class fot given database connection
*
* Function list:
* - add_index_html()
* - server_connect()
* - db_create()
* - db_drop()
* - db_dump()
* - db_clean()
* - db_script()  - see utils_db_load_script.php
* 
* - db_register - set register ID to sysIdentification and rectype, detail and term defintions
*/

require_once(dirname(__FILE__).'/../../hserver/dbaccess/utils_db_load_script.php');

function server_connect($verbose = true){

    $mysqli = new mysqli(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD);
    // Check connection
    if (mysqli_connect_errno()) {
        if($verbose) echo ("<p class='error'>Failed to connect to MySQL database server: ".mysqli_connect_error()."<br/></p>");
        return false;
    }

    return $mysqli;
}


function db_create($db_name, $verbose = true){

    $res = false;

    // Avoid illegal chars in db
    if (preg_match('[\W]', $db_name)){
        echo ("<p class='error'>Error. Only letters, numbers and underscores (_) are allowed in the database name<br/></p>");
    }else{

        $mysqli = server_connect();
        if($mysqli){
            // Create database
            $sql = "CREATE DATABASE `".$db_name."`";
            if ($mysqli->query($sql)) {
                $res = true;
            }else if($verbose) {
                print '<p class="error ui-state-error" style="margin:10px"><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>'
                .'Error '.$mysqli->error." Unable to create database $db_name .</p>";
                //echo ("<p class='error'>Error ".$mysqli->error.". Unable to create database $db_name<br/></p>");
            }
            $mysqli->close();
        }
    }

    return $res;
}



function db_drop($db_name, $verbose = true) { // called in case of failure to remove the partially created database

    $res = false;

    $mysqli = server_connect();
    if($mysqli){

        $sql = "DROP DATABASE IF EXISTS `".$db_name."`";
        if ($mysqli->query($sql)) {
            $res = true;
        }
        $mysqli->close();
    }

    if($verbose) {
        if($res){
            echo ("<p>Database cleanup for $db_name, completed<br/></p>");
        }else{
            echo ("<p class='error'>Error ".$mysqli->error.". Unable to cleanup database $db_name<br/></p>");
        }
    }

    return $res;

}



function empty_table($mysqli, $name, $remark, $verbose){

    if($verbose) echo ("Deleting ".$remark."</br>");


    if(!$mysqli->query("delete from $name where 1")){
        if($verbose) {
            echo ("<br/><p>Warning: Unable to clean ".$remark
                ." - SQL error: ".$mysqli->error."</p>");
        }
        return false;
    }else{
        //if($verbose) { echo ("<p>OK</p>"); }
        return true;
    }
}



function db_clean($db_name, $verbose=true){

    $res = true;

    $mysqli = server_connect();
    if($mysqli){

        if(!$mysqli->select_db($db_name)){
            $res = false;
            if($verbose) {
                echo ("<br/><p>Warning: Could not open database ".$db_name);
            }
        }

        if($res){

            $mysqli->autocommit(FALSE);

            if(!$mysqli->query("update recThreadedComments set cmt_ParentCmtID = NULL where cmt_ID>0")){
                $res = false;
                if($verbose) {
                    echo ("<br/><p>Warning: Unable to set parent IDs to null for Comments".
                        " - SQL error: ".$mysqli->error."</p>");
                }
            }

            if($res){
                $tables = array(
                    "recThreadedComments" => "Comments",
                    "recForwarding" => "Forwarding",
                    "recRelationshipsCache" => "Relationships Cache",
                    "recSimilarButNotDupes" => "List of Similar Records",
                    "usrRecTagLinks" => "Tag Links",
                    "usrReminders" => "Reminders",
                    "usrRemindersBlockList" => "Reminders Block List",
                    "recDetails" => "Details",
                    "usrBookmarks" => "Bookmarks",
                    "Records" => "Records"
                );

                foreach ($tables as $name => $remark) {
                    if(!empty_table($mysqli, $name, $remark, $verbose)){
                        $res = false;
                        break;
                    }
                }

                if($res){
                    $res = $mysqli->query("ALTER TABLE Records AUTO_INCREMENT = 0");
                    if($res) $mysqli->commit();
                }
            }
        }

        if(!$res){
            $mysqli->rollback();
        }

        $mysqli->close();
    }else{
        $res = false;
    }

    return $res;
}



/**
* Copy all tables (except csv import cache) from one db to another
* It is assumed that all tables exist and empty in target db
*
* @param mixed $db_source
* @param mixed $db_target
* @param mixed $verbose
*/
function db_clone($db_source, $db_target, $verbose=true, $nodata=false){

    $res = true;

    $mysqli = server_connect();
    if($mysqli){

        if(!$mysqli->select_db($db_target)){
            $res = false;
            if($verbose) {
                echo ("<br/><p>Warning: Could not open database ".$db_target);
            }
        }

        if($res){
            
            $data_tables = array('Records','recDetails','recLinks','recRelationshipsCache',
            'recSimilarButNotDupes','recThreadedComments','recUploadedFiles','usrBookmarks','usrRecentRecords','usrRecTagLinks',
            'usrReminders','usrRemindersBlockList','woot_ChunkPermissions','woot_Chunks','woot_RecPermissions','woots');

            $tables = $mysqli->query("SHOW TABLES");
            if($tables){

                $mysqli->query("SET foreign_key_checks = 0"); //disable
                $mysqli->query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

                echo ("<b>Adding records to tables: </b>");
                while ($table = $tables->fetch_row()) { //loop for all tables
                    $table = $table[0];
                    
                    if($nodata && in_array($table, $data_tables)){
                        continue;
                    }
                    
                    
                    $mysqli->query("ALTER TABLE `".$table."` DISABLE KEYS");
                    $res = $mysqli->query("INSERT INTO `".$table."` SELECT * FROM ".$db_source.".`".$table."`"  );

                    if($res){
                        echo (" > " . $table . ": ".$mysqli->affected_rows . "  ");
                    }else{
                        if($table=='usrReportSchedule'){
                            echo ("<br/><p class=\"error\">Warning: Unable to add records into ".$table." - SQL error: ".$mysqli->error."</p>");
                        }else{
                            echo ("<br/><p class=\"error\">Error: Unable to add records into ".$table." - SQL error: ".$mysqli->error."</p>");
                            $res = false;
                            break;
                        }
                    }

                    if($table=='recForwarding'){ //remove missed records otherwise we get exception on constraint addition
                        $mysqli->query('DELETE FROM recForwarding where rfw_NewRecID not  in (select rec_ID from Records)');
                    }

                    $mysqli->query("ALTER TABLE `".$table."` ENABLE KEYS");
                }

                $mysqli->query("SET foreign_key_checks = 1"); //restore/enable foreign indexes verification

            }else{
                $res = false;
                if($verbose) {
                    echo ("<br/><p class=\"error\">Error: Cannot get list of table in database ".$db_target."</p>");
                }
            }


            //$mysqli->autocommit(FALSE);
            //if($res) $mysqli->commit();
        }

        if(!$res){
            //$mysqli->rollback();
        }

        $mysqli->close();
    }else{
        $res = false;
    }

    return $res;
}

/**
* Dump all tables (except csv import cache) into text files
* It is assumed that all tables exist and empty in target db
*
* @param mixed $db
* @param mixed $verbose
*/
function db_dump($db, $verbose=true) {
    // Create dump directory
    $directory = HEURIST_UPLOAD_ROOT.$db;
    if(!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }

    // Create file
    $filename = $directory."/hdb_".$db."_".time().".sql";
    $file = fopen($filename, "a+");

    $mysqli = server_connect();
    if($mysqli && $mysqli->select_db("hdb_".$db)){
        // SQL settings
        $settings = "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n
        /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n
        /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n
        /*!40101 SET NAMES utf8 */;\n
        /*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n
        /*!40103 SET TIME_ZONE='+00:00' */;\n
        /*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n
        /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n
        /*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n
        /*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n";
        fwrite($file, $settings);

        // Dump all tables of the database
        $tables = $mysqli->query("SHOW TABLES");
        if($tables){
            // Start to dump all tables
            while ($table = $tables->fetch_row()) {
                $table = $table[0];

                // Select everything in the table
                $result = $mysqli->query('SELECT * FROM '.$table);
                $num_fields = mysqli_field_count($mysqli);

                // Drop table sql
                $output = '\n\nDROP TABLE IF EXISTS `'.$table.'`;';

                // Create table sql
                $row2 = mysqli_fetch_row($mysqli->query('SHOW CREATE TABLE '.$table));
                $output.= $row2[1].";\n\n";

                // Insert values sql
                $output .= '/*!40000 ALTER TABLE '.$table.' DISABLE KEYS */;';
                for ($i = 0; $i < $num_fields; $i++) {
                    while($row = $result->fetch_row()) {
                        $output.= 'INSERT INTO '.$table.' VALUES(';
                        for($j=0; $j<$num_fields; $j++) {
                            $row[$j] = addslashes($row[$j]);
                            $row[$j] = ereg_replace("\n","\\n",$row[$j]);

                            if (isset($row[$j])) {
                                $output.= '"'.$row[$j].'"' ;
                            } else {
                                $output.= '""';
                            }

                            if ($j<($num_fields-1)) {
                                $output.= ',';
                            }
                        }
                        $output.= ");\n";
                    }
                }
                $output .= '/*!40000 ALTER TABLE '.$table.' ENABLE KEYS */;';

                // Write table sql to file
                $output.="\n\n\n";
                fwrite($file, $output);
            }
        }

        fwrite($file, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($file, "SET sql_mode = 'TRADITIONAL';\n");
        $mysqli->close();
    }else{
        if($verbose) echo "Failed to connect to database hdb_".$db;
        return false;
    }

    // Close file
    fclose($file);
    chmod($filename, 0777);

    // Echo output
    if($verbose) {
        $size = filesize($filename) / pow(1024,2);
        echo "<br/>Successfully dumped ".$db." to ".$filename;
        echo "<br/>Size of SQL dump: ".sprintf("%.2f", $size)." MB";
    }

    return true;
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


/**
* "Deletes" a database: makes a .sql dump of the database content
* and zips all files related to this db.
*
* @param mixed $db Database name WITHOUT hdb extension
*/
function db_delete($db, $verbose=true) {
    if(db_dump($db)) {
        // Create DELETED_DATABASES directory if needed
        $folder = HEURIST_UPLOAD_ROOT."DELETED_DATABASES/";
        if(!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        // Zip $source to $file
        $source = HEURIST_UPLOAD_ROOT.$db;
        $destination = $folder.$db."_".time().".zip";
                
        if(zip($source, null, $destination)) {
            // Delete from MySQL
            $mysqli = server_connect();
            $mysqli->query("DROP DATABASE hdb_".$db);
            $mysqli->close();
            if($verbose) echo "<br/>Database ".$db." has been dropped";
            
            // Delete $source folder
            deleteFolder($source);
            if($verbose) echo "<br/>Folder ".$source." has been deleted";
            
            // Delete from central index
            $mysqli->query('DELETE FROM `heurist_index`.`sysIdentifications` WHERE sys_Database="hdb_'.$db.'"');
            $mysqli->query('DELETE FROM `heurist_index`.`sysUsers` WHERE sus_Database="hdb_'.$db.'"');
            
            return true;
        }else{
            if($verbose) echo "<br/>Failed to zip ".$source." to ".$destination;
        }
    }else{
        if($verbose) echo "<br/>Failed to dump database ".$db." to a .sql file";
    }
    return false;
}

//create new empty database
function createDatabaseEmpty($newDBName){

    $newname = HEURIST_DB_PREFIX . $newDBName;

    if(!db_create($newname)){
        return false;
    }

    //
    //echo_flush ("<p>Create Database Structure (tables) ".HEURIST_DIR." </p>");
    if(db_script($newname, HEURIST_DIR."admin/setup/dbcreate/blankDBStructure.sql")){

        // echo_flush ('OK');
        // echo_flush ("<p>Add Referential Constraints ");

        if(db_script($newname, HEURIST_DIR."admin/setup/dbcreate/addReferentialConstraints.sql")){

            // echo_flush ('OK');
            // echo_flush ("<p>Add Procedures and Triggers ");

            if(db_script($newname, HEURIST_DIR."admin/setup/dbcreate/addProceduresTriggers.sql")){

                // echo_flush ('OK');
                return true;
            }
        }
    }
    db_drop($newname);
    return false;
}


//
// create new empty upload folder and copy content from admin/setup
//
function createDatabaseFolders($newDBName){

    // Create a default upload directory for uploaded files eg multimedia, images etc.
    $uploadPath = HEURIST_UPLOAD_ROOT.$newDBName;

    $warnings = !createFolder($newDBName, null, "Please check/create directory by hand. Consult Heurist helpdesk if needed");
    if($warnings==0){
        add_index_html($uploadPath); // index file to block directory browsing
    }

    if(recurse_copy( HEURIST_DIR."admin/setup/rectype-icons", $uploadPath."/rectype-icons" )){
        add_index_html($uploadPath."/rectype-icons"); // index file to block directory browsing
        //add_index_html($uploadPath."/rectype_icons/thumb");
    }else{
        echo ("<h3>Warning:</h3> Unable to create/copy record type icons folder rectype-icons to $uploadPath<br>");
        $warnings = 1;
    }
    /*
    if(recurse_copy( HEURIST_DIR."admin/setup/settings", $uploadPath."/settings" )){
    add_index_html($uploadPath."/settings"); // index file to block directory browsing
    }else{
    echo ("<h3>Warning:</h3> Unable to create/copy settings folder to $uploadPath<br>");
    $warnings = 1;
    }*/
    if(recurse_copy( HEURIST_DIR."admin/setup/smarty-templates", $uploadPath."/smarty-templates" )){
        add_index_html($uploadPath."/smarty-templates"); // index file to block directory browsing
    }else{
        echo ("<h3>Warning:</h3> Unable to create/copy smarty-templates folder to $uploadPath<br>");
        $warnings = 1;
    }
    if(recurse_copy( HEURIST_DIR."admin/setup/xsl-templates", $uploadPath."/xsl-templates" )){
        add_index_html($uploadPath."/xsl-templates"); // index file to block directory browsing
    }else{
        echo ("<h3>Warning:</h3> Unable to create/copy xsl-templates folder to $uploadPath<br>");
        $warnings = 1;
    }

    if(recurse_copy( HEURIST_DIR."/documentation_and_templates", $uploadPath."/documentation_and_templates" )){
        add_index_html($uploadPath."/documentation_and_templates"); // index file to block directory browsing
    }else{
        echo ("<h3>Warning:</h3> Unable to create/copy documentation folder to $uploadPath<br>");
        $warnings = 1;
    }


    // Create all the other standard folders required for the database
    // index.html files are added by createFolder to block index browsing
    $warnings =+ createFolder($newDBName, 'filethumbs', 'used to store thumbnails for uploaded files', true);
    $warnings =+ createFolder($newDBName, 'file_uploads','used to store uploaded files by default');
    $warnings =+ createFolder($newDBName, 'scratch', 'used to store temporary files');
    $warnings =+ createFolder($newDBName, 'hml-output', 'used to write published records as hml files', true);
    $warnings =+ createFolder($newDBName, 'html-output', 'used to write published records as generic html files', true);
    $warnings =+ createFolder($newDBName, 'generated-reports', 'used to write generated reports');
    $warnings =+ createFolder($newDBName, 'backup', 'used to write files for user data dump');
    $warnings =+ createFolder($newDBName, 'term-images', 'used for images illustrating terms');

    if ($warnings > 0) {
        echo "<h2>Please take note of warnings above</h2>";
        echo "You must create the folders indicated or else navigation tree, uploads, icons, templates, publishing, term images etc. will not work<br>";
        echo "If upload folder is created but icons and template folders are not, look at file permissions on new folder creation";
    }

}

//
//
//
function createFolder($newDBName, $name, $msg, $allow_web_access=false){

    $uploadPath = HEURIST_UPLOAD_ROOT.$newDBName;
    if($name){
        $folder = $uploadPath."/".$name;
    }else{
        $folder = $uploadPath;
    }

    if(file_exists($folder) && !is_dir($folder)){
        if(!unlink($folder)){
            echo ("<h3>Warning:</h3> Unable to remove folder $folder. We need to create a folder with this name ($msg)<br>");
            return 1;
        }
    }

    if(!file_exists($folder)){
        if (!mkdir($folder, 0777, true)) {
            echo ("<h3>Warning:</h3> Unable to create folder $folder ($msg)<br>");
            return 1;
        }
    } else if (!is_writable($folder)) {
        echo ("<h3>Warning:</h3> Folder $folder already exists and it is not writeable. Check permissions! ($msg)<br>");
        return 1;
    }

    add_index_html($folder); // index file to block directory browsing

    if($allow_web_access){
        //copy htaccess
        $res = copy(HEURIST_DIR.'admin/setup/.htaccess_via_url', $folder.'/.htaccess');
        if(!$res){
            echo ("<h3>Warning:</h3> Cannot copy htaccess file for folder $folder<br>");
            return 1;
        }
    }

    return 0;
}

/**
* Deletes a folder and all its files recursively
*
* @param mixed $dir Directory to remove
*/
function deleteFolder($dir) {
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


/**
* copy folder recursively
*
* @param mixed $src
* @param mixed $dst
* @param array $folders - zero level folders to copy
*/
function recurse_copy($src, $dst, $folders=null, $file_to_copy=null, $copy_files_in_root=true) {
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
                                $res = recurse_copy($src.$file, $dst . '/' . $file, null, $file_to_copy, true);
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



/**
* Add an index.html file to a directory to block browsing files
* Does not overwrite an existing file if present
*
* @param mixed $directory
*/
function add_index_html($directory) {
    $filename = $directory."/index.html";
    if(!file_exists($filename)){
        $file = fopen($filename,'x');
        if ($file) { // returns false if file exists - don't overwrite
            fwrite($file,"Sorry, this folder cannot be browsed");
            fclose($file);
        }
    }
}

function echo_flush($msg){
    ob_start();
    print $msg;
    @ob_flush();
    @flush();
}


/**
* Check that db function exists
*
* @param mixed $mysqli
* @param mixed $name
*/
function isFuncionExists($mysqli, $name){
    $res = false;
    try{

        // search function
        $row2 = mysqli_fetch_row($mysqli->query('SHOW CREATE FUNCTION '.$name));
        if($row2){
            $res = true;
        }

    } catch (Exception $e) {
    }
    return $res;
}


/**
* This function is called on login
* Validate the presence of db functions. If one of functions does not exist - run admin/setup/dbcreate/addFunctions.sql
*
*/
function checkDatabaseFunctions(){

    $res = false;

    $mysqli = server_connect();
    if($mysqli){

        if(!$mysqli->select_db(DATABASE)){
            $res = false;
            if($verbose) {
                echo ("<br/><p>Warning: Could not open database ".$db_name);
            }
        }else{

            if(isFuncionExists($mysqli, 'NEW_LEVENSHTEIN') && isFuncionExists($mysqli, 'NEW_LIPOSUCTION')
            && isFuncionExists($mysqli, 'hhash') && isFuncionExists($mysqli, 'simple_hash')
            //&& isFuncionExists('set_all_hhash')
            && isFuncionExists($mysqli, 'getTemporalDateString')){

                $res = true;

            }else if(db_script(DATABASE, HEURIST_DIR."admin/setup/dbcreate/addProceduresTriggers.sql")){
                $res = true;
            }
        }
        $mysqli->close();
    }

    return $res;
}

//
// set Origin ID for rectype, detail and term defintions
//
function db_register($db_name, $dbID){

    $res = true;

    if($dbID>0){

        $mysqli = server_connect();
        if($mysqli){

            if(!$mysqli->select_db($db_name)){
                $res = false;
                if($verbose) {
                    echo ("<br/><p>Warning: Could not open database ".$db_name);
                }
            }

            if($res){
                //@todo why 3 actions for every table????? 
                $result = 0;
                $res = $mysqli->query("update defRecTypes set rty_OriginatingDBID='$dbID' ".
                    "where (rty_OriginatingDBID = '0') OR (rty_OriginatingDBID IS NULL) ");
                if (!$res) {$result = 1; }
                $res = $mysqli->query("update defRecTypes set rty_NameInOriginatingDB=rty_Name ".
                    "where (rty_NameInOriginatingDB = '') OR (rty_NameInOriginatingDB IS NULL)");
                if (!$res) {$result = 1; }
                $res = $mysqli->query("update defRecTypes set rty_IDInOriginatingDB=rty_ID ".
                    "where (rty_IDInOriginatingDB = '0') OR (rty_IDInOriginatingDB IS NULL) ");
                if (!$res) {$result = 1; }
                // Fields
                $res = $mysqli->query("update defDetailTypes set dty_OriginatingDBID='$dbID' ".
                    "where (dty_OriginatingDBID = '0') OR (dty_OriginatingDBID IS NULL) ");
                if (!$res) {$result = 1; }
                $res = $mysqli->query("update defDetailTypes set dty_NameInOriginatingDB=dty_Name ".
                    "where (dty_NameInOriginatingDB = '') OR (dty_NameInOriginatingDB IS NULL)");
                if (!$res) {$result = 1; }
                $res = $mysqli->query("update defDetailTypes set dty_IDInOriginatingDB=dty_ID ".
                    "where (dty_IDInOriginatingDB = '0') OR (dty_IDInOriginatingDB IS NULL) ");
                if (!$res) {$result = 1; }
                // Terms
                $res = $mysqli->query("update defTerms set trm_OriginatingDBID='$dbID' ".
                    "where (trm_OriginatingDBID = '0') OR (trm_OriginatingDBID IS NULL) ");
                if (!$res) {$result = 1; }
                $res = $mysqli->query("update defTerms set trm_NameInOriginatingDB=trm_Label ".
                    "where (trm_NameInOriginatingDB = '') OR (trm_NameInOriginatingDB IS NULL)");
                if (!$res) {$result = 1; }
                $res = $mysqli->query("update defTerms set trm_IDInOriginatingDB=trm_ID ".
                    "where (trm_IDInOriginatingDB = '0') OR (trm_IDInOriginatingDB IS NULL) ");
                if (!$res) {$result = 1; }

                
                if (!$res){
                    error_log('Error on database registration '.$db_name.'  '.$mysqli->error);
                }
                
                $res = ($result==0);
            }
        }
    }
    return $res;
}


/**   TAKEN FROM utils_db.php
* open database
* 
* @param mixed $dbname
*/
function mysql__usedatabase($mysqli, $dbname){

    if($dbname){

        $success = $mysqli->select_db($dbname);
        if(!$success){
            return "Could not open database ".$dbname;
        }

        $mysqli->query('set character set "utf8"');
        $mysqli->query('set names "utf8"');

    }
    return true;
}

/**      TAKEN FROM utils_db.php
* insert or update record for given table
*
* returns record ID in case success or error message
*
* @param mixed $mysqli
* @param mixed $table_name
* @param mixed $table_prefix 
* @param mixed $record   - array(fieldname=>value) - all values considered as String except when field ended with ID
*                          fields that don't have specified prefix are ignored
*/
function mysql__insertupdate($database, $table_name, $table_prefix, $record){

    $mysqli = server_connect();
    mysql__usedatabase($mysqli, $database);

    $ret = null;

    if (substr($table_prefix, -1) !== '_') {
        $table_prefix = $table_prefix.'_';
    }
    $primary_field = $table_prefix.'ID';
    $rec_ID = intval(@$record[$primary_field]);
    $isinsert = ($rec_ID<1);
    
    if($isinsert){
        $query = "INSERT into $table_name (";
        $query2 = ') VALUES (';
    }else{
        $query = "UPDATE $table_name set ";
    }
 
    $params = array();
    $params[0] = '';

    foreach($record as $fieldname => $value){

        if(strpos($fieldname, $table_prefix)!==0){ //ignore fields without prefix
            //$fieldname = $table_prefix.$fieldname;
            continue;
        }

        if($isinsert){
            $query = $query.$fieldname.', ';
            $query2 = $query2.'?, ';
        }else{
            if($fieldname==$table_prefix."ID"){
                continue;
            }
            $query = $query.$fieldname.'=?, ';
        }

        $dtype = ((substr($fieldname, -2) === 'ID' || substr($fieldname, -2) === 'Id')?'i':'s');
        $params[0] = $params[0].$dtype;
        if($dtype=='i' && $value==''){
            $value = null;
        }
        array_push($params, $value);
    }

    $query = substr($query,0,strlen($query)-2);
    if($isinsert){
        $query2 = substr($query2,0,strlen($query2)-2).")";
        $query = $query.$query2;
    }else{
        $query = $query." where ".$table_prefix."ID=".$rec_ID;
    }

    $stmt = $mysqli->prepare($query);
    if($stmt){
        call_user_func_array(array($stmt, 'bind_param'), refValues($params));
        if(!$stmt->execute()){
            $ret = $mysqli->error;
        }else{
            $ret = ($isinsert)?$stmt->insert_id:$rec_ID;
        }
        $stmt->close();
    }else{
        $ret = $mysqli->error;
    }

    return $ret;
}


?>
