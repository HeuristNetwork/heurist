<?php
/**
* RecordsExportCSV.php - produces output to CSV format
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

use hserv\utilities\USystem;
use hserv\utilities\USanitize;
use hserv\utilities\Temporal;
use hserv\entity\DbDefRecStructure;

require_once dirname(__FILE__).'/../../../vendor/autoload.php';//for geoPHP
require_once dirname(__FILE__).'/../../utilities/geo/mapSimplify.php';
require_once dirname(__FILE__).'/../../utilities/geo/mapCoordConverter.php';
require_once dirname(__FILE__).'/../../structure/dbsTerms.php';
require_once dirname(__FILE__).'/../../../admin/verification/verifyValue.php';

if(!defined('H_FLDS')){
    define('H_FLDS',[
        'rec_ID' => 'H-ID',
        'rec_Title' => 'Record Title'
    ]);
}

/**
 * Class RecordsExportCSV
 *
 * Provides functionality to export Heurist records to CSV format.
 * This class is designed to be called statically and is typically used by the 'records_output' controller.
 * It offers a wide range of options for customizing the CSV output, including:
 * - Selection of specific fields for different record types.
 * - Handling of multi-value fields, terms (with options for ID, code, label, hierarchy),
 *   resource links (IDs and titles), and file details (ID, name, path, URL).
 * - Inclusion of record URLs (HTML, XML).
 * - Outputting data into separate files per record type or a single joined table.
 * - Advanced data processing options like grouping, summing, counting, sorting, and percentages.
 * - Generation of CSV templates with field details and term pickup lists.
 *
 * Due to its extensive feature set, this class is considerably complex.
 *
 * @package Export
 */
class RecordsExportCSV {

    /**
     * @var \hserv\System|null The Heurist system object.
     */
    private static $system = null;
    /**
     * @var \mysqli|null The mysqli database connection object.
     */
    private static $mysqli = null;
    /**
     * @var bool Flag indicating if the class has been initialized.
     */
    private static $initialized = false;
    /**
     * @var int Version number, currently hardcoded to 3.
     */
    private static $version = 3; // TODO: Purpose of this version unclear from context.

    /**
     * @var array|null Cached record type definitions from dbs_GetRectypeStructures.
     */
    private static $defRecTypes = null;
    /**
     * @var array|null Cached detail type definitions from dbs_GetDetailTypes.
     */
    private static $defDetailtypes = null;
    /**
     * @var \DbsTerms|null Cached terms definitions, wrapped in a DbsTerms object.
     */
    private static $defTerms = null;

    /**
     * Initializes the class with the global Heurist system object.
     *
     * Sets static properties for the system object, mysqli connection,
     * and marks the class as initialized. This method is called internally
     * if the class hasn't been initialized yet.
     */
private static function initialize()
{
    if (self::$initialized) {return;}

    global $system;
    self::$system  = $system;
    self::$mysqli = $system->getMysqli();
    self::$initialized = true;
    self::$version = 3;
}

    /**
     * Allows setting a specific Heurist system session for the class to operate on.
     *
     * This is useful if CSV export needs to be performed on a database context
     * different from the current global one.
     *
     * @param \hserv\System $system The Heurist system object to use.
     */
public static function setSession($system){
    self::$system  = $system;
    self::$mysqli = $system->getMysqli();
    self::$initialized = true;
}

/*

output records as csv

$data  - recordset array('status'=>HEURIST_OK,
                                'data'=> array(
                                'queryid'=>@$params['id'],  //query unqiue id
                                'entityName'=>'Records',
                                'count'=>$total_count_rows,
                                'offset'=>get_offset($params),
                                'reccount'=>count($records),
                                'records'=>$records));

if parameter prefs.fields is defined it creates separate file for every record type

fields {rtid:{id, url, title, dt1, dt2, ....  dt4:resource_rt1, dt4:resource_rt2  } }

for constrained resource (record pointer) fields we use "dt#:rt#"
@todo for enum fields use dt#:code,dt#:id,dt#:label

NOTE: fastest way it simple concatenation in comparison to fputcsv and implode. We use fputcsv
*/
    /**
     * Outputs Heurist records in CSV format.
     *
     * This is the primary method for generating CSV exports. It takes a dataset of records
     * and a set of parameters to customize the output.
     *
     * Key functionalities:
     * - Handles various export preferences specified in `$params['prefs']`, such as:
     *   - `fields`: An array defining which fields to export for each record type.
     *     Format: `[rtID => [field_id_1, field_id_2, 'dt_id:constr_rt_id', ...]]`
     *   - `join_record_types`: Boolean, if true, attempts to join data from different
     *     record types into a single CSV table.
     *   - `main_record_type_ids`: Array of primary record type IDs for the export,
     *     especially important when `join_record_types` is true.
     *   - `term_ids_only`, `include_term_ids`, `include_term_codes`, `include_term_hierarchy`:
     *     Options for how term (enum) fields are represented.
     *   - `include_resource_titles`: Option to include titles of linked records.
     *   - `include_file_url`: Option to include direct URLs for files.
     *   - `include_record_url_html`, `include_record_url_xml`: Options to include Heurist URLs for records.
     *   - `include_temporals`: Option to include raw temporal data for date fields.
     *   - `csv_delimiter`, `csv_enclosure`, `csv_mvsep` (multi-value separator), `csv_header`:
     *     CSV formatting options.
     *   - `advanced_options`: An array for specifying grouping, sorting, summing, counting,
     *     and percentage calculations on specific fields.
     *     Format: `[fieldCode => ['total' => 'group'|'sum'|'count', 'sort' => 'asc'|'desc', 'use_percentage' => bool]]`
     *     (fieldCode is typically 'rtID:dt_id')
     * - Determines output filename and directory.
     * - Prepares CSV headers based on selected fields and options.
     * - Iterates through records, fetching necessary details and related records.
     * - Formats each field value according to its type and selected options (e.g., terms,
     *   resources, files, dates). Multi-value fields are joined by `csv_mvsep`.
     * - Handles linked records: if a linked record's type is also selected for export,
     *   its ID is added to the list of records to process, enabling chained exports.
     * - If `has_advanced` options (like grouping or joining) are enabled, data is typically
     *   accumulated in memory (`$csvData`) before final processing. Otherwise, it's written
     *   to temporary streams.
     * - Performs joining, grouping, summing, counting, percentage calculation, and sorting if specified.
     * - Finally, calls `writeResults` to output the CSV data to browser or file.
     *
     * @param array $data The input data structure, typically from a record search operation.
     *                    Expected to have a 'data' key containing 'records' (an array of record IDs)
     *                    and 'reccount'.
     * @param array $params An array of parameters controlling the export. Key settings are under
     *                      `$params['prefs']` and `$params['file']`.
     * @return string|void If `$params['save_to_file']` is true, may return a status message.
     *                     Otherwise, outputs directly to the browser and exits.
     */
public static function output($data, $params){

    if (!($data && @$data['status']==HEURIST_OK)){
        print print_r($data, true);//print out error array
        return;
    }

    $data = $data['data'];

    if(!(@$data['reccount']>0)){
        print 'EMPTY RESULT SET';
        return;
    }

    self::initialize();

    $isJoinTable = (isset($params['prefs']['join_record_types']) && $params['prefs']['join_record_types']) ? true : false;

    // Get the main record type IDs.
    $mainRecordTypeIDs = [];
    if (isset($params['prefs']['main_record_type_ids'])) {
        $mainRecordTypeIDs = $params['prefs']['main_record_type_ids'];
    } else {
        print 'No field selected from the main record type';
        return;
    }

    $save_to_file = @$params['save_to_file'] == 1;

    $term_ids_only = (@$params['prefs']['term_ids_only']==1);
    $include_term_ids = (@$params['prefs']['include_term_ids']==1) || $term_ids_only;
    $include_term_codes = (@$params['prefs']['include_term_codes']==1) && !$term_ids_only;
    $include_resource_titles =  (@$params['prefs']['include_resource_titles']==1);
    $include_term_hierarchy = (@$params['prefs']['include_term_hierarchy']==1);
    $include_file_url = (@$params['prefs']['include_file_url']==1);
    $include_record_url_html = (@$params['prefs']['include_record_url_html']==1);
    $include_record_url_xml = (@$params['prefs']['include_record_url_xml']==1);
    $include_temporals = (@$params['prefs']['include_temporals']==1);

    $fields = @$params['prefs']['fields'];
    $details = array();//array of detail fields included into output
    $relmarker_details = array();//relmarker fields included into output

    // Handle final filename + directory
    $filename = basename('Export_'.self::$system->dbname());
    if(!empty(@$params['file']['filename'])){

        $filename = basename($params['file']['filename']);
        $filename = USanitize::sanitizeFileName($filename, false);
        $directory = @$params['file']['directory'];

        if(!empty($directory)){

            if(!folderExists($directory, true)){

                if(!$save_to_file) {
                    print "Unable to write to requested directory";
                    return;
                }
                return "Unable to write to requested directory";
            }

            $filename = rtrim($directory, '/') . "/$filename";
        }
    }

    if(self::$defRecTypes==null) {
        self::$defRecTypes = dbs_GetRectypeStructures(self::$system, null, 2);
    }
    $idx_name = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dtype = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
    self::$system->defineConstant('RT_RELATION');

    $defTerms = null;
    if(!$term_ids_only){
        $defTerms = dbs_GetTerms(self::$system);
        $defTerms = new \DbsTerms(self::$system, $defTerms);
    }

    // Track column indices for advanced option fields.
    $groupFields = [];
    $sortFields = [];
    $countFields = [];
    $sumFields = [];
    $percentageFields = [];
    $groupColIndices = [];
    $sortColIndices = [];
    $sortOrders = [];
    $countColIndices = [];
    $sumColIndices = [];
    $percentageColIndices = [];

    $has_advanced = $isJoinTable;
    $need_check_memory = true;

    if (isset($params['prefs']['advanced_options']) || is_array($params['prefs']['advanced_options'])) {
        foreach ($params['prefs']['advanced_options'] as $fieldCode => $option) {
            $codeParts = explode(':', $fieldCode);
            if ($codeParts > 1) {
                $recordTypeID = $codeParts[count($codeParts) - 2];
                $fieldID = $codeParts[count($codeParts) - 1];
                $fieldKey = $recordTypeID . ':' . $fieldID;
                if (isset($option['total'])) {
                    if ($option['total'] === 'group') {
                        $groupFields[] = $fieldKey;
                        $has_advanced = true;
                    } elseif ($option['total'] === 'sum') {
                        $sumFields[]  = $fieldKey;
                        $has_advanced = true;
                    } elseif ($option['total'] === 'count') {
                        $countFields[]  = $fieldKey;
                        $has_advanced = true;
                    }
                }
                if (!empty($option['sort'])) {
                    $sortFields[$fieldKey] = $option['sort'];
                        $has_advanced = true;
                }

                if (isset($option['use_percentage']) && $option['use_percentage']) {
                    $percentageFields[] = $fieldKey;
                        $has_advanced = true;
                }
            }
        }


    }

    $memory_limit = USystem::getConfigBytes('memory_limit');

    //create header
    $any_rectype = null;
    $headers = [];
    $columnInfo = [];
    if($fields){
        foreach($fields as $rt=>$flds){

            if($flds[1] == 'rec_ID'){ // flip, so rec id is first
                list($flds[0], $flds[1]) = [$flds[1], $flds[0]];
            }

            //always include ID field into output
            if(array_search('rec_ID', $flds) === false) {array_unshift($flds, 'rec_ID');}
            $fields[$rt] = $flds;

            $details[$rt] = [];
            $headers[$rt] = [];
            $relmarker_details[$rt] = [];
            $columnInfo[$rt] = [];

            foreach($flds as $dt_id){

                $csvColIndex = null;
                $fieldFullID = $dt_id;

                $constr_rt_id = 0;
                if(strpos($dt_id,':')>0){ //for constrained resource (record pointer) fields
                    //example author:person or organization
                    list($dt_id, $constr_rt_id) = explode(':',$dt_id);
                }

                $field_name_title = '';

                if(is_numeric($dt_id) && $dt_id>0){

                    if($dt_id==DT_PARENT_ENTITY){
                        $field_name = 'Parent entity';
                        $field_type = 'resource';
                    }else{
                        //get field name from structure
                        $field_name = self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_name];
                        $field_type = self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_dtype];
                    }
                    if($constr_rt_id>0){
                        $rectypename_is_in_fieldname = (strpos(strtolower($field_name),
                            strtolower(self::$defRecTypes['names'][$constr_rt_id]))!==false);
                        $field_name_title = $field_name.($rectypename_is_in_fieldname
                            ?'':' ('.self::$defRecTypes['names'][$constr_rt_id].')').' '.H_FLDS['rec_Title'];

                        $field_name = $field_name.($rectypename_is_in_fieldname
                            ?'':' ('.self::$defRecTypes['names'][$constr_rt_id].')').' '.H_FLDS['rec_ID'];
                    }else{
                        $field_name_title = $field_name.' '.H_FLDS['rec_Title'];
                    }
                    if($field_type=='relmarker'){
                        $relmarker_details[$rt][$dt_id] = $constr_rt_id;
                    }else{
                        array_push($details[$rt], $dt_id);
                    }

                }else{
                    // record header field
                    $field_type = null;

                    $field_name = array_key_exists($dt_id, H_FLDS) ? H_FLDS[$dt_id] : $dt_id;

                    if($rt>0){
                        $field_name = self::$defRecTypes['names'][$rt].' '.$field_name;
                    }else{
                        $any_rectype = $rt;
                    }
                }

                if($field_type=='enum' || $field_type=='relationtype'){

                    if(!$term_ids_only){
                        array_push($headers[$rt], $field_name);//labels are always included by default
                        $csvColIndex = count($headers[$rt]) - 1;
                        $columnInfo[$rt][] = [
                            'index' => $csvColIndex,
                            'type' => 'value',
                            'field_id' => $fieldFullID,
                        ];
                    }

                    if($include_term_ids){
                        array_push($headers[$rt], $field_name.' ID');
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'term_id',
                            'field_id' => $fieldFullID,
                        ];
                    }

                    if($include_term_codes){
                        array_push($headers[$rt], $field_name.' StdCode' );
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'term_code',
                            'field_id' => $fieldFullID,
                        ];
                    }


                }else{
                    array_push($headers[$rt], $field_name);
                    $csvColIndex = count($headers[$rt]) - 1;
                    $columnInfo[$rt][] = [
                        'index' => $csvColIndex,
                        'type' => 'value',
                        'field_id' => $fieldFullID,
                    ];

                    if($include_temporals && $field_type=='date'){
                        array_push($headers[$rt], $field_name.' (temporal)');
                        $csvColIndex = count($headers[$rt]) - 1;
                        $columnInfo[$rt][] = [
                            'index' => $csvColIndex,
                            'type' => 'value',
                            'field_id' => $fieldFullID,
                        ];
                    }
                }

