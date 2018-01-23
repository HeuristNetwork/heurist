<?php

/**
* configIni.php: configuration file for this Heurist instance.
*
* Note: This file is overriden by heuristConfigIni.php in the parent directory, if present, allowing a single config file for all instances
*       Program version number, however, is always specified by this file and should not be changed
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


/*
**************************************************************

WARNING:

Setting any of the values in the configIni.php file overrides the
values set in ../heuristConfigIni.php FOR THAT INSTANCE ONLY of Heurist.
All other instances will use the value set in ../heuristConfigIni.php

WE THEREFORE RECOMMEND NOT CHANGING ANYTHING IN THIS FILE

Values in this file should only be set for testing purposes or
by an experienced sysadmin for very unusual server setups

**************************************************************
*/

/* --------------------------------------------------------------------------------------------

Setting up the server to support multiple code versions
-------------------------------------------------------

Note: This is done automatically by the installation routies in install_heurist.sh

Move the file move_to_parent_as_heuristConfigIni.php to the parent directory of the codebase
rename to heuristConfigIni.php and enter MySQL passwords, paths and other config settings there.
This allows Heurist instances to exist as multiple codebases on a single server and avoids
the need for duplication of information or the accidental distribution of passwords etc.
if one of these codebases is used as a code source.

Also move move_to_parent_as_index.html - the Heurist 'switchboard' to the parent directory
of the codebase and rename it to index.html

--------------------------------------------------------------------------------------------
*/

// *** DO NOT CHANGE VERSION NUMBER, THIS IS SET BY THE HEURIST DEVELOPMENT TEAM ***

$version = "4.5.7"; // sets current program version number, determined by Heurist development lead

// 4.5.7 23 Jan 18 A large number of cosmetic changes and bugfixes particularly to editing function, merging recods 
// 4.5.6 30 Dec 17  Finalise new record edit, lota of bug fixes. Update production version
// 4.5.4 9 Dec 17  Improvements to new field definition, term list dropdowns, KML import, bug fixes
// 4.5.3 28 Nov 17  Minor bug fixes
// 4.5.2 22 Nov 17 Fixed bugs arising from DB format changes, improve digitiser layout, fix errors in biblio structures,
//                 improve structure visualise, fix up child record functions and new editor glitches, 
//                 many bug fixes, lots of cosmetic improvements
// 4.5.1 21 Sep 17 Nips and tucks to database structure, DB version 1.2.0
// 4.2.23 3 Aug17 Bug fixes and draft of new record edit function (WIP)
// 4.2.22  Many fixes/improvements for facet searches, file verification, thumbnailing, maps and timelines
// 4.2.21 19Jun17. Clean up methods for uploaded files, multi-record attachment, increase thumbnail image size, 
//                 improve vocabulary tree, handling of repeating and missing value matching in CSV import,
//                 simplify navigation panel 

// 4.2.20 5Jun17. Improved file upload and verifiction, Beyond 1914 website, new ftrs on facet searches, bug fixes


// *** DO NOT SET THESE UNLESS YOU KNOW WHAT YOU ARE DOING ***
//     they override the values set in ../heuristConfigIni.php
$dbHost = "";
$dbAdminUsername = "";
$dbAdminPassword = "";
$dbReadonlyUsername = "";
$dbReadonlyPassword = "";
//$serverName = "null";
$dbPrefix = "";

// Elastic Search (Lucene) server
$indexServerAddress = ""; 
$indexServerPort = "";

$httpProxy = "";
$httpProxyAuth = "";
$indexServerAddress="";
$indexServerPort="";

$passwordForDatabaseCreation="";
$passwordForDatabaseDeletion="";
$passwordForReservedChanges="";

$defaultRootFileUploadPath ="";
$defaultRootFileUploadURL = "";
$sysAdminEmail = "";
$infoEmail = "";
$bugEmail = "";
$websiteThumbnailService = "";
$websiteThumbnailUsername = "";
$websiteThumbnailPassword = "";
// $websiteThumbnailXsize = 500;
// $websiteThumbnailYsize = 300;

// system default file - if a heuristConfigIni.php file exists in the parent directory of the installation,
// the configIni.php in the installation does not need to be configured. This allows unconfigured ConfigIni.php files
// to exist in multiple experimental codebases on a single server and avoids accidental distribution of passwords etc.
$parentIni = dirname(__FILE__)."/../heuristConfigIni.php";

$defaultFaimsModulesPath = ""; // FAIMS only: the location where FAIMS module files will be written

//action passwords
$passwordForReservedChanges = "SpeakEasy1935";

// parent directory configuration file is optional, hence include not require
if (is_file($parentIni)){
    include_once($parentIni);
}
?>
