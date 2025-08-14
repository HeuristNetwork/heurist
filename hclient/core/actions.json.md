# Actions JSON Schema Documentation

This document describes the structure and meaning of keys in the `actions.json` configuration file.  
Each element in the top-level array represents a single **action** in the system.

---

## Top-level keys for an action

| Key       | Type     | Required | Description |
|-----------|----------|----------|-------------|
| `id`      | string   | Yes      | Unique identifier for the action, typically used internally. Often follows the format `menu-<section>-<action>`. |
| `text`    | string   | Yes      | The label shown to the user in menus or UI. May contain separators (e.g., lines of dashes) for grouping. |
| `title`   | string   | No       | Optional tooltip or descriptive text displayed in the UI. |
| `href`    | string   | No       | URL or relative path to open when the action is triggered. If present, the system will open this link. |
| `target`  | string   | No       | HTML link target (`_blank`, `_self`, etc.) when `href` is used. |
| `display` | string   | No       | Optional UI display control (e.g., `"none"` to hide the action). |
| `data`    | object   | No       | Additional parameters controlling the action’s behavior. See below. |

---

## Keys inside `data`

The `data` object contains metadata and behavior configuration for the action.

| Key                    | Type     | Description |
|------------------------|----------|-------------|
| `logaction`            | string   | Identifier for logging or analytics when the action is executed. |
| `icon`                  | string   | UI icon identifier (e.g., `ui-icon-database`, `ui-icon-plus`). |
| `container`            | string   | Logical area or UI container where the action applies (e.g., `admin`, `design`, `publish`, `populate`). |
| `entity`               | string   | Database table, entity name, or logical object associated with the action. |
| `header`               | string   | Dialog or page title shown when executing the action. |
| `help`                 | string   | Optional help topic identifier or file name for user assistance. |
| `pwd` / `pwd-nonowner` | string   | Permission or authentication requirement keyword for running the action. |
| `user-admin-status`    | string   | Required admin privilege level (`-1` - no verification, `0` - must be logged in, `1` - must be admin of Database managers group, `2` - must be Database Owner). |
| `user-experience-level`| string   | Minimum user experience level required to access this action. |
| `user-permissions`     | string   | Required permissions (e.g., `"add delete"`). |
| `is_association_member`| string   | Restriction flag indicating that the user or database(project) must belong to HeuristNetwork association. |
| `user_member_status`   | string   | Use must belong to a certain users group. |
| `query`                | string   | Search query string for search-related actions. |
| `searchGroup`          | string   | Search group name for filtering results. |
| `recID`                | string   | Record identifier for record-specific actions. |
| `dialog_width`         | string   | Width of a popup/dialog window (pixels). |
| `dialog_height`        | string   | Height of a popup/dialog window (pixels). |
| `size`                 | string   | Preset size category for dialogs (`small`, `medium`, `large`, `portrait`). |
| `class`                | string   | Additional CSS class for UI styling. |

---

## Example Action

```json
{
  "id": "menu-structure-summary",
  "href": "viewers/visualize/databaseSummary.php",
  "data": {
    "user-admin-status": "-1",
    "logaction": "st_Visualization",
    "icon": "ui-icon-eye",
    "container": "design",
    "header": "Structure > Visualization of links between record types"
  },
  "text": "Visualise"
}