                if($dt_id == 'rec_ID'){
                    if($include_record_url_html){ // Record URL in HTML
                        array_push($headers[$rt], 'Record URL (HTML)' );
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'record_url_html',
                            'field_id' => $fieldFullID,
                        ];
                    }
                    if($include_record_url_xml){ // Record URL in XML
                        array_push($headers[$rt], 'Record URL (XML)' );
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'record_url_html',
                            'field_id' => $fieldFullID,
                        ];
                    }
                }

                if($field_type=='file'){ // Add extra details for files
                    array_push($headers[$rt], $field_name.' ID' );
                    $columnInfo[$rt][] = [
                        'index' => count($headers[$rt]) - 1,
                        'type' => 'file_id',
                        'field_id' => $fieldFullID,
                    ];
                    array_push($headers[$rt], $field_name.' Name' );
                    $columnInfo[$rt][] = [
                        'index' => count($headers[$rt]) - 1,
                        'type' => 'file_name',
                        'field_id' => $fieldFullID,
                    ];
                    array_push($headers[$rt], $field_name.' Path' );
                    $columnInfo[$rt][] = [
                        'index' => count($headers[$rt]) - 1,
                        'type' => 'file_path',
                        'field_id' => $fieldFullID,
                    ];
                    if($include_file_url){
                        array_push($headers[$rt], $field_name.' URL' );
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'file_url',
                            'field_id' => $fieldFullID,
                        ];
                    }
                }

                //add title for resource (record pointer) fields
                if($include_resource_titles && ($field_type=='resource' || $field_type=='relmarker')){

                    array_push($headers[$rt], $field_name_title);
                    $columnInfo[$rt][] = [
                        'index' => count($headers[$rt]) - 1,
                        'type' => 'value', // resource_title
                        'field_id' => 'rec_Title'
                    ];
                }

                // Save column index for advanced options.
                if ($csvColIndex !== null) {
                    $fieldKey = $rt . ':' . $dt_id;
                    if (in_array($fieldKey, $groupFields)) {
                        if (!isset($groupColIndices[$rt])) {
                            $groupColIndices[$rt] = [];
                        }
                        $groupColIndices[$rt][] = $csvColIndex;
                    }
                    if (in_array($fieldKey, $countFields)) {
                        if (!isset($countColIndices[$rt])) {
                            $countColIndices[$rt] = [];
                        }
                        $countColIndices[$rt][] = $csvColIndex;
                        $headers[$rt][$csvColIndex] = 'Count of ' . $headers[$rt][$csvColIndex];
                    }
                    if (in_array($fieldKey, $sumFields)) {
                        if (!isset($sumColIndices[$rt])) {
                            $sumColIndices[$rt] = [];
                        }
                        $sumColIndices[$rt][] = $csvColIndex;
                        $headers[$rt][$csvColIndex] = 'Sum of ' . $headers[$rt][$csvColIndex];
                    }
                    if (in_array($fieldKey, $percentageFields)) {
                        if (!isset($percentageColIndices[$rt])) {
                            $percentageColIndices[$rt] = [];
                        }
                        $percentageColIndices[$rt][] = $csvColIndex;
                    }
                    if (!empty($sortFields[$fieldKey])) {
                        if (!isset($sortColIndices[$rt])) {
                            $sortColIndices[$rt] = [];
                        }
                        if (!isset($sortOrders[$rt])) {
                            $sortOrders[$rt] = [];
                        }
                        $sortColIndices[$rt][] = $csvColIndex;
                        $sortOrders[$rt][] = $sortFields[$fieldKey];
                    }
                }
            }
        }
    }

    $csv_delimiter =  $params['prefs']['csv_delimiter']?$params['prefs']['csv_delimiter']:',';
    $csv_enclosure =  (@$params['prefs']['csv_enclosure']==null || $params['prefs']['csv_enclosure']=='0')
                                        ?null:$params['prefs']['csv_enclosure'];
    $csv_mvsep =  $params['prefs']['csv_mvsep']?$params['prefs']['csv_mvsep']:'|';
    $csv_linebreak =  $params['prefs']['csv_linebreak']?$params['prefs']['csv_linebreak']:'nix';//not used
    $csv_header =  $params['prefs']['csv_header']?$params['prefs']['csv_header']:true;

    //------------
    $records = $data['records'];

    $streams = array();//one per record type
    $rt_counts = array();
    $csvData = [];

    $error_log = array();
    $error_log[] = 'Total rec count '.count($records);

    $idx = 0;
    if(is_array($records))
    {
        while ($idx<count($records)){

        $recID = $records[$idx];
        $record = recordSearchByID(self::$system, $recID, false);
        $rty_ID = ($any_rectype!=null)?$any_rectype :$record['rec_RecTypeID'];

        $idx++;

        if(!@$fields[$rty_ID]) {continue;} //none of fields for this record type marked to output


        if($has_advanced){ // everything is putting into memory

            if (!isset($csvData[$rty_ID])) {
                $csvData[$rty_ID] = [];
                if($csv_header) {
                    $csvData[$rty_ID][] = $headers[$rty_ID];
                }

                $rt_counts[$rty_ID] = 1;
            } else {
                $rt_counts[$rty_ID]++;
            }

        }else {
            if(!@$streams[$rty_ID]){
                // create a temporary file
                $fd = fopen(TEMP_MEMORY, 'w');//less than 1MB in memory otherwise as temp file
                if (false === $fd) {
                    self::$system->errorExitApi('Failed to create temporary file for csv output');
                }
                $streams[$rty_ID] = $fd;

                //write header
                if($csv_header){
                    if($csv_enclosure){
                        fputcsv($fd, $headers[$rty_ID], $csv_delimiter, $csv_enclosure);
                    }else{
                        fputs($fd, implode($csv_delimiter, $headers[$rty_ID])."\n");    
                    }
                }

                $rt_counts[$rty_ID] = 1;
            }else{
                $fd = $streams[$rty_ID];

                $rt_counts[$rty_ID]++;
            }
        }

        if(!isEmptyArray(@$details[$rty_ID])){
            //fils $record
            recordSearchDetails(self::$system, $record, $details[$rty_ID]);
        }
        if(!isEmptyArray(@$relmarker_details[$rty_ID])){
            $related_recs = recordSearchRelated(self::$system, array($recID), 0);
            if(@$related_recs['status']==HEURIST_OK){
                $related_recs = $related_recs['data'];
            }else{
                $related_recs = array();
            }
        }else{
            $related_recs = array();
        }

        //prepare output array
        $record_row = array();
        foreach($fields[$rty_ID] as $dt_id){

            //suppl.fields for enum (terms) and resource (record pointer) fields
            $date_temporals = array();
            $enum_label = array();
            $enum_code = array();
            $resource_titles = array();
            $file_urls = array();
            $record_urls = array();
            $file_ids = array();
            $file_names = array();
            $file_paths = array();

            $constr_rt_id = 0;
            if(strpos($dt_id,':')>0){ //for constrained resource (record pointer) fields
                list($dt_id, $constr_rt_id) = explode(':', $dt_id);
            }

            if(is_numeric($dt_id) && $dt_id>0){

                if ($constr_rt_id>0 && @$relmarker_details[$rty_ID][$dt_id]==$constr_rt_id) {  //relation

                    $vals = array();

                    foreach($related_recs['direct'] as $relation){
                        $target_rt = $related_recs['headers'][$relation->targetID][1];
                        if( $constr_rt_id==$target_rt && $relation->trmID>0){ //contrained rt and allowed relation type

                            $all_terms = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_tree];
                            $nonsel_terms = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_nosel];
                            $is_allowed = \VerifyValue::isValidTerm($all_terms, $nonsel_terms, $relation->trmID, $dt_id);

                            if($is_allowed){
                                //if record type among selected -  add record to list to be exported
                                //otherwise export only ID  as field "Rectype H-ID"
                                if($fields[$target_rt]){ //record type exists in output
                                    if(!in_array($relation->targetID, $records)){
                                        array_push($records, $relation->targetID);//add to be exported
                                    }
                                }
                                $vals[] = $relation->targetID;

                                if($include_resource_titles){
                                    $resource_titles[] = $related_recs['headers'][$relation->targetID][0];
                                }
                            }
                        }
                    }//foreach

                    //reverse will work only in case source record has detail id as in target
                    foreach($related_recs['reverse'] as $relation){
                        $source_rt = $related_recs['headers'][$relation->sourceID][1];
                        if( $constr_rt_id==$source_rt && $relation->trmID>0
                        && @self::$defRecTypes['typedefs'][$source_rt]['dtFields'][$dt_id]
                        ){ //contrained rt and allowed relation type

                            $all_terms = self::$defRecTypes['typedefs'][$source_rt]['dtFields'][$dt_id][$idx_term_tree];
                            $nonsel_terms = self::$defRecTypes['typedefs'][$source_rt]['dtFields'][$dt_id][$idx_term_nosel];
                            $is_allowed = \VerifyValue::isValidTerm($all_terms, $nonsel_terms, $relation->trmID, $dt_id);

                            if($is_allowed){
                                //if record type among selected -  add record to list to be exported
                                //otherwise export only ID  as field "Rectype H-ID"
                                if($fields[$source_rt]){ //record type exists in output
                                    if(!in_array($relation->sourceID, $records)){
                                        array_push($records, $relation->sourceID);//add to be exported
                                    }
                                }
                                $vals[] = $relation->sourceID;

                                if($include_resource_titles){
                                    $resource_titles[] = $related_recs['headers'][$relation->sourceID][0];
                                }
                            }
                        }
                    }

                    $value = implode($csv_mvsep, $vals);

                    if($include_resource_titles && empty($vals)){ //empty value
                        $resource_titles[] = '';
                    }

                }else{

                    if($dt_id == DT_PARENT_ENTITY){
                        $dt_type = 'resource';
                    }else{
                        $dt_type = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_dtype];
                    }

                    $values = @$record['details'][$dt_id];

                    if(isset($values)){

                        //$values = array_values($values);//get plain array
                        $vals = array();

                        if($dt_type=="resource"){

                            //if record type among selected -  add record to list to be exported
                            //otherwise export only ID (and optionally title)  as field "Rectype H-ID"
                            foreach($values as $val){
                                if( (!($constr_rt_id>0)) || $constr_rt_id==$val['type'] ){ //unconstrained or exact required rt

                                    if($fields[$val['type']]){ //record type exists in output
                                        if(!in_array($val['id'], $records)){
                                            array_push($records, $val['id']);//add to be exported
                                        }
                                    }
                                    $vals[] = $val['id'];

                                    if($include_resource_titles){
                                        $resource_titles[] = $val['title'];
                                    }
                                }
                            }
                        }elseif($dt_type=='geo'){
                            foreach($values as $val){
                                $vals[] = $val['geo']['wkt'];
                            }
                        }elseif($dt_type=='file'){
                            foreach($values as $val){

                                $vals[] = 'ulf_' . $val['file']['ulf_ObfuscatedFileID'];

                                $file_ids[] = $val['file']['ulf_ID'];
                                $file_names[] = !empty($val['file']['ulf_OrigFileName']) ? $val['file']['ulf_OrigFileName'] : ULF_REMOTE;//$val['file']['ulf_ExternalFileReference']

                                if(!empty($val['file']['fullPath'])){
                                    $file_paths[] = $val['file']['fullPath'];
                                }elseif(!empty($val['file']['ulf_ExternalFileReference'])){
                                    $file_paths[] = $val['file']['ulf_ExternalFileReference'];//ULF_REMOTE
                                }else{
                                    $file_paths[] = '';
                                }

                                if($include_file_url){
                                    if(@$val['file']['ulf_ExternalFileReference']){
                                        $file_urls[] = $val['file']['ulf_ExternalFileReference'];
                                    }else{
                                        $file_urls[] = HEURIST_BASE_URL.'?db='.self::$system->dbname().'&file='.$val['file']['ulf_ObfuscatedFileID'];
                                    }
                                }
                            }
                        }elseif($dt_type=='date'){
                            foreach($values as $val){
                                $vals[] = Temporal::toHumanReadable(trim($val));
                                if($include_temporals){
                                    $date_temporals[] = trim($val);
                                }
                            }
                        }elseif($dt_type=='enum' || $dt_type=='relationtype'){

                            if(!empty($defTerms) && !isEmptyArray($values) ){
                                foreach($values as $val){
                                    $enum_label[] = $defTerms->getTermLabel($val, $include_term_hierarchy);
                                    // @$defTerms[$val][$idx_term_label]?$defTerms[$val][$idx_term_label]:'';
                                    $enum_code[] = $defTerms->getTermCode($val);
                                    //@$defTerms[$val][$idx_term_code]?$defTerms[$val][$idx_term_code]:'';
                                }
                            }else{
                                $enum_label[] = '';
                                $enum_code[] = '';
                            }
                            $vals = $values;
                        }elseif($dt_type == 'freetext' && $dt_type == 'blocktext'){
                            // escape all line feed (\n) within text values, to avoid confusing the import
                            // freetext shouldn't have any, but just in case
                            foreach($values as $val){
                                $vals[] = str_replace("\n", "\\n", $val);
                            }
                        }else{
                            $vals = $values;
                        }

                        $value = implode($csv_mvsep, $vals);
                    }elseif($dt_type == 'relmarker' && defined('RT_RELATION') && $constr_rt_id == RT_RELATION){ // selected relationship fields

                        $values = [];

                        foreach($related_recs['direct'] as $related){

                            if($related->relationID == 0 || in_array($related->relationID, $values)){
                                continue;
                            }

                            $all_terms = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_tree];
                            $nonsel_terms = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_nosel];
                            $isTrmAllowed = \VerifyValue::isValidTerm($all_terms, $nonsel_terms, $related->trmID, $dt_id);
                            $isRtyAllowed = \VerifyValue::isValidPointer($relmarker_details[$rty_ID][$dt_id], $related->targetID);

                            if(!$isTrmAllowed || !$isRtyAllowed){
                                continue;
                            }

                            if(array_key_exists(RT_RELATION, $fields) && !in_array($related->relationID, $records)){
                                $records[] = $related->relationID;
                                $related_recs['headers'][$related->relationID] = [
                                    mysql__select_value(self::$mysqli, 'SELECT rec_Title FROM Records WHERE rec_ID = ?', ['i', $related->relationID]),
                                    RT_RELATION,
                                    0,
                                    mysql__select_value(self::$mysqli, 'SELECT rec_NonOwnerVisibility FROM Records WHERE rec_ID = ?', ['i', $related->relationID])
                                ];
                            }

                            $values[] = $related->relationID;

                            if($include_resource_titles){
                                $resource_titles[] = $related_recs['headers'][$related->relationID][0];
                            }
                        }

                        foreach($related_recs['reverse'] as $related){

                            if($related->relationID == 0 || in_array($related->relationID, $values)){
                                continue;
                            }

                            $sourceRTY = $related_recs['headers'][$related->sourceID][1];

                            $all_terms = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_tree];
                            $nonsel_terms = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_nosel];
                            $isTrmAllowed = \VerifyValue::isValidTerm($all_terms, $nonsel_terms, $related->trmID, $dt_id);
                            $isRtyAllowed = \VerifyValue::isValidPointer($relmarker_details[$sourceRTY][$dt_id], $recID);

                            if(!$isTrmAllowed || !$isRtyAllowed){
                                continue;
                            }

                            if(array_key_exists(RT_RELATION, $fields) && !in_array($related->relationID, $records)){
                                $records[] = $related->relationID;
                                $related_recs['headers'][$related->relationID] = [
                                    mysql__select_value(self::$mysqli, 'SELECT rec_Title FROM Records WHERE rec_ID = ?', ['i', $related->relationID]),
                                    RT_RELATION,
                                    0,
                                    mysql__select_value(self::$mysqli, 'SELECT rec_NonOwnerVisibility FROM Records WHERE rec_ID = ?', ['i', $related->relationID])
                                ];
                            }

                            $values[] = $related->relationID;

                            if($include_resource_titles){
                                $resource_titles[] = $related_recs['headers'][$related->relationID][0];
                            }
                        }

                        $value = implode($csv_mvsep, $values);

                        if($include_resource_titles && empty($values)){
                            $resource_titles[] = '';
                        }
                    }else{
                        $value = null;
                    }

                    //empty values
                    if($value === null){
                        if($dt_type=='enum' || $dt_type=='relationtype'){

                            $enum_label[] = '';
                            $enum_code[] = '';

                        }elseif($include_resource_titles && ($dt_type=='resource' || $dt_type=="relmarker")){
                            $resource_titles[] = '';
                        }elseif($dt_type=='file'){
                            $file_ids[] = '';
                            $file_names[] = '';
                            $file_paths[] = '';
                            if($include_file_url){
                                $file_urls[] = '';
                            }
                        }elseif($dt_type=='date' && $include_temporals){
                            $date_temporals[] = '';
                        }
                    }

                }

            }elseif($dt_id=='rec_Tags'){

                $value = recordSearchPersonalTags(self::$system, $recID);
                $value = ($value===null)?'':implode($csv_mvsep, $value);

            }elseif($dt_id=='rec_RecTypeName'){
                $value = self::$defRecTypes['names'][$rty_ID];
            }elseif($dt_id=='rec_ID'){
                $value = @$record[$dt_id];
                $rec_url_base = HEURIST_BASE_URL_PRO . '?db=' . self::$system->dbname() . '&recID=' . $value;
                if($include_record_url_html){ // html
                    $record_urls[] = $rec_url_base . '&fmt=html';
                }
                if($include_record_url_xml){ // xml
                    $record_urls[] = $rec_url_base;
                }
            }else{
                $value = @$record[$dt_id];//from record header
            }
            if($value===null) {$value = ''; }


            if(!isEmptyArray($enum_label)){
                if(!$term_ids_only) {$record_row[] = implode($csv_mvsep,$enum_label);}
                if($include_term_ids) {$record_row[] = $value;}
                if($include_term_codes) {$record_row[] = implode($csv_mvsep,$enum_code);}
            }else {
                $record_row[] = $value;

                // Additional Date Field
                if(!empty($date_temporals)){
                    $record_row[] = implode($csv_mvsep, $date_temporals);
                }

                // Additional File Fields
                if (!empty($file_ids)){
                    $record_row[] = implode($csv_mvsep,$file_ids);
                }
                if (!empty($file_names)){
                    $record_row[] = implode($csv_mvsep,$file_names);
                }
                if (!empty($file_paths)){
                    $record_row[] = implode($csv_mvsep,$file_paths);
                }

                if (!empty($resource_titles)){
                    $record_row[] = implode($csv_mvsep,$resource_titles);
                }elseif (!empty($file_urls)){
                    $record_row[] = implode($csv_mvsep,$file_urls);
                }elseif (!empty($record_urls)){
                    $record_row[] = implode($csv_delimiter,$record_urls);// two separate columns
                }

                if($value == '' && ($dt_type=='resource' || $dt_type=='relmarker') && $include_resource_titles && empty($resource_titles)){ // to avoid mismatched rows when adding details
                    $record_row[] = $value;
                }
            }

        }//for fields

        // write the data to csv
        if(!isEmptyArray($record_row)) {
            if($has_advanced){
                $csvData[$rty_ID][] = $record_row;

                if($need_check_memory){
                    $cnt = count($records);

                    if($cnt>2000){

                        if(strlen(implode(',',$record_row))*$cnt*1.5>$memory_limit){
                            self::$system->errorExitApi('Sorry, this export exceeds the limit set for this server. Please reduce the number of records or the number of fields selected');
                        }

                    }
                    $need_check_memory = false;
                }


            }elseif($csv_enclosure){
                fputcsv($fd, $record_row, $csv_delimiter, $csv_enclosure);
            }else{
                fputs($fd, implode($csv_delimiter, $record_row)."\n");    
            }
        }

    }//for records
    }
    // Join csv tables.
    if ($isJoinTable && !empty($mainRecordTypeIDs)) {
        $mainRecordTypeID = $mainRecordTypeIDs[0];
        if (!empty($csvData[$mainRecordTypeID]) && !empty($columnInfo[$mainRecordTypeID])) {
            $csvData = [
                $mainRecordTypeID => self::createJointCSVTables($csvData, $columnInfo, $mainRecordTypeID, $csv_mvsep, $csv_header),
            ];

            // Change advanced option column indices.
            $groupColIndices = self::changeAdvancedOptionColumnIndex($groupColIndices, $mainRecordTypeID, $columnInfo);
            $sumColIndices = self::changeAdvancedOptionColumnIndex($sumColIndices, $mainRecordTypeID, $columnInfo);
            $countColIndices = self::changeAdvancedOptionColumnIndex($countColIndices, $mainRecordTypeID, $columnInfo);
            $percentageColIndices = self::changeAdvancedOptionColumnIndex($percentageColIndices, $mainRecordTypeID, $columnInfo);
            $sortColIndices = self::changeAdvancedOptionColumnIndex($sortColIndices, $mainRecordTypeID, $columnInfo);
            $sortOrders = self::changeSortOrders($sortOrders, $mainRecordTypeID);
        }
    }

    // Save data to streams.
    if ($has_advanced && !empty($csvData)) {
        foreach ($csvData as $recordTypeID => $rows) {
            $streams[$recordTypeID] = fopen(TEMP_MEMORY, 'w');

            if (is_array($rows) && !empty($rows)) {
                if ($csv_header) {
                    $headerRow = array_shift($rows);
                    if (!empty($percentageColIndices[$recordTypeID])) {
                        $headerRow = self::usePercentageForCSVHeaders($headerRow, $percentageColIndices[$recordTypeID]);
                    }
                    if($csv_enclosure){
                        fputcsv($streams[$recordTypeID], $headerRow, $csv_delimiter, $csv_enclosure);
                    }else{
                        fputs($streams[$recordTypeID], implode($csv_delimiter, $headerRow)."\n");    
                    }
                }
                // Apply advanced options.
                if (!empty($groupColIndices[$recordTypeID])) {
                    $sumCols = empty($sumColIndices[$recordTypeID]) ? [] : $sumColIndices[$recordTypeID];
                    $countCols = empty($countColIndices[$recordTypeID]) ? [] : $countColIndices[$recordTypeID];
                    $rows = self::groupCSVRows($rows, $groupColIndices[$recordTypeID], $sumCols, $countCols);
                }
                if (!empty($percentageColIndices[$recordTypeID])) {
                    $rows = self::usePercentageForCSVRows($rows, $percentageColIndices[$recordTypeID]);
                }
                if (!empty($sortColIndices[$recordTypeID]) && is_array($sortColIndices[$recordTypeID])) {
                    // Mutate col indices as new columns inserted.
                    for ($i = 0; $i < count($sortColIndices[$recordTypeID]); $i++) {
                        $colIndex = $sortColIndices[$recordTypeID][$i];
                        foreach ($percentageColIndices[$recordTypeID] as $percentageColIndex) {
                            if ($colIndex > $percentageColIndex) {
                                $sortColIndices[$recordTypeID][$i]++;
                            }
                        }
                    }
                    $rows = self::sortCSVRows($rows, $sortColIndices[$recordTypeID], $sortOrders[$recordTypeID]);
                }

                
                if($csv_enclosure){
                    foreach ($rows as $row) {
                        fputcsv($streams[$recordTypeID], $row, $csv_delimiter, $csv_enclosure);
                    }
                }else{
                    foreach ($rows as $row) {
                        fputs($streams[$recordTypeID], implode($csv_delimiter, $row)."\n");
                    }
                }
                
            }
        }
    }//$has_advanced


    //calculate number of streams with columns more than one
    $count_streams = 0;
    foreach($headers as $rty_ID => $columns){
        if(is_array($columns) && count($columns)>1){
            $count_streams++;
        }
    }

    $error_log[] = print_r($rt_counts, true);

    return self::writeResults( $streams, $filename, $headers, $error_log, $save_to_file );
} //output

