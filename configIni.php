<?php
/** 
 * configIni.php - Configuration information for Heurist Initialization - USER EDITABLE
 * @version $Id$
 * @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 *
 * Note 1: Heurist requires MySQL 5
 *
 * Note 2: If host, name, username and password are left blank or fillEd with xxxxxx
 *         Heurist will look for a db.ini file in the parent directory
 **/

 
// [database]
// enter the host name or IP address of your MySQL server, blank --> localhost
// $dbHost = "heuristscholar.org";  will cause the code to use mysql on heuristscholar.org server
$dbHost = "";

// MySQL user with full write (create) access on this database server
// the default installation of MySql will give you "root" as the user with whatever password was typed "mySecretPwd"
// for which you would use
//$dbAdminUsername = "root";
//$dbAdminPassword = "mySecretPwd";
$dbAdminUsername = "";
$dbAdminPassword = "";

// MySQL user with readonly access on this database server
//assume you create a useracct named "readonly" with a password "readonlypwd"
//you would use
//$dbReadonlyUsername = "readonly";
//$dbAReadonlyPassword = "readonlypwd";
$dbReadonlyUsername = "";
$dbReadonlyPassword = "";

// dbPrefix will be prepended to all database names so that you
// can easily distinguish Heurist databases on your database system
// It may be left blank, in which case nothing is prepended.
$dbPrefix = "hdb_";

// the name of the default database which will be used if no db= is supplied in the URL
// change this to the name of the database you want to use for your day-to-day database
// eg. your web bookmarks and bibliography
// DO NOT include the prefix, this will be added automatically
$defaultDBname = "";

// [folders]

// The default root pathname of a directory where Heurist can store uploaded
// files eg. images, pdfs. PHP must be able to create subdirectories of this directory
// for each Heurist database and write files within them
// for instance if you would like to upload to /var/www/myUploadDir then use
//$defaultRootFileUploadPath = "/var/www/myUploadDir/";  BE SURE TO INCLUDE THE TRAILING "/"
//then when running heurist with db=main uploaded files will be loaded into /var/www/myUploadDir/main/
//leaving this blank will cause the document directory for your website to be used with an added "upload" subdir
$defaultRootFileUploadPath = "";


// [email]

// email address for the system administrator/installer of Heurist
// where you would like Heurist to deliver system alerts
// leaving this blank will suppress system alert emails
$sysAdminEmail = "";

// email address to which info@<installation server> will be redirected
// leaving this blank will suppress info inquiry emails
$infoEmail = "";
error_log("in config dbHost = $dbHost");

// system defult file
$parentIni = dirname(__FILE__)."/../heuristConfigIni.php";
error_log("in config dbHost = $dbHost  and parent path = $parentIni");
if (is_file($parentIni)){
error_log("trying to load parent configIni");
	include_once($parentIni);
}

?>