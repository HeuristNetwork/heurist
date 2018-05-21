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

require_once(dirname(__FILE__).'/../../hserver/utilities/utils_db_load_script.php');

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
    // set Origin ID for rectype, detail and term defintions
    //
    public static function databaseDrop( $verbose=false, $database_name=null ){

        self::initialize();
        
        $mysqli = self::$mysqli;
        $system = self::$system;
        
        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        
        if($database_name!=HEURIST_DBNAME){ //switch to database
           $connected = mysql__usedatabase($mysqli, $database_name_full);
        }else{
           $connected = true;
        }

        if(!$connected){
            $msg = 'Failed to connect to database '.$database_name;
            $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) echo '<br>'.$msg;
        }else
        if(DbUtils::databaseDump( $verbose, $database_name )) {
            // Create DELETED_DATABASES directory if needed
            $folder = HEURIST_FILESTORE_ROOT."DELETED_DATABASES/";
            if(!folderCreate($folder, true)){
                $system->addError(HEURIST_SYSTEM_CONFIG, 'Can not create folder for deleteted databases');                
                return false;
            }

            // Zip $source to $file
            $source = HEURIST_FILESTORE_ROOT.$database_name.'/'; //HEURIST_FILESTORE_DIR;  database upload folder
            $destination = $folder.$database_name."_".time().".zip";
                    
            if(zip($source, null, $destination, $verbose)) {
                // Delete from MySQL database
                
                $res = $mysqli->query('DROP DATABASE '.$database_name_full);
                if (!$res){
                    $msg = 'Error on database drop '.$database_name;
                    self::$system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
                    if($verbose) echo '<br/>'.$msg;
                    return false;
                }
                
                if($verbose) echo "<br/>Database ".$database_name." has been dropped";
                
                // Delete $source folder
                folderDelete($source);
                if($verbose) echo "<br/>Folder ".$source." has been deleted";
                
                // Delete from central index
                $mysqli->query('DELETE FROM `heurist_index`.`sysIdentifications` WHERE sys_Database="'.$database_name_full.'"');
                $mysqli->query('DELETE FROM `heurist_index`.`sysUsers` WHERE sus_Database="'.$database_name_full.'"');
                
                return true;
            }else{
                $msg = "Failed to zip $source to $destination";
                self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);                
                if($verbose) echo '<br/>'.$msg;
            }
        }else{
                $msg = "Failed to dump database $database_name to a .sql file";
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
    
    
}


?>