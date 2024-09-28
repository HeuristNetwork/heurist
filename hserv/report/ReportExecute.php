<?php
/*
An array of the form array($object, $method) with $object being a reference to an object and $method being a string containing the method-name

*/

class ReportExecute
{
    protected $smarty;
    protected $outputfile;
    protected $outputmode;
    protected $gparams;
    protected $max_allowed_depth = 2;
    protected $publishmode;
    protected $execution_counter;
    protected $execution_total_counter;
    protected $is_included;
    protected $is_jsallowed;
    protected $record_with_custom_styles;
    protected $is_headless;
    protected $smarty_session_id;

    protected $system;
    protected $params;

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

        // Initialize properties from parameters or set defaults
        $this->outputfile = isset($params["output"]) ? htmlspecialchars($params["output"]) : null;
        $this->publishmode = isset($params['publish']) ? intval($params['publish']) : 0;
        $this->record_with_custom_styles = isset($params['cssid']) ? intval($params['cssid']) : null;
        $this->is_headless = isset($params['snippet']) && $params['snippet'] == 1;
        $this->outputmode = isset($params['mode']) ? preg_replace('/[^a-z]/', "", $params["mode"]) : 'html';
        $this->smarty_session_id = isset($params['session']) ? $params['session'] : null;
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

        $this->is_jsallowed = $this->system->isJavaScriptAllowed();
        $mysqli = $this->system->get_mysqli();

        set_time_limit(0); // No script execution time limit

        // Handle publishing modes and output types
        $this->handleOutputMode();

        $params = $this->params;
        $params["f"] = 1; // Always search (do not use cache)
        $params["void"] = isset($params["void"]) && $params["void"] == true;

        $this->gparams = $params; // Store parameters globally

        // Define search limits
        $this->setSearchLimit();

        // Fetch record IDs
        $recIDs = [];
        $qresult = $this->fetchRecordIDs($recIDs);

        // Handle empty result set
        if (!$this->handleEmptyResultSet($qresult)) {
            return true;
        }

        // Process template file or template body
        $content = $this->processTemplate();

        // Pre-parse template to set allowed depth
        $this->preparseTemplate($content);

        // Initialize Smarty if necessary
        $this->initSmarty();

        // Assign variables and execute the template
        return $this->executeTemplate($qresult, $content);
    }

    /**
     * Handle the output mode and set appropriate flags.
     */
    protected function handleOutputMode()
    {
        if ($this->outputmode !== 'js' && $this->outputmode !== 'html') {
            $this->is_headless = true;
        }
        if ($this->outputmode === 'text') {
            $this->outputmode = 'txt';
        }
    }

    /**
     * Set the search limit based on publishing mode or user preferences.
     */
    protected function setSearchLimit()
    {
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
    }

    /**
     * Fetch record IDs based on the provided query parameters.
     *
     * @param array $recIDs Reference to the array where record IDs will be stored.
     * @return array The query result containing records and record count.
     */
    protected function fetchRecordIDs(&$recIDs)
    {
        if (isset($this->params['recordset'])) {
            if (is_array($this->params['recordset'])) {
                $qresult = $this->params['recordset'];
            } else {
                $qresult = json_decode($this->params['recordset'], true);
            }

            if ($this->publishmode == 0 && $qresult && isset($qresult['recIDs'])) {
                $recIDs = $this->prepareIds($qresult['recIDs']);
                if ($this->params["limit"] < count($recIDs)) {
                    $qresult = [
                        'records' => array_slice($recIDs, 0, $this->params["limit"]),
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
    protected function handleEmptyResultSet($qresult)
    {
        $emptysetmessage = isset($this->params['emptysetmessage']) ? $this->params['emptysetmessage'] : null;

        if (!$qresult || !isset($qresult['records']) || !(intval($qresult['reccount']) > 0)) {
            if ($this->publishmode == 4) {
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
    protected function processTemplate()
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
                $this->publishmode = 0;
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
    protected function preparseTemplate($content)
    {
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
    protected function initSmarty()
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
    protected function executeTemplate($qresult, $content)
    {
        $results = $qresult["records"];
        $this->execution_total_counter = count($results);
        $heuristRec = new ReportRecord();

        if (method_exists($this->smarty, 'assignByRef')) {
            $this->smarty->assignByRef('heurist', $heuristRec);
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
            if (method_exists($this->smarty, 'registerPlugin')) {
                $this->smarty->registerPlugin('modifier', 'file_data', [$heuristRec, 'getFileField']);
                $this->smarty->registerPlugin('modifier', 'translate', 'getTranslation');
            } else {
                $this->smarty->register_modifier('file_data', [$heuristRec, 'getFileField']);
                $this->smarty->register_modifier('translate', 'getTranslation');
            }
        } catch (Exception $e) {
            if (strpos($e, 'already registered') === false) {
                $this->smarty_error_output($this->system, $e);
                exit;
            }
        }

        ini_set('display_errors', 'false');
        $this->smarty->error_reporting = 0;

        // Execute the template and handle output filtering
        $result = $this->handleTemplateOutput($content, $results);

        // Handle activity logging if required
        if (!$this->is_headless && !isset($this->params['template_body']) && !$this->params["void"]) {
            log_smarty_activity($this->system, $results);
        }

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

        if ($this->params['template_body']) {
            $replevel = isset($this->params['replevel']) ? $this->params['replevel'] : 0;
            $this->setupErrorReporting($replevel);

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

            $this->smarty->debugging = false;
            $this->smarty->error_reporting = 0;

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

            if ($this->gparams['void']) {
                $this->smarty->fetch($template_file);
            } else {
                $this->smarty->display($template_file);
            }
        } catch (Exception $e) {
            $this->smarty_error_output($this->system, 'Exception on execution: ' . $e->getMessage());
            $result = false;
        }

        if ($this->smarty_session_id > 0) {
            mysql__update_progress($this->system->get_mysqli(), $this->smarty_session_id, false, 'REMOVE');
        }

        return $result;
    }

    // Add other helper functions like setupErrorReporting(), saveTemporaryTemplate(), setOutputHeaders(), etc.
}
