/**
*  Quick addition of fields to a record type
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

var assigned_fields = [];
var selected_fields = [];

/*
 * Assign more standard names to dty_Types
 *
 * Param: (string) type => detail type's Type
 * 
 * Return: (string) Type Name
 */
function getTypeName(type) {
	if(type == 'relmarker'){ type = 'Relationship Marker'; }
	else if(type == 'freetext'){ type = 'Single line Text'; }
	else if(type == 'blocktext'){ type = 'Multi-line Text'; }
	else if(type == 'float'){ type = 'Number'; }
	else if(type == 'enum'){ type = 'Terms list'; }
	else if(type == 'resource'){ type = 'Relationship pointer'; }
	else if(type == 'date'){ type = 'Date/Time'; }
	else if(type == 'separator') { type = 'Tab header'; }
	else{ // Other Type, capitalise first letter
		type = type.charAt(0).toUpperCase() + type.slice(1);
	}

	return type;
}

/*
 * Retrieve dty_Type using it's ID before continuing
 *
 * Param: (int) id => detail type's ID
 *
 * Return: (string) response from getTypeName()
 */
function getTypeById(id){
	return getTypeName($Db.dty(id, 'dty_Type'));
}

/*
 * Get list of already assigned fields, to disable from master list
 *
 * Param: (int) rty_ID => current record type's ID
 *
 * Return: VOID
 */
function getAssignedFields(rty_ID) {

	var recset = $Db.rst(rty_ID);

	if(window.hWin.HEURIST4.util.isempty(recset)){ return; } // skip if there are no base fields

	recset.each2(function(fID, fields){ // proceed through all fields and record each id
		assigned_fields.push(fID);
	});
}

/*
 * Retrieve all existing base fields, grey out/disable options already assigned
 *
 * Param: NONE
 *
 * Return: VOID
 */
function populateBaseFields() {
	var a_list = $('.tabs-list');
	var tabs_container = $('.tabs');

	$Db.dtg().each2(function(gID, group){
		var arr = [];

		if(group['dtg_Name'] == 'Trash') { return; }

		a_list.append('<li class="tabs-items"><a href="#'+ gID +'" class="no-overflow-item tabs-text">'+ group['dtg_Name'] +'</a></li>');

		var tab_page = '<div id="'+ gID +'" style="border:1px solid lightgrey;background:#C9BFD4;height:540px;">'
			+ '<div class="tabs-desc no-overflow-item">'+ group['dtg_Description'] +'</div><hr style="margin-bottom:5.5px;"/><div class="field-group">';

		$Db.dty().each2(function(dID, field){
			var type;

		    if(field['dty_DetailTypeGroupID'] == gID){
		    	var type = getTypeName(field['dty_Type']);

		        tab_page = tab_page + '<div class="field-container">';

		        if(jQuery.inArray(dID, assigned_fields) === -1){
			        tab_page = tab_page + '<input type="checkbox" data-id="'+ dID +'" style="margin-left: 10px;">';
		        }
		        else{
		        	tab_page = tab_page + '<input type="checkbox" data-id="'+ dID +'" style="margin-left: 10px;" disabled>';		        	
		        }

		        tab_page = tab_page 
		        	+ '<div class="field-item no-overflow-item">'+ field['dty_Name'] +'</div>'
		        	+ '<div class="field-item">'+ type +'</div>'
		        	+ '<div class="field-item no-overflow-item">'+ field['dty_HelpText'] +'</div></div>';
		    }
		});

		tab_page = tab_page + '</div></div>';

		tabs_container.append(tab_page);
	});
}

/*
 * Retrieve full list of checked fields to send back
 *
 * Param: NONE
 *
 * Return: VOID
 */
function getCheckedFields(){
	var tabs_container = $('.tabs');

	var checked_opts = tabs_container.find('input:checked');
	var cnt = checked_opts.length;

	for(var i = 0; i < cnt; i++){
		selected_fields.push($(checked_opts[i]).attr('data-id')); // Get each field's ID
	}
}

/*
 * Initialise function, called from within html
 *
 * Param: NONE
 *
 * Return: VOID
 */
function _init() {
	var rtyID = window.hWin.HEURIST4.util.getUrlParameter('rtyID', location.search);

	if(!window.hWin.HEURIST4.util.isempty(rtyID)){

		getAssignedFields(rtyID);

		populateBaseFields();

		$('.tabs').tabs();
	}
	else{
		window.hWin.HEURIST4.msg.showMsgErr("This tool requires you to be editing a record's structure.");
		window.close();
	}

	// Initialise Buttons
	var btnSave = $('#btnAddSelected').button({label:'Insert selected fields'});
	btnSave.on('click', 
		function(){
			getCheckedFields();

			if(window.hWin.HEURIST4.util.isempty(selected_fields)){
				window.hWin.HEURIST4.msg.showMsgErr('No fields have been selected');
				return;
			}
			else{
				window.close(selected_fields);
			}
		});

	btnSave.addClass('ui-button-action').css({'font-size':'1em', 'float':'right', 'color':'white', 'background':'#3D9946 0% 0% no-repeat padding-box'});

	$('#btnClose').button({label:'Close'}).on('click', 
		function(){
			window.close();
		});

	$('#btnClose').css({'font-size':'1em', 'float':'right'});
}