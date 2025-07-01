<?php
/**
* getStructure_V1.php - Redirector for fetching database structure.
* @fileOverview This script acts as a stable URL redirector to `getDBStructureAsSQL.php`. It ensures that external services relying on this URL for database structure information are not affected by potential codebase restructuring. The `_V1` suffix indicates version 1 of this redirector, allowing for future versions with potentially different behaviors or target scripts.
* @project     Heurist academic knowledge management system
* @package  Core
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4
*/
//to avoid "Open Redirect" security warning
parse_str($_SERVER['QUERY_STRING'], $vars);
$query_string = http_build_query($vars);

redirectURL('../hserv/structure/export/getDBStructureAsSQL.php?'.$query_string);
exit;
?>
