<?php

/*
* Copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/
/**
* viewRecord.php - PID redirector for viewing records.
* @fileOverview This script acts as a Persistent Identifier (PID) redirector to the main record viewing script (`viewers/record/viewRecord.php`). It provides a stable URL for accessing record views, abstracting the actual location of the viewing script. This is useful for maintaining stable links even if the internal file structure changes.
* @package     Heurist academic knowledge management system
* @subpackage  /redirects
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @author      Artem Osmakov <osmakov@gmail.com>
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
