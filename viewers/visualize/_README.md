## **Diagram Visualisation**

Directory: [```/viewers/visualize```](.)

Overview: Display record data, or the database structure, in a diagramatic format

This feature contains two types the visualisers: [Record Data](./springDiagram.php) and [Database Structure](./databaseSummary.php)

[Record Data](./springDiagram.php): This version is used by CMS webpages as a widget, this diagram is used for displaying the connections between records, specifically the relationship markers and record pointer values. The widget's initialisation can be found within [connections.js](../../hclient/widgets/viewers/connections.js).

[Database Structure](./databaseSummary.php): This diagram is used within the backend to show how all record types (sometimes referred to as entities) are connected with each other. This one can be accessed only by Database Administrators via Design > Modify > Visualise.<br>From here users can specify which record types they wish to display and further expand by clicking on the field labels to bring in the connected record types.

## Requirements
**Internal**:
 - [visualise.js](./visualize.js) - Core Visualiser widget
 - [settings.js](./settings.js) - Visualiser Settings JavaScript class
 - [overlay.js](./overlay.js) - Visualiser Overlay JavaScript class
 - [drag.js](./drag.js) - Diagram Dragging JavaScript class
 - [selection.js](./selection.js) - Diagram Selection JavaScript class
 - [exporter.js](./exporter.js) - Exporting JavaScript class
 - [selectLinkField.js](./selectLinkField.js) - For creating new connections within the diagram on the fly
 - [connections.js](../../hclient/widgets/viewers/connections.js) - Necessary for a complete record data diagram (_Optional_)

**External**:
 - [jQuery](https://jquery.com/) - jQuery requirement
 - [jQuery UI](https://jqueryui.com/) - Dialogs and Widget Factory
 - [D3 Library](https://d3js.org/) - Visualising library and backbone for this widget
 - [Colpicker](https://github.com/evoluteur/colorpicker) - For colour selections

## Notes
**Classes**: All related visualiser classes start with the prefix ```Visualise```, except linkFields
 - [Settings](./settings.js): Any customisable user settings (e.g. line width, node placements, font size, etc...) saving them to either localStorage or User Preferences (if logged in)
 - [Overlay](./overlay.js): Setups and adds all diagram nodes, includes handling rollover, click and drag events for each node. Also prepares all link data.
 - [Drag](./drag.js): Contains all diagram dragging behaviour, for both dragging new node links and the selection box
 - [Selection](./selection.js): Handles selecting events for nodes including: highlighting nodes that have been selected within the diagram or selected in a related widget (e.g. result list)
 - [Exporter](./exporter.js): For exporting the diagram data, or a URL to embed the diagram in an external webpage
 - [linkFields](./selectLinkField.js): Used to created new links between nodes, also allowing users to specify what type of link (record pointer or relationship marker)

**Widgets**: Both widgets are made via the Widget Factory for jQuery UI and contained within the Heurist namespace ```$.heurist```
 - [connections](../../hclient/widgets/viewers/connections.js) - The driver widget for the [Record Data](./springDiagram.php) diagram, via an iframe element
 - [visualise](./visualize.js) - The visualiser's general driving widget, initialising and handling the various JavaScript classes

Updated: 11 June 2025

---

