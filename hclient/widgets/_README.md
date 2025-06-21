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

# Using Heurist vsn 7 Widgets in Websites 

Artem Osmakov 7 June 2025

Note: the person setting up a website is referred to as the Publisher.  

---

## **HRecordList \- widget for presentation of the set of records.**

The use of HRecordList is discussed under the following headings:

### **Content**

    Initial content can be defined via:

* A Heurist query (as initial filter to be applied at start)   
* Programmatically (via method setRecordSet)   
* Smarty template output   
* Html or csv content of widget element. 

  For smarty and html cases, html elements which are considered as record cards/table rows must have an attribute  data-heurist-rec="nnn"  where nnn is the record ID.


  For csv input, the value in the column H-ID is considered as the Heurist record ID.

### **Appearance/Presentation**

The list can be split into pages (via a parameter in the widget properties). In any case, record cards/rows are rendered incrementally (only in visible viewport), so pagination is useful for quick navigation or for very large recordsets (\> 10K entries).

The publisher of the recordset can define two kinds of messages: for the initial state and where there are no data (empty search result).

Each record card/row can be rendered with:

* Built-in renderer (function within widget) corresponding with the standard views in previous versions of Heurist  
* One of four sample built-in smarty templates   
* The publisher’s smarty template.    
* Programmatically it can be defined as a function in options.rendererCard or it can overwrite *method \_renderRecord* if you use HRecordView as a template for a new widget.

When creating a smarty template for this purpose, each record card or row (html element) must be specified with attribute  data-heurist-rec="nnn".

Record cards can be presented in four view modes: grid, horizontal, vertical list or as a table. For table mode, the publisher’s smarty template should generate \<tr\>\<td\> for records. Otherwise the appearance will look like a vertical list.

### **Connect**

If a search group property is specified, HRecordList accepts ON\_REC\_SEARCHSTART, ON\_REC\_SEARCH\_FINISH, ON\_REC\_SELECT and triggers ON\_REC\_SELECT events. So it can accept search result events from HFilter or selection events from other HRecordSet widgets.

The widget has a built-in HRecordView widget. It handles view action (on record card click, or action link click). See HRecordView for details.

Record card/rows can have html elements: links or buttons (to be specified in smarty template) that can trigger an arbitrary or record-specific action. For this purpose they must have an attribute data-heurist-action.  

For example  \<a href=”\#” data-heurist-action=”record-edit”\>Edit\</a\> will open the record edit dialog.

### ---

## **HMenu \- widget for page navigation, actions and saved filters links**

### **Content**

Content of the menu widget can be defined via the widget property form in the CMS editor which provinces a treeview that consists of menu and submenu items. Each menu item refers to a “CMS page” record, a Saved filter (usrSavedSearches) or an Action (sysDashboard). The Submenu (folder) structure implies no difference in the pages, it exists solely to create a hierarchy within the menu. Technically the CMS page and saved filter can be defined via an action.

An alternative (advanced) way is to define an html snippet with buttons and/or links with one of  the attributes: *data-heurist-action*, *data-heurist-pageid* or *data-heurist-search*.  

### **Appearance/Presentation**

If the content is defined via json or html, snippet elements have attributes that define their role (eg. *data-heurist-role*\="menu-dropdown"). It is possible to define the appearance of the menu via the widget property form. Menus can be vertical, horizontal or treeview. They can be bootstrap or jquery (tbd). They can be collapsable.

### **Connect**

On menu selection, the widget executes the specified action, loads the web page or starts the saved filter. It also triggers the ON\_ACTION event. HMenu has a built-in HFilter widget. It handles Saved Filters. 

If Saved Filter has entries to be defined by the website visitor (faceted search), HMenu opens the Filter form. The appearance of this form is similar to HRecordView for HRecordList. It can be inline (over menu), in a floating popup, in a modal dialog, in an offcanvas (side slide panel).  If the publisher prefers to specify their own HForm, it can be connected to HMenu via the search group.

### **HFilter \- widget for search form and execution of filters**

HFilter generates a search form to define a search query. At the moment it uses the search\_faceted widget.

As a descendant of HBaseView the search form can be presented in the provided container (inline div), float or modal popup or offcanvas(side slide panel).

This widget is integrated with HMenu and its properties can be defined along with its property editor. For standalone mode, the saved filter ID can be obtained from HMenu (via search group link ON\_ACTION event) or defined as a widget property.

### **HRecordView \- widget to render info for a particular Heurist record**

Content can be:

* The built-in renderer (renderRecordData.php)  
* A built-in smarty template  
* A publisher’s smarty template.    
* Programmatically it can be defined as a function in options.customRecordRender or overwrite the method renderContent if you use HRecordView as a template for a new widget.

As a descendant of HBaseView it can be presented in the provided container (inline div), float or modal popup or offcanvas(side slide panel).

This widget is integrated with HRecordList and its properties can be defined along with the HRecordList property editor. As a standalone widget the ID of the record (to be rendered) can be obtained via the search group ON\_SELECT event or defined as a widget property.

