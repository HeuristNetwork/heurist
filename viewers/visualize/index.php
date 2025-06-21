<?php

/**
* index.php - Redirection index
*
* @fileOverview This file redirects requests to the main spring diagram visualization page,
* preserving any query string parameters.
* @package     Heurist academic knowledge management system
* @subpackage  /viewers/visualize
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

// Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
// with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
// Unless required by applicable law or agreed to in writing, software distributed under the License is
// distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
// See the License for the specific language governing permissions and limitations under the License.
//
$script_name = 'springDiagram.php';
//to avoid "Open Redirect" security warning
parse_str($_SERVER['QUERY_STRING'], $vars);
$query_string = http_build_query($vars);

header( 'Location: '.$script_name.'?'.$query_string );

