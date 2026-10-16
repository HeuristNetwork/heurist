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
use hserv\System;

/**
* Class DbExportTSV
*
* Exports entire database to TSV
*/
class DbExportTSV {

    private ?\mysqli $mysqli;
    private ?System $system;

    private ?string $backupFolder;
    private ?string $backupTablesFolder;
    private ?string $backupRecordsFolder;

    private ?string $recordsFilenameTemplate = '{ConceptCode}_{RTYID}_{RTYNAME}';
    private ?array $recordsFilenamePlaceholders = ['{ConceptCode}', '{RTYID}', '{RTYNAME}'];

    private array $warnings = [];
    private array $fileOutput = [];

    private array $recordFields = [];

    /**
     * Constructor for DbExportTSV.
     */
    public function __construct(){}

    /**
     * Sets the session system instance, initializes the database connection, and sets up the backup folder.
     *
     * @param System $system System instance.
     * @param string|null $recordsFilenameTemplate Optional. Template naming scheme for records. Defaults to ConceptCode_RTYID_RTYName.
     */
    public function setSession($system, $recordsFilenameTemplate = null){

        $this->system = $system;
        $this->mysqli = $system->getMysqli();

        ConceptCode::setSystem($system);
        RecordsExportCSV::setSession($system);

        if(!empty($recordsFilenameTemplate) && is_string($recordsFilenameTemplate)){

            $hasPlaceholder = false;
            foreach($this->recordsFilenamePlaceholders as $placeholder){
                if(strpos($recordsFilenameTemplate, $placeholder) !== false){
                    $hasPlaceholder = true;
                    break;
                }
            }

            $this->recordsFilenameTemplate = $hasPlaceholder ? $recordsFilenameTemplate : $this->recordsFilenameTemplate;
        }
    }

    /**
     * Sets the backup folder path for TSV exports and creates it if it doesn't exist.
     *
     * @param string|null $folder Optional. The specific folder to use for backup.
     *                            If null, a default path is generated based on system settings and database name.
     * @param string|null $tableDirectory Optional. The specific sub-directory name where the database tables will be placed.
     *                            If null, the default sub directory is 'tsv-output'.
     * @param string|null $recordDirectory Optional. The specific sub-directory name where the database records will be placed.
     *                            If null, the default sub directory is 'tsv-output/records'.
     * @return bool True on success (folder created or exists), false on failure to create.
     */
    public function setBackupFolder($folder=null, $tableDirectory = 'tsv-output', $recordDirectory = 'tsv-output/records'){

        $tableDirectory = rtrim($tableDirectory, '/\\');
        $recordDirectory = rtrim($recordDirectory, '/\\');

        $this->backupFolder = $folder ?? ($this->system->getSysDir(DIR_BACKUP).$this->system->dbname());
        $this->backupFolder = rtrim($this->backupFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $this->backupTablesFolder = "{$this->backupFolder}{$tableDirectory}" . DIRECTORY_SEPARATOR;
        $this->backupTablesFolder = str_replace(['/','\\'], DIRECTORY_SEPARATOR, $this->backupTablesFolder);

        $this->backupRecordsFolder = "{$this->backupFolder}{$recordDirectory}" . DIRECTORY_SEPARATOR;
        $this->backupRecordsFolder = str_replace(['/','\\'], DIRECTORY_SEPARATOR, $this->backupRecordsFolder);

        return folderCreate($this->backupTablesFolder, true) && folderCreate($this->backupRecordsFolder, true);
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
        $tableDirectory = str_replace($this->backupFolder, DIRECTORY_SEPARATOR, $this->backupTablesFolder);

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

            $filename = "{$this->backupTablesFolder}{$table}.tsv";
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

                    if(!array_key_exists($rty_ID, $this->recordFields)) {
                        $this->recordFields[$rty_ID] = [ 'rec_ID', 'rec_Title' ];// add id + title by default
                    }

                    $this->recordFields[$rty_ID][] = (string)$dty_ID;
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
            }else{
                $this->fileOutput['tables'][] = "{$tableDirectory}{$table}.tsv";
            }
        }//foreach

        return true;
    }


    /**
    * Export Records, recDetails
    */
    private function exportRecords(){

        $recordDirectory = str_replace($this->backupFolder, DIRECTORY_SEPARATOR, $this->backupRecordsFolder);

        // Export records per rectype
        foreach ($this->recordFields as $rty_ID => $field_codes) {

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

            $replacements = [$rty_CC_ID, $rty_ID, $rty_Name];

            $filename = str_replace($this->recordsFilenamePlaceholders, $replacements, $this->recordsFilenameTemplate);

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
                    'directory' => $this->backupRecordsFolder,
                    'filename' => "{$filename}.tsv"
                ]
            ];

            $res = RecordsExportCSV::output($response, $options);
            if($res <= 0){

                $msg = $res == 0 ? "Failed to write to TSV file for record type #$rty_ID"
                                 : "An error occurred while handling the record type #$rty_ID, error was placed within TSV file";

                $this->warnings[] = "<span style='color: red;margin-left: 5px;'>$msg</span><br>";
            }else{
                $this->fileOutput['records'][] = "{$recordDirectory}{$filename}.tsv";
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

        if(!file_exists($this->backupTablesFolder) || !file_exists($this->backupRecordsFolder)){
            return ["Destination folder for records does not exist."];
        }

        // Reset
        $this->recordFields = [];
        $this->warnings = [];
        $this->fileOutput = [
            'tables' => [],
            'records' => []
        ];

        if($this->exportDefinitions()){
            $this->exportRecords();
        }

        return [$this->warnings, $this->fileOutput];
    }

}
?>
