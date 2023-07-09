<?php

/**
* Override configuration file for a Heurist installation - place in parent of the individual codebases,
* obviating the need to set these parameters for each version, avoiding redundancy and ensuring consistency
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// See configIni.php for documentation. 
// This file shoudl be placed in the parent directory of Heurist, eg. the web root,
// and renamed heuristConfigIni.php, the values in this file will then overide those given (if any) 
// in the configIni.php file in each copy of Heurist on the server (in practice the
// configIni.php file does NOT specify the values, since heuristConfigIni.php
// provides all copies with shared values)

// **** DO NOT EDIT THE COPY OF THIS FILE IN THE CODEBASE (..../HEURIST/movetoparent) 
//      as this will have no effect (it is the copy in ..../HEURIST/ which is used)

if (!@$serverName) $serverName = null; // override default taken from request header SERVER_NAME
if (!@$mailDomain) $mailDomain = null; // You may need to set mail domain if it does not use server domain

if (!@$heuristReferenceServer) $heuristReferenceServer = "https://heuristref.net"; 
// address of the "reference server", ie. the server where the master index and code updates are located 

// ------ VALUES YOU SHOULD SET -------------------------------------------------------------------------------------------

// ------ ESSENTIAL SETTINGS ------------------------------------------------------------------------------------------------

// MySQL user with full write (create) access on this database server
// The default installation of MySql gives you "root" as the master user with whatever password you set up for this,
// but you can specify another user and password with full access if preferred. Password cannot be null
// MySQL passwords may not contain special characters - if generating random password generate as alphanumeric
// Values can be assigned to environment variable or defined here
if (!@$dbAdminUsername) $dbAdminUsername = getenv("DB_ADMIN_USERNAME") ?getenv("DB_ADMIN_USERNAME") : "";  // required
if (!@$dbAdminPassword) $dbAdminPassword = getenv("DB_ADMIN_PASSWORD") ?getenv("DB_ADMIN_PASSWORD") : "";  // required


// [folders]

// REQUIRED: defines URL of Heurist filestore (contains files associated with databases)
if (!@$defaultRootFileUploadURL) $defaultRootFileUploadURL = "http://localhost/HEURIST/HEURIST_FILESTORE/";
// REQUIRED: defines internal location of Heurist filestore
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
if (!@$passwordForDatabaseDeletion) $passwordForDatabaseDeletion=""; // if blank only db owner can delete. 
   // If set >14 characters, system adminstrator can delete up to 10 at a time (with password challenge) 
if (!@$passwordForReservedChanges) $passwordForReservedChanges="";// if blank, no-one can modify reserved fields, otherwise challenge
if (!@$passwordForServerFunctions) $passwordForServerFunctions="";// if blank, no-one can run server analysis functions - risk of overload - otherwise challenge

if (!@$dbHost) $dbHost= ""; //optional, blank = localhost


$httpProxyAlwaysActive = false; // if true - always use proxy for CURL, otherwise proxy will be used for non heurist resources mostly
if (!@$httpProxy) $httpProxy = ''; // blank = assumes direct internet access from server
if (!@$httpProxyAuth) $httpProxyAuth = ''; // authorization for proxy server "username:password"
// If set this will override the value of bypassProxy when making external requests via cURL within utils_file.php


//  set to IP address and port of Elastic search server, if used
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

// Optional: if set to -1 this will block creation of new websites with a message to contact sysadmin 
// $allowCMSCreation = -1;

// This allows selected databases on specific servers to access the 
// ESTC lookup data on the reference server (covered by a strict use license)
// $ESTC_UserName = "????"; // User name and password for access to the database
// $ESTC_Password = "????";
// $ESTC_PermittedDBs=""; // comma-separated list of database names on the server
// $ESTC_ServerURL = "https://HeuristRef.Net";

// Add paths to any servers previously used (since 2020), in the form https://server.domain,http://server.domain, … 
// which may have been incorporated in web pages and which should be replaced by local relative paths
// These paths are used in conjunction with Admin > Server management > Fix absolute paths in web page content
// if (!@$absolutePathsToRemoveFromWebPages) $absolutePathsToRemoveFromWebPages = array('https://heuristplus.sydney.edu.au');
$absolutePathsToRemoveFromWebPages = null;

//
// On some servers the severe restrictions can be activated. They may prevent sending to server json, html or js code snippets
// In other words it forbids any request which is suspected as malicious
// To workaround set this value to "1" to encode json requests for record edit
//
if (!@$needEncodeRecordDetails) $needEncodeRecordDetails = 0; 

// common languages for translation database definitions (ISO639-2 codes) in upper case
$common_languages_for_translation = array('ARA','BUR','CHI','CZE','DAN','DUT','ENG','EST','FIN','FRE','GER','GRE','HIN','HUN','IND','ITA','JPN','JAV','KOR','KUR','LAO','LAT','MAO','MAY','MKH','BUR','NEP','NOR','PER','POR','RUS','SLO',' SLV','SPA','SWA','SWE','THA','TIB','TUR','UIG','UKR','VIE','YID','ZUL');

// array of saml service providers
$saml_service_provides = null;
//for example:  $saml_service_provides = array("default-sp"=>"BnF Authentication");
?>