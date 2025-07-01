<?php
/**
* DbExportTSV.php - Class DbExportTSV
* 
* Exports entire database to TSV
*
* @project     Heurist academic knowledge management system
* @package Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay   <blmckay13@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
  
namespace hserv\utilities;
use hserv\records\export\RecordsExportCSV;
use hserv\structure\ConceptCode;

/**
* Class DbExportTSV
* 
* Exports entire database to TSV
*/
class DbExportTSV {
    
    private $mysqli = null;
    private $system = null;    
    
    private $backupFolder = null;
    
    private $warnings = [];
    
    private $record_fields;
    
    /**
     * Constructor for DbExportTSV.
     * Initializes the exporter with the given system context.
     *
     * @param mixed $system The system instance/context.
     */
    public function __construct($system) {
        $this->setSession($system);
    }
   
    /**
     * Sets the session system instance, initializes the database connection, and sets up the backup folder.
     *
     * @param mixed $system System instance.
     * @param string|null $folder Optional. The specific folder to use for backup. Defaults to a path derived from system settings.
     */
    public function setSession($system, $folder=null) {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();

        ConceptCode::setSystem($system);
        RecordsExportCSV::setSession($this->system);
        
        $this->setBackupFolder($folder);
    }   
    
    /**
     * Sets the backup folder path for TSV exports and creates it if it doesn't exist.
     *
     * @param string|null $folder Optional. The specific folder to use for backup. 
     *                            If null, a default path is generated based on system settings and database name.
     * @return bool True on success (folder created or exists), false on failure to create.
     */
    public function setBackupFolder($folder=null){
        $this->backupFolder = $folder ?? ($this->system->getSysDir(DIR_BACKUP).$this->system->dbname().'/');
        $this->backupFolder .= 'tsv-output/';
        return folderCreate("{$this->backupFolder}records", true);
    }
    
    
    /**
    * Exporting database tables as TSV
    *     
    */
    private function exportDefinitions(){

        // Export tables
        $skip_tables = [
            'defcalcfunctions', 'reclinks',
            'recsimilarbutnotdupes', //'records', 'recdetails',
            'sysarchive', 'syslocks', 'usrhyperlinkfilters'
        ];// tables to skip - import and index are filtered out below

        $tables = mysql__select_list2($this->mysqli, "SHOW TABLES");

        $field_types = [];
        $record_types = [];

        //echo_flush2("Exporting database tables as TSV<br>");

        foreach ($tables as $table) {
        
            $table_lc = strtolower($table);

            if(strpos($table_lc, 'woot') !== false || strpos($table_lc, 'import') === 0 || 
               strpos($table_lc, 'index') !== false || in_array($table_lc, $skip_tables)){
                continue;
            }

            $query = "SELECT * FROM $table";
            $res = $this->mysqli->query($query);

            $get_headers = true;

            if(!$res || $res->num_rows == 0){
                continue;
            }

            $filename = "{$this->backupFolder}{$table}.tsv";
            $fd = fopen($filename, 'w');
            if(!$fd){
                $msg = error_get_last();
                $msg = !empty($msg) ? print_r($msg, true) : "None provided";
                $this->warnings[] = "<br>Unable to create TSV file for $table values at $filename<br>Error message: $msg<br>";
                return false;
            }

            while($row = $res->fetch_assoc()){

                if($table_lc == 'defdetailtypes'){

                    $field_types[ $row['dty_ID'] ] = [ 'type' => $row['dty_Type'] ];

                }elseif($table_lc == 'defrecstructure'){

                    $rty_ID = $row['rst_RecTypeID'];
                    $dty_ID = $row['rst_DetailTypeID'];

                    if($field_types[$dty_ID]['type'] == 'separator'){
                        continue;
                    }

                    if(!array_key_exists($rty_ID, $this->record_fields)) {
                        $this->record_fields[$rty_ID] = [ 'rec_ID', 'rec_Title' ];// add id + title by default
                    }

                    $this->record_fields[$rty_ID][] = "$dty_ID";
                }

                if($get_headers){ // get table field names

                    $w_res = fputcsv($fd, array_keys($row), "\t");
                    if(!$w_res){

                        $this->warnings[] = "Unable to write table headings to TSV file for $table";

                        fclose($fd);
                        $res->close();
                        continue;
                    }

                    $get_headers = false;
                }

                $w_res = fputcsv($fd, $row, "\t");
                if(!$w_res){

                    $this->warnings[] = "Unable to write table row to TSV file for $table<br>";

                    fclose($fd);
                    $res->close();
                }

            }

            fclose($fd);
            $res->close();

            if(filesize($filename) == 0){ // remove empty files
                fileDelete($filename);
            }
        }//foreach
        
        return true;
    } 
    
    
    /**
    * Export Records, recDetails
    */
    private function exportRecords(){

        // Export records per rectype
        foreach ($this->record_fields as $rty_ID => $field_codes) {

            $rty_CC_ID = ConceptCode::getRecTypeConceptID($rty_ID);
            $rty_CC_ID = preg_replace('/^0000\-/', '0', $rty_CC_ID);
            $rty_Name = mysql__select_value($this->mysqli, "SELECT rty_Name FROM defRecTypes WHERE rty_ID = $rty_ID");

            $request = [
                'detail' => 'ids',
                'q' => "t:{$rty_ID}"
            ];

            $response = recordSearch($this->system, $request);
            if($response['status'] != HEURIST_OK){

                $this->warnings[] = "Unable to retrieve records for record type #$rty_ID";
                continue;
            }elseif($response['data']['reccount'] == 0){
                continue;
            }

            $options = [
                'prefs' => [
                    'main_record_type_ids' => $rty_ID,
                    'term_ids_only' => 1,
                    'include_resource_titles' => 1,
                    'include_temporals' => 1,
                    'fields' => [$rty_ID => $field_codes],
                    'csv_delimiter' => "\t"
                ],
                'save_to_file' => 1,
                'file' => [
                    'directory' => "{$this->backupFolder}records",
                    'filename' => "{$rty_CC_ID}_{$rty_ID}_{$rty_Name}.tsv"
                ]
            ];

            $res = RecordsExportCSV::output($response, $options);
            if($res <= 0){

                $msg = $res == 0 ? "Failed to write to TSV file for record type #$rty_ID" 
                                 : "An error occurred while handling the record type #$rty_ID, error was placed within TSV file";

                $this->warnings[] = "<span style='color: red;margin-left: 5px;'>$msg</span><br>";
            }
        }//for
    }
    

    /**
     * Executes the TSV export process.
     * It first exports definition tables and then record data.
     *
     * @return array An array of warning messages generated during the export process. Empty if no warnings.
     */
    public function output(){
        
        if(!file_exists("{$this->backupFolder}records")){
            return ["Destination folder does not exist {$this->backupFolder}record"];
        }
        
        $this->record_fields = []; //reset
        
        if($this->exportDefinitions()){
            $this->exportRecords();
        }
        
        return $this->warnings;   
    }
    
}
?>
