<?php
    /**
    * dbUtils.php : Functions to create, delelet, clean the entire HEURIST database
    *               and other functions to do with database file structure
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
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
                            echo ("<br/><p> $table . Added ".$mysqli->affected_rows);
                        }else{
                            echo ("<br/><p>Error: Unable to add records into ".$table." - SQL error: ".$mysqli->error."</p>");
                            $res = false;
                            break;
                        }

                        $mysqli->query("ALTER TABLE `".$table."` ENABLE KEYS");
                    }

                    $mysqli->query("SET foreign_key_checks = 1");

                }else{
                    $res = false;
                    if($verbose) {
                        echo ("<br/><p>Error: Can not get list of table in database ".$db_target);
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



    //create new empty database
    function createDatabaseEmpty($newDBName){

        $newname = HEURIST_DB_PREFIX . $newDBName;

        if(!db_create($newname)){
            return false;
        }

        // echo_flush ("<p>Create Database Structure (tables) ");
        if(db_script($newname, dirname(__FILE__)."/../../admin/setup/dbcreate/blankDBStructure.sql")){

            // echo_flush ('OK');
            // echo_flush ("<p>Add Referential Constraints ");

            if(db_script($newname, dirname(__FILE__)."/../../admin/setup/dbcreate/addReferentialConstraints.sql")){

                // echo_flush ('OK');
                // echo_flush ("<p>Add Procedures and Triggers ");

                if(db_script($newname, dirname(__FILE__)."/../../admin/setup/dbcreate/addProceduresTriggers.sql")){

                    // echo_flush ('OK');
                    return true;
                }
            }
        }
        db_drop($newname);
        return false;
    }



    function createDatabaseFolders($newDBName){

        // Create a default upload directory for uploaded files eg multimedia, images etc.
        $uploadPath = HEURIST_UPLOAD_ROOT.$newDBName;

        $warnings = !createFolder($newDBName, null, "Please check/create directory by hand. Consult Heurist helpdesk if needed");
        if($warnings==0){
            add_index_html($uploadPath); // index file to block directory browsing
        }

        if(recurse_copy( dirname(__FILE__)."/../../admin/setup/rectype-icons", $uploadPath."/rectype-icons" )){
            add_index_html($uploadPath."/rectype-icons"); // index file to block directory browsing
            add_index_html($uploadPath."/rectype_icons/thumb");
        }else{
            echo ("<h3>Warning:</h3> Unable to create/copy record type icons folder rectype-icons to $uploadPath<br>");
            //echo ("This may be because the directory already exists or the parent folder is not writable<br>");
            //echo ("Please check/create directory by hand. Consult Heurist helpdesk if needed<br>");
            //echo ("If upload directory was created OK, this is probably due to incorrect file permissions on new folders<br>");
            $warnings = 1;
        }
        if(recurse_copy( dirname(__FILE__)."/../../admin/setup/smarty-templates", $uploadPath."/smarty-templates" )){
            add_index_html($uploadPath."/smarty-templates"); // index file to block directory browsing
        }else{
            echo ("<h3>Warning:</h3> Unable to create/copy smarty-templates folder to $uploadPath<br>");
            //echo ("This may be because the directory already exists or the parent folder is not writable<br>");
            //echo ("Please check/create directory by hand. Consult Heurist helpdesk if needed<br>");
            $warnings = 1;
        }
        if(recurse_copy( dirname(__FILE__)."/../../admin/setup/xsl-templates", $uploadPath."/xsl-templates" )){
            add_index_html($uploadPath."/xsl-templates"); // index file to block directory browsing
        }else{
            echo ("<h3>Warning:</h3> Unable to create/copy xsl-templates folder to $uploadPath<br>");
            //echo ("This may be because the directory already exists or the parent folder is not writable<br>");
            //echo ("Please check/create directory by hand. Consult Heurist helpdesk if needed<br>");
            $warnings = 1;
        }

        // Create all the other standard folders required for the database
        // index.html files are added by createFolder to block index browsing
        $warnings =+ createFolder($newDBName, "settings","used to store import mappings and the like");
        $warnings =+ createFolder($newDBName, "scratch","used to store temporary files");
        $warnings =+ createFolder($newDBName, "hml-output","used to write published records as hml files");
        $warnings =+ createFolder($newDBName, "html-output","used to write published records as generic html files");
        $warnings =+ createFolder($newDBName, "generated-reports","used to write generated reports");
        $warnings =+ createFolder($newDBName, "backup","used to write files for user data dump");
        $warnings =+ createFolder($newDBName, "term-images","used for images illustrating terms");

        if ($warnings > 0) {
            echo "<h2>Please take note of warnings above</h2>";
            echo "You must create the folders indicated or uploads, icons, templates, publishing, term images etc. will not work<br>";
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
    * copy folder recursively
    *
    * @param mixed $src
    * @param mixed $dst
    */
    function recurse_copy($src, $dst) {
        $res = true;
        $dir = opendir($src);
        if (@mkdir($dst, 0777, true)) {

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

        }else{
            $res = false;
        }
        closedir($dir);

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


?>
