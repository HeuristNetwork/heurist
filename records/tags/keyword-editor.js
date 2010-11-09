/* motley hack to make this code reusable ... no semblance of OO, I'm afraid ...
 * In particular, don't try having two workgroup keyword editors in the same window.
 * Why would you do that, anyway?  I don't know!  It's really up to you.
 * But if you do end up wanting to do that then you will need to fix this code.
 */

if (! window.HEURIST) window.HEURIST = {};
window.HEURIST.keywordEditor = {
	addWgTag: function(row) {
		var newInput = document.createElement("input");
			newInput.type = "hidden";
			newInput.name = "action[]";
			newInput.value = "add " + row.id;
		document.getElementById("keyword-form").appendChild(newInput);

		var kwdDetails = top.HEURIST.user.workgroupKeywords[row.id];
		row.className = "";	// address bug with csshover2
		document.getElementById("current-workgroup-tags").appendChild(row);
		row.ondblclick = function() { HEURIST.keywordEditor.removeWgTag(row); };
		row.onkeypress = function(e) {
			if (! e) e = window.event;
			if (e.keyCode == 10 || e.keyCode == 13) HEURIST.keywordEditor.removeWgTag(row);
		};
		row.onfocus = function() { window.selectedRow = this; removeButton.disabled = false; }
		row.onblur = function() { window.selectedRow = null; removeButton.disabled = true; };
		HEURIST.keywordEditor.addButton.disabled = true;

		if (top.HEURIST.edit) top.HEURIST.edit.changed("workgroups");
	},

	removeWgTag: function(row) {
		var newInput = document.createElement("input");
			newInput.type = "hidden";
			newInput.name = "action[]";
			newInput.value = "del " + row.id;
		document.getElementById("keyword-form").appendChild(newInput);

		var kwdDetails = top.HEURIST.user.workgroupKeywords[row.id];
		row.className = "";
		document.getElementById("all-workgroup-tags").appendChild(row);
		row.ondblclick = function() { HEURIST.keywordEditor.addWgTag(row); };
		row.onkeypress = function(e) {
			if (! e) e = window.event;
			if (e.keyCode == 10 || e.keyCode == 13) HEURIST.keywordEditor.addWgTag(row);
		};
		row.onfocus = function() { window.selectedRow = this; HEURIST.keywordEditor.addButton.disabled = false; }
		row.onblur = function() { window.selectedRow = null; HEURIST.keywordEditor.addButton.disabled = true; };
		HEURIST.keywordEditor.removeButton.disabled = true;

		if (top.HEURIST.edit) top.HEURIST.edit.changed("workgroups");
	},

	tabIndex: 100,
	addKeywordToList: function(list, kwdID, groupID, kwdName) {
		if (! top.HEURIST.workgroups[groupID]) {
			alert(groupID);
			alert(top.HEURIST.workgroups.length);
			console.log(top.HEURIST.workgroups);
		}

		var kwdRow = document.createElement("tr");
			kwdRow.id = kwdID;
			kwdRow.tabIndex = ++HEURIST.keywordEditor.tabIndex;
			kwdRow.onclick = function() { kwdRow.focus(); };
			kwdRow.onselectstart = function() { return false; };

		var groupCell = kwdRow.appendChild(document.createElement("td"));
			groupCell.className = "group-name";
			groupCell.appendChild(document.createTextNode( top.HEURIST.workgroups[groupID].name ));
		var kwdCell = kwdRow.appendChild(document.createElement("td"));
			kwdCell.appendChild(document.createTextNode(kwdName));

		return list.appendChild(kwdRow);
	},

	KeywordEditor: function(currWgTagsElt, allWgTagsElt, addButton, removeButton) {
		var kwdDetailsById = top.HEURIST.user.workgroupKeywords;
		var kwdOrder = top.HEURIST.user.workgroupKeywordOrder;
		var wKwdIds = parent.HEURIST.record? parent.HEURIST.record.workgroupKeywords : [];

		HEURIST.keywordEditor.addButton = addButton;
		HEURIST.keywordEditor.removeButton = removeButton;

		var wgs = top.HEURIST.workgroups;
		var usedKwdIds = {};
		for (var i=0; i < wKwdIds.length; ++i) {
			var kwdDetails = kwdDetailsById[ wKwdIds[i] ];
			if (! kwdDetails) continue;	// workgroup-keyword for a workgroup this user isn't in

			var newRow = HEURIST.keywordEditor.addKeywordToList(currWgTagsElt, wKwdIds[i], kwdDetails[0], kwdDetails[1]);
				newRow.ondblclick = function(row) { return function() { HEURIST.keywordEditor.removeWgTag(row); }; }(newRow);
				newRow.onkeypress = function(row) { return function(e) {
					if (! e) e = window.event;
					if (e.keyCode == 10 || e.keyCode == 13) HEURIST.keywordEditor.removeWgTag(row);
				} }(newRow);
				newRow.onfocus = function() { window.selectedRow = this; HEURIST.keywordEditor.removeButton.disabled = false; }
				newRow.onblur = function() { window.selectedRow = null; HEURIST.keywordEditor.removeButton.disabled = true; };
			usedKwdIds[ wKwdIds[i] ] = true;
		}

		for (var i = 0; i < kwdOrder.length; ++i) {
			wKwdId = kwdOrder[i];
			if (usedKwdIds[wKwdId]) continue;

			var kwdDetails = kwdDetailsById[ wKwdId ];
			var newRow = HEURIST.keywordEditor.addKeywordToList(allWgTagsElt, wKwdId, kwdDetails[0], kwdDetails[1]);
				newRow.ondblclick = function(row) { return function() { HEURIST.keywordEditor.addWgTag(row); }; }(newRow);
				newRow.onkeypress = function(row) { return function(e) {
					if (! e) e = window.event;
					if (e.keyCode == 10 || e.keyCode == 13) HEURIST.keywordEditor.addWgTag(row);
				} }(newRow);
				newRow.onfocus = function() { window.selectedRow = this; addButton.disabled = false; };
				newRow.onblur = function() { window.selectedRow = null; addButton.disabled = true; };
		}
	}
};

top.HEURIST.fireEvent(window, "heurist-keyword-editor-js-loaded");
