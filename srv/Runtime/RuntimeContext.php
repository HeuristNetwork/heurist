<?php
/**
* RuntimeContext.php - Immutable request execution context
*
* Carries the small set of database, user and URL values required by the new
* read-only workflow without exposing the legacy System object to services.
*
* @project     Heurist academic knowledge management system
* @package     Runtime
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Runtime;

/** Values describing the current database and authenticated user. */
final class RuntimeContext
{
    public string $databaseName;
    public string $databaseNameFull;
    public int $userId;
    public string $baseUrl;
    public array $groupIds;
    public bool $isDbOwner;
    public bool $hasAccess;
    public bool $isAdmin;

    /** Initialise the context with already validated runtime values. */
    public function __construct(
        string $databaseName,
        string $databaseNameFull,
        int $userId,
        string $baseUrl,
        array $groupIds = array(),
        bool $isDbOwner = false,
        bool $hasAccess = false,
        bool $isAdmin = false
    ) {
        $this->databaseName = $databaseName;
        $this->databaseNameFull = $databaseNameFull;
        $this->userId = max(0, $userId);
        $this->baseUrl = $baseUrl === '' ? '' : rtrim($baseUrl, '/').'/';
        $this->groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
        $this->isDbOwner = $isDbOwner;
        $this->hasAccess = $hasAccess;
        $this->isAdmin = $isAdmin;
    }

    /**
     * Build a temporary bridge from the initialized legacy System object.
     *
     * @param object $system Initialized legacy System instance.
     */
    public static function fromLegacySystem($system): self
    {
        $shortName = (string)$system->dbname();
        $fullName = method_exists($system, 'dbnameFull')
            ? (string)$system->dbnameFull()
            : (defined('HEURIST_DB_PREFIX') ? HEURIST_DB_PREFIX : '').$shortName;
        return new self(
            $shortName,
            $fullName,
            intval($system->getUserId()),
            defined('HEURIST_BASE_URL') ? HEURIST_BASE_URL : '',
            method_exists($system, 'getUserGroupIds') ? (array)$system->getUserGroupIds() : array(),
            method_exists($system, 'isDbOwner') && $system->isDbOwner(),
            method_exists($system, 'hasAccess') && $system->hasAccess(),
            method_exists($system, 'isAdmin') && $system->isAdmin()
        );
    }
}
