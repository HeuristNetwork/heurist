<?php
/**
* ReportExecute.php - Class ReportExecute
*
* Executes Smarty templates, handles the output of reports in various formats
*
* @project     Heurist academic knowledge management system
* @package Report
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.5
*/
namespace hserv\report;

use hserv\report\ReportRecord;
use hserv\utilities\USanitize;
use hserv\utilities\Temporal;

require_once 'smartyInit.php';
require_once dirname(__FILE__).'/../records/search/recordSearch.php';
require_once dirname(__FILE__).'/../../vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';

/**
 * HTML tag string for closing head element.
 * @var string
 */
define('HEAD_E','</head>');

/**
 * Class ReportExecute
 *
 * This class is responsible for executing reports defined by Smarty templates.
 * It fetches data based on Heurist query parameters, processes it through Smarty
 * using a specified template, and handles various output modes such as displaying
 * in the browser, saving to a file, or providing a downloadable file.
 * It also manages different publishing modes that control output limits and behavior,
 * JavaScript inclusion, custom CSS, and error/debug reporting levels.
 */
class ReportExecute
{
    /** @var \Smarty\Smarty Instance of the Smarty templating engine. */
    private $smarty;

    /** @var string|null Basename of the Smarty template file (e.g., "my_report.tpl"). */
    private $templateFile = null;

    /** @var string|null Sanitized name for the output file if the report is saved or downloaded. */
    private $outputfile;
    /** @var string Output format of the report (e.g., 'html', 'js', 'txt', 'xml', 'json', 'css'). Default 'html'. */
    private $outputmode;
    /** @var bool If true, suppresses direct browser output; used when the primary action is saving to a file. */
    private $isVoid = false;

    /**
     * @var int Defines the publishing mode and behavior of the report execution.
     *          - 0: UI preview (e.g., in template editor), applies limits, HTML output.
     *          - 1: Saves to file in `generated-reports/` and shows an info page (user-generated report).
     *          - 2: Forces download of the report with a given output name; no server-side file save.
     *          - 3: Saves to file and also outputs to browser (no UI limits).
     *          - 4: Calculation field mode (specialized output, usually direct value).
     */
    private $publishmode;

    /** @var string|int|null Session ID for tracking progress of report generation, especially for long reports. */
    private $smartySessionId;
    /** @var int Counter for records processed during Smarty loop, used for progress updates. */
    private $executionCounter;
    /** @var int Total number of records to be processed, used for progress calculation. */
    private $executionCounterTotal;

    /** @var bool Whether JavaScript output/inclusion is allowed, based on system settings. */
    private $isJsAllowed;
    /** @var int|null Record ID that might contain custom CSS definitions (DT_CMS_CSS) to be included in the report. */
    private $recordWithCustomCSS;
    /** @var bool If true, generates HTML output as a snippet (without `<html>`, `<head>`, `<body>` tags), suitable for embedding. */
    private $isHeadless;

    /** @var int The maximum number of records to fetch and process for the report. */
    private $limit;
    /** @var int Debugging/error reporting level for Smarty (0: off, 1: notices, 2: all except strict/notice, 3: Smarty debug console). */
    private $replevel;

    /** @var \hserv\System The Heurist system object, providing access to database, settings, user info etc. */
    private $system;
    /** @var array Parameters for the report execution, typically from `$_REQUEST` or similar source. Contains query, template info, etc. */
    private $params;

    /** @var string|null Message about report truncation if the number of records exceeds the display limit in UI preview mode. */
    private $messageAboutTruncation;
    /** @var string|null Stores an error message if one occurs during report execution. */
    private $messageError;

    /**
     * Constructor for ReportExecute.
     *
     * Initializes the system object and sets up report parameters using `setParameters()`.
     *
     * @param \hserv\System $system The Heurist system object.
     * @param array|null $params (Optional) Parameters for the report execution, typically from `$_REQUEST`.
     *                           Expected keys include:
     *                           - 'template' or 'template_body': Template file name or template string.
     *                           - 'replevel': Error reporting level (0-3).
     *                           - 'output': Desired output filename (if saving/downloading).
     *                           - 'mode': Output format ('html', 'js', 'xml', etc.).
     *                           - 'publish': Publishing mode (0-4).
     *                           - 'cssid': Record ID for custom CSS.
     *                           - 'session': Progress session ID.
     *                           - 'snippet': Boolean for headless HTML output.
     *                           - 'void': Boolean to suppress browser output when saving.
     *                           - Plus Heurist query parameters ('q', 'w', 'limit', 'offset', etc.).
     */
    public function __construct($system, $params=null)
    {
        $this->system = $system;

        if ($params!=null) {
            // Initialize properties from parameters or set defaults
            $this->setParameters($params);
        }
    }

    /**
     * Executes the report generation process.
     *
     * This is the main public method to run a report. It involves:
     * 1. Validating system and parameter initialization.
     * 2. Initializing the Smarty templating engine (`initSmarty`).
     * 3. Loading the Smarty template content (`loadTemplateContent`).
     * 4. Fetching the record IDs based on the query parameters (`fetchRecordIDs`).
     * 5. Handling cases where the result set is empty (`handleEmptyResultSet`).
     * 6. If records are found, processing them through the Smarty template (`executeTemplate`).
     *
     * @return bool True if the report execution completes successfully (data processing and templating),
     *              false if a critical error occurs at any stage.
     */
    public function execute()
    {
        $result = false;

        // Check if the system is initialized
        if (!isset($this->system) || !$this->system->isInited()) {
            $this->outputError();
        } elseif (!isset($this->params)) {
            // Check if parameters are defined
            $this->outputError('Parameters for smarty executions are not defined');
        } elseif (!$this->initSmarty()) {
            // Initialize Smarty if necessary
            // Do nothing as the error was already handled
        } else {
            set_time_limit(0); // No script execution time limit

            // Load template content
            $content = $this->loadTemplateContent();
            
            if ($content) {
                
                // Fetch record IDs based on search query
                $query_result = $this->fetchRecordIDs();
                $result = true;

                // Handle empty result set
                if ($this->handleEmptyResultSet($query_result)) {
                    // Process the fetched records and execute the template with Smarty
                    $result = $this->executeTemplate($query_result, $content);
                }
            }
        }

        return $result;
    }



    /**
     * Initializes properties from parameters or sets defaults.
     * Handles the output mode and sets appropriate flags.
     * Sets the search limit based on publishing mode or user preferences.
     *
     * @param array|null $params The parameters array to set.
     */
    /**
     * Sets and sanitizes various operational parameters for the report execution.
     *
     * This method initializes class properties based on the input `$params` array,
     * applying defaults and sanitizing values for:
     * - `publishmode`: Clamped between 0 and 4.
     * - `outputmode`: Validated against a list of allowed extensions (html, js, txt, etc.), defaults to 'html'.
     * - `recordWithCustomCSS`, `smartySessionId`, `isHeadless`, `isVoid`, `isJsAllowed`, `replevel`.
     *
     * @param array|null $params An associative array of parameters. If null, uses previously set `$this->params`.
     */
    public function setParameters($params=null)
    {

        if($params!=null){
            $this->params = $params;
        }

        $this->publishmode = isset($params['publish']) ? intval($params['publish']) : 1; //by default full recordset execution

        $this->publishmode = max(min($this->publishmode,4),0);

        $this->recordWithCustomCSS = isset($params['cssid']) ? intval($params['cssid']) : null;

        $this->smartySessionId = isset($params['session']) ? $params['session'] : null;


        $this->outputmode = isset($params['mode']) ? preg_replace('/[^a-z]/', "", $params["mode"]) : 'html';
        $allowed_exts = array('html','js','txt','text','csv','xml','json','css');
        $idx = array_search($this->outputmode, $allowed_exts);
        $this->outputmode = ($idx>=0)?$allowed_exts[intval($idx)]:'html';

        if ($this->outputmode === 'text') {
            $this->outputmode = 'txt';
        }

        $this->isHeadless = isset($params['snippet']) && $params['snippet'] == 1; //html output with header or not

        $this->isVoid = isset($params['void']) && $params['void'];  //fetch into file

        $this->isJsAllowed = $this->system->settings->isJavaScriptAllowed();

        $this->replevel = 0;
        $this->testForWidgetTemplate = false;
        if (@$params['template_body']){

            if ($this->publishmode != 4) { //4 - calc field snippet
                $this->publishmode = 0;
            }
            $this->outputmode = 'html'; //always html in test or snippet mode

            $this->replevel = isset($params['replevel']) ? intval($params['replevel']) : 0;
            
            $this->testForWidgetTemplate = isset($params['testwidget']);
        }
    }

