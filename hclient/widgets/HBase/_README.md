# HBase Widgets - Foundational UI Components

## Overview

The `hclient/widgets/HBase/` directory contains a set of foundational JavaScript widgets that serve as base classes for many UI components within the Heurist platform. These widgets provide common functionalities, establish consistent patterns for widget development, and reduce code duplication across more specialized widgets.

## `HBaseWidget.js`

`HBaseWidget` is the primary base class for almost all Heurist UI widgets. It establishes a standardized lifecycle and provides core utilities.

-   **Role**:
    -   Acts as the root of the widget inheritance chain.
    -   Provides a consistent initialization process.
    -   Manages common widget resources and options.

-   **Key Responsibilities & Features**:
    -   **Resource Loading**: Handles the loading of external HTML templates (`options.resourcePath`) and CSS files. If `options.htmlContent` is provided, it's used directly, bypassing the HTML file load.
    -   **Standardized Initialization Lifecycle**:
        -   `_create()`: Basic setup, initializes HAPI access, ensures unique element ID.
        -   `_init()`: Main initialization logic, loads CSS and HTML content.
        -   `_initControls()`: Intended to be overridden by derived widgets to initialize their specific UI elements and event listeners. This is called after HTML content is loaded.
        -   `onInitFinished`: A callback option (`options.onInitFinished`) that is triggered after `_initControls` completes, allowing for post-initialization logic.
    -   **Utility Access**:
        -   Provides easy access to Heurist utilities via `this.$H` (short for `window.hWin.HEURIST4.util`).
        -   Provides access to the Heurist API via `this.HAPI` (defaults to `window.hWin.HAPI4`).
        -   Offers a jQuery shortcut `this._$` for querying elements within the widget's main element (`this.element`).
    -   **Options Editor Integration**: Includes `openOptionsEditor()` and `onCloseOptionEditor()` methods to facilitate the integration of widget-specific option editors (often built using `HBaseOpts` or a derivative).

-   **Core Options**:
    -   `hapi`: An instance of the Heurist API. If not provided, it defaults to the global `window.hWin.HAPI4`.
    -   `resourcePath` (`string`): The base path (relative to Heurist root) for loading associated `.html` and `.css` files (e.g., `hclient/widgets/MyWidget/MyWidget`).
    -   `htmlContent` (`string`): Allows providing HTML content directly, which then bypasses loading from `resourcePath.html`.
    -   `uiLibrary` (`string`): Can specify a UI framework like `'bootstrap'` or `'jqueryui'`, though its direct usage within `HBaseWidget` itself is minimal.
    -   `onInitFinished` (`function`): A callback function executed when the widget's initialization process is fully complete.

## `HBaseView.js`

`HBaseView` extends `HBaseWidget` and serves as a base class for widgets that need to display content within various types of containers, such as popups, modals, offcanvas panels, or inline elements.

-   **Role**:
    -   Manages the display and lifecycle of view-oriented widgets.
    -   Abstracts the specifics of different container types (jQuery UI Dialog, Bootstrap Modal, Bootstrap Offcanvas).

-   **Key Features**:
    -   **View Modes (`viewMode` option)**:
        -   `'popup'`: Uses jQuery UI Dialog for a traditional popup window.
        -   `'modal-*'`: Uses Bootstrap Modals (e.g., `modal-sm`, `modal-lg`, `modal-fullscreen`).
        -   `'offcanvas-*'`: Uses Bootstrap Offcanvas panels (e.g., `offcanvas-start`, `offcanvas-end`, `offcanvas-top`, `offcanvas-bottom`).
        -   `'inline'`: Displays the content directly within the widget's element, optionally with a header.
        -   `'full'`: (Implied) Meant to take over the main content area.
        -   `'container'`: (Implied) To be placed within a specified DOM element.
    -   **Instance Management**: Handles the creation and disposal of dialog/modal/offcanvas instances. The `keepInstance` option controls whether the instance is destroyed or just hidden on close.
    -   **Show/Close Control**: Provides `show()` and `close()` methods to manage the visibility of the view. The `close()` method can be made conditional via the `beforeClose` callback.
    -   **Customizable Action Buttons**: For jQuery UI Dialogs (`viewMode: 'popup'`), the `_getActionButtons()` method can be overridden to define custom buttons in the dialog's button pane.
    -   **Header and Title**: Options like `title`, `isTitleVisible`, and `isHeaderVisible` control the display of titles and header sections, especially for Bootstrap containers and inline views.

