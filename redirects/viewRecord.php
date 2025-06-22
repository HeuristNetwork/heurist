<?php
/**
* viewRecord.php - PID redirector for viewing records.
* @fileOverview This script acts as a Persistent Identifier (PID) redirector to the main record viewing script (`viewers/record/viewRecord.php`). It provides a stable URL for accessing record views, abstracting the actual location of the viewing script. This is useful for maintaining stable links even if the internal file structure changes.
* @package     Heurist academic knowledge management system
* @subpackage  /redirects
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       3.1.0
*/

// Redirect to .../viewers/record/viewRecord.php
// TODO: change to use /records/view/renderRecordAsXML.php (XML with parameterisation for human-readable view)

//to avoid "Open Redirect" security warning
parse_str($_SERVER['QUERY_STRING'], $vars);
$query_string = http_build_query($vars);

redirectURL('../viewers/record/viewRecord.php?'.$query_string);
exit;
?>
