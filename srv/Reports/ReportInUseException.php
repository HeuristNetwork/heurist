<?php
/**
* ReportInUseException.php - A template can not go while outputs point to its report (plan 30)
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

use RuntimeException;

/** The report has outputs. */
final class ReportInUseException extends RuntimeException
{
    private int $outputs;

    /** @param int $outputs Number of output records of the report. */
    public function __construct(int $outputs)
    {
        parent::__construct('This template has '.$outputs.' generated report(s): delete them first');
        $this->outputs = $outputs;
    }

    /** Number of output records of the report. */
    public function outputs(): int
    {
        return $this->outputs;
    }
}