//
//
    /**
     * Outputs a CSV template for specified record types, including field details and term pickup lists.
     *
     * This method generates a CSV file that serves as a template or a descriptive guide
     * for the structure of selected record types. Instead of record data, it outputs:
     * - CSV headers based on selected fields (from `$params['prefs']['fields']`).
     * - Detailed information about each field, including its ID, name, type, multivalue status,
     *   requirement, usage count, concept ID, and base name. This can be output as rows
     *   or as additional header rows depending on `$params['prefs']['output_rows']`.
     * - For 'enum' or 'relationtype' fields, it can generate "pickup lists" of allowed terms,
     *   showing the term hierarchy.
     *
     * Key parameters from `$params['prefs']`:
     *   - `fields`: Defines which fields to include for each record type.
     *   - `include_term_ids`, `include_term_codes`, `include_term_hierarchy`: Options for term representation.
     *   - `include_resource_titles`, `include_file_url`, `include_record_url_html`,
     *     `include_record_url_xml`, `include_temporals`: Similar to `output()` method, affecting header names.
     *   - `output_rows`: Boolean, if true, field details are output as separate rows per field.
     *     Otherwise, field details (like type, count etc.) are output as additional header rows.
     *   - `csv_delimiter`, `csv_enclosure`: CSV formatting options.
     *
     * The output is written to temporary memory streams, one per record type, and then
     * assembled into a single CSV file or a ZIP archive by `writeResults`.
     *
     * @param array $data Although a `$data` parameter is accepted (likely for consistency with `output`),
     *                    it's not directly used for fetching record data in this method.
     * @param array $params An array of parameters controlling the template generation.
     *                      Key settings are under `$params['prefs']`.
     * @return void Outputs directly to the browser or prepares data for file save via `writeResults`.
     */
