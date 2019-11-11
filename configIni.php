<?php

/**
* configIni.php: configuration file for this Heurist instance.
*
* Note: This file is overriden by heuristConfigIni.php in the parent directory, if present, allowing a single config file for all instances
*       Program version number, however, is always specified by this file and should not be changed
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     5
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

$version = "5.1.9"; // sets current program version number, determined by Heurist development lead

// h5.1.9 11 Nov 2019 Improved automatic date correction, compatibility issues with MySQL 8, big rationalisation of colour settings across all code, finalise HML import and template export, location inheritance from Place in Leaflet
// h5.1.8 : 23 Oct 2019 HML(XML) import from Heurist DB or external including all relationships, HML template generator, Custom JS in website home pages, fixes to chld record conversion & Leaflet mapping, smarty report for info popups, cosmetics and imporved error messages. 
// h5.1.7 : 6 Sep 2019  Major work on website designer, add background maps to digitiser, entity buttons above search field, small improvements to CSV import and Leaflet maps, custom report for View record popup
// h5.1.6 : 12 July 2019 Fixes on Leaflet, fixes for timeouts, PDF uploads, selectable set of entity buttons above search field, Maptiler MBTilefile file support, CMS website first draft
// h5.1.5 : 20 June 2019 Mapping with Leaflet in place of GMap, internal documentation of JSon export files, import hierarchichal terms, improve tabs in structure browsers, hosting logo, relationship vocabs usable for term fields, better error reporting particularly Zotero import 
// h5.1.4 : 8 May 2019 Many small cosmetic/message improvements and bug fixes, added SKOS, FOAF, DOAP, BIO, BIBO, RDF, DCMI-TYPES/TERMS vocabularies.
// h5.1.3 15 April 2019 Fixes to verification function and file upload, complete RESTful API and Swagger doco, minor cosmetic and workflow fixes.
// h5.1.2 3 Apr 2019 case sensitive/exact multilingual search, DB properties file folder manager, multi file upload duplicates directory structure, import dot-separated hierarchical terms, CSV data entry template generation,  auto image display from linked multimedia, HML import first draft, Restful API WIP 
// h5.1.1 26 Feb 2019 Render simple html & hyperlinsk in record titles, better handling of map zooms, add Google analytics detail, imprt child records, better error reporting, drag and drop reordering of values in edit form, instant preview of search in facet search builder, clear marker of non-public records, import multimedia with dirctory structure, output Gephi node types, 
// h5.0.5 Feb 2019 Drag/drop & paste images, expose semantic reference fields, expose parent record fields in facet search tree, improved startup help, improved map view info popup, clustering and zooming, KML and map tile handling, mapdocument and views parameters for embedded searches, lots of small fixes and cosmetics
// h5.0.4 Jan 2019 Minor fixes, CSV output, dashboard creation, icon directories etc.
// 5.0.3 11 Dec 2018 WYSIWYG edito for memos, extract text from PDFs, improve verification and child record conversion, fix thumbnail and bookmarklet services, manually sorted result sets, improved ViewRecord, entity types with counts shortcut, filtering menus by user level, fixes to CSV export, finalise Beyond 1914 and Adelaide websites
// 5.0.2 10 Nov 2018 Really working h5. Dashboard, streamlined field definition, read-only groups, revised import templates, conversion to child records, U of Adelaide website from Expert nation, finalise Beyond1914.
// 5.0.1 25 Jul 18  1)php7 conformity 2)got rid h3 client libs 3)got rid mysql__ functions
// 4.5.13 13 Jul 18 Handle multi valus for pointer field in CSV import, bug fixing/cosmetics on mapping, reinstate time filter
// 4.5.12 27 Jun 18 Implicit location of entities on Map view based on connected Place records, minor bug fixing
// 4.5.11 6 Jun 18 Improve integrity when structure is edited, improve handling of child pointers in title generation, various bug fixes and cosmetics
// 4.5.10 14 Apr 18 Rewritten settings functions based on Artem's entity model, improved display of relationships in edit mode, UTM conversion on import/digitiser, clone template databases, correct facet search counts, fixes to child records and titles, permissions fixes, lots of small bug fixing
// 4.5.9 18 Mar 18 Improve CSV import incl. URLs for file fields, URL and tags export in CSV, UTM import, display reverse relationships inline, clone template databases incl. data, lots of bug fixing and cosmetics
// 4.5.8 16 Feb 18 HTML5 media player, title masks for child records, improve timeline layout, fixes to editing, major imrovements to CSV import, bulk convert records to children, lots of cosmetics
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

$passwordForDatabaseCreation=""; // normally blank = any logged in user can create
$passwordForDatabaseDeletion=""; // if less than 15 characters no one can delete db except db owner
                                 //  if set (>14 characters) db admin can delete with password challenge

$passwordForReservedChanges=""; // if blank, no-one can modify reserved fields, otherwise challenge
$passwordForServerFunctions=""; // if blank, no-one can run server analysis functions - risk of overload - otherwise challenge

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
