<?php
/**
* ReportRecordWriter.php - Setup of the report definitions for the srv/ reports manager
*
* Implements Heurist\Reports\ReportRecordWriterInterface with the definitions import
* (DbsImport): installs Custom Report (2-1104) and Custom Report Schedule (2-1105) from
* Heurist_Core_Definitions and converts usrReportSchedule rows to records. Other record
* writes are in the generic record services (srv/Records/Edit, Reports/ReportEntityHandler).
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

use Heurist\Reports\ReportRecordWriterInterface;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../records/edit/recordModify.php';
require_once dirname(__FILE__).'/../structure/import/dbsImport.php';

/**
 * Installs report definitions and converts the old schedules.
 */
class ReportRecordWriter implements ReportRecordWriterInterface
{
    /** Source database of the report definitions (Heurist_Core_Definitions). */
    private const SOURCE_DATABASE_ID = 2;

    /** Record types to import: local ids in the source database. */
    private const RECORD_TYPES = array('2-1104' => 1104, '2-1105' => 1105);

    /** Output formats of ReportExecute and their MIME types. */
    private const FORMATS = array(
        'html' => 'text/html', 'js' => 'text/javascript', 'txt' => 'text/plain',
        'csv' => 'text/csv', 'xml' => 'text/xml', 'json' => 'application/json', 'css' => 'text/css'
    );

    /** @var \hserv\System Initialised legacy system. */
    private $system;

    /**
     * @param \hserv\System $system Initialised legacy system.
     */
    public function __construct($system)
    {
        $this->system = $system;
    }

    /**
     * Create a Custom Report record.
     *
     * @param string $title Report title.
     * @param string $templateFile Template file name.
     * @param bool $isCardView Single-record (card) report.
     * @param string $description Optional description.
     * @param array $access Optional `OwnerUGrpID` / `NonOwnerVisibility`.
     * @return int New record id.
     */
    private function createReport(string $title, string $templateFile, bool $isCardView, string $description = '', array $access = array()): int
    {
        $details = array(
            $this->fieldId('2-1') => $title,
            $this->fieldId('2-62') => $templateFile
        );
        $flag = $this->flagTermId($isCardView);
        if ($flag > 0) {
            $details[$this->fieldId('2-1182')] = $flag;
        }
        if (trim($description) !== '') {
            $details[$this->fieldId('2-4')] = $description;
        }
        return $this->saveRecord($this->recordTypeId('2-1104'), $details, $access);
    }

    /**
     * Install the report definitions when missing, then convert usrReportSchedule rows.
     *
     * @return array<int,string> Report lines.
     */
    public function setup(): array
    {
        $report = array();
        ConceptCode::setSystem($this->system);

        $missing = array();
        foreach (self::RECORD_TYPES as $code => $sourceId) {
            if (!($this->recordTypeId($code, false) > 0)) {
                $missing[] = $sourceId;
            }
        }
        foreach (array('2-1182', '2-1183', '2-1184', '2-62') as $code) {
            if (!($this->fieldId($code, false) > 0)) {
                // a field without its record type: import both record types again
                $missing = array_values(self::RECORD_TYPES);
            }
        }

        if (empty($missing)) {
            $report[] = 'Report definitions are already installed';
        } else {
            $import = new \DbsImport($this->system);
            $ok = $import->doPrepare(array(
                'defType' => 'rectype',
                'databaseID' => self::SOURCE_DATABASE_ID,
                'definitionID' => array_values(array_unique($missing))
            ));
            if ($ok) {
                $ok = $import->doImport();
            }
            if (!$ok) {
                throw new \RuntimeException($this->errorMessage($this->system->getError(), 'Cannot import report definitions'));
            }
            ConceptCode::setSystem($this->system);
            $report[] = 'Imported record types Custom Report (2-1104) and Custom Report Schedule (2-1105)';
        }

        return array_merge($report, $this->convertSchedules());
    }

