/**
 * @file detectHeurist.js
 * @brief Detects main Heurist window and initializes global configurations.
 * @fileOverview This script is responsible for detecting the main Heurist window (`hWin`) across frames
 * and initializing global Heurist-specific configurations like `ResponseStatus` and a default
 * `entityRecordCfg` if they are not already defined. This ensures that core Heurist objects and
 * configurations are accessible consistently, especially in applications that might operate within
 * iframes. It includes the `_detectHeurist` function to find the main window and sets up default
 * `ResponseStatus` enums and `entityRecordCfg` if they don't exist on `window.hWin`.
 * @package Heurist academic knowledge management system
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 6.0
 */

/**
 * Recursively searches for the main Heurist application window (`HEURIST4` object)
 * starting from the given window and traversing up its parent frames.
 * If the top-most window is reached and `HEURIST4` is not found, the current window
 * is assumed to be the main Heurist window (relevant for scenarios where a Heurist
 * component might be loaded standalone or as the top frame).
 * It handles potential cross-domain errors when accessing parent frames.
 *
 * @param {Window} win - The starting window object to check.
 * @returns {Window} The window object deemed to be the main Heurist window (`hWin`).
 *                   This is the window where the `HEURIST4` object is found, or the
 *                   top-most accessible window if `HEURIST4` is not explicitly found higher up.
 */
function _detectHeurist( win ){
    if(win.HEURIST4){ // HEURIST4 object is defined in this window
        return win;
    }

    try{
        // Attempt to access parent document to check if it's accessible (same-domain)
        // This line itself doesn't use 'doc', but accessing win.parent.document can throw a cross-origin error.
        // eslint-disable-next-line no-unused-vars
        let doc = win.parent.document;
    }catch(e){
        // Not accessible, likely due to cross-domain restrictions.
        // In this case, the current 'win' is the highest accessible frame in this lineage.
        return win;
    }

    if (win.top == win.self) {
        // Current window is the top-most window of its frame hierarchy,
        // but HEURIST4 was not found on it in the initial check.
        // This implies the current 'window' should host HEURIST4 or is the primary window.
        return window; // Use 'window' (global scope of this script's execution)
    }else{
        // Not the top-most, and HEURIST4 not found here, so recurse to parent.
        return _detectHeurist( win.parent );
    }
}

/**
 * @global
 * @description Detects whether the current window is the top-most window or inside a frame.
 * Assigns the detected Heurist main window to `window.hWin`.
 * This ensures `hWin` points to the window instance containing the primary Heurist application objects.
 */
if(!window.hWin) window.hWin = _detectHeurist(window);

/**
 * @global
 * @description Initializes `window.hWin.ResponseStatus` if it's not already defined.
 * This object provides a canonical list of status codes used for interpreting responses from server calls.
 *
 * @enum {string} ResponseStatus
 * @property {string} INVALID_REQUEST - The request provided was invalid.
 * @property {string} NOT_FOUND - The requested object was not found.
 * @property {string} OK - The response contains a valid result; the operation was successful.
 * @property {string} REQUEST_DENIED - The user/webpage is not allowed to use the service or perform the action due to permissions.
 * @property {string} ACTION_BLOCKED - The action cannot be performed due to constraints or insufficient rights.
 * @property {string} DB_ERROR - A request could not be processed due to a server database error. Likely a bug.
 * @property {string} UNKNOWN_ERROR - A request could not be processed due to an unspecified server error. Retrying may succeed.
 * @property {string} SYSTEM_CONFIG - A non-fatal system configuration issue occurred. System admin should be contacted.
 * @property {string} SYSTEM_FATAL - A fatal system configuration error occurred. System admin must be contacted.
 */