    /**
     * Prepares and sanitizes the output file name.
     *
     * @return string The sanitized output file name.
     */
    /**
     * Prepares and sanitizes the output file name for saving or downloading.
     *
     * It uses the 'output' parameter if provided, otherwise falls back to the
     * template file name, or 'heurist_output' as a last resort.
     * The filename is sanitized using `USanitize::sanitizeFileName()`, and the
     * correct extension based on `$this->outputmode` is appended.
     *
     * @return string The sanitized and extension-appended output file name.
     */
    private function prepareOutputFile(){
        $this->outputfile = $this->params["output"] ?? $this->templateFile ?? 'heurist_output';
        $path_parts = pathinfo($this->outputfile);
        $this->outputfile = USanitize::sanitizeFileName($path_parts['filename']) . '.' . $this->outputmode;
        return $this->outputfile;
    }


    /**
     * Fetch record IDs based on the provided query parameters.
     *
     * @return array|null The query result as an array (expected to contain 'records' and 'reccount'),
     *                    or null if fetching fails (e.g., `recordSearch` returns an error).
     */
    /**
     * Fetches the set of record IDs to be processed in the report.
     *
     * It first calls `setLimit()` to determine the maximum number of records.
     * Then, if `params['recordset']` is provided, it uses that predefined set of records
     * (via `handleRecordset`). Otherwise, it performs a database search using `searchRecords`
     * based on the query parameters in `$this->params`.
     *
     * @return array|null An array containing 'records' (an array of record IDs) and 'reccount' (total count),
     *                    or null if an error occurred during search.
     */
    private function fetchRecordIDs()
    {
        $this->setLimit();
        $this->messageAboutTruncation = null;

        if (isset($this->params['recordset'])) {
            // Handle predefined recordset
            $qresult = $this->handleRecordset($this->params['recordset']);
        } else {
            // Perform a query/search for the recordset
            $qresult = $this->searchRecords();
        }

        return $qresult;
    }

    /**
     * Sets the record limit for the report query.
     *
     * If `params["limit"]` is not already set:
     * - If `publishmode` is 0 (UI preview), it uses the 'smarty-output-limit' user preference,
     *   defaulting to 50 if the preference is not set or invalid.
     * - For other `publishmode` values, it defaults to `PHP_INT_MAX` (effectively no limit).
     * The determined limit is stored in `$this->limit` and also updates `$this->params["limit"]`.
     */
    private function setLimit()
    {
        if (!isset($this->params["limit"])) {
            if ($this->publishmode == 0) {
                $limit_for_interface = intval($this->system->userGetPreference('smarty-output-limit'));
                if (!$limit_for_interface || $limit_for_interface < 1) {
                    $limit_for_interface = 50; // Default limit
                }
                $this->params["limit"] = $limit_for_interface;
            } else {
                $this->params["limit"] = PHP_INT_MAX;
            }
        }
        $this->limit = intval($this->params["limit"]);
    }

    /**
     * Processes a predefined set of record IDs.
     *
     * If a `recordset` (array or JSON string of record IDs) is passed in parameters,
     * this method uses it instead of querying the database.
     * For UI previews (`publishmode == 0`), it truncates the recordset to `$this->limit`
     * and sets `$this->messageAboutTruncation` if truncation occurs.
     *
     * @param array|string $recordset An array of record IDs or a JSON string representing such an array.
     * @return array An array with 'records' (array of record IDs, potentially truncated)
     *               and 'reccount' (original count of records in the provided set).
     */
    private function handleRecordset($recordset)
    {
        if (is_array($recordset)) {
            $qresult = $recordset;
        } else {
            $qresult = json_decode($recordset, true);
        }

        if ($this->publishmode == 0 && $qresult && isset($qresult['recIDs'])) {
            $recIDs = prepareIds($qresult['recIDs']);

            if($this->limit < count($recIDs) || $this->limit < $qresult['recordCount'] ){
                $this->messageAboutTruncation = '<div><b>Report preview has been truncated to '.intval($this->limit).' records.<br>'
                    .'Please use publish or print to get full set of records.<br Or increase the limit in preferences</b></div>';
            }

            if ($this->limit < count($recIDs)) {
                $qresult = [
                    'records' => array_slice($recIDs, 0, $this->limit),
                    'reccount' => count($recIDs)
                ];

            } else {
                $qresult = [
                    'records' => $recIDs,
                    'reccount' => count($recIDs)
                ];
            }
        }

        return $qresult;
    }

    /**
     * Performs a record search to get the list of record IDs for the report.
     *
     * It sets `params['detail'] = 'ids'` to ensure `recordSearch` only returns IDs.
     * If the search fails or returns an error status, `$this->params['emptysetmessage']`
     * is populated with the error.
     *
     * @return array|null The 'data' part of the `recordSearch` result (containing 'records' and 'reccount')
     *                    on success, or `null` on failure.
     */
    private function searchRecords()
    {
        $this->params['detail'] = 'ids';
        $qresult = recordSearch($this->system, $this->params);

        if (isset($qresult['status']) && $qresult['status'] == HEURIST_OK) {
            return $qresult['data'];
        } else {
            $msg = $this->system->getErrorMsg();
            if($msg==''){
                $msg = 'Undefined error on query executtion';
            }
            $this->params['emptysetmessage'] = $msg;
            return null;
        }
    }

    /**
     * Handles empty result sets and outputs an appropriate error message or info.
     *
     * @param array|null $qresult The query result, expected to have 'records' and 'reccount'.
     * @return bool True if the result set is not empty and valid, false otherwise (and outputs an error/message).
     */
    /**
     * Checks if the fetched record set is empty and handles output accordingly.
     *
     * If the record set (`$qresult['records']`) is empty or `reccount` is not positive:
     * - For calculation fields (`publishmode == 4`), it echoes a sanitized empty message or an empty string.
     * - For other modes, it calls `outputError()` with a relevant message (either from
     *   `$params['emptysetmessage']` or a default one based on `publishmode`).
     *
     * @param array|null $qresult The query result array.
     * @return bool True if the result set is valid and non-empty, false otherwise.
     */
    private function handleEmptyResultSet($qresult)
    {
        $emptysetmessage = $this->params['emptysetmessage'] ?? null;

        if($emptysetmessage=='def'){
            $emptysetmessage = null;
        }

        if (isset($qresult['records']) && intval(@$qresult['reccount']) > 0) {
            return true;
        }

        if ($this->publishmode == 4) {
            //for calculation field
            echo USanitize::sanitizeString($emptysetmessage ?? '');
        } else {
            $error = $emptysetmessage ?? ($this->publishmode > 0
                    ? 'Note: There are no records in this view. The URL will only show records to which the viewer has access. Unless you are logged in to the database, you can only see records which are marked as Public visibility'
                    : 'Search records to see template output');
            $this->outputError($error);
        }
        return false;

    }

    /**
     * Loads the template content from a file or from a provided template body.
     *
     * @return string|false The loaded template content as a string, or `false` on failure (e.g., template empty).
     *                      Errors are set using `outputError()`.
     */
    /**
     * Loads the Smarty template content, either from a specified file or directly from parameters.
     *
     * It determines the template source:
     * - If `params['template']` is set, it uses `loadTemplateFile()` to load from a .tpl file.
     * - Otherwise, it uses `params['template_body']` as the direct template content.
     *
     * It also finalizes the `$this->outputfile` name and path if output is to a file.
     * If the content is empty after loading, it sets an error.
     *
     * @return string|false The template content, or false if loading fails or content is empty.
     */
    private function loadTemplateContent()
    {
        $templateFile = isset($this->params['template']) ? $this->params['template'] : null;
        $template_body = isset($this->params['template_body']) ? $this->params['template_body'] : null;

        if ($templateFile) {
            $content = $this->loadTemplateFile($templateFile);
        } else {
            $content = $template_body;
        }


        if(!isset($this->params["output"]) && $this->publishmode != 2){
            $this->outputfile = null;
            if($this->publishmode==1 ){
                //if output is not defined - output to browser by default
                $this->publishmode = 3;
            }
            $this->outputfile = null;
        }else{
            $this->outputfile = $this->prepareOutputFile();
        }

        if($content!==false && ($content==null || strlen(trim($content))==0)){
            $this->outputError('Template content is empty');
            return false;
        }

        return $content;
    }

