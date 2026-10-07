<?php
/**
* ExportSettings.php - Database settings of record export
*
* Settings come from the database settings file "Export" (settings/export.json):
*   maxRecords   - records in one export, expanded records included (default 500000, up to 5000000)
*   timeLimit    - seconds for one export job (default 600, 30..7200)
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Export;

/** Limits of record export. */
final class ExportSettings
{
    private array $settings;

    /** @param array $settings Contents of the "Export" database settings file. */
    public function __construct(array $settings = array())
    {
        $this->settings = $settings;
    }

    /** Records allowed in one export (setting maxRecords). */
    public function maxRecords(): int
    {
        return $this->intSetting('maxRecords', 500000, 1, 5000000);
    }

    /** Time limit of one export job in seconds (setting timeLimit). */
    public function timeLimitSeconds(): int
    {
        return $this->intSetting('timeLimit', 600, 30, 7200);
    }

    private function intSetting(string $name, int $default, int $min, int $max): int
    {
        if(!isset($this->settings[$name]) || !is_numeric($this->settings[$name])){ return $default; }
        return max($min, min($max, intval($this->settings[$name])));
    }
}
