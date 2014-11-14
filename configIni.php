<?php

/**
* configIni.php: configuration file for this Heurist instance.
*
* Note: This file is overriden by heuristConfigIni.php in the parent directory, if present, allowing a single config file for all instances
*       Program version number, however, is always specified by this file and should not be changed
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @author      Tom Murtagh, Kim Jackson, Stephen White
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
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
    values set in ../heuristConfigIni.php for that instance of Heurist.
    All other instances will use the value set in ../heuristConfigIni.php

    WE THEREFORE RECOMMEND NOT CHANGING ANYTHING IN THIS FILE

    Values in this file should only be set for testing purposes or
    by an experienced sysadmin for very unusual server setups

    **************************************************************
*/

/* --------------------------------------------------------------------------------------------

     Setting up the server to support multiple code versions
     -------------------------------------------------------

     Move the file parentDirectory_heuristConfigIni.php to the parent directory of the codebase
     rename to heuristConfigIni.php and enter MySQL passwords, paths and other config settings there.
     This allows Heurist instances to exist as multiple codebases on a single server and avoids
     the need for duplication of information or the accidental distribution of passwords etc.
     if one of these codebases is used as a code source.

 --------------------------------------------------------------------------------------------
*/

// *** DO NOT CHANGE VERSION NUMBER ***

$version = "3.3.0"; // sets current program version number, determined by Heurist development lead
                    // 3.2.0 alpha 8th July 2014, beta 21st July 2014
                    // 3.3.0 beta 11th Nov 2014

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
$defaultRootFileUploadPath ="";
//$defaultRootFileUploadURL = "";
$sysAdminEmail = "";
$infoEmail = "";
$bugEmail = "";
$websiteThumbnailService = "";
$websiteThumbnailUsername = "";
$websiteThumbnailPassword = "";
// $websiteThumbnailXsize = 500;
// $websiteThumbnailYsize = 300;


// system default file - if a heuristConfigIni.php file exists in the parent directory of the installation,
// the ConfigIni.php in the installation need not be configured. This allows unconfigured ConfigIni.php files to exist
// in multiple experimental codebases on a single server and avoids accidental distribution of passwords etc.
$parentIni = dirname(__FILE__)."/../heuristConfigIni.php";

if (is_file($parentIni)){
	include_once($parentIni);
}
?>
