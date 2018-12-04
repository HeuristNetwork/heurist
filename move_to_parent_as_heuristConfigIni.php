<?php

/**
* Override configuration file for a Heurist installation - place in parent of the individual codebases,
* obviating the need to set these parameters for each version, avoiding redundancy and ensuring consistency
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2018 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// See configIni.php for documentation. If this file is placed in the parent directory of Heurist, eg. the web root,
// and renamed heuristConfigIni.php, the values in this file will overide those given (if any) in the
// configIni.php file in each copy of Heurist

if (!@$serverName) $serverName = null; // override default taken from request header SERVER_NAME

// ------ VALUES YOU SHOULD SET -------------------------------------------------------------------------------------------

// ------ ESSENTIAL SETTINGS ------------------------------------------------------------------------------------------------

// MySQL user with full write (create) access on this database server
// The default installation of MySql gives you "root" as the master user with whatever password you set up for this,
// but you can specify another user and password with full access if preferred. Password cannot be null
// MySQL passwords may not contain special characters - if generating random password generate as alphanumeric
if (!@$dbAdminUsername) $dbAdminUsername = "";  // required
if (!@$dbAdminPassword) $dbAdminPassword = ""; // required

// MySQL user with readonly access on this database server. For example, if there is a user account
// MySQL passwords may not contain special characters - if generating random password generate as alphanumeric
if (!@$dbReadonlyUsername) $dbReadonlyUsername = ""; // required, can re-use root or have special user
if (!@$dbReadonlyPassword) $dbReadonlyPassword = ""; //required


// [folders]

if (!@$defaultRootFileUploadURL) $defaultRootFileUploadURL = "http://localhost/HEURIST/HEURIST_FILESTORE/";
// REQUIRED: defines URL of Heurist filestore - location of files associated with databases
if (!@$defaultRootFileUploadPath) $defaultRootFileUploadPath = "/var/www/html/HEURIST/HEURIST_FILESTORE/";

// [email]

if (!@$sysAdminEmail) $sysAdminEmail = "info@HeuristNetwork.org"; 
// REQUIRED, please set to email of the system administrator or mailing group
if (!@$infoEmail) $infoEmail = "info@HeuristNetwork.org";
// recommended, please set to the email of whoever provides user assistance
if (!@$bugEmail) $bugEmail = "info@HeuristNetwork.org";
// recommended, set to info@heuristNetwork.org if your server is running a standard Heurist installation

// -------- THE REST IS OPTIONAL ----------------------------------------------------------------------------------------------

// A simple challenge password for creation of new databases. If left blank, any logged in user can create a new database
if (!@$passwordForDatabaseCreation) $passwordForDatabaseCreation=""; // normally blank = any logged in user can create
if (!@$passwordForDatabaseDeletion) $passwordForDatabaseDeletion=""; // if blank only db owner can delete
if (!@$passwordForReservedChanges) $passwordForReservedChanges="";// if blank, no-one can modify reserved fields, otherwise challenge
if (!@$passwordForServerFunctions) $passwordForServerFunctions="";// if blank, no-one can run server analysis functions - risk of overload - otherwise challenge

if (!@$dbHost) $dbHost= ""; //optional, blank = localhost
if (!@$httpProxy) $httpProxy = ""; // blank = assumes direct internet access from server
if (!@$httpProxyAuth) $httpProxyAuth = ""; // authorization for proxy server "username:password"
if (!@$indexServerAddress) $indexServerAddress = ""; 
if (!@$indexServerPort) $indexServerPort = "9200";

// dbPrefix will be prepended to all database names so that you can easily distinguish Heurist databases on your database server
// from other MySQL databases. Some Admin tools such as PHPMyAdmin will group databases with common prefixes ending in underscore
// The prefix may be left blank, in which case nothing is prepended. For practial management we strongly recommend a prefix.
if (!@$dbPrefix) $dbPrefix = "hdb_"; // WE STRONGLY recommend retaining hdb_

// URL of 3d party website thumbnail service. Heurist can call any thumbnailing service which returns an
// appropriate JPEG or GIF file when passed the URL of a web page. This may be a thumbnail of a security block page
// if the URL is passworded. The thumbnailing service is called automatically when web pages are bookmarked.
// Beware of exceeding free thumbnailign limits if your database is used for a lot of web page bookmarking
if (!@$websiteThumbnailService) $websiteThumbnailService = "https://api.thumbnail.ws/api/ab73cfc7f4cdf591e05c916e74448eb37567feb81d44/thumbnail/get?url=[URL]&width=320";
if (!@$websiteThumbnailUsername) $websiteThumbnailUsername = "";
if (!@$websiteThumbnailPassword) $websiteThumbnailPassword = "";
if (!@$websiteThumbnailXsize) $websiteThumbnailXsize = 500; // required
if (!@$websiteThumbnailYsize) $websiteThumbnailYsize = 300; // required
?>