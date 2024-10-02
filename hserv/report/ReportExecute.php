<?php
use hserv\utilities\USanitize;


/*
An array of the form array($object, $method) with $object being a reference to an object and $method being a string containing the method-name

*/

class ReportExecute
{
    private $smarty;
    private $outputfile;
    private $outputmode; //output format
    private $is_fetch = false;  
    private $max_allowed_depth = 2; //not used
    private $publishmode;
    private $execution_counter;
    private $execution_total_counter;
    private $is_included;
    private $is_jsallowed;
    private $record_with_custom_styles;
    private $is_headless; //output without html header and styles - for snippet and xml output
    private $smarty_session_id;
    
    private $limit;
    private $replevel;

    private $system;
    private $params;

/*
* parameters
* 'template' or 'template_body' - template file name or template value as a text
* 'replevel'  - 1 notices, 2 all, 3 debug mode
*
* 'output' -  full file path to be saved
* 'mode' -    output format if publish>0: js or html (default)
* 'publish' - 0 vsn 3 UI (smarty tab),
*             1,2,3 - different behaviour when output is defined
*             if output is null if html and js - output to browser, otherwise download
*             4 - for calculation fields
*
* other parameters are hquery's
*/    
    
    
    /**
     * Constructor
     *
     * @param mixed $system The system object used for database and other interactions.
     * @param array $params The parameters array typically passed from $_REQUEST.
     */
    public function __construct($system, $params)
    {
        $this->system = $system;
        $this->params = $params;
    }

    /**
     * Main function to execute the Smarty template with the provided parameters.
     *
     * @return bool Returns true on successful execution, false on failure.
     */
    public function execute()
    {
        if (!isset($this->system) || !$this->system->is_inited()) {
            $this->smarty_error_output(null);
            return false;
        }

        set_time_limit(0); // No script execution time limit

        // Initialize properties from parameters or set defaults
        $this->setParameters();

        // Fetch record IDs
        $qresult = $this->fetchRecordIDs();

        // Handle empty result set
        if (!$this->handleEmptyResultSet($qresult)) {
            return true;
        }

        // Loads template file or template body
        $content = $this->loadTemplateContent();

        // Pre-parse template to set allowed depth
        //$this->preparseTemplate($content);

        // Initialize Smarty if necessary
        $this->initSmarty();

        // Assign variables and execute the template
        return $this->executeTemplate($qresult, $content);
    }

    /**
    *  Initialize properties from parameters or set defaults
     * Handle the output mode and set appropriate flags.
     * Set the search limit based on publishing mode or user preferences.
     */
    private function setParameters()
    {
        
        $this->outputfile = isset($params["output"]) ? htmlspecialchars($params["output"]) : null;
        $this->publishmode = isset($params['publish']) ? intval($params['publish']) : 0;
        $this->record_with_custom_styles = isset($params['cssid']) ? intval($params['cssid']) : null;
        
        $this->is_headless = isset($params['snippet']) && $params['snippet'] == 1;
        $this->outputmode = isset($params['mode']) ? preg_replace('/[^a-z]/', "", $params["mode"]) : 'html';
        $this->smarty_session_id = isset($params['session']) ? $params['session'] : null;
        
        if ($this->outputmode !== 'js' && $this->outputmode !== 'html') {
            $this->is_headless = true;
        }
        if ($this->outputmode === 'text') {
            $this->outputmode = 'txt';
        }
        
        $this->is_fetch = isset($this->params["void"]) && $this->params["void"] == true;  //fetch into file
        
        $this->is_jsallowed = $this->system->isJavaScriptAllowed();

        //Set the search limit based on publishing mode or user preferences.
        if (!isset($this->params["limit"])) {
            if ($this->publishmode == 0) {
                $limit_for_interface = intval($this->system->user_GetPreference('smarty-output-limit'));
                if (!$limit_for_interface || $limit_for_interface < 1) {
                    $limit_for_interface = 50; // Default limit
                }
                $this->params["limit"] = $limit_for_interface;
            } else {
                $this->params["limit"] = PHP_INT_MAX;
            }
        }
        $this->limit = $this->params["limit"];
        
        $this->replevel = 0;
        if (@$this->params['template_body']){
            $this->replevel = isset($this->params['replevel']) ? intval($this->params['replevel']) : 0;
        }
    }

