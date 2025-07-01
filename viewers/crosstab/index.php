<?php
/**
* index.php - Redirects to the main crosstabs viewer page.
*
* @fileOverview This script handles incoming requests to the /viewers/crosstab/ directory
* and redirects them to `crosstabs.php`, preserving any query string parameters.
* This ensures that users accessing the directory directly are forwarded to the
* functional page of the crosstab viewer.
*
* @project     Heurist academic knowledge management system
* @package  Viewers\Crosstab
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

$script_name = 'crosstabs.php';
parse_str($_SERVER['QUERY_STRING'], $vars);
$query_string = http_build_query($vars);
header( 'Location: '.$script_name.'?'.$query_string );

