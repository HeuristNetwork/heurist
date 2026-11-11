<?php
/**
* ReportService.php - Templates of the Custom Report records
*
* Reads, writes, imports, exports and runs the template of a report record, and tells the
* state of the report definitions (plan 30). A report is named by its record id only; the
* template file stays behind the record. A template file without a record is not run:
* it is listed by status() and registered, or deleted, first.
*
* @project     Heurist academic knowledge management system
* @package     Reports
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports;

use DomainException;
use Heurist\Records\Edit\RecordInput;
use Heurist\Records\Edit\RecordSaveService;
use Heurist\Runtime\SystemCode;
use InvalidArgumentException;
use OutOfBoundsException;

/** Templates of report records. */
final class ReportService
{
    private ReportRepository $reports;
    private ReportTemplateStore $templates;
    private ReportPolicy $policy;
    private ReportRendererInterface $renderer;
    private ReportRecordWriterInterface $writer;
    /** @var callable|bool Database may run JavaScript in reports (or a callable that tells it). */
    private $javaScriptAllowed;
    /** @var callable():RecordSaveService|null */
    private $recordSave;
    private ?SystemCode $codes;

    /**
     * Initialise the service from explicit dependencies.
     *
     * @param callable():RecordSaveService $recordSave The record save of the imported templates.
     */
    public function __construct(
        ReportRepository $reports,
        ReportTemplateStore $templates,
        ReportPolicy $policy,
        ReportRendererInterface $renderer,
        ReportRecordWriterInterface $writer,
        $javaScriptAllowed = false,
        ?callable $recordSave = null,
        ?SystemCode $codes = null
    ) {
        $this->javaScriptAllowed = $javaScriptAllowed;
        $this->recordSave = $recordSave;
        $this->codes = $codes;
        $this->reports = $reports;
        $this->templates = $templates;
        $this->policy = $policy;
        $this->renderer = $renderer;
        $this->writer = $writer;
    }

    /**
     * GET /report/setup: the state of the definitions, what the client needs to know about the report
     * output, and the template files without a record.
     */
    public function status(): array
    {
        $this->policy->requireMember();
        return array(
            'installed' => $this->reports->isInstalled(),
            'missing' => $this->reports->missingDefinitions(),
            'canSetup' => $this->policy->isManager(),
            'allowDynamicReports' => $this->policy->allowDynamicReports(),
            'settings' => array(
                'javaScriptAllowed' => $this->javaScriptAllowed(),
                'testRecordLimit' => ReportPolicy::TEST_RECORD_LIMIT
            ),
            'unregistered' => $this->unregisteredFiles()
        );
    }

    /** Install the report definitions and convert old schedules. Managers only. */
    public function setup(): array
    {
        $this->policy->requireManager();
        return array('report' => $this->writer->setup());
    }

    /** Whether the database may run JavaScript and CSS blocks in reports (js_in_database_authorised.txt). */
    private function javaScriptAllowed(): bool
    {
        if(is_callable($this->javaScriptAllowed)){
            $this->javaScriptAllowed = (bool)call_user_func($this->javaScriptAllowed);
        }
        return (bool)$this->javaScriptAllowed;
    }

    /** Template body of a report: `{report, body}`. Logged-in users only. */
    public function readTemplate(int $reportId): array
    {
        $this->policy->requireMember();
        $report = $this->report($reportId);
        if($report['file'] === ''){
            throw new OutOfBoundsException('The report has no template file');
        }
        return array('report' => $report, 'body' => $this->templates->read($report['file']));
    }

    /**
     * Save the template body of a report. Refused when another report record uses the same file.
     *
     * @throws DomainException|InvalidArgumentException
     */
    public function saveTemplate(int $reportId, string $body): array
    {
        $this->policy->requireMember();
        $report = $this->editable($reportId);
        if($report['file'] === ''){
            throw new InvalidArgumentException('The report has no template file');
        }
        $this->assertOwnFile($report['file']);
        $this->templates->write($report['file'], $body);
        return $this->report($reportId);
    }

    /**
     * Delete the template file of a report; the record stays. Refused while outputs point to the report,
     * or when another report record uses the same file.
     *
     * @throws DomainException|ReportInUseException
     */
    public function deleteTemplate(int $reportId): array
    {
        $this->policy->requireMember();
        $report = $this->editable($reportId);
        $this->assertNoOutputs($reportId);
        $deleted = $report['file'] !== '' && $this->templates->exists($report['file']);
        if($deleted){
            $this->assertOwnFile($report['file']);
            $this->templates->delete($report['file']);
        }
        return array('id' => $reportId, 'fileDeleted' => $deleted);
    }

    /**
     * A template file used by more than one report record is changed by none of them through the new API.
     * The new API never gives two records one file, but a legacy save, a batch update or an import of
     * 2-62 can: then the edit right on one report must not change the template of another.
     *
     * @throws DomainException
     */
    private function assertOwnFile(string $file): void
    {
        $uses = $this->reports->registeredFiles()[strtolower($file)] ?? 0;
        if($uses > 1){
            throw new DomainException('The template file '.$file.' is used by '.$uses
                .' report records. Give this report its own file (Save as) before changing it');
        }
    }

