<?php
/**
* RecordActionInterface.php - One record action applied to one record (plan 33)
*
* Outcome of run(): {status, reason?, message?, found?, added?, removed?}
*   status  changed | unchanged | skipped | error ("changed" = deleted for the delete action)
*   reason  of a skipped record: structure, rights, required, single_value, max,
*           language_exists, blank, missing
*
* @project     Heurist academic knowledge management system
* @package     Records\Actions
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Actions;

/** Applies an action to one record. */
interface RecordActionInterface
{
    /**
     * Apply the action to one record (its own transaction).
     *
     * @param int $recordId Record id.
     * @param int $rectype Its record type.
     * @return array Outcome (see the file header).
     */
    public function run(int $recordId, int $rectype): array;
}
