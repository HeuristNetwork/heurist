<?php
/**
* ExportRecords.php - abstract class ExportRecords to export records
*
* Controller is records_output
*
* @project     Heurist academic knowledge management system
* @package Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/
namespace hserv\records\export;

use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../../vendor/autoload.php';//for geoPHP and EasyRdf
require_once dirname(__FILE__).'/../../utilities/geo/mapSimplify.php';
require_once dirname(__FILE__).'/../../utilities/geo/mapCoordConverter.php';
require_once dirname(__FILE__).'/../../structure/dbsTerms.php';

/**
* Class ExportRecords
* 
* Abstract base class for exporting Heurist records in various formats.
*
* This class provides common functionalities for record export, such as
* initializing system and database connections, preparing data (including fetching
* related records based on depth parameters), managing temporary files for output,
* and handling common export parameters. Concrete subclasses must implement
* `_outputHeader()`, `_outputRecord()`, and `_outputFooter()` to define the
* specifics of a particular export format.
*/
abstract class ExportRecords {

    /** @var bool Flag indicating if the class has been initialized. */
    private $initialized = false;

    /** @var \hserv\System|null The Heurist system object. */
    protected $system = null;

    /** @var \mysqli|null The mysqli database connection object. */
    protected $mysqli = null;

    /** @var array Stores the records (typically IDs or basic data) to be exported. Populated by `_outputPrepare`. */
    protected $records;

    /** @var array Associative array storing counts of records per record type ID (`rty_ID => count`). */
    protected $rt_counts;

    /** @var string|false Path to the temporary file used for building the export output. */
    private $tmp_destination;

    /** @var resource|false File descriptor for the temporary export file. */
    protected $fd;

    /** @var string Separator string, often used for JSON/CSV formatting (e.g., a comma). Initialized to empty. */
    protected $comma = '';

    /** @var array|string|null A comma-separated string of record header fields to retrieve (e.g., 'rec_ID,rec_Title'). Null means all. */
    protected $retrieve_header_fields = null;

    /** @var array|bool An array of detail type IDs to retrieve. True means all details, false means no details. */
    protected $retrieve_detail_fields = false;

    /**
     * @var int Mode for exporting extended data in JSON/GeoJSON formats:
     *          0: Heurist internal format (default).
     *          1: Interpretable format.
     *          2: Include concept codes and labels for terms.
     *          3: Simplified format suitable for media viewers.
     */
    protected $extended_mode = 0;

    /** @var array|null Static cache for record type definitions. */
    protected static $defRecTypes = null;

    /** @var array|null Static cache for detail type definitions. */
    protected static $defDetailtypes = null;

    /** @var array|null Static cache for term definitions. */
    protected static $defTerms = null;

    /**
     * Constructor for ExportRecords.
     *
     * @param \hserv\System $system The Heurist system object.
     */
    public function __construct($system) {
        $this->setSession($system);
    }

    /**
     * Initializes the class with the Heurist system object and database connection.
     *
     * This method is called by `_outputPrepare` or `setSession` and ensures that
     * initialization (setting up `$this->system` and `$this->mysqli`) occurs only once.
     *
     * @return void
     */
    private function initialize() {
        if ($this->initialized) { return; }

        global $system;
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
        $this->initialized = true;
    }

    /**
     * Sets the Heurist system instance and initializes the database connection.
     *
     * Also sets the `$initialized` flag to true.
     *
     * @param \hserv\System $system The Heurist system object.
     * @return void
     */
    public function setSession($system) {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
        $this->initialized = true;
    }