public static function output_header($data, $params)
{

    self::initialize();

    $include_term_ids = (@$params['prefs']['include_term_ids']==1);
    $include_term_codes = (@$params['prefs']['include_term_codes']==1);
    $include_resource_titles =  (@$params['prefs']['include_resource_titles']==1);
    $include_term_hierarchy = (@$params['prefs']['include_term_hierarchy']==1);
    $include_file_url = (@$params['prefs']['include_file_url']==1);
    $include_record_url_html = (@$params['prefs']['include_record_url_html']==1);
    $include_record_url_xml = (@$params['prefs']['include_record_url_xml']==1);
    $include_temporals = (@$params['prefs']['include_temporals']==1);
    $output_rows = (@$params['prefs']['output_rows'] == 1);// default output details as columns

    $fields = @$params['prefs']['fields'];
    $details = array();//array of detail fields included into output
    $relmarker_details = array();//relmarker fields included into output
    $fld_type_names = dbs_GetDtLookups();
    $base_fld_names = dbs_GetDetailTypes(self::$system, null, 0);

    if(self::$defRecTypes==null) {
        self::$defRecTypes = dbs_GetRectypeStructures(self::$system, null, 2);
    }
    $idx_cid = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_ConceptID'];
    $idx_name = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dtype = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    $idx_count = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_MaxValues'];
    $idx_require = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_RequirementType'];
    $idx_term_tree = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];

    $fld_usages = array();
    $header_details = array('Field ID', 'Field name', 'Field type', 'Multivalue', 'Requirement', 'Usage count', 'Concept ID', 'Base name');// field details being exported
    $defRecStructure = new DbDefRecStructure(self::$system, null);
    $rst_data = array('a' => 'counts', 'mode' => 'rectype_field_usage', 'get_meta_counts' => 1, 'rtyID' => null);

    //create header
    $any_rectype = null;
    $headers = array();
    $fld_details = array();
    $terms_pickup = array();
    if($fields){
        foreach($fields as $rt=>$flds){

            if($flds[1] == 'rec_ID'){ // flip, so rec id is first
                list($flds[0], $flds[1]) = [$flds[1], $flds[0]];
            }

            //always include ID field into output
            if($flds[0]!='rec_ID') {array_unshift($flds, 'rec_ID');}
            $fields[$rt] = $flds;

            $details[$rt] = array();
            $headers[$rt] = array();
            $fld_details[$rt] = array();
            $relmarker_details[$rt] = array();

            // Get field usages
            if($rt > 0 && !array_key_exists($rt, $fld_usages)){
                // update rectype id
                $rst_data['rtyID'] = $rt;
                $defRecStructure->setData($rst_data);
                // retrieve usages
                $cnt_res = $defRecStructure->run();
                // save
                $fld_usages[$rt] = $cnt_res !== false ? $cnt_res : self::$system->getErrorMsg();
                //$fld_usages[$rt] = $cnt_res !== false ? $cnt_res : array();
            }

            foreach($flds as $dt_id){

                $constr_rt_id = 0;
                if(strpos($dt_id,':')>0){ //for constrained resource (record pointer) fields
                    //example author:person or organization
                    list($dt_id, $constr_rt_id) = explode(':',$dt_id);
                }

                $field_name_title = '';

                if(is_numeric($dt_id) && $dt_id>0){

                    if($dt_id==DT_PARENT_ENTITY){
                        $field_name = 'Parent entity';
                        $field_type = 'resource';
                    }else{
                        //get field name from structure
                        $field_name = self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_name];
                        $field_type = self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_dtype];
                    }
                    if($constr_rt_id>0){
                        $rectypename_is_in_fieldname = (strpos(strtolower($field_name),
                                            strtolower(self::$defRecTypes['names'][$constr_rt_id]))!==false);
                        $field_name_title = $field_name.' '
                                                //.($rectypename_is_in_fieldname?'':(self::$defRecTypes['names'][$constr_rt_id].' '))
                                                .H_FLDS['rec_Title'];
                        $field_name = $field_name.($rectypename_is_in_fieldname
                                            ?'':' ('.self::$defRecTypes['names'][$constr_rt_id].')').' '.H_FLDS['rec_ID'];
                    }else{
                        $field_name_title = $field_name.H_FLDS['rec_Title'];
                    }
                    if($field_type=='relmarker'){
                        $relmarker_details[$rt][$dt_id] = $constr_rt_id;
                    }else{
                        array_push($details[$rt], $dt_id);
                    }

                }else{
                    //record header
                    $field_type = null;

                    $field_name = array_key_exists($dt_id, H_FLDS) ? H_FLDS[$dt_id] : $dt_id;

                    if($rt>0){
                        $field_name = self::$defRecTypes['names'][$rt].' '.$field_name;
                    }else{
                        $any_rectype = $rt;
                    }
                }

                if($field_type=='separator'){ // skip separator
                    continue;
                }

                $fld = self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id];
                $count = $fld[$idx_count] != 1 ? 'Multivalue' : 'Single';
                $typename = !empty($fld_type_names[$field_type]) ? $fld_type_names[$field_type] : 'Built-in';
                $requirement = $fld[$idx_require];
                $usage = is_array($fld_usages[$rt]) && array_key_exists($dt_id, $fld_usages[$rt]) ? $fld_usages[$rt][$dt_id] : 0;
                $concept_id = $fld[$idx_cid];
                $base_name = $base_fld_names['names'][$dt_id];

                if($requirement == ''){
                    if($dt_id == 'rec_ID'){
                        $requirement = 'required';
                    }else{
                        $requirement = 'optional';
                    }
                }

                array_push($fld_details[$rt], array($dt_id, $field_name, $typename, $count, ucfirst($requirement), "N=$usage", $concept_id, $base_name));

                if($field_type=='enum' || $field_type=='relationtype'){

                    array_push($headers[$rt], $field_name);// labels are always included

                    if($include_term_ids){
                        array_push($headers[$rt], $field_name.' ID');
                    }

                    if($include_term_codes){
                        array_push($headers[$rt], $field_name.' StdCode' );
                    }

                    //add terms pickup list
                    if(!@$terms_pickup[$rt]) {$terms_pickup[$rt] = array();}
                    $terms_pickup[$rt][$dt_id] = array('name'=>$field_name, 'domain'=>$field_type,
                                             'term_ids'=>self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_term_tree],
                                             'nonsel'=>self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_term_tree]);

                }else{
                    array_push($headers[$rt], $field_name);
                    if($include_temporals && $field_type=='date'){
                        array_push($headers[$rt], $field_name.'(temporal)');
                    }
                }

                //add title for resource (record pointer) fields
                if($include_resource_titles && ($field_type=='resource' || $field_type=='relmarker')){
                    array_push($headers[$rt], $field_name_title);
                }
            }
        }
    }


    if(!isEmptyArray($terms_pickup)) {
        $defTerms = dbs_GetTerms(self::$system);
        $defTerms = new \DbsTerms(self::$system, $defTerms);
    }


    $csv_delimiter =  $params['prefs']['csv_delimiter']?$params['prefs']['csv_delimiter']:',';
    $csv_enclosure =  $params['prefs']['csv_enclosure']?$params['prefs']['csv_enclosure']:'"';

    $streams = array();//one per record type

    $temp_name = null;
    $print_header = true;
    //------------
    foreach($headers as $rty_ID => $columns){

        $placeholders = null;
        $cnt_cols = count($columns);
        if($cnt_cols>1){
            if(!@$fields[$rty_ID]) {continue;} //none of fields for this record type marked to output

            //prepare terms
            if(is_array(@$terms_pickup[$rty_ID])){  //there are enum fields for this rt

                $max_count = 0;
                $placeholders = array();

                foreach($terms_pickup[$rty_ID] as $dtid => $field){

                    $placeholders[] = strtoupper($field['name']);
                    $ph_help[] = '<Use to create value control lists>';
                    //get list of terms
                    $vocabId = $field['term_ids'];
                    $terms = $defTerms->treeData($vocabId, 3);
                    array_unshift($terms, $vocabId);
                    $max_count = max($max_count, count($terms));
                    $terms_pickup[$rty_ID][$dtid]['terms'] = $terms;
                }
            }

            $fd = fopen(TEMP_MEMORY, 'w');//less than 1MB in memory otherwise as temp file
            $streams[$rty_ID] = $fd;

            $header = $headers[$rty_ID];
            if($output_rows){
                $header = $header_details;
            }

            //write header
            if($print_header){
                fputcsv($fd, $header, $csv_delimiter, $csv_enclosure);
                //fwrite($fd, "\n\n");

                $print_header = $output_rows ? false : true; // print header once for rows output
            }

            //write field details
            if(array_key_exists($rty_ID, $fld_details)){

                if($output_rows){
                    foreach ($fld_details[$rty_ID] as $details) {
                        fputcsv($fd, $details, $csv_delimiter, $csv_enclosure);
                    }
                }else{

                    $max = count($header_details);
                    $idx = 1; // ignore field name
                    while($idx < $max){

                        $dtl_row = array();
                        foreach($fld_details[$rty_ID] as $dtls){
                            array_push($dtl_row, $dtls[$idx]);
                        }

                        fputcsv($fd, $dtl_row, $csv_delimiter, $csv_enclosure);

                        $idx ++;
                    }
                }

                fwrite($fd, "\n\n");
            }

            //write terms
            if($placeholders!=null){

                fputcsv($fd, $placeholders, $csv_delimiter, $csv_enclosure);
                fputcsv($fd, $ph_help, $csv_delimiter, $csv_enclosure);

                $k = 0;
                while ($k<$max_count){

                    $placeholders = array(); //no need to create empty columns: array_fill(0, $cnt_cols, '')

                    foreach($terms_pickup[$rty_ID] as $dtid => $field){

                        $terms = $terms_pickup[$rty_ID][$dtid]['terms'];

                        if($k<count($terms)){
                            $placeholders[] =  $defTerms->getTermLabel($terms[$k], true);
                        }else{
                            $placeholders[] = '';
                        }
                    }//for fields

                    fputcsv($fd, $placeholders, $csv_delimiter, $csv_enclosure);

                    $k++;
                }//while

            }

            if($temp_name==null){
                $temp_name = 'Heurist_'.self::$system->dbname();//.'_t'.$rty_ID.'_'.self::$defRecTypes['names'][$rty_ID];
            }
        }
    }
    self::writeResults( $streams, $temp_name, $headers, null );
}


