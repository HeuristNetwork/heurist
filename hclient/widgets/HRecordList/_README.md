# HRecordList Widget

## Overview

The `HRecordList` widget is a Heurist component responsible for displaying a list of records from a given `HRecordSet`. It provides a flexible way to render records in various layouts and supports features like pagination, selection, and custom rendering.

This widget inherits functionality from `HBaseWidget` and `HBaseList`, building upon them to provide record-specific listing capabilities.

## Functionality and Purpose

- **Display Records:** Renders records from an `HRecordSet`, which can be dynamically updated.
- **Pagination:** Supports pagination for large record sets, allowing users to navigate through pages of records. If `pageSize` is 0, all records are shown, and pagination is disabled (up to a maximum of 1000 records).
- **View Modes:** Offers multiple view modes to display records:
    - `grid`: Displays records in a card-like grid layout.
    - `list`: Displays records in a vertical list.
    - `row`: Displays records in a horizontal list, typically for a more compact view.
    - `table`: Displays records in a tabular format.
- **Record Selection:** Allows users to select records. The selection behavior can be configured (e.g., single selection).
- **Record Viewing:** Can be configured to open a detailed view of a record when clicked. The view can be displayed in a popup, inline, modal, or other targets.
- **Customization:**
    - Supports custom renderers for record cards (`rendererCard`).
    - Allows the use of Smarty templates for record cards (`templateCard`) and detailed views (`templateView`).
- **Search Integration:** Can be linked to a search domain (`searchDomain`) and perform initial searches (`searchInitial`).
- **Placeholder Content:** Displays a message when no records match the filter criteria or when the list is empty.

## How to Use

The `HRecordList` widget is typically initialized as a jQuery UI widget.

```javascript
$('#myRecordListContainer').HRecordList({
    // Options
    hapi: heuristApiInstance, // Instance of Heurist API
    recordSet: myRecordSet,   // HRecordSet object
    viewMode: 'grid',
    pageSize: 20,
    // ... other options
});
```

## Options

The widget offers several options to customize its behavior and appearance. These are passed during initialization:

