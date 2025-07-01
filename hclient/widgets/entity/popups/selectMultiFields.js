/**
* @file selectMultiFields.js
* @brief Provides a UI for rapidly adding multiple base fields to a Record Type structure.
* @fileOverview This file defines the HRapidFieldAdditions class, which powers a user interface enabling the quick selection and addition of multiple existing base fields (DetailTypes) to a specified Record Type's structure. It's designed to streamline the process of building out record type definitions.
* @project     Heurist academic knowledge management system
* @package  hclient\widgets\entity\popups
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay <blmckay13@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * @class HRapidFieldAdditions
 * @brief UI component for rapidly adding multiple existing base fields to a Record Type.
 * @description This class provides the logic and UI management for a tool that allows users
 * to quickly select from a list of all available base fields (DetailTypes) in the system
 * and add them to the structure of a specified Record Type. It features a tabbed interface
 * grouped by DetailTypeGroup, search functionality, and visual indication of fields already
 * present in the target Record Type.
 *
 * @property {?number} rty_ID The ID of the current Record Type to which fields will be added.
 * @property {Array<number|string>} assigned_fields An array storing the IDs of DetailTypes already assigned to the current Record Type.
 * @property {Array<number|string>} selected_fields An array to store the IDs of DetailTypes selected by the user in the UI for addition.
 * @property {Array<Array<any>>} all_fields An array containing data for all available base fields in the system,
 *                                         structured as `[ [dty_id, dty_name, [rst_name1, rst_name2, ...]], ... ]`,
 *                                         where `rst_name` refers to instances where the base field is used in other record types.
 * @property {?jQuery} tab_container jQuery object representing the main container for the tabbed interface displaying field groups.
 * @property {?jQuery} btn_action jQuery object for the primary action button (e.g., "Insert selected fields").
 * @property {?jQuery} btn_close jQuery object for the close/cancel button.
 */
class HRapidFieldAdditions{

	rty_ID = null;

	assigned_fields = [];
	selected_fields = [];

	all_fields = [];

	tab_container = null;
	btn_action = null;
	btn_close = null;

	/**
	 * @brief Constructs an HRapidFieldAdditions instance.
	 * @memberof HRapidFieldAdditions
	 * @param {number} _rty_ID The ID of the Record Type to which fields will be added.
	 * If `_rty_ID` is not provided, an error message is shown, and the window may be closed.
	 */
	constructor(_rty_ID){

		if(window.hWin.HEURIST4.util.isempty(_rty_ID)){
			window.hWin.HEURIST4.msg.showMsgErr({
				message: 'A record type is required to use this tool',
				error_title: 'Missing record type'
			});

			window.close();
			return;
		}

		this.rty_ID = _rty_ID;
	}

	/**
	 * @brief Initializes the main functionality of the HRapidFieldAdditions UI.
	 * @memberof HRapidFieldAdditions
	 * Calls methods to set up internal variables, get already assigned fields,
	 * populate the base fields display, initialize UI elements, and apply styling.
	 */
	init(){

		this.setupVariables();

		this.getAssignedFields();

		this.populateBaseFields();

		this.setupElements();

		this.setupStyling();
	}

	/**
	 * @brief Sets up initial class variables and retrieves all base fields.
	 * @memberof HRapidFieldAdditions
	 * Initializes `all_fields` by fetching all base field instances from the database,
	 * excluding those from the current `rty_ID`. Sorts the record types alphabetically
	 * before fetching their field instances. Initializes jQuery references for `tab_container`,
	 * `btn_action`, and `btn_close`.
	 */
	setupVariables(){

		let rectypes = $Db.rty().getIds();
		let idx = rectypes.indexOf(this.rty_ID);

		if(idx >= 0) { rectypes.splice(idx, 1); }

		rectypes.sort((a, b) => {

			a = $Db.rty(a, 'rty_Name');
			b = $Db.rty(b, 'rty_Name');

			return this.alphabeticSort(a, b);
		});

		this.all_fields = $Db.getBaseFieldInstances(rectypes, 0, 'all', []);

		this.tab_container = $('.tabs');

		this.btn_action = $('#btnAddSelected');
		this.btn_close = $('#btnClose');
	}