    /**
     * Loads template content from a specified .tpl file.
     *
     * Ensures the filename ends with ".tpl" and constructs the full path using
     * the system's Smarty templates directory.
     *
     * @param string $templateFile The basename of the template file.
     * @return string|false The content of the template file, or `false` if the file doesn't exist.
     *                      Sets an error via `outputError()` on failure.
     */
    private function loadTemplateFile($templateFile)
    {
        if (substr($templateFile, -4) !== ".tpl") {
            $templateFile .= ".tpl";
        }
        
        if(strpos($templateFile,'def/')===0){
            $template_path = HEURIST_DIR.'hclient/widgets/HRecordList/'. basename($templateFile);
            $this->templateFile = null;
        }else{
            $templateFile = basename($templateFile);
            $template_path = $this->system->getSysDir('smarty-templates') . $templateFile;    
            $this->templateFile = $templateFile;
        }
        
        if (!file_exists($template_path)) {
            $error = 'Template file ' . htmlspecialchars($templateFile) . ' does not exist';
            $this->outputError($error);
            return false;
        }

        
        return file_get_contents($template_path);
    }

    /**
     * Initializes the Smarty engine if it is not already initialized.
     *
     * @param bool $force_init If true, forces re-initialization even if Smarty is already initialized.
     * @return bool True if Smarty is successfully initialized (or was already), false on error.
     *              Errors are set via `outputError()`.
     */
    /**
     * Initializes the Smarty templating engine.
     *
     * If Smarty is not already initialized (or if `$force_init` is true), this method
     * calls the global `smartyInit()` function to get a Smarty instance.
     * It then registers Heurist-specific Smarty plugins/functions:
     * - `progressCallback`: For updating progress during template loops.
     * - `out`: For `printLabelValuePair`.
     * - `wrap`: For `printProcessedValue`.
     *
     * @param bool $force_init If true, forces re-initialization.
     * @return bool True on successful initialization, false if `smartyInit()` fails.
     */
     public function initSmarty($force_init=false)
    {
        if (!$force_init && isset($this->smarty)) {
            return true; //already inited
        }

        $errorMsg = '';

        try{
            $this->smarty = smartyInit($this->system);
        } catch (\Exception $e) {
            $errorMsg  = $e->getMessage();
        }


        if (!isset($this->smarty) || $this->smarty === null) {
            $this->outputError('Cannot init Smarty report engine. '.$errorMsg);
            return false;
        }

        $this->smarty->registerPlugin(\Smarty\Smarty::PLUGIN_FUNCTION, 'progressCallback', [$this, 'progressCallback']);
        $this->smarty->registerPlugin(\Smarty\Smarty::PLUGIN_FUNCTION, 'out', [$this, 'printLabelValuePair']);
        $this->smarty->registerPlugin(\Smarty\Smarty::PLUGIN_FUNCTION, 'wrap', [$this, 'printProcessedValue']);

        return true;
    }

    /**
     * Executes the Smarty template with the provided records and template content.
     *
     * @param array $qresult The result set containing records.
     * @param string $content The Smarty template string content.
     * @return bool True if template execution and output handling complete without critical errors, false otherwise.
     */
    /**
     * Sets up Smarty variables and plugins, then initiates template processing.
     *
     * Assigns the main record set (`$results`) and individual records (via `ReportRecord` instance)
     * to Smarty variables. It also registers custom Smarty modifiers for accessing file data and field labels.
     * Finally, it calls `executeTemplateContinue()` to perform the actual template fetching and output.
     *
     * @param array $qresult The query result, containing 'records' (an array of record IDs) and 'reccount'.
     * @param string $content The template string to be processed by Smarty.
     * @return bool Returns the result of `executeTemplateContinue()`.
     */
    public function executeTemplate($qresult, $content)
    {
        $results = $qresult["records"];  //reocrd ids
        $this->executionCounterTotal = count($results);
        $heuristRec = new ReportRecord($this->system);

        if (method_exists($this->smarty, 'assignByRef')) {
            $this->smarty->assignByRef('heurist', $heuristRec); //deprecated
        } else {
            $this->smarty->assign('heurist', $heuristRec);
        }
        
        if($this->publishmode == 4){
            //assign the only record for calculation field
            $this->smarty->assign('r', $heuristRec->getRecord($results[0]));
        }else{
            $this->smarty->assign('results', $results);    
        }

        

        $facet_value = isset($this->params['facet_val']) ? htmlspecialchars($this->params['facet_val']) : null;
        if (!empty($facet_value)) {
            $this->smarty->assign('selected_term', $facet_value);
        }

        // Register Smarty plugins and modifiers
        try {
            $this->smarty->registerPlugin('modifier', 'file_data', [$heuristRec, 'getFileField']);
        } catch (\Exception $e) {
            if (strpos($e, 'already registered') === false) {
                $this->outputError('Cannot register smarty plugin. '.$e->getMessage());
                return false;
            }
        }

        try {
            $this->smarty->registerPlugin('modifier', 'label', [$heuristRec, 'getFieldLabel']);
        } catch (\Exception $e) {
            if (strpos($e, 'already registered') === false) {
                $this->outputError('Cannot register smarty plugin. '.$e->getMessage());
                return false;
            }
        }
        
        // Handle activity logging if required
        //if (!$this->isHeadless && !isset($this->params['template_body']) && !$this->is_fetch) {
        //    log_smarty_activity($this->system, $results);
        //}

        // Execute the template and handle output filtering
        return $this->executeTemplateContinue($content, $results);
    }

    /**
     * Continues the template execution by processing output and handling filters.
     *
     * @param string $content The template content.
     * @param array $results An array of record IDs to be processed by the template.
     * @return bool True if the template fetches and is handled successfully, false on Smarty exception.
     */
    /**
     * Continues template execution: sets up filters, fetches, and handles output.
     *
     * - If `template_body` was used, saves it to a temporary file via `saveTemporaryTemplate()`.
     * - Registers Smarty pre-filter `translateTerms` and post-filter `addProgressCallback`.
     * - Calls `smarty->fetch()` to process the template.
     * - Passes the fetched output to `handleTemplateOutput()`.
     * - Manages progress session updates and cleanup.
     * - Displays truncation messages if applicable.
     * - Deletes any temporary template file.
     *
     * @param string $content The template string (used only if a temporary template needs to be created from `template_body`).
     *                        If `$this->templateFile` is already set, this parameter's value for content isn't directly used for fetch.
     * @param array $results The array of record IDs.
     * @return bool True on successful completion, false if a Smarty exception occurs.
     */
    private function executeTemplateContinue($content, $results)
    {
        $result = true;
        $temp_templateFile = null;

        $this->setupErrorReporting($this->replevel);

        if ($this->templateFile==null){  //execution from $this->params['template_body']

                $temp_templateFile = $this->saveTemporaryTemplate($content);

                if($this->publishmode == 4){
                    try{
                        $output = $this->smarty->fetch($temp_templateFile);
                    } catch (\Exception $e) {
                        $output = 'Exception on calculation field execution (if you get this message, please send a bug report - we are trying to track the problem): '.$e->getMessage();
                    }
                    fileDelete($this->smarty->getTemplateDir().$temp_templateFile);
                    echo $output;
                    return true;
                }

                $this->templateFile = $temp_templateFile;
        }


        $this->smarty->registerFilter('pre', [$this, 'translateTerms']);  //smarty_pre_filter
        if ($this->publishmode == 0 && $this->smartySessionId > 0) {
            $this->executionCounter = 0;

            $this->smarty->registerFilter('post', [$this, 'addProgressCallback']);  //smarty_post_filter
        }else{
            $this->smartySessionId = 0;
        }

        /* smarty output filter is not used in this version since we fetch output to variable
            $this->smarty->registerFilter('output', [$this, 'handleTemplateOutput']);
        */

        $this->smarty->assign('template_file', $this->templateFile);
        try {

            $this->handleTemplateOutput( $this->smarty->fetch($this->templateFile) );

            /* Apparently need to use $this->smarty->display($templateFile) for huge reprot to direct output to browser*/

        } catch (\Exception $e) {
            $this->outputError('Exception on execution (if you get this error please send us a bug report, we are trying to track the problem): ' . $e->getMessage());
            $result = false;
        }

        if ($this->smartySessionId > 0) {
            mysql__update_progress(null, $this->smartySessionId, false, 'REMOVE');
        }


        if($this->publishmode==0 && isset($this->messageAboutTruncation)){
            echo $this->messageAboutTruncation;
        }

        //remove temporary file
        if($temp_templateFile){
            fileDelete($this->smarty->getTemplateDir().$temp_templateFile);
        }

        return $result;
    }