| Option                  | Type                        | Default                                                                      | Description                                                                                                                               |
| ----------------------- | --------------------------- | ---------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `resourcePath`          | `string`                    | `'hclient/widgets/HRecordList/HRecordList'`                                  | Relative path and filename to resources (HTML, CSS, localization).                                                                        |
| `hapi`                  | `object`                    | `null`                                                                       | (Inherited) Heurist API instance.                                                                                                         |
| `htmlContent`           | `string`                    | `null`                                                                       | (Inherited) Custom HTML content for the widget.                                                                                           |
| `uiLibrary`             | `string`                    | `null`                                                                       | (Inherited) UI library to use (e.g., 'bootstrap', 'jqueryui').                                                                            |
| `onInitFinished`        | `function`                  | `null`                                                                       | (Inherited) Callback function executed after widget initialization is complete.                                                             |
| `entityType`            | `string`                    | `'rec'`                                                                      | (Inherited) The type of entity being listed (default is 'rec' for records).                                                               |
| `recordSet`             | `HRecordSet`                | `null`                                                                       | Initial `HRecordSet` to display.                                                                                                          |
| `searchDomain`          | `string`                    | `null`                                                                       | Reference to an entity `HSearchDomains` for search integration.                                                                           |
| `searchInitial`         | `string`                    | `null`                                                                       | Initial search query to execute.                                                                                                          |
| `showCounter`           | `boolean`                   | `true`                                                                       | If `true`, displays the total count of records in the list.                                                                               |
| `selectFirstRecord`     | `boolean`                   | `false`                                                                      | If `true`, automatically selects the first record in the list upon loading.                                                               |
| `pageSize`              | `number`                    | `0`                                                                          | Number of records to display per page. If `0`, all records are shown (max 1000), and pagination is disabled.                              |
| `supportCollection`     | `boolean`                   | `false`                                                                      | (TBD) Placeholder for collection support functionality.                                                                                   |
| `showMediaViewer`       | `boolean`                   | `false`                                                                      | (TBD) If `true`, shows a gallery on thumbnail click (using `data-heurist-media` attribute).                                                 |
| `selectAction`          | `string`                    | `'select'`                                                                   | Action to perform on record item click: `'none'`, `'select'`, or `'view'`.                                                                 |
| `selectMode`            | `string`                    | `'single'`                                                                   | Selection mode: `'none'`, `'single'`, `'multi'`. (Multi-select is TBD).                                                                  |
| `viewMode`              | `string`                    | `'grid'`                                                                     | Default view mode: `'grid'`, `'list'`, `'row'`, `'table'`.                                                                                |
| `viewRecordMode`        | `string`                    | `'popup'`                                                                    | How to display the detailed record view: `'none'`, `'inline'`, `'offcanvas-*'`, `'modal-*'`, `'popup'` (jQuery dialog), target ID, or `'event'`. |
| `editRecordMode`        | `string`                    | `'none'`                                                                     | (TBD) How to display the record editing interface.                                                                                        |
| `rendererCard`          | `function`                  | `null`                                                                       | Custom JavaScript function to render a record card. Overrides the default renderer.                                                       |
| `templateCard`          | `string`                    | `null`                                                                       | Path to a Smarty template for rendering a record card.                                                                                    |
| `templateView`          | `string`                    | `null`                                                                       | Path to a Smarty template for the detailed record view. If not defined, it uses the entity's default Smarty report.                     |
| `placeholderEmptyBlank` | `boolean`                   | `false`                                                                      | If `true`, displays nothing when the list is empty.                                                                                       |
| `placeholderEmpty`      | `string`                    | `null`                                                                       | Custom message to display when the list is empty. Overrides `placeholderEmptyDef`.                                                        |
| `placeholderEmptyDef`   | `string`                    | `'No entries match the filter criteria (entries may exist but may not have been made visible to the public or to your user profile)'` | Default message when the list is empty.                                                                                                   |

## View Modes

The `HRecordList` supports different ways to visualize the records through the `viewMode` option:

-   **`grid`**: (Default) Displays records as cards in a responsive grid layout. The number of columns may adjust based on screen size.
-   **`list`**: Renders records in a single vertical column, with each record taking the full width.
-   **`row`**: Displays records in a single horizontal row, allowing horizontal scrolling if the content exceeds the container width. This is suitable for compact displays.
-   **`table`**: Presents records in a traditional table format, with columns for different record fields.

The widget dynamically adjusts its internal structure and styling based on the selected `viewMode`.

## Related Files

This directory (`/hclient/widgets/HRecordList`) contains the following key files related to the `HRecordList` widget:

-   **`HRecordList.js`**: The core JavaScript file implementing the `HRecordList` widget and its logic.
-   **`HRecordList.css`**: CSS styles specifically for the `HRecordList` widget, ensuring proper layout and appearance for different view modes and components.
-   **`HRecordList.html`**: An HTML snippet that defines the basic structure (layout and toolbar) for the `HRecordList` widget.
-   **`HRecordListOpts.js`**: JavaScript for the property editor form associated with `HRecordList`, allowing configuration of the widget's options, likely within a Heurist administrative interface.
-   **`HRecordListOpts.html`**: HTML snippet for the property editor form used by `HRecordListOpts.js`.
-   **`HRecordView.js`**: A separate widget responsible for displaying a detailed view of a single record, often used in conjunction with `HRecordList` when a user selects a record to view.
-   **`HRecordList*.tpl`**: A set of built-in sample Smarty templates that can be used for rendering record cards or rows within the `HRecordList`. These provide default layouts that can be customized or replaced.
-   **`HRecordView.tpl`**: A built-in sample Smarty template for the `HRecordView` widget, defining how a single record's details are presented.

These files work together to provide the complete functionality and presentation of the record listing and viewing features within the Heurist system.