    /**
     * Fetch record IDs based on the provided query parameters.
     *
     * @return array The query result containing records and record count.
     */
    private function fetchRecordIDs()
    {
        if (isset($this->params['recordset'])) {
            //A. recordset (list of record ids) is already defined
            
            if (is_array($this->params['recordset'])) {
                $qresult = $this->params['recordset'];
            } else {
                $qresult = json_decode($this->params['recordset'], true);
            }

            if ($this->publishmode == 0 && $qresult && isset($qresult['recIDs'])) {
                $recIDs = $this->prepareIds($qresult['recIDs']);
                if ($this->limit < count($recIDs)) {
                    $qresult = [
                        'records' => array_slice($recIDs, 0, $this->limit),
                        'reccount' => count($recIDs)
                    ];
                } else {
                    $qresult = [
                        'records' => $recIDs,
                        'reccount' => count($qresult['records'])
                    ];
                }
            }
        } else {
            //B. get recordset from query/search results
            $this->params['detail'] = 'ids';
            $qresult = $this->recordSearch($this->system, $this->params);

            if (isset($qresult['status']) && $qresult['status'] == HEURIST_OK) {
                $qresult = $qresult['data'];
            } else {
                $this->smarty_error_output($this->system, null);
            }
        }

        return $qresult;
    }

    /**
     * Handle empty result sets, output appropriate error or message.
     *
     * @param array $qresult The query result containing records and record count.
     * @return bool Returns false if result is empty, true otherwise.
     */
    private function handleEmptyResultSet($qresult)
    {
        $emptysetmessage = isset($this->params['emptysetmessage']) ? $this->params['emptysetmessage'] : null;

        if (!$qresult || !isset($qresult['records']) || !(intval($qresult['reccount']) > 0)) {
            if ($this->publishmode == 4) {
                //for calculation field
                echo USanitize::sanitizeString($emptysetmessage && $emptysetmessage != 'def' ? $emptysetmessage : '');
            } else {
                $error = $emptysetmessage && $emptysetmessage != 'def'
                    ? $emptysetmessage
                    : ($this->publishmode > 0
                        ? '<span style="color:#ff0000;font-weight:bold">Note: There are no records in this view. The URL will only show records to which the viewer has access. Unless you are logged in to the database, you can only see records which are marked as Public visibility</span>'
                        : '<span style="color:#ff0000;font-weight:bold">Search or Select records to see template output</span>');
                $this->smarty_error_output($this->system, $error);
            }
            return false;
        }
        return true;
    }

    /**
     * Process the template file or the template body.
     *
     * @return string The content of the template to be processed.
     */
    private function loadTemplateContent()
    {
        $template_file = isset($this->params['template']) ? basename($this->params['template']) : null;
        $template_body = isset($this->params['template_body']) ? $this->params['template_body'] : null;

        if ($template_file) {
            if (substr($template_file, -4) !== ".tpl") {
                $template_file .= ".tpl";
            }

            $template_path = $this->system->getSysDir('smarty-templates') . $template_file;
            if (file_exists($template_path)) {
                return file_get_contents($template_path);
            } else {
                $error = '<span style="color:#ff0000;font-weight:bold">Template file ' . htmlspecialchars($template_file) . ' does not exist</span>';
                $this->smarty_error_output($this->system, $error);
                return false;
            }
        } else {
            $content = $template_body;
            if ($this->publishmode != 4) {
                $this->publishmode = 0; //always html in test/snippet mode
            }
            $this->outputmode = 'html';
            return $content;
        }
    }

    /**
     * Pre-parse the template to set allowed depth.
     *
     * @param string $content The content of the template.
     */
    private function preparseTemplate($content)
    {
        // NOT USED
        //verify that template has new features
        /*
        //need to detect $heurist->getRecord - if it is not found this is old version - show error message
        if($this->publishmode!=4 && strpos($content, '{foreach $results as ')>0 && strpos($content, '$heurist->getRecord(')===false){

               $error = '<p>To improve performance we have made some small changes to the report template specifications (July 2016).</p>'.
                        '<p>Please edit the report and add  <b>{$r = $heurist->getRecord($r)}</b><br>'
                        .'immediately after the start of the main record loop indicated by {foreach $results as $r}, like this:<p/>'
                        .'<p><br>{*------------------------------------------------------------*}'
                        .'<br>{foreach $results as $r}'
                        .'<br><b>{$r = $heurist->getRecord($r)}</b>'
                        .'<br>{*------------------------------------------------------------*}</p>'
                        .'<p><br>If your report format contains repeating value loops, you will also need to add similar<br>'
                        .'expressions to these loops, for example: {$r.f103 = $heurist->getRecord($r.f103)}.<br>'
                        .'Generate a new report to obtain examples, then cut and paste into your existing report.</p>'
                        .'<p>If you are stuck, please send your report template to <br>'
                        .'support at HeuristNetwork dot org and we will adjust the template for you.</p>';

               smarty_error_output($this->system, $error);
               return false; //exit;
        }
        */    
       
        $k = strpos($content, "{*depth");
        $kp = 8;
        if (is_bool($k) && !$k) {
            $k = strpos($content, "{* depth");
            $kp = 9;
        }

        if (is_numeric($k) && $k >= 0) {
            $nd = substr($content, $k + $kp, 1);
            if (is_numeric($nd) && $nd < 3) {
                $this->max_allowed_depth = $nd;
            }
        }
    }

