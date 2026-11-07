<?php
/**
* ReportEntityHandler.php - Hooks and actions of the entities report and output (plan 30)
*
* report (Custom Report 2-1104): the record owns its template file. The file name is made once, from
* the title, when the record is created and does not change after that.
*   GET | PUT | DELETE /report/{id}/asset     the template (`{report, body}`; `{body}` to save)
*   GET | POST /report/{id}/execute           one record as HTML (`recordId`, or `q` limited to 1; `lang`)
*   GET /report/{id}/export                   the template with concept codes (.gpl)
*   POST /report/import                       a template file (multipart `import_template`, `isCardView`)
*   GET | POST | DELETE /report/setup         state and unregistered files | install | delete a file (`file`)
*
* output (Custom Report Schedule 2-1105): a generated report, a record with one file.
*   GET | DELETE /output/{id}/asset           the file (`download=1`: as a file named by the title)
*   POST /output/{id}/execute                 job report-generate (api.php)
*
* A template with outputs is not deleted; a duplicate of a report gets its own file.
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
use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Edit\RecordInput;
use Heurist\Records\Edit\RecordSaveService;
use Heurist\Records\Entity\RecordEntityHandler;
use Heurist\Runtime\RawResponse;
use Heurist\Runtime\SystemCode;
use InvalidArgumentException;
use OutOfBoundsException;

/** Report and output entities. */
final class ReportEntityHandler extends RecordEntityHandler
{
    /** A parameter of a query ("Ask the user" in the Filter Builder). */
    private const PARAMETER = '/\$[A-Za-z][A-Za-z0-9_]*\$/';

    /** "report" or "output". */
    private string $kind;
    private ReportRepository $reports;
    private ReportTemplateStore $templates;
    private SystemCode $codes;
    private ?ReportOutputStore $outputs;
    /** @var callable():ReportService|null */
    private $service;
    /** @var callable():RecordSaveService|null */
    private $recordSave;
    /** @var callable(mixed):int|null First visible record of a query. */
    private $firstRecord;
    /** @var array{from:string,to:string}|null Template file to create after the save. */
    private ?array $file = null;

    /**
     * @param string $kind "report" or "output".
     * @param callable():ReportService|null $service Templates (needs the report engine).
     * @param callable():RecordSaveService|null $recordSave The save of a duplicate's values.
     * @param callable(mixed):int|null $firstRecord First visible record of a query.
     */
    public function __construct(
        string $kind,
        ReportRepository $reports,
        ReportTemplateStore $templates,
        SystemCode $codes,
        ?ReportOutputStore $outputs = null,
        ?callable $service = null,
        ?callable $recordSave = null,
        ?callable $firstRecord = null
    ) {
        $this->kind = $kind;
        $this->reports = $reports;
        $this->templates = $templates;
        $this->codes = $codes;
        $this->outputs = $outputs;
        $this->service = $service;
        $this->recordSave = $recordSave;
        $this->firstRecord = $firstRecord;
    }

    public function beforeSave(string $method, int $id, array $body, array $params, bool $dryRun = false): array
    {
        $this->file = null;
        $record = isset($body['records'][0]) && is_array($body['records'][0]) ? $body['records'][0] : $body;
        $details = is_array($record['details'] ?? null) ? $record['details'] : array();
        if($this->kind === 'output'){
            $this->checkQuery($details);
            return $body;
        }
        $field = $this->codes->id('DT_FILE_NAME');
        if($method === 'PATCH' && !isset($details[$field])){ return $body; }
        $current = $id > 0 ? (string)($this->reports->getReport($id)['file'] ?? '') : '';
        $file = $this->fileName(self::text($details[$field] ?? null), $current, self::text($details[$this->codes->id('DT_NAME')] ?? null), $field);
        if($file === $current){ return $body; }
        // written by afterSave: a refused save leaves the files as they are
        if(!$dryRun){ $this->file = array('from' => $current, 'to' => $file); }
        $details[$field] = array($file);
        $record['details'] = $details;
        if(isset($body['records'][0])){
            $body['records'][0] = $record;
            return $body;
        }
        return $record;
    }

    public function afterSave(string $method, int $id, array $body, array $params): array
    {
        $file = $this->file;
        $this->file = null;
        if($file !== null && !$this->templates->exists($file['to'])){
            $this->templates->write($file['to'], '');
        }
        return array();
    }

    /**
     * A copy of a report gets its own template file, named after its title: `title` of the request,
     * else the title of the copy. Without this the two records share one file.
     */
    public function afterDuplicate(int $sourceId, int $newId, array $body, array $params): array
    {
        if($this->kind !== 'report'){ return array(); }
        $title = trim((string)($body['title'] ?? ''));
        $copy = $this->reports->getReport($newId);
        if($title === ''){ $title = (string)($copy['title'] ?? ''); }
        $file = $this->templates->uniqueName(ReportService::fileNameFromTitle($title));
        $source = (string)($copy['file'] ?? '');
        $this->templates->write($file, $source !== '' && $this->templates->exists($source) ? $this->templates->read($source) : '');
        $save = $this->recordSave !== null ? ($this->recordSave)() : null;
        if($save === null){ throw new RecordEditException(500, 'server_error', 'The copy of the report can not be saved'); }
        $save->save('PATCH', $newId, RecordInput::fromBody(array('details' => array(
            $this->codes->id('DT_NAME') => array($title),
            $this->codes->id('DT_FILE_NAME') => array($file)
        ))), true);
        return array();
    }