//
//
    /**
     * Writes the generated CSV data from memory streams to the output (browser or file).
     *
     * This method handles the final stage of CSV export.
     * If there's only one stream (or effectively one, as others might be empty or just headers):
     * - It retrieves the content from the stream.
     * - If `$save_to_file` is true, it saves the content to the specified `$temp_name` (which
     *   might include a directory path).
     * - Otherwise, it sends appropriate HTTP headers for CSV download and echoes the content.
     *   A BOM (Byte Order Mark) is prepended for UTF-8 compatibility.
     *
     * If there are multiple streams (typically one per record type with actual data):
     * - It creates a ZIP archive.
     * - Each stream's content is added as a separate CSV file within the ZIP archive.
     *   Filenames are generated based on record type ID and name.
     * - An error log (if provided) is added as 'log.txt' to the ZIP.
     * - HTTP headers for ZIP download are sent, and the ZIP file is streamed to the browser.
     * - The temporary ZIP file is deleted afterwards.
     *
     * @param array $streams An array of file pointer resources (memory streams) containing the CSV data for each record type.
     * @param string $temp_name The base filename for the output. For single CSV, this is the filename.
     *                          For ZIP, this is part of the ZIP filename and the target path for saving if `$save_to_file` is true.
     * @param array $headers An array of headers, used to determine if a stream for a record type actually produced data beyond just a header.
     * @param array|null $error_log An array of error messages to include in a log file (primarily for ZIP output).
     * @param bool $save_to_file If true, attempts to save the output to a file on the server
     *                           instead of streaming to the browser. Default is false.
     * @return int|void If `$save_to_file` is true, returns the number of bytes written (or a negative value on error).
     *                  Otherwise, no explicit return value as it exits after output.
     */
