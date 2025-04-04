Directory:    /hserv/records/import

Overview: This directory contains classes to work with import from csv, json, hml(xml) and kml files

importParser.php - parsing source file and save data into temp table
importSession.php - working with session table sysImportFiles
importAction.php - working with import data in temporary table: matching, assign idx, validation, create records

ImportHeurist.php - inter-database import via json or hml format (see user interface importRecords)

All classes above are called from controller: importController.php

Updated: 26th October 2023

-------------------------------------------------------------------------------------------------------------------------------------

