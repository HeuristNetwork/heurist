# Directory: /admin/setup/dbcreate

## Overview

This directory holds SQL scripts and definition files for creating the fundamental structure, functions, procedures, triggers, and core definitions of a new Heurist database.

## Subfolders

- `icons/`: Contains icons potentially used during database creation or for default record types.
- `smarty-templates/`: This directory contains Smarty template files (.gpl, .tpl) used for generating reports, including basic examples and specific formats like Harvard Bibliography.

## Key files

- `addFunctions.sql`: MySQL stored functions for Heurist, to replace old UDFs.
- `addProceduresTriggers.sql`: Contains the stored procedures and triggers for Heurist databases.
- `addReferentialConstraints.sql`: Script for adding relational constraints in Heurist Vsn 3 Build.
- `blankDBStructure.sql`: Defines the basic table structure for a new database; requires other SQL scripts in this directory to add functions, procedures, and constraints.
- `coreDefinitions.txt`: Contains core Heurist definitions, typically used during database creation.
- `sqlCreateRecLinks.sql`: [Please provide a brief description of this file's purpose.]
