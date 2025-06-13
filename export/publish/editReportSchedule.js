/**
 * editReportSchedule.js
 * Provides client-side logic for a pop-up form to edit or create report schedules.
 * This script interacts with the `loadReports.php` AJAX endpoint to fetch and save schedule data.
 * It is typically utilized as a pop-up from a manage reports interface.
 *
 * Relies on jQuery, jQuery UI, and Heurist specific JavaScript objects (e.g., `window.hWin.HEURIST4`).
 *
 * @package     HeuristWebApp
 * @subpackage  AdminUI
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network Ltd.
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     5
 */

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * Constructor for the ReportScheduleEditor "class".
 * Manages the UI and logic for editing or creating a report schedule.
 * This is typically instantiated when the pop-up dialog is shown.
 *
 * @constructor
 * @author Artem Osmakov <osmakov@gmail.com>
 * @version 2011.0427 // REMARK: Original version, consider updating if significant changes are made.
 *
 * @returns {object} An object with public methods `save`, `cancel`, `getClass`, and `isA`.
 */
function ReportScheduleEditor() {
    /** @private @const {string} _className - The name of this class, used by `getClass` and `isA`. */
    const _className = "ReportScheduleEditor";
    /** @private {?Array} _entity - Holds the current report schedule data being edited. Populated from AJAX response.
     *                         Format is an array where indices correspond to field names in `_reports.fieldNames`.
     */
    let _entity;
    /** @private {number} _recID - The ID of the report schedule record. 0 or -1 for a new record. */
    let _recID;
    /** @private {Array<string>} _updatedFields - Array to store names of fields whose values have changed. */
    let _updatedFields = [];
    /** @private {Array<any>} _updatedDetails - Array to store the new values of changed fields. */
    let _updatedDetails = [];
    /** @private {?object} _reports - Stores the structure (fieldNames) and record data fetched from the server.
     *                            Expected structure: `{ fieldNames: [...], records: { recID: [...] } }`.
     */
    let _reports = null;

    /**
     * Initializes the editor form.
     * Sets up help text and fetches the report schedule data if editing an existing record,
     * or prepares for a new record.
     * Makes an AJAX call to `loadReports.php` with method `getreport`.
     *
     * @private
     */
    function _init() {
        // Set help text for the file path input.
        document.getElementById("lblFilePathHelp").innerHTML = "Path to which report is published (leave blank for default path " + window.hWin.HAPI4.database + "/generated-reports)";

        // Get record ID from URL parameters.
        _recID = window.hWin.HEURIST4.util.getUrlParameter('recID', location.search);
        if (!(Number(_recID) > 0)) { // REMARK: Corrected logic slightly for clarity. Original: !Number(_recID)>0
            _recID = 0; // Treat as new if ID is invalid or not provided.
        }
            
        const _url = window.hWin.HAPI4.baseURL + 'export/publish/loadReports.php';
        const request = { method: 'getreport', recID: _recID };
            
        // Send AJAX request to fetch report data.
        window.hWin.HEURIST4.util.sendRequest(_url, request, null, _continueInit);
    }
    
    /**
     * Continues initialization after report data is fetched.
     * Populates form fields and sets up UI elements based on the fetched data or for a new entry.
     *
     * @private
     * @param {object} response - The AJAX response object from the `getreport` call.
     *                          Expected to contain `status` and `data` (with `fieldNames` and `records`).
     */
    function _continueInit(response) {
        if (response.status != window.hWin.ResponseStatus.OK) {
            window.hWin.HEURIST4.msg.showMsgErr(response);
            return;
        }

        _reports = response['data'];

        let qlabel = ''; // REMARK: qlabel is assigned but not used further in this function. Might be for future use or leftover.
        let typeID = window.hWin.HEURIST4.util.getUrlParameter('typeID', location.search);
        let templatefile = window.hWin.HEURIST4.util.getUrlParameter('template', location.search);
        let hquery = window.hWin.HEURIST4.util.getUrlParameter('hquery', location.search);
            
        // Set default values if parameters are not provided (usually for new schedules based on a report).
        if (window.hWin.HEURIST4.util.isempty(typeID)) {
            typeID = "smarty";
        }
        if (window.hWin.HEURIST4.util.isnull(hquery)) {
            hquery = '';
        } else {
            // Attempt to get a label from the hquery string itself if present.
            let _qlabel = window.hWin.HEURIST4.util.getUrlParameter('label', hquery);
            if (!window.hWin.HEURIST4.util.isnull(_qlabel)) {
                qlabel = _qlabel;
            }
        }
        if (window.hWin.HEURIST4.util.isnull(templatefile)) {
            templatefile = '';
        }

        // Try to get the entity being edited.
        _entity = (_recID > 0 && _reports && _reports.records && _reports.records[_recID]) ? _reports.records[_recID] : null;

        if (Number(_recID > 0) && window.hWin.HEURIST4.util.isnull(_entity)) {
            document.getElementById("statusMsg").innerHTML = "<strong>Error: Report Schedule #" + _recID + "  was not found. Clicking 'save' button will create a new Schedule.</strong><br><br>";
        }
        
        // If _entity is still null (new record or not found), create a default structure.
        if (window.hWin.HEURIST4.util.isnull(_entity)) {
            _recID = -1; // Mark as new record.
            // Default structure: ["rps_ID", "rps_Type", "rps_Title", "rps_FilePath", "rps_URL", "rps_FileName", "rps_HQuery", "rps_Template", "rps_IntervalMinutes"]
            _entity = [-1, typeID, qlabel, '', '', '', hquery, templatefile, 1440]; // Default interval: 1 day (1440 minutes)
        }
        
        // Auto-fill FileName based on Title (cleaned for filenames).
        document.getElementById('rps_Title').onchange = function(event) {
            document.getElementById('rps_FileName').value = window.hWin.HEURIST4.ui.cleanFilename(event.target.value);
        };

        _updateTemplatesList(); // Populate template dropdown.
        _fromArrayToUI();     // Populate form fields from _entity data.
    }

    /**
     * Populates the template selector dropdown (`#rps_Template`).
     * Uses `window.hWin.HEURIST4.ui.createTemplateSelector` for dynamic population.
     * REMARK: An older, commented-out implementation for populating the selector was present.
     *
     * @private
     * @todo Consider if `#todo - filter based on record types in result set` is still relevant.
     */
    function _updateTemplatesList() {
        let sel = $('#rps_Template');
        const keepSelValue = sel.val(); // Preserve current selection if possible.

        sel.empty(); // Clear existing options.

        // Use Heurist utility to populate template selector.
        window.hWin.HEURIST4.ui.createTemplateSelector(sel, null, keepSelValue, null);
    }

    /**
     * Fills the form input fields with values from the `_entity` array.
     * Updates the document title and status messages based on whether it's a new or existing record.
     *
     * @private
     */
    function _fromArrayToUI() {
        if (!_reports || !_reports.fieldNames) {
             // Should not happen if _continueInit ran correctly.
            return;
        }
        let fnames = _reports.fieldNames;

        // Populate each form field corresponding to a fieldName.
        for (let i = 0, l = fnames.length; i < l; i++) {
            let fname = fnames[i];
            let el = document.getElementById(fname);
            if (!window.hWin.HEURIST4.util.isnull(el)) {
                el.value = (_entity && _entity[i] !== null && _entity[i] !== undefined) ? _entity[i] : ''; // Ensure null/undefined are empty strings
            }
        }

        // Update UI elements based on new/edit mode.
        if (_recID < 0) {
            document.getElementById("rps_ID").innerHTML = 'to be generated';
            document.title = "Create New Report Schedule";
        } else {
            document.getElementById("rps_ID").innerHTML = _recID;
            document.title = "Report Schedule #: " + _recID + " '" + (_entity && _entity[2] ? _entity[2] : '') + "'"; // _entity[2] is rps_Title
            document.getElementById("statusMsg").innerHTML = "";
        }
        
        // Ensure interval has a default value if empty or invalid.
        let intervalEl = document.getElementById("rps_IntervalMinutes");
        let interval = intervalEl.value;
        if (window.hWin.HEURIST4.util.isempty(interval) || isNaN(parseInt(interval)) || parseInt(interval) < 0) {
            intervalEl.value = 1440; // Default to 1 day (1440 minutes).
        }
    }

    /**
     * Gathers data from UI form fields, compares with original `_entity` data,
     * and populates `_updatedFields` and `_updatedDetails` arrays with changed values.
     * Also performs validation for mandatory fields.
     *
     * @private
     * @param {boolean} isShowWarn - If true, shows an alert message for empty mandatory fields.
     * @returns {string} "mandatory" if any mandatory field is empty, otherwise "ok".
     */
    function _fromUItoArray(isShowWarn) {
        _updatedFields = [];
        _updatedDetails = [];

        // Ensure interval has a default if cleared or invalid.
        let intervalEl = document.getElementById("rps_IntervalMinutes");
        let interval = intervalEl.value;
        if (window.hWin.HEURIST4.util.isempty(interval) || isNaN(parseInt(interval)) || parseInt(interval) < 0) {
            intervalEl.value = 1440;
        }
        
        if (!_reports || !_reports.fieldNames) return "mandatory"; // Should not happen.
        let fnames = _reports.fieldNames;

        // Iterate through fields to find changes and validate.
        for (let i = 0, l = fnames.length; i < l; i++) {
            let fname = fnames[i];
            let el = document.getElementById(fname);

            if (window.hWin.HEURIST4.util.isnull(el) || fname === 'rps_ID') { // Skip ID field or non-existent elements.
                continue;
            }
            
            // Check if field value has changed from original _entity value.
            // Handles new records (_recID < 0) by including all fields.
            // Handles null vs empty string comparison.
            const originalValue = (_entity && _entity[i] !== null && _entity[i] !== undefined) ? String(_entity[i]) : "";
            const currentValue = el.value;

            if (_recID < 0 || (currentValue !== originalValue && !(currentValue === "" && originalValue === ""))) {
                 // Special case: if original was null and current is empty string, treat as unchanged.
                if (!(_recID >=0 && _entity[i] === null && currentValue === "")) {
                     _updatedFields.push(fname);
                     _updatedDetails.push(currentValue);
                }
            }

            // Mandatory field check (excluding specific fields).
            if (window.hWin.HEURIST4.util.isempty(el.value) &&
                !(fname === 'rps_FilePath' || fname === 'rps_URL' || fname === 'rps_IntervalMinutes')) {
                if (isShowWarn) {
                    alert(fname.substr(4) + " is a mandatory field"); // Show user-friendly field name.
                }
                if (el.focus) el.focus(); // el.dispatchEvent(new Event('focus')) might be better for modern usage.
                _updatedFields = []; // Clear updated fields as validation failed.
                return "mandatory";
            }
        }
        return "ok"; // All mandatory fields filled.
    }

    /**
     * Handles the AJAX response after attempting to save report data.
     * Shows success/error messages and closes the window on success.
     *
     * @private
     * @param {object} response - The AJAX response object from the `savereport` call.
     *                          Expected to contain `status` and `data` (array of results).
     */
    function _updateResult(response) {
        if (response.status != window.hWin.ResponseStatus.OK) {
            window.hWin.HEURIST4.msg.showMsgErr(response);
            return;
        }
        
        let error = false;
        // let report = ""; // REMARK: `report` variable was previously assembled but not used. Removed.
        // let ind; // REMARK: `ind` is not needed if using for...of or forEach. Kept for now to match original structure.

        for (let ind in response.data) { // Iterate over results for potentially multiple saves (though UI implies one).
            if (!window.hWin.HEURIST4.util.isnull(ind)) {
                let item = response.data[ind];
                if (isNaN(item)) { // If item is not a number, it's an error message from server.
                    window.hWin.HEURIST4.msg.showMsgErr(item); // Show specific error.
                    error = true;
                } else { // Success, item is the (potentially new) record ID.
                    _recID = Number(item); // Update current record ID.
                    // if (report !== "") { report = report + ","; }
                    // report = report + Math.abs(_recID);
                }
            }
        }

        if (!error) {
            // let ss = (_recID < 0) ? "added" : "updated"; // REMARK: `ss` variable was not used. Removed.
            window.close(response); // Close window, passing response back to parent if needed.
        }
    }

    /**
     * Gathers changed data from the UI, constructs a data object, and sends it to the server
     * via an AJAX call to `loadReports.php` with method `savereport`.
     * This is the core logic for the public `save()` method.
     *
     * @private
     */
    function _updateOnServer() {
        // First, gather and validate data from UI.
        if (_fromUItoArray(true) === "mandatory") { // Show warnings for mandatory fields.
            return; // Stop if validation fails.
        }

        // If there are changes or it's a new record, prepare data for server.
        if (_recID < 0 || (_updatedFields.length > 0)) { // Always send if new, or if fields updated for existing
            let oDataToServer = {
                report: {
                    colNames: [],
                    defs: {}
                }
            };

            // If it's a new record and no fields were explicitly changed (e.g., all defaults accepted),
            // then populate _updatedFields and _updatedDetails with all fields to ensure they are sent.
            if (_recID < 0 && _updatedFields.length === 0 && _reports && _reports.fieldNames) {
                 let fnames = _reports.fieldNames;
                 for(let i = 0, l = fnames.length; i < l; i++) {
                     let fname = fnames[i];
                     let el = document.getElementById(fname);
                     if (window.hWin.HEURIST4.util.isnull(el) || fname === 'rps_ID') continue;
                     _updatedFields.push(fname);
                     _updatedDetails.push(el.value);
                 }
            }

            if (_updatedFields.length === 0) { // No actual changes to send for an existing record.
                 window.close(null); // Just close.
                 return;
            }


            oDataToServer.report.colNames = _updatedFields;
            oDataToServer.report.defs[(_recID < 0) ? -1 : _recID] = _updatedDetails; // Use -1 for new records, actual ID for updates

            // Send AJAX request to save data.
            let baseurl = window.hWin.HAPI4.baseURL + "export/publish/loadReports.php";
            let request = { method: 'savereport', data: oDataToServer };
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, _updateResult);
        } else {
            // No changes were made to an existing record.
            window.close(null); // Simply close the window.
        }
    }

    // --- Public Methods ---
    let that = {
            /**
             * Saves the current report schedule data.
             * It gathers data from the form, validates it, and sends it to the server via AJAX.
             * On success, the window is typically closed by the AJAX callback handler.
             */
            save : function () {
                _updateOnServer();
            },

            /**
             * Handles the cancel action.
             * Checks if any changes were made to the form. If so, prompts the user for confirmation
             * before closing the window. If no changes, closes the window directly.
             */
            cancel : function () {
                _fromUItoArray(false); // Check for changes without showing warnings.
                if (_updatedFields.length > 0) {
                    let areYouSure = confirm("Changes were made. By cancelling, all changes will be lost. Are you sure?");
                    if (areYouSure) {
                        window.close(null);
                    }
                } else {
                    window.close(null);
                }
            },

            /**
             * Gets the class name.
             * @returns {string} The class name "ReportScheduleEditor".
             */
            getClass: function () {
                return _className;
            },

            /**
             * Checks if the object is an instance of the given class name.
             * @param {string} strClass - The class name to check against.
             * @returns {boolean} True if `strClass` matches "ReportScheduleEditor", false otherwise.
             */
            isA: function (strClass) {
                return (strClass === _className);
        }
    };

    _init(); // Initialize the editor when a new instance is created.
    return that; // Return the public interface.
}