    //
    //
    //
    /**
     * Configures PHP and Smarty error reporting levels based on the provided report level.
     *
     * - `$replevel` 0: Display errors off.
     * - `$replevel` 1: PHP display errors on, Smarty reports E_NOTICE.
     * - `$replevel` 2: PHP display errors on, Smarty reports E_ALL & ~E_STRICT & ~E_NOTICE.
     * - `$replevel` 3: PHP display errors on, Smarty debugging enabled, Smarty reports E_ALL & ~E_STRICT & ~E_NOTICE.
     * Sets the Smarty debug template if `$replevel` > 0.
     *
     * @param int $replevel The reporting level (0-3).
     */
    private function setupErrorReporting($replevel){

        $this->smarty->debugging = false;
        $this->smarty->error_reporting = 0;

        if($replevel==1 || $replevel==2){
            ini_set( 'display_errors' , 'true');// 'stdout' );
            if($replevel==2){
                $this->smarty->error_reporting = E_ALL & ~E_STRICT & ~E_NOTICE;
            }else{
                $this->smarty->error_reporting = E_NOTICE;
            }
        }else{
            ini_set( 'display_errors' , 'false');
            $this->smarty->debugging = ($replevel==3);
        }
        if($replevel>0){
            $this->smarty->debug_tpl = dirname(__FILE__).'/debug_html.tpl';
        }

    }

    //
    /**
     * Saves template content (typically from `params['template_body']`) to a temporary file.
     *
     * The temporary file is named based on the current user's name (sanitized) and
     * created in the Smarty template directory. This allows Smarty to process
     * template content that was not loaded from a pre-existing .tpl file.
     *
     * @param string $content The template content string to save.
     * @return string The basename of the created temporary template file (e.g., "_username.tpl").
     */
    private function saveTemporaryTemplate($content){
        //save temporary template
        //this is user name $templateFile = "_temp.tpl";
        $user = $this->system->getCurrentUser();

        $templateFile = '_'.basename(USanitize::sanitizeFileName($user['ugr_Name'])).'.tpl';
        $template_folder = $this->smarty->getTemplateDir();
        if(is_array($template_folder)) {$template_folder = $template_folder[0];}
        $file = fopen ($template_folder.$templateFile, "w");
        fwrite($file, $content);
        fclose ($file);

        return $templateFile;
    }

    //
    //
    //
    /**
     * Handles the display or storage of an error message.
     *
     * Stores the error message in `$this->messageError`.
     * It then calls `handleTemplateOutput()` to display the message, formatted as HTML
     * if the `outputmode` is 'html'.
     *
     * @param string|null $error_msg The error message to output. If null, it retrieves
     *                               the last error from `$this->system->getErrorMsg()`.
     */
    private function outputError($error_msg=null){

        if(!isset($error_msg)){
            $error_msg = $this->system->getErrorMsg();
            if($error_msg==''){
                $error_msg = 'Undefined smarty error';
            }
        }

        $this->messageError = $error_msg;

        if($this->outputmode=='html'){
            $error_msg = '<span style="color:#ff0000;font-weight:bold">'.$error_msg.'</span>';
        }
        $this->handleTemplateOutput($error_msg);
    }

    /**
     * Retrieves the last error message stored by `outputError()`.
     *
     * @return string|null The last error message, or null if no error has been set.
     */
    public function getError(){
        return $this->messageError;
    }

    //SMARTY FILTERS

