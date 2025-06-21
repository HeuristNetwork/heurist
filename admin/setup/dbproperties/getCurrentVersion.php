<?php
/**
* getCurrentVersion.php - Retrieves the current Heurist code and database schema versions.
*
* @fileOverview This script is intended to run on a Heurist master index server.
*               When invoked by other Heurist servers, it returns the main Heurist
*               application version (HEURIST_VERSION) and the schema version of the
*               specified database, concatenated with a pipe character (e.g., "6.x.x|1.3.14").
*               This allows remote instances to check if they are up-to-date.
*
* @package     Heurist academic knowledge management system
* @subpackage  /admin/setup/dbproperties
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       3.1.0
*/

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

require_once dirname(__FILE__).'/../../../autoload.php';

$rawdata = '';

$mysqli = mysql__init(@$_REQUEST['db']);
$db_version = getDbVersion($mysqli);
if($db_version){
    $rawdata = HEURIST_VERSION."|".$db_version;
}

print $rawdata;
