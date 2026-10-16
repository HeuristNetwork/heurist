<?php
/**
* ReportController.php - Class ReportController
*
* Handler actions for report templates.
*
* @project     Heurist academic knowledge management system
* @package Controller
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.6
*/
namespace hserv\controller;

use hserv\System;
use hserv\report\ReportTemplateMgr;
use hserv\report\ReportExecute;
use hserv\structure\ConceptCode;
use hserv\utilities\USanitize;

/**
 * Class ReportController
 *
 * This class handles report-related actions such as executing, updating, listing,
 * importing, and exporting report templates.
 *
 */
class ReportController
{
    /**
     * @var System The system instance for managing core functionalities.
     */
    private $system;

    /**
     * @var string The directory path for templates.
     */
    private $dir;

    /**
     * @var ReportTemplateMgr Manages report template-related actions.
     */
    private $repAction;

    /**
     * @var array The request parameters, sanitized from GET or POST.
     */
    private $req_params;

    /**
     * ReportController constructor.
     *
     * Initializes the request parameters, sets up the system, and the directory for templates.
     * Also creates an instance of the ReportTemplateMgr class for managing report actions.
     *
     * @param System $system The system instance.
     * @param array|null $params Optional array of request parameters.
     *
     * @return void
     */
    public function __construct($system, $params = null)
    {
        $this->req_params = is_array($params) ? $params : USanitize::sanitizeInputArray();

        if (!isset($system)) {
            $system = new System();
            if (!$system->init(@$this->req_params['db'])) {
                dataOutput($system->getError());
                return null;
            }
        }

        $this->system = $system;

        if (defined('HEURIST_SMARTY_TEMPLATES_DIR')) {
            $this->dir = HEURIST_SMARTY_TEMPLATES_DIR;
        } else {
            $this->dir = $this->system->getSysDir('smarty-templates');
        }

        $this->repAction = new ReportTemplateMgr($this->system, $this->dir);
    }

    /**
     * Handles different actions related to report management based on the input action.
     *
     * @param string|null $action The action to be performed such as 'execute', 'update', 'list', etc.
     *
     * @return void
     */
    public function handleRequest($action)
    {
        $result = null;
        $mimeType = null;
        $filename = null;

        try {

            if ($this->req_params['template_id'] > 0 || $this->req_params['id'] > 0) {
                $action = 'update';
            }

            $template_file = $this->getTemplateFileName($action);
            $template_body = $this->getTemplateBody();
            
            if ($template_file && $action == null) {
                $action = 'execute'; //by default
            }
            
            switch ($action) {
                case 'execute':
                    if (!$this->isExecutionAllowed()) {
                        throw new \Exception('Reports for a set of records cannot be run on request in this database. '
                            .'Please use a generated report.');
                    }
                    $repExec = new ReportExecute($this->system, $this->req_params);
                    $repExec->execute();
                    break;

                case 'update':
                    $this->updateTemplate();
                    break;

                case 'list':
                    if(@$this->req_params['cms']){
                        $result = $this->repAction->getListForCms($this->req_params['cms']);
                    }else{
                        $result = $this->repAction->getList();    
                    }
                    break;

                case 'get':
                    $this->repAction->downloadTemplate($template_file, @$this->req_params['cms']);
                    break;

                case 'save':
                    $result = $this->saveTemplate($template_body, $template_file);
                    break;

                case 'delete':
                    $result = $this->repAction->deleteTemplate($template_file);
                    break;

                case 'import':
                    $result = $this->importTemplate();
                    break;

                case 'export':
                    $is_check_only = array_key_exists('check', $this->req_params);
                    $result = $this->repAction->exportTemplate($template_file, $is_check_only, null);
                    break;

                case 'check':
                    $this->repAction->checkTemplate($template_file);
                    $result = 'exist';
                    break;

                case 'rename':
                    $result = $this->repAction->renameTemplate($template_file, @$this->req_params['new_name']);
                    break;

                default:
                    throw new \Exception('Invalid "action" parameter');
            }
        } catch (\Exception $e) {
            $result = false;
            $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage());
        }