	/**
	 * @brief Sets up UI elements and event handlers.
	 * @memberof HRapidFieldAdditions
	 * Initializes the jQuery UI Tabs for the `tab_container`.
	 * Sets up the main action button (`btn_action`) to collect checked fields and close the window.
	 * Sets up the close button (`btn_close`).
	 * Initializes the text search input (`#field_search`) for filtering fields.
	 */
	setupElements(){

		this.tab_container.tabs({
			beforeActivate: function(e, ui){
				if(window.hWin.HEURIST4.util.isempty(ui.newPanel) || ui.newPanel.length == 0) {
					e.preventDefault();
				}
			}
		});

		// Initialise Buttons
		this.btn_action.addClass('ui-button-action')
			.button({label:'Insert selected fields'})
			.on('click', () => {

				this.getCheckedFields();

				if(window.hWin.HEURIST4.util.isempty(this.selected_fields)){
					window.hWin.HEURIST4.msg.showMsgErr({
						message: 'No fields have been selected',
						error_title: 'Missing fields'
					});
					return;
				}
				else{
					window.close(this.selected_fields);
				}
			});

		this.btn_close.button({label:'Close'}).on('click', () => {window.close();});

		// Initialise Text Searching
		$('#field_search').on('keyup', () => { this.searchBaseField(); });
	}

	/**
	 * @brief Applies CSS styling to specific UI elements.
	 * @memberof HRapidFieldAdditions
	 * Styles the action button and the close button.
	 */
	setupStyling(){

		this.btn_action.css({'font-size':'1em', 'float':'right', 'color':'white', 'background':'#3D9946 0% 0% no-repeat padding-box'});

		this.btn_close.css({'font-size':'1em', 'float':'right'});
	}

	/**
	 * @brief Retrieves the list of DetailType IDs already assigned to the current Record Type.
	 * @memberof HRapidFieldAdditions
	 * Populates `this.assigned_fields` with the IDs of fields present in the
	 * structure of the current `this.rty_ID`. These fields will be marked as
	 * disabled/checked in the selection UI.
	 */
	getAssignedFields(){

		let recset = $Db.rst(this.rty_ID);

		if(window.hWin.HEURIST4.util.isempty(recset)){ return; } // skip if there are no base fields

		this.assigned_fields = recset.getIds();
	}

	/**
	 * @brief Populates the tabbed interface with available base fields, grouped by DetailTypeGroup.
	 * @memberof HRapidFieldAdditions
	 * Iterates through DetailTypeGroups (excluding "Trash"). For each group:
	 *  - Creates a new tab.
	 *  - Fetches all DetailTypes belonging to that group.
	 *  - Sorts these DetailTypes alphabetically.
	 *  - For each DetailType, creates a checkbox item showing its name, type, and help text.
	 *  - If a DetailType is already in `this.assigned_fields`, its checkbox is disabled and checked.
	 * Sets up click handlers for field items to toggle their checkboxes.
	 */
	populateBaseFields(){

		let that = this;
		let a_list = $('.tabs-list');

		$Db.dtg().each2(function(gID, group){
			let arr = [];

			if(group['dtg_Name'] == 'Trash') { return; }

			// Create Grouping
			a_list.append(`<li class="tabs-items"><a href="#${gID}" class="no-overflow-item tabs-text">${group['dtg_Name']}</a></li>`);

			let tab_page = `<div id="${gID}" style="border:1px solid lightgrey;background:#C9BFD4;height:540px;">`
				+ `<div class="tabs-desc no-overflow-item">${group['dtg_Description']}</div><hr style="margin-bottom:5.5px;"/><div class="field-group">`;

			// Get all Base Fields belonging to this group
			$Db.dty().each2(function(dID, field){

				if(field['dty_DetailTypeGroupID'] == gID){
					let type = that.getTypeName(field['dty_Type']);

					arr.push([dID, field['dty_Name'], type, field['dty_HelpText']]);
				}
			});

			arr.sort(that.alphabeticSort);

			/*
			arr:
				0 => ID
				1 => Label/Name
				2 => Type
				3 => Help Text/Additional Info
			*/
			// Display Base Fields
			for(let i = 0; i < arr.length; i++){

				tab_page += '<div class="field-container">';

				let is_checked = that.isInArray(arr[i][0], that.assigned_fields, false);
				tab_page += `<input type="checkbox" data-id="${arr[i][0]}" ${is_checked ? 'disabled checked="checked"' : ''}>`;

				tab_page += `<div class="field-item no-overflow-item" title="${arr[i][1]}">${arr[i][1]}</div>`
					+ `<div class="field-item no-overflow-item" title="${arr[i][2]}">${arr[i][2]}</div>`
					+ `<div class="field-item no-overflow-item" title="${arr[i][3]}">${that.stripNewlines(arr[i][3])}</div></div>`;

			}

			tab_page += '</div></div>';

			that.tab_container.append(tab_page);
		});

		this.tab_container.on('click', function(e){

			let ele = $(e.target);

			if(ele.is('.field-group, .tabs-desc, input, div[role="tabpanel"], a, ul, li')){
				return;
			}

			let cb = $(ele.parent('div').find('input')[0]);
			
			if(!cb.prop('disabled')){
				cb.trigger('click');
			}
		});
	}