    /**
     * Smarty pre-filter to handle shorthand term translations.
     *
     * This filter searches for patterns like `{trm_id \Language_1 \Language_2 ...}` in the template source.
     * For each match, it attempts to find a translation for `trm_id` in the specified languages
     * (in order) from `defTranslations`. If no translation is found, it falls back to the
     * term's default label from `defTerms`. The matched shorthand is replaced with the found label/translation.
     *
     * @param string $tpl_source The raw template source code.
     * @param \Smarty\Template $template The Smarty template object.
     * @return string The modified template source with shorthand terms translated.
     */
    public function translateTerms($tpl_source, \Smarty\Template $template){

        $matches = array();

        // Handle shorthand term translations {trm_id \Language_1 \Language_2 ...}
        if(!preg_match_all('/{\\d*\\s*(?:\\\\\\w{3}\\s*)+}/', $tpl_source, $matches)){
            return $tpl_source;
        }

        $mysqli = $this->system->getMysqli();

        $query = "SELECT trn_Translation FROM defTranslations WHERE trn_Code={id} AND trn_Source='trm_Label' AND trn_LanguageCode='{lang}'";
        $to_replace = array('{id}', '{lang}');
        $done = array();

        foreach ($matches[0] as $match) {

            if(in_array($match, $done)){ // already replaced
                continue;
            }

            $parts = explode('\\', trim($match, ' {}'));
            $str_replace = '';

            if(empty($parts) || intval($parts[0]) < 1){ // ignore
                continue;
            }

            $id = intval(array_shift($parts));

            foreach ($parts as $lang) {
                $str_replace = mysql__select_value($mysqli, str_replace($to_replace, array($id, $lang), $query));

                if(!empty($str_replace)){
                    break;
                }
            }

            if(empty($str_replace)){
                $str_replace = mysql__select_value($mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID=$id");
            }

            $tpl_source = str_replace($match, $str_replace, $tpl_source);
            array_push($done, $match);
        }


        return $tpl_source;
    }

    //
    /**
     * Smarty post-filter to inject a progress callback into template loops.
     *
     * This filter searches for the beginning of Smarty's compiled `foreach` loops
     * and inserts a call to the `progressCallback` Smarty plugin. This allows
     * for progress tracking during the rendering of large datasets.
     *
     * @param string $tpl_source The compiled template source code.
     * @param \Smarty\Template $template The Smarty template object.
     * @return string The modified compiled template source with the progress callback injected.
     */
    public function addProgressCallback($tpl_source, \Smarty\Template $template)
    {
        //find fist foreach and insert as first operation
        $offset = strpos($tpl_source,'foreach ($_from ?? [] as $_smarty_tpl->getVariable(');//'foreach ($_from as $_smarty_tpl');

        if($offset>0){
            $pos = strpos($tpl_source,'{',$offset);

            return substr($tpl_source,0,$pos+1)
."\n".'$_smarty_tpl->getSmarty()->getFunctionHandler("progressCallback")->handle(array(), $_smarty_tpl);'."\n"
//old way            ."\n".'{ if(progressCallback(array(), $_smarty_tpl)){ return; }'."\n"
            .substr($tpl_source,$pos+1);

        }

        return $tpl_source;
    }

    private function removeHeadAndBodyTags($content){

            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            //disable this feature $dom->formatOutput       = true;
            @$dom->loadHTML($content);
            $body = $dom->getElementsByTagName('body');
            if($body){
                $new_content = $dom->saveHtml($body[0]);//outer html
                $new_content = preg_replace( '@(?:^<body[^>]*>)|(?:</body>$)@g', '', $new_content );
                $new_content = preg_replace( '@(?:^<p[^>]*>)|(?:</p>$)@g', '', $new_content );
                if($new_content!=null && $new_content!=''){
                    return $new_content;
                }
            }

            return $content;
    }

    //
    // Get TinyMCE formats
    //
    private function getFontStyles(){

        $font_styles = '';

            $formats = $this->system->settings->getDatabaseSetting('TinyMCE formats');
            if(is_array($formats) && array_key_exists('formats', $formats)){
                foreach($formats['formats'] as $format){

                    $styles = $format['styles'];

                    $classes = $format['classes'];

                    if(empty($styles) || empty($classes)){
                        continue;
                    }

                    $font_styles .= "." . implode(", .", explode(" ", $classes)) . " { ";
                    foreach($styles as $property => $value){
                        $font_styles .= "$property: $value; ";
                    }
                    $font_styles .= "} ";
                }
            }

        return $font_styles;
    }


    /**
     * Adds custom styles and scripts from CMS settings.
     *
     * @return string The HTML content containing custom styles and scripts.
     */
    private function addCustomStylesAndScripts()
    {
         $head = '';
         $css_fields = array();
         if($this->system->defineConstant('DT_CMS_CSS')){
             array_push($css_fields, DT_CMS_CSS);
         }
         if($this->system->defineConstant('DT_CMS_EXTFILES')){
             array_push($css_fields, DT_CMS_EXTFILES);
         }
         if(empty($css_fields)){
             return '';
         }

         $record = recordSearchByID($this->system, $this->recordWithCustomCSS, $css_fields, 'rec_ID');
         if(!@$record['details']){
            return '';
         }

         if(defined('DT_CMS_CSS') && @$record['details'][DT_CMS_CSS]){
             //add to begining
             $head .= '<style>'.recordGetField($record, DT_CMS_CSS).'</style>';
         }

         if(defined('DT_CMS_EXTFILES') && @$record['details'][DT_CMS_EXTFILES]){
             //add to header
             $external_files = $record['details'][DT_CMS_EXTFILES] ?? [];
             if(!is_array($external_files)){
                     $external_files = array($external_files);
             }

             foreach ($external_files as $ext_file){
                $head .= $ext_file;
             }
         }

         return $head;
    }

    /**
     * Handles the case where JavaScript is allowed.
     *
     * @param string $tpl_source The source template content.
     * @param string $font_styles The CSS font styles.
     * @return string The modified template content with JavaScript and styles.
     */
    private function handleJsAllowed($tpl_source, $font_styles){

         $script_tag = '<script type="text/javascript" src="'.HEURIST_BASE_URL;

         if(!$this->isHeadless){ //full html output. inside iframe - add all styles and scripts to header at once

             //adds custom scripts and styles to html head

             $head = $font_styles;
             $close_tags = '';

             if(strpos($tpl_source, '<html>')===false){
                 $open_tags = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
                 $close_tags = '</body></html>';
             }

             //add custom css and external links from CMS_HOME  DT_CMS_CSS and DT_CMS_EXTFILES
             if ($this->recordWithCustomCSS > 0) {
                    $head .= $this->addCustomStylesAndScripts();
             }

             //check if need to init mediaViewer
             if(strpos($tpl_source,'fancybox-thumb')>0){

                 $baseURL = HEURIST_BASE_URL;
                 $head .= <<<EXP
                            {$script_tag}external/jquery/jquery-3.7.1.js"></script>
                            {$script_tag}external/jquery/jquery-ui.js"></script>
                            {$script_tag}external/jquery.fancybox/jquery.fancybox.js"></script>
                            {$script_tag}hclient/core/detectHeurist.js"></script>
                            {$script_tag}hclient/widgets/viewers/mediaViewer.js"></script>
                            <link rel="stylesheet" href="{$baseURL}external/jquery.fancybox/jquery.fancybox.css" />
                        EXP;
                 //init mediaviewer after page load
                 $head .=  ('<script>'
                     .'var rec_Files=[];'
                     .'$(document).ready(function() {'
                     .'$("body").mediaViewer({rec_Files:rec_Files, showLink:false, selector:".fancybox-thumb", '
                     .'database:"'.$this->system->dbname().'", baseURL:"'.HEURIST_BASE_URL.'"});'
                     .'});'
                     .'</script>'
                     .'<style>.fancybox-toolbar{visibility: visible !important; opacity: 1 !important;}</style>');
             }


             //forcefully adds html and body tags
             $tpl_source = $open_tags.$tpl_source.$close_tags;

             $tpl_source = str_replace('<body>','<body class="smarty-report">', $tpl_source);
             if($head!=''){
                 $tpl_source = str_replace(HEAD_E,$head.HEAD_E, $tpl_source);
             }


         }else{ //html snippet output (without head) ----------------------------
                //loading as content of existing html element

             //adds custom scripts and styles to parent document head (insertAdjacentHTML and )

             $head = $font_styles;

             //check if need to init mediaViewer
             if(strpos($tpl_source,'fancybox-thumb')>0){

                 $head = <<<EXP
    {$script_tag}external/jquery/jquery-3.7.1.js"></script>
    {$script_tag}external/jquery/jquery-ui.js"></script>
    {$script_tag}external/jquery.fancybox/jquery.fancybox.js"></script>
    {$script_tag}hclient/core/detectHeurist.js"></script>
    {$script_tag}hclient/widgets/viewers/mediaViewer.js"></script>
    <script>var rec_Files=[];</script>
    EXP;

                 $head .= (
                     '<script>$(document).ready(function() {'

                     .'document.getElementsByTagName("head")[0].insertAdjacentHTML("beforeend","<link rel=\"stylesheet\" href=\"'.HEURIST_BASE_URL.'external/jquery.fancybox/jquery.fancybox.css\" />");'

                     .'$("body").mediaViewer({rec_Files:rec_Files, showLink:false, selector:".fancybox-thumb", '
                     .'database:"'.$this->system->dbname().'", baseURL:"'.HEURIST_BASE_URL.'"});'
                     .'});'
                     .'</script>'
                     .'<style>.fancybox-toolbar{visibility: visible !important; opacity: 1 !important;}</style>');
             }

             //add custom css and external links from CMS_HOME  DT_CMS_CSS and DT_CMS_EXTFILES
             if($this->recordWithCustomCSS>0){
                 //find record with css fields
                 $css_fields = array();
                 if($this->system->defineConstant('DT_CMS_CSS')){
                     array_push($css_fields, DT_CMS_CSS);
                 }
                 if($this->system->defineConstant('DT_CMS_EXTFILES')){
                     array_push($css_fields, DT_CMS_EXTFILES);
                 }
                 if(!empty($css_fields)){
                     $record = recordSearchByID($this->system, $this->recordWithCustomCSS, $css_fields, 'rec_ID');
                     if($record && @$record['details']){

                         if(defined('DT_CMS_CSS') && @$record['details'][DT_CMS_CSS]){
                             //add to begining
                             $head = '<style>'.recordGetField($record, DT_CMS_CSS).'</style>'.$head;

                             $head = $head.'<script>if(document.body){
                             document.body.classList.add("smarty-report");
                             } </script>';
                         }

                         if(defined('DT_CMS_EXTFILES') && @$record['details'][DT_CMS_EXTFILES]){
                             //add to header
                             $external_files = @$record['details'][DT_CMS_EXTFILES];
                             if($external_files!=null){
                                 if(!is_array($external_files)){
                                     $external_files = array($external_files);
                                 }
                                 if(!empty($external_files)){
                                     foreach ($external_files as $ext_file){
                                         if(strpos($ext_file,'<link')===0){ // || strpos($ext_file,'<script')===0
                                             $head = $head .$ext_file;
                                         }
                                     }

                                 }
                             }
                         }
                     }
                 }
             }

             //
             if($head!=''){
                 if(strpos($tpl_source, '<head>')>0){
                     $tpl_source = str_replace(HEAD_E, $head.HEAD_E, $tpl_source);
                 }else{
                     $tpl_source = $this->removeHeadAndBodyTags($tpl_source);
                     $tpl_source = $head.$tpl_source;
                 }
             }
         }
         return $tpl_source;
    }

    /**
     * Sanitizes the HTML using HTMLPurifier.
     *
     * @param string $tpl_source The source template content.
     * @param string $font_styles The CSS font styles.
     * @return string The sanitized template content.
     */
    private function sanitizeHtml($tpl_source, $font_styles)
    {

                //if javascript not allowed, use html purifier to remove suspicious code
                $config = \HTMLPurifier_Config::createDefault();
                $config->set('HTML.Doctype', 'HTML 4.01 Transitional');

                $config->set('HTML.DefinitionID', 'html5-definitions');// unqiue id
                $config->set('HTML.DefinitionRev', 1);

                $config->set('Cache', 'SerializerPath', $this->system->getSysDir('scratch'));
                $config->set('CSS.Trusted', true);
                $config->set('Attr.AllowedFrameTargets','_blank');
                $config->set('HTML.SafeEmbed', true);
                $config->set('HTML.SafeIframe', true);
                //allow YouTube, Soundlcoud and Vimeo
                // https://w.soundcloud.com/player/
                $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/|w\.soundcloud\.com/player/)%');