if(!window.hWin.ResponseStatus){
    
    window.hWin.ResponseStatus =
    {
            INVALID_REQUEST: "invalid",    // The Request provided was invalid.
            NOT_FOUND: "notfound",         // The requested object not found.
            OK: "ok",                      // The response contains a valid Result.
            REQUEST_DENIED: "denied",      // The webpage is not allowed to use the service. User permissions.
            ACTION_BLOCKED: "blocked",     // No enough rights or action is blocked by constraints
            DB_ERROR: "database",          // A request could not be processed due to a server database error. Most probably this is BUG. Contact developers
            UNKNOWN_ERROR: "unknown",      // A request could not be processed due to a server error. The request may succeed if you try again.
            SYSTEM_CONFIG: "syscfg",       // System NON-fatal configuration. Contact system admin
            SYSTEM_FATAL: "system"         // System fatal configuration. Contact system admin
    };
    
}

/**
 * @global
 * @description Initializes `window.hWin.entityRecordCfg` if it's not already defined.
 * This object provides a default base configuration for the "records" entity type.
 * It defines standard fields like ID, Title, URL, ScratchPad, RecordTypeID,
 * ownership, and visibility settings, along with their default properties
 * (data type, role, display name, etc.) for the Heurist system.
 * This configuration is used by various parts of the UI when dealing with generic record operations
 * or as a fallback if specific entity configurations are not available.
 */
if(!window.hWin.entityRecordCfg){
    window.hWin.entityRecordCfg = {
        "entityName": "records",

        "tableName": "Records",
        "tablePrefix": "rec",
        
        "helpContent": "records.htm",
        
        "searchFormContent": "searchRecords.html",
        
        "entityTitle": "Record",
        "entityTitlePlural": "Records",
        "empty_remark": "No records match the search criteria",
        
        "fields": [
                {
                    "dtID": "rec_ID",
                    "keyField": true,
                    "dtFields":{
                        "dty_Type":"integer",
                        "dty_Role":"primary",
                        "rst_DisplayName": "ID:",
                        "rst_Display":"hidden"
                    }
                },
                {
                    "dtID": "rec_Title",
                    "titleField": true,
                    "dtFields":{
                        "dty_Type":"freetext",
                        "dty_Role":"title",
                        "dty_Size":1023,
                        "rst_DisplayName": "Record title",
                        "rst_DisplayHelpText": "", 
                        "rst_Display":"hidden"
                    }
                },
                {   
                    "dtID": "rec_URL",
                    "dtFields":{
                        "dty_Type":"freetext",
                        "dty_Size":2000,
                        "rst_DisplayWidth": 85,
                        "rst_DisplayName": "Record hyperlink URL",
                        "rst_DisplayHelpText": "This is a special URL field which is hyperlinked in search results. Use where one primary URL applies to <br>each record, eg. for internet bookmarks. These URLs can be auto-verified. Turn on/off in record attributes" ,
                        "rst_Display": "hidden"
                    }
                },
                {
                    "dtID": "rec_ScratchPad",
                    "titleField": true,
                    "dtFields":{
                        "dty_Type":"blocktext",
                        "dty_Size":65535,
                        "rst_DisplayName": "ScratchPad",
                        "rst_DisplayHelpText": "", 
                        "rst_Display": "hidden"
                    }
                },
                {
                    "dtID": "rec_RecTypeID",
                    "dtFields":{
                        "dty_Type":"integer",
                        "dty_Size":5,
                        "rst_DisplayName": "Record type",
                        "rst_DisplayHelpText": "" ,
                        "rst_Display": "hidden"
                    }
                },
                {
                    "dtID": "rec_OwnerUGrpID",
                    "dtFields":{
                        "dty_Type":"integer",
                        "dty_Size":5,
                        "rst_DisplayName": "Ownership",
                        "rst_DisplayHelpText": "" ,
                        "rst_Display": "hidden"
                    }
                },
                {
                    "dtID": "rec_NonOwnerVisibility",
                    "dtFields":{
                        "dty_Type":"freetext",
                        "dty_Size":20,
                        "rst_DisplayName": "Non owner access",
                        "rst_DisplayHelpText": "" ,
                        "rst_Display": "hidden"
                    }
                },
                {
                    "dtID": "rec_NonOwnerVisibilityGroups",
                    "dtFields":{
                        "dty_Type":"freetext",
                        "dty_Role":"virtual",
                        "dty_Size":2000,
                        "rst_Display": "hidden"
                    }
                }
        ]
    };

}
