
# Directory: /hclient/widgets/search

## Overview

Files in this directory manage searching/filtering of the database, as well as the management of the navigation panel containing saved searches, and functions to build search (filter) strings and Rules.

The structure for treeview of saved filters is stored in the users/groups table. The navigation panel is built up using all the `svstree` structures that apply to the current user.

## Key files

-   **ruleBuilder.js**: jQuery UI widget for defining rules to search for related records. It allows users to build complex, multi-level queries by specifying relationships and filters between record types.
-   **ruleBuilderDialog.js**: Provides the JavaScript logic for the RuleSet builder dialog. It handles initializing the dialog, loading existing rules, adding new rule levels, saving rules, and interacting with the `ruleBuilder` widget.
-   **ruleBuilderDialog.php**: Provides the HTML structure and client-side script initialization for the RuleSet Builder Dialog.
-   **search.js**: jQuery UI widget for the main search interface in Heurist. It handles search input, domain selection (all, bookmark, etc.), and interaction with other search tools like entity filters and search builders.
-   **searchBuilder.html**: HTML template for the advanced search builder widget. Defines the UI structure for creating complex search queries.
-   **searchBuilder.js**: jQuery UI widget that provides a wizard-like interface for constructing advanced search queries. Users can define criteria based on record types, fields, relationships, and sorting.
-   **searchBuilderItem.js**: jQuery UI widget representing a single item (criterion) in the `searchBuilder`. It allows users to define a search condition based on a field, operator, and value, handling various field types.
-   **searchBuilderSort.js**: jQuery UI widget representing a single sort criterion item in the `searchBuilder`. It allows users to select a field to sort by and the sort direction.
-   **searchByEntity.js**: jQuery UI widget for filtering search results by entity (record type). It can display favorite record types as buttons and/or a usage-based dropdown.
-   **searchInput.js**: jQuery UI widget providing a simplified search input form. It can include a preliminary filter and supports various search domains, often used in specific contexts like the CMS.
-   **search_faceted.js**: jQuery UI widget for applying faceted search. It allows users to refine search results by selecting values from various facets, handling query creation, and facet display.
-   **search_faceted_wiz.html**: HTML template for the faceted search configuration wizard. Defines the UI structure for setting up faceted search.
-   **search_faceted_wiz.js**: jQuery UI widget that provides a step-by-step wizard for users to create and configure faceted search interfaces.
-   **svsEdit.html**: HTML template for editing saved searches/visualizations. Defines the UI structure for modifying saved items.
-   **svsEdit.js**: Factory function (`HSvsEdit`) to create an object for managing the dialog and logic for creating, editing, and saving saved searches, rulesets, and faceted search configurations.
-   **svs_list.js**: jQuery UI widget that manages and displays lists of saved searches, faceted searches, and tag searches, typically in the navigation panel.
```