                $def = $config->getHTMLDefinition(true);
                $def->addElement(
                    'audio',
                    'Block',
                    'Flow',
                    'Common',
                    [
                        'controls' => 'Bool',
                        'autoplay' => 'Bool',
                        'data-id' => 'Number'
                    ]
                );
                $def->addElement('source', 'Block', 'Flow', 'Common', array(
                    'src' => 'URI',
                    'type' => 'Text',
                ));
                
                //$config->set('HTML.AllowedAttributes','*.data-heurist-rec');
                $def->addAttribute('div', 'data-heurist-rec', 'Number');

                /* to test it
                if ($def = $config->maybeGetRawHTMLDefinition()) {
                    // http://developers.whatwg.org/the-video-element.html#the-video-element
                    $def->addElement('video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', array(
                        'src' => 'URI',
                        'type' => 'Text',
                        'width' => 'Length',
                        'height' => 'Length',
                        'poster' => 'URI',
                        'preload' => 'Enum#auto,metadata,none',
                        'controls' => 'Bool',
                    ));
                }
                $config->set('HTML.Trusted', true);
                $config->set('Filter.ExtractStyleBlocks', true);

                */

                $purifier = new \HTMLPurifier($config);

                $tpl_source = $purifier->purify($tpl_source);

                if(!empty($font_styles)){
                    if(strpos($tpl_source, '<head>')>0){
                        $tpl_source = str_replace(HEAD_E,$font_styles.HEAD_E, $tpl_source);
                    }else{
                        $tpl_source = $font_styles.$tpl_source;
                    }
                }

