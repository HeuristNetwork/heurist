<?php
/**
* DatabaseException.php - Modern database failure
*
* Represents a database error that may be logged with its original exception
* while exposing a safe message at the API boundary.
*
* @project     Heurist academic knowledge management system
* @package     Database
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Database;

/** Raised when a parameterised database operation cannot be completed. */
final class DatabaseException extends \RuntimeException
{
}
