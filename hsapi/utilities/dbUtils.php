<?php
/**
* dbUtils.php : Functions to create, delelet, clean the entire HEURIST database
*               and other functions to do with database file structure
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2018 University of Sydney
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
* Static class to perform database operations
*
* Methods:

* - databaseCreate()
* - databaseDrop()
* - databaseDump()
* - databaseCreateFolders
*   databaseEmpty    
*   databaseClone
* 
* - db_script()  - see utils_db_load_script.php
* 
* - databaseRegister - set register ID to sysIdentification and rectype, detail and term defintions
*/

require_once(dirname(__FILE__).'/../../hsapi/utilities/utils_db_load_script.php');

class DbUtils {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}    
    private static $mysqli = null;
    private static $system = null;
    private static $initialized = false;

    public static function initialize($mysqli=null)
    {
        if (self::$initialized)
            return;

        global $system;
        self::$system = $system;
        
        if($mysqli){
            self::$mysqli = $mysqli;
        }else{
            self::$mysqli = $system->get_mysqli();    
        }

        self::$initialized = true;
    }

    //
    // set Origin ID for rectype, detail and term defintions
    //
    public static function databaseRegister($dbID){

        self::initialize();
        
        $res = true;

        if($dbID>0){
                    $mysqli = self::$mysqli;
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
                        self::$system->addError(HEURIST_DB_ERROR,
                                    'Error on database registration '.$db_name, $mysqli->error);
                    }
                    
                    $res = ($result==0);
        }
        return $res;
    }    

    //
    // remove database entirely
    //
    public static function databaseDrop( $verbose=false, $database_name=null, $createArchive=false, $dumpData=false ){

        self::initialize();
        
        $mysqli = self::$mysqli;
        $system = self::$system;
        
        if($database_name==null) $database_name = HEURIST_DBNAME;
        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        
        if($database_name!=HEURIST_DBNAME){ //switch to database
           $connected = mysql__usedatabase($mysqli, $database_name_full);
        }else{
           $connected = true;
        }
        
        $archiveFolder = HEURIST_FILESTORE_ROOT."DELETED_DATABASES/";        

        if(!$connected){
            $msg = 'Failed to connect to database '.$database_name;
            $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) echo '<br>'.$msg;
        }else 
        if($createArchive) {

            // Create DELETED_DATABASES directory if needed
            if(!folderCreate($archiveFolder, true)){
                    $system->addError(HEURIST_SYSTEM_CONFIG, 'Can not create folder for deleteted databases');                
                    return false;
            }
            
            if($dumpData){
                if (!DbUtils::databaseDump( $verbose, $database_name )) {
                    $msg = "Failed to dump database $database_name to a .sql file";
                    self::$system->addError(HEURIST_ERROR, $msg);                
                    if($verbose) echo '<br/>'.$msg;
                    return false;
                }
            }
        }
        
        // Zip $source to $file
        $source = HEURIST_FILESTORE_ROOT.$database_name.'/'; //HEURIST_FILESTORE_DIR;  database upload folder
        $destination = $archiveFolder.$database_name."_".time().".zip";
            
        if(!$createArchive || zip($source, null, $destination, $verbose)) {
            
                // Delete database from MySQL server
                if(!mysql__drop_database($mysqli, $database_name_full)){
                    
                    $msg = 'Error on database drop '.$database_name;
                    self::$system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
                    if($verbose) echo '<br/>'.$msg;
                    return false;
                }
                
                if($verbose) echo "<br/>Database ".$database_name." has been dropped";
                
                // Delete $source folder
                folderDelete($source);
                if($verbose) echo "<br/>Folder ".$source." has been deleted";
                
                /* Delete from central index
                $mysqli->query('DELETE FROM `Heurist_DBs_index`.`sysIdentifications` WHERE sys_Database="'.$database_name_full.'"');
                $mysqli->query('DELETE FROM `Heurist_DBs_index`.`sysUsers` WHERE sus_Database="'.$database_name_full.'"');
                */
                
            return true;
        }else{
            $msg = "Failed to zip $source to $destination";
            self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);                
            if($verbose) echo '<br/>'.$msg;
        }
        
                
        return false;

    }    
    
    
    /**
    * Dump all tables (except csv import cache) into text files
    * It is assumed that all tables exist and empty in target db
    *
    * @param mixed $db
    * @param mixed $verbose
    */
     private static function databaseDump( $verbose=true, $database_name=null ) {
        
        self::initialize();
        
        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        
        $mysqli = self::$mysqli;

        if($database_name!=HEURIST_DBNAME){ //switch to database
           $connected = mysql__usedatabase($mysqli, $database_name_full);
        }else{
           $connected = true;
        }
        
        if($connected){
            
            // dump will be created in database upload folder
            $directory = HEURIST_FILESTORE_ROOT.$database_name;
            /*if(!folderCreate($directory, true)){
                self::$system->addError(HEURIST_SYSTEM_CONFIG, 'Can not create folder for deleteted databases');                
                if($verbose) echo 'Unable to create folder '.$directory;
                return false;
            }*/

            // Create DUMP file
            $filename = $directory.'/'.$database_name_full.'_'.time().'.sql';
            $file = fopen($filename, "a+");
            if(!$file){
                $msg = 'Unable to open dump file '.$file;
                self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                if($verbose) echo '<br>'.$msg;
                return false;
            }
            
            
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
            
            //$mysqli->close();
        }else{
            $msg = 'Failed to connect to database '.$database_name_full;
            self::$system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) echo '<br>'.$msg;
            return false;
        }

        // Close file
        fclose($file);
        chmod($filename, 0777);

        // Echo output
        if($verbose) {
            $size = filesize($filename) / pow(1024,2);
            echo "<br/>Successfully dumped ".$database_name." to ".$filename;
            echo "<br/>Size of SQL dump: ".sprintf("%.2f", $size)." MB";
        }

        return true;
    }
  
    //    
    //create new empty heurist database
    // $level - 0 empty db, 1 +strucute, 2 +constraints and triggers
    //
    public static function databaseCreate($database_name, $level=2){

        self::initialize();
        
        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        
        $mysqli = self::$mysqli;
        
        $res = mysql__create_database($mysqli, $database_name_full);
        
        if (is_array($res)){
            self::$system->addError($res[0], $res[1]); //can't create
        }else
        if($level<1 || db_script($database_name_full, HEURIST_DIR."admin/setup/dbcreate/blankDBStructure.sql")){

            // echo_flush ('OK');
            // echo_flush ("<p>Add Referential Constraints ");

            if($level<2 || db_script($database_name_full, HEURIST_DIR."admin/setup/dbcreate/addReferentialConstraints.sql")){

                // echo_flush ('OK');
                // echo_flush ("<p>Add Procedures and Triggers ");

                if($level<2 || db_script($database_name_full, HEURIST_DIR."admin/setup/dbcreate/addProceduresTriggers.sql")){

                    // echo_flush ('OK');
                    return true;
                }else{
                    self::$system->addError(HEURIST_DB_ERROR, 'Can not add procedures and triggers');
                }
            }else{
                self::$system->addError(HEURIST_DB_ERROR, 'Can not add referential constraints');
            }
        }else{
            self::$system->addError(HEURIST_DB_ERROR, 'Can not create database tables');
        }
        //fail
        mysql__drop_database($mysqli, $database_name_full);
        return false;
    }
        
    //
    // create if not exists set of folders for given database
    //
    public static function databaseCreateFolders($database_name){

        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        
        $upload_root = self::$system->getFileStoreRootFolder();
        
        // Create a default upload directory for uploaded files eg multimedia, images etc.
        $database_folder = $upload_root.$database_name.'/';

        if (folderCreate($database_folder, true)){
            folderAddIndexHTML( $database_folder ); //add index file to block directory browsing
        }else{
            return array('Database root folder. Please check/create directory by hand. Consult Heurist helpdesk if needed');
        }

        $warnings = array();
        
        if(folderRecurseCopy( HEURIST_DIR."admin/setup/rectype-icons", $database_folder."rectype-icons" )){//todo move to entity
            
            folderAddIndexHTML($database_folder."rectype-icons"); // index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy record type icons folder rectype-icons to $database_folder";
        }
        if(folderRecurseCopy( HEURIST_DIR."admin/setup/entity", $database_folder."entity" )){
            
            folderAddIndexHTML($database_folder."entity"); // index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy entity folder (icons) to $database_folder";
        }
        
        

        if(folderRecurseCopy( HEURIST_DIR."admin/setup/smarty-templates", $database_folder."smarty-templates" )){
            
            folderAddIndexHTML($database_folder."smarty-templates"); // index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy smarty-templates folder to $database_folder";
        }

        if(folderRecurseCopy( HEURIST_DIR."admin/setup/xsl-templates", $database_folder."xsl-templates" )){
            
            folderAddIndexHTML($database_folder."xsl-templates"); // index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy xsl-templates folder to $database_folder";
        }

        if(folderRecurseCopy( HEURIST_DIR."documentation_and_templates", $database_folder."documentation_and_templates" )){
            
            folderAddIndexHTML($database_folder."documentation_and_templates"); // index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy documentation folder to $database_folder";
        }

        
        // Create all the other standard folders required for the database
        // index.html files are added by createFolder to block index browsing
        $warnings[] = folderCreate2($database_folder. '/filethumbs', 'used to store thumbnails for uploaded files', true);
        $warnings[] = folderCreate2($database_folder. '/file_uploads','used to store uploaded files by default');
        $warnings[] = folderCreate2($database_folder. '/scratch', 'used to store temporary files');
        $warnings[] = folderCreate2($database_folder. '/hml-output', 'used to write published records as hml files', true);
        $warnings[] = folderCreate2($database_folder. '/html-output', 'used to write published records as generic html files', true);
        $warnings[] = folderCreate2($database_folder. '/generated-reports', 'used to write generated reports');
        $warnings[] = folderCreate2($database_folder. '/backup', 'used to write files for user data dump');
        $warnings[] = folderCreate2($database_folder. '/term-images', 'used for images illustrating terms'); //todo move to entity
        
        //remove empty warns
        $warnings = array_filter($warnings, function($value) { return $value !== ''; });

        return $warnings;
    }
    
    
    //
    //
    //
    private static function empty_table($name, $remark, $verbose){

        $mysqli = self::$mysqli;
        
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

    

    //
    //
    //
    public static function databaseEmpty($database_name, $verbose=true){

        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        
        $res = true;
        
        self::initialize();
        $mysqli = self::$mysqli;
        
        if($database_name!=HEURIST_DBNAME){ //switch to database
           $connected = mysql__usedatabase($mysqli, $database_name_full);
        }else{
           $connected = true;
        }
        if(!$connected){
            $msg = 'Failed to connect to database '.$database_name;
            $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) echo '<br><p>'.$msg.'</p>';
            return false;
        }
        
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
                "usrRecPermissions" => "Permissions",
                "recDetails" => "Details",
                "usrBookmarks" => "Bookmarks",
                "Records" => "Records"
            );

            foreach ($tables as $name => $remark) {
                if(! self::empty_table($name, $remark, $verbose)){
                    $res = false;
                    break;
                }
            }

            if($res){
                $res = $mysqli->query("ALTER TABLE Records AUTO_INCREMENT = 0");
                if($res) $mysqli->commit();
            }
        }
    
        if(!$res){
            $mysqli->rollback();
        }

        $mysqli->close();

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
    public static function databaseClone($db_source, $db_target, $verbose, $nodata=false, $isCloneTemplate){

        self::initialize();
        
        $res = true;
        $mysqli = self::$mysqli;
        
        if( !mysql__usedatabase($mysqli, $db_source) ){
            $res = false;
            if($verbose) {
                echo ("<br/><p>Warning: Could not open source database ".$db_source);
            }
        }else{
            updateDatabseToLatest(self::$system);
            
            if( !mysql__usedatabase($mysqli, $db_target) ){
                $res = false;
                if($verbose) {
                    echo ("<br/><p>Warning: Could not open target database ".$db_target);
                }
            }
        }   

        if($res){
                
                //$isCloneTemplate
                $exception_for_clone_template = array('sysugrps','sysusrgrplinks',
                'woot_chunkpermissions','woot_chunks','woot_recpermissions','woots',
                'usrreminders','usrremindersblocklist','recthreadedcomments','usrrecentrecords','usrreportschedule','usrhyperlinkfilters');
                
                $data_tables = array('records','recdetails','reclinks','recrelationshipscache',
                'recsimilarbutnotdupes','recthreadedcomments','recuploadedfiles','usrbookmarks','usrrecentrecords','usrrectaglinks',
                'usrreminders','usrremindersblocklist','woot_chunkpermissions','woot_chunks','woot_recpermissions','woots');
          
                
                $tables = $mysqli->query("SHOW TABLES");  //get all tables from target db
                if($tables){

                    $mysqli->query("SET foreign_key_checks = 0"); //disable
                    $mysqli->query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

                    echo ("<b>Adding records to tables: </b>");
                    while ($table = $tables->fetch_row()) { //loop for all tables
                        $table = $table[0];
                        
                        if($nodata && in_array(strtolower($table), $data_tables)){
                            continue;
                        }
                        if($isCloneTemplate &&  in_array(strtolower($table), $exception_for_clone_template)){
                            continue;
                        }
                        
                        if(strtolower($table)=='usrrecpermissions'){
                            $cnt = mysql__select_value($mysqli,'select count() from usrRecPermissions');
                            if(!($cnt>0)) continue;
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
                    }//while

                    if($isCloneTemplate){
                        //change ownership OR remove entries for all users and groups but 0~3
                        $mysqli->query('delete FROM usrRecTagLinks,usrTags WHERE rtl_TagID=tag_ID AND tag_UGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('delete FROM usrTags WHERE tag_UGrpID NOT IN (0,1,2,3)');

                        $mysqli->query('delete FROM usrBookmarks WHERE bkm_UGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('delete FROM usrSavedSearches WHERE svs_UGrpID NOT IN (0,1,2,3)');
                        
                        $mysqli->query('update Records set rec_AddedByUGrpID=2 WHERE rec_AddedByUGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('update Records set rec_OwnerUGrpID=2 WHERE rec_OwnerUGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('update recUploadedFiles set ulf_UploaderUGrpID=2 WHERE ulf_UploaderUGrpID NOT IN (0,1,2,3)');
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

        return $res;
    }
    
}


?>