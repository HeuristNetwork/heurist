<?php
/**
* ReportPolicy.php - Report access rules and database report settings
*
* Settings come from the database settings file "Reports" (settings/reports.json):
*   allowDynamicReports - run record-set reports for an arbitrary query on
*                         request. A missing file means an existing database, so
*                         the default is true; new databases get false.
*   previewTimeLimit    - seconds for a test run (default 30)
*   generateTimeLimit   - seconds for a generation (default 600)
*   maxJobs             - active background jobs in the database (default 3)
*   generateMaxRecords  - largest record set of a generated report (default 100000)

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
use Heurist\Runtime\RuntimeContext;

/** Decides who may do what with reports. */
final class ReportPolicy
{
    /** Record limit of an editor test run. */
    public const TEST_RECORD_LIMIT = 50;

    private RuntimeContext $runtime;
    private array $settings;

    /**
     * @param RuntimeContext $runtime Current user.
     * @param array $settings Contents of the "Reports" database settings file.
     */
    public function __construct(RuntimeContext $runtime, array $settings = array())
    {
        $this->runtime = $runtime;
        $this->settings = $settings;
    }

    /** True when record-set reports may run for an arbitrary query on request. */
    public function allowDynamicReports(): bool
    {
        return !array_key_exists('allowDynamicReports', $this->settings)
            || filter_var($this->settings['allowDynamicReports'], FILTER_VALIDATE_BOOLEAN);
    }

    /** Time limit of a report test run (setting previewTimeLimit, seconds). */
    public function previewLimitSeconds(): int
    {
        return $this->intSetting('previewTimeLimit', 30, 5, 300);
    }

    /** Time limit of a report generation (setting generateTimeLimit, seconds). */
    public function generateLimitSeconds(): int
    {
        return $this->intSetting('generateTimeLimit', 600, 30, 7200);
    }

    /** Largest record set of a generated report (setting generateMaxRecords). */
    public function generateMaxRecords(): int
    {
        return $this->intSetting('generateMaxRecords', 100000, 1000, 5000000);
    }

    /** Active background jobs allowed in the database (setting maxJobs). */
    public function maxJobsPerDatabase(): int
    {
        return $this->intSetting('maxJobs', 3, 1, 20);
    }

    /** Current user id (0 = anonymous). */
    public function userId(): int
    {
        return $this->runtime->userId;
    }

    /** True for a logged-in user with access to this database. */
    public function isMember(): bool
    {
        return $this->runtime->userId > 0 && $this->runtime->hasAccess;
    }

    /** True for the database owner or a database manager. */
    public function isManager(): bool
    {
        return $this->runtime->userId > 0 && ($this->runtime->isDbOwner || $this->runtime->isAdmin);
    }

    /** Fail unless the user is logged in to this database. */
    public function requireMember(): void
    {
        if(!$this->isMember()){
            throw new DomainException('Authentication is required');
        }
    }

    /** Fail unless the user is the database owner or a manager. */
    public function requireManager(): void
    {
        if(!$this->isManager()){
            throw new DomainException('Only the database owner or a database manager can do this');
        }
    }

    /** Integer setting within [min, max], or the default. */
    private function intSetting(string $name, int $default, int $min, int $max): int
    {
        $value = $this->settings[$name] ?? null;
        return is_numeric($value) ? max($min, min($max, intval($value))) : $default;
    }
}
