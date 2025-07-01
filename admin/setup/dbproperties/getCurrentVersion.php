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
* @project     Heurist academic knowledge management system
* @package Admin/dbproperties
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       3.1.0
*/

require_once dirname(__FILE__).'/../../../autoload.php';

$rawdata = '';

$mysqli = mysql__init(@$_REQUEST['db']);
$db_version = getDbVersion($mysqli);
if($db_version){
    $rawdata = HEURIST_VERSION."|".$db_version;
}

print $rawdata;
