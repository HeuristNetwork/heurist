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

    /** Initialise the service from explicit dependencies. */
    public function __construct(
        ReportRepository $reports,
        ReportTemplateStore $templates,
        ReportPolicy $policy,
        ReportRendererInterface $renderer,
        ReportRecordWriterInterface $writer,
        string $generatedDirectory = '',
        string $generatedUrl = ''
    ) {
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

        $result = array(
            'installed' => $this->reports->isInstalled(),
            'reports' => $reports
        );
        if($scope !== 'card' && $this->policy->isMember()){
            $result['unregistered'] = $this->unregisteredFiles();
            $result['missingDefinitions'] = $this->reports->missingDefinitions();
            $result['canSetup'] = $this->policy->isManager();
            $result['allowDynamicReports'] = $this->policy->allowDynamicReports();
        }
        return $result;
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

    /**
     * Create a new report: a new template file and, when the definitions are
     * installed, its Custom Report record.
     */
    public function createReport(string $title, bool $isCardView, string $body = '', string $description = '', string $file = ''): array
    {
        $this->policy->requireMember();
        $title = trim($title);
        if($title === ''){
            throw new InvalidArgumentException('Report title is required');
        }
        if(trim($file) !== ''){
            $file = self::asciiName($file, 'Template file name').'.tpl';
            if($this->templates->exists($file)){
                throw new InvalidArgumentException('A template file '.$file.' already exists');
            }
        }else{
            $file = $this->templates->uniqueName($this->fileNameFromTitle($title));
        }
        $this->templates->write($file, $body);
        if(!$this->reports->isInstalled()){
            return $this->resolve($file);
        }
        $recordId = $this->writer->createReport($title, $file, $isCardView, $description);
        return $this->resolve((string)$recordId);
    }

    /** Create the Custom Report record for an unregistered template file. */
    public function registerFile(string $file, string $title = '', bool $isCardView = false, string $description = ''): array
    {
        $this->policy->requireMember();
        if(!$this->reports->isInstalled()){
            throw new DomainException('Report definitions are not installed in this database');
        }
        $report = $this->resolve($file);
        if($report['id'] !== null){
            throw new InvalidArgumentException('Template '.$report['file'].' already has a report record');
        }
        $title = trim($title) === '' ? substr($report['file'], 0, -4) : trim($title);
        $recordId = $this->writer->createReport($title, $report['file'], $isCardView, $description);
        return $this->resolve((string)$recordId);
    }

    /**
     * Change the properties of a report: title, description, card flag and the
     * template file name (the file is renamed). Unregistered files can only be renamed.
     *
     * @param string $reference Record id or file name.
     * @param array $values title?, description?, isCardView?, file? (without ".tpl").
     * @return array The changed report.
     */
    public function updateReport(string $reference, array $values): array
    {
        $this->policy->requireMember();
        $report = $this->resolve($reference);
        $this->requireEdit($report);

        $file = $report['file'];
        if(isset($values['file']) && trim((string)$values['file']) !== ''){
            $newFile = self::asciiName((string)$values['file'], 'Template file name').'.tpl';
            if(strcasecmp($newFile, $report['file']) !== 0){
                if($this->templates->exists($newFile)){
                    throw new InvalidArgumentException('A template file '.$newFile.' already exists');
                }
                $body = $report['file'] !== '' && $this->templates->exists($report['file'])
                    ? $this->templates->read($report['file']) : '';
                $this->templates->write($newFile, $body);
                if($report['file'] !== ''){ $this->templates->delete($report['file']); }
            }
            $file = $newFile;
        }

        if($report['id'] === null){
            return $this->resolve($file);
        }
        $changes = array('file' => $file);
        if(array_key_exists('title', $values)){
            $title = trim((string)$values['title']);
            if($title === ''){
                throw new InvalidArgumentException('Report title is required');
            }
            $changes['title'] = $title;
        }
        if(array_key_exists('description', $values)){ $changes['description'] = trim((string)$values['description']); }
        if(array_key_exists('isCardView', $values)){
            $changes['isCardView'] = filter_var($values['isCardView'], FILTER_VALIDATE_BOOLEAN);
        }
        $this->writer->updateReport($report['id'], $changes);
        return $this->resolve((string)$report['id']);
    }

    /**
     * Delete a report record (and its file when no other record uses it), or an
     * unregistered template file.
     */
    public function deleteReport(string $reference, bool $keepFile = false): array
    {
        $this->policy->requireMember();
        $report = $this->resolve($reference);
        $this->requireEdit($report);

        $fileDeleted = false;
        if($report['id'] !== null){
            $uses = $this->reports->registeredFiles()[strtolower($report['file'])] ?? 0;
            $this->writer->deleteRecord($report['id']);
            if(!$keepFile && $report['file'] !== '' && $uses <= 1){
                $this->templates->delete($report['file']);
                $fileDeleted = true;
            }
        }else{
            $this->templates->delete($report['file']);
            $fileDeleted = true;
        }
        return array('id' => $report['id'], 'file' => $report['file'], 'fileDeleted' => $fileDeleted);
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
        $recordId = $this->writer->createReport(substr($file, 0, -4), $file, $isCardView);
        return $this->resolve((string)$recordId);
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
     * Files in the generated-reports folder.
     *
     * @return array<int,array{file:string,size:int,modified:string,url:string}>
     */
    public function listGenerated(string $prefix = ''): array
    {
        $this->policy->requireMember();
        if($this->generatedDirectory === '' || !is_dir($this->generatedDirectory)){ return array(); }
        $prefix = strtolower(trim($prefix));
        $files = array();
        foreach(scandir($this->generatedDirectory) ?: array() as $name){
            $path = $this->generatedDirectory.$name;
            // "job-tmp-*": output of a running generation job (ReportGenerateJob)
            if($name[0] === '.' || $name[0] === '_' || $name === 'index.html' || strpos($name, 'job-tmp-') === 0
                || !is_file($path)){ continue; }
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
        foreach($this->reports->listReports() as $report){ $titles[$report['id']] = $report['title']; }
        $result = array();
        foreach($this->reports->listSchedules() as $schedule){
            $schedule['reportTitle'] = $titles[$schedule['reportId']] ?? null;
            $schedule['generated'] = $this->generatedInfo($schedule['file'].'.'.$schedule['format']);
            $result[] = $schedule;
        }
        usort($result, static function(array $a, array $b): int { return strcasecmp($a['title'], $b['title']); });
        return $result;
    }

    /**
     * Change a schedule of a report.
     *
     * @param string $reference Report record id.
     * @param int $scheduleId Schedule record id.
     * @param array $params title?, querySource?, suffix?, format?, intervalMinutes?
     * @return array The report with its schedules.
     */
    public function updateSchedule(string $reference, int $scheduleId, array $params): array
    {
        $this->policy->requireMember();
        $report = $this->resolve($reference);
        $schedule = null;
        foreach($report['schedules'] as $item){
            if($item['id'] === $scheduleId){ $schedule = $item; }
        }
        if($schedule === null){
            throw new OutOfBoundsException('Schedule '.$scheduleId.' is not available');
        }
        if(!$schedule['canEdit']){
            throw new DomainException('You are not allowed to change this schedule');
        }
        $changes = array();
        if(isset($params['title']) && trim((string)$params['title']) !== ''){ $changes['title'] = trim((string)$params['title']); }
        if(intval($params['querySource'] ?? 0) > 0){ $changes['dataSourceId'] = intval($params['querySource']); }
        if(array_key_exists('suffix', $params)){ $changes['output'] = $this->outputName($report, (string)$params['suffix']); }
        if(isset($params['format'])){ $changes['format'] = ReportRepository::formatFromMime((string)$params['format']); }
        if(array_key_exists('intervalMinutes', $params)){ $changes['intervalMinutes'] = max(0, intval($params['intervalMinutes'])); }
        $this->writer->updateSchedule($scheduleId, $changes);
        return $this->resolve($reference);
    }

    /**
     * Output file name of a report: its template file name, a space and an
     * optional suffix of letters, digits, "-", "_", "(", ")" and spaces.
     */
    public function outputName(array $report, string $suffix): string
    {
        $base = trim((string)preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]+/u', '_', substr($report['file'], 0, -4)), ' ._');
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
     * Add a Report Schedule to a report. Only users who may edit the report
     * (its owner group, the database owner and managers) manage its schedules.
     *
     * @param string $reference Report record id.
     * @param array $params title?, querySource, output?, format?, intervalMinutes?
     * @return array The report with its schedules.
     */
    public function createSchedule(string $reference, array $params): array
    {
        $this->policy->requireMember();
        $report = $this->resolve($reference);
        if($report['id'] === null){
            throw new InvalidArgumentException('Register the template before adding a schedule');
        }
        $this->requireEdit($report);
        $dataSourceId = intval($params['querySource'] ?? 0);
        if($dataSourceId < 1){
            throw new InvalidArgumentException('A Query Source is required');
        }
        $output = $this->outputName($report, (string)($params['suffix'] ?? ''));
        $interval = $params['intervalMinutes'] ?? null;
        $this->writer->createSchedule(array(
            'title' => trim((string)($params['title'] ?? '')) !== '' ? trim((string)$params['title']) : $report['title'],
            'reportId' => $report['id'],
            'dataSourceId' => $dataSourceId,
            'output' => $output,
            'format' => ReportRepository::formatFromMime((string)($params['format'] ?? 'html')),
            'intervalMinutes' => is_numeric($interval) && intval($interval) > 0 ? intval($interval) : null
        ), intval($report['ownerGroupId']));
        return $this->resolve($reference);
    }

    /** Delete a Report Schedule record of a report. */
    public function deleteSchedule(string $reference, int $scheduleId): array
    {
        $this->policy->requireMember();
        $report = $this->resolve($reference);
        $schedule = null;
        foreach($report['schedules'] as $item){
            if($item['id'] === $scheduleId){ $schedule = $item; }
        }
        if($schedule === null){
            throw new OutOfBoundsException('Schedule '.$scheduleId.' is not available');
        }
        if(!$schedule['canEdit']){
            throw new DomainException('You are not allowed to change this schedule');
        }
        $this->writer->deleteRecord($scheduleId);
        return $this->resolve($reference);
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
    private function fileNameFromTitle(string $title): string
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
