<?php
/**
* SmartyReportRenderer.php - Legacy Smarty engine behind the srv/ reports interface
*
* It will be removed as soon as ReportTemplateMgr (export, import) will be ported to /srv
* ReportExecute is not used in /srv already
* 
* Implements Heurist\Reports\ReportRendererInterface with ReportExecute and
* ReportTemplateMgr, so the modern reports API does not depend on hserv classes.
*
* @project     Heurist academic knowledge management system
* @package     Report
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/
namespace hserv\report;

use Heurist\Jobs\JobContext;
use Heurist\Reports\ReportRendererInterface;
use hserv\utilities\USanitize;

/**
 * Smarty report execution and template conversion for /api/{db}/reports.
 */
class SmartyReportRenderer implements ReportRendererInterface
{
    /** @var \hserv\System Initialised legacy system. */
    private $system;

    /** @var ReportTemplateMgr Template file manager. */
    private $templates;

    /**
     * @param \hserv\System $system Initialised legacy system.
     */
    public function __construct($system)
    {
        $this->system = $system;
        $this->templates = new ReportTemplateMgr($system, $system->getSysDir('smarty-templates'));
    }

    /**
     * Render one record as an HTML snippet. Same parameters as the legacy popup
     * URL (?template=..&q=ids:N&publish=1&snippet=1): output to the response only.
     *
     * @param string $templateFile Template file name.
     * @param int $recordId Record id.
     * @return string Rendered HTML or the engine's error message.
     */
    public function renderRecord(string $templateFile, int $recordId): string
    {
        $execute = new ReportExecute($this->system, array(
            'template' => $templateFile,
            'q' => 'ids:'.$recordId,
            'publish' => 1,
            'snippet' => 1,
            'mode' => 'html'
        ));

        ob_start();
        try{
            $execute->execute();
        } finally {
            $html = ob_get_clean();
        }
        return $html === false ? '' : $html;
    }

    /**
     * Run a template on records with ReportExecute: a test run (publish=0) or a
     * generated file (publish=1 to a temporary file that is read and removed).
     * The new API uses the srv engine; this is used by the engine comparison
     * tests only, so a job context gives no progress or Stop inside the run.
     *
     * @param array $source ['file' => template] or ['body' => template text].
     * @param array $ids Record ids.
     * @param array $options purpose (preview|file), mode, replevel.
     * @param JobContext|null $context Running job (not used).
     * @return array{output:string,error:?string}
     */
    public function renderIds(array $source, array $ids, array $options = array(), ?JobContext $context = null): array
    {
        $ids = array_values(array_map('intval', $ids));
        $purpose = $options['purpose'] ?? 'preview';
        $params = array();
        if (isset($source['body'])) {
            $params['template_body'] = (string)$source['body'];
        } else {
            $params['template'] = (string)($source['file'] ?? '');
        }

        $tempFile = null;
        if ($purpose === 'file') {
            $tempName = 'job-tmp-'.substr(md5(uniqid('', true)), 0, 12);
            $mode = (string)($options['mode'] ?? 'html');
            $params += array(
                'recordset' => array('records' => $ids, 'reccount' => count($ids)),
                'publish' => 1,
                'output' => $tempName,
                'mode' => $mode,
                'void' => true
            );
            // the same name as ReportExecute::prepareOutputFile() writes
            $tempFile = rtrim((string)$this->system->getSysDir('generated-reports'), '/').'/'
                .USanitize::sanitizeFileName($tempName).'.'.$mode;
        } else {
            $params += array(
                'publish' => 0,
                'recordset' => array('recIDs' => $ids, 'recordCount' => count($ids)),
                'limit' => \Heurist\Reports\ReportPolicy::TEST_RECORD_LIMIT,
                'replevel' => intval($options['replevel'] ?? 0)
            );
        }

        $execute = new ReportExecute($this->system, $params);

        // a job runs after its HTTP response was sent: header() calls of the
        // engine must not add warnings to the report text
        set_error_handler(function ($errno, $errstr) {
            return strpos($errstr, 'Cannot modify header information') !== false;
        }, E_WARNING);
        ob_start();
        try {
            $ok = $execute->execute();
        } finally {
            $output = ob_get_clean();
            restore_error_handler();
        }

        try {
            $error = $execute->getError();
            if ($tempFile !== null) {
                if ($ok && $error === null) {
                    if (!is_file($tempFile)) {
                        $error = 'The report file was not written. Check permissions for the generated-reports folder';
                    } else {
                        $output = (string)file_get_contents($tempFile);
                    }
                } elseif ($error === null) {
                    $error = 'Report execution failed';
                }
            }
        } finally {
            if ($tempFile !== null && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }

        return array('output' => $output === false ? '' : (string)$output, 'error' => $error);
    }


    /**
     * Import an uploaded template, converting concept codes to local ids.
     *
     * @param array $upload Uploaded file entry.
     * @return string Saved template file name.
     */
    public function importTemplate(array $upload): string
    {
        $result = $this->templates->importTemplate($upload);
        return (string)$result['filename'];
    }

    /**
     * Template body with local ids converted to concept codes.
     *
     * @param string $templateFile Template file name.
     * @return string Exported template body.
     */
    public function exportTemplate(string $templateFile): string
    {
        if (!$this->system->settings->get('sys_dbRegisteredID')) {
            throw new \DomainException('Database must be registered to allow translation of local template to global template');
        }
        $path = $this->templates->checkTemplate($templateFile);
        $content = $this->templates->convertTemplate(file_get_contents($path), 0);
        if (is_array($content)) {
            if (isset($content['error'])) {
                throw new \RuntimeException($content['error']);
            }
            $content = (string)($content['template'] ?? '');
        }
        return (string)$content;
    }
}
