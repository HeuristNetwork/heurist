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
* configutration file for a heurist installation
*
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

// Set the version number of the Heurist program
$version = "3.1.4"; // required - DO NOT CHANGE

// if a heuristConfigIni.php file exists in the parent directory of the installation,
// it will override the ConfigIni.php in the installation. This allows unconfigured ConfigIni.php files to exist
// in multiple experimental codebases on a single server and avoids accidental distribution of passwords etc.

// [server]
// enter the server name or IP address of your Web server, null will pull SERVER_NAME from the request header
// for example $serverName = "heuristscholar.org";  Be sure to include the port if not port 80
$serverName = null; // override default taken from request header SERVER_NAME

// [database]
// enter the host name or IP address of your MySQL server, blank --> localhost
// for example $dbHost = "heuristscholar.org";  will cause the code to use mysql on the server at heuristscholar.org
$dbHost = ""; // leave blank for localhost

// MySQL user with full write (create) access on this database server
// The default installation of MySql gives you "root" as the master user with whatever password you set up for this,
// but you can specify another user and password with full access if preferred. Password cannot be null
$dbAdminUsername = ""; // required
$dbAdminPassword = ""; //required

// MySQL user with readonly access on this database server
// For example, if there is a user account "readonly" with a password "readonlypwd", then you would use:
// $dbReadonlyUsername = "readonly";
// $dbAReadonlyPassword = "readonlypwd";
// Password cannot be null
$dbReadonlyUsername = ""; // required
$dbReadonlyPassword = ""; //required

// dbPrefix will be prepended to all database names so that you can easily distinguish Heurist databases on your database server
// from other MySQL databases. Some Admin tools such as PHPMyAdmin will group databases with common prefixes ending in underscore
// The prefix may be left blank, in which case nothing is prepended. For practial management we strongly recommend a prefix.
$dbPrefix = "hdb_"; // strongly recommended

// The name of the default database which will be used if no db= is supplied in the URL.
// If the users of this server nearly always use a shared database eg. a workgroup database of web bookmarks, bibliographic or other research data,
// specify its name here. DO NOT include the prefix, this will be added automatically.
// If defaultDBName is blank, a list of available databases will be displayed on startup if none is specified
$defaultDBname = ""; // may be left blank, should not include prefix (see previous))

// enter the server name or IP address of your Memcached server, blank --> localhost
$memcachedHost = ""; // required
// enter the port of your Memcached server, blank --> 11211
$memcachedPort = ""; // required

// The HTTP address:port of teh proxy server that will allow access to the internet for external URI's
// this address will allow heurist to request content through the firewall for general Internet URI's
$httpProxy = ""; // blank = assumes direct internet access from server - ok for laptop installations.

// A simple challenge password for creation of new databases. If left blank, any logged in user can create a new database
$passwordForDatabaseCreation=""; // blank = any logged in user can create

// [folders]

// The default root pathname of a directory where Heurist can store uploaded
// files eg. images, pdfs. PHP must have permissions to be able to create subdirectories of this directory
// for each Heurist database and write files within them.
// For instance, if you would like to upload to a database directory under /var/www/myUploadDir then use
// $defaultRootFileUploadPath = "/var/www/myUploadDir/";
// Then, when running Heurist with db=xyz, uploaded files will be loaded into /var/www/myUploadDir/xyz/
$defaultRootFileUploadPath = ""; // recommended, defaults to <web root>/HEURIST_FILESTORE/<dbname>

// The pathname relative to the document root of a directory where Heurist can store uploaded
// icons eg. .png for rectypes. PHP must be able to create subdirectories off this directory
// for each Heurist database and write files within them. Record type icons are stored in the rectype-icons subdirectory.
// For instance, if you would like to upload to /var/www/htdocs/myUploadDir/<dbname>/rectype-icons/
// assuming that /var/www/htdocs is the sites http document root then use
// $siteRelativeIconUploadBasePath = "/myUploadDir/";  BE SURE TO INCLUDE THE TRAILING "/"
// Then, when running Heurist with db=xyz, uploaded icons will be loaded
// into /var/www/htdocs/myUploadDir/xyz/rectype-icons/
$siteRelativeIconUploadBasePath = ""; // recommended, defaults /HEURIST_FILESTORE/

// [email]

// email address for the system administrator/installer of Heurist
// where you would like Heurist to deliver system alerts.
// Leaving this blank will suppress system alert emails
$sysAdminEmail = ""; // recommended

// email address to which info@<installation server> will be redirected.
// Leaving this blank will suppress info inquiry emails
$infoEmail = ""; // recommended

// email address to which bug reports will be sent.
// Leaving this blank will send to heurist development
$bugEmail = ""; // recommended


// system default file - if a heuristConfigIni.php file exists in the parent directory of the installation,
// it will override the ConfigIni.php in the installation. This allows unconfigured ConfigIni.php files to exist
// in multiple experimental codebases on a single server and avoids accidental distribution of passwords etc.
$parentIni = dirname(__FILE__)."/../heuristConfigIni.php";
if (is_file($parentIni)){
	include_once($parentIni);
}

// URL of 3d party website thumbnail service. Heurist can call any thumbnailing service which returns an
// appropriate JPEG or GIF file when passed the URL of a web page. This may be a thumbnail of a secirity block page
// if the URL is passworded. The thumbnailign service is called automatically when web pages are bookmarked.
$websiteThumbnailService = "http://immediatenet.com/t/m?Size=1024x768&URL=[URL]";
$websiteThumbnailUsername = "";
$websiteThumbnailPassword = "";
$websiteThumbnailXsize = 500;
$websiteThumbnailYsize = 300;

?>
