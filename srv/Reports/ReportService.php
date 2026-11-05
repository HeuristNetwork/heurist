<?php
/**
* ReportService.php - Reports manager operations
*
* A report is addressed by a reference: a Custom Report record id, or a template
* file name for a file that has no record yet ("unregistered"). Template bodies
* stay in the smarty-templates folder; the record keeps the file name.
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

/** Lists, edits, imports, exports and renders reports. */
final class ReportService
{
    private ReportRepository $reports;
    private ReportTemplateStore $templates;
    private ReportPolicy $policy;
    private ReportRendererInterface $renderer;
    private ReportRecordWriterInterface $writer;
    private string $generatedDirectory;
    private string $generatedUrl;
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
        string $generatedDirectory = '',
        string $generatedUrl = '',
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
        $this->generatedDirectory = $generatedDirectory === '' ? '' : rtrim($generatedDirectory, '/\\').'/';
        $this->generatedUrl = $generatedUrl === '' ? '' : rtrim($generatedUrl, '/').'/';
    }

    /**
     * The reports manager list.
     *
     * Anonymous users and `scope=card` get only visible report records. Logged-in
     * users also get the unregistered template files.
     *
     * @param string $scope "all", "card" (single-record reports) or "set" (record-set reports).
     */
    public function listReports(string $scope = 'all'): array
    {
        if(!in_array($scope, array('all', 'card', 'set'), true)){
            throw new InvalidArgumentException('Parameter "scope" must be all, card or set');
        }
        $reports = $this->reports->listReports($scope === 'card');
        if($scope === 'set'){
            $reports = array_values(array_filter($reports, static function(array $report): bool {
                return !$report['isCardView'];
            }));
        }

        if($this->policy->isMember()){ $reports = $this->withGeneratedCount($reports); }
        $result = array(
            'installed' => $this->reports->isInstalled(),
            'reports' => $reports
        );
        if($scope !== 'card' && $this->policy->isMember()){
            $result['unregistered'] = $this->unregisteredFiles();
            $result['missingDefinitions'] = $this->reports->missingDefinitions();
            $result['canSetup'] = $this->policy->isManager();
            $result['allowDynamicReports'] = $this->policy->allowDynamicReports();
            // what the client needs to know about the report output
            $result['settings'] = array(
                'javaScriptAllowed' => $this->javaScriptAllowed(),
                'testRecordLimit' => ReportPolicy::TEST_RECORD_LIMIT
            );
        }
        return $result;
    }

    /** Whether the database may run JavaScript and CSS blocks in reports (js_in_database_authorised.txt). */
    private function javaScriptAllowed(): bool
    {
        if(is_callable($this->javaScriptAllowed)){
            $this->javaScriptAllowed = (bool)call_user_func($this->javaScriptAllowed);
        }
        return (bool)$this->javaScriptAllowed;
    }

    /** One report record (by id) or unregistered file (by name). */
    public function getReport(string $reference): array
    {
        $report = $this->resolve($reference);
        if($report['id'] === null){ $this->policy->requireMember(); }
        return $report;
    }

    /** Template body of a report. Logged-in users only. */
    public function readTemplate(string $reference): array
    {
        $this->policy->requireMember();
        $report = $this->resolve($reference);
        if($report['file'] === ''){
            throw new OutOfBoundsException('The report has no template file');
        }
        return array('report' => $report, 'body' => $this->templates->read($report['file']));
    }

    /** Save the template body of an existing report or unregistered file. */
    public function saveTemplate(string $reference, string $body): array
    {
        $this->policy->requireMember();
        $report = $this->resolve($reference);
        $this->requireEdit($report);
        if($report['file'] === ''){
            throw new InvalidArgumentException('The report has no template file');
        }
        $this->templates->write($report['file'], $body);
        return $this->resolve($reference);
    }

    /** Delete a template file without a record. A report is deleted by DELETE /report/{id}. */
    public function deleteFile(string $reference): array
    {
        $this->policy->requireMember();
        $report = $this->resolve($reference);
        $this->requireEdit($report);
        if($report['id'] !== null){
            throw new InvalidArgumentException('Template '.$report['file'].' has a report record: delete the record');
        }
        $this->templates->delete($report['file']);
        return array('id' => null, 'file' => $report['file'], 'fileDeleted' => true);
    }

    /**
     * Import an uploaded template (concept codes are converted to local ids) and
     * create its record when the definitions are installed.
     */
    public function importTemplate(array $upload, bool $isCardView = false): array
    {
        $this->policy->requireMember();
        if(empty($upload['tmp_name']) || !is_uploaded_file((string)$upload['tmp_name'])){
            throw new InvalidArgumentException('Template file is not uploaded');
        }
        $file = $this->renderer->importTemplate($upload);
        if(!$this->reports->isInstalled()){
            return $this->resolve($file);
        }
        $details = array(
            $this->codes->id('DT_NAME') => array(substr($file, 0, -4)),
            $this->codes->id('DT_FILE_NAME') => array($file)
        );
        if($isCardView){ $details[$this->codes->id('DT_IS_CARD_VIEW')] = array($this->codes->id('TRM_YES')); }
        $saved = ($this->recordSave)()->save('POST', 0, RecordInput::fromBody(
            array('rec_RecTypeID' => $this->codes->id('RT_CUSTOM_REPORT'), 'details' => $details)));
        return $this->resolve((string)$saved['recordId']);
    }

    /**
     * Export a template with concept codes.
     *
     * @return array{file:string,body:string} Download name (.gpl) and body.
     */
    public function exportTemplate(string $reference): array
    {
        $this->policy->requireMember();
        $report = $this->resolve($reference);
        if($report['file'] === '' || !$this->templates->exists($report['file'])){
            throw new OutOfBoundsException('Template file does not exist');
        }
        return array(
            'file' => substr($report['file'], 0, -4).'.gpl',
            'body' => $this->renderer->exportTemplate($report['file'])
        );
    }

    /**
     * Render one record with a report template. Always allowed (plan 12, decision 3);
     * the engine applies the record visibility of the current user.
     */
    public function renderRecord(string $reference, int $recordId): string
    {
        if($recordId < 1){
            throw new InvalidArgumentException('Parameter "rec" must be a record id');
        }
        if(ctype_digit($reference)){
            $report = $this->resolve($reference);
            $file = $report['file'];
        }else{
            // legacy configurations store the template file name
            $file = $this->templates->normalize($reference);
        }
        if($file === '' || !$this->templates->exists($file)){
            throw new OutOfBoundsException('Template file does not exist');
        }
        return $this->renderer->renderRecord($file, $recordId);
    }

    /**
     * Which report each generated file belongs to (plan 27e §24): the schedule with this output, else the report with the
     * longest name followed by a space (a suffix), "_" (a user) or ".". A plain prefix would give `Single` also the files of
     * `Single22` and of `Single _copy`.
     *
     * @param string[] $names File names in generated-reports.
     * @param array<int,array> $reports Reports with `id`, `file`, `schedules`.
     * @return array<string,string[]> File names by report id, or file name of a report without a record.
     */
    public static function assignGenerated(array $names, array $reports): array
    {
        $outputs = array();
        $bases = array();
        foreach($reports as $report){
            $key = (string)($report['id'] ?? $report['file']);
            foreach($report['schedules'] ?? array() as $schedule){
                $output = self::scheduleOutput((string)$report['file'], (string)($schedule['file'] ?? ''));
                $outputs[strtolower($output.'.'.($schedule['format'] ?? 'html'))] = $key;
            }
            $base = strtolower(self::outputBase((string)$report['file']));
            if($base !== ''){ $bases[$base] = $key; }
        }
        // the longest name first
        uksort($bases, static function($a, $b): int { return strlen((string)$b) - strlen((string)$a); });
        $result = array();
        foreach($names as $name){
            $lower = strtolower($name);
            $key = $outputs[$lower] ?? null;
            foreach($key === null ? $bases : array() as $base => $report){
                $base = (string)$base;
                if(strpos($lower, $base) === 0 && in_array($lower[strlen($base)] ?? '', array(' ', '_', '.'), true)){
                    $key = $report;
                    break;
                }
            }
            if($key !== null){ $result[(string)$key][] = $name; }
        }
        return $result;
    }

    /** The reports of the list with the number of their generated files. */
    private function withGeneratedCount(array $reports): array
    {
        $files = self::assignGenerated($this->generatedNames(), $this->allReports());
        foreach($reports as $index => $report){
            $reports[$index]['generatedCount'] = count($files[(string)($report['id'] ?? $report['file'])] ?? array());
        }
        return $reports;
    }

    /** All reports with a record and the template files without one: every file has its report. */
    private function allReports(): array
    {
        return array_merge($this->reports->listReports(), $this->unregisteredFiles());
    }

    /** @return string[] File names in generated-reports. */
    private function generatedNames(): array
    {
        if($this->generatedDirectory === '' || !is_dir($this->generatedDirectory)){ return array(); }
        $names = array();
        foreach(scandir($this->generatedDirectory) ?: array() as $name){
            // "job-tmp-*": output of a running generation job (ReportGenerateJob)
            if($name[0] === '.' || $name[0] === '_' || $name === 'index.html' || strpos($name, 'job-tmp-') === 0
                || !is_file($this->generatedDirectory.$name)){ continue; }
            $names[] = $name;
        }
        return $names;
    }

    /**
     * Files in the generated-reports folder.
     *
     * @return array<int,array{file:string,size:int,modified:string,url:string}>
     */
    public function listGenerated(string $prefix = '', string $report = ''): array
    {
        $this->policy->requireMember();
        $prefix = strtolower(trim($prefix));
        $names = $this->generatedNames();
        if($report !== ''){ $names = self::assignGenerated($names, $this->allReports())[$report] ?? array(); }
        $files = array();
        foreach($names as $name){
            $path = $this->generatedDirectory.$name;
            if($prefix !== '' && strpos(strtolower($name), $prefix) !== 0){ continue; }
            $files[] = array(
                'file' => $name,
                'size' => intval(filesize($path)),
                'modified' => gmdate('Y-m-d\TH:i:s\Z', intval(filemtime($path))),
                'url' => $this->generatedUrl === '' ? '' : $this->generatedUrl.rawurlencode($name)
            );
        }
        usort($files, static function(array $a, array $b): int { return strcmp($b['modified'], $a['modified']); });
        return $files;
    }

    /**
     * Delete a generated file. Allowed for managers, for the editors of the
     * report the file belongs to (name starts with the report's file name) and
     * for the user who generated it ("_u<user id>" in the name).
     *
     * @param string $file File name in generated-reports.
     * @return array{file:string,deleted:bool}
     */
    public function deleteGenerated(string $file): array
    {
        $this->policy->requireMember();
        $file = basename(trim($file));
        if($file === '' || $file[0] === '.' || $this->generatedDirectory === ''){
            throw new InvalidArgumentException('Invalid file name');
        }
        $path = $this->generatedDirectory.$file;
        if(!is_file($path)){
            throw new OutOfBoundsException('Generated file '.$file.' does not exist');
        }
        $allowed = $this->policy->isManager()
            || preg_match('/_u'.$this->policy->userId().'\.[a-z]+$/i', $file) === 1;
        if(!$allowed){
            foreach($this->reports->listReports() as $report){
                $base = strtolower(substr($report['file'], 0, -4));
                if($report['canEdit'] && $base !== '' && strpos(strtolower($file), $base) === 0){
                    $allowed = true;
                    break;
                }
            }
        }
        if(!$allowed){
            throw new DomainException('You are not allowed to delete this file');
        }
        if(!@unlink($path)){
            throw new \RuntimeException('Cannot delete '.$file.'. Check permissions for the generated-reports folder');
        }
        return array('file' => $file, 'deleted' => true);
    }

    /**
     * All visible schedules with the title of their report and their last
     * generated file (or null).
     *
     * @return array<int,array>
     */
    public function listSchedules(): array
    {
        $this->policy->requireMember();
        $titles = array();
        $files = array();
        foreach($this->reports->listReports() as $report){
            $titles[$report['id']] = $report['title'];
            $files[$report['id']] = $report['file'];
        }
        $result = array();
        foreach($this->reports->listSchedules() as $schedule){
            $schedule['reportTitle'] = $titles[$schedule['reportId']] ?? null;
            $output = self::scheduleOutput((string)($files[$schedule['reportId']] ?? ''), $schedule['file']);
            $schedule['generated'] = $this->generatedInfo($output.'.'.$schedule['format']);
            $result[] = $schedule;
        }
        usort($result, static function(array $a, array $b): int { return strcasecmp($a['title'], $b['title']); });
        return $result;
    }

    /** The template file name as the start of an output file name. */
    private static function outputBase(string $file): string
    {
        return trim((string)preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]+/u', '_', substr($file, 0, -4)), ' ._');
    }

    /**
     * Output file name of a schedule, without extension: it always starts with the name of the report. The record has
     * any name: none is the report's name, another one gets the report's name in front.
     *
     * @param string $templateFile Template file of the report.
     * @param string $file Value of the file name field of the schedule.
     */
    public static function scheduleOutput(string $templateFile, string $file): string
    {
        $base = self::outputBase($templateFile);
        $name = trim((string)preg_replace('/\.[a-z0-9]{1,5}$/i', '', trim($file)));
        if($name === ''){ return $base; }
        return stripos($name, $base) === 0 ? $name : $base.' '.$name;
    }

    /**
     * Output file name of a report: its template file name, a space and an
     * optional suffix of letters, digits, "-", "_", "(", ")" and spaces.
     */
    public function outputName(array $report, string $suffix): string
    {
        $base = self::outputBase($report['file']);
        $suffix = trim($suffix);
        if($suffix !== ''){ $base .= ' '.self::asciiName($suffix, 'File name suffix'); }
        if($base === ''){
            throw new InvalidArgumentException('Invalid output file name');
        }
        return $base;
    }

    /**
     * A file name of letters A-Z, digits, "-", "_", "(", ")" and spaces (without extension).
     *
     * @param string $name Requested name; ".tpl" is removed.
     * @param string $what Field name for the error message.
     */
    public static function asciiName(string $name, string $what = 'File name'): string
    {
        $name = trim((string)preg_replace('/\.tpl$/i', '', trim($name)));
        if($name === '' || strlen($name) > 100 || !preg_match('/^[A-Za-z0-9 _()-]+$/', $name)){
            throw new InvalidArgumentException($what.' may contain only the letters A-Z, digits, "-", "_", "(", ")" and spaces');
        }
        return $name;
    }

    /**
     * Report description for a reference (record id or file name) with the
     * visibility rules of the current user; used by the report jobs.
     */
    public function resolveReport(string $reference): array
    {
        return $this->resolve($reference);
    }

    /** True when the template file exists. */
    public function templateExists(string $file): bool
    {
        return $file !== '' && $this->templates->exists($file);
    }

    /** Install the report definitions and convert old schedules. Managers only. */
    public function setup(): array
    {
        $this->policy->requireManager();
        return array('report' => $this->writer->setup());
    }

    /**
     * Resolve a reference to a report description. Unregistered files get
     * `id` null and the same keys as records.
     */
    private function resolve(string $reference): array
    {
        $reference = trim($reference);
        if($reference === ''){
            throw new InvalidArgumentException('Report reference is required');
        }
        if(ctype_digit($reference)){
            $report = $this->reports->getReport(intval($reference));
            if($report === null){
                throw new OutOfBoundsException('Report '.$reference.' is not available');
            }
            $report['fileExists'] = $report['file'] !== '' && $this->templates->exists($report['file']);
            return $report;
        }

        $file = $this->templates->normalize($reference);
        if(isset($this->reports->registeredFiles()[strtolower($file)])){
            $report = $this->reports->findByFile($file);
            if($report === null){
                throw new DomainException('Template '.$file.' belongs to a report you cannot access');
            }
            $report['fileExists'] = $this->templates->exists($report['file']);
            return $report;
        }
        if(!$this->templates->exists($file)){
            throw new OutOfBoundsException('Template file '.$file.' does not exist');
        }
        return $this->unregistered($file);
    }

    /** Fail unless the current user may change the report or file. */
    private function requireEdit(array $report): void
    {
        if($report['id'] === null ? !$this->policy->isMember() : !$report['canEdit']){
            throw new DomainException('You are not allowed to change this report');
        }
    }

    /** Template files without a Custom Report record. */
    private function unregisteredFiles(): array
    {
        $registered = $this->reports->registeredFiles();
        $result = array();
        foreach($this->templates->listFiles() as $entry){
            if(!isset($registered[strtolower($entry['file'])])){
                $result[] = $this->unregistered($entry['file'], $entry);
            }
        }
        return $result;
    }

    /** Report description for an unregistered file. */
    private function unregistered(string $file, ?array $entry = null): array
    {
        return array(
            'id' => null,
            'title' => substr($file, 0, -4),
            'description' => '',
            'file' => $file,
            'isCardView' => false,
            'ownerGroupId' => null,
            'visibility' => null,
            'modified' => $entry['modified'] ?? null,
            'canEdit' => $this->policy->isMember(),
            'schedules' => array(),
            'fileExists' => true
        );
    }

    /** A template file name (letters A-Z, digits, "-", "_", spaces) derived from a title. */
    public static function fileNameFromTitle(string $title): string
    {
        $ascii = function_exists('iconv') ? (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) : $title;
        $name = preg_replace('/[^A-Za-z0-9 _()-]+/', '_', $ascii);
        $name = trim(preg_replace('/\s+/', ' ', (string)$name), " _");
        if($name === ''){ $name = 'report'; }
        return substr($name, 0, 100).'.tpl';
    }

    /** Name, size, date and URL of a generated file, or null when it does not exist. */
    private function generatedInfo(string $file): ?array
    {
        $path = $this->generatedDirectory.$file;
        if($this->generatedDirectory === '' || !is_file($path)){ return null; }
        return array(
            'file' => $file,
            'size' => intval(filesize($path)),
            'modified' => gmdate('Y-m-d\TH:i:s\Z', intval(filemtime($path))),
            'url' => $this->generatedUrl === '' ? '' : $this->generatedUrl.rawurlencode($file)
        );
    }
}