    /**
     * Prepares for the export process.
     *
     * Initializes the class, checks input data validity, sets up a temporary file for output,
     * and fetches related records based on depth and link mode parameters.
     * Populates `$this->records` and `$this->tmp_destination`, `$this->fd`.
     *
     * @param array $data The raw data array from a record search (expected to have 'status' and 'data' keys).
     * @param array $params An associative array of export parameters, including:
     *                      - 'depth': Depth for fetching linked records ('all' or numeric).
     *                      - 'linkmode': 'none', 'direct', 'direct_links', or 'all' (default).
     *                      - 'format': Export format (e.g., 'gephi', 'iiif').
     *                      - 'limit': (For 'gephi') Limit for related records.
     *                      - 'extended': (For JSON/GeoJSON) Extended data mode.
     * @return bool True if preparation is successful, false otherwise (errors are added to the system object).
     */
    protected function _outputPrepare($data, $params) {
        $this->initialize();

        if (!($data && @$data['status'] == HEURIST_OK)) {
            return false;
        }

        $data = $data['data'];

        if (@$data['memory_warning']) {//memory overflow in recordSearch
            $this->records = array();
        } elseif (!(@$data['reccount'] > 0)) {
            $this->records = array();
        } else {
            $this->records = $data['records'];
        }

        $this->rt_counts = array();
        $this->tmp_destination = tempnam($this->system->getSysDir(DIR_SCRATCH), "exp");
        $this->fd = fopen($this->tmp_destination, 'w');

        if ($this->fd === false) {
            $this->system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
            return false;
        }

        $this->extended_mode = intval(@$params['extended']) > 0 ? intval($params['extended']) : 0;

        // Handling depth and linked records
        $max_depth = 0;
        if (@$params['depth']=='all'){
            $max_depth = 9999;
        }elseif(isPositiveInt(@$params['depth'])) {
            $max_depth = intval($params['depth']);
        }

        $direction = 0;
        $no_relationships = false;

        if (@$params['linkmode']) {
            switch ($params['linkmode']) {
                case 'none':
                    $max_depth = 0;
                    break;
                case 'direct':
                    $direction = 1;
                    break;
                case 'direct_links':
                    $direction = 1;
                    $no_relationships = true;
                    break;
                default:
                    $direction = 0;
                    break;
            }
        }

        if ($max_depth > 0 && $params['format']!='iiif') {
            $limit = ($params['format'] == 'gephi' && @$params['limit'] > 0) ? $params['limit'] : 0;
            recordSearchRelatedIds($this->system, $this->records, $direction, $no_relationships, 0, $max_depth, $limit);
        }

        return true;
    }

    /**
     * Determines and prepares the list of header and detail fields to be included in the export.
     *
     * Based on `$params['detail']` or `$params['columns']`.
     * Populates `$this->retrieve_header_fields` (string or null) and `$this->retrieve_detail_fields` (array or bool).
     * Ensures 'rec_ID' and 'rec_RecTypeID' are always included if specific header fields are requested.
     *
     * @param array $params Parameters that may contain 'detail' or 'columns' to specify fields.
     * @return void
     */
    protected function _outputPrepareFields($params) {
        $default_all_fields = true;
        $this->retrieve_header_fields = array();
        $this->retrieve_detail_fields = array();

        if (@$params['detail']) {
            $params['columns'] = is_array($params['detail']) ? $params['detail'] : explode(',', $params['detail']);
            $default_all_fields = false;
        }

        if (@$params['columns'] && is_array($params['columns'])) {
            foreach ($params['columns'] as $col_name) {
                if (is_array($col_name)) {
                    $col_name = $col_name['data'];
                }

                if (is_numeric($col_name) && $col_name > 0) {
                    array_push($this->retrieve_detail_fields, $col_name);
                } elseif (strpos($col_name, 'rec_') === 0) {
                    array_push($this->retrieve_header_fields, $col_name);
                }
            }
        }

        if (empty($this->retrieve_header_fields)) {
            $this->retrieve_header_fields = null; // Retrieve all header fields
        } else {
            if (!in_array('rec_RecTypeID', $this->retrieve_header_fields)) {
                array_unshift($this->retrieve_header_fields, 'rec_RecTypeID');
            }
            if (!in_array('rec_ID', $this->retrieve_header_fields)) {
                array_unshift($this->retrieve_header_fields, 'rec_ID');
            }
            $this->retrieve_header_fields = implode(',', $this->retrieve_header_fields);
        }

        $this->retrieve_detail_fields = !empty($this->retrieve_detail_fields) ? $this->retrieve_detail_fields : $default_all_fields;
    }

    /**
     * Abstract method for outputting the header of the export format.
     *
     * Must be implemented by concrete subclasses.
     *
     * @return void
     */
    abstract protected function _outputHeader();

    /**
     * Abstract method for outputting a single record in the export format.
     *
     * Must be implemented by concrete subclasses.
     *
     * @param array $record The record data (header and details) to be output.
     * @return bool Should return true on success, false on failure to stop processing.
     */
    abstract protected function _outputRecord($record);

    /**
     * Abstract method for outputting the footer of the export format.
     *
     * Must be implemented by concrete subclasses.
     *
     * @return void
     */
    abstract protected function _outputFooter();

