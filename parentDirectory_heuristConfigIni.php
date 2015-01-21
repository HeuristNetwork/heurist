<?php

/**
* Configuration file for a Heurist installation - place in parent of the individual codebases,
* obviating the need to set these parameters for each version, avoiding redundancy and ensuring consistency
* Values in configIni.php in individual codebases, if set, will override values in this file for that codebase only
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Tom Murtagh, Kim Jackson, Stephen White
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


/*

    IMPORTANT NOTE
    **************
    This file should be placed in the parent directory of the Heurist instances, normally /var/www/html/HEURIST
    The values in this file will apply to all instances of Heurist, unless overridden in the individual configIni.php file

*/


// ------ ESSENTIAL SETTINGS ------------------------------------------------------------------------------------------------

// MySQL user with full write (create) access on this database server
// The default installation of MySql gives you "root" as the master user with whatever password you set up for this,
// but you can specify another user and password with full access if preferred. Password cannot be null
// IMPORTANT NOTE:
// MySQL passwords may not contain special characters - if generating random password generate as alphanumeric
// @todo: allow MySQL password other than root - currently (Dec 2013) gives error if not root

if (!@$dbAdminUsername) $dbAdminUsername = "root";  // required, MUST be 'root'
if (!@$dbAdminPassword) $dbAdminPassword = ""; // required

// MySQL user with readonly access on this database server. For example, if there is a user account
// "readonly" with a password "readonlypwd", then you would use:
// $dbReadonlyUsername = "readonly"; $dbReadonlyPassword = "readonlypwd";
// MySQL passwords may not contain special characters - if generating random password generate as alphanumeric

if (!@$dbReadonlyUsername) $dbReadonlyUsername = "readonly"; // required, can re-use root or have special user
if (!@$dbReadonlyPassword) $dbReadonlyPassword = ""; //required

// [database]
// enter the host name or IP address of your MySQL server, blank --> localhost
// for example $dbHost = "heuristscholar.org";  will cause the code to use mysql on the server at heuristscholar.org
// Can be used to specify a separate database server in a tiered setup

if (!@$dbHost) $dbHost= ""; //optional, blank = localhost ie. datsbase on same server as Heurist application



// [folders]

// The default root pathname of a directory where Heurist can store uploaded files eg. images, pdfs, as well as record type icons, templates,
// output files, scratch space and so forth. This directory should ideally not be web accessible (but see note below) - leaving it
// off the web protects the uploaded data, although filenames are obfuscated when uploaded to avoid sequential download by replication.
// PHP must have permissions to be able to create subdirectories of this directory for each Heurist database and write files within them.
// For instance, if you set $defaultRootFileUploadPath = "/srv/HeuristUploadDir/"; then, when running Heurist with db=hdb_xyz, uploaded files
// will be loaded into /srv/HeuristUploadDir/xyz/, and new databases will be created with a subdirectory in /srv/HeuristUploadDir
// NOTE: needs to be web accessible for now b/c record type icons and thumbnails in these directories are accessed directly to allow caching
// Protect files in other directories with apache directives in /etc/apache2/security or .htaccess file in the root of the filestore directory
// Normal installation directory is: /var/www/html/HEURIST/HEURIST_FILESTORE/

if (!@$defaultRootFileUploadPath) $defaultRootFileUploadPath = "/var/www/html/HEURIST/HEURIST_FILESTORE/";

// The URL to the default root file path defined above. Can be relative to root web address or absolute
// eg. http://heurist.sydney.edu.au/HEURIST/HEURIST_FILESTORE/

if (!@$defaultRootFileUploadURL) $defaultRootFileUploadURL = "/HEURIST/HEURIST_FILESTORE/"; //REQUIRED

// [email]

// email address for the system administrator/installer of Heurist
// where you would like Heurist to deliver system alerts.
// Leaving this blank will suppress system alert emails

if (!@$sysAdminEmail) $sysAdminEmail = ""; // recommended

// email address to which info@<installation server> will be redirected.
// Leaving this blank will suppress info inquiry emails

if (!@$infoEmail) $infoEmail = "info@heuristscholar.org"; // recommended

// email address to which bug reports will be sent.
// Leaving this blank will send bug reports to the Heurist development team

if (!@$bugEmail) $bugEmail = "info@heuristscholar.org"; // recommended, set to your email if you are doing development work


// -------- THE REST IS OPTIONAL ----------------------------------------------------------------------------------------------

// A simple challenge password for creation of new databases. If left blank, any logged in user can create a new database

if (!@$passwordForDatabaseCreation) $passwordForDatabaseCreation=""; // normally blank = any logged in user can create

// A simple challenge password for deletion of databases. If left blank, nobody can delete databases
if(!@$passwordForDatabaseDeletion) $passwordForDatabaseDeletion="";

// [server]
// enter the server name or IP address of your Web server, null will pull SERVER_NAME from the request header
// for example $serverName = "heuristscholar.org";  Be sure to include the port if not port 80

if (!@$serverName) $serverName = null; // if not 'null', overrides default taken from request header SERVER_NAME

// The HTTP address:port of the proxy server that will allow access to the internet for external URI's
// this address will allow Heurist to request content through the firewall for general Internet URI's

if (!@$httpProxy) $httpProxy = ""; // blank = assumes direct internet access from server
if (!@$httpProxyAuth) $httpProxyAuth = ""; // authorization for proxy server "username:password"

// The base URL for Elastic Search (Lucene). If left blank, some search methods may not be available.
// See http://www.elasticsearch.org for information on installation (only dependency is Java)
// Specify address as http://129.78.138.64 or http://frog.net (note: localhost may also work)

if (!@$indexServerAddress) $indexServerAddress = ""; // REQUIRED if Elastic Search is installed, include http://
if (!@$indexServerPort) $indexServerPort = "9200";   // default for Elastic Search

// dbPrefix will be prepended to all database names so that you can easily distinguish Heurist databases on your database server
// from other MySQL databases. Some Admin tools such as PHPMyAdmin will group databases with common prefixes ending in underscore
// The prefix may be left blank, in which case nothing is prepended. For practial management we strongly recommend a prefix.

if (!@$dbPrefix) $dbPrefix = "hdb_"; // WE STRONGLY recommend retaining hdb_

// URL of 3d party website thumbnail service. Heurist can call any thumbnailing service which returns an
// appropriate JPEG or GIF file when passed the URL of a web page. This may be a thumbnail of a security block page
// if the URL is passworded. The thumbnailing service is called automatically when web pages are bookmarked.
// Beware of exceeding free thumbnailign limits if your database is used for a lot of web page bookmarking

if (!@$websiteThumbnailService) $websiteThumbnailService = "http://immediatenet.com/t/m?Size=1024x768&URL=[URL]";
if (!@$websiteThumbnailUsername) $websiteThumbnailUsername = "";
if (!@$websiteThumbnailPassword) $websiteThumbnailPassword = "";
if (!@$websiteThumbnailXsize) $websiteThumbnailXsize = 500; // required
if (!@$websiteThumbnailYsize) $websiteThumbnailYsize = 300; // required

?>