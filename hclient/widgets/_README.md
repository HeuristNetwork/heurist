# Directory: /hclient/widgets

## Overview

This directory contains Heurist widgets, which are client-side components used to build the standard user interface of Heurist. They are also utilized in websites created with the Heurist Content Management System (CMS). These widgets provide various functionalities, from basic actions and configuration dialogues to complex data visualization and management tools.

Templates and instructions for creating new widgets can be found in `_widget_template.js` and related base class files.

## Key files

-   **_widget_template.js**: A template file for defining new jQuery UI widget. It provides a basic structure for widget creation, including options, constructor, initialization, and destroy methods.
-   **baseAction.js**: Provides a base jQuery UI widget (`heurist.baseAction`) for popup dialogues and actions that operate on a scope of records. It handles common dialog functionalities, HTML content loading, and help content integration.
-   **baseConfig.js**: Provides a base jQuery UI widget (`heurist.baseConfig`) for configuration widgets, particularly for lookup services and repositories. It manages loading and saving service configurations.

## Subdirectories

The `widgets` directory is further organized into subdirectories, each typically focusing on a specific area of functionality:

-   **admin/**: Widgets related to administrative tasks, such as managing server configurations and user email forms.
-   **cms/**: Widgets for the Heurist Content Management System, including tools for editing CMS pages, managing site structure, and displaying CMS statistics.
-   **cpanel/**: Widgets for the main Heurist control panel, including navigation menus, database overview, and version checking.
-   **database/**: Widgets for database-level operations like creating, cloning, deleting, restoring, and verifying databases.
-   **editing/**: Widgets related to various editing functionalities, such as editing temporal objects, themes, translations, and using code editors like CodeMirror.
-   **entity/**: A large collection of widgets for managing and searching various Heurist entities (both system-defined and user-defined), including record types, fields, terms, users, groups, and records themselves.
-   **lookup/**: Widgets for integrating with external lookup services and authorities (e.g., Geonames, Wikidata, BnF) to enrich Heurist data.
-   **profile/**: Widgets for managing user profiles, including login, registration, password changes, and user preferences.
-   **record/**: Widgets focused on actions related to individual or sets of records, such as adding, linking, exporting, tagging, rating, and finding duplicates.
-   **report/**: Widgets for creating, editing, and viewing Smarty-based reports.
-   **search/**: Widgets for search functionalities, including rule builders, faceted search, and saved search management.
-   **viewers/**: Widgets for displaying data in various formats, such as 3D models, story maps, network connections, media galleries, and specialized record list views.

Each of these subdirectories typically contains its own `_README.md` file detailing its specific contents.

