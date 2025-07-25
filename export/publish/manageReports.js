/**
* manageReports.js
* 
* Provides the main user interface for managing scheduled reports within Heurist.
* This includes listing existing schedules, searching, and providing actions to
* create, edit, delete, and manually run/view reports.
*
* It uses jQuery DataTables to display the list of report schedules and interacts
* with the `loadReports.php` AJAX endpoint for data operations. Editing or creating
* a new schedule typically involves launching `editReportSchedule.html` (which uses
* `editReportSchedule.js`) in a dialog or popup.
*
* @todo Change to a more generic entity manager pattern if applicable.
*
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.26
*/

/**
 * Global instance of the ReportManager. Initialized typically on page load.
 * @type {?ReportManager}
 */
let reportManager = null;


/**
 * ReportManager class for listing, searching, and managing scheduled reports.
 *
 * @constructor
 * @param {boolean} [_isFilterMode=false] - Indicates if the manager is in a mode to select from all reports or filter an existing set.
 *                                          Currently marked as NOT USED in original comments.
 * @param {boolean} [_isWindowMode=false] - True if the manager is operating within a popup window context, false if embedded in a div.
 *                                          This may affect how cancel/close operations are handled.
 */
function ReportManager(_isFilterMode, _isWindowMode) { // _isFilterMode is not used
    /** @private @const {string} _className - The name of this class. */
	const _className = "ReportManager";
    /** @private {?DataTable} _dataTable - The DataTables instance for displaying report schedules. */
    let _dataTable;
    /** @private {?object} _dataTableParams - Parameters used to initialize the DataTable. */
    let _dataTableParams;
            
    /** @private {boolean} _isSelection - Flag indicating if selection mode (e.g., checkboxes) is active in the table. */
    let _isSelection = false;
    /** @private {?(number|string)} _usrID - User ID for filtering reports (currently not implemented for filtering). */
	let _usrID;
    /** @private {Array<string>} _arr_selection - Array to store IDs of selected items, typically from URL params in selection mode. */
	let _arr_selection = [];
    /** @private {?function} _callback_func - Callback function to be executed in non-window mode, e.g., after selection. */
	let _callback_func;

    /** @private {Array<object>} _records - Array storing all report schedule data fetched from the server. */
	let _records;
    /** @private {?string} _keepParameters - Stores URL parameters to be passed to the edit dialog, e.g., for pre-filling a new schedule. */
	let _keepParameters = null;

	/**
	 * Initializes the ReportManager.
	 * Reads URL parameters for initial state (e.g., pre-selected items, parameters for new schedule).
	 * Fetches and displays the list of report schedules.
	 *
	 * @private
	 * @param {number|string} [usrID] - User ID, potentially for filtering (currently not used for filtering).
	 * @param {function} [_callback] - Callback function for non-window mode operations.
	 */
	function _init(usrID, _callback) {
		_callback_func = _callback;
		_keepParameters = null;

        // Check for URL parameters if not a direct user ID call (typically for selection mode or linking from elsewhere)
		if (window.hWin.HEURIST4.util.isnull(usrID) && location.search.length > 1) {
			let sIDs = window.hWin.HEURIST4.util.getUrlParameter('ids', location.search);
			if (sIDs) {
				_arr_selection = sIDs.split(',');
			}

            // If hquery and template are in URL, keep these to pass to 'new report' dialog
			if (!(window.hWin.HEURIST4.util.isempty(
                        window.hWin.HEURIST4.util.getUrlParameter('hquery', location.search)) ||
                    window.hWin.HEURIST4.util.isempty(
                        window.hWin.HEURIST4.util.getUrlParameter('template', location.search)))) {
				_keepParameters = location.search; // Keep the entire query string
			}
		}

		_usrID = usrID; // Store usrID, though filtering by it isn't implemented.

        _refreshReports(); // Fetch and display reports.
	}


	/**
	 * Initializes or re-initializes the DataTable with the provided dataset.
	 * Defines columns, rendering for actions (edit, run, delete, view links), and status.
	 *
	 * @private
	 * @param {Array<object>} dataSet - An array of report schedule objects to populate the table.
	 */
    function _initDataTable(dataSet) {
        // If no data, hide the bottom "Create Schedule" button and exit.
        if (!(dataSet && dataSet.length > 0)) {
            $('#tb_bottom').hide();
            if (_dataTable) { // Clear existing table if it exists
                 _dataTable.clear().destroy();
                 $('.div_datatable').empty();
                 _dataTable = null;
            }
            return;
        }
        $('#tb_bottom').show();

        // Destroy existing DataTable instance if it exists, before re-initializing.
        if (_dataTable) {
            _dataTable.destroy();
            _dataTable = null;
            $('.div_datatable').empty(); // Clear the container
        }
        
        _dataTableParams = {
            autoWidth: false,
            dom: 'fip', // Show filter, information, pagination
            pageLength: 20,
            ordering: true,
            processing: false, // Client-side processing
            serverSide: false,
            data: dataSet,
            columns: null // Defined below
        };
        
        _dataTableParams['columns'] = [
            { data: "selection", title: "Sel", visible: _isSelection, sortable: true, orderable: false }, // Checkbox column for selection

            // Status column with icon and tooltip
            { data: 'status', title: "<div style='font-size:10;'>Status</div>", visible: true, sortable: true, className: 'center', width: "16px",
                render: function(data, type) { // data is the status code (0-3)
                    if (type === 'display') {
                        if (data > 0) { // 0 means OK
                            let shint = '';
                            let sfont = '';
                            if (data == 1) { // Template file missing
                                shint = 'Template file does not exist';
                                sfont = 'style="color:red"';
                            } else if (data == 2) { // Output folder missing
                                shint = 'Output folder does not exist';
                            } else if (data == 3) { // Generated report not created yet
                                shint = 'Generated report is not created yet';
                            }
                            if (shint) {
                                return '<span class="ui-icon ui-icon-alert" ' + sfont + ' title="' + shint + '"></span>';
                            }
                        }
                        return ''; // No icon for status 0 (OK)
                    }
                    return data; // For sorting/filtering, use the raw status code
            }},

            { data: 'rps_ID', title: "<div style='max-width:15px;'>#</div>", sortable: true, className: 'right', width: "16px" },

            // Edit button column
            { data: 'rps_ID', title: "<div style='font-size:10;'>Edit</div>", sortable: false, orderable: false, width: "16px",
                render: function(data, type) {
                    if (type === 'display') {
                        return '<a href="#" onclick="window.reportManager.editReport(' + data + ');return false;">' +
                               '<span class="ui-icon ui-icon-pencil" title="' + window.hWin.HR('Edit') + '"></span></a>';
                    }
                    return data;
            }},

            // Execute (run) report button column
            { data: 'rps_ID', title: "<div style='font-size:10;'>Exec</div>", sortable: false, orderable: false, width: "16px",
                render: function(rps_ID, type, row) { // row.status can be used here
                    if (type === 'display') {
                        if (row.status == 1) { return ''; } // Cannot run if template is missing
                        return `<a href="${window.hWin.HAPI4.baseURL}?db=${window.hWin.HAPI4.database}&publish=1&template_id=${rps_ID}" target="_blank">` +
                               '<span class="ui-icon ui-icon-refresh" title="' + window.hWin.HR('Run report') + '"></span></a>';
                    }
                    return rps_ID;
            }},

            // View HTML report link column
            { data: 'rps_ID', title: "<div style='font-size:10;min-width:30px;'>HTML</div>", sortable: false, orderable: false, width: "18px",
                render: function(rps_ID, type, row) {
                    if (type === 'display') {
                         if (row.status == 1 || row.status == 2) { return ''; } // Cannot view if template/folder missing
                        return `<a href="${window.hWin.HAPI4.baseURL}?db=${window.hWin.HAPI4.database}&publish=3&template_id=${rps_ID}&mode=html" target="_blank">` +
                               '<img alt="HTML link" src="../../hclient/assets/external_link_16x16.gif" width="16" height="16" border="0" title="HTML link"></a>';
                    }
                    return rps_ID;
            }},
            
            // View Raw report output link column
            { data: 'rps_URL', title: "<div style='font-size:10;'>Raw</div>", sortable: false, orderable: false, width: "16px",
                render: function(data, type, row) { // data here is rps_URL (output format)
                    if (type === 'display') {
                        if (row.status == 1 || row.status == 2) { return ''; }
                        let outputformat = data ? data : 'txt'; // Default to txt if rps_URL is empty
                        return `<a href="${window.hWin.HAPI4.baseURL}?db=${window.hWin.HAPI4.database}&publish=3&template_id=${row.rps_ID}&mode=${outputformat}" target="_blank">` +
                               outputformat.toUpperCase() +
                               '&nbsp;<img alt="Raw output link" src="../../hclient/assets/external_link_16x16.gif" width="16" height="16" border="0" title="Raw output link"></a>';
                    }
                    return data;
            }},

            { data: "rps_Title", title: "Title", sortable: true /*, resizeable:true // resizeable not a standard DT option */ },
            { data: "rps_HQuery", title: "Query", sortable: false, /* resizeable:true, */
                render: function(data, type) {
                    if (type === 'display' && data) { // Prevent null from being displayed as "null"
                        return "<div style='max-width:400px;overflow: hidden;white-space: nowrap;text-overflow: ellipsis;' title='" + data + "'>" + data + "</div>";
                    }
                    return data ? data : ''; // Return empty string for filtering/sorting if data is null
            }},
            { data: "rps_IntervalMinutes", title: "Interval (min)", sortable: true /*, resizeable:false */ }, // Added (min) for clarity

            // Delete button column
            { data: 'rps_ID', title: "Del", className: 'center', sortable: false, orderable: false,
                render: function(data, type) {
                    if (type === 'display') {
                        return '<div align="center" data-id="' + data + '">' + // data-id might be used by delete logic
                               '<a href="#" onclick="window.reportManager.deleteReport(' + data + ');return false;">' +
                               '<span class="ui-icon ui-icon-close" title="' + window.hWin.HR('Delete this Report') + '"></span></a></div>';
                    }
                    return data;
                }
            }
        ];

        _dataTable = $('.div_datatable').DataTable(_dataTableParams);
        $('.dataTables_filter').css({ float: 'left' }); // Adjust filter position.
    }

	/**
	 * Opens the popup dialog to add a new report schedule or edit an existing one.
	 * The dialog loads `editReportSchedule.html`.
	 *
	 * @private
	 * @param {string} [params] - URL parameters to append to the dialog URL (e.g., `?db=...&recID=...`).
	 */
	function _onAddEditRecord(params) {
		let url = window.hWin.HAPI4.baseURL + "export/publish/editReportSchedule.html";
		if (!window.hWin.HEURIST4.util.isempty(params)) {
			url = url + params;
		}

		window.hWin.HEURIST4.msg.showDialog(url, {
		    "close-on-blur": false, // Standard Heurist dialog option
			"no-resize": false,     // Standard Heurist dialog option
			height: 440,
			width: 620,
			callback: function(context) { // Called when dialog closes
				if (!window.hWin.HEURIST4.util.isnull(context)) { // context may contain data from the dialog
					// let recID = Math.abs(Number(context.data[0])); // Example of getting ID if dialog returns it
					_refreshReports(); // Refresh the DataTable to show changes.
				}
			}
		});
	}
    
    /**
     * Handles the deletion of a report schedule.
     * Shows a confirmation dialog and, if confirmed, makes an AJAX call to `loadReports.php`
     * with method `deletereport`. Updates the DataTable on successful deletion.
     *
     * @private
     * @param {number} recID - The ID of the report schedule to delete.
     */
    function _onDeleteRecord(recID) {
        window.hWin.HEURIST4.msg.showMsgDlg(
            "Do you really want to delete report schedule #" + recID + "?", // Added recID to message
            function() { // 'Yes' callback for confirmation
                /** @private */
                function _updateAfterDelete(response) {
                    if (response.status == window.hWin.ResponseStatus.OK) {
                        // Find and remove the row from DataTable
                        // Using a more robust way to find the row to delete.
                        _dataTable.rows(function (idx, data, node) {
                            return data.rps_ID === recID;
                        }).remove().draw(false);

                        window.hWin.HEURIST4.msg.showMsgFlash(
                            "Report schedule #" + recID + " was deleted.", 1000);
                    } else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }

                let baseurl = window.hWin.HAPI4.baseURL + "export/publish/loadReports.php";
                let request = { method: 'deletereport', db: window.hWin.HAPI4.database, recID: recID };
                window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, _updateAfterDelete);
            },
            { title: 'Confirm Deletion', yes: 'Continue', no: 'Cancel' } // Dialog options
        );
    }

    /**
     * Refreshes the list of reports by fetching data from the server and re-initializing the DataTable.
     * Makes an AJAX call to `loadReports.php` with method `searchreports`.
     *
     * @private
     */
    function _refreshReports() {
        /**
         * @private
         * Callback function to handle the AJAX response from `searchreports`.
         * @param {object} response - The AJAX response.
         */
        function __updateRecordsList(response) {
            if (response.status == window.hWin.ResponseStatus.OK) {
                _records = window.hWin.HEURIST4.util.isnull(response.data) ? [] : response.data;
                _initDataTable(_records);
            } else {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        }

        let baseurl = window.hWin.HAPI4.baseURL + "export/publish/loadReports.php";
        let request = { method: 'searchreports', db: window.hWin.HAPI4.database }; // No search parameters, fetches all.
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, __updateRecordsList);
    }

	// --- Public members exposed by the ReportManager instance ---
	let that = {
				/**
				 * Reinitializes the ReportManager. Typically used if parameters like usrID need to change post-instantiation.
				 *
				 * @param {number|string} [usrID] - User ID for filtering (currently not implemented for filtering).
				 * @param {function} [_callback] - Callback function for non-window mode.
				 */
				reinit : function (usrID, _callback) {
						_init(usrID, _callback);
				},

				/**
				 * Handles a cancel action, typically for closing the manager interface or a selection process.
				 * If in window mode, it closes the window. Otherwise, if a callback was provided, it's invoked.
				 * NO USED HERE
				 */
				cancel : function () {
					if (_isWindowMode) { // If operating as a separate window/popup
						window.close();
					} else if (!window.hWin.HEURIST4.util.isnull(_callback_func)) { // If embedded and callback exists
						_callback_func();
					}
				},

				/**
				 * Opens the dialog to edit an existing report schedule or create a new one.
				 *
				 * @param {number} recID - The ID of the report schedule to edit.
				 *                         If `recID` is less than 0 (or 0), it implies creating a new schedule.
				 *                         If `_keepParameters` is set (e.g. from URL), these are passed to pre-fill the new schedule form.
				 */
				editReport: function(recID) {
					_onAddEditRecord((recID <= 0 && _keepParameters) // Corrected condition for new record with params
                        ? _keepParameters
                        : "?db=" + window.hWin.HAPI4.database + "&recID=" + recID);
				},
                
                /**
                 * Initiates the deletion process for a report schedule.
                 *
                 * @param {number} recID - The ID of the report schedule to delete.
                 */
                deleteReport: function(recID) {
                    _onDeleteRecord(recID);
                }
	};

	_init();  // Initialize the manager when a new instance is created.
	return that; // Return the public interface.
}