	/**
	 * @brief Retrieves the list of DetailType IDs selected by the user from the checkboxes.
	 * @memberof HRapidFieldAdditions
	 * Populates `this.selected_fields` with the `data-id` attribute of all
	 * checked and enabled input checkboxes within the `tab_container`.
	 */
	getCheckedFields(){

		let checked_opts = this.tab_container.find('input:checked').not(':disabled');
		let cnt = checked_opts.length;

		for(let i = 0; i < cnt; i++){
			this.selected_fields.push($(checked_opts[i]).attr('data-id')); // Get each field's ID
		}
	}

	/**
	 * @brief Filters and displays base fields based on user input in the search box.
	 * @memberof HRapidFieldAdditions
	 * @returns {boolean|undefined} False if the search field is empty, otherwise undefined.
	 * Implements a live search functionality:
	 *  - Hides the results dropdown if the search term is too short (<= 2 chars).
	 *  - Iterates through `this.all_fields`.
	 *  - If a base field's name or any of its instance names (rst_name) match the search term
	 *    (case-insensitive) and it's not already assigned, it's added to the results dropdown.
	 *  - Results are clickable; clicking a result checks the corresponding checkbox in the tabs
	 *    and shows a confirmation message.
	 */
	searchBaseField(){

		let search_field = $('#field_search');
		let search_container = $('.field_search_container');
		let result_container = $('#field_result');

		let searched = search_field.val().toLowerCase();

		let has_result = false;

		if(search_field.length == 0){
			return false;
		}

		if(result_container.length == 0){ // Create result container

			result_container = $('<div>', {id: 'field_result'}).appendTo(search_container);

			$(document).on('click', function(e){
				if(!$(e.target).is('#field_result') && $(e.target).parents('#field_result').length == 0){
					result_container.hide();
				}
			});
		}

		if(searched.length <= 2){
			result_container.hide();
			return;
		}

		// Begin Search
		result_container.empty();

		// For instances where the entered value has an exact match
		let first_entry = $('<div>', {class: 'no-overflow-item'}).appendTo(result_container);

		// Ensure there are fields to compare against
		if(this.all_fields.length == 0){
			result_container.hide();
			return;
		}

		for(const dty_field of this.all_fields){

			const name = dty_field[1];
			const id = dty_field[0];

			// Check if there is a customised instance with the search string
			const in_other_array = this.isInArray(searched, dty_field[2], true);

			if(this.isInArray(id, this.assigned_fields, false) || (name.toLowerCase().indexOf(searched) == -1 && !in_other_array)){
				continue;
			}

			let main_ele;

			if(name.toLowerCase() == searched || in_other_array == true){   
				main_ele = first_entry;
			}else{
				main_ele = $('<div>', {class: 'no-overflow-item'}).appendTo(result_container);
			}

			// Add original base field for search
			main_ele
				.attr({'d-id': id, title: name})
				.text(name)
				.on('click', (e) => {

					let id = $(e.target).attr('d-id');
					let name = $(e.target).text();

					let cb = this.tab_container.find(`input[data-id="${id}"]`);

					if(cb.length > 0) {
						cb.prop('checked', true);

						window.hWin.HEURIST4.msg.showMsgFlash(`Checked ${name}`, 5000);
					}else{
						window.hWin.HEURIST4.msg.showMsgErr({
							message: `An error has occurred with the selection of base field ${name} (${id})`,
							error_title: 'Invalid base field',
							status: window.hWin.ResponseStatus.UNKNOWN_ERROR									
						});
					}

					result_container.hide();
				});

			for(const rst_name of dty_field[2]) {

				let sub_ele = $('<div>', {class: 'no-overflow-item sub-text'}).appendTo(result_container);

				// Add customised version of base field
				sub_ele
				.attr({'d-id': id, title: `${name} (${rst_name})`, 'd-name': name})
				.html(`&nbsp;${rst_name}`)
				.on('click', (e) => {

					let id = $(e.target).attr('d-id');
					let name = $(e.target).attr('d-name');
					let sel_name = $(e.target).text();

					let cb = this.tab_container.find(`input[data-id="${id}"]`);

					if(cb.length > 0) {
						cb.prop('checked', true);

						window.hWin.HEURIST4.msg.showMsgFlash(`Checked ${name} (${sel_name})`, 5000);
					}else{
						window.hWin.HEURIST4.msg.showMsgErr({
							message: `An error has occurred with the selection of base field ${sel_name} (${id} => ${name})`,
							error_title: 'Invalid base field',
							status: window.hWin.ResponseStatus.UNKNOWN_ERROR
						});
					}

					result_container.hide();
				});
			}

			result_container.append('<div style="margin-bottom: 5px;">----------------------------------------</div>');

			has_result = true;
		}

		if(has_result) {
			result_container
			.css({
				'width': '530px', 
				'position': 'absolute',
				'top': '20px',
				'right': 0 
			})
			.show();
		}else{
			result_container.hide();
		}
	}

