Directory: /hclient/widgets/HBase

Overview

Base/template widgets for creating new widgets.

Notes


HBaseWidget.js – Base widget for all UI widgets

This widget handles the initialization process:

    Loads resources (CSS, HTML, localization) from options.resourcePath or options.htmlContent.
    Calls _initControls after loading content, then triggers options.onInitFinished.

Use this widget if you wish to implement UI components with a standardized initialization process.

---

HBaseView.js – A container widget for displaying a popup or inline dialog

Define options.viewMode to specify the view mode:

    popup – Uses a jQuery dialog.
    offcanvas-{position} – Uses Bootstrap Offcanvas.
    modal-{breakpoint} – Uses a Bootstrap Modal.
    inline – Displays content inline.

Override the _getActionButtons method to define buttons in the bottom button pane.

This widget serves as the parent for HRecordView and HRecordAction.

---

HBaseList.js – Base widget for handling a set of records (HResultSet)

    Allows assigning an initial record set (options.recordSet) or performing an initial search (options.searchInitial).
    If options.searchDomain is defined, it listens for ON_REC_SEARCHSTART and ON_REC_SEARCH_FINISH events, assigning the record set from the search result.
    Listens for ON_REC_SELECT and updates the selection using the setSelection method.
    Key methods to implement in descendant widgets:
        renderContent – Renders the records.
        renderMessage – Renders placeholder messages (initial, empty result set, or error messages).

This widget serves as the parent for HRecordList, HRecordNetwork, HRecordMap, and others.

    
    Widgets acting on a single record (e.g. record edit, record view); 
    Widgets acting on the current Resultset or a selection (filter selector, results view, tabular view, map view, custom report, network diagram, storymap, various export functions, merge records, recode functions);
    Functions acting independently (pe. Filter and facet builders, Preferences, CSV import, structure export, new database)

Updated:     07 March 2025

----------------------------------------------------------------------------------------------------------------

/**
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @version     7.0
*/

