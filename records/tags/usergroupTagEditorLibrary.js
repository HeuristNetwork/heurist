/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* motley hack to make this code reusable ... no semblance of OO, I'm afraid ...
* In particular, don't try having two workgroup tag editors in the same window.
* Why would you do that, anyway?  I don't know!  It's really up to you.
* But if you do end up wanting to do that then you will need to fix this code
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


if (! window.HEURIST) window.HEURIST = {};
window.HEURIST.wgTagEditor = {

	// add tag to selected list
	addWgTag: function(row) {

		var newInput = document.createElement("input");
			newInput.type = "hidden";
			newInput.name = "action[]";
			newInput.value = "add " + row.id;
		document.getElementById("wgtag-form").appendChild(newInput);

		var kwdDetails = top.HEURIST.user.workgroupTags[row.id];
		row.className = "";	// address bug with csshover2
		HEURIST.wgTagEditor.currWgTagsElt.appendChild(row);
		row.ondblclick = function() { HEURIST.wgTagEditor.removeWgTag(row); };
		row.onkeypress = function(e) {
			if (! e) e = window.event;
			if (e.keyCode == 10 || e.keyCode == 13) HEURIST.wgTagEditor.removeWgTag(row);
		};
		row.onfocus = function() { window.selectedRow = this; removeButton.disabled = false; }
		row.onblur = function() { window.selectedRow = null; removeButton.disabled = true; };
		HEURIST.wgTagEditor.addButton.disabled = true;


		HEURIST.wgTagEditor.selectedTags.push(row.id);

		if (top.HEURIST.edit) top.HEURIST.edit.changed("workgroups");
	},

	// remove tag from selected list
	removeWgTag: function(row) {
		var newInput = document.createElement("input");
			newInput.type = "hidden";
			newInput.name = "action[]";
			newInput.value = "del " + row.id;
		document.getElementById("wgtag-form").appendChild(newInput);

		var kwdDetails = top.HEURIST.user.workgroupTags[row.id];
		row.className = "";
		HEURIST.wgTagEditor.allWgTagsElt.appendChild(row);
		row.ondblclick = function() { HEURIST.wgTagEditor.addWgTag(row); };
		row.onkeypress = function(e) {
			if (! e) e = window.event;
			if (e.keyCode == 10 || e.keyCode == 13) HEURIST.wgTagEditor.addWgTag(row);
		};
		row.onfocus = function() { window.selectedRow = this; HEURIST.wgTagEditor.addButton.disabled = false; }
		row.onblur = function() { window.selectedRow = null; HEURIST.wgTagEditor.addButton.disabled = true; };
		HEURIST.wgTagEditor.removeButton.disabled = true;


		var k = HEURIST.wgTagEditor.selectedTags.indexOf(row.id);
		if(k>=0){
			HEURIST.wgTagEditor.selectedTags.splice(k,1);
		}

		if (top.HEURIST.edit) top.HEURIST.edit.changed("workgroups");
	},

	tabIndex: 100,
	// create row and append it to table
	addWgTagToList: function(list, kwdID, groupID, kwdName) {
		if (! top.HEURIST.workgroups[groupID]) {
			alert(groupID);
			alert(top.HEURIST.workgroups.length);
			console.log(top.HEURIST.workgroups);
		}

		var kwdRow = document.createElement("tr");
			kwdRow.id = kwdID;
			kwdRow.tabIndex = ++HEURIST.wgTagEditor.tabIndex;
			kwdRow.onclick = function() { kwdRow.focus(); };
			kwdRow.onselectstart = function() { return false; };

		var groupCell = kwdRow.appendChild(document.createElement("td"));
			groupCell.className = "group-name";
			groupCell.appendChild(document.createTextNode( top.HEURIST.workgroups[groupID].name ));
		var kwdCell = kwdRow.appendChild(document.createElement("td"));
			kwdCell.appendChild(document.createTextNode(kwdName));

		return list.appendChild(kwdRow);
	},

	clearList: function(list) {
		while( list.hasChildNodes && list.lastChild )
		{
			list.removeChild(list.lastChild);
		}
	},

	//filld table with rows
	reloadTags: function() {

		HEURIST.wgTagEditor.clearList(HEURIST.wgTagEditor.currWgTagsElt);
		HEURIST.wgTagEditor.clearList(HEURIST.wgTagEditor.allWgTagsElt);


		var kwdDetailsById = top.HEURIST.user.workgroupTags;
		var kwdOrder = top.HEURIST.user.workgroupTagOrder;
		if(!HEURIST.wgTagEditor.selectedTags) {
			HEURIST.wgTagEditor.selectedTags = parent.HEURIST.edit.record? parent.HEURIST.edit.record.workgroupTags : [];
		}
		var wKwdIds = HEURIST.wgTagEditor.selectedTags;

		var wgs = top.HEURIST.workgroups;
		var usedKwdIds = {}; //already selected

		//fill current list
		for (var i=0; i < wKwdIds.length; ++i) {
			var kwdDetails = kwdDetailsById[ wKwdIds[i] ];
			if (! kwdDetails) continue;	// workgroup-tag for a workgroup this user isn't in

			var newRow = HEURIST.wgTagEditor.addWgTagToList(HEURIST.wgTagEditor.currWgTagsElt, wKwdIds[i], kwdDetails[0], kwdDetails[1]);
				newRow.ondblclick = function(row) { return function() { HEURIST.wgTagEditor.removeWgTag(row); }; }(newRow);
				newRow.onkeypress = function(row) { return function(e) {
					if (! e) e = window.event;
					if (e.keyCode == 10 || e.keyCode == 13) HEURIST.wgTagEditor.removeWgTag(row);
				} }(newRow);
				newRow.onfocus = function() { window.selectedRow = this; HEURIST.wgTagEditor.removeButton.disabled = false; }
				newRow.onblur = function() { window.selectedRow = null; HEURIST.wgTagEditor.removeButton.disabled = true; };
			usedKwdIds[ wKwdIds[i] ] = true;
		}

		//fill list of all tags except selected
		for (var i = 0; i < kwdOrder.length; ++i) {
			wKwdId = kwdOrder[i];
			if (usedKwdIds[wKwdId]) continue; //already selected

			var kwdDetails = kwdDetailsById[ wKwdId ];
			var newRow = HEURIST.wgTagEditor.addWgTagToList(HEURIST.wgTagEditor.allWgTagsElt, wKwdId, kwdDetails[0], kwdDetails[1]);
				newRow.ondblclick = function(row) { return function() { HEURIST.wgTagEditor.addWgTag(row); }; }(newRow);
				newRow.onkeypress = function(row) { return function(e) {
					if (! e) e = window.event;
					if (e.keyCode == 10 || e.keyCode == 13) HEURIST.wgTagEditor.addWgTag(row);
				} }(newRow);
				newRow.onfocus = function() { window.selectedRow = this; addButton.disabled = false; };
				newRow.onblur = function() { window.selectedRow = null; addButton.disabled = true; };
		}
	},

	WgTagEditor: function(currWgTagsElt, allWgTagsElt, addButton, removeButton)
	{
		HEURIST.wgTagEditor.addButton = addButton;
		HEURIST.wgTagEditor.removeButton = removeButton;
		HEURIST.wgTagEditor.currWgTagsElt = currWgTagsElt;
		HEURIST.wgTagEditor.allWgTagsElt = allWgTagsElt;

		HEURIST.wgTagEditor.selectedTags =  parent.HEURIST.edit.record? parent.HEURIST.edit.record.workgroupTags : [];

		HEURIST.wgTagEditor.reloadTags();
	}
};

top.HEURIST.fireEvent(window, "heurist-wgtag-editor-js-loaded");
