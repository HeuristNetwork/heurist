<?php
/**
 * index.php - Redirects to map.php, preserving query string.
 *
 * @fileOverview This file handles redirection from the base map directory to the main map script (map.php),
 * ensuring that any query parameters are passed along.
 * @project     Heurist academic knowledge management system
 * @package  Viewers\Map
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson ian.johnson.heurist@gmail.com
 * @since       4.0
 */

$script_name = 'map.php';
parse_str($_SERVER['QUERY_STRING'], $vars);
$query_string = http_build_query($vars);
header( 'Location: '.$script_name.'?'.$query_string );

