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

$version = "4.2.10"; // sets current program version number, determined by Heurist development lead

// Vsn 4.2.10 9 Aug 2016  New CSV importer largely functional
// Vsn 4.2.9 22 Jul 2016  First draft of new CSV importer
// Vsn 4.2.8 17 Jun 2016  Further small fixes 
// Vsn 4.2.7 14 May 2016  Further small fixes 
// Vsn 4.2.6 26 Apr 2016  Complete revisoon of core definitions, lots of small fixes to mapping and structue management
// Vsn 4.2.5 19 Mar 2016  Further fixes including serious problem with Win 10 (not saving subsidiary records)
// Vsn 4.2.4 19 Mar 2016  Further debugging, fix up database archive
// Vsn 4.2.3 8 Mar 2016  Further testing and fixes, fix up exemplar DB function
// Vsn 4.2.2 17 Feb 2016 Further testing and fixes, fix up new database functions
// Vsn 4.2.1 05 Feb 2016 Further testing and fixes
// Vsn 4.2.0 17 Jan 2016 Testing for beta release
// Vsn 4.1.4 17 Dec 2015 Restructure, move H3 code back to codebase level
// Vsn 4.1.3 16 Dec 2015 Distribution as ...alpha  16/12/15
// Vsn 4.1.2 15 Nov 2015
// Vsn 4.1.1 02 Nov 2015
// Vsn 4.1.0 15 sep 2015

// 3.2.0 alpha 8th July 2014, beta 21st July 2014
// 3.3.0 beta 11th Nov 2014
// 3.3.0 27th Nov 2014 / 3.4.0-beta
// 3.4.0 22nd Jan 2015

// *** DO NOT SET THESE UNLESS YOU KNOW WHAT YOU ARE DOING ***
//     they override the values set in ../heuristConfigIni.php
$dbHost = "";
$dbAdminUsername = "";
$dbAdminPassword = "";
$dbReadonlyUsername = "";
$dbReadonlyPassword = "";
//$serverName = "null";
$dbPrefix = "";
$httpProxy = "";
$httpProxyAuth = "";
$indexServerAddress="";
$indexServerPort="";
$passwordForDatabaseCreation="";
$passwordForDatabaseDeletion="";
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

// parent directory configuration file is optional, hence include not require
if (is_file($parentIni)){
    include_once($parentIni);
}
?>
