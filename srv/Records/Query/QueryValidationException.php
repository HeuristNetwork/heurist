<?php
/**
* QueryValidationException.php - Invalid record query input
*
* @project Heurist academic knowledge management system
* @package Records\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     This software may be installed and operated only on servers operated by Heurist Network, or with the prior written permission of Heurist Network. No right is granted to copy, distribute, modify, install or operate this software elsewhere.
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/
declare(strict_types=1);
namespace Heurist\Records\Query;
/** Raised when record query syntax or values are invalid. */
class QueryValidationException extends \InvalidArgumentException {}
