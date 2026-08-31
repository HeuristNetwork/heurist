<?php
/**
* UnsupportedQueryException.php - Unsupported valid record query
*
* @project Heurist academic knowledge management system
* @package Records\Query
* @link https://HeuristNetwork.org
* @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author Artem Osmakov <osmakov@gmail.com>
* @author Ian Johnson <ian.johnson.heurist@gmail.com>
* @since 7.0
*/
declare(strict_types=1);
namespace Heurist\Records\Query;
/** Raised when valid query syntax is outside the migrated execution engine. */
class UnsupportedQueryException extends \RuntimeException {}
