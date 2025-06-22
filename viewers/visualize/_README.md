# Directory: /viewers/visualize

## Overview
Provides the D3.js-based network visualization (spring diagram) for Heurist. It allows users to view relationships between records in a search result set or the overall database structure as a network graph. Includes functionalities for interaction, styling, settings management, and data export (e.g., to GEXF for Gephi).

## Subfolders
- `assets/`: Contains image assets used in the visualization.

## Key files
- `databaseSummary.php`: Displays a table of record types and counts, and an SVG entity connections schema.
- `drag.js`: Handles node dragging functionality within the D3 visualization.
- `gephi.js`: Provides functions to export the current visualization to GEXF format for Gephi.
- `index.php`: Redirects to the main spring diagram visualization page (`springDiagram.php`).
- `overlay.js`: Manages informational overlays for nodes and relationships in the graph.
- `selectLinkField.js`: JavaScript logic for selecting or creating link field types when editing database structure.
- `selectLinkField.php`: UI for the dialog to select or create link field types.
- `selection.js`: Handles node selection mechanics (single, multiple, lasso) in the visualization.
- `settings.js`: Manages user-configurable settings for the visualization's appearance and behavior.
- `springDiagram.php`: Main entry point that renders a search result set as a network diagram.
- `visualize.css`: Contains CSS styles for the network diagram visualization.
- `visualize.js`: Core jQuery plugin that sets up and manages the D3.js force-directed graph.
- `visualize.php`: Provides the HTML structure and toolbar for the network diagram interface.