    /**
     * Main method to orchestrate the record export process.
     *
     * It calls `_outputPrepare` to initialize and fetch data, then `_outputPrepareFields`
     * to determine which fields to include. It then iterates through records, calling
     * the abstract `_outputRecord` for each, and finally calls `_outputHeader` and `_outputFooter`
     * (typo, should be `_outputFooter` after records).
     * After generating the content in a temporary file, it calls `_outputResult` to handle
     * sending the file to the client (e.g., download, inline, zipped).
     *
     * @param array $data The raw data array from a record search.
     * @param array $params An associative array of export parameters. Key parameters include:
     *   - `format`: (string) The desired output format (e.g., 'json', 'xml', 'csv').
     *   - `linkmode`: (string) How to handle linked records ('none', 'direct', 'direct_links', 'all').
     *   - `depth`: (int|string) Depth for fetching related records ('all' or a number).
     *   - `file`: (bool) If true, prompt for download with a filename.
     *   - `filename`: (string) Suggested filename for download.
     *   - `zip`: (bool) If true, compress the output as a ZIP file.
     *   - `extended`: (int) Mode for extended JSON/GeoJSON output.
     *   - `columns` or `detail`: (array|string) Specific fields to include.
     *   Other format-specific parameters may also be present.
     * @return bool True if the export process completes successfully, false otherwise.
     */
    public function output($data, $params) {
        if (!$this->_outputPrepare($data, $params)) {
            return false;
        }

        $this->_outputPrepareFields($params);
        $this->_outputHeader();

        //MAIN LOOP  ----------------------------------------
        foreach ($this->records as $record) {
            $recID = is_array($record) ? $record['rec_ID'] : $record;
            if (!is_array($record)) {
                $record = recordSearchByID($this->system, $recID, $this->retrieve_detail_fields, $this->retrieve_header_fields);
            }

            $rty_ID = @$record['rec_RecTypeID'];
            if ($rty_ID > 0) {
                $this->rt_counts[$rty_ID] = isset($this->rt_counts[$rty_ID]) ? $this->rt_counts[$rty_ID] + 1 : 1;
            }

            if (!$this->_outputRecord($record)) {
                break;
            }
        }

        //CLOSE brackets ----------------------------------------
        $this->_outputFooter();

        $this->_outputResult($params);

        return true;
    }