private static function writeResults( $streams, $temp_name, $headers, $error_log, $save_to_file=false ) {

    if(is_array($streams) && count($streams)<2){

        $out = false;
        $rty_ID = 0;

        if(empty($streams)){
            if($error_log) {array_push($error_log, "Streams are not defined");}
        }else{
            $rty_ID = array_keys($streams);
            $rty_ID = intval($rty_ID[0]);

            if(!$save_to_file || empty($temp_name)){

                $csv_filename = basename($temp_name);
                if($rty_ID>0){
                    $rty_Name = mb_ereg_replace('\s', '_', self::$defRecTypes['names'][$rty_ID]);
                    $csv_filename = basename(USanitize::sanitizeFileName($csv_filename.'_t'.$rty_ID.'_'.$rty_Name));
                }
            }
            
            $ext = pathinfo($csv_filename, PATHINFO_EXTENSION);
            if(!(strlen($ext)>0 && preg_match('/^[a-zA-Z0-9]+$/', $ext))){
                $csv_filename .= '.csv';    
            }

            $csv_filename = basename($csv_filename);

            $fd = $streams[$rty_ID];

            if($fd==null){
                if($error_log) {array_push($error_log, "Stream for record type $rty_ID is not defined");}
            }else{
                rewind($fd);
                $out = stream_get_contents($fd);
                fclose($fd);
            }
        }

        $has_error = false;

        if( !isset($out) || $out===false || strlen($out)==0){
            $out = "Stream for record type $rty_ID is empty";
            if($error_log) {
                array_push($error_log, $out);
                $out = implode(PHP_EOL, $error_log);
            }
        }

        //header('Content-Description: File Transfer');
        //header('Content-Type: application/octet-stream');
        //header('Content-Transfer-Encoding: binary');


        $content_len = strlen($out);
        if(!($content_len>0)) {$content_len = 0;}

        if($save_to_file){
            if($content_len > 0){ // save csv/error log to file
                $content_len = fileSave($out, $temp_name);
            }
            if($has_error){
                $content_len *= -1;
            }
            return $content_len;
        }

        $content_len = $content_len+3;

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.rawurlencode($csv_filename));
        header(CONTENT_LENGTH . $content_len);
        echo "\xEF\xBB\xBF";// Byte Order Mark
        exit($out);

    }else{

        $zipname = $temp_name.'_'.date("YmdHis").'.zip';
        $destination = tempnam(self::$system->getSysDir(DIR_SCRATCH), "zip");

        $zip = new \ZipArchive();
        if (!$zip->open($destination, \ZipArchive::OVERWRITE)) {
            array_push($error_log, "Cannot create zip $destination");
        }else{
            $is_first = true;

            foreach($streams as $rty_ID => $fd){

                if($fd==null){
                    array_push($error_log, "Stream for record type $rty_ID is not defined");
                }else{
                    // return to the start of the stream
                    rewind($fd);

                    if($is_first || (is_array($headers[$rty_ID]) && count($headers[$rty_ID])>1)){
                        $is_first = false;

                        $content = stream_get_contents($fd);

                        if($content===false || strlen($content)==0){
                            array_push($error_log, "Stream for record type $rty_ID is empty");
                        }else{
                            // add the in-memory file to the archive, giving a name
                            $rty_Name = mb_ereg_replace('\s', '_', self::$defRecTypes['names'][$rty_ID]);
                            $zip->addFromString('rectype-'.$rty_ID.'-'.$rty_Name.'.csv',  $content);
                        }

                    }
                    //close the file
                    fclose($fd);
                }
            }

            if(!isEmptyArray($error_log)){
                $zip->addFromString('log.txt', implode(PHP_EOL, $error_log) );
            }

            // close the archive
            $zip->close();
        }

        if(@file_exists($destination)>0){

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename='.$zipname);
            header(CONTENT_LENGTH . filesize($destination));
            readfile($destination);

            // remove the zip archive
            unlink($destination);

        }else{
            array_push($error_log, "Zip archive ".$destination." doesn't exist");

            $out = implode(PHP_EOL, $error_log);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=log.txt');
            header(CONTENT_LENGTH . strlen($out));
            exit($out);

        }

    }

}

/**
 * Group by columns for exported CSV rows.
 *
 * @param array $rows The CSV row data.
 * @param array $groupColIndices The indices of the group by columns.
 * @param array $sumColIndices The indices of the columns applied with SUM private static function.
 * @param array $countColIndices The indices of the columns applied with COUNT private static function.
 *
 * @return array The grouped CSV rows.
 */
    /**
     * Groups CSV rows based on specified column indices and calculates sums and counts.
     *
     * Iterates through rows. If a row matches an existing group (based on values in `$groupColIndices`),
     * it updates sum and count columns for that group. Otherwise, it starts a new group.
     * Sum and count columns are initialized appropriately for new groups.
     *
     * @param array $rows The array of CSV rows to process.
     * @param array $groupColIndices Array of column indices to group by.
     * @param array $sumColIndices Array of column indices on which to perform a sum for each group.
     * @param array $countColIndices Array of column indices for which to count occurrences in each group (values are incremented).
     * @return array An array of new CSV rows, grouped and with calculated sums/counts.
     */
private static function groupCSVRows(array $rows, array $groupColIndices = [], array $sumColIndices = [], array $countColIndices = []) {
    if (!empty($groupColIndices)) {
        $groupedRows = [];
        foreach ($rows as $row) {
            $findRowIndex = -1;
            for ($i = 0; $i < count($groupedRows); $i++) {
                $isMatch = true;
                for ($j = 0; $j < count($groupColIndices); $j++) {
                    if ($groupedRows[$i][$groupColIndices[$j]] !== $row[$groupColIndices[$j]]) {
                        $isMatch = false;
                        break;
                    }
                }
                if ($isMatch) {
                    $findRowIndex = $i;
                    break;
                }
            }
            if ($findRowIndex >= 0) {
                for ($i = 0; $i < count($countColIndices); $i++) {
                    $groupedRows[$findRowIndex][$countColIndices[$i]] += 1;
                }
                for ($i = 0; $i < count($sumColIndices); $i++) {
                    $groupedRows[$findRowIndex][$sumColIndices[$i]] += self::valueToNumeric($row[$sumColIndices[$i]]);
                }
            } else {
                for ($i = 0; $i < count($countColIndices); $i++) {
                    $row[$countColIndices[$i]] = 1;
                }
                for ($i = 0; $i < count($sumColIndices); $i++) {
                    $row[$sumColIndices[$i]] = self::valueToNumeric($row[$sumColIndices[$i]]);
                }
                $groupedRows[] = $row;
            }
        }
        return $groupedRows;
    } else {
        return $rows;
    }
}

/**
     * Adds percentage column headers to an existing header array.
     *
     * For each column index specified in `$usePercentageColIndices`, this function
     * inserts a new header immediately after it. The new header is the original
     * header text suffixed with '(%)'.
     *
     * @param array $headers The original array of CSV headers.
     * @param array $usePercentageColIndices Array of column indices for which percentage columns will be added.
     * @return array The modified header array with added percentage headers.
     */