    /**
     * Initialize the Smarty engine if it is not already initialized.
     */
    private function initSmarty()
    {
        if (!isset($this->smarty) || $this->smarty === null) {
            
            initSmarty($this->system->getSysDir('smarty-templates'));

            if (!isset($this->smarty) || $this->smarty === null) {
                $this->smarty_error_output($this->system, 'Cannot init Smarty report engine');
                exit;
            }
        }
    }

    /**
     * Execute the template using Smarty.
     *
     * @param array $qresult The result set containing records.
     * @param string $content The content of the template.
     * @return bool Returns true on successful execution, false otherwise.
     */
    public function executeTemplate($qresult, $content)
    {
        $results = $qresult["records"];  //reocrd ids
        $this->execution_total_counter = count($results);
        $heuristRec = new ReportRecord();

        if (method_exists($this->smarty, 'assignByRef')) {
            $this->smarty->assignByRef('heurist', $heuristRec); //deprecated 
        } else {
            $this->smarty->assign('heurist', $heuristRec);
        }

        $this->smarty->assign('results', $results);

        $facet_value = isset($this->params['facet_val']) ? htmlspecialchars($this->params['facet_val']) : null;
        if (!empty($facet_value)) {
            $this->smarty->assign('selected_term', $facet_value);
        }

        // Register Smarty plugins and modifiers
        try {
            $this->smarty->registerPlugin('modifier', 'file_data', [$heuristRec, 'getFileField']);
        } catch (Exception $e) {
            if (strpos($e, 'already registered') === false) {
                $this->smarty_error_output($this->system, $e);
                exit;
            }
        }

        ini_set('display_errors', 'false');
        
        // Execute the template and handle output filtering
        $result = $this->handleTemplateOutput($content, $results);

        // Handle activity logging if required
        //if (!$this->is_headless && !isset($this->params['template_body']) && !$this->is_fetch) {
        //    log_smarty_activity($this->system, $results);
        //}

        return $result;
    }

    /**
     * Handle template output and filtering based on the content and results.
     *
     * @param string $content The template content.
     * @param array $results The result records.
     * @return bool True on success, false otherwise.
     */
    protected function handleTemplateOutput($content, $results)
    {
        $result = true;
        $need_output_filter = true;
        
        $this->setupErrorReporting($this->replevel);

        if (@$this->params['template_body']) {

            $template_file = $this->saveTemporaryTemplate($content);
            if ($this->publishmode == 4) {
                $this->smarty->assign('r', (new ReportRecord())->getRecord($results[0]));
                $output = $this->smarty->fetch($template_file);
                echo $output;
                return true;
            }
        } else {
            if (!$template_file) {
                $template_file = 'test01.tpl';
            }
            if ($this->outputfile) {
                $this->smarty->registerFilter('output', 'smarty_output_filter');
                $need_output_filter = false;
            } elseif ($this->outputmode === 'js') {
                $this->smarty->registerFilter('output', 'smarty_output_filter_wrap_js');
                $need_output_filter = false;
            }
        }

        if ($need_output_filter) {
            $this->smarty->registerFilter('output', 'smarty_output_filter_strip_js');
        }

        $this->smarty->registerFilter('pre', 'smarty_pre_filter');
        if ($this->publishmode == 0 && $this->smarty_session_id > 0) {
            $this->smarty->registerFilter('post', 'smarty_post_filter');
        }

        $this->smarty->assign('template_file', $template_file);
        try {
            if ($this->outputfile === null && !$this->is_included) {
                $this->setOutputHeaders();
            }

            if ($this->is_fetch) {
                $this->smarty->fetch($template_file);
            } else {
                $this->smarty->display($template_file); //browser output
            }
        } catch (Exception $e) {
            $this->smarty_error_output($this->system, 'Exception on execution: ' . $e->getMessage());
            $result = false;
        }

        if ($this->smarty_session_id > 0) {
            mysql__update_progress(null, $this->smarty_session_id, false, 'REMOVE');
        }

        return $result;
    }
    
    private function setupErrorReporting($replevel){
        
        $this->smarty->debugging = false;
        $this->smarty->error_reporting = 0;
        
        if($replevel==1 || $replevel==2){
            ini_set( 'display_errors' , 'true');// 'stdout' );
            if($replevel==2){
                $smarty->error_reporting = E_ALL & ~E_STRICT & ~E_NOTICE;
            }else{
                $smarty->error_reporting = E_NOTICE;
            }
        }else{
            ini_set( 'display_errors' , 'false');
            $smarty->debugging = ($replevel==3);
        }
        if($replevel>0){
            $smarty->debug_tpl = dirname(__FILE__).'/debug_html.tpl';
        }
        
    }

    // Add other helper functions like saveTemporaryTemplate(), setOutputHeaders(), etc.
}