                return $tpl_source;
    }

    /**
     * Strips JavaScript and sanitizes HTML output.
     * This function is called before other output filters.
     *
     * @param string $tpl_source The source template content.
     * @param \Smarty\Template $template The Smarty template object.
     * @return string The sanitized template content.
     */
    private function stripJavascriptAndSantize($tpl_source){

            if(!($this->outputmode=='js' || $this->outputmode=='html')){
                //other than html or js output - it removes html and body tags
                return $this->removeHeadAndBodyTags($tpl_source);
            }

            $font_styles = $this->system->settings->getWebFontsLinks('ui-sans-serif');
            if(isEmptyStr($font_styles)){
                $font_styles = '';
            }else{
                $font_styles = "<style> $font_styles </style>";
            }
            $tinymce_styles = $this->getFontStyles(); //tinyMCE formats
            if(!empty($tinymce_styles)){
                $font_styles = "<style> $tinymce_styles </style>".$font_styles;
            }
            if($this->testForWidgetTemplate){
                //$font_styles = '<style type="text/css"> @import url("hclient/widgets/HRecordList/HRecordList.css"); </style>'.$font_styles;
                
                $font_styles = '<script src="'.HEURIST_BASE_URL.'external/bootstrap/bootstrap.bundle.min.js"></script>'
                .'<link href="'.HEURIST_BASE_URL.'external/bootstrap/bootstrap.min.css" rel="stylesheet">'
                .'<link href="'.HEURIST_BASE_URL.'hclient/widgets/HRecordList/HRecordList.css" rel="stylesheet">'
                .'<link href="'.HEURIST_BASE_URL.'h4styles.css" rel="stylesheet">'
                .'<link href="'.HEURIST_BASE_URL.'h6styles.css" rel="stylesheet">'
                .$font_styles;
                
            }
            

            // Allow JavaScript or sanitize HTML
            if ($this->isJsAllowed) {
                $tpl_source = $this->handleJsAllowed($tpl_source, $font_styles);
            } else {
                $tpl_source = $this->sanitizeHtml($tpl_source, $font_styles);
            }

        //replace relative path for images that are in blocktext fields
        $tpl_source = str_replace(' src="./?db='.$this->system->dbname().'&',
            ' src="'.HEURIST_BASE_URL.'?db='.$this->system->dbname().'&',$tpl_source);



        $onclick = '';
        if($this->publishmode==0 || $this->publishmode==1){
            $onclick = 'onclick="'
                . '{try'
                    .'{'
                        .'let event_target = event.target.getAttribute("target");'
                        .'let def_targets = ["_self","_blank","_parent","_top"];'
                        .'if(event_target && def_targets.indexOf(event_target) !== -1){ return true; }'
                        .'var h=window.hWin?window.hWin.HEURIST4:window.parent.hWin.HEURIST4;'
                        .'h.msg.showDialog(event.target.href,{title:\'.\',width: 600,height:500,modal:false});'
                        .'return false'
                    .'}catch(e){'
                        .'return true'
                    .'}'
                .'}" ';
        }

        $tpl_source = preg_replace_callback('/href=["|\']?(\d+\/.+\.tpl|\d+)["|\']?/',
                function($matches) use ($onclick){
                    return $onclick.'href="'.$this->system->recordLink($matches[1]).'"';
                },
                $tpl_source);

        return $tpl_source;

    }

    //
    //
    //
    private function saveOutputAsJavascript( $tpl_source ){

        $tpl_source = str_replace("\n","",$tpl_source);
        $tpl_source = str_replace("\r","",$tpl_source);
        $tpl_source = str_replace("'","&#039;",$tpl_source);
        return "document.write('". $tpl_source."');";
    }

    //
    //
    //
    private function saveOutputToFile($file_name, $tpl_source){

        $errors = null;

        try{
            //output to generated-reports only
            $dirname = $this->system->getSysDir(DIR_GENERATED_REPORTS);
            if(!folderCreate($dirname, true)){
                return 'Failed to create folder for generated reports';
            }

            $res_file = $dirname."/".$file_name; // acutal file
            $temp_file = $dirname."/_".$file_name; // temporary file, if needed

            $file = false; // file handler
            $use_temp = false; // using temporary file

            if(!file_exists($res_file) || is_writable($res_file)){ // open existing file
                $file = fopen ($res_file, "w");
            }else{ // create temp file to replace original
                $file = fopen($temp_file, "w");
                $use_temp = true;
            }

            if(!$file){
                $errors = "Can't write file $res_file. Check permission for directory";
            }else{
                fwrite($file, $tpl_source);
                fclose($file);
            }

            if($use_temp){

                if(unlink($res_file) === false){ // Delete old file
                    unlink($temp_file);// on error, remove temp file
                    $errors = "Can't delete old report file $res_file. Check permission for file";
                }elseif(rename($temp_file, $res_file) === false){ // Rename temp file
                    unlink($temp_file);// on error, remove temp file
                    $errors = "Can't rename temporary file $temp_file to $res_file. Check permissions";
                }
            }


        }catch(\Exception $e)
        {
            $errors = $e->getMessage();
        }

        return $errors;

    }


    /**
     * Handles the output from the Smarty template, saving or outputting it as required.
     *
     * @param string $smarty_output The rendered Smarty output.
     * @param bool $need_sanitize Whether or not to sanitize the output.
     */
    private function handleTemplateOutput($smarty_output, $need_sanitize=true){ // ,  \Smarty\Template $template=null

        $errors = null;

        //sanitize
        if($need_sanitize){
            $smarty_output = $this->stripJavascriptAndSantize($smarty_output);

            if($this->outputmode=='js'){
                $smarty_output = $this->saveOutputAsJavascript($smarty_output);
            }
        }


        if($this->publishmode!=1){
            $this->setOutputHeaders();
        }

        // if param "output" ($outputfile) is defined it saves smarty report into file
        // and
        // $publishmode - 1 saving into file and produces info page (user report) only
        //                2 downloads ONLY it under given output name (no file save, no browser output)
        //                3 saving into file and outputs smarty report into browser

        if ($this->publishmode==2) {//download

                header('Pragma: public');
                header('Content-Disposition: attachment; filename="'.$this->outputfile.'"');
                header(CONTENT_LENGTH . strlen($smarty_output));
                echo $smarty_output;

        }elseif ($this->publishmode==0) {    //browser output only

            echo $smarty_output;
        }else {
            //3 - save into file and browser output
            //1 - save into file and info page

            if($this->outputfile!=null){
                $errors = $this->saveOutputToFile($this->outputfile, $smarty_output);
            }
            if($this->isVoid){
                return;
            }
            if($this->publishmode==3){
                echo $smarty_output;
            }else{
                $this->generateInfoPage($this->outputfile, $errors);
            }

        }

    }


    /**
     * Outputs appropriate headers for the content type based on the output mode.
     */
    private function setOutputHeaders(){

            if($this->publishmode!=1){
                switch ($this->outputmode) {
                    case 'js': $mimetype = 'text/javascript'; break;
                    case 'txt': $mimetype = 'text/plain'; break;
                    case 'json': $mimetype = MIMETYPE_JSON; break;
                    default: $mimetype = 'text/'.$this->outputmode  ; break; //text/xml text/html
                }
                header("Content-type: $mimetype;charset=UTF-8");
            }

    }

    //
    //
    //
    private function generateInfoPage($file_name, $errors){

        header(CTYPE_HTML);

        if(isset($errors)){
            print $errors;
            return;
        }

        $gparams = $this->params;

        $url = htmlspecialchars($this->system->getSysUrl(DIR_GENERATED_REPORTS) . $file_name);
?>
<!DOCTYPE>
<html lang="en">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <link rel="stylesheet" type="text/css" href="../../h4styles.css">
    <title>File generated</title>
</head>
<body style="margin: 25px;">
<div>
    The following file has been updated:  <a href="<?php echo $url; ?>" target="_blank" rel="noopener"><?php echo $url;?></a>
</div>
<br>

<?php
$rps_recid = @$gparams['rps_id']; //report schedule iD
if($rps_recid){

    //$link = str_replace('&amp;','&',htmlspecialchars(HEURIST_BASE_URL."?db=".$this->system->dbname()."&publish=3&template_id=".$rps_recid));
    
    $link = HEURIST_BASE_URL.'?db='.htmlspecialchars($this->system->dbname()).'&publish=3&template_id='.intval($rps_recid);
?>

    <p style="font-size: 14px;">Regenerate and view the file:<br><br>
    <?php echo strtoupper($this->outputmode); ?>: <a href="<?=$link?>" target="_blank"  rel="noopener" style="font-weight: bold;font-size: 0.9em;"><?=$link?></a><br><br>
    Javascript: <a href="<?=$link?>&mode=js" target="_blank" style="font-weight: bold;font-size: 0.9em;"><?=$link?>&mode=js</a><br>

<?php
}

// code for insert of dynamic report output - duplication of functionality in repMenu.html
$surl = HEURIST_BASE_URL."?db=".$this->system->dbname().
"&ver=".$gparams['ver']."&w=".$gparams['w']."&q=".$gparams['q'].
"&template=".$gparams['template'];

if(@$gparams['rules']){
    $surl = $surl."&rules=".$gparams['rules'];
}
if(@$gparams['h4']){
    $surl = $surl."&h4=".$gparams['h4'];
}

$surl = str_replace('&amp;','&',htmlspecialchars($surl, ENT_QUOTES));


$surl2 = $surl.'&mode=js';
if($this->outputmode!='html'){
    $surl = $surl.'&mode='.$this->outputmode;
}


?><br>
To publish the report as dynamic (generated on-the-fly) output, use the code below.
<br><br>
URL:<br>
<textarea readonly style="border: 1px dotted gray; padding: 3px; margin: 2px; font-family: times; font-size: 10px;"
    id="code-textbox1" onClick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" rows="3" cols="150"><?php echo $surl;?></textarea>

<br>
Javascript wrap:<br>
<textarea readonly style="border: 1px dotted gray; padding: 3px; margin: 2px; font-family: times; font-size: 10px;"
    id="code-textbox2" onClick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" rows="5" cols="150">
    <script type="text/javascript" src="<?php echo $surl2;?>"></script><noscript><iframe title width="80%" height="70%" frameborder="0" src="<?php echo $surl;?>"></iframe></noscript>
</textarea>

</p></body></html>
                    <?php
    }


    //
    /**
     * Smarty plugin function for progress tracking within template loops.
     *
     * This function is called by the code injected by `addProgressCallback`.
     * It updates the progress session on the server side via `mysql__update_progress`
     * periodically (e.g., every 10 records or near the beginning/end).
     * It also checks if the user has requested to terminate the report generation.
     *
     * @param array $params Parameters passed from the Smarty tag. Expected (optional):
     *                      'done': Current number of items processed.
     *                      'tot_count': Total number of items to process.
     * @param \Smarty\Template $smarty The Smarty template object (passed by reference, but not strictly needed by this method's logic for `$smarty` object itself).
     * @return bool True if the process was terminated by the user, false otherwise.
     */
    public function progressCallback($params, &$smarty){

        if($this->publishmode!=0 || $this->smartySessionId==null){ //check that this call from ui
            return false;
        }

        $res = false;

            if(@$params['done']==null){//not set, this is execution from smarty
                $this->executionCounter++;
            }else{
                $this->executionCounter = @$params['done'];
            }

            if(isset($this->executionCounterTotal) && $this->executionCounterTotal>0){
                $tot_count = $this->executionCounterTotal;
            }elseif(@$params['tot_count']>=0){
                $tot_count = $params['tot_count'];
            }else{
                $tot_count = count(@$smarty->getVariable('results')->value);
            }

            if($this->executionCounter<2 || $this->executionCounter % 10==0 || $this->executionCounter>$tot_count-3){

                $session_val = $this->executionCounter.','.$tot_count;
                $current_val = mysql__update_progress(null, $this->smartySessionId, false, $session_val);
                if($current_val && $current_val=='terminate'){
                    $session_val = '';//remove from db
                    $res = true;
                }
            }

        return $res;


    }


    /**
     * Smarty plugin function `{out}` to print a label-value pair, typically in a div structure.
     *
     * Example: `{out lbl="Name" var=$record.name}`
     *
     * @param array $params Parameters from the Smarty tag:
     *                      - 'lbl': The label string.
     *                      - 'var': The variable/value to display.
     * @param \Smarty\Template $smarty The Smarty template object (passed by reference).
     * @return string HTML string `<div><div class="tlbl">Label: </div><b>Value</b></div>` if 'var' is not empty, otherwise empty string.
     */
    public function printLabelValuePair($params, &$smarty)
    {
        if($params['var']){
            return '<div><div class="tlbl">'.$params['lbl'].': </div><b>'.$params['var'].'</b></div>';
        }else{
            return '';
        }
    }

    /**
     * Smarty plugin function `{wrap}` for versatile display of field values with formatting options.
     *
     * This function handles different data types ('url', 'file', 'geo', 'date', text/CMS content)
     * and applies specific formatting, linking, or media player generation based on parameters.
     *
     * @param array $params Parameters from the Smarty tag:
     *                      - 'var': The field value or array of values.
     *                      - 'dt': (Optional) The detail type (e.g., 'url', 'file', 'geo', 'date').
     *                              If not set, treated as general text/CMS content.
     *                      - 'mode': (Optional) Specific mode for certain data types:
     *                                - For 'file': 'thumbnail', 'link', or player (default).
     *                                - For 'date': Date format specifier (passed to `Temporal::toHumanReadable`).
     *                      - 'lbl': (Optional) A label to prepend to the output.
     *                      - 'fancybox': (Optional) If true for 'file' type, prepares data for Fancybox media viewer.
     *                      - 'style': (Optional) Inline CSS style string for the output element.
     *                      - 'width', 'height': (Optional) Dimensions for images, players, maps.
     *                      - 'limit': (Optional) For multi-value fields, limits the number of items displayed.
     *                      - 'calendar': (Optional) For 'date' type, calendar system for display.
     * @param \Smarty\Template $smarty The Smarty template object (passed by reference).
     * @return string The formatted HTML output for the field value.
     */
    public function printProcessedValue($params, &$smarty)
    {
        if(!isset($params['var'])){
            return '';
        }


            if(array_key_exists('dt',$params)){
                $dt = $params['dt'];
            }
            if(array_key_exists('mode',$params)){
                $mode = $params['mode'];
            }else{
                $mode = null;
            }

            $label = "";
            if(array_key_exists('lbl',$params) && $params['lbl']!=""){
                $label = $params['lbl'];
            }
            $size = '';
            $mapsize = '';
            $style = '';

            if(array_key_exists('style',$params) && $params['style']!=""){

                $style = ' style="'.$params['style'].'"';

            }else{

                $width = "";
                $mapsize = "width=200";

                if(array_key_exists('width',$params) && $params['width']!=""){
                    $width = $params['width'];
                    if(is_numeric($width)<0){
                        $width = $width."px";
                        $mapsize = "width=".$width;
                    }
                }
                $height = "";
                if(array_key_exists('height',$params) && $params['height']!=""){
                    $height = $params['height'];
                    if(is_numeric($height)<0){
                        $height = $height."px";
                        $mapsize = $mapsize."&height=".$height;
                    }
                }
                if(strpos($mapsize,'&')===false){
                    $mapsize = $mapsize.'&height=200';
                }

                $size = '';
                if($width=='' && $height==''){
                    if($mode!='thumbnail'){
                        $size = "width=".(($dt=='geo')?"200px":"'300px'");
                    }
                }else {
                    if($width!=""){
                        $size = "width='".$width."'";
                    }
                    if($height!=""){
                        $size = $size." height='".$height."'";
                    }
                }
            }

            switch ($dt){
                case 'url':
                    $result = "<a href='{$params['var']}' target=_blank rel=noopener $style>{$params['var']}</a>";
                    break;
                case 'file':
                    $result = $this->processFieldFile($params, $mode, $style, $size);
                    break;
                case 'geo':
                    $value = $params['var'];
                    $result = '';

                    if($value && $value['wkt']){
                        $geom = \geoPHP::load($value['wkt'],'wkt');
                        if(!$geom->isEmpty()){
                                $point = $geom->centroid();
                                if($label=="") {$label = "on map";}
                                $result = '<a href="https://maps.google.com/maps?z=18&q='.$point->y().",".$point->x().'" target="_blank" rel="noopener">'.$label."</a>";
                        }
                    }
                    break;
                case 'date':
                    if($mode==null) {$mode = 1;}

                    $calendar = null;
                    if(array_key_exists('calendar',$params)){
                        $calendar = $params['calendar'];
                    }
                    if(is_array($params['var']) && array_key_exists(0,$params['var'])){
                        $params['var'] = $params['var'][0];
                    }

                    $content = Temporal::toHumanReadable($params['var'], true, $mode, '|', $calendar);

                    if($label!="") {$label = $label.": ";}
                    $result = $label.$content.'<br>';
                    break;
                default:
                    //if this is CMS content
                    // 1. Extract HTML content from text elements [{"name":"Content","type":"text","css":{},"content":
                    // 2. Convert relative paths to absolute
                    if(is_string(@$params['var'])){
                        $content = json_decode($params['var'], true);
                    }else{
                        $content = @$params['var'];
                    }
                    if(is_array($content)){
                        $content = $this->prepareCMScontent($content);
                    }else{
                        $content = $this->prepareCMScontent($params['var']);
                    }

                    if($label!="") {$label = $label.": ";}
                    $result = $label.$content.'<br>';
            }

            return $result;
    }

    /**
    * Process file field and output img or player/viewer output
    *
    * @param mixed $params
    * @param mixed $mode
    * @param mixed $style
    * @param mixed $size
    */
    private function processFieldFile($params, $mode, $style, $size){
        //insert image or link
        $values = $params['var'];

        $limit = intval(@$params['limit']);

        $sres = "";

        if(!is_array($values) || !array_key_exists(0,$values)) {$values = array($values);}

        foreach ($values as $idx => $fileinfo){

            if($limit>0 && $idx>=$limit) {break;}

            $external_url = $fileinfo['ulf_ExternalFileReference'];//ulf_ExternalFileReference
            $originalFileName = $fileinfo['ulf_OrigFileName'];
            $file_nonce = $fileinfo['ulf_ObfuscatedFileID'];
            $file_desc = htmlspecialchars(strip_tags($fileinfo['ulf_Description']));
            $mimeType = $fileinfo['fxm_MimeType'];
            $file_Ext= $fileinfo['ulf_MimeExt'];

            /*in this version we use player tag  see fileGetPlayerTag
                $file_playerURL = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&file='.$file_nonce.'&mode=tag';
            */
            $file_thumbURL  = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&thumb='.$file_nonce;
            $file_URL   = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&file='.$file_nonce; //download

            if($mode=="link") {

                $sname = (!$originalFileName || $originalFileName==ULF_REMOTE || strpos($originalFileName,ULF_IIIF)===0)
                ?$external_url:$originalFileName;

                if(@$params['fancybox']){
                    $sres = $sres."<a class=\"fancybox-thumb\" data-id=\"$file_nonce\" href='"
                    .$file_URL."' target=_blank rel=noopener title='".$file_desc."' $style>$sname</a>";
                }else{
                    $sres = $sres."<a href='$file_URL' target=_blank rel=noopener title='$file_desc' $style>$sname</a>";
                }

            }elseif($mode=="thumbnail"){

                if(@$params['fancybox']){
                    $sres .= "<img class=\"fancybox-thumb\" data-id=\"$file_nonce\" src=\"".$file_thumbURL."\" title=\"".$file_desc."\" $size $style/></a>";
                }else{
                    $sres = $sres."<a href='$file_URL' target=_blank rel=noopener>".
                    "<img class=\"\" src=\"".$file_thumbURL."\" title=\"".$file_desc."\" $size $style/></a>";
                }

            }else{ //player is default

                $sres = $sres.fileGetPlayerTag($this->system, $file_nonce, $mimeType, $params, $external_url, $size, $style);//see recordFile.php

            }

            if(@$params['fancybox'] && $this->isJsAllowed){

                $mode_3d_viewer = detect3D_byExt($file_Ext);

                $sres .= ('<script>if(rec_Files)rec_Files.push({'
                    .'rec_ID:'.$fileinfo['rec_ID']
                    .',id:"'.$file_nonce
                    .'",mimeType:"'.$mimeType
                    .'",mode_3d_viewer:"'.$mode_3d_viewer
                    .'",filename:"'.htmlspecialchars($originalFileName)
                    .'",external:"'.htmlspecialchars($external_url).'"});</script>');
            }

        }

        return $sres;
    }


    //
    //  Replace relative path to absolute
    //
    private function prepareCMScontent($content){

        $cnt = '';
        $convert_links = true;

        if(is_array($content)){

            if(@$content['type']=='group' && is_array(@$content['children'])){
                $convert_links = false;
                $cnt = $this->prepareCMSgroup($content['children']);
            }elseif(!@$content['type']=='text'){
                $convert_links = false;
                $cnt = $this->prepareCMSgroup($content);
            }elseif(@$content['type']=='text'){
                $cnt =  @$content['content'];
            }
        }else{
            $cnt = $content;
        }

        if($convert_links && $cnt!=null){
            $cnt = str_replace('./?db=',HEURIST_BASE_URL.'?db=',$cnt);
        }

        return $cnt;

    }

    private function prepareCMSgroup($content){
        $cnt = '';
        foreach($content as $grp){
            $res = $this->prepareCMScontent($grp);
            if($res){
                $cnt = $cnt.'<br>'.$res;
            }
        }
        return $cnt;
    }

    //
    // setParameters must be executed beforehand
    // $update_interval - in minutes
    //
    public function outputGeneratedReport($update_interval){

        $dir = $this->system->getSysDir(DIR_GENERATED_REPORTS);

        $this->outputfile = $this->prepareOutputFile();

        $generated_report = $dir.$this->outputfile;

        if(file_exists($generated_report)){

            if($update_interval>0){
                $dt1 = new \DateTime('now');
                $dt2 = new \DateTime();
                $dt2->setTimestamp(filemtime($generated_report));//get file time
                $interval = $dt1->diff( $dt2 );

                $tot_minutes = ($interval->days*1440 + $interval->h*60 + $interval->i);
                if($tot_minutes > $update_interval){
                    //generatated report is outdated
                    return 2; //existing and outdated
                }
            }

            $result = 3; //existing and up to date

            //request for current files (without smarty execution)
            $content = file_get_contents($generated_report);

            $this->outputfile = null; //to avoid save to file
            $this->handleTemplateOutput($content, false);


        }else{
            $result = 1; //need to create new report
        }

        return $result;
    }

}
