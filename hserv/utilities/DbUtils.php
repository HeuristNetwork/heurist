<?php
/**
* DbUtils.php - Class DbUtils
* 
* Handles various database lifecycle and utility operations with database folders.
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
use hserv\utilities\DbRegis;
use hserv\utilities\UArchive;

require_once 'DbExecuteScript.php';
require_once dirname(__FILE__).'/../../external/php/Mysqldump8.php';
require_once dirname(__FILE__).'/../structure/import/importDefintions.php';


/**
* Class DbUtils
* 
* Static class to perform various database lifecycle and utility operations.
*
* This class provides functionality for creating, dropping, cloning, renaming, dumping,
* restoring, and emptying Heurist databases. It also includes methods for managing
* database folders, validating names, handling database registration aspects,
* and managing definition origins. Progress tracking for long operations is also supported.
*
* Public Static Methods:
* - initialize(\mysqli|null $mysqli): Initializes the DbUtils class.
* - setSessionId(int $id): Sets the session ID for progress tracking.
* - setSessionVal(mixed $session_val): Updates the progress value for the current session.
* - databaseCheckNewDefs(string|null $database): Checks for new (unregistered) definitions.
* - databaseDrop(bool $verbose, string|null $database_name, bool|string $createArchive): Removes a database entirely.
* - databaseDump(string|null $database_name, string|null $database_dumpfile, array|null $dump_options, bool $verbose): Dumps database tables to an SQL file.
* - databaseCreateFull(string $database_name, array &$user_record, string|null $templateFileName): Creates a new, fully functional Heurist database.
* - databaseValidateName(string $database_name, int $check_exist_or_unique): Validates a database name.
* - databaseRestoreFromArchive(string $database_name, string $archive_file, int $archive_folder): Restores a database from an archive.
* - databaseCreate(string $database_name, int $level, string|null $dumpfile): Creates a new Heurist database, potentially from a dump.
* - databaseCreateConstraintsAndTriggers(string $database_name): Recreates constraints and triggers for a database.
* - databaseCreateFolders(string $database_name): Creates the standard set of folders for a database.
* - databaseEmpty(string $database_name, bool $verbose): Clears data tables from a database, retaining definitions.
* - databaseClone(string $db_source, string $db_target, bool $verbose, bool $nodata, bool $isCloneTemplate): Copies tables from one database to another.
* - databaseCloneFull(string|null $db_source, string $db_target, bool $nodata, bool $isCloneTemplate): Clones an entire database, including folders.
* - databaseResetRegistration(string $dbname): Removes registration info and assigns origin ID after cloning.
* - databaseUpdateRegistration(string $dbname, array $reg_record): Updates database registration info.
* - databaseRename(string $db_source, string $db_target, bool $createArchive): Renames a database.
* - updateOriginatingDB(int $dbID): Assigns a given Origin ID to definitions.
* - updateImportedOriginatingDB(): Assigns Origin ID for definitions imported from an unregistered database.
*/
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
    private static $db_del_in_progress = null;
    private static $session_id = 0;
    private static $progress_step = 0;

    /**
     * Initializes the DbUtils class.
     * Sets the system and mysqli objects.
     *
     * @param \mysqli|null $mysqli Optional mysqli object. If not provided, it's retrieved from the global $system object.
     * @return void
     */
    public static function initialize($mysqli=null)
    {
        if (self::$initialized) {return;}

        global $system;
        self::$system = $system;

        if($mysqli){
            self::$mysqli = $mysqli;
        }else{
            self::$mysqli = $system->getMysqli();
        }

        self::$initialized = true;
    }
    
    public static function prepareSessionId($session_id){
        // Normalize session_id to string
        if (is_int($session_id)) {
            $session_id = (string)$session_id;
        } elseif (!is_string($session_id)) {
            return '';
        }

        // Validate: 1–15 digits only
        if (!preg_match('/^\d{1,15}$/', $session_id)) {
            return '';
        }
        return $session_id;
    }

    /**
     * Sets the session ID for progress tracking.
     *
     * @param int $id The session ID.
     * @return void
     */
    public static function setSessionId($id){
        self::$session_id = $id;
        self::$progress_step = 0;
    }

    /**
     * Updates the progress value for the current session.
     * Handles incremental progress steps. Checks if the session has been terminated.
     *
     * @param mixed $session_val The value to add to the progress or the absolute progress value.
     * @return bool True if the session has been terminated by the client, false otherwise.
     */
    public static function setSessionVal($session_val){

        if(self::$progress_step>0 && intval($session_val)>0){
            $session_val = self::$progress_step+$session_val;
        }
        $current_val = mysql__update_progress(self::$mysqli, self::$session_id, false, $session_val);
        if($current_val=='terminate'){ //session was terminated from client side
            self::$session_id = 0;
            return true;
        }else{
            return false;
        }
    }

    /**
     * Checks for new (unregistered) definitions (record types, detail types, terms) in a given database.
     *
     * @param string|null $database Optional. The name of the database to check. Defaults to the current database.
     * @return string|false A string listing counts of new definitions if found, otherwise false.
     */
    public static function databaseCheckNewDefs($database=null){

        if($database!=null){
            list($database_full, $database ) = mysql__get_names($database);
            $database_full = '`'.$database_full.'`.';
        }else{
            $database_full = '';
        }

        //check for new definitions
        $rty = mysql__select_value(self::$mysqli, "SELECT count(*) FROM {$database_full}defRecTypes "
            ." WHERE (rty_OriginatingDBID = '0') OR (rty_OriginatingDBID IS NULL)");
        $dty = mysql__select_value(self::$mysqli, "SELECT count(*) FROM {$database_full}defDetailTypes "
            ." WHERE (dty_OriginatingDBID = '0') OR (dty_OriginatingDBID IS NULL)");
        $trm = mysql__select_value(self::$mysqli, "SELECT count(*) FROM {$database_full}defTerms "
            ." WHERE (trm_OriginatingDBID = '0') OR (trm_OriginatingDBID IS NULL)");

        $sHasNewDefsWarning = false;
        if($rty>0 || $dty>0 || $trm>0){
            $s = array();
            if($rty>0) { $s[] = intval($rty).' record types';}
            if($dty>0) { $s[] = intval($dty).' base fields';}
            if($trm>0) { $s[] = intval($trm).' vocabularies or terms';}
            $sHasNewDefsWarning = implode(', ',$s);
        }

        return $sHasNewDefsWarning;
    }

    /**
     * Removes a database entirely, with an option to create an archive before deletion.
     *
     * @param bool $verbose If true, outputs detailed messages. Defaults to false.
     * @param string|null $database_name Name of the database to be deleted. If null, an error is generated.
     * @param bool|string $createArchive If true, creates a db dump and archives uploaded files.
     *                                   Can be 'zip' or 'tar' to specify archive format (defaults to 'zip' if true). Defaults to false.
     * @return bool True on successful deletion, false on failure.
     */
    public static function databaseDrop( $verbose=false, $database_name=null, $createArchive=false ){

        // 1. Create an SQL dump in the filestore direcory
        // 2. Zip the filestore directories (using bzip2) directly into the _DELETED_DATABASES directory
        // 3. Delete filestore directory for the database
        // 4. Drop database
        // 5. Append row to DELETED_DATABASES_LOG.csv in the Heurist filestore.

        self::initialize();

        if(self::$db_del_in_progress!==null){
            //DELETION ALREADY IN PROGRESS
            return false;
        }

        $format = 'zip';
        if(!is_bool($createArchive)){ //default is zip format
            $format = ($createArchive=='tar')?'tar':'zip';
            $createArchive = true;
        }

        self::$db_del_in_progress = null;

        if($database_name==null){
            $msg = 'Database parameter not defined';
            self::$system->addError(HEURIST_INVALID_REQUEST, $msg);
            if($verbose) {echo '<br>'.$msg;}
            return false;
        }

        $mysqli = self::$mysqli;
        $system = self::$system;

        self::$db_del_in_progress = $database_name;

        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        $msg_prefix = "Unable to delete <b> $database_name </b>. ";

        if($database_name!=$system->dbname()){ //switch to database
           $connected = (mysql__usedatabase($mysqli, $database_name_full)===true);
        }else{
           $connected = true;
        }

        $archiveFolder = HEURIST_FILESTORE_ROOT."_DELETED_DATABASES/";
        $db_dump_file = null;

        $source = HEURIST_FILESTORE_ROOT.$database_name.'/';//  HEURIST_FILESTORE_DIR;  database upload folder
        $archOK = true;


        if(!$connected){
            $msg = $msg_prefix.' Failed to connect to database '
                    .($database_name).'  '.($createArchive);
            $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) {echo '<br>'.htmlspecialchars($msg);}
            self::$db_del_in_progress = null;
            return false;
        }elseif($createArchive) {
            // Create _DELETED_DATABASES directory if needed
            if(!folderCreate($archiveFolder, true)){
                    $system->addError(HEURIST_ACTION_BLOCKED,
                        $msg_prefix.' Cannot create archive folder for database to be deleteted.');
                    self::$db_del_in_progress = null;
                    return false;
            }

            self::setSessionVal(1);//archive folder created

            $db_dump_file = self::databaseDump( $database_name, null, null, $verbose );

            if ($db_dump_file===false) {
                    $msg = $msg_prefix.' Failed to dump database to a .sql file';
                    self::$system->addError(HEURIST_ACTION_BLOCKED, $msg);
                    if($verbose) {echo '<br>'.htmlspecialchars($msg);}
                    self::$db_del_in_progress = null;
                    return false;
            }

            if(self::setSessionVal(2)) {return false;} //database dumped

            // Zip $source to $destination
            $datetime1 = date_create('now');
            $destination = $archiveFolder.$database_name.'_'.$datetime1->format('Y-m-d_H_i_s');

            $filestore_dir = HEURIST_FILESTORE_ROOT.$database_name.'/';
            $folders_to_copy = folderSubs($filestore_dir, array('backup', 'scratch', 'documentation',
            //'uploaded_files', 'uploaded_tilestacks',
            'rectype-icons','term-images','webimagecache','blurredimagescache'));
            foreach($folders_to_copy as $idx=>$folder_name){
                $folder_name = realpath($folder_name);
                if($folder_name!==false){
                    $folders_to_copy[$idx] = str_replace('\\', '/', $folder_name);
                }

            }

            $folders_to_copy[] = realpath($db_dump_file);

            if($format=='zip' || !extension_loaded('bz2')){

                $destination = $destination.'.zip';

                $archOK = UArchive::zip($source, $folders_to_copy, $destination, $verbose);
            }else{
                $destination = $destination.'.tar';

                $archOK = UArchive::createBz2($source, $folders_to_copy, $destination, $verbose);
            }

            if($archOK!==true){

                if($verbose){
                    $msg_prefix = $msg_prefix.' <br>'.$archOK;
                    $archOK = false;
                }

                $msg = $msg_prefix.' Cannot create archive with database folder. Failed to archive '
                        .($source).' to '.($destination);
                self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                if($verbose) {echo '<br>'.htmlspecialchars($msg);}
                self::$db_del_in_progress = null;
                return false;
            }

            if(self::setSessionVal(3)) {return false;} //database dump archived
        }

        if($archOK){

            //get owner info
            $owner_user = user_getDbOwner($mysqli);

            //set it to false to check archiving only
            $real_delete_database = true;
            if($real_delete_database){

                $regID = mysql__select_value($mysqli, 'select sys_dbRegisteredID from sysIdentification where 1');


                // Delete database from MySQL server
                if(!mysql__drop_database($mysqli, $database_name_full)){

                    $msg = $msg_prefix.' Database error on sql drop operation. '.$mysqli->error;
                    self::$system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
                    if($verbose) {echo '<br>'.htmlspecialchars($msg);}
                    return false;
                }

                if(self::setSessionVal(4)) {return false;} //database dropped

                if($verbose) {
                    echo "<br>Database ".htmlspecialchars($database_name)." has been dropped";
                }

                // Delete $source folder
                folderDelete($source);
                //change current folder
                chdir(HEURIST_FILESTORE_ROOT);
                if($verbose) {
                    echo "<br>Folder ".htmlspecialchars($source)." has been deleted";
                }
                if(self::setSessionVal(5)) {return false;} //database folder deleted

                //add to log file
                $filename = HEURIST_FILESTORE_ROOT.'DELETED_DATABASES_LOG.csv';
                $fp = fopen($filename, 'a');//open for add
                if($fp){
                    $row = array($database_name,
                        $owner_user['ugr_LastName'],
                        $owner_user['ugr_FirstName'],
                        $owner_user['ugr_eMail'],
                    date_create('now')->format(DATE_8601));
                    fputcsv($fp, $row);
                    fclose($fp);
                }

                if($regID>0)
                {
                    /* TEMP
                    $dbowner = user_getDbOwner($mysqli);
                    $params = array(
                        'action'=>'delete',
                        'dbID'=>$regID,
                        'usrPassword'=>$dbowner['ugr_Password'],
                        'usrEmail'=>$dbowner['ugr_eMail']
                    );
                    $res = DbRegis::registrationDelete($params);
                    // if not integer - this is error
                    if(is_bool($res) && $res===false){
                        self::$system->addErrorMsg(
                            'Failed to delete record in reference index for #'.$regID.' for deleted database '.$db_target.'<br>');
                    }
                    */
                }
            }

            self::$db_del_in_progress = null;
            return true;
        }


        self::$db_del_in_progress = null;
        return false;

    }


    /**
     * Dumps all tables of a specified database into an SQL file.
     *
     * @param string|null $database_name Database name. Defaults to the current database if null.
     * @param string|null $database_dumpfile Path to the output SQL dump file. If null, a default path is generated.
     * @param array|null $dump_options Options for mysqldump or the PHP-based dumper. See code for details.
     * @param bool $verbose If true, outputs detailed messages. Defaults to false.
     * @return string|false The path to the created dump file on success, false on failure.
     */
    public static function databaseDump($database_name=null, $database_dumpfile=null, $dump_options=null, $verbose=false ) {

        self::initialize();

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $mysqli = self::$mysqli;

        if($database_name!=self::$system->dbname()){ //switch to database
           $connected = (mysql__usedatabase($mysqli, $database_name_full)===true);
        }else{
           $connected = true;
        }

        if($connected){

            // dump will be created in database upload folder
            if($database_dumpfile==null){
                $directory = HEURIST_FILESTORE_ROOT.basename($database_name);

                // Define dump file name
                $database_dumpfile = $directory.'/'.basename($database_name_full).'_'.time().'.sql';
            }

            if($dump_options==null){
                $dump_options = array(
                        'add-drop-table' => true,
                        'single-transaction' => true, //improve performance on restore
                        'quick' =>true,               //improve performance on restore
                        'add-drop-trigger' => true,
                        //'databases' => true,
                        'skip-triggers' =>true,
                        'skip-dump-date' => true,
                        //'routines' =>true,
                        'no-create-db' =>true,
                        'add-drop-database' => true);

                //do not archive sysArchive and import tables??


            }else{
                //$dump_options = array('skip-triggers' => true,  'add-drop-trigger' => false);
            }

            //0: use 3d party PDO mysqldump, 2 - call mysql via shell (default)
            $dbScriptMode = defined('HEURIST_DB_MYSQL_DUMP_MODE')?HEURIST_DB_MYSQL_DUMP_MODE :0;

            if($dbScriptMode==2){  //use native mysqldump
                if (!defined('HEURIST_DB_MYSQLDUMP') || !file_exists(HEURIST_DB_MYSQLDUMP)){

                    $msg = 'The path to mysqldump has not been correctly specified. '
                    .'Please ask your system administrator to fix this in the heuristConfigIni.php '
                    .'(note the settings required for a single server vs mysql running on a separate server)';

                    self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                    if($verbose) {echo '<br>'.$msg;}
                    return false;
                }
            }else{ //use php library
                $dbScriptMode = 0;
            }

            if($verbose){
                echo 'dump mode: '.$dbScriptMode.'<br>';
            }

            if($dbScriptMode==2){ // use mysql native mysqldump utility via shell

                $tables = array();
                $options = '';

                foreach($dump_options as $opt => $val){

                    if($opt=='include-tables'){
                        if(!isEmptyArray($val)){
                            $tables = $val;
                        }
                    }elseif($val===true){
                        $options = $options .' --'.$opt;
                    }elseif($val!==false){
                        $options = $options .' --'.$opt.'='.$val;
                    }
                }

                if(!empty($tables)){
                    $tables = implode(' ', $tables);//'--tables '.
                }else{
                    $tables = '';
                }

                //--log-error=mysqldump_error.log -h {$server_name}
                //--hex-blob --routines --skip-lock-tables
                //-u ".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD."
                $res2 = null;

                //https://dev.mysql.com/doc/refman/8.0/en/mysql-config-editor.html
                // use mysql_config_editor to store authentication credentials
                // in an obfuscated login path file named .mylogin.cnf.


                $cmd = escapeshellcmd(HEURIST_DB_MYSQLDUMP);
                if(strpos(HEURIST_DB_MYSQLDUMP,' ')>0){
                    $cmd = '"'.$cmd.'"';
                }

                $port = '';
                if(HEURIST_DB_PORT){
                    $port = " -P ".HEURIST_DB_PORT;
                }

                $cmd = $cmd
                ." -h ".HEURIST_DBSERVER_NAME." ".$port
                ." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD
                //." --login-path=local
                ." {$options} ".escapeshellarg($database_name_full)
                ." {$tables} > ".$database_dumpfile;

                $arr_out = array();

                exec($cmd, $arr_out, $res2);

                if($res2 !== 0) {

                    $msg = 'mysqldump for '.htmlspecialchars($database_name_full)
                            .' failed with a return status: '.($res2!=null?intval($res2):'unknown')
                            .'. Output: '.(is_array($arr_out)&&!empty($arr_out)?print_r($arr_out, true):'');

                    if($verbose) {echo '<br>'.$msg;}

                    self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);


                    //echo "Error message was:\n";
                    //$file = escapeshellarg("mysqldump_error.log");
                    //$message = `tail -n 1 $file`;
                    //echo "- $message\n\n";

                    return false;
                }elseif($verbose){
                    echo 'MySQL Dump completed<br>';
                }


            }
            else{ //USE 3d Party php MySQLdump lib

                if(@$dump_options['quick']){ unset($dump_options['quick']);} //not supported
                if(@$dump_options['no-create-db']){ unset($dump_options['no-create-db']);}

                try{
                    $port = '';
                    if(HEURIST_DB_PORT){
                        $port = ';port='.HEURIST_DB_PORT;
                    }
                    $pdo_dsn = 'mysql:host='.HEURIST_DBSERVER_NAME.$port.';dbname='.$database_name_full.';charset=utf8mb4';
                    $dump = new \Mysqldump( $pdo_dsn, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, $dump_options);

                    $dump->start($database_dumpfile);
                } catch (\Exception $e) {
                    self::$system->addError(HEURIST_SYSTEM_CONFIG, $e->getMessage());
                    return false;
                }

            }

            //$mysqli->close();

            chmod($database_dumpfile, 0750);

            // Echo output
            if($verbose) {
                $size = filesize($database_dumpfile) / pow(1024,2);
                echo "<br>Successfully dumped "
                    .htmlspecialchars($database_name)." to ".htmlspecialchars($database_dumpfile);
                echo "<br>Size of SQL dump: ".htmlspecialchars(sprintf("%.2f", $size))." MB";
            }

            return $database_dumpfile;

        }else{
            $msg = 'Failed to connect to database '.htmlspecialchars($database_name_full);
            self::$system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) {echo '<br>'.$msg;}
            return false;
        }
    }

    /**
     * Creates a new Heurist database with file folders, initializes it with a given user, and makes it ready to use.
     *
     * @param string $database_name Target database name.
     * @param array &$user_record User data (by reference) that will be set as the db owner.
     * @param string|null $templateFileName Path to a text file with database definitions (e.g., "coreDefinitions.txt").
     *                                      Defaults to HEURIST_DIR."admin/setup/dbcreate/coreDefinitions.txt".
     * @return array|false An array of warning messages on success (can be empty), or false on critical failure.
     */
    public static function databaseCreateFull($database_name, &$user_record, $templateFileName=null){

            self::initialize();
            $mysqli = self::$mysqli;
            $system = self::$system;

            if($templateFileName==null){
                $templateFileName = HEURIST_DIR."admin/setup/dbcreate/coreDefinitions.txt";
            }
            $templateFoldersContent = 'NOT DEFINED';//it is used for template database only

            //check template
            if(!file_exists($templateFileName)){
                $system->addError(HEURIST_SYSTEM_CONFIG,
                        'Template database structure file '.$templateFileName.' not found');
                return false;
            }

            list($database_name_full, $database_name) = mysql__get_names( $database_name );

            //checks that database name is valid, correct length and unique
            $error_msg = self::databaseValidateName($database_name, 1);//unique
            if ($error_msg!=null) {
                self::$system->addError(HEURIST_ACTION_BLOCKED, $error_msg);
                return false;
            }

            if(self::setSessionVal(1)) {return false;}

            //create folders
            $upload_root = self::$system->getFileStoreRootFolder();

            $database_folder = $upload_root.$database_name.'/';

            $warnings = self::databaseCreateFolders($database_name);
            if(!isEmptyArray($warnings)){
                folderDelete($database_folder);
                self::$system->addError(HEURIST_ACTION_BLOCKED,
                                            implode("<br>",$warnings));
                return false;
            }

            if(self::setSessionVal(2)) {return false;}

            //create empty database
            if(!self::databaseCreate($database_name_full)){ //with structure and triggers from default dump file
                folderDelete($database_folder);
                return false;
            }

            if(self::setSessionVal(3)) {return false;}

            //switch to new database
            if(!self::importDefinitionsFromTemplate($database_name_full, $templateFileName)){
                //rollback
                folderDelete($database_folder);
                mysql__drop_database( $mysqli, $database_name_full );
                return false;
            }

            //override content of setting folders with template database files - rectype icons, dashboard icons, smarty templates etc
            //not used
            if(file_exists($templateFoldersContent) && filesize($templateFoldersContent)>0){
                $upload_root = $system->getFileStoreRootFolder();

                $unzip_error = null;
                try{
                    UArchive::unzip($system, $templateFoldersContent, $upload_root.$database_name.'/');
                }catch(\Exception $e){
                    array_push($warnings, 'Cannot extract template folders from archive '.$templateFoldersContent
                                //.' Target :'.$upload_root.$database_name
                                .' Error: '.$e->getMessage());
                }
            }

            $warnings2 = self::_databaseInitForNew($user_record);

            if(self::setSessionVal(5)) {return false;}

            $warnings = array_merge($warnings, $warnings2);

            //self::setSessionVal('REMOVE');

            return $warnings;
    }

    /**
     * Imports core definitions from a template file into the specified database.
     *
     * @param string $database_name_full - The full name of the database to import definitions into.
     * @param string $templateFileName - The path to the template file containing the definitions.
     *
     * @return bool - Returns true on success, false on failure.
     */
    private static function importDefinitionsFromTemplate($database_name_full, $templateFileName){

        $mysqli = self::$mysqli;
        $system = self::$system;

        // Switch to the target database
        mysql__usedatabase( $mysqli, $database_name_full );

        if(file_exists($templateFileName) && filesize($templateFileName)>0){

            //import definitions from template file
            $idef = new \ImportDefinitions();
            $idef->initialize( $mysqli );

            if(!$idef->doImport( $templateFileName )) {

                $system->addError(HEURIST_SYSTEM_CONFIG,
                    'Error importing core definitions from '
                    . basename($templateFileName)
                    .' for database '.$database_name_full.'<br>'
                    .'Check whether this file or database is valid.'.CONTACT_HEURIST_TEAM_PLEASE.' if needed');

                return false;
            }

        }
        if(self::setSessionVal(4)) {return false;} //import core defs

        return true;
    }




    /**
    * Updates dbowner, adds default saved searches (for users ##1,2) and lookups (geonames and nakala)
    * it uses current database
    */
    private static function _databaseInitForNew(&$user_record)
    {
            $warnings = array();

            $mysqli = self::$mysqli;

            //update owner user (#2) in new database
            $user_record['ugr_ID'] = 2;
            $user_record['ugr_NavigationTree'] = '"bookmark":{"expanded":true,"key":"root_1","title":"root","children":[{"folder":false,"key":"_1","title":"Recent changes","data":{"url":"?w=bookmark&q=sortby:-m after:\"1 week ago\"&label=Recent changes"}},{"folder":false,"key":"_2","title":"All (date order)","data":{"url":"?w=bookmark&q=sortby:-m&label=All records"}}]},"all":{"expanded":true,"key":"root_2","title":"root","children":[{"folder":false,"key":"_3","title":"Recent changes","data":{"url":"?w=all&q=sortby:-m after:\"1 week ago\"&label=Recent changes"}},{"folder":false,"key":"_4","title":"All (date order)","data":{"url":"?w=all&q=sortby:-m&label=All records"}}]}';
//,{"folder":true,"key":"_5","title":"Rules","children":[{"folder":false,"key":"12","title":"Person > anything they created","data":{"isfaceted":false}},{"folder":false,"key":"13","title":"Organisation > Assoc. places","data":{"isfaceted":false}}]}
            $user_record['ugr_Preferences'] = '';

            $ret = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', $user_record);
            if($ret!=2){
                array_push($warnings, 'Cannot set owner user. '.$ret);
            }

            //add default saved searches and tree
            $navTree = '{"expanded":true,"key":"root_3","title":"root","children":[{"expanded":true,"folder":true,"key":"_1","title":"Save some filters here ...","children":[]}]}';
//{"key":"28","title":"Organisations","data":{"isfaceted":false}},{"key":"29","title":"Persons","data":{"isfaceted":false}},{"key":"30","title":"Media items","data":{"isfaceted":false}}
            $ret = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', array('ugr_ID'=>1, 'ugr_NavigationTree'=>$navTree ));
            if($ret!=1){
                array_push($warnings, 'Cannot set navigation tree for group 1. '.$ret);
            }

            //ADD DEFAULT LOOKUPS
            $def_lookups = array();

            $to_replace = array('DB_ID', 'DTY_ID', 'RTY_ID');
            $dty_CCode = 'SELECT dty_ID FROM defDetailTypes INNER JOIN defRecStructure ON rst_DetailTypeID = dty_ID WHERE dty_OriginatingDBID = DB_ID AND dty_IDInOriginatingDB = DTY_ID AND rst_RecTypeID = RTY_ID';

            // GeoNames
            $rty_query = 'SELECT rty_ID FROM defRecTypes WHERE rty_OriginatingDBID = 3 AND rty_IDInOriginatingDB = 1009';
            $rty_id = mysql__select_value($mysqli, $rty_query);
            if(!empty($rty_id)){

                $fld_name = mysql__select_value($mysqli, str_replace($to_replace, array('2', '1', $rty_id), $dty_CCode));
                $fld_name = (empty($fld_name)) ? '' : $fld_name;

                $fld_geo = mysql__select_value($mysqli, str_replace($to_replace, array('2', '28', $rty_id), $dty_CCode));
                $fld_geo = (empty($fld_geo)) ? '' : $fld_geo;

                $fld_cc = mysql__select_value($mysqli, str_replace($to_replace, array('2', '26', $rty_id), $dty_CCode));
                $fld_cc = (empty($fld_cc)) ? '' : $fld_cc;

                $fld_fname = mysql__select_value($mysqli, str_replace($to_replace, array('3', '1068', $rty_id), $dty_CCode));
                $fld_fname = (empty($fld_fname)) ? '' : $fld_fname;

                $fld_id = mysql__select_value($mysqli, str_replace($to_replace, array('2', '581', $rty_id), $dty_CCode));
                $fld_id = (empty($fld_id)) ? '' : $fld_id;

                $key = 'geoName_' . $rty_id;
                $def_lookups[$key] = array('service' => 'geoName', 'rty_ID' => $rty_id, 'label' => 'GeoName', 'dialog' => 'lookupGN', 'fields' => null);
                $def_lookups[$key]['fields'] = array('name' => $fld_name, 'lng' => $fld_geo, 'lat' => $fld_geo, 'countryCode' => $fld_cc, 'adminCode1' => "", 'fclName' => $fld_fname, 'fcodeName' => "", 'geonameId' => $fld_id, 'population' => "");
            }

            // Nakala
            $rty_query = 'SELECT rty_ID FROM defRecTypes WHERE rty_OriginatingDBID = 2 AND rty_IDInOriginatingDB = 5';
            $rty_id = mysql__select_value($mysqli, $rty_query);
            if(!empty($rty_id)){

                $fld_url = mysql__select_value($mysqli, str_replace($to_replace, array('2', '38', $rty_id), $dty_CCode));
                $fld_url = (empty($fld_url)) ? '' : $fld_url;

                $fld_title = mysql__select_value($mysqli, str_replace($to_replace, array('2', '1', $rty_id), $dty_CCode));
                $fld_title = (empty($fld_title)) ? '' : $fld_title;

                $fld_aut = mysql__select_value($mysqli, str_replace($to_replace, array('2', '15', $rty_id), $dty_CCode));
                $fld_aut = (empty($fld_aut)) ? '' : $fld_aut;

                $fld_date = mysql__select_value($mysqli, str_replace($to_replace, array('2', '10', $rty_id), $dty_CCode));
                $fld_date = (empty($fld_date)) ? '' : $fld_date;

                $fld_lic = mysql__select_value($mysqli, str_replace($to_replace, array('1144', '318', $rty_id), $dty_CCode));
                $fld_lic = (empty($fld_lic)) ? '' : $fld_lic;

                $fld_type = mysql__select_value($mysqli, str_replace($to_replace, array('2', '41', $rty_id), $dty_CCode));
                $fld_type = (empty($fld_type)) ? '' : $fld_type;

                $fld_desc = mysql__select_value($mysqli, str_replace($to_replace, array('2', '3', $rty_id), $dty_CCode));
                $fld_desc = (empty($fld_desc)) ? '' : $fld_desc;

                $fld_name = mysql__select_value($mysqli, str_replace($to_replace, array('2', '62', $rty_id), $dty_CCode));
                $fld_name = (empty($fld_name)) ? '' : $fld_name;

                $key = 'nakala_' . $rty_id;
                $def_lookups[$key] = array('service' => 'nakala', 'rty_ID' => $rty_id, 'label' => 'Nakala Lookup', 'dialog' => 'lookupNakala', 'fields' => null);
                $def_lookups[$key]['fields'] = array('url' => $fld_url, 'title' => $fld_title, 'author' => $fld_aut, 'date' => $fld_date, 'license' => $fld_lic, 'mime_type' => $fld_type, 'abstract' => $fld_desc, 'rec_url' => '', 'filename' => $fld_name);
            }

            if(!empty($def_lookups)){

                $lookup_str = json_encode($def_lookups);
                $upd_query = "UPDATE sysIdentification SET sys_ExternalReferenceLookups = ? WHERE sys_ID = 1";
                mysql__exec_param_query($mysqli, $upd_query, array('s', $lookup_str));
            }else{
                array_push($warnings, 'Unable to setup default lookup services.');
            }

            return  $warnings;

    }


    /**
     * Verifies that a database name is valid and optionally checks if the database exists or is unique.
     *
     * @param string $database_name The name of the database to validate.
     * @param int $check_exist_or_unique Optional. 0 to skip existence/uniqueness check (default).
     *                                     1 to check if the database name is unique (must not exist).
     *                                     2 to check if the database exists.
     * @return string|null Null if the name is valid and passes checks, otherwise an error message string.
     */
    public static function databaseValidateName($database_name, $check_exist_or_unique=1){

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $error_msg = mysql__check_dbname($database_name_full);

        if ($check_exist_or_unique>0 && $error_msg==null) {

            $resereved = array('deleted_databases','dbs_to_restore','aaa_logs','installation','tutorials','heurist','startup','matomo');

            if($check_exist_or_unique==1 &&
                in_array( strtolower($database_name), $resereved ) ){

                $error_msg = 'Database name '.htmlspecialchars($database_name).' is reserved. Try different name.';

            }else{
                //verify that database with such name already exists
                $dblist = mysql__select_list2(self::$mysqli, 'show databases');
                if (array_search(strtolower($database_name_full), array_map('strtolower', $dblist)) !== false ){
                    if($check_exist_or_unique==1){
                        $error_msg = 'Database with name '.htmlspecialchars($database_name_full).' aready exists. Try different name.';
                    }
                }elseif($check_exist_or_unique==2){
                        $error_msg = 'Database with name '.htmlspecialchars($database_name_full).' does not exists.';
                }
            }
        }

        return $error_msg;
    }

    /**
     * Restores a database from an archive file.
     *
     * @param string $database_name Name of the target database to create/restore.
     * @param string $archive_file Name of the archive file (e.g., .zip, .tar.bz2).
     * @param int $archive_folder Identifier for the source folder of the archive.
     *                            1: _DELETED_DATABASES (default)
     *                            2: /srv/BACKUP/
     *                            3: /srv/BACKUP/ARCHIVE/ (or HEURIST_FILESTORE_ROOT.'BACKUP/ARCHIVE/' for local dev)
     *                            4: _DBS_TO_RESTORE
     *                            5: _DBS_FROM_REMOTES
     * @return bool True on success, false on failure.
     */
    public static function databaseRestoreFromArchive($database_name, $archive_file, $archive_folder=1){

        self::initialize();

        $upload_root = self::$system->getFileStoreRootFolder();

        //only from limited list of folders
        $source = intval($archive_folder);
        if($source==2){
            $lib_path = '/srv/BACKUP/';
        }elseif($source==3){
            if(strpos(HEURIST_BASE_URL, '://127.0.0.1')>0){
                $lib_path = HEURIST_FILESTORE_ROOT.'BACKUP/ARCHIVE/';
            }else{
                $lib_path = '/srv/BACKUP/ARCHIVE/';
            }
        }elseif($source==4){
            $lib_path = $upload_root.'_DBS_TO_RESTORE/';
        }elseif($source == 5){
            $archive_file = "{$upload_root}_DBS_FROM_REMOTES/{$archive_file}/backup/{$archive_file}.zip";
            $archive_file = !file_exists($archive_file) ? "{$upload_root}_DBS_FROM_REMOTES/{$archive_file}/backup/{$archive_file}_sql.zip" : $archive_file;
        }else{
            //default
            $lib_path = $upload_root.'_DELETED_DATABASES/';
        }

        if($source != 5){
            $archive_file = $lib_path.basename($archive_file);
        }

        //check archive
        if(!file_exists($archive_file)){
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Database archive file not found');
            return false;
        }

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        //check database name and unique
        $error_msg = self::databaseValidateName($database_name, 1);//unique
        if ($error_msg!=null) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, $error_msg);
            return false;
        }

        self::setSessionVal(1);//database name and archive validated

        //create folders and all subfolders with default content
        $database_folder = $upload_root.$database_name.'/';

        $warnings = self::databaseCreateFolders($database_name);
        if(!isEmptyArray($warnings)){
            folderDelete($database_folder);
            self::$system->addError(HEURIST_ACTION_BLOCKED,
                                    implode('<br>',$warnings));
            return false;
        }

        self::setSessionVal(2);//folders created

        //if true the archive is sql dump - need to copy the minimal set of files from current database
        $needCopyCurrentDbFolder = false;
        //unpack archive into this folder
        $unzip_error = null;
        try{
            $path_parts = pathinfo($archive_file);
            $ext = 'zip';
            if(array_key_exists('extension', $path_parts)){
                $ext = $path_parts['extension'];
            }

            if(strcasecmp($ext, 'bz2')==0 || strpos($archive_file,'.sql.bz2.')>0){
                if(extension_loaded('bz2')){
                    $needCopyCurrentDbFolder = true;
                    UArchive::bunzip2($archive_file, $database_folder.'dump.sql');
                }else{
                    throw new \Exception('bz2 extension is not detected');
                }
            }else{
                $fileCount = UArchive::unzip(self::$system, $archive_file, $database_folder);
                $needCopyCurrentDbFolder = $fileCount == 1 && $source != 5;
            }

        }catch(\Exception $e){
            folderDelete($database_folder);
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Cannot unpack database archive. '
                            .' Error: '.$e->getMessage());
            return false;
        }

        self::setSessionVal(3);//unpack archive

        //find dump file
        $dumpfile = folderFirstFile($database_folder, 'sql', false);

        //create database and import data from dumpfile
        if(!file_exists($dumpfile)){

            folderDelete($database_folder);
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Archive does not contain sql dump file');
            return false;

        }else{

            //$subs = folderGetSubFolders($database_folder);
            if($needCopyCurrentDbFolder){
                //archive does not contain any file but database dump
                //copy folders from current database

                if(folderRecurseCopy( HEURIST_FILESTORE_DIR, $database_folder )){
                    self::databaseUpdateFilePaths(self::$system->dbname() , $database_name);
                    
                    //drop defintions cache
                    $entityDir = self::$system->getSysDir('entity', $database_name);
                    fileDelete( $entityDir. 'dbdef_cache.json');
                }else{
                    folderDelete($database_folder);
                    self::$system->addError(HEURIST_ACTION_BLOCKED,
                        'Sorry, we were not able to copy file directories for restoring database.');
                    return false;
                }
            }
            $dumpfile_from_archive = $database_folder.basename($dumpfile);
          
            $res = self::databaseCreate($database_name, 1, $dumpfile_from_archive);//from archive

            self::setSessionVal(4);//database restored from dump

            fileDelete($dumpfile_from_archive);
            
            if(!$res){
                folderDelete($database_folder);
            }else{
                $path = realpath(dirname(__FILE__).'/../../../');
                $now = getNow();
                fileAdd("{$database_name} # restored {$now->format('Y-m-d')}\n",
                            $path.'/databases_not_to_purge.txt');
            }

            return $res;
        }
    }
    
    /**
     * Stream-rewrite a MySQL dump:
     *  - DEFINER=`user`@`host`  => DEFINER=CURRENT_USER
     *  - COLLATE=utf8mb4_0900_* => either removed or replaced with a generic collation
     *
     * Works chunk-wise (handles matches across chunk boundaries).
     */
    private static function rewriteDump(string $src, array $opts = []): void
    {
        $replaceCollation = $opts['replaceCollation'] ?? 'utf8mb4_general_ci'; // or 'utf8mb4_unicode_ci'
        $dropCollation    = $opts['dropCollation'] ?? false; // if true, remove COLLATE=... instead of replace

        $dir  = dirname($src);
        $base = basename($src);

        // temp file in same directory (required for rename() on Windows)
        $tmp = $dir . DIRECTORY_SEPARATOR . '.' . $base . '.tmp';

        $in = fopen($src, 'rb');
        if (!$in) {
            throw new \RuntimeException("Cannot open dump for read: $src");
        }

        $out = fopen($tmp, 'wb');
        if (!$out) {
            fclose($in);
            throw new \RuntimeException("Cannot open temp file for write: $tmp");
        }

        // Regexes are designed to be applied to chunks (not lines).
        $reDefiner = '~DEFINER=`[^`]*`@`[^`]*`~';
        // Match COLLATE=utf8mb4_0900_xxx (xxx can be ai_ci, as_cs, etc.)
        $reColl0900 = '~\bCOLLATE\s*=\s*utf8mb4_0900_[A-Za-z0-9_]+~';
        // Some dumps use "COLLATE utf8mb4_0900_ai_ci" (space form), handle that too
        $reColl0900Space = '~\bCOLLATE\s+utf8mb4_0900_[A-Za-z0-9_]+~';

        $chunkSize = $opts['chunkSize'] ?? (8 * 1024 * 1024); // 8MB is a good default for 100–500MB files
        $carry = '';

        // Keep enough tail to cover the longest pattern that could straddle chunks.
        // 256 bytes is plenty for these tokens; bump if you add more complex patterns later.
        $carryLen = $opts['carryLen'] ?? 256;

        while (!feof($in)) {
            $chunk = fread($in, $chunkSize);
            if ($chunk === false) break;
            if ($chunk === '') continue;

            $buf = $carry . $chunk;

            // Transform DEFINER
            if (strpos($buf, 'DEFINER=`') !== false) {
                $buf = preg_replace($reDefiner, 'DEFINER=CURRENT_USER', $buf);
            }

            // Transform COLLATE utf8mb4_0900_*
            if (strpos($buf, 'utf8mb4_0900_') !== false) {
                if ($dropCollation) {
                    $buf = preg_replace($reColl0900, '', $buf);
                    $buf = preg_replace($reColl0900Space, '', $buf);
                } else {
                    $buf = preg_replace($reColl0900, 'COLLATE=' . $replaceCollation, $buf);
                    $buf = preg_replace($reColl0900Space, 'COLLATE ' . $replaceCollation, $buf);
                }
            }

            // Write all but the tail, keep tail as carry for boundary safety.
            if (strlen($buf) > $carryLen) {
                $write = substr($buf, 0, -$carryLen);
                $carry = substr($buf, -$carryLen);
                fwrite($out, $write);
            } else {
                // Buffer still small; keep accumulating (rare unless file is tiny / chunkSize tiny)
                $carry = $buf;
            }
        }

        // Flush remainder
        if ($carry !== '') {
            fwrite($out, $carry);
        }

        fclose($in);
        fclose($out);
        
        // Replace original file
        @unlink($src);
        if (!rename($tmp, $src)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to replace dump file: $src");
        }    
    }
        

    /**
     * Creates a new Heurist database.
     *
     * @param string $database_name The name for the new database.
     * @param int $level Optional. Level of creation:
     *                   0: Create an empty database.
     *                   1: Create database and import structure (typically from $dumpfile).
     *                   2: Create database, import structure, and add constraints/triggers (default).
     * @param string|null $dumpfile Optional. Name of the SQL dump file to use for creating structure/data.
     *                              Defaults to 'blankDBStructure.sql'. Assumed to be in the db folder or admin/setup/dbcreate.
     * @return bool True on success, false on failure.
     */
    public static function databaseCreate($database_name, $level=2, $dumpfile=null){

        self::initialize();

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $error_msg = self::databaseValidateName($database_name, 1);//unique
        if ($error_msg!=null) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, $error_msg);
            return false;
        }

        $database_folder = null;
        
        $start = microtime(true);

        if($dumpfile==null){
            $dumpfile = 'blankDBStructure.sql';
        }else{
            $dumpfile = basename($dumpfile);

            $upload_root = self::$system->getFileStoreRootFolder();
            $database_folder = $upload_root.$database_name.'/';
            $dump_name_full = $database_folder.$dumpfile;
            
            try{
                self::rewriteDump($dump_name_full, [
                    'dropCollation' => true,
                    // safest for MySQL 5.7 + MariaDB
                    //'replaceCollation' => 'utf8mb4_general_ci',
                ]); 
            }catch (\RuntimeException $e) {         
                    $error = 'Error: '.print_r($e->getMessage(), true);
                    error_log($error);
                    self::$system->addErrorArr($error);
                    return false;
            }
            
            $elapsed = microtime(true) - $start;
            error_log('rewriteDump '.$dumpfile.' execution time: '.number_format($elapsed, 3). 'seconds');
            $start = microtime(true);

        }

        $mysqli = self::$mysqli;

        $res = mysql__create_database($mysqli, $database_name_full);

        if (is_array($res)){
            self::$system->addErrorArr($res);//can't create
            mysql__drop_database($mysqli, $database_name_full);
            return false;
        }elseif($level<1){
            return true; //create empty database
        }

        //restore data from sql dump
        $res = mysql__script($database_name_full, $dumpfile, $database_folder);//restore from dump
        
        $elapsed = microtime(true) - $start;
        error_log('Restore '.$database_name_full.' from dump execution time: '.number_format($elapsed, 3). 'seconds');
        
        if($res!==true){
            $res[1] = 'Cannot create database tables. '.$res[1];
            self::$system->addErrorArr($res);
        }elseif($level<2){
            return true;  //without constraints and triggers
        }elseif(self::databaseCreateConstraintsAndTriggers($database_name_full)){
            return true;
        }

        if (is_array($res)){
            self::$system->addErrorArr($res);//can't create
        }elseif($level<1){
            return true; //create empty database
        }

        //fails
        mysql__drop_database($mysqli, $database_name_full);
        return false;
    }


    /**
     * Recreates constraints and triggers for a specified database by executing SQL script files.
     *
     * @param string $database_name The name of the database.
     * @return bool True on success, false if any script fails.
     */
    public static function databaseCreateConstraintsAndTriggers($database_name){

        self::initialize();
        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $mysqli = self::$mysqli;

        $res = mysql__script($database_name_full, 'addReferentialConstraints.sql');
        if($res===true){
            $res = mysql__script($database_name_full, 'addProceduresTriggers.sql');
        }

        if($res!==true){
            self::$system->addErrorArr($res);
            $res = false;
        }

        return $res;

    }

    /**
     * Creates the standard set of folders required for a Heurist database if they do not already exist.
     *
     * @param string $database_name The name of the database for which to create folders.
     * @param string $server_prefix Server prefix to attach to filestore directory.
     * @return array An array of warning messages if any folder creation failed. Empty if all successful.
     */
    public static function databaseCreateFolders($database_name, $server_prefix = ''){

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $upload_root = self::$system->getFileStoreRootFolder();

        if($server_prefix !== ''){
            $upload_root = str_replace('HEURIST_FILESTORE', "HEURIST_FILESTORE_{$server_prefix}", $upload_root);
        }

        // Create a default upload directory for uploaded files eg multimedia, images etc.
        $database_folder = $upload_root.$database_name.'/';

        if (folderCreate($database_folder, true)){
            folderAddIndexHTML( $database_folder );//add index file to block directory browsing
        }else{
            return array('Heurist was unable to create the required database root folder,<br>Database name: '
                        . $database_name . '<br>Server url: ' . HEURIST_BASE_URL);//, 'revert'=>true
        }

        $warnings = array();

        if(folderRecurseCopy( HEURIST_DIR."admin/setup/dbcreate/icons", $database_folder."entity" )){

            folderAddIndexHTML($database_folder."entity");// index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy entity folder (icons) to $database_folder";
        }



        if(folderRecurseCopy( HEURIST_DIR."admin/setup/dbcreate/smarty-templates", $database_folder."smarty-templates" )){

            folderAddIndexHTML($database_folder."smarty-templates");// index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy smarty-templates folder to $database_folder";
        }
/* removed 2021-07-15
        if(folderRecurseCopy( HEURIST_DIR."admin/setup/xsl-templates", $database_folder."xsl-templates" )){

            folderAddIndexHTML($database_folder."xsl-templates");// index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy xsl-templates folder to $database_folder";
        }
*/
    //since 2023-06-02 documentation is not created
    /*
    if(false){
        if(folderRecurseCopy( HEURIST_DIR."documentation", $database_folder."documentation" )){

            folderAddIndexHTML($database_folder."documentation");// index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy documentation folder to $database_folder";
        }
    }
    */

        // Create all the other standard folders required for the database
        // index.html files are added by createFolder to block index browsing
        $warnings[] = folderCreate2($database_folder. '/filethumbs', 'used to store thumbnails for uploaded files', true);
        $warnings[] = folderCreate2($database_folder. '/file_uploads','used to store uploaded files by default');
        $warnings[] = folderCreate2($database_folder. '/scratch', 'used to store temporary files');
        $warnings[] = folderCreate2($database_folder. '/hml-output', 'used to write published records as hml files', true);
        $warnings[] = folderCreate2($database_folder. '/html-output', 'used to write published records as generic html files', true);
        $warnings[] = folderCreate2($database_folder. '/generated-reports', 'used to write generated reports');
        $warnings[] = folderCreate2($database_folder. '/backup', 'used to write files for user data dump');

        //remove empty warns
        $warnings = array_filter($warnings, function($value) { return $value !== '';});
        if(!empty($warnings)){
            array_unshift($warnings, "Unable to create the sub directories within the database root directory,<br>Database name: "
                        . $database_name . ",<br><br>Server url: " . HEURIST_BASE_URL . ",<br>Warnings:\n");
        }

        return $warnings;
    }

    /**
    * Clears data (delete all records) for given table
    *
    * @param mixed $name
    * @param mixed $remark - session message
    * @param mixed $verbose
    */
    private static function _emptyTable($name, $remark, $verbose){

        $mysqli = self::$mysqli;

        if($verbose){ echo "Deleting ".htmlspecialchars($remark)."</br>";}

        self::setSessionVal($remark);

        if(!$mysqli->query("delete from $name where 1")){

            $error_msg = 'Unable to clean '.htmlspecialchars($remark);

            self::$system->addError(HEURIST_ACTION_BLOCKED, $error_msg, $mysqli->error);
            if($verbose) {
                echo "<br><p>Warning: $error_msg - SQL error: {$mysqli->error}</p>";
            }
            return false;
        }else{
            return true;
        }
    }

    /**
     * Clears data from tables in a specified database, retaining table structures and definitions.
     * Empties tables like Records, recDetails, recLinks, etc.
     *
     * @param string $database_name The name of the database to empty.
     * @param bool $verbose Optional. If true, outputs detailed messages. Defaults to true.
     * @return bool True on success, false on failure.
     */
    public static function databaseEmpty($database_name, $verbose=true){

        self::initialize();
        $mysqli = self::$mysqli;
        $system  = self::$system;

        $error_msg = self::databaseValidateName($database_name, 2);//exists
        if ($error_msg!=null) {
            $system->addError(HEURIST_ACTION_BLOCKED, $error_msg);
            return false;
        }

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $res = true;

        if($database_name!=$system->dbname()){ //switch to database
           $connected = (mysql__usedatabase($mysqli, $database_name_full)==true);
        }else{
           $connected = true;
        }
        if(!$connected){
            $msg = 'Failed to connect to database '.htmlspecialchars($database_name);
            $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) {echo '<br><p>'.$msg.'</p>';}
            return false;
        }

        self::setSessionVal('Permission validation');

        $keep_autocommit = mysql__select_value($mysqli, 'SELECT @@autocommit');
        if($keep_autocommit===true) {$mysqli->autocommit(false);}

        if(!$mysqli->query("update recThreadedComments set cmt_ParentCmtID = NULL where cmt_ID>0")){
            //not used
            $system->addError(HEURIST_ACTION_BLOCKED, 'Unable to set parent IDs to null for Comments');
            $res = false;
            if($verbose) {
                echo "<br><p>Warning: Unable to set parent IDs to null for Comments - SQL error: {$mysqli->error}</p>";
            }
        }

        if($res){

            $tables = array(
                "recThreadedComments" => "Comments",
                "recForwarding" => "Forwarding",
                "recLinks" => "Relationships Cache",
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
                if(! self::_emptyTable($name, $remark, $verbose)){
                    $res = false;
                    break;
                }
            }

            if($res){
                $res2 = $mysqli->query("ALTER TABLE Records AUTO_INCREMENT = 0");
                if($res2) {$mysqli->commit();}
            }
        }

        if(!$res){
            $mysqli->rollback();
        }

        if($keep_autocommit===true) {$mysqli->autocommit(true);}
        //$mysqli->close();

        return $res;
    }

    /**
    * Copy all tables (except csv import cache) from one db to another
    * It is assumed that all tables exist and empty in target db
    *
    * source and target database must exist
    *
    * $isCloneTemplate - true for clone curated database
    *
    * @param string $db_source Full name (e.g., hdb_source) for the source database.
    * @param string $db_target Full name (e.g., hdb_target) for the target database.
    *                          The target database must exist (e.g., created by databaseCreate($db_target, 1)).
    * @param bool $verbose If true, outputs detailed messages.
    * @param bool $nodata Optional. If true, only table structures are copied, not data from data_tables. Defaults to false.
    * @param bool $isCloneTemplate Optional. If true, special handling for cloning a template database. Defaults to false.
    * @return bool True on success, false on failure.
    *
    * @todo Make private and rename to _databaseCopyTables (as per original @todo).
    */
    public static function databaseClone($db_source, $db_target, $verbose, $nodata=false, $isCloneTemplate=false){

        self::initialize();

        $res = true;
        $mysqli = self::$mysqli;
        $message = null;

        $db_source = $mysqli->real_escape_string($db_source);
        $db_target = $mysqli->real_escape_string($db_target);

        if( mysql__usedatabase($mysqli, $db_source)!==true ){
            $message = 'Could not open source database '.$db_source;
            $res = false;
            if($verbose) {
                $message = '<br><p>Warning: '.$message.'</p>';
            }
        }else{

            if( mysql__usedatabase($mysqli, $db_target)!==true ){
                $message = 'Could not open target database '.$db_target;
                $res = false;
                if($verbose) {
                    $message = '<br><p>Warning: '.$message.'</p>';
                }
            }
        }

        if($res){

                // Remove initial values from empty target database
                $mysqli->query('delete from sysIdentification where 1');

                if(!$isCloneTemplate){
                    $mysqli->query('delete from sysUsrGrpLinks where 1');
                    $mysqli->query('delete from sysUGrps where ugr_ID>=0');
                }

                //$isCloneTemplate
                $exception_for_clone_template = array('sysugrps','sysusrgrplinks',
                'woot_chunkpermissions','woot_chunks','woot_recpermissions','woots',  //for clone templates
                'usrworkingsubsets', //'usrrecpermissions',
                'usrreminders','usrremindersblocklist','recthreadedcomments','usrreportschedule','usrhyperlinkfilters', 'sysarchive');

                $data_tables = array('records','recdetails','reclinks','recdetailsdateindex',
                'recsimilarbutnotdupes','recthreadedcomments','recuploadedfiles','usrbookmarks','usrrectaglinks',
                'usrrecpermissions','usrworkingsubsets',
                'usrreminders','usrremindersblocklist','sysarchive');


                $tables = $mysqli->query("SHOW TABLES");//get all tables from target db
                if($tables){

                    // SET unique_checks=0; SET foreign_key_checks=0; 
                    mysql__foreign_check( $mysqli, false );
                    $mysqli->query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

                    if($verbose) {
                        echo "<b>Adding records to tables: </b>";
                    }
                    while ($table = $tables->fetch_row()) { //loop for all tables
                        $table = $mysqli->real_escape_string($table[0]);

                        if($nodata && in_array(strtolower($table), $data_tables)){
                            continue;
                        }
                        if($isCloneTemplate &&  in_array(strtolower($table), $exception_for_clone_template)){
                            continue;
                        }
                        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {//invalid tablename
                            continue;
                        }
                        

                        if(strtolower($table)=='usrrecpermissions'){
                            $cnt = mysql__select_value($mysqli,'select count(*) from usrRecPermissions');
                            if(!($cnt>0)) {continue;}
                        }elseif($table=='sysUGrps'){
                            $cnt = mysql__select_value($mysqli, "SELECT count(*) FROM `". $db_source ."`.sysUGrps WHERE ugr_Enabled != 'n' AND ugr_Enabled != 'y'");

                            if(is_numeric($cnt) && $cnt > 0){
                                checkUserStatusColumn(self::$system, $db_target);
                            }
                        }

                        $table = $mysqli->real_escape_string($table);
                        $mysqli->query("ALTER TABLE `".$table."` DISABLE KEYS");
                        $res = $mysqli->query("INSERT INTO `".$table."` SELECT * FROM `".$db_source."`.`".$table."`"  );

                        if($res){
                                if($verbose) {
                                    echo " > " . htmlspecialchars($table) . ": ".intval($mysqli->affected_rows) . "  ";
                                }
                        }else{
                                if($table=='usrReportSchedule'){
                                    if($verbose) {
                                        echo "<br><p class=\"error\">Warning: Unable to add records into ".htmlspecialchars($table)." - SQL error: {$mysqli->error}</p>";
                                    }
                                }else{
                                    $message = "Unable to add records into $table - SQL error: ".$mysqli->error;
                                    if($verbose) {
                                        $message = "<br><p class=\"error\">Error: $message</p>";
                                    }
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

                    mysql__foreign_check( $mysqli, true );//restore/enable foreign indexes verification

                    //cleanup target database to avoid issues with addition of constraints

                    //1. cleanup missed trm_InverseTermID
                    $mysqli->query('update defTerms t1 left join defTerms t2 on t1.trm_InverseTermID=t2.trm_ID
                        set t1.trm_InverseTermID=null
                    where t1.trm_ID>0 and t2.trm_ID is NULL');

                    //3. remove missed rl_SourceID and rl_TargetID
                    $mysqli->query('delete FROM recLinks
                        where rl_SourceID is not null
                    and rl_SourceID not in (select rec_ID from Records)');

                    $mysqli->query('delete FROM recLinks
                        where rl_TargetID is not null
                    and rl_TargetID not in (select rec_ID from Records)');

                    //4. cleanup orphaned details
                    $mysqli->query('delete FROM recDetails
                        where dtl_RecID is not null
                    and dtl_RecID not in (select rec_ID from Records)');

                    //5. cleanup missed references to uploaded files
                    $mysqli->query('delete FROM recDetails
                        where dtl_UploadedFileID is not null
                    and dtl_UploadedFileID not in (select ulf_ID from recUploadedFiles)');

                    //6. cleanup missed rec tags links
                    $mysqli->query('delete FROM usrRecTagLinks where rtl_TagID not in (select tag_ID from usrTags)');
                    $mysqli->query('delete FROM usrRecTagLinks where rtl_RecID not in (select rec_ID from Records)');

                    //7. cleanup orphaned bookmarks
                    $mysqli->query('delete FROM usrBookmarks where bkm_RecID not in (select rec_ID from Records)');

                }else{
                    $res = false;
                    $message = 'Cannot get list of table in database '.$db_target;
                    if($verbose) {
                        echo '<br><p class=\"error\">Error: '.htmlspecialchars($message).'</p>';
                    }
                }

            }

        if(!$res){
            if($verbose) {
                if($message) {echo htmlspecialchars($message);}
            }else{
                self::$system->addError(HEURIST_ERROR, $message);
            }
        }

        return $res;
    }

    /**
     * Clones an entire database, including its file structure and data.
     *
     * @param string|null $db_source Source database name. Defaults to the current database ($system->dbname()) if null.
     * @param string $db_target Target database name.
     * @param bool $nodata Optional. If true, only definitions and structure are cloned (no record data). Defaults to false.
     * @param bool $isCloneTemplate Optional. If true, specific logic for cloning a template database is applied.
     *                              The database owner will be changed to the current user. Defaults to false.
     * @return bool True on success, false on failure.
     */
    public static function databaseCloneFull($db_source, $db_target, $nodata=false, $isCloneTemplate=false)
    {
        global $passwordForServerFunctions;

        self::initialize();

        if($db_source==null){
            $db_source = self::$system->dbname();
        }

        //$system = self::$system;
        $mysqli = self::$mysqli;
        $ugr_ID = self::$system->getUserId();//current user
        $usr_owner = user_getById($mysqli, $ugr_ID);


        list($db_source_full, $db_source ) = mysql__get_names($db_source);
        list($db_target_full, $db_target ) = mysql__get_names($db_target);


        $sErrorMsg = DbUtils::databaseValidateName($db_target, 1);//unique
        if ($sErrorMsg!=null) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
            return false;
        }

        //additional check for self clone/rename
        if($db_source==self::$system->dbname() && !self::$system->isAdmin()){

                self::$system->addError(HEURIST_REQUEST_DENIED,
                            'To perform this action you must be logged in as Administrator of group \'Database Managers\' or as Database Owner');
                return false;
        }

        if(self::setSessionVal(1)) {return false;} //validation

        //create folders
        $upload_root = self::$system->getFileStoreRootFolder();
        $database_folder = $upload_root.$db_target.'/';

        //2. Copy folders
        //copy files and folder
        if($nodata){
            //limited set of folders
            $warnings = self::databaseCreateFolders($db_target);
            if(!isEmptyArray($warnings)){
                folderDelete($database_folder);
                self::$system->addError(HEURIST_ACTION_BLOCKED,
                    'Sorry, we were not able to create all file directories required by the database. '
                                                .implode("<br>",$warnings));
                return false;
            }

            folderRecurseCopy( HEURIST_FILESTORE_ROOT.$db_source."/smarty-templates",
                        HEURIST_FILESTORE_ROOT.$db_target."/smarty-templates" );
            folderRecurseCopy( HEURIST_FILESTORE_ROOT.$db_source."/xsl-templates",
                        HEURIST_FILESTORE_ROOT.$db_target."/xsl-templates" );
            folderRecurseCopy( HEURIST_FILESTORE_ROOT.$db_source."/entity",
                        HEURIST_FILESTORE_ROOT.$db_target."/entity" );

        }elseif(!folderRecurseCopy( HEURIST_FILESTORE_ROOT.$db_source, HEURIST_FILESTORE_ROOT.$db_target )){
                folderDelete($database_folder);
                self::$system->addError(HEURIST_ACTION_BLOCKED,
                    'Sorry, we were not able to copy file directories for cloning  database.');
                return false;
        }
        if(self::setSessionVal(2)) {return false;} //copy folders

        //3. create target database
        $res = DbUtils::databaseCreate($db_target, 1);

        if(!$res){
            folderDelete($database_folder);
            return false;
        }

        if(self::setSessionVal(3)) {return false;} //database creation

        //4. copy tables  - it switches to target db
        $res = DbUtils::databaseClone($db_source_full, $db_target_full, false, $nodata, $isCloneTemplate);

        if(!$res){
            DbUtils::databaseDrop( false, $db_target, false);
            return false;
        }

        if(self::setSessionVal(4)) {return false;} //copy data

        if($isCloneTemplate){
        //5. add current user from current database as owner to target cloned db
            $usr_owner['ugr_ID'] = 2;
            unset($usr_owner['ugr_NavigationTree']);
            $ret = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', $usr_owner);
            if($ret!=2){
                DbUtils::databaseDrop( false, $db_target, false);
                self::$system->addError(HEURIST_ACTION_BLOCKED,
                                'Cannot set owner user. '.$ret);
                return false;
            }
        }

        //6. add constraints
        if(!DbUtils::databaseCreateConstraintsAndTriggers($db_target)){
            DbUtils::databaseDrop( false, $db_target, false);
            return false;
        }

        if(self::setSessionVal(5)) {return false;} //triggers and constraints

        // 7. Update file path in target database  with absolute paths
        self::databaseUpdateFilePaths($db_source, $db_target);

        if(self::setSessionVal(6)) {return false;} //triggers and constraints

        return true;
    }

    /**
    * Update ulf_FilePath in recUploadedFiles for new database
    *
    * @param mixed $db_source
    * @param mixed $db_target
    */
    private static function databaseUpdateFilePaths($db_source, $db_target)
    {
        $mysqli = self::$mysqli;
        $query1 = "update recUploadedFiles set ulf_FilePath='".HEURIST_FILESTORE_ROOT.$db_target.
        "/' where ulf_FilePath='".HEURIST_FILESTORE_ROOT.$db_source."/' and ulf_ID>0";
        $res1 = $mysqli->query($query1);
        if ($mysqli->error)  { //(mysql_num_rows($res1) == 0)
//@todo
//        print "<p><h4>Warning</h4><b>Unable to set database files path to new path</b>".
//        "<br>Query was:".htmlspecialchars($query1).
//        "<br>Please get your system administrator to fix this problem BEFORE editing the database (your edits will affect the original database)</p>";

        }
    }


    /**
     * Resets database registration information and assigns a new origin ID for its definitions.
     * Typically used after cloning a database.
     *
     * @param string $dbname The name of the database to reset registration for.
     * @return void
     */
    public static function databaseResetRegistration($dbname){

        self::initialize();

        mysql__usedatabase(self::$mysqli, $dbname);

        //get current reg id
        $sourceRegID = mysql__select_value(self::$mysqli, 'select sys_dbRegisteredID from sysIdentification where 1');

        //reset reg id and some other values in sysIdentification
        $query1 = "update sysIdentification set sys_dbRegisteredID=0, sys_hmlOutputDirectory=null, "
            ."sys_htmlOutputDirectory=null, sys_SyncDefsWithDB=null, sys_MediaFolders='uploaded_files', "
            ."sys_eMailImapProtocol='', sys_eMailImapUsername='', sys_dbRights='', sys_NewRecOwnerGrpID=0 where 1";

        $res1 = self::$mysqli->query($query1);
        if($sourceRegID>0){
            self::updateOriginatingDB($sourceRegID);
        }
    }

    /**
     * Updates the registration information for a database.
     * This includes setting the OriginatingDBID for definitions and updating sysIdentification.
     *
     * @param string $dbname The name of the database to update.
     * @param array $reg_record An array containing registration details. Expected keys:
     *                          'dbID' (int) - The registration ID of the database.
     *                          'dbTitle' (string) - The title or description of the database.
     * @return void
     */
    public static function databaseUpdateRegistration($dbname, $reg_record){

        self::initialize();
        $res = mysql__usedatabase(self::$mysqli, $dbname);
        if($res===true){

            $dbID = intval(@$reg_record['dbID']);
            $dbDescription = @$reg_record['dbTitle'];

            if($dbID>0){
                //update concept codes
                self::updateOriginatingDB( $dbID );

                //update sysIndentificatons
                $upd_query = 'update sysIdentification set `sys_dbRegisteredID`=?, `sys_dbDescription`=? where 1';
                mysql__exec_param_query(self::$mysqli, $upd_query, array('is', $dbID, $dbDescription));
            }
        }

    }



    /**
     * Renames a database. This is typically achieved by cloning the database to a new name
     * and then dropping the original database (optionally archiving it first).
     *
     * @param string $db_source The current name of the database.
     * @param string $db_target The new name for the database.
     * @param bool $createArchive Optional. If true, the original database is archived before being dropped. Defaults to false.
     * @return bool True on successful rename, false on failure.
     */
    public static function databaseRename($db_source, $db_target, $createArchive=false){

        /*
        [db_source_full, db_source] = mysql__get_names(db_source)
        [db_target_full, db_target] = mysql__get_names(db_target)

        // Copy filestore
        rename(SOURCE_FILESTORE, TARGET_FILESTORE)
        setSessionVal(2)

        // Create new database
        "CREATE DATABASE db_target_full"
        setSessionVal(3)

        // InnoDB lets us just rename the table [MySQL 5.5 +]
        $tables = "SHOW TABLES"
        FOREACH tables as table:
            "RENAME TABLE db_source_full.table TO db_target_full.table"
        END FOR

        // Reset Privileges
        "GRANT ALL PRIVILEGES ON db_target_full.* TO 'user'@'db server'"
        "FLUSH PRIVILEGES"

        // Add SQL triggers and constraints
        databaseCreateConstraintsAndTriggers(db_target_full)
        setSessionVal(5)

        // Update ulf paths to new database
        databaseUpdateFilePaths(db_source, db_target)
        setSessionVal(6)

        // Update registration
        ...

        // Drop original database
        databaseDrop(false, db_source, createArchive)
        */

        //copy all data to new database
        $res = DbUtils::databaseCloneFull($db_source, $db_target, false, false);
        //drop/archive previous database
        if($res){
            $mysqli = self::$mysqli;
            //update registration
            $rec = mysql__select_row_assoc($mysqli, 'select sys_dbRegisteredID, sys_dbDescription from sysIdentification where 1');
            $regID = intval($rec['sys_dbRegisteredID']);
            if($regID>0)
            {
                $dbTitle = $rec['sys_dbDescription'];
                $dbowner = user_getDbOwner($mysqli);
                $serverURL = HEURIST_SERVER_URL . HEURIST_DEF_DIR . "?db=" . $db_target;
                $params = array(
                    'action'=>'update',
                    'dbID'=>$regID,
                    'dbReg'=>$db_target, //new name
                    'dbTitle'=>$dbTitle,
                    'usrPassword'=>$dbowner['ugr_Password'],
                    'usrEmail'=>$dbowner['ugr_eMail'],
                    'serverURL'=>$serverURL //new url
                );
                $res2 = DbRegis::registrationUpdate($params);
                // if not integer - this is error
                if(is_bool($res2) && $res2===false){
                    self::$system->addErrorMsg(
                        'Failed to update reference index for #'.$regID.' for renamed database '.$db_target.'<br>');
                }
            }

            //archive and drop database with old name
            self::$progress_step = 6;
            DbUtils::databaseDrop(false, $db_source, $createArchive);
        }

        return $res;
    }



    /**
     * Assigns a given Originating Database ID (dbID) to record types, detail types, and terms
     * in the current database that do not yet have an OriginatingDBID set (or it's '0').
     *
     * @param int $dbID The database registration ID to set as the OriginatingDBID.
     * @return bool True on success, false if any update query fails.
     */
    public static function updateOriginatingDB($dbID){

        self::initialize();

        $res = true;

        if($dbID>0){
            $dbID = intval($dbID);
            $mysqli = self::$mysqli;
            $result = 0;
            $res2 = $mysqli->query("update defRecTypes set "
                ."rty_OriginatingDBID='$dbID',rty_NameInOriginatingDB=rty_Name,rty_IDInOriginatingDB=rty_ID "
                ."where (rty_OriginatingDBID = '0') OR (rty_OriginatingDBID IS NULL) ");
            if ($res2===false) {$result = 1; }
            // Fields
            $res2 = $mysqli->query("update defDetailTypes set "
                ."dty_OriginatingDBID='$dbID',dty_NameInOriginatingDB=dty_Name,dty_IDInOriginatingDB=dty_ID "
                ."where (dty_OriginatingDBID = '0') OR (dty_OriginatingDBID IS NULL) ");
            if ($res2===false) {$result = 1; }
            // Terms
            $res2 = $mysqli->query("update defTerms set "
                ."trm_OriginatingDBID='$dbID',trm_NameInOriginatingDB=trm_Label, trm_IDInOriginatingDB=trm_ID "
                ."where (trm_OriginatingDBID = '0') OR (trm_OriginatingDBID IS NULL) ");
            if ($res2===false) {$result = 1; }


            if ($result == 1){
                self::$system->addError(HEURIST_DB_ERROR,
                            'Error on update IDs "IDInOriginatingDB" fields for database registration '.$dbID, $mysqli->error);
                $res = false;
            }
        }
        return $res;
    }

    /**
     * Assigns the current database's registration ID (HEURIST_DBID if defined) as the
     * OriginatingDBID for record types, detail types, and terms that have a placeholder
     * OriginatingDBID of '9999'. This is typically used after importing definitions
     * from an unregistered database.
     *
     * @return bool True on success, false if any update query fails or HEURIST_DBID is not defined.
     */
    public static function updateImportedOriginatingDB(){

        self::initialize();

        $res = true;

        $dbID = 0;
        if(defined('HEURIST_DBID')){
            $dbID = HEURIST_DBID;
        }

        $mysqli = self::$mysqli;
        $result = 0;
        $res2 = $mysqli->query("update defRecTypes set "
            ."rty_OriginatingDBID='$dbID',rty_NameInOriginatingDB=rty_Name,rty_IDInOriginatingDB=rty_ID "
            ."where (rty_OriginatingDBID = '9999')");
        if ($res2===false) {$result = 1; }
        // Fields
        $res2 = $mysqli->query("update defDetailTypes set "
            ."dty_OriginatingDBID='$dbID',dty_NameInOriginatingDB=dty_Name,dty_IDInOriginatingDB=dty_ID "
            ."where (dty_OriginatingDBID = '9999')");
        if ($res2===false) {$result = 1; }
        // Terms
        $res2 = $mysqli->query("update defTerms set "
            ."trm_OriginatingDBID='$dbID',trm_NameInOriginatingDB=trm_Label, trm_IDInOriginatingDB=trm_ID "
            ."where (trm_OriginatingDBID = '9999')");
        if ($res2===false) {$result = 1; }


        if ($result == 1){
            self::$system->addError(HEURIST_DB_ERROR,
                        'Error on update IDs "IDInOriginatingDB" fields for unregistered (imported) definitions '.$dbID, $mysqli->error);
            $res = false;
        }

        return $res;
    }

    /**
     * Setup filesotre for connecting a new remote database, by:
     *  0 => Adds the database to the waiting list (_DBS_AWAITING_SYNC.txt) and awaits the completion of the remote filestore download
     *       This assumes that the setup remote filestore shell script is setup and runs every minute (script: setup_remote_db_filestore.sh)
     *  1 => Create default database filestore directory within the remote filestore directory
     *       This assumes the auto rsync shell script is setup and runs ever 5 minutes (script: monitor_and_rsync_db_filestores.sh)
     * The association setup uses mode 1.
     *
     * @param array $request
     * @return bool|array{message: string, status: string|bool}
     */
    public static function connectRemoteDatabase($request){

        global $remoteServers, $defaultRootFileUploadPath;

        if(empty($request['remoteDB']) || empty($request['server'])){
            self::$system->addError(HEURIST_INVALID_REQUEST, 'Missing required parameters for remote database setup');
            return false;
        }

        /*
            Modes: 
            0 - use the waiting list (add to text file, file will be read by bash script, file store directory will be created in the bash script)
            1 - use the sync script (create file store directory, on next sync files will be added)
        */
        $mode = @$_REQUEST['mode'] === 0 ? 0 : 1;

        // Prepare server prefix and database name
        $remoteDatabase = htmlspecialchars($request['remoteDB']);
        $serverPrefix = htmlspecialchars($request['server']);
        if(empty($remoteServers) || empty($defaultRootFileUploadPath) || !array_key_exists($serverPrefix, $remoteServers)){
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Operation not allowed');
            return false;
        }
        $syncDatabase = "{$serverPrefix}-{$remoteDatabase}";

        $filestorePath = str_replace("HEURIST_FILESTORE", "HEURIST_FILESTORE_{$serverPrefix}", $defaultRootFileUploadPath);
        $remoteFilestorePath = "{$filestorePath}{$remoteDatabase}";

        // Verify that: the server has a filestore root on this server and that the database isn't already connected
        if(!file_exists($filestorePath)){
            self::$system->addError(HEURIST_ERROR, 'Unable to determine path to local filestore.');
            return false;
        }elseif(folderExists($remoteFilestorePath, false) === 1){
            self::$system->addError(HEURIST_ACTION_BLOCKED, "This remote database is already connected.");
            return false;
        }

        // ERROR MESSAGES
        $emailSupport = <<<EMAIL
        please contact the <a href="mailto:support@heuristnetwork.org?subject=Attempting to synchronise {$remoteDatabase} to Heurist.eu">Heurist team</a>.
        EMAIL;

        $SYNC_DONE = <<<STR
        Your remote database has been synchronised to this server.<br>
        If your database doesn't appear on the list below, {$emailSupport}
        STR;

        $SYNC_INPROGRESS = <<<STR
        Your remote database is still being synchronised to this server, please refresh the database list page in a few minutes (for larger databases this time can be longer than normal).<br>
        If your database still doesn't appear after 10 minutes, {$emailSupport}
        STR;

        $SYNC_ERROR = <<<STR
        An error has occurred while attempting to synchronise the remote database to this server, this can be due to networking issues between this server and the remote server.<br>
        Please contact the <a href="mailto:support@heuristnetwork.org?subject=Error while synchronising {$remoteDatabase} to Heurist.eu">Heurist team</a>, include which remote server and database you are attempting to set up.
        STR;

        $SYNC_BUSY = <<<STR
        The server is currently experiencing delays in setting up new remote databases, please refresh the database list page after a few minutes.<br>
        If after 10 minutes your database doesn't appear, {$emailSupport}<br><br>
        <em><strong>NOTE: Spamming the remote set up request will not make the process go any faster</strong></em>
        STR;

        $SYNC_PREP_DONE = <<<STR
        Your remote database has been synchronised to this server.<br>
        If your database doesn't appear on the list below, {$emailSupport}
        STR;

        $SYNC_PREP_WAIT = <<<STR
        The local filestore has been prepped for synchronisation.<br>
        The synchronisation script is ran roughly every 5 minutes on this server.<br>
        The download may take longer for databases with many images or very large files.<br><br>
        <strong>Please wait a few minutes before refreshing this page and attempting to access your remote database</strong>.
        STR;

        $SYNC_PREP_FAILED = <<<STR
        Heurist failed to prepare the local filestore for the new remote database.<br>
        Please contact the <a href="mailto:support@heuristnetwork.org?subject=Error while synchronising {$remoteDatabase} to Heurist.eu">Heurist team</a>, include which remote server and database you are attempting to set up.<br><br>
        STR;

        if($mode === 0){ // use the waiting list - requires setup_remote_db_filestore.sh to be ran from a cronjob

            $waitingListFile = HEURIST_FILESTORE_ROOT . '_DBS_AWAITING_SYNC.txt';
            if(!file_exists($waitingListFile) || filesize($waitingListFile) === 0){
                file_put_contents($waitingListFile, "$syncDatabase,");
            }else{
                $waitingList = file_get_contents($waitingListFile) ?: '';
                if(!mb_ereg("{$syncDatabase},", $waitingList)){
                    $waitingList .= "{$syncDatabase},";
                    file_put_contents($waitingListFile, $waitingList);
                }
            }

            $waiting = 0;
            while(true){

                sleep(60);

                if(folderSize($remoteFilestorePath) > 0 || filesize($waitingListFile) === 0 || $waiting === 10){
                    break;
                }

                $waiting ++;
            }

            $isRemoteFilestoreCreated = folderSize($remoteFilestorePath) > 0;
            $stillSyncing = file_exists("/tmp/ssh_setup_fs_{$syncDatabase}");
            $waitingList = file_get_contents($waitingListFile) ?: '';

            $result = $stillSyncing ? ['status' => 'inprogress', 'message' => $SYNC_INPROGRESS] : [];
            $result = $isRemoteFilestoreCreated && !$stillSyncing ? ['status' => 'done', 'message' => $SYNC_DONE] : $result;
            $result = empty($result) && mb_ereg("{$syncDatabase},", $waitingList) ? ['status' => 'busy', 'message' => $SYNC_BUSY] : $result;
            $result = empty($result) ? ['status' => 'error', 'message' => $SYNC_ERROR] : $result;

        }else{ // creates the basic filestore, then waits for the 5 minute auto sync to run 

            $warnings = self::databaseCreateFolders($remoteDatabase, $serverPrefix);

            if(!empty($warnings)){

                $listedErrors = implode(' | ', $warnings);

                self::$system->addError(HEURIST_ERROR, "Failed to setup the filestore for {$syncDatabase}, errors: {$listedErrors}");

                $SYNC_PREP_FAILED .= implode('<br>', $warnings);

                $result = ['status' => 'failed', 'message' => $SYNC_PREP_FAILED];
            }

            $waiting = 0;
            while(true){

                sleep(60);

                if(file_exists("$remoteFilestorePath/userInteraction.log") || $waiting == 10){
                    break;
                }

                $waiting ++;
            }

            $result = file_exists("$remoteFilestorePath/userInteraction.log") ? ['status' => 'done', 'message' => $SYNC_PREP_DONE] : ['status' => 'inprogress', 'message' => $SYNC_PREP_WAIT];
        }

        return $result;
    }

}


?>