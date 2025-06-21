# Directory: /viewers/map

## Overview
Contains the core functionality for the Heurist mapping interface, built using Leaflet. It handles map display, layer management (including map documents and various data sources), drawing tools, timeline integration, and publishing capabilities.

## Key files
- `index.php`: Redirects to map.php, preserving query string. This file handles redirection from the base map directory to the main map script (map.php).
- `map.php`: Main mapping page for Heurist. Serves as the main entry point for the mapping interface.
- `mapDocument.js`: Manages map documents and their associated layers and data sources.
- `mapDraw.js`: Provides utility functions for converting coordinate data to WKT format.
- `mapDraw.php`: Leaflet-based map digitizing tool for Heurist, for drawing and editing geographic shapes.
- `mapLayer.js`: Interface between Heurist layer/datasource and Leaflet layers.
- `mapManager.js`: Manages layers, map documents (projects), and the map legend.
- `mapPreview.php`: Allows previewing selected layers as a map space and saving them.
- `mapPublish.js`: Defines Leaflet controls for publishing a map and accessing help.
- `mapping.js`: Core mapping widget for Heurist (jQuery UI widget).
- `timeline.js`: jQuery UI widget for managing and displaying a Vis.js timeline integrated with the map.