    /** @throws ReportInUseException Outputs point to the report. */
    public function assertNoOutputs(int $reportId): void
    {
        $count = $this->reports->countOutputs($reportId);
        if($count > 0){ throw new ReportInUseException($count); }
    }

    /** Delete a template file without a record: DELETE /report/setup?name=file name. */
    public function deleteUnregistered(string $file): array
    {
        $this->policy->requireMember();
        $name = $this->templates->normalize($file);
        if(isset($this->reports->registeredFiles()[strtolower($name)])){
            throw new InvalidArgumentException('Template '.$name.' has a report record: delete the record');
        }
        if(!$this->templates->exists($name)){
            throw new OutOfBoundsException('Template file '.$name.' does not exist');
        }
        $this->templates->delete($name);
        return array('file' => $name, 'fileDeleted' => true);
    }

    /**
     * Import an uploaded template (concept codes are converted to local ids) and create its record.
     *
     * @param array $upload The uploaded file ($_FILES entry).
     * @param bool $isCardView The new report is a card report.
     * @return array The new report.
     */
    public function importTemplate(array $upload, bool $isCardView = false): array
    {
        $this->policy->requireMember();
        if(empty($upload['tmp_name']) || !is_uploaded_file((string)$upload['tmp_name'])){
            throw new InvalidArgumentException('Template file is not uploaded');
        }
        if(!$this->reports->isInstalled()){
            throw new DomainException('The report record types are not installed: POST /report/setup');
        }
        $file = $this->renderer->importTemplate($upload);
        $details = array(
            $this->codes->id('DT_NAME') => array(substr($file, 0, -4)),
            $this->codes->id('DT_FILE_NAME') => array($file)
        );
        if($isCardView){ $details[$this->codes->id('DT_IS_CARD_VIEW')] = array($this->codes->id('TRM_YES')); }
        $saved = ($this->recordSave)()->save('POST', 0, RecordInput::fromBody(
            array('rec_RecTypeID' => $this->codes->id('RT_REPORT_TEMPLATE'), 'details' => $details)));
        return $this->report(intval($saved['recordId']));
    }

    /**
     * Export a template with concept codes.
     *
     * @return array{file:string,body:string} Download name (.gpl) and body.
     */
    public function exportTemplate(int $reportId): array
    {
        $this->policy->requireMember();
        $report = $this->report($reportId);
        if($report['file'] === '' || !$this->templates->exists($report['file'])){
            throw new OutOfBoundsException('Template file does not exist');
        }
        return array(
            'file' => substr($report['file'], 0, -4).'.gpl',
            'body' => $this->renderer->exportTemplate($report['file'])
        );
    }

    /**
     * Render one record with a report (GET /report/{id}/execute). The report must be visible to the user,
     * a guest runs card reports only; the engine applies the record visibility of the user. A wrong
     * template must not run for ever, so the time of a test run is the limit.
     */
    public function execute(int $reportId, int $recordId): string
    {
        if($recordId < 1){
            throw new InvalidArgumentException('Parameter "recordId" must be a record id');
        }
        $report = $this->report($reportId);
        if(!$this->policy->isMember() && !$report['isCardView']){
            throw new DomainException('Authentication is required');
        }
        if($report['file'] === '' || !$this->templates->exists($report['file'])){
            throw new OutOfBoundsException('Template file does not exist');
        }
        @set_time_limit($this->policy->previewLimitSeconds());
        return $this->renderer->renderRecord($report['file'], $recordId);
    }

    /** A report the current user sees, for the report jobs. */
    public function report(int $reportId): array
    {
        $report = $this->reports->getReport($reportId);
        if($report === null){
            throw new OutOfBoundsException('Report '.$reportId.' is not available');
        }
        $report['fileExists'] = $report['file'] !== '' && $this->templates->exists($report['file']);
        return $report;
    }

    /** True when the template file exists. */
    public function templateExists(string $file): bool
    {
        return $file !== '' && $this->templates->exists($file);
    }

    /** A report the current user may change. */
    private function editable(int $reportId): array
    {
        $report = $this->report($reportId);
        if(!$report['canEdit']){
            throw new DomainException('You are not allowed to change this report');
        }
        return $report;
    }

    /**
     * Template files without a Custom Report record.
     *
     * @return array<int,array{file:string,title:string,modified:?string}>
     */
    private function unregisteredFiles(): array
    {
        $registered = $this->reports->registeredFiles();
        $result = array();
        foreach($this->templates->listFiles() as $entry){
            if(!isset($registered[strtolower($entry['file'])])){
                $result[] = array('file' => $entry['file'], 'title' => substr($entry['file'], 0, -4), 'modified' => $entry['modified'] ?? null);
            }
        }
        return $result;
    }
}
