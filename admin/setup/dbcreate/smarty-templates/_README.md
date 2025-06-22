# Directory: /admin/setup/dbcreate/smarty-templates

## Overview

This directory contains Smarty template files (.gpl, .tpl) used for generating reports, including basic examples and specific formats like Harvard Bibliography. Gpl files are converted to local version (tpl) and copied to database storage/smarty templates subfolder.

## Key files

- `Basic (initial record types).tpl`: This is a simple Smarty report template which you can edit into something more sophisticated. It should give basic output for any database, as it uses the standard record types which are part of all databases.
- `Basic (initial record types).gpl`: Global version of Basic (initial record types).tpl. It uses concept codes instead of local codes for fields.
- `Harvard Bibliography.gpl`: Harvard Bibliography template