    /**
     * Handles the final output of the generated export file.
     *
     * Sends appropriate HTTP headers (MIME type, Content-Disposition for download,
     * Content-Encoding for GZIP) and streams the content of the temporary export file
     * to the client. Deletes the temporary file afterwards.
     *
     * @param array $params Parameters controlling the output, including:
     *                      - `format`: The export format (used to determine MIME type and filename extension).
     *                      - `serial_format`: (For RDF) Specific RDF serialization (e.g., 'ntriples', 'turtle').
     *                      - `zip`: If true, GZIPs the content.
     *                      - `filename`: Suggested base name for downloaded file.
     *                      - `metadata`: If present, indicates a metadata- kèm theo export, affecting filename.
     *                      - `file`: If true, forces download.
     *                      - `restapi`: If true, sets CORS headers and HTTP status codes.
     *                      - `db`: Database name, used in default filenames.
     * @return bool|void
     */
    private function _outputResult($params){

        $format = @$params['format'];
        if($format==null) {$format = 'xml';}
        if($format=='json' || $format=='geojson' || $format=='iiif' || @$params['serial_format']=='json'){
            $mimeType = CTYPE_JSON;
        }elseif(@$params['serial_format']=='ntriples' || @$params['serial_format']=='turtle'){ //$format=='rdf'
            $mimeType = CTYPE_HTML;
        }else {
            $mimeType = 'Content-Type: text/xml';
        }

        //
        // OUTPUT
        //
        if(@$params['zip']==1 || @$params['zip']===true){
            // in case ERR_CONTENT_DECODING_FAILED need to check unwanted outputs
            // such as spaces after close php brackets 

            $output = gzencode(file_get_contents($this->tmp_destination), 6);
            
            fclose($this->fd);

            header('Content-Encoding: gzip');
            header($mimeType);

            fileDelete($this->tmp_destination);
            echo $output;
            unset($output);

            return;
        }

        //$content = stream_get_contents($this->fd);
        fclose($this->fd);

        //
        // download output as a file
        //
        if(@$params['filename'] || @$params['metadata']){

            $record = null;
            $originalFileName = null;
            if(@$params['metadata']){
                list($db_meta,$rec_ID) = explode('-',$params['metadata']);
                if(!$db_meta && $rec_ID) {$db_meta = $this->system->dbname();}

                $record = array("rec_ID"=>$rec_ID);
                if($db_meta!=$this->system->dbname()){
                    $this->system->init($db_meta, true, false);
                    //mysql__usedatabase($this->mysqli, $db_meta);
                }

                if($this->system->defineConstant('DT_NAME', true)){

                    recordSearchDetails($this->system, $record, array(DT_NAME));
                    if(is_array($record['details'][DT_NAME])){
                        $originalFileName = USanitize::sanitizeFileName(array_values($record['details'][DT_NAME])[0]);
                    }
                }
                if(!$originalFileName) {$originalFileName = 'Dataset_'.$record['rec_ID'];}

            }else{
                $originalFileName = $params['filename'];
            }


            //save into specified file in scratch folder
            $file_records  = $originalFileName.'.'.($format=='gephi'?'gexf':$format);

            //archive into zip
            $file_zip = $originalFileName.'.zip';
            $file_zip_full = tempnam($this->system->getSysDir(DIR_SCRATCH), "arc");
            $zip = new \ZipArchive();
            if (!$zip->open($file_zip_full, \ZIPARCHIVE::CREATE)) {
                $this->system->addError(HEURIST_SYSTEM_CONFIG, "Cannot create zip $file_zip_full");
                return false;
            }else{
                $zip->addFile($this->tmp_destination, $file_records);
            }

            // SAVE hml into file DOES NOT WORK - need to rewrite flathml
            if(@$params['metadata']){//save hml into scratch folder
                $zip->addFromString($originalFileName.'.txt',
                    recordLinksFileContent($this->system, $record));

            }
            $zip->close();
            //donwload
            $contentDispositionField = 'Content-Disposition: attachment; '
            . sprintf('filename="%s";', rawurlencode($file_zip))
            . sprintf("filename*=utf-8''%s", rawurlencode($file_zip));

            header('Content-Type: application/zip');
            header($contentDispositionField);
            header(CONTENT_LENGTH . getFileSize($file_zip_full));
            fileReadByChunks($file_zip_full);

            // remove the zip archive and temp files
            //unlink($file_zip_full);
            //unlink($file_metadata_full);
            fileDelete($this->tmp_destination);
            return;
        }

        if(@$params['restapi']){

            // Allow from any origin
            if (isset($_SERVER['HTTP_ORIGIN'])) {
                // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
                // you want to allow, and if so:
                header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 5');// default value 5 sec
            }
            //2024-02-23 else header(HEADER_CORS_POLICY);
        }elseif($format=='iiif'){
            header(HEADER_CORS_POLICY);            
        }

        header($mimeType);

        if(@$params['file']==1 || @$params['file']===true){

            if($format=='iiif'){
                $filename = 'manifest_'.$params['db'].'_'.date("YmdHis").'.json';
            }else{
                $filename = 'Export_'.$params['db'].'_'.date("YmdHis").'.'.($format=='gephi'?'gexf':$format);
            }

            header('Content-Disposition: attachment; filename='.$filename);
            header(CONTENT_LENGTH . getFileSize($this->tmp_destination));
        }

        if(@$params['restapi']){

            if(empty($this->rt_counts)){
                http_response_code(404);
            }else{
                http_response_code(200);
            }
        }
        //read and download file
        fileReadByChunks($this->tmp_destination);
        fileDelete($this->tmp_destination);

    }

    //
    //
    //
    /**
     * Gathers basic information about the current database and the record types being exported.
     *
     * Used to include metadata in some export formats (e.g., XML, JSON).
     *
     * @return array An associative array containing:
     *               - 'id': Registered ID of the database.
     *               - 'url': Base URL of the Heurist instance.
     *               - 'db': Name of the current database.
     *               - 'rectypes': An array where each key is a `rty_ID` present in the export,
     *                             and the value is an array `['name'=>..., 'code'=>..., 'count'=>...]`.
     */
    protected function _getDatabaseInfo(){

        //add database information to be able to load definitions later
        $dbID = $this->system->settings->get('sys_dbRegisteredID');
        $database_info = array('id'=>$dbID,
                                'url'=>HEURIST_BASE_URL,
                                'db'=>$this->system->dbname());

        $query = 'select rty_ID,rty_Name,'
        ."if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(rty_ID as char(5)))) as rty_ConceptID"
        .' from defRecTypes where rty_ID in ('.implode(',',array_keys($this->rt_counts)).')';
        $rectypes = mysql__select_all($this->system->getMysqli(),$query,1);

        foreach($this->rt_counts as $rtid => $cnt){
            //include record types that are in output - name, ccode and count
            $this->rt_counts[$rtid] = array('name'=>$rectypes[$rtid][0],'code'=>$rectypes[$rtid][1],'count'=>$cnt);
        }
        $database_info['rectypes'] = $this->rt_counts;

        return $database_info;
    }
} //end class
?>