private static function usePercentageForCSVHeaders(array $headers, array $usePercentageColIndices = []) {
    if (!empty($usePercentageColIndices)) {
        $colIncrease = 0;
        for ($i = 0; $i < count($usePercentageColIndices); $i++) {
            $colIndex = $usePercentageColIndices[$i] + $colIncrease;
            if ($colIndex + 1 > count($headers) - 1) {
                $headers[] = $headers[$colIndex] . '(%)';
            } else {
                array_splice($headers, $colIndex + 1, 0, [$headers[$colIndex] . '(%)']);
            }
            $colIncrease++;
        }
    }
    return $headers;
}

/**
 * Calculate the percentage value of the specified columns in the CSV rows.
 *
 * @param array $rows The CSV row data.
 * @param array $usePercentageColIndices The indices of the columns to calculate the percentages.
 *
 * @return array The CSV rows with the percentage values calculated.
 */
    /**
     * Calculates percentage values for specified columns and inserts them as new columns.
     *
     * First, it calculates the total sum for each column specified in `$usePercentageColIndices`.
     * Then, for each row, it calculates the percentage of that row's value relative to the total sum
     * for each specified column. The percentage (multiplied by 100) is inserted as a new column
     * immediately after the original value column.
     *
     * @param array $rows The array of CSV rows. Each row is an array of values.
     * @param array $usePercentageColIndices Array of column indices for which to calculate percentages.
     * @return array The modified array of CSV rows, with new percentage columns added.
     */
private static function usePercentageForCSVRows(array $rows, array $usePercentageColIndices = []) {
    if (!empty($usePercentageColIndices)) {
        $colTotal = [];
        for ($i = 0; $i < count($rows); $i++) {
            for ($j = 0; $j < count($usePercentageColIndices); $j++) {
                $colIndex = $usePercentageColIndices[$j];
                if (!isset($colTotal[$colIndex])) {
                    $colTotal[$colIndex] = self::valueToNumeric($rows[$i][$colIndex]);
                } else {
                    $colTotal[$colIndex] += self::valueToNumeric($rows[$i][$colIndex]);
                }
            }
        }
        for ($i = 0; $i < count($rows); $i++) {
            $colIncrease = 0;
            for ($j = 0; $j < count($usePercentageColIndices); $j++) {
                $colIndex = $usePercentageColIndices[$j];
                if($colTotal[$colIndex]>0){
                    $percentage = round(self::valueToNumeric($rows[$i][$colIndex + $colIncrease]) / $colTotal[$colIndex], 4) * 100;
                }else{
                    $percentage = 0;
                }
                if ($colIndex + 1 > count($rows[$i]) - 1) {
                    $rows[$i][] = $percentage;
                } else {
                    array_splice($rows[$i], $colIndex + $colIncrease + 1, 0, [$percentage]);
                }
                $colIncrease++;
            }
        }
    }
    return $rows;
}

/**
 * Sort the CSV rows by specified columns and orders.
 *
 * @param array $rows The CSV row data.
 * @param array $sortByColIndices The indices of the columns to sort.
 * @param array $sortOrders The sort orders of the specified columns. Each element can either
 *   be 'asc' or 'des'.
 *
 * @return array The sorted CSV rows.
 */
    /**
     * Sorts an array of CSV rows based on specified columns and sort orders.
     *
     * Uses `usort` with a custom comparison function. The comparison function
     * iterates through the `$sortByColIndices`. For each specified column, it compares
     * the values in rows `$a` and `$b`. If values are numeric, a numeric comparison
     * is done; otherwise, `strcmp` is used. Sorting respects the corresponding
     * order in `$sortOrders` ('asc' or 'desc'). The sort is stable in the sense
     * that if primary sort keys are equal, it moves to the next specified sort key.
     *
     * @param array $rows The array of CSV rows to sort.
     * @param array $sortByColIndices An array of column indices to sort by, in order of precedence.
     * @param array $sortOrders An array of sort orders ('asc' or 'desc') corresponding to `$sortByColIndices`.
     * @return array The sorted array of CSV rows.
     */
private static function sortCSVRows(array $rows, array $sortByColIndices = [], array $sortOrders = []) {
    if (!empty($sortByColIndices)) {
        usort($rows, function($a, $b) use ($sortByColIndices, $sortOrders) {
            $result = 0;
            for ($i = 0; $i < count($sortByColIndices); $i++) {
                $sortByColIndex = $sortByColIndices[$i];
                $isAscending = true;
                if (isset($sortOrders[$i])) {
                    $isAscending = ($sortOrders[$i] === 'asc');
                }
                if (is_numeric($a[$sortByColIndex]) && is_numeric($b[$sortByColIndex])) {
                    if ($a[$sortByColIndex] == $b[$sortByColIndex]) {
                        $result = 0;
                    } else {
                        $result = $a[$sortByColIndex] < $b[$sortByColIndex] ? -1 : 1;
                        if (!$isAscending) {
                            $result = -$result;
                        }
                        break;
                    }
                } else {
                    $result = strcmp($a[$sortByColIndex], $b[$sortByColIndex]);
                    if ($result !== 0) {
                        if (!$isAscending) {
                            $result = -$result;
                        }
                        break;
                    }
                }
            }
            return $result;
        });
    }
    return $rows;
}

/**
 * Create the joint CSV data from multiple record types.
 *
 * @param array $csvData The original CSV data divided by record types.
 * @param array $columnInfo The column information of the original CSV data by
 *   record types.
 * @param int $mainRecordTypeID The ID of the root record type to export.
 * @param string $filedValueDelimiter The delimiter used for multi-value field.
 * @param bool $includeHeader Whether the header included in the CSV data.
 *
 * @return array The joint CSV data.
 */
    /**
     * Creates a single CSV table by joining data from multiple record types.
     *
     * This method takes CSV data that has been pre-processed and separated by record type
     * (`$csvData`) and merges it into a single array of rows. The merging is based on
     * following resource links defined in the main record type's data.
     *
     * It first builds lookup tables (`$csvRowLookups`) for non-main record types, keyed by record ID.
     * Then, it iterates through each row of the main record type and calls `createJointCSVRow`
     * to recursively build the full joined row.
     *
     * @param array $csvData An array where keys are record type IDs and values are arrays of CSV rows for that type.
     * @param array &$columnInfo Passed by reference. An array holding column information for each record type.
     *                           This will be updated by `createJointCSVRow` to reflect new column indices in the joined table.
     * @param int $mainRecordTypeID The ID of the primary record type from which joins originate.
     * @param string $filedValueDelimiter The delimiter used for multi-value fields (passed to `createJointCSVRow`).
     * @param bool $includeHeader Whether the input CSV data includes a header row (used for lookup construction).
     * @return array An array of rows representing the single, joined CSV table.
     */
private static function createJointCSVTables($csvData, &$columnInfo, $mainRecordTypeID, $filedValueDelimiter, $includeHeader = true) {
    $csvRows = $csvData[$mainRecordTypeID];

    // Create join table lookups.
    $csvRowLookups = [];
    foreach ($csvData as $recordTypeID => $rows) {
        if ($recordTypeID != $mainRecordTypeID) {
            if (!isset($csvRowLookups[$recordTypeID])) {
                $csvRowLookups[$recordTypeID] = [];
            }

            if ($includeHeader) {
                $csvRowLookups[$recordTypeID]['header'] = array_shift($rows); //$rows[0];
            }

            foreach ($rows as $row) {
                $csvRowLookups[$recordTypeID][$row[0]] = $row;
            }
        }
    }

    $jointRows = [];

    foreach ($csvRows as $row) {
        $jointRow = [];
        $recordTypeIDTrack = [];
        self::createJointCSVRow($jointRow, $row, $mainRecordTypeID, $columnInfo, $csvRowLookups, $recordTypeIDTrack, $filedValueDelimiter);
        $jointRows[] = $jointRow;
    }

    return $jointRows;
}

/**
 * Create a row for the joint CSV.
 *
 * @param array $jointRow Passed in reference. It will contain the data for the row
 *   after the private static function is finished.
 * @param array $row The row data from the original CSV data.
 * @param int $recordTypeID The record type ID of the original row data.
 * @param array $columnInfo The full array containing the original column information.
 *   It will contain the new column indices in the joint CSV after the private static function is finished.
 * @param array $csvRowLookups The lookup array for each record type keyed by record ID.
 * @param array $recordTypeIDTrack The array which keeps a track of the record type IDs have
 *   been joint.
 * @param string $filedValueDelimiter The delimiter used for multi-value field.
 * @param int $level The depth of the current record type.
 */
    /**
     * Recursively creates a single row for a joined CSV table.
     *
     * This method takes a row from a specific record type and expands it by appending
     * data from linked records. It iterates through the columns of the current row.
     * If a column represents a link to another record type (identified by ':' in `field_id`),
     * it looks up the linked record(s) in `$csvRowLookups` and recursively calls itself
     * to append that linked record's data.
     *
     * It updates `$colInfo['joint_column_index']` to store the new column index in the
     * flattened `$jointRow`.
     *
     * @param array &$jointRow Passed by reference. The array being built to represent the full joined row.
     * @param array $row The current row data from a specific record type.
     * @param int $recordTypeID The record type ID of `$row`.
     * @param array &$columnInfo Passed by reference. Contains column metadata; `joint_column_index` is updated here.
     * @param array $csvRowLookups Lookup table for finding rows of linked records.
     * @param array &$recordTypeIDTrack Passed by reference. Keeps track of record types already processed in the current join path to avoid infinite loops (though current usage seems to allow re-visiting, controlled by `$level` and specific conditions).
     * @param string $filedValueDelimiter Delimiter for multi-value fields, used by `findInCSVRowLookup`.
     * @param int $level Current recursion depth.
     */
