<?php
/**
* index.php - Redirection index
*
* @fileOverview This file redirects requests to the main spring diagram visualization page,
* preserving any query string parameters.
* @project     Heurist academic knowledge management system
* @package  Viewers\Network
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
$script_name = 'springDiagram.php';
//to avoid "Open Redirect" security warning
parse_str($_SERVER['QUERY_STRING'], $vars);
$query_string = http_build_query($vars);

header( 'Location: '.$script_name.'?'.$query_string );