-   **Core Options**:
    -   `viewMode` (`string`): Specifies the display container type (e.g., `'popup'`, `'modal-default'`, `'offcanvas-start'`, `'inline'`). Default is `'popup'`.
    -   `title` (`string`): The title for the dialog, modal, or offcanvas header.
    -   `modal` (`boolean`): For jQuery UI Dialog, determines if the dialog is modal. Default is `true`.
    -   `keepInstance` (`boolean`): If `false` (default for jQuery Dialog), the dialog instance is destroyed when closed. If `true`, it's hidden and can be reused.
    -   `onClose` (`function`): Callback executed after the view is closed. Receives `_contextOnClose` data.
    -   `beforeClose` (`function`): Callback executed before closing. If it returns `false`, the close action is prevented.
    -   `height`, `width`: Dimensions for jQuery UI Dialog.
    -   `default_palette_class`: CSS class for styling, often used for jQuery UI Dialog theming.

## `HBaseList.js`

`HBaseList` extends `HBaseWidget` and is designed as a base for widgets that display lists of records or other data, typically sourced from an `HRecordSet`.

-   **Role**:
    -   Provides common functionality for list-based widgets.
    -   Manages data (`HRecordSet`) and handles data loading and updates.
    -   Integrates with Heurist's event system for search and selection.

-   **Key Features**:
    -   **RecordSet Management**: Manages an `HRecordSet` instance (`this.recordSet`) which holds the data to be displayed.
    -   **Initial Data Loading**:
        -   Can be initialized with an existing `HRecordSet` via the `recordSet` option.
        -   Alternatively, can perform an initial search if the `searchInitial` option (a query string) is provided.
    -   **Event-Driven Updates (`searchDomain`)**:
        -   If a `searchDomain` is specified, the widget listens to Heurist events:
            -   `ON_REC_SEARCHSTART`: Typically triggers clearing content or showing a loading indicator.
            -   `ON_REC_SEARCH_FINISH`: Receives the new `HRecordSet` and calls `setRecordSet()` to refresh the list.
            -   `ON_REC_SELECT`: Handles changes in record selection, updating `this.recordSetSelected`.
    -   **Selection Handling**: Manages a list of selected record IDs (`this.recordSetSelected`) and provides `getSelection()` and `setSelection()` methods.
    -   **Abstract Rendering Methods**:
        -   `clearContent()`: Expected to be implemented by derived widgets to clear the list display.
        -   `renderConent()`: Abstract method that derived widgets must implement to render the actual list items from `this.recordSet`.
        -   `renderMessage()`: Used to display messages (e.g., "No items found", "Loading...").

-   **Core Options**:
    -   `entityType` (`string`): The type of entity being listed (e.g., `'rec'` for records). Default is `'rec'`.
    -   `searchDomain` (`string`): A unique identifier for a search context. Widgets sharing the same `searchDomain` can react to each other's search and selection events.
    -   `searchInitial` (`string`): A query string to execute for fetching the initial list of data if `recordSet` is not provided.
    -   `recordSet` (`HRecordSet`): An initial `HRecordSet` to populate the list.
    -   `placeholderInit` / `placeholderInitBlank` / `placeholderInitDef`: Options for controlling the message displayed when the list is initially empty.

## `HBaseOpts.js`

`HBaseOpts` extends `HBaseView` and appears to be a base widget for creating option editor forms for other widgets.

-   **Role**:
    -   Provides a standardized way to build UI for editing widget options.
    -   Typically displayed in a dialog (`HBaseView`'s popup mode).
    -   Handles filling form controls from an `editOptions` object and applying changes back.

-   **Key Features**:
    -   Uses `HBaseView` for display (often as a popup dialog).
    -   `editOptions` option: Takes an object representing the options to be edited.
    -   `_fillControls()`: Populates form fields from the `editOptions` object.
    -   `_getEditOptions()` / `_applyChanges()`: Retrieves values from form fields and updates `editOptions`, then typically calls an `onChange` callback or closes the dialog passing the modified options.
    -   Initializes HSelect for select elements and tabs.

## Key Files in this Directory

-   **`HBaseWidget.js`**: The fundamental base class for all Heurist widgets.
-   **`HBaseView.js`**: Base class for widgets that present content in various container types (dialogs, modals, offcanvas, inline).
-   **`HBaseList.js`**: Base class for widgets that display lists of data, often records from an `HRecordSet`.
-   **`HBaseOpts.js`**: Base class for creating option editor forms for widgets.
-   **`HBaseView.html`**: Contains HTML templates for the different `viewMode` containers used by `HBaseView` (e.g., Bootstrap modal structure, offcanvas structure).