	/**
	 * @brief Assigns more standard, user-friendly names to internal DetailType type strings.
	 * @memberof HRapidFieldAdditions
	 * @param {string} type - The internal DetailType type string (e.g., 'freetext', 'resource').
	 * @returns {string} A more user-friendly name for the field type (e.g., 'Single line Text', 'Record pointer').
	 */
	getTypeName(type){

		if(window.hWin.HEURIST4.util.isempty(type)){
			return "Unknown";
		}

		switch (type){

			case 'resource':
				type = 'Record pointer';
				break;

			case 'relmarker':
				type = 'Relationship marker';				
				break;

			case 'freetext':
				type = 'Single line Text';				
				break;

			case 'blocktext':
				type = 'Multi-line Text';				
				break;

			case 'float':
				type = 'Number';				
				break;

			case 'enum':
				type = 'Terms list';				
				break;

			case 'date':
				type = 'Date/Time';				
				break;

			case 'separator':
				type = 'Tab header';				
				break;

			case 'geo':
				type = 'Geospatial';
				break;

			case 'calculated':
				type = 'Calculated';
				break;

			default:
				type = type = type.charAt(0).toUpperCase() + type.slice(1);

				break;
		}

		return type;
	}

	/**
	 * @brief Sorts an array of items alphabetically based on a specific element if items are arrays.
	 * @memberof HRapidFieldAdditions
	 * @param {Array|string} a The first item to compare. If an array, `a[1]` is used for comparison.
	 * @param {Array|string} b The second item to compare. If an array, `b[1]` is used for comparison.
	 * @returns {number} -1 if `a` comes before `b`, 1 if `a` comes after `b`, 0 if equal.
	 * Performs a case-insensitive alphabetic sort.
	 */
	alphabeticSort(a, b){

		if(a.constructor === Array && b.constructor === Array) {
			a = a[1];
			b = b[1];
		}else if(a.constructor === Array || b.constructor === Array) {
			return 0;
		}

		let min_len = Math.min(a.length, b.length);
		let i = 0;

		for(; i < min_len; i++){

			let c = a[i].toUpperCase();
            let d = b[i].toUpperCase();

            if (c < d) {
                return -1;
            }
            if (c > d) {
                return 1;
            }
        }

        if(window.hWin.HEURIST4.util.isempty(a[i])){
        	return -1;
        }else if(window.hWin.HEURIST4.util.isempty(b[i])){
        	return 1;
        }else{
        	return 0;
        }
	}

	/**
	 * @brief Removes newline characters and `<br>` tags from a string, replacing them with spaces.
	 * @memberof HRapidFieldAdditions
	 * @param {string} text The input string.
	 * @returns {string} The string with newline characters and `<br>` tags replaced by spaces.
	 */
	stripNewlines(text){
		return text.replaceAll(/\n|\r|<br>/g, ' ');
	}

	/**
	 * @brief Checks if a 'needle' string exists within a 'haystack' array of strings.
	 * @memberof HRapidFieldAdditions
	 * @param {string} needle The string to search for.
	 * @param {string[]} haystack The array of strings to search within.
	 * @param {boolean} check_partial If true, checks for partial matches (if `needle` is a substring of any element in `haystack`).
	 * @returns {boolean|number} If `check_partial` is false, returns true if `needle` is found, false otherwise.
	 *                             If `check_partial` is true, returns the index of the first partial match if found, otherwise false.
	 */
	isInArray(needle, haystack, check_partial){

		let idx = haystack.indexOf(needle);

		if(!check_partial){
			return idx >= 0;
		}

		if(idx >= 0){
			return true;
		}

		// Check for partial match
		for(let i in haystack){
			idx = haystack[i].indexOf(needle);

			if(idx >= 0){
				return idx;
			}
		}

		return false;
	}
}