    public function beforeDelete(int $id, array $params): array
    {
        if($this->kind !== 'report'){ return array(); }
        $count = $this->reports->countOutputs($id);
        if($count > 0){ throw self::inUse($count); }
        $file = (string)($this->reports->getReport($id)['file'] ?? '');
        return array('file' => $file, 'uses' => $this->reports->registeredFiles()[strtolower($file)] ?? 0);
    }

    public function afterDelete(int $id, array $params, array $state, array $result): array
    {
        if($this->kind === 'output'){
            $result['fileDeleted'] = $this->outputs !== null && $this->outputs->delete($id);
            return $result;
        }
        $file = (string)($state['file'] ?? '');
        $result['fileDeleted'] = false;
        if($file !== '' && intval($state['uses'] ?? 0) <= 1 && $this->templates->exists($file)){
            $this->templates->delete($file);
            $result['fileDeleted'] = true;
        }
        return $result;
    }

    public function describe(int $id): array
    {
        $items = $this->describeMany(array($id));
        return isset($items[$id]) ? array($this->kind => $items[$id]) : array();
    }

    /** The values of a report or output a client shows, by record id (meta.items of a search). */
    public function describeMany(array $ids): array
    {
        $wanted = array_fill_keys(array_map('intval', $ids), true);
        $rows = $this->kind === 'report' ? $this->reports->listReports() : $this->outputsOf($wanted);
        $items = array();
        foreach($rows as $row){
            if(isset($wanted[$row['id']])){
                $items[$row['id']] = $this->kind === 'report'
                    ? $row + array('fileExists' => $row['file'] !== '' && $this->templates->exists($row['file']))
                    : $row;
            }
        }
        return $items;
    }

    /** Output rows with the title of their report and the state of their file. */
    private function outputsOf(array $wanted): array
    {
        $titles = array_column($this->reports->listReports(), 'title', 'id');
        $rows = array();
        foreach($this->reports->listOutputs() as $output){
            if(!isset($wanted[$output['id']])){ continue; }
            $found = $this->outputs !== null ? $this->outputs->find($output['id']) : null;
            unset($found['path'], $found['time']);
            $rows[] = $output + array('reportTitle' => $titles[$output['reportId']] ?? null, 'generated' => $found);
        }
        return $rows;
    }

    public function allowsGuest(string $action): bool
    {
        return ($this->kind === 'report' && $action === 'execute') || ($this->kind === 'output' && $action === 'asset');
    }

    public function acceptsUpload(string $action): bool
    {
        return $this->kind === 'report' && $action === 'import';
    }

    public function action(string $action, string $method, int $id, array $params, array $body): array
    {
        try{
            return $this->kind === 'report' ? $this->reportAction($action, $method, $id, $params, $body)
                : $this->outputAction($action, $method, $id, $params);
        }catch(ReportInUseException $error){
            throw self::inUse($error->outputs());
        }catch(InvalidArgumentException $error){
            throw RecordEditException::invalid($error->getMessage());
        }catch(OutOfBoundsException $error){
            throw RecordEditException::notFound($error->getMessage());
        }catch(DomainException $error){
            throw RecordEditException::denied($error->getMessage());
        }
    }

    private function reportAction(string $action, string $method, int $id, array $params, array $body): array
    {
        $service = $this->service !== null ? ($this->service)() : null;
        if($service === null){ throw new RecordEditException(405, 'method_not_allowed', 'Reports can not run here'); }
        if($id < 1){
            if($action === 'setup'){
                self::allow($method, array('GET', 'POST', 'DELETE'));
                if($method === 'GET'){ return $service->status(); }
                return $method === 'POST' ? $service->setup() : $service->deleteUnregistered((string)($params['file'] ?? ''));
            }
            if($action === 'import'){
                self::allow($method, array('POST'));
                $upload = $params['uploads']['import_template'] ?? null;
                if(!is_array($upload)){ throw new InvalidArgumentException('Multipart field "import_template" is required'); }
                return $service->importTemplate($upload, filter_var($params['isCardView'] ?? false, FILTER_VALIDATE_BOOLEAN));
            }
            return parent::action($action, $method, $id, $params, $body);
        }
        switch($action){
            case 'asset':
                self::allow($method, array('GET', 'PUT', 'DELETE'));
                if($method === 'GET'){ return $service->readTemplate($id); }
                return $method === 'PUT' ? $service->saveTemplate($id, (string)($body['body'] ?? '')) : $service->deleteTemplate($id);
            case 'export':
                self::allow($method, array('GET'));
                $export = $service->exportTemplate($id);
                return array('rawResponse' => new RawResponse('text/plain; charset=utf-8', $export['body'], '', array(
                    'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $export['file']).'"'
                )));
            case 'execute':
                self::allow($method, array('GET', 'POST'));
                return array('rawResponse' => new RawResponse('text/html; charset=utf-8',
                    $service->execute($id, $this->recordOfRun($params, $body)), '', array('Cache-Control' => 'no-cache')));
        }
        return parent::action($action, $method, $id, $params, $body);
    }

