# Directory: export/xml

## Overview
This directory contains scripts for exporting Heurist database content and structures in XML-based formats, specifically HML (Heurist Markup Language) and KML (Keyhole Markup Language).

## Key files
*   `flathml.php`: Exports Heurist records to a "flattened" HML (XML) format, where records are referenced rather than redundantly generated. Supports command-line execution, various output options (stubs, reverse pointers), and a special multi-file mode for HuNI.
*   `kml.php`: Exports Heurist record data to KML format. It can output existing KML files linked to records or generate KML placemarks from WKT geometry data within records. It also supports KML NetworkLinks.