private static function createJointCSVRow(&$jointRow, $row, $recordTypeID, &$columnInfo, $csvRowLookups, &$recordTypeIDTrack, $filedValueDelimiter, $level = 1) {
    $recordTypeIDTrack[] = (int) $recordTypeID;
    foreach ($columnInfo[$recordTypeID] as &$colInfo) {
        if (strpos($colInfo['field_id'], ':') === false) {
            if ($level === 1 || $colInfo['index'] !== 0) {
                $jointRow[] = $row[$colInfo['index']];
                $colInfo['joint_column_index'] = count($jointRow) - 1;
            }
        } else {
            $fieldIDParts = explode(':', $colInfo['field_id']);
            $targetRecordTypeID = $fieldIDParts[count($fieldIDParts) - 1];
            if (false && in_array((int) $targetRecordTypeID, $recordTypeIDTrack) && $colInfo['type'] !== 'resource_title') {
                $jointRow[] = $row[$colInfo['index']];
                $colInfo['joint_column_index'] = count($jointRow) - 1;
            } else {
                if ($colInfo['type'] === 'value') {
                    $jointRow[] = $row[$colInfo['index']];
                    $colInfo['joint_column_index'] = count($jointRow) - 1;
                    $targetRecordID = $row[$colInfo['index']];
                    if (!empty($targetRecordID) && !is_numeric($targetRecordID) && strpos($targetRecordID, $filedValueDelimiter) === false) {
                        $targetRecordID = 'header';
                    }
                    if (empty($targetRecordID)) {
                        $jointRow = array_merge($jointRow, self::generateEmptyCellsForTargetRecordType($targetRecordTypeID, $csvRowLookups));
                    } else {
                        $targetRow = self::findInCSVRowLookup($targetRecordID, $targetRecordTypeID, $csvRowLookups, $filedValueDelimiter);
                        if ($targetRow) {
                            self::createJointCSVRow($jointRow, $targetRow, $targetRecordTypeID, $columnInfo, $csvRowLookups, $recordTypeIDTrack, $filedValueDelimiter, $level + 1);
                        } else {
                            $jointRow = array_merge($jointRow, self::generateEmptyCellsForTargetRecordType($targetRecordTypeID, $csvRowLookups));
                        }
                    }
                }
            }
        }
    }
}

/**
 * Lookup the row data based on the record ID.
 *
 * @param string $recordIDLiteral The record ID. It could be multiple IDs separated
 *   by the delimiter.
 * @param string $targetRecordTypeID The ID of the target record type.
 * @param string $csvRowLookups The lookup data.
 * @param string $filedValueDelimiter The delimiter used for multiple IDs.
 *
 * @return array|bool False if no records found, otherwise an array representing the (potentially merged) row.
 */
    /**
     * Looks up and potentially merges row data for one or more record IDs from the CSV lookup table.
     *
     * If `$recordIDLiteral` contains multiple record IDs (separated by `$filedValueDelimiter`),
     * this function finds each corresponding row in `$csvRowLookups[$targetRecordTypeID]`.
     * It then "merges" these rows by concatenating the values in each column with the
     * `$filedValueDelimiter`. This is used when a single field in the parent row links to
     * multiple records, and these multiple linked records need to be represented within
     * the cells of the (single) joined row being constructed for the parent.
     *
     * @param string $recordIDLiteral A string containing one or more record IDs, possibly separated by `$filedValueDelimiter`.
     * @param string|int $targetRecordTypeID The ID of the target record type to look up in `$csvRowLookups`.
     * @param array $csvRowLookups The lookup data, where keys are record type IDs, and values are arrays
     *                             of rows (keyed by record ID) for that type.
     * @param string $filedValueDelimiter The delimiter used for separating multiple record IDs in `$recordIDLiteral`
     *                                    and for joining values in the merged row.
     * @return array|false An array representing the found (and possibly merged) row, or false if no record IDs produce a match.
     */
private static function findInCSVRowLookup($recordIDLiteral, $targetRecordTypeID, $csvRowLookups, $filedValueDelimiter) {
    $recordIDs = explode($filedValueDelimiter, $recordIDLiteral);
    $lookupRow = [];
    foreach ($recordIDs as $recordID) {
        if (isset($csvRowLookups[$targetRecordTypeID][$recordID])) {
            foreach ($csvRowLookups[$targetRecordTypeID][$recordID] as $index => $item) {
                if (!isset($lookupRow[$index])) {
                    $lookupRow[$index] = [];
                }
                $lookupRow[$index][] = $item;
            }
        }
    }
    if (empty($lookupRow)) {
        return false;
    } else {
        $concatRow = [];
        foreach ($lookupRow as $values) {
            $concatRow[] = implode($filedValueDelimiter, $values);
        }
        return $concatRow;
    }
}

/**
 * Generate an array of empty strings for a target record type.
 *
 * This private static function is used to generate empty cells in the joint CSV when the
 * reference value is empty, or the reference value can be found from the
 * lookup data.
 *
 * @param string $targetRecordTypeID The ID of the target record type.
 * @param array $csvRowLookups The lookup data.
 *
 * @return array An array of empty strings.
 */
    /**
     * Generates an array of empty strings, with the count matching the number of
     * columns (minus one, for the ID column) of a given target record type.
     *
     * This is used by `createJointCSVRow` to insert appropriate padding when a linked
     * record cannot be found or a link field is empty, ensuring the joined CSV row
     * maintains consistent column structure.
     *
     * @param string|int $targetRecordTypeID The ID of the target record type.
     * @param array $csvRowLookups The lookup data, used here only to determine the number of columns
     *                             for the `$targetRecordTypeID` by looking at its first available row.
     * @return array An array of empty strings.
     */
private static function generateEmptyCellsForTargetRecordType($targetRecordTypeID, $csvRowLookups) {
    $cells = [];
    if (!empty($csvRowLookups[$targetRecordTypeID])) {
        $length = count(reset($csvRowLookups[$targetRecordTypeID])) - 1;
        for ($i = 0; $i < $length; $i++) {
            $cells[] = "";
        }
    }
    return $cells;
}

/**
 * Change the column index array after the CSV data is joint.
 *
 * This private static function will change the column indices of advanced option to the
 * new indices in the joint CSV table.
 *
 * @param array $columnIndices The original column indices of advanced option.
 * @param array $mainRecordTypeID The ID of the root record type to export.
 * @param array $columnInfo The column information. After the csv data is joined,
 *   this array contains the new column indices in the joint CSV table.
 *
 * @return array The new column indices, structured similarly to the input but reflecting indices in the joined table.
 */
    /**
     * Adjusts column indices used for advanced options (grouping, summing, etc.)
     * after CSV tables have been joined.
     *
     * When tables are joined, the column indices from original, separate record type CSVs
     * change. This function maps the original column indices (stored in `$columnIndices`
     * and keyed by record type ID) to their new indices in the final joined table
     * (which are stored in `$columnInfo[$recordTypeID][$columnIndex]['joint_column_index']`).
     *
     * @param array $columnIndices Original column indices for advanced options, keyed by record type ID,
     *                             e.g., `[rtID1 => [colIdx1, colIdx2], rtID2 => [colIdx3]]`.
     * @param int|string $mainRecordTypeID The ID of the main record type in the joined table.
     *                                     All adjusted indices will be under this key in the output.
     * @param array $columnInfo Column metadata, which should contain the `joint_column_index` mapping
     *                          after `createJointCSVRow` has run.
     * @return array The adjusted column indices, now all under the `$mainRecordTypeID` key,
     *               e.g., `[mainRtID => [newColIdxA, newColIdxB, newColIdxC]]`.
     */
private static function changeAdvancedOptionColumnIndex($columnIndices, $mainRecordTypeID, $columnInfo) {
    if (empty($columnIndices)) {
        return $columnIndices;
    }
    $newColumnIndices = [
        $mainRecordTypeID => [],
    ];
    foreach ($columnIndices as $recordTypeID => $colIndices) {
        foreach ($colIndices as $columnIndex) {
            if (isset($columnInfo[$recordTypeID][$columnIndex]['joint_column_index'])) {
                $newColumnIndices[$mainRecordTypeID][] = $columnInfo[$recordTypeID][$columnIndex]['joint_column_index'];
            }
        }
    }
    return $newColumnIndices;
}

/**
 * Change the sorting orders to fit the joint CSV table.
 *
 * @param array $sortOrders The original sorting orders.
 * @param array $mainRecordTypeID The ID of the root record type to export.
 *
 * @return array The new sorting orders, structured similarly to the input but consolidated under the main record type ID.
 */
    /**
     * Consolidates sort orders for different record types into a single array
     * under the main record type ID, after CSV tables have been joined.
     *
     * This function is similar to `changeAdvancedOptionColumnIndex` but for sort orders.
     * It takes sort orders originally defined per record type and flattens them into
     * a single list associated with the `$mainRecordTypeID`, corresponding to the
     * new, consolidated list of sort column indices.
     *
     * @param array $sortOrders Original sort orders, keyed by record type ID,
     *                          e.g., `[rtID1 => ['asc', 'desc'], rtID2 => ['asc']]`.
     * @param int|string $mainRecordTypeID The ID of the main record type in the joined table.
     * @return array The adjusted sort orders, now all under the `$mainRecordTypeID` key,
     *               e.g., `[mainRtID => ['asc', 'desc', 'asc']]`.
     */
private static function changeSortOrders($sortOrders, $mainRecordTypeID) {
    if (empty($sortOrders)) {
        return $sortOrders;
    }
    $newSortOrders = [
        $mainRecordTypeID => [],
    ];
    foreach ($sortOrders as $recordTypeID => $orders) {
        foreach ($orders as $order) {
            $newSortOrders[$mainRecordTypeID][] = $order;
        }
    }
    return $newSortOrders;
}

/**
 * Convert a value to numeric.
 *
 * @param $value
 *
 * @return int|float The numeric value.
 */
    /**
     * Converts a value to a numeric type (integer or float).
     *
     * If the input value is not already numeric, it's converted to an integer using `intval()`.
     * This is a simple utility primarily used before performing arithmetic operations
     * (like summing) on CSV cell values.
     *
     * @param mixed $value The value to convert.
     * @return int|float The numeric representation of the value.
     */
private static function valueToNumeric($value) {
    if (!is_numeric($value)) {
        $value = intval($value);
    }
    return $value;
}


} //end class
?>
