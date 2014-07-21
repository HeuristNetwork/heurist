<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* Override configuration file for a Heurist installation - place in parent of the individual codebases,
* obviating the need to set these parameters for each version, avoiding redundancy and ensuring consistency
*
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.5
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

// See configIni.php for documentation. If this file is placed in the parent directory of Heurist, eg. the web root,
// the values in this file will overide those given (if any) in the configIni.php file in each copy of Heurist

if (!@$serverName) $serverName = null; // override default taken from request header SERVER_NAME

if (!@$dbHost) {
	$dbHost= ""; //optional, blank = localhost
	$dbAdminUsername = "";  // required
	$dbAdminPassword = ""; // required
	$dbReadonlyUsername = ""; // required
	$dbReadonlyPassword = ""; //required
}

if (!@$dbPrefix) $dbPrefix = "hdb_"; // recommend retaining hdb_

if (!@$indexServerAddress) $indexServerAddress = ""; // set to IP address of server
if (!@$indexServerPort) $indexServerPort = "9200";

if (!@$defaultDBname) $defaultDBname = ""; // not required, generally best left blank
if (!@$httpProxy) $httpProxy = ""; // if access to the outside world is through a proxy
if (!@$httpProxyAuth) $httpProxyAuth = ""; // ditto
if (!@$passwordForDatabaseCreation) $passwordForDatabaseCreation=""; // blank = any logged in user can create

if (!@$defaultRootFileUploadPath) $defaultRootFileUploadPath = ""; // defines location of all files associated with a database
if (!@$sysAdminEmail) $sysAdminEmail = ""; // recommended
if (!@$infoEmail) $infoEmail = ""; // recommended
if (!@$bugEmail) $bugEmail = ""; // recommended, set to info@heuristscholar.org if your server is running a standard Heurist installation

if (!@$websiteThumbnailService) $websiteThumbnailService = "";
if (!@$websiteThumbnailUsername) $websiteThumbnailUsername = "";
if (!@$websiteThumbnailPassword) $websiteThumbnailPassword = "";
if (!@$websiteThumbnailXsize) $websiteThumbnailXsize = 500; // required
if (!@$websiteThumbnailYsize) $websiteThumbnailYsize = 300; // required

?>