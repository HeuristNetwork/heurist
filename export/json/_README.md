# Directory: export/json

## Overview
This directory contains scripts for exporting Heurist database content and structures in JSON format.

## Key files
*   `recordTemplate.php`: Exports record structure templates in JSON format. It generates a JSON template for specified record types. If no record types are specified, it attempts to export actual record data by including `hserv/controller/record_output.php`. The script outputs a significant help text block before the JSON data.