    /** The record of a run: `recordId`, else the first record of the query `q`. */
    private function recordOfRun(array $params, array $body): int
    {
        $recordId = intval($body['recordId'] ?? $params['recordId'] ?? 0);
        if($recordId > 0){ return $recordId; }
        $query = $body['q'] ?? $params['q'] ?? null;
        if($query === null || $query === '' || $this->firstRecord === null){ return 0; }
        $found = intval(call_user_func($this->firstRecord, $query));
        if($found < 1){ throw new OutOfBoundsException('The query finds no records'); }
        return $found;
    }

    private function outputAction(string $action, string $method, int $id, array $params): array
    {
        if($action !== 'asset' || $id < 1 || $this->outputs === null){
            return parent::action($action, $method, $id, $params, array());
        }
        self::allow($method, array('GET', 'DELETE'));
        $output = $this->reports->getOutput($id);
        if($output === null){ throw new OutOfBoundsException('Output '.$id.' is not available'); }
        if($method === 'DELETE'){
            if(!$output['canEdit']){ throw new DomainException('You are not allowed to change this output'); }
            return array('id' => $id, 'fileDeleted' => $this->outputs->delete($id));
        }
        $file = $this->outputs->find($id);
        if($file === null){ throw new OutOfBoundsException('Output '.$id.' is not generated yet'); }
        $headers = array('Cache-Control' => 'no-cache');
        if(filter_var($params['download'] ?? false, FILTER_VALIDATE_BOOLEAN)){
            $name = substr(ReportService::fileNameFromTitle((string)$output['title']), 0, -4);
            $headers['Content-Disposition'] = 'attachment; filename="'.$name.'.'.$file['format'].'"';
        }
        $type = ReportRepository::FORMATS[$file['format']];
        return array('rawResponse' => new RawResponse(strpos($type, 'text/') === 0 ? $type.'; charset=utf-8' : $type, '', $file['path'], $headers));
    }

    /** The answer of a report with outputs: 409, with their number. */
    private static function inUse(int $count): RecordEditException
    {
        return new RecordEditException(409, 'report_in_use', 'This template has '.$count.' generated report(s): delete them first', array(), array('outputs' => $count));
    }

    /**
     * The template file of a saved report: set once, on a new record.
     *
     * @param string $given Value of the field.
     * @param string $current Template file of the stored record ('' for a new one).
     * @param string $title Title of the record.
     * @param int $field File name field (for the message).
     * @return string File name with ".tpl"; `$current` when it does not change.
     */
    private function fileName(string $given, string $current, string $title, int $field): string
    {
        if($given === ''){
            return $current !== '' ? $current : $this->templates->uniqueName(ReportService::fileNameFromTitle($title));
        }
        // an unchanged name is not checked: a legacy name with other characters stays
        $withExtension = strtolower(substr($given, -4)) === '.tpl' ? $given : $given.'.tpl';
        if($current !== ''){
            if(strcasecmp($withExtension, $current) === 0){ return $current; }
            throw self::refused($field, 'The template file name does not change: save a copy with another title');
        }
        // the name of a template without a record (registration) stays as it is
        try{
            if($this->templates->exists($withExtension)){
                if(isset($this->reports->registeredFiles()[strtolower($withExtension)])){
                    throw self::refused($field, 'A template file '.$withExtension.' already exists');
                }
                return $withExtension;
            }
            return ReportService::asciiName($given, 'Template file name').'.tpl';
        }catch(InvalidArgumentException $error){
            throw self::refused($field, 'A template file name may contain only the letters A-Z, digits, "-", "_", "(", ")" and spaces');
        }
    }

    /** Refuse a query with parameters. */
    private function checkQuery(array $details): void
    {
        $field = $this->codes->id('DT_QUERY_STRING');
        if(preg_match(self::PARAMETER, self::text($details[$field] ?? null)) === 1){
            throw self::refused($field, 'A generated report runs without a user: its query can not have parameters');
        }
    }

    /** First value of a field of the request body. */
    private static function text($values): string
    {
        $value = is_array($values) ? (array_values($values)[0] ?? '') : $values;
        if(is_array($value)){ $value = $value['value'] ?? ''; }
        return trim((string)$value);
    }

    /** The error of a field, in the format of the record validator. */
    private static function refused(int $field, string $message): RecordEditException
    {
        return new RecordEditException(422, 'validation', $message, array(
            array('field' => (string)$field, 'index' => 0, 'code' => 'invalid', 'severity' => 'error', 'message' => $message)
        ));
    }
}