    /**
     * Create Report Schedule records for usrReportSchedule rows that have none yet.
     * The table is kept; cron reads it until it uses the records (plan 12, Phase 2).
     *
     * @return array<int,string> Report lines.
     */
    private function convertSchedules(): array
    {
        $mysqli = $this->system->getMysqli();
        $rows = $this->selectAssoc('SELECT rps_ID,rps_Title,rps_URL,rps_FileName,rps_HQuery,rps_Template,rps_IntervalMinutes FROM usrReportSchedule ORDER BY rps_ID');
        if (empty($rows)) {
            return array('No old report schedules (usrReportSchedule) to convert');
        }

        $scheduleType = $this->recordTypeId('2-1105');
        $fileField = $this->fieldId('2-62');
        $report = array();
        $created = 0;
        foreach ($rows as $row) {
            $output = (string)($row['rps_FileName'] ?: pathinfo((string)$row['rps_Template'], PATHINFO_FILENAME));
            $exists = mysql__select_value($mysqli,
                'SELECT r.rec_ID FROM Records r JOIN recDetails d ON d.dtl_RecID=r.rec_ID AND d.dtl_DetailTypeID='.intval($fileField)
                .' WHERE r.rec_RecTypeID='.intval($scheduleType).' AND r.rec_FlagTemporary=0 AND d.dtl_Value="'.$mysqli->real_escape_string($output).'" LIMIT 1');
            if ($exists) {
                $report[] = 'Schedule #'.$row['rps_ID'].' "'.$row['rps_Title'].'": already converted (record '.$exists.')';
                continue;
            }

            // records made from shared schedules belong to the Database managers group
            $access = array('OwnerUGrpID' => 1, 'NonOwnerVisibility' => 'viewable');
            $reportId = $this->findOrCreateReport((string)$row['rps_Template'], $access);

            $details = array(
                $this->fieldId('2-1') => (string)$row['rps_Title'],
                $this->fieldId('2-12') => self::scheduleQuery((string)$row['rps_HQuery']),
                $this->fieldId('2-1183') => $reportId,
                $fileField => $output
            );
            $format = in_array($row['rps_URL'], array_keys(self::FORMATS), true) ? $row['rps_URL'] : 'html';
            $mimeTerm = $this->mimeTermId($format);
            if ($mimeTerm > 0) {
                $details[$this->fieldId('2-29')] = $mimeTerm;
            }
            if (is_numeric($row['rps_IntervalMinutes']) && intval($row['rps_IntervalMinutes']) > 0) {
                $details[$this->fieldId('2-1184')] = intval($row['rps_IntervalMinutes']);
            }
            $scheduleId = $this->saveRecord($scheduleType, $details, $access);
            $created++;
            $report[] = 'Schedule #'.$row['rps_ID'].' "'.$row['rps_Title'].'" converted to record '.$scheduleId
                .($mimeTerm > 0 || $format === 'html' ? '' : ' (format '.$format.' has no term in the Mime Type vocabulary)');
        }
        $report[] = $created.' of '.count($rows).' old report schedules converted';
        return $report;
    }

    /**
     * Id of the Custom Report record for a template file; create it when missing.
     */
    private function findOrCreateReport(string $templateFile, array $access): int
    {
        $mysqli = $this->system->getMysqli();
        $file = pathinfo($templateFile, PATHINFO_FILENAME).'.tpl';
        $id = mysql__select_value($mysqli,
            'SELECT r.rec_ID FROM Records r JOIN recDetails d ON d.dtl_RecID=r.rec_ID AND d.dtl_DetailTypeID='.intval($this->fieldId('2-62'))
            .' WHERE r.rec_RecTypeID='.intval($this->recordTypeId('2-1104')).' AND r.rec_FlagTemporary=0'
            .' AND (d.dtl_Value="'.$mysqli->real_escape_string($file).'" OR d.dtl_Value="'.$mysqli->real_escape_string(substr($file, 0, -4)).'") LIMIT 1');
        if ($id) {
            return intval($id);
        }
        return $this->createReport(substr($file, 0, -4), $file, false, '', $access);
    }

