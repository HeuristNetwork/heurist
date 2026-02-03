# HMenu Widget

## Overview

The `HMenu` widget is a Heurist component designed for displaying navigation menus within the Heurist interface. It provides a flexible way to render menus from various data sources and supports different visual styles and interactions.

HFilter is widget that handles execution of saved searches. It is invoked via HMenu. Both are used in CMS v3 only.

## Key Features

-   **View Modes**: Supports multiple ways to display the menu:
    -   `horizontal`: A standard horizontal navigation bar.
    -   `vertical`: A vertically stacked menu.
    -   `treeview`: A hierarchical tree-like structure, often used for site maps or complex navigation.
-   **Styling Options**: Offers different visual styles for menu items:
    -   `links`: Standard hyperlink-based menu items.
    -   `pills`: Menu items styled as pills (rounded rectangular buttons).
    -   `buttons`: Menu items can be styled to look like buttons.
-   **Data Sources**: Menu items can be defined through:
    -   An array of record IDs (CMS page records or Saved filter IDs).
    -   A JSON structure detailing `title`, `icon`, `pageId`, `action`, `actionParams`, and `children` for hierarchical menus.
-   **Action Handling**: Menu items can trigger various actions:
    -   `data-heurist-pageid`: Navigates to a CMS page specified by its record ID.
    -   `data-heurist-action`: Executes a predefined system or custom action. Action parameters can be supplied via `data-heurist-actionParams`.
    -   `data-heurist-search`: Initiates a saved search (filter) identified by its ID.
-   **Multi-level Dropdowns**: Supports nested menus (dropdowns within dropdowns) for `horizontal` and `vertical` view modes.
-   **Customization and Callbacks**:
    -   `customActionHandler`: Allows replacing the default action handling mechanism with a custom function.
    -   `onBeforeAction`: A callback function that executes before an action is performed, allowing for pre-action logic or cancellation.
    -   `onActionComplete`: A callback function invoked after an action has been executed.
-   **Dynamic Reloading**: Menu data can be reloaded, for instance, based on changes in user credentials or other events.
-   **Edit Mode**: Includes an `isEditMode` option, used in HMenuEdit as an integration with content management functionalities for editing menu structures.

## How to Initialize

The `HMenu` widget is initialized as a jQuery UI widget on a container element.

```javascript
$('#myMenuContainer').HMenu({
    // Heurist API instance
    hapi: heuristApiInstance,

    // Menu items can be an array of IDs or a JSON structure
    menuItems: [1, 2, 3], // Example: Array of page record IDs
    // OR
    // menuItems: '[{"title": "Home", "pageId": 1}, {"title": "About", "children": [...]}]',

    viewMode: 'horizontal', // 'horizontal', 'vertical', or 'treeview'
    styleMode: 'links',     // 'links' or 'pills'
    expandLevels: 1,        // For 'treeview', how many levels to expand initially

    // Optional: Link to a search domain for search-related actions
    // searchDomain: 'mySearchDomain',

    // Optional: Custom action handler
    // customActionHandler: function(action_id, opts) {
    //     console.log("Custom action:", action_id, opts);
    // }
});
```

## Options

The following options are available for configuring the `HMenu` widget:

| Option                | Type                                  | Default                               | Description                                                                                                                                                              |
| --------------------- | ------------------------------------- | ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `resourcePath`        | `string`                              | `'hclient/widgets/HMenu/HMenu'`       | Path to the widget's resources (CSS).                                                                                                                                    |
| `menuItems`           | `array`\|`string`                     | `null`                                | Array of record IDs or a JSON string defining the menu structure. JSON can include `title`, `icon`, `pageId`, `action`, `actionParams`, and `children` for sub-menus. |
| `viewMode`            | `string`                              | `'horizontal'`                        | Display mode for the menu: `'horizontal'`, `'vertical'`, or `'treeview'`.                                                                                                |
| `styleMode`           | `string`                              | `'links'`                             | Visual style of menu items: `'links'`, `'pills'`.                                                                                                                        |
| `expandLevels`        | `number`                              | `0`                                   | For `treeview` mode, specifies how many levels of the tree are initially expanded (e.g., `0` for none, `1` for the first level, `2` for all).                          |
| `viewFilterMode`      | `string`                              | `'inline'`                            | Mode for displaying the filter view when a search action is triggered.                                                                                                   |
| `searchDomain`        | `string`                              | `null`                                | The search domain to be used if the menu triggers search actions.                                                                                                        |
| `customActionHandler` | `function`                            | `null`                                | A custom function to handle menu item actions, overriding the default behavior. Signature: `function(action_id, opts)`.                                                  |
| `onBeforeAction`      | `function`                            | `null`                                | Callback function executed before a menu action. Can prevent the action if it returns `true`. Signature: `function(action_id, opts)`.                                   |
| `onActionComplete`    | `function`                            | `null`                                | Callback function executed after a menu action is completed. Passed as `opts.callback` to the action handler.                                                            |
| `isEditMode`          | `boolean`                             | `false`                               | If `true`, may enable editing functionalities or alter link generation (e.g., links to edit pages instead of view pages).                                                  |

## Related Widgets

-   **`HMenuPersonal.js`**: A specialized version of `HMenu` tailored for displaying user-specific menus, such as login/logout links, user profile access, or personalized navigation options. It reacts to changes in user credentials.
-   **`HMenuEdit.js`**: A widget used for editing functionality of menus in CMS editor

## Key Files in this Directory

-   **`HMenu.js`**: The core JavaScript file for the `HMenu` widget.
-   **`HMenu.css`**: CSS styles for the `HMenu` widget, defining its appearance for different view modes and styles.
-   **`HMenuOpts.js`**: Widget for HMenu's property editor form, used for configuring HMenu instances, within CMS editor.
-   **`HMenuOpts.html`**: The HTML structure for the HMenu property editor form.
-   **`HMenuEdit.js`**: extension for HMenu for editing functionality of menus. It is used in CMS editor
-   **`HMenuPersonal.js`**: Implements the user-specific menu widget.
-   **`HPersonalBtn.html`**: HTML template for the button/trigger element of the personal menu.
-   **`HPersonalMenu.html`**: HTML template for the dropdown content of the personal menu.
