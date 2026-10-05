<?php
/**
* ReportRecordWriterInterface.php - Record writes needed by the reports manager
*
* srv/ is read-only for records. Registering a template, deleting a report and
* installing the report definitions are delegated to the legacy record save
* and definitions import code through this interface.
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

/** Creates and deletes Custom Report records and installs their definitions. */
interface ReportRecordWriterInterface
{
    /**
     * Create a Custom Report record for a template file.
     *
     * @param string $title Report title.
     * @param string $templateFile Template file name.
     * @param bool $isCardView True for a single-record (card) report.
     * @param string $description Optional description.
     * @return int New record id.
     */
    public function createReport(string $title, string $templateFile, bool $isCardView, string $description = ''): int;

    /**
     * Create a Report Schedule record.
     *
     * @param array $schedule title, reportId, dataSourceId, output, format, intervalMinutes (null = manual).
     * @param int $ownerGroupId Owner group (the report's).
     * @return int New record id.
     */
    public function createSchedule(array $schedule, int $ownerGroupId): int;

    /**
     * Change a Custom Report record. Only the given keys are written:
     * title, description, file, isCardView.
     *
     * @param int $recordId Record id.
     * @param array $values New values.
     * @return void
     */
    public function updateReport(int $recordId, array $values): void;

    /**
     * Change a Report Schedule record. Only the given keys are written:
     * title, dataSourceId, output, format, intervalMinutes (0 = on request).
     *
     * @param int $recordId Record id.
     * @param array $values New values.
     * @return void
     */
    public function updateSchedule(int $recordId, array $values): void;

    /**
     * Delete one record with the legacy delete rules (links, bookmarks, archive).
     *
     * @param int $recordId Record id.
     * @return void
     */
    public function deleteRecord(int $recordId): void;

    /**
     * Import the Custom Report / Report Schedule definitions when they are missing
     * and convert usrReportSchedule rows into Report Schedule records.
     *
     * @return array<int,string> Report lines.
     */
    public function setup(): array;
}