    /** The query of an old schedule: rps_HQuery is a query or a URL query string ("db=..&w=all&q=..."). */
    private static function scheduleQuery(string $hquery): string
    {
        if (strpos($hquery, 'q=') !== false && strpos($hquery, '&') !== false) {
            parse_str($hquery, $params);
            return (string)($params['q'] ?? '');
        }
        return $hquery;
    }

    /**
     * Save a new record without structure validation.
     */
    private function saveRecord(int $recordTypeId, array $details, array $access = array()): int
    {
        $record = array_merge(array(
            'ID' => 0,
            'RecTypeID' => $recordTypeId,
            'no_validation' => 'ignore_all',
            'details' => $details
        ), $access);
        $result = recordSave($this->system, $record);
        if (!is_array($result) || @$result['status'] != HEURIST_OK) {
            throw new \RuntimeException($this->errorMessage($result ?: $this->system->getError(), 'Cannot save record'));
        }
        return intval($result['data']);
    }

    /**
     * Term id of "Yes" or "No" in the vocabulary of the card view field.
     */
    private function flagTermId(bool $yes): int
    {
        $labels = $yes ? array('yes', 'y', 'true') : array('no', 'n', 'false');
        foreach ($this->fieldTerms($this->fieldId('2-1182', false)) as $term) {
            if (in_array(strtolower(trim($term['trm_Label'])), $labels, true)
                || in_array(strtolower(trim((string)$term['trm_Code'])), $labels, true)) {
                return intval($term['trm_ID']);
            }
        }
        return 0;
    }

    /**
     * Term id for an output format in the vocabulary of DT_FILE_EXT (by extension, or MIME type of the older vocabulary).
     */
    private function mimeTermId(string $format): int
    {
        $names = array($format, self::FORMATS[$format] ?? $format);
        foreach ($this->fieldTerms($this->fieldId('2-29', false)) as $term) {
            if (in_array(strtolower(trim($term['trm_Label'])), $names, true)
                || in_array(strtolower(trim((string)$term['trm_Code'])), $names, true)) {
                return intval($term['trm_ID']);
            }
        }
        return 0;
    }

    /**
     * Terms of an enum field's vocabulary (direct children and linked terms).
     */
    private function fieldTerms(int $fieldId): array
    {
        if ($fieldId < 1) {
            return array();
        }
        $mysqli = $this->system->getMysqli();
        $vocabulary = intval(mysql__select_value($mysqli, 'SELECT dty_JsonTermIDTree FROM defDetailTypes WHERE dty_ID='.$fieldId));
        if ($vocabulary < 1) {
            return array();
        }
        return $this->selectAssoc(
            'SELECT trm_ID,trm_Label,trm_Code FROM defTerms WHERE trm_ParentTermID='.$vocabulary
            .' OR trm_ID IN (SELECT trl_TermID FROM defTermsLinks WHERE trl_ParentID='.$vocabulary.')');
    }

    /**
     * All rows of a query as associative arrays.
     */
    private function selectAssoc(string $query): array
    {
        $result = $this->system->getMysqli()->query($query);
        if (!$result) {
            throw new \RuntimeException('Database query failed: '.$this->system->getMysqli()->error);
        }
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->close();
        return $rows;
    }

    /**
     * Local record type id for a concept code.
     */
    private function recordTypeId(string $code, bool $required = true): int
    {
        $id = intval(ConceptCode::getRecTypeLocalID($code));
        if ($required && $id < 1) {
            throw new \DomainException('Record type '.$code.' is not defined in this database');
        }
        return $id;
    }

    /**
     * Local field id for a concept code.
     */
    private function fieldId(string $code, bool $required = true): int
    {
        $id = intval(ConceptCode::getDetailTypeLocalID($code));
        if ($required && $id < 1) {
            throw new \DomainException('Field '.$code.' is not defined in this database');
        }
        return $id;
    }

    /**
     * Message of a legacy error array.
     */
    private function errorMessage($error, string $default): string
    {
        if (is_array($error) && !empty($error['message'])) {
            return $default.': '.strip_tags((string)$error['message']);
        }
        return $default;
    }
}