        if (isset($result)) {
            if ($mimeType == null) {
                if (is_bool($result) && $result == false) {
                    $result = $this->system->getError();
                } else {
                    $result = ['status' => HEURIST_OK, 'data' => $result];
                }
            }
            dataOutput($result, $filename, $mimeType);
        }
    }

    /**
     * Output file names of Report Schedule records (2-1105) in the current database.
     * Concept codes are resolved by query, not constants: cron loops over databases.
     *
     * @return array<int,string>
     */
    private function convertedScheduleOutputs()
    {
        ConceptCode::setSystem($this->system);
        $rty = intval(ConceptCode::getRecTypeLocalID('2-1105'));
        $dty = intval(ConceptCode::getDetailTypeLocalID('2-62'));
        if ($rty < 1 || $dty < 1) {
            return array();
        }
        return mysql__select_list2($this->system->getMysqli(),
            'SELECT DISTINCT d.dtl_Value FROM Records r JOIN recDetails d ON d.dtl_RecID=r.rec_ID AND d.dtl_DetailTypeID='.$dty
            .' WHERE r.rec_RecTypeID='.$rty.' AND r.rec_FlagTemporary=0');
    }

    /**
     * Whether an "execute" request may run (plan 12, Smarty reports).
     *
     * Always allowed: one record (q=ids:N or a one-id recordset), calculated
     * fields (publish=4) and the template editor test of a logged-in user
     * (template_body, limited by the smarty-output-limit preference).
     * Any other record-set run requires the database setting
     * Reports.allowDynamicReports (true when the settings file is missing).
     *
     * @return bool
     */
    private function isExecutionAllowed()
    {
        $settings = $this->system->settings->getDatabaseSetting('Reports');
        if (!is_array($settings) || !array_key_exists('allowDynamicReports', $settings)
            || filter_var($settings['allowDynamicReports'], FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $params = $this->req_params;
        if (intval(@$params['publish']) == 4) {
            return true;
        }
        if (!empty($params['template_body']) && $this->system->getUserId() > 0) {
            return true;
        }
        if (isset($params['q']) && is_string($params['q'])
            && preg_match('/^\s*ids:\s*\d+\s*$/', urldecode($params['q']))) {
            return true;
        }
        if (isset($params['recordset'])) {
            $recordset = is_array($params['recordset']) ? $params['recordset'] : json_decode($params['recordset'], true);
            $ids = is_array($recordset) ? prepareIds(@$recordset['recIDs']) : array();
            return count($ids) == 1;
        }
        return false;
    }

    /**
     * Saves the report template to the system.
     *
     * @param string $template_body The body of the template.
     * @param string $template_file The file name of the template.
     *
     * @return mixed The result of the template save operation.
     */
    private function saveTemplate($template_body, $template_file)
    {
        return $this->repAction->saveTemplate($template_body, $template_file);
    }

    /**
     * Imports a template either from CMS or an uploaded file.
     *
     * @return mixed The result of the import operation.
     */
    private function importTemplate()
    {
        $params = null;
        $for_cms = null;
        if (isset($this->req_params['import_template']['cms_tmp_name'])) {
            $for_cms = basename($this->req_params['import_template']['cms_tmp_name']);
            $params['size'] = 999;
            $params['name'] = $this->req_params['import_template']['name'];
        } else {
            $params = $_FILES['import_template'];
        }

        return $this->repAction->importTemplate($params, $for_cms);
    }

    /**
     * Retrieves the file name of the report template from the request parameters.
     *
     * @return string|null The sanitized template file name.
     */
    private function getTemplateFileName($action)
    {
        if (array_key_exists('template', $this->req_params)) {
            
            $templateName = USanitize::sanitizeFileName(basename(urldecode($this->req_params['template'])), false);

            if($action==='get' &&  strpos($this->req_params['template'],'def/')===0){
                $templateName = 'def/'.$templateName;
            }
            
            return $templateName;
        }

        return null;
    }

    /**
     * Retrieves the body of the report template from the request parameters.
     *
     * @return string|null The template body.
     */
    private function getTemplateBody()
    {
        return array_key_exists('template_body', $this->req_params) ? $this->req_params['template_body'] : null;
    }

    /**
     * Updates the report template based on the request parameters.
     *
     * @return array The result of the update operation including error, created, and updated counts.
     */
    public function updateTemplate()
    {
        $result_report = [0, 0, 0, 0, [], []];
        $is_void = false;

        $rps_ID = intval($this->req_params['template_id'] ?? @$this->req_params['id']);
        $publishmode = $this->req_params['publish'] ?? 4;
        $query = 'SELECT * FROM usrReportSchedule';

        if ($rps_ID > 0) {
            $query .= ' WHERE rps_ID=' . $rps_ID;
        } else {
            $publishmode = 4;
        }

        if ($publishmode == 4) {
            $publishmode = 3;
            $is_void = true;
        }

        $repExec = new ReportExecute($this->system);
        $repExec->setParameters(['publish' => 3, 'void' => $is_void]);

        if (!$repExec->initSmarty(true)) {
            $result_report[5]['fatal'] = $repExec->getError();
            return $result_report;
        }

        // rows converted to Report Schedule records are generated by runReportSchedules.php
        $converted = $rps_ID > 0 ? array() : $this->convertedScheduleOutputs();

        $res = $this->system->getMysqli()->query($query);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (in_array((string)$row['rps_FileName'], $converted, true)) {
                    continue;
                }
                $format = $this->req_params['mode'] ?? @$row['rps_URL'];
                $params = [
                    'publish' => $publishmode,
                    'mode' => $format,
                    'output' => $row['rps_FileName'] ?? $row['rps_Template'],
                    'template' => $row['rps_Template'],
                    'rps_id' => $row['rps_ID'],
                    'void' => $is_void
                     
                ];


                $hquery = $row['rps_HQuery'];
                if (strpos($hquery, "&q=") > 0) {
                    parse_str($hquery, $params2);
                    $params = array_merge($params, $params2);
                } else {
                    $params['q'] = $hquery; //was incorrect $params = ['q'=>$hquery];
                }

                $repExec->setParameters($params);

                //result: 0 - error, 1 - created, 2 - updated, 3 - intacted
                //check that report is already exists
                $result = 1;

                if ($publishmode == 3) {
                    $result = $repExec->outputGeneratedReport($row['rps_IntervalMinutes']);
                }

                if ($result != 3) {
                    $proc_start = time();

                    if (!$repExec->execute()) {
                        $result = 0;
                        $result_report[5][$row['rps_ID'] . ' ' . basename($row['rps_Template'])] = $repExec->getError();
                    }

                    $proc_length = time() - $proc_start;
                    if ($proc_length > 10) {
                        $result_report[4][$row['rps_ID'] . ' ' . basename($row['rps_Template'])] = $proc_length;
                    }
                }

                $result_report[$result]++;
            }
            $res->close();
        }

        return $result_report;
    }
}
