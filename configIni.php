<?php

/*<!--
 * configIni.php - Configuration information for Heurist Initialization - EDITABLE by the installer of Heurist
 * @version $Id$
 * @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

// if a heuristConfigIni.php file exists in the parent directory of the installation,
// it will override the ConfigIni.php in the installation. This allows unconfigured ConfigIni.php files to exist
// in multiple experimental codebases on a single server and avoids accidental distribution of passwords etc.

// [database]
// enter the host name or IP address of your MySQL server, blank --> localhost
// for example $dbHost = "heuristscholar.org";  will cause the code to use mysql on the server at heuristscholar.org
$dbHost = ""; // required

// MySQL user with full write (create) access on this database server
// The default installation of MySql gives you "root" as the master user with whatever password you set up for this,
// but you can specify another user and password with full access if preferred
$dbAdminUsername = "root"; // required
$dbAdminPassword = ""; //required

// MySQL user with readonly access on this database server
// For example, if there is a user account "readonly" with a password "readonlypwd", then you would use:
// $dbReadonlyUsername = "readonly";
// $dbAReadonlyPassword = "readonlypwd";
$dbReadonlyUsername = "root"; // required
$dbReadonlyPassword = ""; //required

// dbPrefix will be prepended to all database names so that you can easily distinguish Heurist databases on your database server
// from other MySQL databases. Some Admin tools such as PHPMyAdmin will group databases with common prefixes ending in underscore
// The prefix may be left blank, in which case nothing is prepended.
$dbPrefix = "hdb_"; // recommended

// The name of the default database which will be used if no db= is supplied in the URL.
// If the users of this server nearly always use a shared database eg. a workgroup database of web bookmarks, bibliographic or other research data,
// specify its name here. DO NOT include the prefix, this will be added automatically.
// If defaultDBName is blank, a list of available databases will be displayed on startup if none is specified
$defaultDBname = ""; // may be left blank

// A challenge password for creation of new databsaes. If left blank, any logged in user can create a new database
$passwordForDatabaseCreation=""; // blank = any logged in user can create

// [folders]

// The default root pathname of a directory where Heurist can store uploaded
// files eg. images, pdfs. PHP must be able to create subdirectories of this directory
// for each Heurist database and write files within them.
// For instance, if you would like to upload to /var/www/myUploadDir then use
// $defaultRootFileUploadPath = "/var/www/myUploadDir/";  BE SURE TO INCLUDE THE TRAILING "/"
// Then, when running Heurist with db=main, uploaded files will be loaded into /var/www/myUploadDir/main/
// defaults to root document directory/HEURIST_FILESTORE/dbname
$defaultRootFileUploadPath = "/var/www/htdocs/HEURIST_FILESTORE/"; // recommended

// [email]

// email address for the system administrator/installer of Heurist
// where you would like Heurist to deliver system alerts.
// Leaving this blank will suppress system alert emails
$sysAdminEmail = ""; // recommended

// email address to which info@<installation server> will be redirected.
// Leaving this blank will suppress info inquiry emails
$infoEmail = ""; // recommended


// system default file - if a heuristConfigIni.php file exists in the parent directory of the installation,
// it will override the ConfigIni.php in the installation. This allows unconfigured ConfigIni.php files to exist
// in multiple experimental codebases on a single server and avoids accidental distribution of passwords etc.
$parentIni = dirname(__FILE__)."/../heuristConfigIni.php";
if (is_file($parentIni)){
	include_once($parentIni);
}

// URL of 3d party website thumbnail service
$websiteThumbnailService = "http://immediatenet.com/t/m?Size=1024x768&URL=[URL]";
$websiteThumbnailUsername = "";
$websiteThumbnailPassword = "";
$websiteThumbnailXsize = 500;
$websiteThumbnailYsize = 300;
?>