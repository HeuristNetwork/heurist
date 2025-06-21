<!--
_README.md - Provides an overview of the /startup directory, detailing the setup sequence and the purpose of each file within it.
@fileOverview This file outlines the functionality of the /startup directory, which includes scripts for user registration, database creation, and initial setup guides for the Heurist application.
@package Heurist academic knowledge management system
@subpackage /startup
@link https://HeuristNetwork.org
@copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
@license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
@author Ian Johnson ian.johnson.heurist@gmail.com
@since 12 May 2024
-->
Directory:    /startup

Overview:  Setup sequence - register new user, create new database, getting started

Notes:

index.php - main script 
            a) to show Register new user/SetUp new database wizard (from https://heuristserver.tld/ )
            b) to show list of all databases (in case database not found or db parameter is missed https://heuristserver.tld/heurist/?db= )
            
listDatabases.php - returns json array with all databases on server 
                    or produces page with list of all databases (not used)

gettingStarted.html   - html snippets for inroductory guides on startup and as hints from main menu
userRegistration.html - html snippets - content of new user registration form

Updated:     12 May 2024

---------------------------------------------------------------------
