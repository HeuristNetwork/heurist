<?php
/**
* ReportEntityHandler.php - Report and schedule entities (plan 28 §6, plan 12)
*
* Custom Report and Report Schedule records are saved and deleted by the generic
* record controller; only the rules of these types are here. The hooks run for every
* write of the record type, also through /records/{id}.
* - report: the template file follows the record. The file name has only the letters
*   A-Z, digits, "-", "_", "(", ")" and spaces; empty is made from the title; a new name
*   renames the file; a new record gets an empty file, or takes an existing file without
*   a record; a deleted record takes its file with it, unless other records use it or
*   the request has keepFile.
* - schedule: the query runs without a user, so it may not have parameters.
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

use Heurist\Records\Edit\RecordEditException;
use Heurist\Records\Entity\RecordEntityHandler;
use Heurist\Runtime\SystemCode;
use InvalidArgumentException;

/** Report and schedule entities. */
final class ReportEntityHandler extends RecordEntityHandler
{
    /** A parameter of a query ("Ask the user" in the Filter Builder). */
    private const PARAMETER = '/\$[A-Za-z][A-Za-z0-9_]*\$/';

    /** "report" or "schedule". */
    private string $kind;
    private ReportRepository $reports;
    private ReportTemplateStore $templates;
    private SystemCode $codes;
    /** @var array{from:string,to:string}|null Template file to rename or create after the save. */
    private ?array $file = null;

    public function __construct(string $kind, ReportRepository $reports, ReportTemplateStore $templates, SystemCode $codes)
    {
        $this->kind = $kind;
        $this->reports = $reports;
        $this->templates = $templates;
        $this->codes = $codes;
    }

    public function beforeSave(string $method, int $id, array $body, array $params, bool $dryRun = false): array
    {
        $this->file = null;
        $record = isset($body['records'][0]) && is_array($body['records'][0]) ? $body['records'][0] : $body;
        $details = is_array($record['details'] ?? null) ? $record['details'] : array();
        if($this->kind === 'schedule'){
            $this->checkQuery($details);
            return $body;
        }
        $field = $this->codes->id('DT_FILE_NAME');
        if($method === 'PATCH' && !isset($details[$field])){ return $body; }
        $current = $id > 0 ? (string)($this->reports->getReport($id)['file'] ?? '') : '';
        $file = $this->fileName(self::text($details[$field] ?? null), $current, self::text($details[$this->codes->id('DT_NAME')] ?? null), $field);
        if(strcasecmp($file, $current) === 0){ return $body; }
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
        if($file === null){ return array(); }
        if($file['from'] !== '' && $this->templates->exists($file['from'])){
            $this->templates->write($file['to'], $this->templates->read($file['from']));
            $this->templates->delete($file['from']);
        }elseif(!$this->templates->exists($file['to'])){
            $this->templates->write($file['to'], '');
        }
        return array();
    }

    public function beforeDelete(int $id, array $params): array
    {
        if($this->kind !== 'report'){ return array(); }
        $file = (string)($this->reports->getReport($id)['file'] ?? '');
        return array(
            'file' => $file,
            'uses' => $this->reports->registeredFiles()[strtolower($file)] ?? 0,
            'keepFile' => filter_var($params['keepFile'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );
    }

    public function afterDelete(int $id, array $params, array $state, array $result): array
    {
        if($this->kind !== 'report'){ return $result; }
        $file = (string)($state['file'] ?? '');
        $result['fileDeleted'] = false;
        if($file !== '' && empty($state['keepFile']) && intval($state['uses'] ?? 0) <= 1 && $this->templates->exists($file)){
            $this->templates->delete($file);
            $result['fileDeleted'] = true;
        }
        return $result;
    }

    /**
     * The template file of a saved report.
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
        if(strcasecmp($withExtension, $current) === 0){ return $current; }
        try{
            $file = ReportService::asciiName($given, 'Template file name').'.tpl';
        }catch(InvalidArgumentException $error){
            throw self::refused($field, $error->getMessage());
        }
        // a file with this name: free for a new record when no report uses it
        $taken = $this->templates->exists($file)
            && ($current !== '' || isset($this->reports->registeredFiles()[strtolower($file)]));
        if($taken){
            throw self::refused($field, 'A template file '.$file.' already exists');
        }
        return $file;
    }

    /** Refuse a query with parameters. */
    private function checkQuery(array $details): void
    {
        $field = $this->codes->id('DT_QUERY_STRING');
        if(preg_match(self::PARAMETER, self::text($details[$field] ?? null)) === 1){
            throw self::refused($field, 'A schedule runs without a user: its query can not have parameters');
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
