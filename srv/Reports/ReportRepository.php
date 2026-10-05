<?php
/**
* ReportRepository.php - Custom Report and Report Schedule records
*
* Reads RT_CUSTOM_REPORT records (title, description, template file, card flag)
* and the RT_REPORT_SCHEDULE records that point to them, with the normal record
* visibility rules of the current user.
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

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Presentation\PresentationRecordRepository;
use Heurist\Runtime\RuntimeContext;
use Heurist\Runtime\SystemCode;

/** Read access to report records. */
final class ReportRepository
{
    /** Codes that must be defined in the database before records can be used. */
    public const REQUIRED_CODES = array(
        'RT_CUSTOM_REPORT', 'RT_REPORT_SCHEDULE',
        'DT_FILE_NAME', 'DT_IS_CARD_VIEW', 'DT_REPORT', 'DT_INTERVAL_MINUTES'
    );

    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private SystemCode $codes;
    private PresentationRecordRepository $records;

    /** Initialise report record access. */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        SystemCode $codes,
        PresentationRecordRepository $records
    ) {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->codes = $codes;
        $this->records = $records;
    }

    /**
     * Codes from REQUIRED_CODES that are not defined in this database.
     *
     * @return array<int,string>
     */
    public function missingDefinitions(): array
    {
        $missing = array();
        foreach(self::REQUIRED_CODES as $code){
            if($this->codes->id($code) < 1){ $missing[] = $code; }
        }
        return $missing;
    }

    /** True when the report record types and fields exist. */
    public function isInstalled(): bool
    {
        return empty($this->missingDefinitions());
    }

    /**
     * Visible Custom Report records with their visible schedules, sorted by title.
     *
     * @param bool $cardOnly Return only single-record (card) reports.
     * @return array<int,array>
     */
    public function listReports(bool $cardOnly = false): array
    {
        if(!$this->isInstalled()){ return array(); }
        $reports = $this->loadReports(null);
        if($cardOnly){
            $reports = array_values(array_filter($reports, static function(array $report): bool {
                return $report['isCardView'];
            }));
        }
        return $reports;
    }

    /** One visible Custom Report record, or null. */
    public function getReport(int $recordId): ?array
    {
        if($recordId < 1 || !$this->isInstalled()){ return null; }
        $reports = $this->loadReports($recordId);
        return $reports[0] ?? null;
    }

    /** One visible Custom Report record that uses the given template file, or null. */
    public function findByFile(string $file): ?array
    {
        foreach($this->listReports() as $report){
            if(strcasecmp($report['file'], $file) === 0){ return $report; }
        }
        return null;
    }

    /**
     * Template file names used by any Custom Report record, visible or not, in lower case.
     * A private report's file must not be shown to others as "unregistered".
     *
     * @return array<string,int> file name => number of records using it
     */
    public function registeredFiles(): array
    {
        if(!$this->isInstalled()){ return array(); }
        $files = array();
        foreach($this->database->fetchRows(
            'SELECT d.dtl_Value FROM Records r JOIN recDetails d ON d.dtl_RecID=r.rec_ID AND d.dtl_DetailTypeID=? '
            .'WHERE r.rec_RecTypeID=? AND r.rec_FlagTemporary=0',
            array($this->codes->id('DT_FILE_NAME'), $this->codes->id('RT_CUSTOM_REPORT'))
        ) as $row){
            $file = strtolower(trim((string)$row[0]));
            if($file === ''){ continue; }
            if(substr($file, -4) !== '.tpl'){ $file .= '.tpl'; }
            $files[$file] = ($files[$file] ?? 0) + 1;
        }
        return $files;
    }

    /** True when the current user may edit (or delete) the record. */
    public function canEdit(int $ownerGroupId): bool
    {
        if($this->runtime->userId < 1){ return false; }
        if($this->runtime->isDbOwner || $this->runtime->isAdmin){ return true; }
        return in_array($ownerGroupId, $this->records->userAndGroupIds(), true);
    }

    /**
     * Load reports (all visible, or one id) with their visible schedules.
     *
     * @return array<int,array>
     */
    private function loadReports(?int $recordId): array
    {
        $parameters = array($this->codes->id('RT_CUSTOM_REPORT'));
        $where = 'rec_RecTypeID=?';
        if($recordId !== null){
            $where .= ' AND rec_ID=?';
            $parameters[] = $recordId;
        }
        $where .= ' AND '.$this->records->accessCondition($parameters);
        $rows = $this->database->fetchAll(
            'SELECT rec_ID,rec_Title,rec_OwnerUGrpID,rec_NonOwnerVisibility,rec_Modified FROM Records WHERE '.$where,
            $parameters
        );
        if(empty($rows)){ return array(); }

        $ids = array_map(static function(array $row): int { return intval($row['rec_ID']); }, $rows);
        $fields = array(
            'name' => $this->codes->id('DT_NAME'),
            'description' => $this->codes->id('DT_EXTENDED_DESCRIPTION'),
            'summary' => $this->codes->id('DT_SHORT_SUMMARY'),
            'file' => $this->codes->id('DT_FILE_NAME'),
            'card' => $this->codes->id('DT_IS_CARD_VIEW')
        );
        $details = $this->loadDetails($ids, array_values($fields));
        $terms = $this->loadTerms($this->collectValues($details, array($fields['card'])));
        $schedules = $this->loadSchedules($ids);

        $reports = array();
        foreach($rows as $row){
            $id = intval($row['rec_ID']);
            $values = $details[$id] ?? array();
            $title = $this->first($values, $fields['name']) ?? (string)$row['rec_Title'];
            $file = trim((string)($this->first($values, $fields['file']) ?? ''));
            if($file !== '' && strtolower(substr($file, -4)) !== '.tpl'){ $file .= '.tpl'; }
            $ownerGroupId = intval($row['rec_OwnerUGrpID']);
            $reports[] = array(
                'id' => $id,
                'title' => $title,
                'description' => $this->first($values, $fields['description'])
                    ?? $this->first($values, $fields['summary']) ?? '',
                'file' => $file,
                'isCardView' => $this->isYes($terms, $this->first($values, $fields['card'])),
                'ownerGroupId' => $ownerGroupId,
                'visibility' => (string)$row['rec_NonOwnerVisibility'],
                'modified' => (string)$row['rec_Modified'],
                'canEdit' => $this->canEdit($ownerGroupId),
                'schedules' => $schedules[$id] ?? array()
            );
        }
        usort($reports, static function(array $a, array $b): int {
            return strcasecmp($a['title'], $b['title']);
        });
        return $reports;
    }

    /**
     * One visible Report Schedule record, or null.
     */
    public function getSchedule(int $scheduleId): ?array
    {
        if($scheduleId < 1 || !$this->isInstalled()){ return null; }
        $rows = $this->querySchedules(null, $scheduleId, false);
        return $rows[0] ?? null;
    }

    /**
     * All visible Report Schedule records.
     *
     * @return array<int,array>
     */
    public function listSchedules(): array
    {
        return $this->isInstalled() ? $this->querySchedules(null, null, false) : array();
    }

    /**
     * All Report Schedule records regardless of visibility (cron).
     *
     * @return array<int,array>
     */
    public function allSchedules(): array
    {
        return $this->isInstalled() ? $this->querySchedules(null, null, true) : array();
    }

    /**
     * Visible Report Schedule records grouped by the report id they point to.
     *
     * @param array<int,int> $reportIds
     * @return array<int,array<int,array>>
     */
    private function loadSchedules(array $reportIds): array
    {
        if(empty($reportIds)){ return array(); }
        $result = array();
        foreach($this->querySchedules($reportIds, null, false) as $schedule){
            $result[$schedule['reportId']][] = $schedule;
        }
        return $result;
    }

    /**
     * Report Schedule records with their details.
     *
     * @param array<int,int>|null $reportIds Only schedules of these reports.
     * @param int|null $scheduleId Only this schedule.
     * @param bool $ignoreAccess Skip the visibility rules (system tasks only).
     * @return array<int,array>
     */
    private function querySchedules(?array $reportIds, ?int $scheduleId, bool $ignoreAccess): array
    {
        $scheduleType = $this->codes->id('RT_REPORT_SCHEDULE');
        $reportField = $this->codes->id('DT_REPORT');
        if($scheduleType < 1 || $reportField < 1){ return array(); }

        $parameters = array($reportField, $scheduleType);
        $where = 'rec_RecTypeID=?';
        if($reportIds !== null){
            $where .= ' AND dtl_Value IN ('.implode(',', array_fill(0, count($reportIds), '?')).')';
            array_push($parameters, ...$reportIds);
        }
        if($scheduleId !== null){
            $where .= ' AND rec_ID=?';
            $parameters[] = $scheduleId;
        }
        $where .= ' AND '.($ignoreAccess ? 'rec_FlagTemporary=0' : $this->records->accessCondition($parameters));
        $rows = $this->database->fetchAll(
            'SELECT rec_ID,rec_Title,rec_OwnerUGrpID,dtl_Value AS reportId FROM Records '
            .'JOIN recDetails ON dtl_RecID=rec_ID AND dtl_DetailTypeID=? WHERE '.$where,
            $parameters
        );
        if(empty($rows)){ return array(); }

        $ids = array_map(static function(array $row): int { return intval($row['rec_ID']); }, $rows);
        $fields = array(
            'name' => $this->codes->id('DT_NAME'),
            'source' => $this->codes->id('DT_DATA_SOURCE'),
            'file' => $this->codes->id('DT_FILE_NAME'),
            'mime' => $this->codes->id('DT_MIME_TYPE'),
            'interval' => $this->codes->id('DT_INTERVAL_MINUTES')
        );
        $details = $this->loadDetails($ids, array_values($fields));
        $terms = $this->loadTerms($this->collectValues($details, array($fields['mime'])));

        $result = array();
        foreach($rows as $row){
            $id = intval($row['rec_ID']);
            $values = $details[$id] ?? array();
            $mime = $terms[intval($this->first($values, $fields['mime']))] ?? null;
            $interval = $this->first($values, $fields['interval']);
            $ownerGroupId = intval($row['rec_OwnerUGrpID']);
            $result[] = array(
                'id' => $id,
                'reportId' => intval($row['reportId']),
                'title' => $this->first($values, $fields['name']) ?? (string)$row['rec_Title'],
                'dataSourceId' => intval($this->first($values, $fields['source'])),
                'file' => (string)($this->first($values, $fields['file']) ?? ''),
                'format' => self::formatFromMime($mime === null ? '' : ($mime['code'] !== '' ? $mime['code'] : $mime['label'])),
                'intervalMinutes' => is_numeric($interval) ? intval($interval) : null,
                'ownerGroupId' => $ownerGroupId,
                'canEdit' => $this->canEdit($ownerGroupId)
            );
        }
        return $result;
    }

    /** Output formats of the report engine and their MIME types. */
    public const FORMATS = array(
        'html' => 'text/html', 'js' => 'text/javascript', 'txt' => 'text/plain', 'csv' => 'text/csv',
        'xml' => 'text/xml', 'json' => 'application/json', 'css' => 'text/css'
    );

    /** Report output format ("html", "csv", ...) for a MIME type or extension term; default html. */
    public static function formatFromMime(string $value): string
    {
        $value = strtolower(trim($value));
        if(isset(self::FORMATS[$value])){ return $value; }
        $format = array_search($value, self::FORMATS, true);
        if($format !== false){ return $format; }
        if(in_array($value, array('application/javascript', 'application/xml'), true)){
            return $value === 'application/xml' ? 'xml' : 'js';
        }
        return $value === 'text' ? 'txt' : 'html';
    }

    /**
     * Detail values of several records for the given fields.
     *
     * @return array<int,array<int,array<int,string>>> record id => field id => values
     */
    private function loadDetails(array $recordIds, array $fieldIds): array
    {
        $fieldIds = array_values(array_filter($fieldIds, static function(int $id): bool { return $id > 0; }));
        if(empty($recordIds) || empty($fieldIds)){ return array(); }
        $parameters = array_merge($recordIds, $fieldIds);
        $result = array();
        foreach($this->database->fetchRows(
            'SELECT dtl_RecID,dtl_DetailTypeID,dtl_Value FROM recDetails '
            .'WHERE dtl_RecID IN ('.implode(',', array_fill(0, count($recordIds), '?')).') '
            .'AND dtl_DetailTypeID IN ('.implode(',', array_fill(0, count($fieldIds), '?')).') ORDER BY dtl_ID',
            $parameters
        ) as $row){
            $result[intval($row[0])][intval($row[1])][] = (string)$row[2];
        }
        return $result;
    }

    /** Distinct values of the given fields over all loaded records. */
    private function collectValues(array $details, array $fieldIds): array
    {
        $values = array();
        foreach($details as $fields){
            foreach($fieldIds as $fieldId){
                foreach($fields[$fieldId] ?? array() as $value){ $values[intval($value)] = true; }
            }
        }
        return array_keys(array_filter($values, static function($unused, $key): bool { return $key > 0; }, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Labels and codes of terms.
     *
     * @return array<int,array{label:string,code:string}>
     */
    private function loadTerms(array $termIds): array
    {
        if(empty($termIds)){ return array(); }
        $result = array();
        foreach($this->database->fetchRows(
            'SELECT trm_ID,trm_Label,trm_Code FROM defTerms WHERE trm_ID IN ('
            .implode(',', array_fill(0, count($termIds), '?')).')',
            $termIds
        ) as $row){
            $result[intval($row[0])] = array('label' => (string)$row[1], 'code' => (string)($row[2] ?? ''));
        }
        return $result;
    }

    /** True when the term value of a Flag field is "Yes". */
    private function isYes(array $terms, ?string $value): bool
    {
        $term = $terms[intval($value)] ?? null;
        if($term === null){ return false; }
        return in_array(strtolower(trim($term['label'])), array('yes', 'y', 'true'), true)
            || in_array(strtolower(trim($term['code'])), array('yes', 'y', 'true', '1'), true);
    }

    /** First value of a field, or null. */
    private function first(array $values, int $fieldId): ?string
    {
        if($fieldId < 1 || !isset($values[$fieldId][0])){ return null; }
        $value = trim($values[$fieldId][0]);
        return $value === '' ? null : $value;
    }
}
