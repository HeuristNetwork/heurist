<?php
/**
* ReportRecordWriterInterface.php - Setup of the report definitions (legacy import)
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

/** Installs the report definitions. Records are written by the generic record services. */
interface ReportRecordWriterInterface
{
    /**
     * Import the Custom Report / Report Schedule definitions when they are missing
     * and convert usrReportSchedule rows into Report Schedule records.
     *
     * @return array<int,string> Report lines.
     */
    public function setup(): array;
}
