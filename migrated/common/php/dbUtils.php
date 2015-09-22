<?php
    /**
    * dbUtils.php : Functions to create, delelet, clean the entire HEURIST database
    *               and other functions to do with database file structure
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
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
    * Function list:
    * - add_index_html()
    * - server_connect()
    * - db_create()
    * - db_drop()
    * - db_dump()
    * - db_clean()
    * - db_script()  - see dbScript.php
    */

    require_once('dbScript.php');

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
                    echo ("<p class='error'>Error ".$mysqli->error.". Unable to create database $db_name<br/></p>");
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
    function db_clone($db_source, $db_target, $verbose=true){

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

                $tables = $mysqli->query("SHOW TABLES");
                if($tables){

                    $mysqli->query("SET foreign_key_checks = 0");
                    $mysqli->query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

                    while ($table = $tables->fetch_row()) {
                        $table = $table[0];
                        $mysqli->query("ALTER TABLE `".$table."` DISABLE KEYS");
                        $res = $mysqli->query("INSERT INTO `".$table."` SELECT * FROM ".$db_source.".`".$table."`"  );

                        if($res){
                            echo ($table . " Added ".$mysqli->affected_rows . ". ");
                        }else{
                            echo ("<br/><p class=\"error\">Error: Unable to add records into ".$table." - SQL error: ".$mysqli->error."</p>");
                            $res = false;
                            break;
                        }

                        $mysqli->query("ALTER TABLE `".$table."` ENABLE KEYS");
                    }

                    $mysqli->query("SET foreign_key_checks = 1");

                }else{
                    $res = false;
                    if($verbose) {
                        echo ("<br/><p class=\"error\">Error: Can not get list of table in database ".$db_target."</p>");
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
                if( $folders ){

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

    function unzip($zipfile, $destination){

        if(file_exists($zipfile) && filesize($zipfile)>0 &&  file_exists($destination)){

            $zip = new ZipArchive;
            if ($zip->open($zipfile) === TRUE) {
                $zip->extractTo($destination);
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
            if(zip($source, $destination)) {
                // Delete $source folder
                deleteFolder($source);
                if($verbose) echo "<br/>Folder ".$source." has been deleted";

                // Delete from MySQL
                $mysqli = server_connect();
                $mysqli->query("DROP DATABASE hdb_".$db);
                $mysqli->close();
                if($verbose) echo "<br/>Database ".$db." has been dropped";
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
            add_index_html($uploadPath."/rectype_icons/thumb");
        }else{
            echo ("<h3>Warning:</h3> Unable to create/copy record type icons folder rectype-icons to $uploadPath<br>");
            $warnings = 1;
        }
        if(recurse_copy( HEURIST_DIR."admin/setup/settings", $uploadPath."/settings" )){
            add_index_html($uploadPath."/settings"); // index file to block directory browsing
        }else{
            echo ("<h3>Warning:</h3> Unable to create/copy settings folder to $uploadPath<br>");
            $warnings = 1;
        }
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
        
        if(recurse_copy( HEURIST_DIR."../documentation", $uploadPath."/documentation" )){
            add_index_html($uploadPath."/documentation"); // index file to block directory browsing
        }else{
            echo ("<h3>Warning:</h3> Unable to create/copy documentation folder to $uploadPath<br>");
            $warnings = 1;
        }
                                                                                                   


        // Create all the other standard folders required for the database
        // index.html files are added by createFolder to block index browsing
        $warnings =+ createFolder($newDBName, "file_uploads","used to store uploaded files by default");
        $warnings =+ createFolder($newDBName, "scratch","used to store temporary files");
        $warnings =+ createFolder($newDBName, "hml-output","used to write published records as hml files");
        $warnings =+ createFolder($newDBName, "html-output","used to write published records as generic html files");
        $warnings =+ createFolder($newDBName, "generated-reports","used to write generated reports");
        $warnings =+ createFolder($newDBName, "backup","used to write files for user data dump");
        $warnings =+ createFolder($newDBName, "term-images","used for images illustrating terms");

        if ($warnings > 0) {
            echo "<h2>Please take note of warnings above</h2>";
            echo "You must create the folders indicated or else navigation tree, uploads, icons, templates, publishing, term images etc. will not work<br>";
            echo "If upload folder is created but icons and template folders are not, look at file permissions on new folder creation";
        }

    }




    function createFolder($newDBName, $name, $msg){

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
                        rmdir($dir."/".$object);
                    } else {
                        unlink($dir."/".$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
         }
    }


    /**
    * copy folder recursively
    *
    * @param mixed $src
    * @param mixed $dst
    */
    function recurse_copy($src, $dst) {
        $res = false;
        $dir = opendir($src);
        if($dir!==false){
        
            if (@mkdir($dst, 0777, true)) {

                $res = true;
                
                while(false !== ( $file = readdir($dir)) ) {
                    if (( $file != '.' ) && ( $file != '..' )) {
                        if ( is_dir($src . '/' . $file) ) {
                            $res = recurse_copy($src . '/' . $file,$dst . '/' . $file);
                            if(!$res) break;
                        }
                        else {
                            copy($src . '/' . $file,$dst . '/' . $file);
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
        ob_flush();
        flush();
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



?>
