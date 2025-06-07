# HFilter Widget

## Overview

The `HFilter` widget serves as a container and controller for displaying filter dialog, primarily for executing saved searches (also known as "Saved Filters") and managing faceted search interfaces within the Heurist system. It allows users to apply predefined search criteria or interact with faceted navigation to refine record sets.

The widget inherits from `HBaseView`, providing a foundational structure for a view component.

## Key Features

-   **Load Saved Searches**: Can load and execute a saved search by its unique identifier (`svsID`). The widget caches loaded search parameters to optimize subsequent requests.
-   **Handles Different Search Types**:
    -   **Faceted Searches**: If the loaded saved search is a faceted search (`params.type == 3`), `HFilter` initializes and displays the `search_faceted` widget (another Heurist component) to render the faceted navigation interface. It passes relevant parameters like the query name, search parameters, and search domain.
    -   **Standard Query Searches**: For non-faceted saved searches, `HFilter` parses the query and executes it using `HAPI4.RecordSearch.doSearch()`. It can include additional parameters like detail level and search domain.
    -   **RuleSets**: If a saved search contains rules (`params.rules`) but no primary query string (`params.q`), it's treated as a "RuleSet." These rules can be applied to an existing search result set within a specified `searchDomain`.
-   **Search Domain Interaction**: Operates within a `searchDomain`, which defines the context or scope for the searches being performed (e.g., which collection of records to search within).
-   **Dynamic Content Display**: Can display the faceted search interface inline or as a separate view/dialog.
-   **Error Handling**: Provides feedback to the user if a saved search is not found, corrupted, or if a RuleSet is applied without an initial search result.

## How to Initialize

The `HFilter` widget is typically initialized on a `div` element that will act as the container for the filter interface.

```javascript
$('#myFilterContainer').HFilter({
    // Heurist API instance (implicitly available via window.hWin.HAPI4)
    hapi: heuristApiInstance, // Though not explicitly in options, HAPI is used via window.hWin

    svsID: 123, // The ID of the Saved Filter to load and display/execute
    searchDomain: 'myRecordsDomain', // The search domain this filter operates on

    // Optional: viewMode can be 'inline' or other modes handled by HBaseView
    // viewMode: 'inline'
});
```

To trigger a search for a different Saved Filter after initialization:
```javascript
$('#myFilterContainer').HFilter('doSearchByID', 456);
```

## Options

The following options are available for configuring the `HFilter` widget:

| Option         | Type     | Default | Description                                                                                                |
| -------------- | -------- | ------- | ---------------------------------------------------------------------------------------------------------- |
| `svsID`        | `number` | `0`     | The ID of the Saved Filter (usrSavedSearches record) to load and execute when the widget initializes.      |
| `searchDomain` | `string` | `null`  | The identifier for the search domain/context in which the filter will operate. This is crucial for targeting the search correctly. |


## Inheritance

-   `$.heurist.HBaseView`: `HFilter` extends `HBaseView`, inheriting its basic view management capabilities.

## Key Files in this Directory

-   **`HFilter.js`**: The core JavaScript file implementing the `HFilter` widget.
